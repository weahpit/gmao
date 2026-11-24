<?php

declare(strict_types=1);

namespace App\Service\Logistique;

use App\Entity\Logistique\{Dossier, DocsDossier, ModeleDoc, DocType, Client};
use App\Entity\Production\Produit;
use Doctrine\ORM\EntityManagerInterface;

/** Un modele client/produit remplace le modele standard du produit ; instantane par dossier. */
final class DocumentsExport
{
    public const STATUTS = ['APPROVE' => 'APPROVE', 'NON_APPROVE' => 'NON APPROVE', 'POUR_APPROBATION' => 'POUR APPROBATION'];

    public function __construct(private EntityManagerInterface $em) {}

    public function liste(Produit $produit, Client $client): array
    {
        $repo = $this->em->getRepository(ModeleDoc::class);
        $modele = $repo->findOneBy(['code_produit' => $produit, 'code_client' => $client], ['id' => 'ASC']);
        if (!$modele) $modele = $repo->findOneBy(['code_produit' => $produit, 'code_client' => null, 'standard' => true], ['id' => 'ASC']);
        $documents = [];
        if ($modele) {
            foreach ($modele->getDetailsModeleDocs() as $detail) {
                $type = $detail->getCodeDoc();
                if ($type) $documents[(string) $type->getId()] = ['id' => (string) $type->getId(), 'libelle' => $type->getLibelle()];
            }
        } else {
            foreach ($produit->getDocTypes() as $type) $documents[(string) $type->getId()] = ['id' => (string) $type->getId(), 'libelle' => $type->getLibelle()];
        }
        return ['modele' => $modele, 'documents' => array_values($documents), 'personnalise' => $modele?->getCodeClient() !== null];
    }

    /** Initialisation non destructive : ne remplace jamais une liste/document d'un dossier existant. */
    public function initialiser(Dossier $dossier): void
    {
        if ($this->em->getRepository(DocsDossier::class)->findOneBy(['code_dossier' => $dossier])) return;
        if (!$dossier->getProduit() || !$dossier->getClient()) throw new \InvalidArgumentException('Client et produit requis pour le suivi documentaire.');
        $liste = $this->liste($dossier->getProduit(), $dossier->getClient());
        // Une liste absente ne bloque pas l'ouverture minimale de l'OT.
        if ($liste['documents'] === []) return;
        foreach ($liste['documents'] as $ligne) {
            $doc = (new DocsDossier())->setCodeDossier($dossier)->setCodeModele($liste['modele'])->setLibelle((string) $ligne['libelle']);
            $this->em->persist($doc);
        }
    }

    /** Complement manuel d'un dossier : sans effacer les exigences ni les fichiers deja suivis. */
    public function ajouter(Dossier $dossier, string $libelle): DocsDossier
    {
        $libelle = trim($libelle);
        if ($libelle === '' || mb_strlen($libelle) > 255) throw new \InvalidArgumentException('Libelle du document obligatoire (255 caracteres maximum).');
        if ($this->em->getRepository(DocsDossier::class)->findOneBy(['code_dossier' => $dossier, 'libelle' => $libelle])) throw new \InvalidArgumentException('Ce document figure deja dans le suivi.');
        $doc = (new DocsDossier())->setCodeDossier($dossier)->setLibelle($libelle);
        $this->em->persist($doc);
        return $doc;
    }
}
