<?php

namespace App\Controller\Rh;

use App\Entity\Rh\Candidat;
use App\Entity\Rh\Candidature;
use App\Entity\Rh\DocumentRh;
use App\Entity\Rh\OffreEmploi;
use App\Entity\Services;
use App\Repository\DirectionsRepository;
use App\Repository\Rh\CandidatureRepository;
use App\Repository\Rh\DocumentRhRepository;
use App\Repository\Rh\OffreEmploiRepository;
use App\Repository\ServicesRepository;
use App\Security\RhAcces;
use App\Service\Rh\ReferencesRh;
use App\Service\Rh\StockageDocumentRh;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Recrutement : offres (brouillon, soumission, validation DRH, publication),
 * candidats, candidatures et suivi des entretiens.
 */
#[Route('/rh/recrutement', name: 'rh_recrutement')]
#[IsGranted('ROLE_USER')]
final class RecrutementRhController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private OffreEmploiRepository $offres,
        private CandidatureRepository $candidatures,
        private DocumentRhRepository $documents,
        private DirectionsRepository $directions,
        private ServicesRepository $services,
        private ReferencesRh $references,
        private StockageDocumentRh $stockage,
        private RhAcces $acces,
    ) {}

    #[Route('', name: '', methods: ['GET'])]
    public function index(): Response
    {
        if (!$this->acces->estRh() && !$this->acces->aRole('ROLE_MANAGER', 'ROLE_CHEF_SERVICE')) {
            throw $this->createAccessDeniedException('Le recrutement est reserve au service RH et aux responsables.');
        }

        return $this->render('rh/recrutement/index.html.twig', [
            'offres' => $this->offres->findToutes(),
            'aValider' => $this->offres->findAValider(),
            'publiees' => $this->offres->findPubliees(),
            'pipeline' => $this->candidatures->compterParEtape(),
            'enCours' => $this->candidatures->findEnCours(),
            'estRh' => $this->acces->estRh(),
        ]);
    }

    // ------------------------------------------------------------------
    //  Offres
    // ------------------------------------------------------------------

    #[Route('/offres/nouvelle', name: '_offre_new', methods: ['GET', 'POST'])]
    public function offreNew(Request $request): Response
    {
        $this->acces->exigerRh();

        $offre = (new OffreEmploi())
            ->setReference($this->references->offre())
            ->setStatut(OffreEmploi::BROUILLON);

        $donnees = $this->donneesOffre($offre);
        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('rh_offre', (string) $request->request->get('_token'))) {
                $erreurs[] = 'Le formulaire a expire. Rechargez la page puis recommencez.';
            } else {
                $donnees = $request->request->all();
                $erreurs = $this->appliquerOffre($offre, $donnees);

                if ($erreurs === []) {
                    $this->em->persist($offre);
                    $this->em->flush();
                    $this->addFlash('success', 'L\'offre ' . $offre->getReference() . ' est enregistree en brouillon.');

                    return $this->redirectToRoute('rh_recrutement_offre', ['id' => $offre->getId()]);
                }
            }
        }

        return $this->render('rh/recrutement/offre_form.html.twig', [
            'offre' => $offre,
            'donnees' => $donnees,
            'erreurs' => $erreurs,
            'directions' => $this->directions->findBy([], ['libelle' => 'ASC']),
            'services' => $this->services->findBy([], ['libelle' => 'ASC']),
            'estCreation' => true,
        ]);
    }

    #[Route('/offres/{id}/modifier', name: '_offre_edit', methods: ['GET', 'POST'])]
    public function offreEdit(Request $request, string $id): Response
    {
        $this->acces->exigerRh();

        $offre = $this->offres->find($id);
        if ($offre === null) {
            throw $this->createNotFoundException('Offre introuvable.');
        }

        if (in_array($offre->getStatut(), [OffreEmploi::PUBLIEE, OffreEmploi::CLOTUREE], true)) {
            $this->addFlash('danger', 'Une offre publiee ou cloturee ne peut plus etre modifiee.');
            return $this->redirectToRoute('rh_recrutement_offre', ['id' => $id]);
        }

        $donnees = $this->donneesOffre($offre);
        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('rh_offre', (string) $request->request->get('_token'))) {
                $erreurs[] = 'Le formulaire a expire. Rechargez la page puis recommencez.';
            } else {
                $donnees = $request->request->all();
                $erreurs = $this->appliquerOffre($offre, $donnees);

                if ($erreurs === []) {
                    $this->em->flush();
                    $this->addFlash('success', 'L\'offre ' . $offre->getReference() . ' a ete mise a jour.');

                    return $this->redirectToRoute('rh_recrutement_offre', ['id' => $id]);
                }
            }
        }

        return $this->render('rh/recrutement/offre_form.html.twig', [
            'offre' => $offre,
            'donnees' => $donnees,
            'erreurs' => $erreurs,
            'directions' => $this->directions->findBy([], ['libelle' => 'ASC']),
            'services' => $this->services->findBy([], ['libelle' => 'ASC']),
            'estCreation' => false,
        ]);
    }

    #[Route('/offres/{id}', name: '_offre', methods: ['GET'])]
    public function offre(string $id): Response
    {
        if (!$this->acces->estRh() && !$this->acces->aRole('ROLE_MANAGER', 'ROLE_CHEF_SERVICE')) {
            throw $this->createAccessDeniedException('Cette offre est reservee au service RH et aux responsables.');
        }

        $offre = $this->offres->find($id);
        if ($offre === null) {
            throw $this->createNotFoundException('Offre introuvable.');
        }

        return $this->render('rh/recrutement/offre.html.twig', [
            'offre' => $offre,
            'candidatures' => $this->candidatures->findParOffre($offre),
            'estRh' => $this->acces->estRh(),
            'statuts' => OffreEmploi::STATUTS,
        ]);
    }

    /** Changement d'etape du circuit de validation de l'offre. */
    #[Route('/offres/{id}/statut', name: '_offre_statut', methods: ['POST'])]
    public function offreStatut(Request $request, string $id): Response
    {
        $this->acces->exigerRh();

        $offre = $this->offres->find($id);
        if ($offre === null) {
            throw $this->createNotFoundException('Offre introuvable.');
        }

        if (!$this->isCsrfTokenValid('rh_offre_statut_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Le formulaire a expire. Rechargez la page puis recommencez.');
            return $this->redirectToRoute('rh_recrutement_offre', ['id' => $id]);
        }

        $action = (string) $request->request->get('action', '');
        $erreur = null;

        switch ($action) {
            case 'soumettre':
                if ($offre->getStatut() !== OffreEmploi::BROUILLON) {
                    $erreur = 'Seule une offre en brouillon peut etre soumise.';
                    break;
                }
                $offre->setStatut(OffreEmploi::SOUMISE);
                $this->addFlash('success', 'L\'offre ' . $offre->getReference() . ' est soumise a la validation de la DRH.');
                break;

            case 'valider':
                if ($offre->getStatut() !== OffreEmploi::SOUMISE) {
                    $erreur = 'Seule une offre soumise peut etre validee.';
                    break;
                }
                $offre->setStatut(OffreEmploi::VALIDEE_DRH);
                $this->addFlash('success', 'L\'offre ' . $offre->getReference() . ' est validee. Vous pouvez la publier.');
                break;

            case 'publier':
                if (!in_array($offre->getStatut(), [OffreEmploi::VALIDEE_DRH, OffreEmploi::SOUMISE], true)) {
                    $erreur = 'L\'offre doit etre validee avant publication.';
                    break;
                }
                $offre->setStatut(OffreEmploi::PUBLIEE)
                    ->setDatePublication(new \DateTimeImmutable('today'));
                $this->addFlash('success', 'L\'offre ' . $offre->getReference() . ' est publiee.');
                break;

            case 'cloturer':
                $offre->setStatut(OffreEmploi::CLOTUREE)
                    ->setDateCloture(new \DateTimeImmutable('today'));
                $this->addFlash('success', 'L\'offre ' . $offre->getReference() . ' est cloturee.');
                break;

            case 'refuser':
                $offre->setStatut(OffreEmploi::REFUSEE);
                $this->addFlash('warning', 'L\'offre ' . $offre->getReference() . ' est refusee.');
                break;

            default:
                $erreur = 'Action inconnue : ' . $action . '.';
        }

        if ($erreur !== null) {
            $this->addFlash('danger', $erreur);
            return $this->redirectToRoute('rh_recrutement_offre', ['id' => $id]);
        }

        $this->em->flush();

        return $this->redirectToRoute('rh_recrutement_offre', ['id' => $id]);
    }

    // ------------------------------------------------------------------
    //  Candidatures
    // ------------------------------------------------------------------

    #[Route('/candidatures/nouvelle', name: '_candidature_new', methods: ['GET', 'POST'])]
    public function candidatureNew(Request $request): Response
    {
        $this->acces->exigerRh();

        $candidature = (new Candidature())
            ->setReference($this->references->candidature())
            ->setDateSoumission(new \DateTimeImmutable('today'))
            ->setStatut(Candidature::RECUE);

        $offreId = (string) $request->query->get('offre', '');
        if ($offreId !== '' && ($offre = $this->offres->find($offreId)) !== null) {
            $candidature->setOffre($offre);
        }

        $donnees = $this->donneesCandidature($candidature);
        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('rh_candidature', (string) $request->request->get('_token'))) {
                $erreurs[] = 'Le formulaire a expire. Rechargez la page puis recommencez.';
            } else {
                $donnees = $request->request->all();
                $erreurs = $this->appliquerCandidature($candidature, $donnees, $request->files->get('cv'));

                if ($erreurs === []) {
                    $this->em->persist($candidature);
                    $this->em->flush();
                    $this->addFlash('success', 'La candidature ' . $candidature->getReference() . ' est enregistree.');

                    return $this->redirectToRoute('rh_recrutement_candidature', ['id' => $candidature->getId()]);
                }
            }
        }

        return $this->render('rh/recrutement/candidature_form.html.twig', [
            'candidature' => $candidature,
            'donnees' => $donnees,
            'erreurs' => $erreurs,
            'offres' => $this->offres->findPubliees(),
            'estCreation' => true,
        ]);
    }

    #[Route('/candidatures/{id}', name: '_candidature', methods: ['GET', 'POST'])]
    public function candidature(Request $request, string $id): Response
    {
        if (!$this->acces->estRh() && !$this->acces->aRole('ROLE_MANAGER', 'ROLE_CHEF_SERVICE')) {
            throw $this->createAccessDeniedException('Cette candidature est reservee au service RH et aux responsables.');
        }

        $candidature = $this->candidatures->find($id);
        if ($candidature === null) {
            throw $this->createNotFoundException('Candidature introuvable.');
        }

        $erreurs = [];

        if ($request->isMethod('POST')) {
            $this->acces->exigerRh();

            if (!$this->isCsrfTokenValid('rh_candidature_suivi_' . $id, (string) $request->request->get('_token'))) {
                $erreurs[] = 'Le formulaire a expire. Rechargez la page puis recommencez.';
            } else {
                $erreurs = $this->suivreCandidature($candidature, $request);
                if ($erreurs === []) {
                    $this->em->flush();
                    $this->addFlash('success', 'Le suivi de la candidature est mis a jour.');
                    return $this->redirectToRoute('rh_recrutement_candidature', ['id' => $id]);
                }
            }
        }

        return $this->render('rh/recrutement/candidature.html.twig', [
            'candidature' => $candidature,
            'documents' => $this->documents->findPourCandidat($candidature->getCandidat()),
            'erreurs' => $erreurs,
            'statuts' => Candidature::STATUTS,
            'pipeline' => Candidature::PIPELINE,
            'estRh' => $this->acces->estRh(),
        ]);
    }

    private function suivreCandidature(Candidature $candidature, Request $request): array
    {
        $statut = (string) $request->request->get('statut', $candidature->getStatut());
        if (!isset(Candidature::STATUTS[$statut])) {
            return ['Le statut "' . $statut . '" n\'existe pas.'];
        }

        $noteTests = $this->entier($request->request->get('note_tests'));
        $noteEntretien = $this->entier($request->request->get('note_entretien'));
        if ($noteTests !== null && ($noteTests < 0 || $noteTests > 100)) {
            return ['La note des tests doit etre comprise entre 0 et 100.'];
        }
        if ($noteEntretien !== null && ($noteEntretien < 0 || $noteEntretien > 100)) {
            return ['La note d\'entretien doit etre comprise entre 0 et 100.'];
        }

        $dateEntretien = $this->dateHeure($request->request->get('date_entretien', ''));

        $candidature
            ->setStatut($statut)
            ->setNoteTests($noteTests)
            ->setDateEntretien($dateEntretien)
            ->setEvaluateurs(trim((string) $request->request->get('evaluateurs', '')) ?: null)
            ->setNoteEntretien($noteEntretien)
            ->setCompteRenduEntretien(trim((string) $request->request->get('compte_rendu', '')) ?: null)
            ->setObservations(trim((string) $request->request->get('observations', '')) ?: null);

        $fichier = $request->files->get('compte_rendu_fichier');
        if ($fichier instanceof UploadedFile) {
            $document = (new DocumentRh())
                ->setCategorie(DocumentRh::CATEGORIE_RECRUTEMENT)
                ->setType('compte_rendu_entretien')
                ->setTitre('Compte rendu d\'entretien - ' . ($candidature->getCandidat()?->getNomComplet() ?? ''))
                ->setCandidat($candidature->getCandidat())
                ->setDeposePar($this->acces->utilisateur());

            try {
                $this->stockage->stocker($fichier, $document);
                $this->em->persist($document);
            } catch (\RuntimeException $e) {
                return [$e->getMessage()];
            }
        }

        return [];
    }

    private function donneesOffre(OffreEmploi $offre): array
    {
        return [
            'reference' => $offre->getReference(),
            'intitule' => $offre->getIntitule(),
            'description' => $offre->getDescription(),
            'profil' => $offre->getProfil(),
            'nombre_postes' => $offre->getNombrePostes(),
            'direction' => (string) $offre->getDirection()?->getId(),
            'service' => (string) $offre->getService()?->getId(),
            'date_cloture' => $offre->getDateCloture()?->format('Y-m-d'),
        ];
    }

    private function appliquerOffre(OffreEmploi $offre, array $donnees): array
    {
        $erreurs = [];
        $intitule = trim((string) ($donnees['intitule'] ?? ''));
        $nombrePostes = $this->entier($donnees['nombre_postes'] ?? null) ?? 1;

        if ($intitule === '') {
            $erreurs[] = 'L\'intitule du poste est obligatoire.';
        }
        if ($nombrePostes < 1) {
            $erreurs[] = 'Le nombre de postes doit etre au moins egal a 1.';
        }

        $direction = null;
        $directionId = (string) ($donnees['direction'] ?? '');
        if ($directionId !== '') {
            $direction = $this->directions->find($directionId);
        }

        $service = null;
        $serviceId = (string) ($donnees['service'] ?? '');
        if ($serviceId !== '') {
            $service = $this->services->find($serviceId);
        }

        if ($erreurs !== []) {
            return $erreurs;
        }

        $offre
            ->setReference(trim((string) ($donnees['reference'] ?? $offre->getReference())))
            ->setIntitule($intitule)
            ->setDescription(trim((string) ($donnees['description'] ?? '')) ?: null)
            ->setProfil(trim((string) ($donnees['profil'] ?? '')) ?: null)
            ->setNombrePostes($nombrePostes)
            ->setDirection($direction)
            ->setService($service)
            ->setDateCloture($this->date($donnees['date_cloture'] ?? ''));

        return [];
    }

    private function donneesCandidature(Candidature $candidature): array
    {
        return [
            'offre' => (string) $candidature->getOffre()?->getId(),
            'nom' => $candidature->getCandidat()?->getNom(),
            'prenoms' => $candidature->getCandidat()?->getPrenoms(),
            'email' => $candidature->getCandidat()?->getEmail(),
            'telephone' => $candidature->getCandidat()?->getTelephone(),
            'adresse' => $candidature->getCandidat()?->getAdresse(),
            'diplomes' => $candidature->getCandidat()?->getDiplomes(),
            'experiences' => $candidature->getCandidat()?->getExperiences(),
            'observations' => $candidature->getObservations(),
        ];
    }

    private function appliquerCandidature(Candidature $candidature, array $donnees, mixed $cv): array
    {
        $erreurs = [];

        $offre = $this->offres->find((string) ($donnees['offre'] ?? ''));
        $nom = trim((string) ($donnees['nom'] ?? ''));
        $prenoms = trim((string) ($donnees['prenoms'] ?? ''));
        $email = trim((string) ($donnees['email'] ?? ''));
        $telephone = trim((string) ($donnees['telephone'] ?? ''));

        if ($offre === null) {
            $erreurs[] = 'Choisissez l\'offre a laquelle le candidat postule.';
        }
        if ($nom === '' || $prenoms === '') {
            $erreurs[] = 'Le nom et les prenoms du candidat sont obligatoires.';
        }
        if ($email === '' && $telephone === '') {
            $erreurs[] = 'Indiquez au moins un contact : email ou telephone.';
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $erreurs[] = 'L\'adresse email "' . $email . '" n\'est pas valide.';
        }

        if ($erreurs !== []) {
            return $erreurs;
        }

        $candidat = $candidature->getCandidat() ?? new Candidat();
        $candidat
            ->setNom($nom)
            ->setPrenoms($prenoms)
            ->setEmail($email ?: null)
            ->setTelephone($telephone ?: null)
            ->setAdresse(trim((string) ($donnees['adresse'] ?? '')) ?: null)
            ->setDiplomes(trim((string) ($donnees['diplomes'] ?? '')) ?: null)
            ->setExperiences(trim((string) ($donnees['experiences'] ?? '')) ?: null);

        $candidature
            ->setOffre($offre)
            ->setCandidat($candidat)
            ->setObservations(trim((string) ($donnees['observations'] ?? '')) ?: null);

        if ($candidat->getId() === null) {
            $this->em->persist($candidat);
        }

        if ($cv instanceof UploadedFile) {
            $document = (new DocumentRh())
                ->setCategorie(DocumentRh::CATEGORIE_RECRUTEMENT)
                ->setType('cv')
                ->setTitre('CV - ' . $candidat->getNomComplet())
                ->setCandidat($candidat)
                ->setDeposePar($this->acces->utilisateur());

            try {
                $this->stockage->stocker($cv, $document);
                $this->em->persist($document);
            } catch (\RuntimeException $e) {
                return [$e->getMessage()];
            }
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

    private function dateHeure(mixed $valeur): ?\DateTimeInterface
    {
        $valeur = trim((string) $valeur);
        if ($valeur === '') {
            return null;
        }

        foreach (['Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $valeur);
            if ($date instanceof \DateTimeImmutable) {
                return $date;
            }
        }

        return null;
    }

    private function entier(mixed $valeur): ?int
    {
        $valeur = trim((string) $valeur);
        if ($valeur === '' || !preg_match('/^-?\d+$/', $valeur)) {
            return null;
        }

        return (int) $valeur;
    }
}
