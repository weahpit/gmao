<?php

namespace App\Services;

use App\Entity\Maintenance\FicheIntervention;
use App\Entity\Maintenance\PlanMaintenance;
use App\Entity\Maintenance\PlanMaintenanceOccurrence;
use App\Entity\Maintenance\StatutIntervention;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de programmation des plans de maintenance preventive.
 *
 *  - genererDates()        : calcule les echeances d'un plan sur une periode.
 *  - genererOccurrences()  : materialise ces echeances en occurrences (suivi).
 *  - genererFichesDues()   : cree automatiquement les fiches d'intervention
 *                            pour les echeances arrivees a terme (a lancer via cron).
 */
class PlanMaintenanceScheduler
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Calcule la liste des dates d'echeance d'un plan entre sa date de debut
     * et la borne de fin (bornee par la date de fin du plan si definie).
     *
     * @return \DateTime[]
     */
    public function genererDates(PlanMaintenance $plan, \DateTime $borneFin): array
    {
        $dates = [];
        $debut = $plan->getDateDebut();
        if (!$debut instanceof \DateTime) {
            return $dates;
        }

        // Borne de fin effective
        $fin = $plan->getDateFin() instanceof \DateTime && $plan->getDateFin() < $borneFin
            ? $plan->getDateFin()
            : $borneFin;

        $cursor = (clone $debut);
        $cursor->setTime(0, 0, 0);
        $periodicite = strtoupper((string) $plan->getPeriodicite());
        $intervalle  = max(1, $plan->getIntervalle());

        // Alignement sur le jour de semaine cible pour les plans hebdomadaires
        if ($periodicite === 'HEBDOMADAIRE' && $plan->getJourSemaine()) {
            $target = (int) $plan->getJourSemaine();           // 1 (lundi) .. 7 (dimanche)
            $courant = (int) $cursor->format('N');
            $diff = ($target - $courant + 7) % 7;
            if ($diff > 0) {
                $cursor->modify('+' . $diff . ' day');
            }
        }

        $pas = $this->pasModification($periodicite, $intervalle);

        $i = 0;
        while ($cursor <= $fin && $i < 2000) {
            $dates[] = (clone $cursor);
            $cursor->modify($pas);
            $i++;
        }

        return $dates;
    }

    /** Retourne l'expression \DateTime::modify() correspondant a la periodicite. */
    private function pasModification(string $periodicite, int $intervalle): string
    {
        return match ($periodicite) {
            'JOURNALIERE'   => '+' . $intervalle . ' day',
            'HEBDOMADAIRE'  => '+' . $intervalle . ' week',
            'MENSUELLE'     => '+' . $intervalle . ' month',
            'TRIMESTRIELLE' => '+' . (3 * $intervalle) . ' month',
            'SEMESTRIELLE'  => '+' . (6 * $intervalle) . ' month',
            'ANNUELLE'      => '+' . (12 * $intervalle) . ' month',
            default         => '+' . $intervalle . ' week',
        };
    }

    /**
     * Materialise les echeances du plan en occurrences (sans doublon).
     * Ne touche pas aux occurrences deja realisees.
     *
     * @return int Nombre d'occurrences ajoutees
     */
    public function genererOccurrences(PlanMaintenance $plan, ?\DateTime $borneFin = null, bool $flush = true): int
    {
        $borneFin ??= new \DateTime('last day of December ' . date('Y'));

        // Index des occurrences existantes par date (Y-m-d)
        $existantes = [];
        foreach ($plan->getOccurrences() as $occ) {
            if ($occ->getDatePrevue()) {
                $existantes[$occ->getDatePrevue()->format('Y-m-d')] = true;
            }
        }

        $ajoutees = 0;
        foreach ($this->genererDates($plan, $borneFin) as $date) {
            $cle = $date->format('Y-m-d');
            if (isset($existantes[$cle])) {
                continue;
            }
            $occ = new PlanMaintenanceOccurrence();
            $occ->setDatePrevue($date);
            $occ->setStatut(PlanMaintenanceOccurrence::A_FAIRE);
            $plan->addOccurrence($occ);
            $this->em->persist($occ);
            $existantes[$cle] = true;
            $ajoutees++;
        }

        if ($flush) {
            $this->em->flush();
        }

        return $ajoutees;
    }

    /**
     * Cree automatiquement les fiches d'intervention pour toutes les occurrences
     * "a faire" dont la date prevue est atteinte (<= $jusquAu, aujourd'hui par defaut).
     *
     * @return int Nombre de fiches generees
     */
    public function genererFichesDues(?\DateTime $jusquAu = null, string $createdBy = 'SYSTEME (plan preventif)'): int
    {
        $jusquAu ??= new \DateTime('today');
        $jusquAu->setTime(23, 59, 59);

        $occurrences = $this->em
            ->getRepository(PlanMaintenanceOccurrence::class)
            ->findDues($jusquAu);

        $statutOuvert = $this->trouverStatutOuvert();
        $compteur = 0;

        foreach ($occurrences as $occ) {
            $plan = $occ->getPlan();
            if (!$plan) {
                continue;
            }

            $fiche = new FicheIntervention();
            $fiche->setReference($this->genererReference($plan, $occ->getDatePrevue()));
            $fiche->setSujet($this->tronquer(strtoupper((string) $plan->getLibelle()), 255));
            $fiche->setDescription(
                'Fiche generee automatiquement par le plan de maintenance preventive "'
                . $plan->getLibelle() . '" (echeance du '
                . ($occ->getDatePrevue() ? $occ->getDatePrevue()->format('d/m/Y') : '') . ').'
            );
            $fiche->setCreatedAt(new \DateTimeImmutable());
            $fiche->setCreatedBy($createdBy);
            $fiche->setDateDebutPlanifie($occ->getDatePrevue() ? (clone $occ->getDatePrevue()) : new \DateTime());

            if ($plan->getTypeIntervention()) { $fiche->setTypeIntervention($plan->getTypeIntervention()); }
            if ($plan->getZone())            { $fiche->setZone($plan->getZone()); }
            if ($plan->getEquipementType())  { $fiche->setEquipementType($plan->getEquipementType()); }
            if ($statutOuvert)               { $fiche->setStatut($statutOuvert); }

            $this->em->persist($fiche);

            $occ->setFiche($fiche);
            $occ->setStatut(PlanMaintenanceOccurrence::FAIT);
            $occ->setDateRealisation(new \DateTime());

            $plan->setDerniereGeneration($occ->getDatePrevue() ? (clone $occ->getDatePrevue()) : new \DateTime());

            $compteur++;
        }

        if ($compteur > 0) {
            $this->em->flush();
        }

        return $compteur;
    }

    /** Recherche un statut "Ouvert(e)" pour initialiser les fiches generees. */
    private function trouverStatutOuvert(): ?StatutIntervention
    {
        $statuts = $this->em->getRepository(StatutIntervention::class)->findBy([], ['libelle' => 'ASC']);
        foreach ($statuts as $s) {
            $norm = $this->norm($s->getLibelle());
            if (str_contains($norm, 'ouvert')) {
                return $s;
            }
        }
        return $statuts[0] ?? null;
    }

    private function genererReference(PlanMaintenance $plan, ?\DateTime $date): string
    {
        $base = $plan->getReference() ?: 'PM';
        $suffixe = $date ? $date->format('Ymd') : date('Ymd');
        return $this->tronquer($base . '-' . $suffixe, 50);
    }

    private function tronquer(string $s, int $max): string
    {
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max) : $s;
    }

    private function norm(?string $s): string
    {
        $s = (string) $s;
        $c = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        return strtolower(trim($c !== false ? $c : $s));
    }
}
