<?php

namespace App\Controller\Rh;

use App\Entity\Rh\AccidentTravail;
use App\Entity\Rh\DossierMedical;
use App\Entity\Rh\VisiteMedicale;
use App\Entity\Rh\Vaccination;
use App\Repository\Rh\DossierMedicalRepository;
use App\Repository\Rh\EmployeRepository;
use App\Repository\Rh\VisiteMedicaleRepository;
use App\Security\RhAcces;
use App\Services\pdfService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Sante au travail. Donnees sensibles : acces strictement reserve
 * au medecin, a l'infirmier et a l'administration.
 */
#[Route('/rh/medical', name: 'rh_medical')]
#[IsGranted('ROLE_USER')]
final class MedicalRhController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private DossierMedicalRepository $dossiers,
        private VisiteMedicaleRepository $visites,
        private EmployeRepository $employes,
        private RhAcces $acces,
    ) {}

    #[Route('', name: '', methods: ['GET'])]
    public function index(): Response
    {
        $this->acces->exigerMedical();

        return $this->render('rh/medical/index.html.twig', [
            'dossiers' => $this->dossiers->findTousAvecEmploye(),
            'renouvellements' => $this->visites->findRenouvellements(60),
            'dernieres' => $this->visites->findDernieres(10),
            'nbDossiers' => $this->dossiers->compter(),
            'nbInaptitudes' => $this->visites->compterInaptitudes(),
            'aptitudes' => VisiteMedicale::APTITUDES,
            'typesVisite' => VisiteMedicale::TYPES,
        ]);
    }

    #[Route('/nouveau', name: '_dossier_new', methods: ['GET', 'POST'])]
    public function nouveau(Request $request): Response
    {
        $this->acces->exigerMedical();

        $dossier = (new DossierMedical())->setNumeroDossier($this->dossiers->prochainNumero());
        $donnees = ['employe' => '', 'groupe_sanguin' => '', 'allergies' => '', 'antecedents' => '', 'notes' => ''];
        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('rh_dossier_medical', (string) $request->request->get('_token'))) {
                $erreurs[] = 'Le formulaire a expire. Rechargez la page puis recommencez.';
            } else {
                $donnees = $request->request->all();
                $employe = $this->employes->find((string) ($donnees['employe'] ?? ''));

                if ($employe === null) {
                    $erreurs[] = 'Selectionnez l\'employe concerne par le dossier medical.';
                } else {
                    $existant = $this->dossiers->findParEmploye($employe);
                    if ($existant !== null) {
                        $erreurs[] = 'Un dossier medical existe deja pour ' . $employe->getNomComplet() . ' (' . $existant->getNumeroDossier() . ').';
                    }
                }

                if ($erreurs === []) {
                    $dossier
                        ->setEmploye($employe)
                        ->setGroupeSanguin((string) ($donnees['groupe_sanguin'] ?? ''))
                        ->setAllergies($this->texte($donnees['allergies'] ?? null))
                        ->setAntecedents($this->texte($donnees['antecedents'] ?? null))
                        ->setNotes($this->texte($donnees['notes'] ?? null));

                    $this->em->persist($dossier);
                    $this->em->flush();
                    $this->addFlash('success', 'Le dossier medical ' . $dossier->getNumeroDossier() . ' est cree.');

                    return $this->redirectToRoute('rh_medical_dossier', ['id' => $dossier->getId()]);
                }
            }
        }

        return $this->render('rh/medical/dossier_form.html.twig', [
            'dossier' => $dossier,
            'donnees' => $donnees,
            'employes' => $this->employes->findActifs(),
            'groupes' => DossierMedical::GROUPES_SANGUINS,
            'erreurs' => $erreurs,
            'estCreation' => true,
        ]);
    }

    #[Route('/{id}', name: '_dossier', methods: ['GET'])]
    public function dossier(string $id): Response
    {
        $this->acces->exigerMedical();

        $dossier = $this->dossiers->find($id);
        if ($dossier === null) {
            throw $this->createNotFoundException('Dossier medical introuvable.');
        }

        return $this->render('rh/medical/dossier.html.twig', [
            'dossier' => $dossier,
            'aptitudes' => VisiteMedicale::APTITUDES,
            'typesVisite' => VisiteMedicale::TYPES,
            'gravites' => AccidentTravail::GRAVITES,
        ]);
    }

    #[Route('/{id}/modifier', name: '_dossier_edit', methods: ['GET', 'POST'])]
    public function modifier(Request $request, string $id): Response
    {
        $this->acces->exigerMedical();

        $dossier = $this->dossiers->find($id);
        if ($dossier === null) {
            throw $this->createNotFoundException('Dossier medical introuvable.');
        }

        $donnees = [
            'employe' => (string) $dossier->getEmploye()?->getId(),
            'groupe_sanguin' => $dossier->getGroupeSanguin(),
            'allergies' => $dossier->getAllergies(),
            'antecedents' => $dossier->getAntecedents(),
            'notes' => $dossier->getNotes(),
        ];
        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('rh_dossier_medical', (string) $request->request->get('_token'))) {
                $erreurs[] = 'Le formulaire a expire. Rechargez la page puis recommencez.';
            } else {
                $donnees = $request->request->all();
                $dossier
                    ->setGroupeSanguin((string) ($donnees['groupe_sanguin'] ?? ''))
                    ->setAllergies($this->texte($donnees['allergies'] ?? null))
                    ->setAntecedents($this->texte($donnees['antecedents'] ?? null))
                    ->setNotes($this->texte($donnees['notes'] ?? null));

                $this->em->flush();
                $this->addFlash('success', 'Le dossier medical ' . $dossier->getNumeroDossier() . ' est mis a jour.');

                return $this->redirectToRoute('rh_medical_dossier', ['id' => $id]);
            }
        }

        return $this->render('rh/medical/dossier_form.html.twig', [
            'dossier' => $dossier,
            'donnees' => $donnees,
            'employes' => $this->employes->findActifs(),
            'groupes' => DossierMedical::GROUPES_SANGUINS,
            'erreurs' => $erreurs,
            'estCreation' => false,
        ]);
    }

    #[Route('/{id}/visite', name: '_visite_new', methods: ['GET', 'POST'])]
    public function visite(Request $request, string $id): Response
    {
        $this->acces->exigerMedical();

        $dossier = $this->dossiers->find($id);
        if ($dossier === null) {
            throw $this->createNotFoundException('Dossier medical introuvable.');
        }

        $visite = (new VisiteMedicale())->setDossier($dossier)->setDate(new \DateTimeImmutable());
        $donnees = [
            'type' => VisiteMedicale::PERIODIQUE,
            'date' => date('Y-m-d'),
            'medecin' => '',
            'centre_medical' => '',
            'aptitude' => VisiteMedicale::APTE,
            'conclusion' => '',
            'date_prochaine' => date('Y-m-d', strtotime('+1 year')),
        ];
        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('rh_visite_medicale', (string) $request->request->get('_token'))) {
                $erreurs[] = 'Le formulaire a expire. Rechargez la page puis recommencez.';
            } else {
                $donnees = $request->request->all();
                $date = $this->date($donnees['date'] ?? '');
                $prochaine = $this->date($donnees['date_prochaine'] ?? '');

                if ($date === null) {
                    $erreurs[] = 'La date de la visite est obligatoire.';
                }
                if ($prochaine !== null && $date !== null && $prochaine <= $date) {
                    $erreurs[] = 'La prochaine visite doit etre posterieure a la date de la visite.';
                }

                if ($erreurs === []) {
                    $visite
                        ->setType((string) ($donnees['type'] ?? VisiteMedicale::PERIODIQUE))
                        ->setDate($date)
                        ->setMedecin($this->texte($donnees['medecin'] ?? null))
                        ->setCentreMedical($this->texte($donnees['centre_medical'] ?? null))
                        ->setAptitude((string) ($donnees['aptitude'] ?? VisiteMedicale::APTE))
                        ->setConclusion($this->texte($donnees['conclusion'] ?? null))
                        ->setDateProchaine($prochaine);

                    $this->em->persist($visite);
                    $this->em->flush();
                    $this->addFlash('success', 'La visite medicale du ' . $date->format('d/m/Y') . ' est enregistree.');

                    return $this->redirectToRoute('rh_medical_dossier', ['id' => $id]);
                }
            }
        }

        return $this->render('rh/medical/visite_form.html.twig', [
            'dossier' => $dossier,
            'donnees' => $donnees,
            'erreurs' => $erreurs,
            'types' => VisiteMedicale::TYPES,
            'aptitudes' => VisiteMedicale::APTITUDES,
        ]);
    }

    #[Route('/{id}/vaccination', name: '_vaccination_new', methods: ['GET', 'POST'])]
    public function vaccination(Request $request, string $id): Response
    {
        $this->acces->exigerMedical();

        $dossier = $this->dossiers->find($id);
        if ($dossier === null) {
            throw $this->createNotFoundException('Dossier medical introuvable.');
        }

        $vaccination = (new Vaccination())->setDossier($dossier);
        $donnees = ['vaccin' => '', 'date' => date('Y-m-d'), 'prochain_rappel' => '', 'lot' => ''];
        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('rh_vaccination', (string) $request->request->get('_token'))) {
                $erreurs[] = 'Le formulaire a expire. Rechargez la page puis recommencez.';
            } else {
                $donnees = $request->request->all();
                $vaccin = trim((string) ($donnees['vaccin'] ?? ''));
                $date = $this->date($donnees['date'] ?? '');

                if ($vaccin === '') {
                    $erreurs[] = 'Indiquez le vaccin administre.';
                }
                if ($date === null) {
                    $erreurs[] = 'La date de vaccination est obligatoire.';
                }

                if ($erreurs === []) {
                    $vaccination
                        ->setVaccin($vaccin)
                        ->setDate($date)
                        ->setProchainRappel($this->date($donnees['prochain_rappel'] ?? ''))
                        ->setLot($this->texte($donnees['lot'] ?? null));

                    $this->em->persist($vaccination);
                    $this->em->flush();
                    $this->addFlash('success', 'La vaccination "' . $vaccin . '" est enregistree.');

                    return $this->redirectToRoute('rh_medical_dossier', ['id' => $id]);
                }
            }
        }

        return $this->render('rh/medical/vaccination_form.html.twig', [
            'dossier' => $dossier,
            'donnees' => $donnees,
            'erreurs' => $erreurs,
        ]);
    }

    #[Route('/{id}/accident', name: '_accident_new', methods: ['GET', 'POST'])]
    public function accident(Request $request, string $id): Response
    {
        $this->acces->exigerMedical();

        $dossier = $this->dossiers->find($id);
        if ($dossier === null) {
            throw $this->createNotFoundException('Dossier medical introuvable.');
        }

        $accident = (new AccidentTravail())->setDossier($dossier);
        $donnees = [
            'date' => date('Y-m-d'),
            'lieu' => '',
            'description' => '',
            'gravite' => AccidentTravail::LEGER,
            'arret_travail' => '0',
            'date_debut_arret' => '',
            'date_fin_arret' => '',
            'numero_declaration' => '',
        ];
        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('rh_accident', (string) $request->request->get('_token'))) {
                $erreurs[] = 'Le formulaire a expire. Rechargez la page puis recommencez.';
            } else {
                $donnees = $request->request->all();
                $date = $this->date($donnees['date'] ?? '');
                $debut = $this->date($donnees['date_debut_arret'] ?? '');
                $fin = $this->date($donnees['date_fin_arret'] ?? '');
                $arret = ($donnees['arret_travail'] ?? '0') === '1';

                if ($date === null) {
                    $erreurs[] = 'La date de l\'accident est obligatoire.';
                }
                if ($arret && $debut === null) {
                    $erreurs[] = 'Indiquez la date de debut de l\'arret de travail.';
                }
                if ($debut !== null && $fin !== null && $fin < $debut) {
                    $erreurs[] = 'La fin de l\'arret doit etre posterieure a son debut.';
                }

                if ($erreurs === []) {
                    $accident
                        ->setDate($date)
                        ->setLieu($this->texte($donnees['lieu'] ?? null))
                        ->setDescription($this->texte($donnees['description'] ?? null))
                        ->setGravite((string) ($donnees['gravite'] ?? AccidentTravail::LEGER))
                        ->setArretTravail($arret)
                        ->setDateDebutArret($debut)
                        ->setDateFinArret($fin)
                        ->setNumeroDeclaration($this->texte($donnees['numero_declaration'] ?? null));

                    if ($debut !== null) {
                        $accident->calculerJoursArret();
                    }

                    $this->em->persist($accident);
                    $this->em->flush();
                    $this->addFlash('success', 'L\'accident de travail du ' . $date->format('d/m/Y') . ' est enregistre.');

                    return $this->redirectToRoute('rh_medical_dossier', ['id' => $id]);
                }
            }
        }

        return $this->render('rh/medical/accident_form.html.twig', [
            'dossier' => $dossier,
            'donnees' => $donnees,
            'erreurs' => $erreurs,
            'gravites' => AccidentTravail::GRAVITES,
        ]);
    }

    #[Route('/{id}/pdf', name: '_pdf', methods: ['GET'])]
    public function pdf(string $id, pdfService $pdf): Response
    {
        $this->acces->exigerMedical();

        $dossier = $this->dossiers->find($id);
        if ($dossier === null) {
            throw $this->createNotFoundException('Dossier medical introuvable.');
        }

        $visites = [];
        foreach ($dossier->getVisites() as $visite) {
            $visites[] = [
                $visite->getDate()?->format('d/m/Y') ?? '-',
                $visite->getTypeLibelle(),
                $visite->getAptitudeLibelle(),
                $visite->getMedecin() ?? '-',
                $visite->getDateProchaine()?->format('d/m/Y') ?? '-',
            ];
        }

        $employe = $dossier->getEmploye();

        $contenu = $pdf->genererRapport(
            'Dossier medical du travail',
            $dossier->getNumeroDossier(),
            [
                ['Employe', $employe?->getNomComplet() ?? '-'],
                ['Matricule', $employe?->getMatricule() ?? '-'],
                ['Service', $employe?->getService()?->getLibelle() ?? '-'],
                ['Groupe sanguin', $dossier->getGroupeSanguin() ?? 'Non renseigne'],
            ],
            [[
                'titre' => 'Historique des visites',
                'entetes' => ['Date', 'Type', 'Aptitude', 'Medecin', 'Prochaine'],
                'poids' => [0.17, 0.23, 0.23, 0.22, 0.15],
                'lignes' => $visites,
                'align' => ['C', 'L', 'L', 'L', 'C'],
            ]],
            'P'
        );

        return new Response($contenu, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="dossier-medical-' . $dossier->getNumeroDossier() . '.pdf"',
        ]);
    }

    private function texte(mixed $valeur): ?string
    {
        $valeur = trim((string) $valeur);

        return $valeur === '' ? null : $valeur;
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
