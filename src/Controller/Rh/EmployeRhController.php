<?php

namespace App\Controller\Rh;

use App\Entity\Directions;
use App\Entity\Poste;
use App\Entity\Rh\Affectation;
use App\Entity\Rh\Employe;
use App\Entity\Services;
use App\Entity\User;
use App\Repository\DirectionsRepository;
use App\Repository\PosteRepository;
use App\Repository\Rh\AbsenceRepository;
use App\Repository\Rh\ContratRepository;
use App\Repository\Rh\DocumentRhRepository;
use App\Repository\Rh\EmployeRepository;
use App\Repository\Rh\PointageRepository;
use App\Repository\ServicesRepository;
use App\Repository\UserRepository;
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
 * Dossiers des employes : liste et recherche, creation, modification,
 * photo, fiche complete, changement d'affectation et sortie.
 */
#[Route('/rh/employes', name: 'rh_employes')]
#[IsGranted('ROLE_USER')]
final class EmployeRhController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private EmployeRepository $employes,
        private ContratRepository $contrats,
        private AbsenceRepository $absences,
        private PointageRepository $pointages,
        private DocumentRhRepository $documents,
        private DirectionsRepository $directions,
        private ServicesRepository $services,
        private PosteRepository $postes,
        private UserRepository $users,
        private ReferencesRh $references,
        private StockageDocumentRh $stockage,
        private RhAcces $acces,
    ) {}

    #[Route('', name: '', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->acces->exigerRh();

        $terme = trim((string) $request->query->get('q', ''));
        $statut = (string) $request->query->get('statut', '');
        $serviceId = (string) $request->query->get('service', '');

        $service = null;
        if ($serviceId !== '') {
            $service = $this->services->find($serviceId);
        }

        $liste = $this->employes->rechercher($terme ?: null, $statut ?: null, $service);

        return $this->render('rh/employes/index.html.twig', [
            'employes' => $liste,
            'terme' => $terme,
            'statut' => $statut,
            'serviceId' => $serviceId,
            'services' => $this->services->findBy([], ['libelle' => 'ASC']),
            'statuts' => Employe::STATUTS,
            'effectif' => count($liste),
        ]);
    }

    #[Route('/nouveau', name: '_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->acces->exigerRh();

        $employe = new Employe();
        $employe->setMatricule($this->references->matricule());
        $employe->setStatut(Employe::STATUT_ACTIF);
        $employe->setDateEmbauche(new \DateTime('today'));
        $donnees = $this->donneesVides();
        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('rh_employe', (string) $request->request->get('_token'))) {
                $erreurs[] = 'Le formulaire a expire. Rechargez la page puis recommencez.';
            } else {
                $donnees = $request->request->all();
                $erreurs = $this->appliquer($employe, $donnees, true);

                if ($erreurs === []) {
                    $erreurs = $this->enregistrerPhoto($request, $employe);
                }

                if ($erreurs === []) {
                    $this->em->persist($employe);

                    $affectation = (new Affectation())
                        ->setEmploye($employe)
                        ->setDirection($employe->getDirection())
                        ->setService($employe->getService())
                        ->setPoste($employe->getPoste())
                        ->setDateDebut($employe->getDateEmbauche() ?? new \DateTimeImmutable('today'))
                        ->setMotif('Affectation initiale');
                    $this->em->persist($affectation);
                    $this->em->flush();

                    $this->addFlash('success', 'L\'employe ' . $employe->getNomComplet() . ' a ete cree avec le matricule ' . $employe->getMatricule() . '.');

                    return $this->redirectToRoute('rh_employes_fiche', ['id' => $employe->getId()]);
                }
            }
        }

        return $this->render('rh/employes/form.html.twig', [
            'employe' => $employe,
            'donnees' => $donnees,
            'erreurs' => $erreurs,
            'directions' => $this->directions->findBy([], ['libelle' => 'ASC']),
            'services' => $this->services->findBy([], ['libelle' => 'ASC']),
            'postes' => $this->postes->findBy([], ['libelle' => 'ASC']),
            'managers' => $this->employes->findActifs(),
            'utilisateurs' => $this->users->findBy([], ['nom' => 'ASC'], 300),
            'statuts' => Employe::STATUTS,
            'sexes' => Employe::SEXES,
            'situations' => Employe::SITUATIONS,
            'estCreation' => true,
        ]);
    }

    #[Route('/{id}', name: '_fiche', methods: ['GET'])]
    public function fiche(string $id): Response
    {
        $employe = $this->employes->find($id);
        if ($employe === null) {
            throw $this->createNotFoundException('Employe introuvable.');
        }

        if (!$this->acces->peutVoirEmploye($employe)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas acces a cette fiche.');
        }

        $annee = (int) date('Y');

        return $this->render('rh/employes/fiche.html.twig', [
            'employe' => $employe,
            'contrats' => $employe->getContrats(),
            'affectations' => $employe->getAffectations(),
            'absences' => $employe->getAbsences(),
            'soldeConges' => $this->absences->soldeCongesAnnuel($employe, $annee),
            'documents' => $this->documents->findPourEmploye($employe),
            'subordonnes' => $employe->getSubordonnes(),
            'peutVoirSalaires' => $this->acces->peutVoirSalaires(),
            'estRh' => $this->acces->estRh(),
            'annee' => $annee,
        ]);
    }

    #[Route('/{id}/modifier', name: '_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, string $id): Response
    {
        $this->acces->exigerRh();

        $employe = $this->employes->find($id);
        if ($employe === null) {
            throw $this->createNotFoundException('Employe introuvable.');
        }

        $donnees = [
            'matricule' => $employe->getMatricule(),
            'nom' => $employe->getNom(),
            'prenoms' => $employe->getPrenoms(),
            'date_naissance' => $employe->getDateNaissance()?->format('Y-m-d'),
            'sexe' => $employe->getSexe(),
            'situation' => $employe->getSituationMatrimoniale(),
            'nationalite' => $employe->getNationalite(),
            'telephone' => $employe->getTelephone(),
            'email' => $employe->getEmail(),
            'adresse' => $employe->getAdresse(),
            'numero_cnps' => $employe->getNumeroCnps(),
            'code_pointage' => $employe->getCodePointage(),
            'direction' => (string) $employe->getDirection()?->getId(),
            'service' => (string) $employe->getService()?->getId(),
            'poste' => (string) $employe->getPoste()?->getId(),
            'manager' => (string) $employe->getManager()?->getId(),
            'utilisateur' => (string) $employe->getUtilisateur()?->getId(),
            'date_embauche' => $employe->getDateEmbauche()?->format('Y-m-d'),
            'statut' => $employe->getStatut(),
            'date_sortie' => $employe->getDateSortie()?->format('Y-m-d'),
            'motif_sortie' => $employe->getMotifSortie(),
        ];
        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('rh_employe', (string) $request->request->get('_token'))) {
                $erreurs[] = 'Le formulaire a expire. Rechargez la page puis recommencez.';
            } else {
                $donnees = $request->request->all();
                $erreurs = $this->appliquer($employe, $donnees, false);
                $anciennePhoto = $employe->getPhoto();

                if ($erreurs === []) {
                    $erreurs = $this->enregistrerPhoto($request, $employe);
                }

                if ($erreurs === []) {
                    $this->em->flush();
                    if ($employe->getPhoto() !== $anciennePhoto) {
                        $this->stockage->supprimerPhoto($anciennePhoto);
                    }
                    $this->addFlash('success', 'La fiche de ' . $employe->getNomComplet() . ' a ete mise a jour.');

                    return $this->redirectToRoute('rh_employes_fiche', ['id' => $employe->getId()]);
                }
            }
        }

        return $this->render('rh/employes/form.html.twig', [
            'employe' => $employe,
            'donnees' => $donnees,
            'erreurs' => $erreurs,
            'directions' => $this->directions->findBy([], ['libelle' => 'ASC']),
            'services' => $this->services->findBy([], ['libelle' => 'ASC']),
            'postes' => $this->postes->findBy([], ['libelle' => 'ASC']),
            'managers' => $this->employes->findActifs(),
            'utilisateurs' => $this->users->findBy([], ['nom' => 'ASC'], 300),
            'statuts' => Employe::STATUTS,
            'sexes' => Employe::SEXES,
            'situations' => Employe::SITUATIONS,
            'estCreation' => false,
        ]);
    }

    #[Route('/{id}/photo', name: '_photo', methods: ['POST'])]
    public function photo(Request $request, string $id): Response
    {
        $this->acces->exigerRh();

        $employe = $this->employes->find($id);
        if ($employe === null) {
            throw $this->createNotFoundException('Employe introuvable.');
        }

        if (!$this->isCsrfTokenValid('rh_employe_photo_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Le formulaire a expire. Rechargez la page puis recommencez.');
            return $this->redirectToRoute('rh_employes_fiche', ['id' => $id]);
        }

        $fichier = $request->files->get('photo');
        if ($fichier === null) {
            $this->addFlash('danger', 'Aucune photo n\'a ete selectionnee.');
            return $this->redirectToRoute('rh_employes_fiche', ['id' => $id]);
        }

        try {
            $nouveau = $this->stockage->stockerPhoto($fichier, $employe);
            $ancien = $employe->getPhoto();
            $employe->setPhoto($nouveau);
            $this->em->flush();
            if ($ancien !== null) {
                $this->stockage->supprimerPhoto($ancien);
            }
            $this->addFlash('success', 'La photo a ete mise a jour.');
        } catch (\RuntimeException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('rh_employes_fiche', ['id' => $id]);
    }

    #[Route('/{id}/affectation', name: '_affectation', methods: ['GET', 'POST'])]
    public function affectation(Request $request, string $id): Response
    {
        $this->acces->exigerRh();

        $employe = $this->employes->find($id);
        if ($employe === null) {
            throw $this->createNotFoundException('Employe introuvable.');
        }

        $donnees = [
            'direction' => (string) $employe->getDirection()?->getId(),
            'service' => (string) $employe->getService()?->getId(),
            'poste' => (string) $employe->getPoste()?->getId(),
            'date_debut' => date('Y-m-d'),
            'motif' => '',
        ];
        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('rh_affectation_' . $id, (string) $request->request->get('_token'))) {
                $erreurs[] = 'Le formulaire a expire. Rechargez la page puis recommencez.';
            } else {
                $donnees = $request->request->all();
                $direction = $this->direction($donnees['direction'] ?? '');
                $service = $this->service($donnees['service'] ?? '');
                $poste = $this->poste($donnees['poste'] ?? '');
                $dateDebut = $this->date($donnees['date_debut'] ?? '');

                if ($dateDebut === null) {
                    $erreurs[] = 'Indiquez la date de debut de la nouvelle affectation.';
                }
                if ($direction === null && $service === null && $poste === null) {
                    $erreurs[] = 'Choisissez au moins une direction, un service ou un poste.';
                }

                if ($erreurs === []) {
                    // On cloture l'affectation en cours la veille du changement.
                    $courante = null;
                    foreach ($employe->getAffectations() as $affectation) {
                        if ($affectation->getDateFin() === null) {
                            $courante = $affectation;
                        }
                    }
                    if ($courante !== null) {
                        $courante->setDateFin($dateDebut->modify('-1 day'));
                    }

                    $nouvelle = (new Affectation())
                        ->setEmploye($employe)
                        ->setDirection($direction)
                        ->setService($service)
                        ->setPoste($poste)
                        ->setDateDebut($dateDebut)
                        ->setMotif(trim((string) ($donnees['motif'] ?? '')) ?: null);

                    $employe->setDirection($direction)->setService($service)->setPoste($poste);

                    $this->em->persist($nouvelle);
                    $this->em->flush();

                    $this->addFlash('success', 'La nouvelle affectation est enregistree depuis le ' . $dateDebut->format('d/m/Y') . '.');

                    return $this->redirectToRoute('rh_employes_fiche', ['id' => $id]);
                }
            }
        }

        return $this->render('rh/employes/affectation.html.twig', [
            'employe' => $employe,
            'donnees' => $donnees,
            'erreurs' => $erreurs,
            'directions' => $this->directions->findBy([], ['libelle' => 'ASC']),
            'services' => $this->services->findBy([], ['libelle' => 'ASC']),
            'postes' => $this->postes->findBy([], ['libelle' => 'ASC']),
            'affectations' => $employe->getAffectations(),
        ]);
    }

    #[Route('/{id}/sortie', name: '_sortie', methods: ['POST'])]
    public function sortie(Request $request, string $id): Response
    {
        $this->acces->exigerRh();

        $employe = $this->employes->find($id);
        if ($employe === null) {
            throw $this->createNotFoundException('Employe introuvable.');
        }

        if (!$this->isCsrfTokenValid('rh_sortie_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Le formulaire a expire. Rechargez la page puis recommencez.');
            return $this->redirectToRoute('rh_employes_fiche', ['id' => $id]);
        }

        $dateSortie = $this->date($request->request->get('date_sortie', ''));
        $motif = trim((string) $request->request->get('motif_sortie', ''));

        if ($dateSortie === null) {
            $this->addFlash('danger', 'Indiquez la date de sortie.');
            return $this->redirectToRoute('rh_employes_fiche', ['id' => $id]);
        }

        $employe->setStatut(Employe::STATUT_SORTI)
            ->setDateSortie($dateSortie)
            ->setMotifSortie($motif !== '' ? $motif : null);

        // Les contrats encore en cours sont clos a la date de sortie.
        foreach ($employe->getContrats() as $contrat) {
            if ($contrat->getStatut() === \App\Entity\Rh\Contrat::EN_COURS) {
                $contrat->setStatut(\App\Entity\Rh\Contrat::TERMINE);
                if ($contrat->getDateFin() === null || $contrat->getDateFin() > $dateSortie) {
                    $contrat->setDateFin($dateSortie);
                }
            }
        }

        $this->em->flush();

        $this->addFlash('success', 'La sortie de ' . $employe->getNomComplet() . ' est enregistree au ' . $dateSortie->format('d/m/Y') . '.');

        return $this->redirectToRoute('rh_employes_fiche', ['id' => $id]);
    }

    #[Route('/{id}/pdf', name: '_pdf', methods: ['GET'])]
    public function pdf(string $id, pdfService $pdf): Response
    {
        $employe = $this->employes->find($id);
        if ($employe === null) {
            throw $this->createNotFoundException('Employe introuvable.');
        }

        if (!$this->acces->peutVoirEmploye($employe)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas acces a cette fiche.');
        }

        $lignesContrats = [];
        foreach ($employe->getContrats() as $contrat) {
            if (!$this->acces->peutVoirSalaires()) {
                continue;
            }
            $lignesContrats[] = [
                (string) $contrat->getReference(),
                (string) $contrat->getTypeLibelle(),
                $contrat->getDateDebut()?->format('d/m/Y') ?? '-',
                $contrat->getDateFin()?->format('d/m/Y') ?? 'Indeterminee',
                number_format((float) $contrat->getSalaire(), 0, ',', ' ') . ' FCFA',
                (string) $contrat->getStatutLibelle(),
            ];
        }

        $tableaux = [[
            'titre' => 'Identite et affectation',
            'entetes' => ['Rubrique', 'Information'],
            'poids' => [0.35, 0.65],
            'lignes' => [
                ['Matricule', (string) $employe->getMatricule()],
                ['Nom et prenoms', $employe->getNomComplet()],
                ['Date de naissance', $employe->getDateNaissance()?->format('d/m/Y') ?? '-'],
                ['Sexe', $employe->getSexeLibelle()],
                ['Situation matrimoniale', $employe->getSituationLibelle()],
                ['Telephone', $employe->getTelephone() ?? '-'],
                ['Email', $employe->getEmail() ?? '-'],
                ['Adresse', $employe->getAdresse() ?? '-'],
                ['Numero CNPS', $employe->getNumeroCnps() ?? '-'],
                ['Direction', $employe->getDirection()?->getLibelle() ?? '-'],
                ['Service', $employe->getService()?->getLibelle() ?? '-'],
                ['Poste', $employe->getPoste()?->getLibelle() ?? '-'],
                ['Responsable', $employe->getManager()?->getNomComplet() ?? '-'],
                ['Date d\'embauche', $employe->getDateEmbauche()?->format('d/m/Y') ?? '-'],
                ['Statut', $employe->getStatutLibelle()],
            ],
            'align' => ['L', 'L'],
        ]];

        if ($lignesContrats !== []) {
            $tableaux[] = [
                'titre' => 'Contrats',
                'entetes' => ['Reference', 'Type', 'Debut', 'Fin', 'Salaire', 'Statut'],
                'poids' => [0.18, 0.14, 0.15, 0.18, 0.2, 0.15],
                'lignes' => $lignesContrats,
                'align' => ['L', 'L', 'C', 'C', 'R', 'C'],
            ];
        }

        $contenu = $pdf->genererRapport(
            'Fiche employe',
            $employe->getNomComplet() . ' - ' . ($employe->getPoste()?->getLibelle() ?? 'poste non defini'),
            [],
            $tableaux
        );

        $nom = 'fiche-employe-' . preg_replace('/[^A-Za-z0-9-]/', '-', (string) $employe->getMatricule()) . '.pdf';

        return new Response($contenu, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $nom . '"',
        ]);
    }

    // ------------------------------------------------------------------
    //  Aides
    // ------------------------------------------------------------------

    private function donneesVides(): array
    {
        return [
            'matricule' => '',
            'nom' => '',
            'prenoms' => '',
            'date_naissance' => '',
            'sexe' => 'M',
            'situation' => 'celibataire',
            'nationalite' => 'Ivoirienne',
            'telephone' => '',
            'email' => '',
            'adresse' => '',
            'numero_cnps' => '',
            'code_pointage' => '',
            'direction' => '',
            'service' => '',
            'poste' => '',
            'manager' => '',
            'utilisateur' => '',
            'date_embauche' => date('Y-m-d'),
            'statut' => Employe::STATUT_ACTIF,
            'date_sortie' => '',
            'motif_sortie' => '',
        ];
    }

    /** Renseigne l'employe et renvoie la liste des erreurs a afficher. */
    private function appliquer(Employe $employe, array $donnees, bool $creation): array
    {
        $erreurs = [];

        $matricule = trim((string) ($donnees['matricule'] ?? ''));
        $nom = trim((string) ($donnees['nom'] ?? ''));
        $prenoms = trim((string) ($donnees['prenoms'] ?? ''));
        $email = trim((string) ($donnees['email'] ?? ''));
        $telephone = trim((string) ($donnees['telephone'] ?? ''));
        $codePointage = trim((string) ($donnees['code_pointage'] ?? ''));

        if ($matricule === '') {
            $erreurs[] = 'Le matricule est obligatoire.';
        }
        if ($nom === '') {
            $erreurs[] = 'Le nom est obligatoire.';
        }
        if ($prenoms === '') {
            $erreurs[] = 'Les prenoms sont obligatoires.';
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $erreurs[] = 'L\'adresse email "' . $email . '" n\'est pas valide.';
        }
        if ($telephone !== '' && !preg_match('/^[0-9 +().-]{6,25}$/', $telephone)) {
            $erreurs[] = 'Le numero de telephone "' . $telephone . '" contient des caracteres inattendus.';
        }

        if ($creation && $matricule !== '' && $this->employes->findOneBy(['matricule' => $matricule]) !== null) {
            $erreurs[] = 'Le matricule ' . $matricule . ' est deja utilise par un autre employe.';
        }

        if ($erreurs !== []) {
            return $erreurs;
        }

        $employe
            ->setMatricule($matricule)
            ->setNom($nom)
            ->setPrenoms($prenoms)
            ->setDateNaissance($this->date($donnees['date_naissance'] ?? ''))
            ->setSexe((string) ($donnees['sexe'] ?? 'M'))
            ->setSituationMatrimoniale((string) ($donnees['situation'] ?? 'celibataire'))
            ->setNationalite(trim((string) ($donnees['nationalite'] ?? '')) ?: 'Ivoirienne')
            ->setTelephone($telephone ?: null)
            ->setEmail($email ?: null)
            ->setAdresse(trim((string) ($donnees['adresse'] ?? '')) ?: null)
            ->setNumeroCnps(trim((string) ($donnees['numero_cnps'] ?? '')) ?: null)
            ->setCodePointage($codePointage ?: null)
            ->setDirection($this->direction($donnees['direction'] ?? ''))
            ->setService($this->service($donnees['service'] ?? ''))
            ->setPoste($this->poste($donnees['poste'] ?? ''))
            ->setManager($this->manager($donnees['manager'] ?? '', $employe))
            ->setUtilisateur($this->utilisateur($donnees['utilisateur'] ?? ''))
            ->setDateEmbauche($this->date($donnees['date_embauche'] ?? ''))
            ->setStatut((string) ($donnees['statut'] ?? Employe::STATUT_ACTIF))
            ->setDateSortie($this->date($donnees['date_sortie'] ?? ''))
            ->setMotifSortie(trim((string) ($donnees['motif_sortie'] ?? '')) ?: null);

        return [];
    }

    /**
     * Enregistre la photo eventuellement envoyee avec le formulaire.
     *
     * La photo n'est pas obligatoire : si aucun fichier n'est transmis, la
     * fiche est enregistree normalement.
     *
     * @return string[] messages d'erreur (vide si tout va bien)
     */
    private function enregistrerPhoto(Request $request, Employe $employe): array
    {
        $fichier = $request->files->get('photo');
        if (!$fichier instanceof UploadedFile) {
            return [];
        }

        try {
            $employe->setPhoto($this->stockage->stockerPhoto($fichier, $employe));
        } catch (\RuntimeException $e) {
            return ['Photo : ' . $e->getMessage()];
        }

        return [];
    }

    private function date(mixed $valeur): ?\DateTimeInterface
    {
        $valeur = trim((string) $valeur);
        if ($valeur === '') {
            return null;
        }

        // Une colonne Doctrine de type « date » attend un \DateTime (mutable).
        $date = \DateTime::createFromFormat('Y-m-d', $valeur);

        return $date instanceof \DateTime ? $date : null;
    }

    private function direction(mixed $id): ?Directions
    {
        $id = (string) $id;

        return $id !== '' ? $this->directions->find($id) : null;
    }

    private function service(mixed $id): ?Services
    {
        $id = (string) $id;

        return $id !== '' ? $this->services->find($id) : null;
    }

    private function poste(mixed $id): ?Poste
    {
        $id = (string) $id;

        return $id !== '' ? $this->postes->find($id) : null;
    }

    private function manager(mixed $id, Employe $employe): ?Employe
    {
        $id = (string) $id;
        if ($id === '' || ($employe->getId() !== null && $id === (string) $employe->getId())) {
            return null;
        }

        return $this->employes->find($id);
    }

    private function utilisateur(mixed $id): ?User
    {
        $id = (string) $id;

        return $id !== '' ? $this->users->find($id) : null;
    }
}
