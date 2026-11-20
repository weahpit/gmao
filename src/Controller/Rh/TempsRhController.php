<?php

namespace App\Controller\Rh;

use App\Entity\Rh\Absence;
use App\Entity\Rh\DocumentRh;
use App\Entity\Rh\Pointage;
use App\Repository\Rh\AbsenceRepository;
use App\Repository\Rh\EmployeRepository;
use App\Repository\Rh\PointageRepository;
use App\Security\RhAcces;
use App\Service\Rh\ReferencesRh;
use App\Service\Rh\StockageDocumentRh;
use App\Services\pdfService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Temps de travail : pointages quotidiens, demandes de conges et
 * absences, circuit de validation (chef de service puis RH).
 */
#[Route('/rh/temps', name: 'rh_temps')]
#[IsGranted('ROLE_USER')]
final class TempsRhController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private PointageRepository $pointages,
        private AbsenceRepository $absences,
        private EmployeRepository $employes,
        private ReferencesRh $references,
        private StockageDocumentRh $stockage,
        private RhAcces $acces,
    ) {}

    // ------------------------------------------------------------------
    //  Pointages
    // ------------------------------------------------------------------

    #[Route('/pointages', name: '_pointages', methods: ['GET'])]
    public function pointages(Request $request): Response
    {
        if (!$this->acces->estRh() && !$this->acces->aRole('ROLE_CHEF_SERVICE', 'ROLE_MANAGER')) {
            throw $this->createAccessDeniedException('Les pointages sont reserves au service RH et aux responsables.');
        }

        $jour = $this->date($request->query->get('jour', '')) ?? new \DateTimeImmutable('today');
        $duJour = $this->pointages->findDuJour($jour);

        $index = [];
        foreach ($duJour as $pointage) {
            if ($pointage->getEmploye() !== null) {
                $index[(string) $pointage->getEmploye()->getId()] = $pointage;
            }
        }

        $stats = $this->pointages->totalRetardsMois($jour);

        return $this->render('rh/temps/pointages.html.twig', [
            'jour' => $jour,
            'pointages' => $index,
            'employes' => $this->employes->findActifs(),
            'presents' => $this->pointages->compterPresents($jour),
            'retardsMois' => (int) ($stats['minutes'] ?? 0),
            'heuresSupMois' => (int) ($stats['heures'] ?? 0),
            'estRh' => $this->acces->estRh(),
        ]);
    }

    #[Route('/pointages/enregistrer', name: '_pointage_save', methods: ['POST'])]
    public function pointageSave(Request $request): Response
    {
        if (!$this->acces->estRh() && !$this->acces->aRole('ROLE_CHEF_SERVICE', 'ROLE_MANAGER')) {
            throw $this->createAccessDeniedException('La saisie des pointages est reservee au service RH et aux responsables.');
        }

        $jour = $this->date($request->request->get('jour', '')) ?? new \DateTimeImmutable('today');
        $retour = $this->redirectToRoute('rh_temps_pointages', ['jour' => $jour->format('Y-m-d')]);

        if (!$this->isCsrfTokenValid('rh_pointage', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Le formulaire a expire. Rechargez la page puis recommencez.');
            return $retour;
        }

        $employe = $this->employes->find((string) $request->request->get('employe', ''));
        if ($employe === null) {
            $this->addFlash('danger', 'Employe introuvable.');
            return $retour;
        }

        $arrivee = $this->heure($request->request->get('heure_arrivee', ''), $jour);
        $depart = $this->heure($request->request->get('heure_depart', ''), $jour);

        if ($arrivee === null && $depart === null) {
            $this->addFlash('danger', 'Renseignez au moins l\'heure d\'arrivee ou de depart de ' . $employe->getNomComplet() . '.');
            return $retour;
        }

        if ($arrivee !== null && $depart !== null && $depart <= $arrivee) {
            $this->addFlash('danger', 'L\'heure de depart doit etre posterieure a l\'heure d\'arrivee.');
            return $retour;
        }

        $pointage = $this->pointages->findUnPourEmployeEtJour($employe, $jour) ?? (new Pointage())->setEmploye($employe)->setJour($jour);

        $pointage->setHeureArrivee($arrivee)->setHeureDepart($depart);

        // Reference : arrivee a 08h00, depart a 17h00.
        $retard = 0;
        if ($arrivee !== null) {
            $limite = $jour->setTime(8, 0);
            if ($arrivee > $limite) {
                $retard = (int) floor(($arrivee->getTimestamp() - $limite->getTimestamp()) / 60);
            }
        }

        $supplementaires = 0;
        if ($depart !== null) {
            $fin = $jour->setTime(17, 0);
            if ($depart > $fin) {
                $supplementaires = (int) floor(($depart->getTimestamp() - $fin->getTimestamp()) / 60);
            }
        }

        $pointage
            ->setRetardMinutes($retard)
            ->setHeuresSupplementaires($supplementaires)
            ->setCommentaire(trim((string) $request->request->get('commentaire', '')) ?: null);

        if ($pointage->getId() === null) {
            $this->em->persist($pointage);
        }
        $this->em->flush();

        $this->addFlash('success', 'Pointage de ' . $employe->getNomComplet() . ' enregistre pour le ' . $jour->format('d/m/Y') . '.'

        );

        return $retour;
    }

    // ------------------------------------------------------------------
    //  Absences et conges
    // ------------------------------------------------------------------

    #[Route('/absences', name: '_absences', methods: ['GET'])]
    public function absences(Request $request): Response
    {
        $statut = (string) $request->query->get('statut', '');
        $type = (string) $request->query->get('type', '');

        $qb = $this->absences->createQueryBuilder('a')
            ->leftJoin('a.employe', 'e')->addSelect('e')
            ->orderBy('a.dateDebut', 'DESC');

        if ($statut !== '') {
            $qb->andWhere('a.statut = :statut')->setParameter('statut', $statut);
        }
        if ($type !== '') {
            $qb->andWhere('a.type = :type')->setParameter('type', $type);
        }

        return $this->render('rh/temps/absences.html.twig', [
            'absences' => $qb->getQuery()->getResult(),
            'enAttente' => $this->absences->findEnAttente(),
            'statut' => $statut,
            'type' => $type,
            'statuts' => Absence::STATUTS,
            'types' => Absence::TYPES,
            'annee' => (int) date('Y'),
            'joursParType' => $this->absences->joursParType((int) date('Y')),
            'estRh' => $this->acces->estRh(),
        ]);
    }

    #[Route('/absences/nouvelle', name: '_absence_new', methods: ['GET', 'POST'])]
    public function absenceNew(Request $request): Response
    {
        $absence = (new Absence())
            ->setReference($this->references->absence())
            ->setStatut(Absence::SOUMIS)
            ->setType(Absence::CONGE_ANNUEL);
        $donnees = $this->donneesAbsence($absence);
        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('rh_absence', (string) $request->request->get('_token'))) {
                $erreurs[] = 'Le formulaire a expire. Rechargez la page puis recommencez.';
            } else {
                $donnees = $request->request->all();
                $erreurs = $this->appliquerAbsence($absence, $donnees, $request->files->get('justificatif'));

                if ($erreurs === []) {
                    $this->em->persist($absence);
                    $this->em->flush();
                    $this->addFlash('success', 'La demande ' . $absence->getReference() . ' est transmise pour validation.');

                    return $this->redirectToRoute('rh_temps_absences');
                }
            }
        }

        return $this->render('rh/temps/absence_form.html.twig', [
            'absence' => $absence,
            'donnees' => $donnees,
            'erreurs' => $erreurs,
            'employes' => $this->employes->findActifs(),
            'types' => Absence::TYPES,
            'estCreation' => true,
        ]);
    }

    #[Route('/absences/{id}', name: '_absence', methods: ['GET', 'POST'])]
    public function absence(Request $request, string $id): Response
    {
        $absence = $this->absences->find($id);
        if ($absence === null) {
            throw $this->createNotFoundException('Demande d\'absence introuvable.');
        }

        $peutValider = $this->acces->peutValiderConge($absence);
        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$peutValider) {
                throw $this->createAccessDeniedException('Vous n\'etes pas habilite a valider cette demande.');
            }

            if (!$this->isCsrfTokenValid('rh_absence_decision_' . $id, (string) $request->request->get('_token'))) {
                $erreurs[] = 'Le formulaire a expire. Rechargez la page puis recommencez.';
            } else {
                $erreur = $this->decider($absence, (string) $request->request->get('action', ''), trim((string) $request->request->get('commentaire', '')));
                if ($erreur !== null) {
                    $erreurs[] = $erreur;
                } else {
                    $this->em->flush();
                    $this->addFlash('success', 'La decision est enregistree sur la demande ' . $absence->getReference() . '.');

                    return $this->redirectToRoute('rh_temps_absence', ['id' => $id]);
                }
            }
        }

        return $this->render('rh/temps/absence.html.twig', [
            'absence' => $absence,
            'erreurs' => $erreurs,
            'peutValider' => $peutValider,
            'estRh' => $this->acces->estRh(),
        ]);
    }

    #[Route('/absences/pdf', name: '_absences_pdf', methods: ['GET'])]
    public function absencesPdf(pdfService $pdf): Response
    {
        $lignes = [];
        foreach ($this->absences->findRecentes(500) as $absence) {
            $lignes[] = [
                (string) $absence->getReference(),
                $absence->getEmploye()?->getNomComplet() ?? '-',
                (string) $absence->getTypeLibelle(),
                $absence->getDateDebut()?->format('d/m/Y') ?? '-',
                $absence->getDateFin()?->format('d/m/Y') ?? '-',
                (string) $absence->getNombreJours(),
                (string) $absence->getStatutLibelle(),
            ];
        }

        $contenu = $pdf->genererTableau(
            'Conges et absences',
            ['Reference', 'Employe', 'Type', 'Du', 'Au', 'Jours', 'Statut'],
            [0.15, 0.27, 0.18, 0.12, 0.12, 0.06, 0.10],
            $lignes,
            'L',
            'Etat au ' . date('d/m/Y')
        );

        return new Response($contenu, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="absences-rh.pdf"',
        ]);
    }

    // ------------------------------------------------------------------
    //  Aides
    // ------------------------------------------------------------------

    private function decider(Absence $absence, string $action, string $commentaire): ?string
    {
        if ($action === 'refuser') {
            if (in_array($absence->getStatut(), [Absence::REFUSE, Absence::VALIDE_RH], true)) {
                return 'Cette demande est deja ' . mb_strtolower($absence->getStatutLibelle()) . ' : elle ne peut plus etre refusee.';
            }
            $absence->setStatut(Absence::REFUSE)
                ->setCommentaireValidation($commentaire ?: null)
                ->setDateValidationChef(new \DateTimeImmutable());

            return null;
        }

        if ($action !== 'valider') {
            return 'Action inconnue : ' . $action . '.';
        }

        if ($absence->getStatut() === Absence::VALIDE_RH) {
            return 'Cette demande est deja validee par le service RH.';
        }
        if ($absence->getStatut() === Absence::REFUSE) {
            return 'Cette demande a ete refusee : elle ne peut plus etre validee.';
        }

        if ($this->acces->estRh()) {
            $absence->setStatut(Absence::VALIDE_RH)
                ->setValideRhPar($this->acces->utilisateur())
                ->setDateValidationRh(new \DateTimeImmutable())
                ->setCommentaireValidation($commentaire ?: null);

            return null;
        }

        $absence->setStatut(Absence::VALIDE_CHEF)
            ->setValideChefPar($this->acces->utilisateur())
            ->setDateValidationChef(new \DateTimeImmutable())
            ->setCommentaireValidation($commentaire ?: null);

        return null;
    }

    private function donneesAbsence(Absence $absence): array
    {
        return [
            'employe' => (string) $absence->getEmploye()?->getId(),
            'type' => $absence->getType(),
            'date_debut' => $absence->getDateDebut()?->format('Y-m-d'),
            'date_fin' => $absence->getDateFin()?->format('Y-m-d'),
            'motif' => $absence->getMotif(),
        ];
    }

    private function appliquerAbsence(Absence $absence, array $donnees, mixed $justificatif): array
    {
        $erreurs = [];

        $employe = $this->employes->find((string) ($donnees['employe'] ?? ''));
        $debut = $this->date($donnees['date_debut'] ?? '');
        $fin = $this->date($donnees['date_fin'] ?? '');

        if ($employe === null) {
            $erreurs[] = 'Choisissez l\'employe concerne par la demande.';
        }
        if ($debut === null || $fin === null) {
            $erreurs[] = 'Indiquez la date de debut et la date de fin.';
        }
        if ($debut !== null && $fin !== null && $fin < $debut) {
            $erreurs[] = 'La date de fin (' . $fin->format('d/m/Y') . ') ne peut pas preceder la date de debut ('
                . $debut->format('d/m/Y') . ').';
        }

        if ($employe !== null && $debut !== null && $fin !== null) {
            $existante = $this->absences->findChevauchement($employe, $debut, $fin);
            if ($existante !== null) {
                $erreurs[] = 'Une demande (' . $existante->getReference() . ') couvre deja la periode du '
                    . $existante->getDateDebut()?->format('d/m/Y') . ' au ' . $existante->getDateFin()?->format('d/m/Y')
                    . ' pour ' . $employe->getNomComplet() . '.';
            }
        }

        if ($erreurs !== []) {
            return $erreurs;
        }

        $absence
            ->setEmploye($employe)
            ->setType((string) ($donnees['type'] ?? Absence::CONGE_ANNUEL))
            ->setDateDebut($debut)
            ->setDateFin($fin)
            ->setMotif(trim((string) ($donnees['motif'] ?? '')) ?: null);

        $absence->recalculerJours();

        if ($justificatif instanceof UploadedFile) {
            $document = (new DocumentRh())
                ->setCategorie(DocumentRh::CATEGORIE_RH)
                ->setType('attestation')
                ->setTitre('Justificatif ' . $absence->getReference())
                ->setEmploye($employe)
                ->setDeposePar($this->acces->utilisateur());

            try {
                $this->stockage->stocker($justificatif, $document);
                $this->em->persist($document);
                $absence->setJustificatif($document->getChemin());
            } catch (\RuntimeException $e) {
                return [$e->getMessage()];
            }
        }

        return [];
    }

    private function date(mixed $valeur): ?\DateTimeImmutable
    {
        $valeur = trim((string) $valeur);
        if ($valeur === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $valeur);

        return $date instanceof \DateTimeImmutable ? $date : null;
    }

    private function heure(mixed $valeur, \DateTimeInterface $jour): ?\DateTimeImmutable
    {
        $valeur = trim((string) $valeur);
        if ($valeur === '' || !preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $valeur)) {
            return null;
        }

        [$h, $m] = array_map('intval', explode(':', $valeur));

        return \DateTimeImmutable::createFromInterface($jour)->setTime($h, $m);
    }
}
