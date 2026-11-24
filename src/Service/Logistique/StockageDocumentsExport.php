<?php

declare(strict_types=1);

namespace App\Service\Logistique;

use App\Service\Documents\OptimiseurDocuments;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Stockage prive : aucun acces direct HTTP, noms aleatoires, lecture par controleur autorise. */
final class StockageDocumentsExport
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private string $projet, private OptimiseurDocuments $optimiseur) {}

    public function enregistrer(UploadedFile $fichier): array
    {
        if (!$fichier->isValid()) throw new \InvalidArgumentException('Echec de transfert du document (code PHP ' . $fichier->getError() . ').');
        if ($fichier->getSize() <= 0 || $fichier->getSize() > 20 * 1024 * 1024) throw new \InvalidArgumentException('Document vide ou superieur a 20 Mo.');
        $extension = strtolower(pathinfo($fichier->getClientOriginalName(), PATHINFO_EXTENSION));
        $mimes = [
            'pdf' => ['application/pdf'], 'doc' => ['application/msword', 'application/vnd.ms-office', 'application/x-ole-storage'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/x-ole-storage'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
            'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp'],
        ];
        $mime = $fichier->getMimeType();
        if (!isset($mimes[$extension]) || !in_array($mime, $mimes[$extension], true)) throw new \InvalidArgumentException('Format ou contenu du document non autorise.');
        // Une archive generique renommee .docx/.xlsx n'est pas un document Office.
        if (in_array($extension, ['docx', 'xlsx'], true)) {
            if (!class_exists(\ZipArchive::class)) throw new \InvalidArgumentException('Activez PHP zip pour verifier les documents Office.');
            $zip = new \ZipArchive();
            if ($zip->open($fichier->getPathname()) !== true) throw new \InvalidArgumentException('Document Office invalide.');
            try {
                $cle = $extension === 'docx' ? 'word/document.xml' : 'xl/workbook.xml';
                if ($zip->locateName('[Content_Types].xml') === false || $zip->locateName($cle) === false) throw new \InvalidArgumentException('Le fichier ne correspond pas au format Office annonce.');
            } finally { $zip->close(); }
        }
        $dossier = $this->projet . '/var/documents_export/';
        if (!is_dir($dossier) && !@mkdir($dossier, 0770, true) && !is_dir($dossier)) throw new \RuntimeException('Creation du stockage documentaire impossible.');
        $nom = bin2hex(random_bytes(16)) . '.' . $extension;
        $original = mb_substr(str_replace(["\r", "\n", '/', '\\'], '_', $fichier->getClientOriginalName()), 0, 255);
        $fichier->move($dossier, $nom);
        try { $this->optimiseur->optimiser($dossier . $nom); } catch (\Throwable $e) { @unlink($dossier . $nom); throw $e; }
        return ['fichier' => $nom, 'nom' => $original];
    }

    public function chemin(?string $nom): ?string
    {
        if (!$nom || !preg_match('/^[a-f0-9]{32}\.(pdf|docx?|xlsx?|jpe?g|png|webp)$/D', $nom)) return null;
        $p = $this->projet . '/var/documents_export/' . $nom;
        return is_file($p) ? $p : null;
    }

    public function supprimer(?string $nom): void
    {
        $p = $this->chemin($nom);
        if ($p) @unlink($p);
    }
}
