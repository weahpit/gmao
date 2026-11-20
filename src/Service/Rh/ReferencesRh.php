<?php

namespace App\Service\Rh;

use App\Repository\Rh\AbsenceRepository;
use App\Repository\Rh\ContratRepository;
use App\Repository\Rh\CandidatureRepository;
use App\Repository\Rh\DossierMedicalRepository;
use App\Repository\Rh\EmployeRepository;
use App\Repository\Rh\OffreEmploiRepository;

/**
 * Generation des references lisibles du module RH (matricules, contrats,
 * offres, candidatures, conges, dossiers medicaux).
 *
 * Le format reste stable : PREFIXE-ANNEE-NUMERO. En cas de suppression d'un
 * enregistrement, un numero deja utilise peut etre reutilise : c'est sans
 * consequence car l'identifiant technique reste unique.
 */
final class ReferencesRh
{
    public function __construct(
        private EmployeRepository $employes,
        private ContratRepository $contrats,
        private OffreEmploiRepository $offres,
        private CandidatureRepository $candidatures,
        private AbsenceRepository $absences,
        private DossierMedicalRepository $dossiers,
    ) {}

    public function matricule(): string
    {
        return $this->employes->prochainMatricule();
    }

    public function contrat(): string
    {
        return $this->contrats->prochaineReference();
    }

    public function offre(): string
    {
        return $this->offres->prochaineReference();
    }

    public function candidature(): string
    {
        return $this->candidatures->prochaineReference();
    }

    public function dossierMedical(): string
    {
        return $this->dossiers->prochainNumero();
    }

    /** Reference d'une demande d'absence : ABS-AAAA-0000. */
    public function absence(): string
    {
        $annee = date('Y');
        $nombre = (int) $this->absences->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.reference LIKE :prefixe')
            ->setParameter('prefixe', 'ABS-' . $annee . '-%')
            ->getQuery()
            ->getSingleScalarResult();

        return 'ABS-' . $annee . '-' . str_pad((string) ($nombre + 1), 4, '0', STR_PAD_LEFT);
    }

    /** Suffixe court et lisible pour un nom de fichier (ex. N-2026-0007). */
    public function jetonFichier(string $prefixe = 'DOC'): string
    {
        return $prefixe . '-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}
