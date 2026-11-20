<?php

namespace App\Controller\Rh;

use App\Repository\Rh\AbsenceRepository;
use App\Repository\Rh\AccidentTravailRepository;
use App\Repository\Rh\CandidatureRepository;
use App\Repository\Rh\ContratRepository;
use App\Repository\Rh\DocumentRhRepository;
use App\Repository\Rh\EmployeRepository;
use App\Repository\Rh\FormationRepository;
use App\Repository\Rh\VisiteMedicaleRepository;
use App\Security\RhAcces;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Tableau de bord RH : effectif, mouvements, contrats a echeance,
 * absences a valider, recrutement, formation et sante au travail.
 */
#[Route('/rh', name: 'rh_')]
#[IsGranted('ROLE_USER')]
final class DashboardRhController extends AbstractController
{
    public function __construct(private RhAcces $acces) {}

    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function index(
        EmployeRepository $employes,
        ContratRepository $contrats,
        AbsenceRepository $absences,
        CandidatureRepository $candidatures,
        FormationRepository $formations,
        VisiteMedicaleRepository $visites,
        AccidentTravailRepository $accidents,
        DocumentRhRepository $documents,
    ): Response {
        $annee = (int) date('Y');
        $debutMois = new \DateTimeImmutable('first day of this month');
        $finMois = new \DateTimeImmutable('last day of this month');

        $effectif = $employes->effectifActif();
        $parSexe = $employes->compterParSexe();
        $parService = $employes->compterParService();
        $parStatut = $employes->compterParStatut();

        $joursAbsenceMois = $absences->totalJoursValides($debutMois, $finMois);
        $joursOuvresMois = $this->joursOuvres($debutMois, $finMois);
        $tauxAbsenteisme = ($effectif > 0 && $joursOuvresMois > 0)
            ? round($joursAbsenceMois / ($effectif * $joursOuvresMois) * 100, 1)
            : 0.0;

        $echeances = $contrats->findEcheances(60);
        $expires = $contrats->findExpiresARegulariser();

        return $this->render('rh/dashboard.html.twig', [
            'effectif' => $effectif,
            'hommes' => $parSexe['M'] ?? 0,
            'femmes' => $parSexe['F'] ?? 0,
            'parService' => $parService,
            'parStatut' => $parStatut,
            'suspendus' => $parStatut['suspendu'] ?? 0,
            'sortis' => $parStatut['sorti'] ?? 0,
            'tauxAbsenteisme' => $tauxAbsenteisme,
            'joursAbsenceMois' => $joursAbsenceMois,
            'absencesEnAttente' => $absences->compterEnAttente(),
            'echeances' => $echeances,
            'contratsExpires' => $expires,
            'recrutementsAnnee' => $candidatures->compterRecrutementsAnnee($annee),
            'candidaturesEnCours' => count($candidatures->findEnCours()),
            'pipeline' => $candidatures->compterParEtape(),
            'formationsAVenir' => $formations->findAVenir(),
            'coutFormation' => $this->acces->estRh() ? $formations->coutTotalAnnee($annee) : null,
            'visitesARenouveler' => $this->acces->peutVoirMedical() ? $visites->findRenouvellements(60) : [],
            'visitesMedicales' => $this->acces->peutVoirMedical(),
            'accidentsAnnee' => $this->acces->peutVoirMedical() ? $accidents->compterAnnee($annee) : null,
            'joursArretAnnee' => $this->acces->peutVoirMedical() ? $accidents->compterJoursArretAnnee($annee) : null,
            'masseSalariale' => $this->acces->peutVoirSalaires() ? $contrats->masseSalarialeMensuelle() : null,
            'contratsParType' => $this->acces->estRh() ? $contrats->compterParType() : [],
            'documents' => $this->acces->estRh() ? $documents->compterParCategorie() : [],
            'peutVoirSalaires' => $this->acces->peutVoirSalaires(),
            'estRh' => $this->acces->estRh(),
            'annee' => $annee,
        ]);
    }

    /** Nombre de jours ouvres (lundi-vendredi) entre deux dates incluses. */
    private function joursOuvres(\DateTimeInterface $debut, \DateTimeInterface $fin): int
    {
        $jour = \DateTimeImmutable::createFromInterface($debut);
        $dernier = \DateTimeImmutable::createFromInterface($fin);
        $total = 0;

        while ($jour <= $dernier) {
            if ((int) $jour->format('N') <= 5) {
                ++$total;
            }
            $jour = $jour->modify('+1 day');
        }

        return $total;
    }
}
