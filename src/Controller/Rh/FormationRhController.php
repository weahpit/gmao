<?php

namespace App\Controller\Rh;

use App\Entity\Rh\Formation;
use App\Entity\Rh\ParticipationFormation;
use App\Repository\Rh\EmployeRepository;
use App\Repository\Rh\FormationRepository;
use App\Security\RhAcces;
use App\Service\Rh\StockageDocumentRh;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Plan de formation : actions, participants, resultats et couts.
 */
#[Route('/rh/formations', name: 'rh_formations')]
#[IsGranted('ROLE_USER')]
final class FormationRhController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private FormationRepository $formations,
        private EmployeRepository $employes,
        private RhAcces $acces,
        private StockageDocumentRh $stockage,
    ) {}

    #[Route('', name: '', methods: ['GET'])]
    public function index(): Response
    {
        if (!$this->acces->estRh() && !$this->acces->aRole('ROLE_CHEF_SERVICE', 'ROLE_MANAGER')) {
            throw $this->createAccessDeniedException('Le plan de formation est reserve au service RH et aux responsables.');
        }

        $annee = (int) date('Y');

        return $this->render('rh/formations/index.html.twig', [
            'formations' => $this->formations->findToutes(),
            'aVenir' => $this->formations->findAVenir(),
            'coutAnnee' => $this->acces->estRh() ? $this->formations->coutTotalAnnee($annee) : null,
            'annee' => $annee,
            'statuts' => Formation::STATUTS,
            'estRh' => $this->acces->estRh(),
        ]);
    }

    #[Route('/nouvelle', name: '_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->acces->exigerRh();

        $formation = (new Formation())->setStatut(Formation::PLANIFIEE);
        $donnees = $this->donnees($formation);
        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('rh_formation', (string) $request->request->get('_token'))) {
                $erreurs[] = 'Le formulaire a expire. Rechargez la page puis recommencez.';
            } else {
                $donnees = $this->lireFormulaire($request) + [
                    'support_nom' => $formation->getSupportNom(),
                    'liste_presences_nom' => $formation->getListePresencesNom(),
                ];
                $erreurs = $this->appliquer($formation, $donnees);

                if ($erreurs === []) {
                    $erreurs = $this->enregistrerDocuments($formation, $request);
                }

                if ($erreurs === []) {
                    $this->em->persist($formation);
                    $this->em->flush();
                    $this->addFlash('success', 'La formation "' . $formation->getIntitule() . '" est enregistree.');

                    return $this->redirectToRoute('rh_formations_detail', ['id' => $formation->getId()]);
                }
            }
        }

        return $this->render('rh/formations/form.html.twig', [
            'formation' => $formation,
            'donnees' => $donnees,
            'erreurs' => $erreurs,
            'statuts' => Formation::STATUTS,
            'estCreation' => true,
        ]);
    }

    #[Route('/{id}/modifier', name: '_edit', methods: ['GET', 'POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function edit(Request $request, string $id): Response
    {
        $this->acces->exigerRh();

        $formation = $this->formations->find($id);
        if ($formation === null) {
            throw $this->createNotFoundException('Formation introuvable.');
        }

        $donnees = $this->donnees($formation);
        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('rh_formation', (string) $request->request->get('_token'))) {
                $erreurs[] = 'Le formulaire a expire. Rechargez la page puis recommencez.';
            } else {
                $donnees = $this->lireFormulaire($request) + [
                    'support_nom' => $formation->getSupportNom(),
                    'liste_presences_nom' => $formation->getListePresencesNom(),
                ];
                $erreurs = $this->appliquer($formation, $donnees);

                if ($erreurs === []) {
                    $erreurs = $this->enregistrerDocuments($formation, $request);
                }

                if ($erreurs === []) {
                    $this->em->flush();
                    $this->addFlash('success', 'La formation "' . $formation->getIntitule() . '" a ete mise a jour.');

                    return $this->redirectToRoute('rh_formations_detail', ['id' => $id]);
                }
            }
        }

        return $this->render('rh/formations/form.html.twig', [
            'formation' => $formation,
            'donnees' => $donnees,
            'erreurs' => $erreurs,
            'statuts' => Formation::STATUTS,
            'estCreation' => false,
        ]);
    }

    #[Route('/{id}', name: '_detail', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function detail(string $id): Response
    {
        $formation = $this->formations->find($id);
        if ($formation === null) {
            throw $this->createNotFoundException('Formation introuvable.');
        }

        $inscrits = [];
        foreach ($formation->getParticipations() as $participation) {
            if ($participation->getEmploye() !== null) {
                $inscrits[(string) $participation->getEmploye()->getId()] = true;
            }
        }

        $documents = [];
        foreach ([
            'support' => ['Support de presentation', $formation->getSupport(), $formation->getSupportNom()],
            'liste' => ['Liste de presences', $formation->getListePresences(), $formation->getListePresencesNom()],
        ] as $cle => [$libelle, $chemin, $nom]) {
            $documents[$cle] = [
                'libelle' => $libelle,
                'chemin' => $chemin,
                'nom' => $nom ?: basename((string) $chemin),
                'existe' => $chemin !== null && $this->stockage->cheminFormation($chemin) !== null,
                'extension' => strtoupper((string) pathinfo((string) $chemin, PATHINFO_EXTENSION)),
            ];
        }

        return $this->render('rh/formations/detail.html.twig', [
            'formation' => $formation,
            'documents' => $documents,
            'participations' => $formation->getParticipations(),
            'employes' => $this->employes->findActifs(),
            'inscrits' => $inscrits,
            'resultats' => ParticipationFormation::RESULTATS,
            'estRh' => $this->acces->estRh(),
        ]);
    }

    #[Route('/{id}/document/{type}/supprimer', name: '_document_supprimer', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}', 'type' => 'support|liste'])]
    public function supprimerDocument(Request $request, string $id, string $type): Response
    {
        $this->acces->exigerRh();

        $formation = $this->formations->find($id);
        if ($formation === null) {
            throw $this->createNotFoundException('Formation introuvable.');
        }

        if (!$this->isCsrfTokenValid('rh_formation_doc_' . $id . '_' . $type, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Le formulaire a expire. Rechargez la page puis recommencez.');

            return $this->redirectToRoute('rh_formations_detail', ['id' => $id]);
        }

        if ($type === 'support') {
            $this->stockage->supprimerDocumentFormation($formation->getSupport());
            $formation->setSupport(null)->setSupportNom(null);
            $this->addFlash('success', 'Le support de presentation a ete supprime.');
        } elseif ($type === 'liste') {
            $this->stockage->supprimerDocumentFormation($formation->getListePresences());
            $formation->setListePresences(null)->setListePresencesNom(null);
            $this->addFlash('success', 'La liste de presences a ete supprimee.');
        } else {
            $this->addFlash('danger', 'Type de document inconnu : "' . $type . '".');
        }

        $this->em->flush();

        return $this->redirectToRoute('rh_formations_detail', ['id' => $id]);
    }

    #[Route('/{id}/participants', name: '_participants', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function participants(Request $request, string $id): Response
    {
        $this->acces->exigerRh();

        $formation = $this->formations->find($id);
        if ($formation === null) {
            throw $this->createNotFoundException('Formation introuvable.');
        }

        if (!$this->isCsrfTokenValid('rh_formation_participants_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Le formulaire a expire. Rechargez la page puis recommencez.');
            return $this->redirectToRoute('rh_formations_detail', ['id' => $id]);
        }

        $employesChoisis = array_filter(array_map('strval', (array) $request->request->all('employes')));
        if ($employesChoisis === []) {
            $this->addFlash('warning', 'Aucun employe n\'a ete selectionne.');
            return $this->redirectToRoute('rh_formations_detail', ['id' => $id]);
        }

        $existants = [];
        foreach ($formation->getParticipations() as $participation) {
            if ($participation->getEmploye() !== null) {
                $existants[(string) $participation->getEmploye()->getId()] = true;
            }
        }

        $ajoutes = 0;
        $ignores = 0;
        foreach ($employesChoisis as $employeId) {
            if (isset($existants[$employeId])) {
                ++$ignores;
                continue;
            }

            $employe = $this->employes->find($employeId);
            if ($employe === null) {
                continue;
            }

            $formation->addParticipation((new ParticipationFormation())->setEmploye($employe));
            ++$ajoutes;
        }

        if ($ajoutes === 0) {
            $this->addFlash('warning', 'Aucun nouvel inscrit : ' . ($ignores > 0 ? 'ces employes sont deja inscrits.' : 'employe introuvable.'));

            return $this->redirectToRoute('rh_formations_detail', ['id' => $id]);
        }

        $this->em->flush();
        $this->addFlash('success', $ajoutes . ' participant(s) ajoute(s).' . ($ignores > 0 ? ' ' . $ignores . ' etaient deja inscrits.' : ''));

        return $this->redirectToRoute('rh_formations_detail', ['id' => $id]);
    }

    #[Route('/{id}/resultat/{participationId}', name: '_resultat', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}', 'participationId' => '[0-9a-fA-F-]{36}'])]
    public function resultat(Request $request, string $id, string $participationId): Response
    {
        $this->acces->exigerRh();

        $formation = $this->formations->find($id);
        if ($formation === null) {
            throw $this->createNotFoundException('Formation introuvable.');
        }

        $participation = $this->em->find(ParticipationFormation::class, $participationId);
        if ($participation === null || $participation->getFormation() !== $formation) {
            throw $this->createNotFoundException('Participant introuvable.');
        }

        if (!$this->isCsrfTokenValid('rh_formation_resultat_' . $participationId, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Le formulaire a expire. Rechargez la page puis recommencez.');
            return $this->redirectToRoute('rh_formations_detail', ['id' => $id]);
        }

        $resultat = (string) $request->request->get('resultat', ParticipationFormation::EN_COURS);
        $note = trim((string) $request->request->get('note', ''));

        if ($note !== '' && (!preg_match('/^\d+$/', $note) || (int) $note > 100)) {
            $this->addFlash('danger', 'La note doit etre un nombre entier entre 0 et 100 (recu : "' . $note . '").');
            return $this->redirectToRoute('rh_formations_detail', ['id' => $id]);
        }

        $participation
            ->setResultat($resultat)
            ->setNote($note !== '' ? (int) $note : null)
            ->setCommentaire(trim((string) $request->request->get('commentaire', '')) ?: null);

        $this->em->flush();
        $this->addFlash('success', 'Le resultat de ' . ($participation->getEmploye()?->getNomComplet() ?? 'l\'employe') . ' est enregistre.');

        return $this->redirectToRoute('rh_formations_detail', ['id' => $id]);
    }

    /**
     * Documents pedagogiques : support de presentation et liste de presences.
     *
     * @return list<string> messages d'erreur (vide si tout est enregistre)
     */
    private function enregistrerDocuments(Formation $formation, Request $request): array
    {
        $erreurs = [];

        $fichiers = [
            'support' => ['fichier' => $request->files->get('support'), 'prefixe' => 'support'],
            'liste_presences' => ['fichier' => $request->files->get('liste_presences'), 'prefixe' => 'presences'],
        ];

        foreach ($fichiers as $cle => $info) {
            /** @var UploadedFile|null $fichier */
            $fichier = $info['fichier'];
            if (!$fichier instanceof UploadedFile) {
                continue;
            }

            try {
                $resultat = $this->stockage->stockerDocumentFormation($fichier, $info['prefixe']);
            } catch (\RuntimeException $e) {
                $erreurs[] = $e->getMessage();

                continue;
            }

            if ($cle === 'support') {
                $this->stockage->supprimerDocumentFormation($formation->getSupport());
                $formation->setSupport($resultat['chemin'])->setSupportNom($resultat['nom']);
            } else {
                $this->stockage->supprimerDocumentFormation($formation->getListePresences());
                $formation->setListePresences($resultat['chemin'])->setListePresencesNom($resultat['nom']);
            }
        }

        return $erreurs;
    }

    private function lireFormulaire(Request $request): array
    {
        return [
            'intitule' => (string) $request->request->get('intitule', ''),
            'organisme' => (string) $request->request->get('organisme', ''),
            'cout' => (string) $request->request->get('cout', ''),
            'date_debut' => (string) $request->request->get('date_debut', ''),
            'date_fin' => (string) $request->request->get('date_fin', ''),
            'nombre_places' => (string) $request->request->get('nombre_places', ''),
            'description' => (string) $request->request->get('description', ''),
            'statut' => (string) $request->request->get('statut', Formation::PLANIFIEE),
        ];
    }

    private function donnees(Formation $formation): array
    {
        return [
            'intitule' => $formation->getIntitule(),
            'organisme' => $formation->getOrganisme(),
            'cout' => $formation->getCout(),
            'date_debut' => $formation->getDateDebut()?->format('Y-m-d'),
            'date_fin' => $formation->getDateFin()?->format('Y-m-d'),
            'nombre_places' => $formation->getNombrePlaces(),
            'description' => $formation->getDescription(),
            'statut' => $formation->getStatut(),
            'support_nom' => $formation->getSupportNom(),
            'liste_presences_nom' => $formation->getListePresencesNom(),
        ];
    }

    private function appliquer(Formation $formation, array $donnees): array
    {
        $erreurs = [];

        $intitule = trim((string) ($donnees['intitule'] ?? ''));
        $debut = $this->date($donnees['date_debut'] ?? '');
        $fin = $this->date($donnees['date_fin'] ?? '');
        $cout = trim((string) ($donnees['cout'] ?? ''));

        if ($intitule === '') {
            $erreurs[] = 'L\'intitule de la formation est obligatoire.';
        }
        if ($debut !== null && $fin !== null && $fin < $debut) {
            $erreurs[] = 'La date de fin doit etre posterieure a la date de debut.';
        }
        if ($cout !== '' && !is_numeric(str_replace([' ', ','], ['', '.'], $cout))) {
            $erreurs[] = 'Le cout "' . $cout . '" doit etre un montant en chiffres.';
        }

        if ($erreurs !== []) {
            return $erreurs;
        }

        $formation
            ->setIntitule($intitule)
            ->setOrganisme(trim((string) ($donnees['organisme'] ?? '')) ?: null)
            ->setCout($cout !== '' ? str_replace([' ', ','], ['', '.'], $cout) : null)
            ->setDateDebut($debut)
            ->setDateFin($fin)
            ->setNombrePlaces((int) ($donnees['nombre_places'] ?? 0))
            ->setDescription(trim((string) ($donnees['description'] ?? '')) ?: null)
            ->setStatut((string) ($donnees['statut'] ?? Formation::PLANIFIEE));

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
