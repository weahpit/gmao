<?php
// src/Services/FicheInterventionPdfService.php
//
// Service injectable : construit le PDF de la fiche d'intervention a partir
// de l'entite FicheIntervention (ou d'un tableau pour une fiche vierge).
//
// IMPORTANT (PSR-4 / autowiring Symfony) :
//   un fichier = une classe, et le nom du fichier doit correspondre au nom
//   de la classe. C'est pour cela que ce service est dans son PROPRE fichier
//   (FicheInterventionPdfService.php) et NON dans FicheInterventionPdf.php.

namespace App\Services;

use App\Entity\Maintenance\FicheIntervention;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class FicheInterventionPdfService
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir
    ) {}

    /** Genere le PDF a partir de l'entite FicheIntervention. Retourne le binaire PDF. */
    public function generer(FicheIntervention $fiche): string
    {
        return $this->genererDepuisTableau($this->mapper($fiche));
    }

    /** Genere le PDF a partir d'un tableau (fiche vierge ou tests). */
    public function genererDepuisTableau(array $data): string
    {
        $pdf = new FicheInterventionPdf('P', 'mm', 'A4');
        $pdf->logoPath = $this->projectDir . '/public/images/CITRAC.png';
        $pdf->data = $data;
        $pdf->SetTitle('Fiche d\'intervention ' . (string)($data['reference'] ?? ''));
        $pdf->SetAutoPageBreak(false);
        return $pdf->build();
    }

    /** Mappe l'entite vers le tableau attendu par le generateur. */
    private function mapper(FicheIntervention $f): array
    {
        $tech = [];
        foreach ($f->getCodeTechnicien() as $u) {
            $tech[] = trim(((string) $u->getPrenoms()) . ' ' . ((string) $u->getNom()));
        }

        return [
            'reference'      => $f->getReference(),
            'dateEmission'   => $f->getCreatedAt() ? $f->getCreatedAt()->format('d/m/Y H:i') : '',
            'demandePar'     => $f->getCreatedBy(),
            'type'           => $f->getTypeIntervention()?->getLibelle(),
            'priorite'       => $f->getPriorite()?->getLibelle(),
            'statut'         => $f->getStatut()?->getLibelle(),
            'origine'        => $f->getOrigine()?->getLibelle(),
            'equipement'     => $f->getEquipementType()?->getNomEquipement(),
            'codeEquipement' => $f->getEquipement()?->getCode() ?? $f->getEquipementType()?->getCode(),
            'zone'           => $f->getZone()?->getNom(),
            'emplacement'    => $f->getPreciserEmplacement() ?: $f->getEmplacement()?->getLibelle(),
            'demandeur'      => $f->getDemandeur(),
            'service'        => $f->getServiceDemandeur()?->getLibelle(),
            'sujet'          => $f->getSujet(),
            'description'    => $f->getDescription(),
            'cause'          => $f->getCause(),
            'nature'         => $f->getNature()?->getLibelle(),
            'arretMachine'   => $f->isArretMachine(),
            'technicien'     => implode(', ', array_filter($tech)),
            'sousTraitant'   => $f->getSousTraitant()?->getRaisonSociale(),
            'datePlanifieeDebut' => $f->getDateDebutPlanifie() ? $f->getDateDebutPlanifie()->format('d/m/Y') : '',
            'datePlanifieeFin'   => $f->getDateFinPlanifie() ? $f->getDateFinPlanifie()->format('d/m/Y') : '',
            'dateDebutReel'      => $f->getDateDebutReel() ? $f->getDateDebutReel()->format('d/m/Y H:i') : '',
            'dateFinReel'        => $f->getDateFinReel() ? $f->getDateFinReel()->format('d/m/Y H:i') : '',
        ];
    }
}
