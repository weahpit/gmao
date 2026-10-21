<?php

namespace App\Controller\Api\Maintenance;

use App\Entity\EquipementType;
use App\Entity\Maintenance\PlanMaintenance;
use App\Entity\Maintenance\TypeIntervention;
use App\Entity\ZoneExploitation;
use App\Services\PlanMaintenanceScheduler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiPlanMaintenanceController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private PlanMaintenanceScheduler $scheduler
    ) {}

    // =====================================================================
    //  LISTE
    // =====================================================================
    #[Route('api/getAllPlansMaintenance', name: 'app_api_get_all_plans_maintenance')]
    public function getAll(): Response
    {
        if (!$this->getUser()) { return $this->redirectToRoute('app_login'); }
        $reponse = [];
        $data = [];
        if ($this->isGranted('ROLE_USER')) {
            try {
                $plans = $this->em->getRepository(PlanMaintenance::class)->findBy([], ['libelle' => 'ASC']);
                foreach ($plans as $plan) {
                    $nbAFaire = 0; $nbFait = 0;
                    foreach ($plan->getOccurrences() as $o) {
                        if ($o->getStatut() === 'FAIT') { $nbFait++; } else { $nbAFaire++; }
                    }
                    $data[] = [
                        'id'                => $plan->getId(),
                        'reference'         => $plan->getReference(),
                        'libelle'           => $plan->getLibelle(),
                        'zone'              => $plan->getZone() ? $plan->getZone()->getNom() : '',
                        'equipement_type'   => $plan->getEquipementType() ? $plan->getEquipementType()->getNomEquipement() : '',
                        'type_intervention' => $plan->getTypeIntervention() ? $plan->getTypeIntervention()->getLibelle() : '',
                        'periodicite'       => $plan->getPeriodicite(),
                        'intervalle'        => $plan->getIntervalle(),
                        'jour_semaine'      => $plan->getJourSemaine(),
                        'date_debut'        => $plan->getDateDebut() ? $plan->getDateDebut()->format('d/m/Y') : '',
                        'date_fin'          => $plan->getDateFin() ? $plan->getDateFin()->format('d/m/Y') : '',
                        'couleur'           => $plan->getCouleur(),
                        'actif'             => $plan->isActif(),
                        'nb_a_faire'        => $nbAFaire,
                        'nb_fait'           => $nbFait,
                    ];
                }
                $reponse = ['code' => 'success', 'msg' => 'Succes', 'data' => $data];
            } catch (\Throwable $e) {
                $reponse = ['code' => 'error', 'msg' => 'Erreur ! <br>' . $e->getMessage()];
            }
        } else {
            $reponse = ['code' => 'warning', 'msg' => 'Vous n\'etes pas autorise a acceder a cette ressource'];
        }
        return new JsonResponse(json_encode($reponse));
    }

    // =====================================================================
    //  FICHE UNIQUE (pour edition)
    // =====================================================================
    #[Route('api/getSinglePlanMaintenance/{id}', name: 'app_api_get_single_plan_maintenance')]
    public function getSingle(string $id): Response
    {
        if (!$this->getUser()) { return $this->redirectToRoute('app_login'); }
        $reponse = [];
        if ($this->isGranted('ROLE_USER')) {
            try {
                $plan = $this->em->getRepository(PlanMaintenance::class)->find(Uuid::fromString($id));
                if ($plan) {
                    $reponse = [
                        'code'              => 'success',
                        'msg'               => 'Success',
                        'reference'         => $plan->getReference(),
                        'libelle'           => $plan->getLibelle(),
                        'zone'              => $plan->getZone() ? (string) $plan->getZone()->getId() : '',
                        'equipement_type'   => $plan->getEquipementType() ? (string) $plan->getEquipementType()->getId() : '',
                        'type_intervention' => $plan->getTypeIntervention() ? (string) $plan->getTypeIntervention()->getId() : '',
                        'periodicite'       => $plan->getPeriodicite(),
                        'intervalle'        => $plan->getIntervalle(),
                        'jour_semaine'      => $plan->getJourSemaine(),
                        'date_debut'        => $plan->getDateDebut() ? $plan->getDateDebut()->format('Y-m-d') : '',
                        'date_fin'          => $plan->getDateFin() ? $plan->getDateFin()->format('Y-m-d') : '',
                        'couleur'           => $plan->getCouleur(),
                        'actif'             => $plan->isActif(),
                    ];
                } else {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de selectionner un plan dans la liste !'];
                }
            } catch (\Throwable $e) {
                $reponse = ['code' => 'error', 'msg' => 'Une erreur s\'est produite !<br>' . $e->getMessage()];
            }
        } else {
            $reponse = ['code' => 'warning', 'msg' => 'Vous n\'etes pas autorise a acceder a cette ressource'];
        }
        return new JsonResponse($reponse);
    }

    // =====================================================================
    //  ENREGISTRER (creation / mise a jour)
    // =====================================================================
    #[Route('api/savePlanMaintenance', name: 'app_api_save_plan_maintenance')]
    public function save(Request $request): Response
    {
        if (!$this->getUser()) { return $this->redirectToRoute('app_login'); }
        $reponse = [];
        if ($this->isGranted('ROLE_ADMIN')) {
            try {
                $idPlan   = (string) $request->request->get('id_plan', '0');
                $libelle  = trim((string) $request->request->get('libelle'));
                $periodicite = strtoupper(trim((string) $request->request->get('periodicite')));
                $dateDebutRaw = (string) $request->request->get('date_debut');

                if ($libelle === '' || $periodicite === '' || $dateDebutRaw === '') {
                    return new JsonResponse(['code' => 'warning', 'msg' => 'Merci de saisir les champs obligatoires (libelle, periodicite, date de debut) !']);
                }

                $dateDebut = $this->parseDate($dateDebutRaw);
                if (!$dateDebut) {
                    return new JsonResponse(['code' => 'warning', 'msg' => 'Date de debut invalide !']);
                }

                if ($idPlan === '0') {
                    $plan = new PlanMaintenance();
                    $plan->setCreatedBy(strtoupper((string) $this->getUser()->getUserIdentifier()));
                    $isNew = true;
                } else {
                    $plan = $this->em->getRepository(PlanMaintenance::class)->find(Uuid::fromString($idPlan));
                    if (!$plan) {
                        return new JsonResponse(['code' => 'warning', 'msg' => 'Plan introuvable !']);
                    }
                    $isNew = false;
                }

                $plan->setReference(strtoupper(trim((string) $request->request->get('reference'))) ?: null);
                $plan->setLibelle(strtoupper($libelle));
                $plan->setPeriodicite($periodicite);
                $plan->setIntervalle((int) $request->request->get('intervalle', 1));
                $jour = $request->request->get('jour_semaine');
                $plan->setJourSemaine($jour !== null && $jour !== '' ? (int) $jour : null);
                $plan->setDateDebut($dateDebut);
                $dateFin = $this->parseDate((string) $request->request->get('date_fin'));
                $plan->setDateFin($dateFin);
                $plan->setCouleur(trim((string) $request->request->get('couleur')) ?: '#C8953A');
                $plan->setActif(in_array((string) $request->request->get('actif', '1'), ['1', 'true', 'on'], true));

                // Relations (uuid)
                $plan->setZone($this->findEntity(ZoneExploitation::class, $request->request->get('zone')));
                $plan->setEquipementType($this->findEntity(EquipementType::class, $request->request->get('equipement_type')));
                $plan->setTypeIntervention($this->findEntity(TypeIntervention::class, $request->request->get('type_intervention')));

                $this->em->persist($plan);
                $this->em->flush();

                // Materialise les echeances jusqu'a fin d'annee en cours
                $ajoutees = $this->scheduler->genererOccurrences($plan);

                $reponse = [
                    'code' => 'success',
                    'msg'  => ($isNew ? 'Plan de maintenance cree' : 'Plan de maintenance mis a jour')
                        . ' avec succes ! ' . $ajoutees . ' echeance(s) programmee(s).',
                ];
            } catch (\Throwable $e) {
                $reponse = ['code' => 'error', 'msg' => 'Une erreur s\'est produite !<br>' . $e->getMessage()];
            }
        } else {
            $reponse = ['code' => 'warning', 'msg' => 'Vous n\'etes pas autorise a acceder a cette ressource'];
        }
        return new JsonResponse($reponse);
    }

    // =====================================================================
    //  SUPPRIMER
    // =====================================================================
    #[Route('api/deletePlanMaintenance/{id}', name: 'app_api_delete_plan_maintenance')]
    public function delete(string $id): Response
    {
        if (!$this->getUser()) { return $this->redirectToRoute('app_login'); }
        $reponse = [];
        if ($this->isGranted('ROLE_ADMIN')) {
            try {
                $plan = $this->em->getRepository(PlanMaintenance::class)->find(Uuid::fromString($id));
                if ($plan) {
                    $this->em->remove($plan);
                    $this->em->flush();
                    $reponse = ['code' => 'success', 'msg' => 'Plan de maintenance supprime avec succes !'];
                } else {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de selectionner un plan !'];
                }
            } catch (\Throwable $e) {
                $reponse = ['code' => 'error', 'msg' => 'Une erreur s\'est produite !<br>' . $e->getMessage()];
            }
        } else {
            $reponse = ['code' => 'warning', 'msg' => 'Vous n\'etes pas autorise a acceder a cette ressource'];
        }
        return new JsonResponse($reponse);
    }

    // =====================================================================
    //  GENERATION MANUELLE DES FICHES DUES (bouton "Lancer la programmation")
    // =====================================================================
    #[Route('api/genererFichesPreventivesDues', name: 'app_api_generer_fiches_preventives_dues')]
    public function genererFichesDues(): Response
    {
        if (!$this->getUser()) { return $this->redirectToRoute('app_login'); }
        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Vous n\'etes pas autorise a acceder a cette ressource']);
        }
        try {
            $nb = $this->scheduler->genererFichesDues(
                new \DateTime('today'),
                strtoupper((string) $this->getUser()->getUserIdentifier())
            );
            return new JsonResponse([
                'code' => 'success',
                'msg'  => $nb > 0
                    ? $nb . ' fiche(s) d\'intervention generee(s) a partir des plans dus.'
                    : 'Aucune echeance due pour le moment.',
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Une erreur s\'est produite !<br>' . $e->getMessage()]);
        }
    }

    // =====================================================================
    //  PLANNING ANNUEL (grille semaines x plans)
    // =====================================================================
    #[Route('api/getPlanningAnnuel/{annee}', name: 'app_api_get_planning_annuel')]
    public function planningAnnuel(int $annee): Response
    {
        if (!$this->getUser()) { return $this->redirectToRoute('app_login'); }
        $reponse = [];
        if ($this->isGranted('ROLE_USER')) {
            try {
                $moisLabels = ['', 'JAN', 'FEV', 'MAR', 'AVR', 'MAI', 'JUN', 'JUL', 'AOU', 'SEP', 'OCT', 'NOV', 'DEC'];

                // Construit la liste des semaines (lundis) couvrant l'annee
                $debutAnnee = new \DateTime($annee . '-01-01');
                $finAnnee   = new \DateTime($annee . '-12-31');
                $lundi = (clone $debutAnnee);
                $decalage = ((int) $lundi->format('N')) - 1; // ramene au lundi
                if ($decalage > 0) { $lundi->modify('-' . $decalage . ' day'); }

                $weeks = [];
                $cur = (clone $lundi);
                $g = 0;
                while ($cur <= $finAnnee && $g < 60) {
                    $weeks[] = [
                        'debut' => (clone $cur),
                        'label' => $cur->format('d/m'),
                        'mois'  => $moisLabels[(int) $cur->format('n')],
                    ];
                    $cur->modify('+7 day');
                    $g++;
                }

                // Pour chaque plan : dates dues + drapeau par semaine
                $plans = [];
                $tousPlans = $this->em->getRepository(PlanMaintenance::class)->findBy([], ['libelle' => 'ASC']);
                foreach ($tousPlans as $plan) {
                    $dues = $this->scheduler->genererDates($plan, $finAnnee);
                    $flags = array_fill(0, count($weeks), false);
                    $dateStrings = [];
                    foreach ($dues as $d) {
                        if ((int) $d->format('Y') !== $annee) { continue; }
                        $dateStrings[] = $d->format('d/m/Y');
                        foreach ($weeks as $wi => $w) {
                            $finSemaine = (clone $w['debut'])->modify('+6 day');
                            if ($d >= $w['debut'] && $d <= $finSemaine) {
                                $flags[$wi] = true;
                                break;
                            }
                        }
                    }
                    $plans[] = [
                        'id'          => $plan->getId(),
                        'reference'   => $plan->getReference(),
                        'libelle'     => $plan->getLibelle(),
                        'zone'        => $plan->getZone() ? $plan->getZone()->getNom() : '',
                        'couleur'     => $plan->getCouleur() ?: '#C8953A',
                        'periodicite' => $plan->getPeriodicite(),
                        'actif'       => $plan->isActif(),
                        'dates'       => $dateStrings,
                        'flags'       => $flags,
                    ];
                }

                // Allege les semaines pour le JSON
                $weeksOut = array_map(static fn ($w) => ['label' => $w['label'], 'mois' => $w['mois']], $weeks);

                $reponse = ['code' => 'success', 'msg' => 'Succes', 'annee' => $annee, 'weeks' => $weeksOut, 'plans' => $plans];
            } catch (\Throwable $e) {
                $reponse = ['code' => 'error', 'msg' => 'Erreur ! <br>' . $e->getMessage()];
            }
        } else {
            $reponse = ['code' => 'warning', 'msg' => 'Vous n\'etes pas autorise a acceder a cette ressource'];
        }
        return new JsonResponse(json_encode($reponse));
    }

    // =====================================================================
    //  OUTILS
    // =====================================================================
    private function parseDate(?string $raw): ?\DateTime
    {
        $raw = trim((string) $raw);
        if ($raw === '') { return null; }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            $d = \DateTime::createFromFormat($format, $raw);
            if ($d instanceof \DateTime) {
                $d->setTime(0, 0, 0);
                return $d;
            }
        }
        return null;
    }

    /** @param class-string $class */
    private function findEntity(string $class, mixed $id): ?object
    {
        $id = (string) $id;
        if ($id === '' || $id === '0') { return null; }
        try {
            return $this->em->getRepository($class)->find(Uuid::fromString($id));
        } catch (\Throwable) {
            return null;
        }
    }
}
