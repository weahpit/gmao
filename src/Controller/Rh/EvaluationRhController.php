<?php

namespace App\Controller\Rh;

use App\Entity\Rh\CampagneEvaluation;
use App\Entity\Rh\CritereEvaluation;
use App\Entity\Rh\Evaluation;
use App\Repository\Rh\EmployeRepository;
use App\Repository\Rh\EvaluationRepository;
use App\Security\RhAcces;
use App\Services\pdfService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Evaluations professionnelles : campagnes annuelles, criteres ponderes,
 * auto-evaluation, evaluation du superieur et validation RH.
 */
#[Route('/rh/evaluations', name: 'rh_evaluations')]
#[IsGranted('ROLE_USER')]
final class EvaluationRhController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private EvaluationRepository $evaluations,
        private EmployeRepository $employes,
        private RhAcces $acces,
    ) {}

    #[Route('', name: '', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if (!$this->acces->peutEvaluer()) {
            throw $this->createAccessDeniedException('Les evaluations sont reservees aux responsables et au service RH.');
        }

        $campagnes = $this->em->getRepository(CampagneEvaluation::class)->findBy([], ['annee' => 'DESC', 'libelle' => 'ASC']);

        $campagne = null;
        $campagneId = (string) $request->query->get('campagne', '');
        if ($campagneId !== '') {
            $campagne = $this->em->getRepository(CampagneEvaluation::class)->find($campagneId);
        }
        if ($campagne === null && $campagnes !== []) {
            $campagne = $campagnes[0];
        }

        $fiches = [];
        $moyenne = null;
        $parStatut = [];
        if ($campagne !== null) {
            $fiches = $this->evaluations->findParCampagne($campagne);
            $moyenne = $this->evaluations->moyenneCampagne($campagne);
            $parStatut = $this->evaluations->compterParStatut($campagne);
        }

        return $this->render('rh/evaluations/index.html.twig', [
            'campagnes' => $campagnes,
            'campagne' => $campagne,
            'fiches' => $fiches,
            'moyenne' => $moyenne,
            'parStatut' => $parStatut,
            'statuts' => Evaluation::STATUTS,
            'estRh' => $this->acces->estRh(),
        ]);
    }

    #[Route('/campagnes/nouvelle', name: '_campagne_new', methods: ['GET', 'POST'])]
    public function campagneNew(Request $request): Response
    {
        $this->acces->exigerRh();

        $campagne = (new CampagneEvaluation())
            ->setLibelle('Campagne d\'evaluation ' . date('Y'))
            ->setAnnee((int) date('Y'))
            ->setStatut(CampagneEvaluation::OUVERTE);
        $donnees = [
            'libelle' => $campagne->getLibelle(),
            'annee' => $campagne->getAnnee(),
            'date_debut' => date('Y-m-d'),
            'date_fin' => date('Y-m-d', strtotime('+1 month')),
            'objectifs' => '',
            'criteres_libelle' => ['Objectifs atteints', 'Competences techniques', 'Qualite du travail', 'Autonomie et initiative'],
            'criteres_poids' => [3, 3, 2, 2],
        ];
        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('rh_campagne', (string) $request->request->get('_token'))) {
                $erreurs[] = 'Le formulaire a expire. Rechargez la page puis recommencez.';
            } else {
                $donnees = [
                    'libelle' => (string) $request->request->get('libelle', ''),
                    'annee' => (string) $request->request->get('annee', ''),
                    'date_debut' => (string) $request->request->get('date_debut', ''),
                    'date_fin' => (string) $request->request->get('date_fin', ''),
                    'objectifs' => (string) $request->request->get('objectifs', ''),
                    'criteres_libelle' => (array) $request->request->all('criteres_libelle'),
                    'criteres_poids' => (array) $request->request->all('criteres_poids'),
                ];
                $erreurs = $this->appliquerCampagne($campagne, $donnees);

                if ($erreurs === []) {
                    try {
                        $this->em->persist($campagne);
                        $this->em->flush();
                    } catch (\Throwable $e) {
                        $erreurs[] = 'La campagne n\'a pas pu etre enregistree : ' . $e->getMessage()
                            . ' Verifiez que la table rh_campagne_evaluation et rh_critere_evaluation existent'
                            . ' (php bin/console app:rh:installer --etat).';
                    }

                    if ($erreurs === []) {
                        $this->addFlash('success', 'La campagne "' . $campagne->getLibelle() . '" est ouverte.');

                        return $this->redirectToRoute('rh_evaluations_campagne', ['id' => $campagne->getId()]);
                    }

                    // En cas d\'echec, on detache l\'entite pour reafficher le formulaire proprement.
                    if ($this->em->contains($campagne)) {
                        $this->em->detach($campagne);
                    }
                }
            }
        }

        return $this->render('rh/evaluations/campagne_form.html.twig', [
            'campagne' => $campagne,
            'donnees' => $donnees,
            'erreurs' => $erreurs,
            'estCreation' => true,
        ]);
    }

    #[Route('/campagnes/{id}', name: '_campagne', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function campagne(string $id): Response
    {
        if (!$this->acces->peutEvaluer()) {
            throw $this->createAccessDeniedException('Les evaluations sont reservees aux responsables et au service RH.');
        }

        $campagne = $this->em->getRepository(CampagneEvaluation::class)->find($id);
        if ($campagne === null) {
            throw $this->createNotFoundException('Campagne introuvable.');
        }

        $fiches = [];
        foreach ($this->evaluations->findParCampagne($campagne) as $fiche) {
            $fiches[(string) $fiche->getEmploye()?->getId()] = $fiche;
        }

        return $this->render('rh/evaluations/campagne.html.twig', [
            'campagne' => $campagne,
            'fiches' => $fiches,
            'employes' => $this->employes->findActifs(),
            'moyenne' => $this->evaluations->moyenneCampagne($campagne),
            'parStatut' => $this->evaluations->compterParStatut($campagne),
            'statuts' => Evaluation::STATUTS,
            'estRh' => $this->acces->estRh(),
        ]);
    }

    #[Route('/campagnes/{id}/saisie/{employeId}', name: '_saisie', methods: ['GET', 'POST'], requirements: ['id' => '[0-9a-fA-F-]{36}', 'employeId' => '[0-9a-fA-F-]{36}'])]
    public function saisie(Request $request, string $id, string $employeId): Response
    {
        if (!$this->acces->peutEvaluer()) {
            throw $this->createAccessDeniedException('La saisie des evaluations est reservee aux responsables et au service RH.');
        }

        $campagne = $this->em->getRepository(CampagneEvaluation::class)->find($id);
        $employe = $this->employes->find($employeId);

        if ($campagne === null || $employe === null) {
            throw $this->createNotFoundException('Campagne ou employe introuvable.');
        }

        $evaluation = $this->evaluations->findUne($campagne, $employe)
            ?? (new Evaluation())->setCampagne($campagne)->setEmploye($employe);

        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('rh_evaluation_' . $id . '_' . $employeId, (string) $request->request->get('_token'))) {
                $erreurs[] = 'Le formulaire a expire. Rechargez la page puis recommencez.';
            } else {
                $notes = $request->request->all('notes');
                $nettoyees = [];
                foreach ($campagne->getCriteres() as $critere) {
                    $cle = (string) $critere->getId();
                    $valeur = $notes[$cle] ?? '';
                    if ($valeur === '') {
                        continue;
                    }
                    if (!preg_match('/^[0-5]$/', (string) $valeur)) {
                        $erreurs[] = 'La note du critere "' . $critere->getLibelle() . '" doit etre comprise entre 0 et 5.';
                        continue;
                    }
                    $nettoyees[$cle] = (int) $valeur;
                }

                if ($nettoyees === []) {
                    $erreurs[] = 'Renseignez au moins une note.';
                }

                if ($erreurs === []) {
                    $evaluation
                        ->setType((string) $request->request->get('type', Evaluation::SUPERIEUR))
                        ->setNotes($nettoyees)
                        ->setCommentaire(trim((string) $request->request->get('commentaire', '')) ?: null)
                        ->setPlanDeveloppement(trim((string) $request->request->get('plan_developpement', '')) ?: null)
                        ->setEvaluateur($this->acces->utilisateur())
                        ->setStatut((string) $request->request->get('statut', Evaluation::BROUILLON))
                        ->touch();

                    $evaluation->setNoteGlobale($evaluation->calculerNoteGlobale());

                    if ($evaluation->getId() === null) {
                        $this->em->persist($evaluation);
                    }
                    $this->em->flush();

                    $this->addFlash('success', 'L\'evaluation de ' . $employe->getNomComplet() . ' est enregistree.');

                    return $this->redirectToRoute('rh_evaluations_campagne', ['id' => $id]);
                }
            }
        }

        return $this->render('rh/evaluations/saisie.html.twig', [
            'campagne' => $campagne,
            'employe' => $employe,
            'evaluation' => $evaluation,
            'erreurs' => $erreurs,
            'types' => Evaluation::TYPES,
            'statuts' => Evaluation::STATUTS,
        ]);
    }

    #[Route('/campagnes/{id}/cloturer', name: '_cloturer', methods: ['POST'])]
    public function cloturer(Request $request, string $id): Response
    {
        $this->acces->exigerRh();

        $campagne = $this->em->getRepository(CampagneEvaluation::class)->find($id);
        if ($campagne === null) {
            throw $this->createNotFoundException('Campagne introuvable.');
        }

        if (!$this->isCsrfTokenValid('rh_campagne_cloture_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Le formulaire a expire. Rechargez la page puis recommencez.');
            return $this->redirectToRoute('rh_evaluations_campagne', ['id' => $id]);
        }

        $campagne->setStatut(CampagneEvaluation::CLOTUREE);
        $this->em->flush();
        $this->addFlash('success', 'La campagne "' . $campagne->getLibelle() . '" est cloturee.');

        return $this->redirectToRoute('rh_evaluations_campagne', ['id' => $id]);
    }

    #[Route('/campagnes/{id}/pdf', name: '_campagne_pdf', methods: ['GET'])]
    public function campagnePdf(string $id, pdfService $pdf): Response
    {
        if (!$this->acces->peutEvaluer()) {
            throw $this->createAccessDeniedException('Les evaluations sont reservees aux responsables et au service RH.');
        }

        $campagne = $this->em->getRepository(CampagneEvaluation::class)->find($id);
        if ($campagne === null) {
            throw $this->createNotFoundException('Campagne introuvable.');
        }

        $lignes = [];
        foreach ($this->evaluations->findParCampagne($campagne) as $fiche) {
            $lignes[] = [
                $fiche->getEmploye()?->getMatricule() ?? '-',
                $fiche->getEmploye()?->getNomComplet() ?? '-',
                $fiche->getEmploye()?->getService()?->getLibelle() ?? '-',
                $fiche->getTypeLibelle(),
                $fiche->getNoteGlobale() !== null ? number_format((float) $fiche->getNoteGlobale(), 2, ',', ' ') . ' / 5' : '-',
                $fiche->getStatutLibelle(),
            ];
        }

        $moyenne = $this->evaluations->moyenneCampagne($campagne);
        $criteres = [];
        foreach ($campagne->getCriteres() as $critere) {
            $criteres[] = [$critere->getLibelle(), 'Poids ' . $critere->getPoids()];
        }

        $tableaux = [[
            'titre' => 'Fiches d\'evaluation',
            'entetes' => ['Matricule', 'Employe', 'Service', 'Type', 'Note', 'Statut'],
            'poids' => [0.13, 0.30, 0.22, 0.15, 0.10, 0.10],
            'lignes' => $lignes,
            'align' => ['L', 'L', 'L', 'L', 'C', 'C'],
        ]];

        if ($criteres !== []) {
            $tableaux[] = [
                'titre' => 'Criteres de la campagne',
                'entetes' => ['Critere', 'Ponderations'],
                'poids' => [0.7, 0.3],
                'lignes' => $criteres,
                'align' => ['L', 'C'],
            ];
        }

        $contenu = $pdf->genererRapport(
            'Campagne d\'evaluation',
            $campagne->getLibelle() . ' - ' . $campagne->getAnnee(),
            [
                ['Statut', $campagne->getStatutLibelle()],
                ['Periode', $campagne->getDateDebut()?->format('d/m/Y') . ' au ' . $campagne->getDateFin()?->format('d/m/Y')],
                ['Note moyenne', $moyenne !== null ? number_format($moyenne, 2, ',', ' ') . ' / 5' : 'Non disponible'],
            ],
            $tableaux,
            'L'
        );

        return new Response($contenu, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="campagne-evaluation.pdf"',
        ]);
    }

    private function appliquerCampagne(CampagneEvaluation $campagne, array $donnees): array
    {
        $erreurs = [];

        $libelle = trim((string) ($donnees['libelle'] ?? ''));
        $annee = (int) ($donnees['annee'] ?? 0);
        $debut = $this->date($donnees['date_debut'] ?? '');
        $fin = $this->date($donnees['date_fin'] ?? '');

        if ($libelle === '') {
            $erreurs[] = 'Le libelle de la campagne est obligatoire.';
        }
        if ($annee < 2000 || $annee > 2100) {
            $erreurs[] = 'L\'annee "' . $annee . '" n\'est pas valide.';
        }
        if ($debut === null || $fin === null) {
            $erreurs[] = 'Indiquez la periode de la campagne.';
        }
        if ($debut !== null && $fin !== null && $fin < $debut) {
            $erreurs[] = 'La date de fin doit etre posterieure a la date de debut.';
        }

        $libelles = (array) ($donnees['criteres_libelle'] ?? []);
        $poids = (array) ($donnees['criteres_poids'] ?? []);
        $criteres = [];

        foreach ($libelles as $index => $valeur) {
            $valeur = trim((string) $valeur);
            if ($valeur === '') {
                continue;
            }
            $poidsCritere = (int) ($poids[$index] ?? 1);
            $criteres[] = [mb_substr($valeur, 0, 150), max(1, min(10, $poidsCritere))];
        }

        if ($criteres === []) {
            $erreurs[] = 'Ajoutez au moins un critere d\'evaluation.';
        }

        if ($erreurs !== []) {
            return $erreurs;
        }

        $campagne
            ->setLibelle($libelle)
            ->setAnnee($annee)
            ->setDateDebut($debut)
            ->setDateFin($fin)
            ->setObjectifs(trim((string) ($donnees['objectifs'] ?? '')) ?: null);

        // Mise a jour en place : on reutilise les criteres existants, on
        // supprime seulement ceux qui ne servent plus et on ajoute les nouveaux.
        // Cela evite les erreurs Doctrine liees a un remplacement massif de
        // collection pendant le flush.
        $existants = $campagne->getCriteres()->toArray();
        $ordre = 1;

        foreach ($criteres as $position => [$critereLibelle, $criterePoids]) {
            $critere = $existants[$position] ?? null;
            if ($critere === null) {
                $critere = new CritereEvaluation();
                $campagne->addCritere($critere);
            }

            $critere->setLibelle($critereLibelle)->setPoids($criterePoids)->setOrdre($ordre++);
            unset($existants[$position]);
        }

        foreach ($existants as $enTrop) {
            $campagne->removeCritere($enTrop);
        }

        return [];
    }

    private function date(mixed $valeur): ?\DateTimeInterface
    {
        $valeur = trim((string) $valeur);
        if ($valeur === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $valeur);

        return $date instanceof \DateTimeImmutable ? $date : null;
    }
}
