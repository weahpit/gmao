<?php

namespace App\Controller\Rh;

use App\Entity\Poste;
use App\Entity\Rh\Contrat;
use App\Entity\Rh\Employe;
use App\Repository\PosteRepository;
use App\Repository\Rh\ContratRepository;
use App\Repository\Rh\EmployeRepository;
use App\Security\RhAcces;
use App\Service\Rh\ReferencesRh;
use App\Services\pdfService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrats de travail : suivi des CDI, CDD, stages et interims,
 * dates d'echeance et regularisations.
 */
#[Route('/rh/contrats', name: 'rh_contrats')]
#[IsGranted('ROLE_USER')]
final class ContratRhController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ContratRepository $contrats,
        private EmployeRepository $employes,
        private PosteRepository $postes,
        private ReferencesRh $references,
        private RhAcces $acces,
    ) {}

    #[Route('', name: '', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->acces->exigerRh();

        $statut = (string) $request->query->get('statut', '');
        $type = (string) $request->query->get('type', '');

        $qb = $this->contrats->createQueryBuilder('c')
            ->leftJoin('c.employe', 'e')->addSelect('e')
            ->orderBy('c.dateDebut', 'DESC');

        if ($statut !== '') {
            $qb->andWhere('c.statut = :statut')->setParameter('statut', $statut);
        }
        if ($type !== '') {
            $qb->andWhere('c.type = :type')->setParameter('type', $type);
        }

        return $this->render('rh/contrats/index.html.twig', [
            'contrats' => $qb->getQuery()->getResult(),
            'statut' => $statut,
            'type' => $type,
            'statuts' => Contrat::STATUTS,
            'types' => Contrat::TYPES,
            'echeances' => $this->contrats->findEcheances(60),
            'expires' => $this->contrats->findExpiresARegulariser(),
            'peutVoirSalaires' => $this->acces->peutVoirSalaires(),
        ]);
    }

    #[Route('/nouveau', name: '_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->acces->exigerRh();

        $contrat = new Contrat();
        $contrat->setReference($this->references->contrat());
        $contrat->setDateDebut(new \DateTimeImmutable('today'));
        $employeChoisi = null;
        $employeId = (string) $request->query->get('employe', '');
        if ($employeId !== '') {
            $employeChoisi = $this->employes->find($employeId);
            if ($employeChoisi !== null) {
                $contrat->setEmploye($employeChoisi)->setFonction($employeChoisi->getPoste()?->getLibelle())->setPoste($employeChoisi->getPoste());
            }
        }

        $donnees = $this->donnees($contrat);
        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('rh_contrat', (string) $request->request->get('_token'))) {
                $erreurs[] = 'Le formulaire a expire. Rechargez la page puis recommencez.';
            } else {
                $donnees = $request->request->all();
                $erreurs = $this->appliquer($contrat, $donnees);

                if ($erreurs === []) {
                    $this->em->persist($contrat);
                    $this->em->flush();
                    $this->addFlash('success', 'Le contrat ' . $contrat->getReference() . ' est enregistre.');

                    return $this->redirectToRoute('rh_employes_fiche', ['id' => $contrat->getEmploye()?->getId()]);
                }
            }
        }

        return $this->render('rh/contrats/form.html.twig', [
            'contrat' => $contrat,
            'donnees' => $donnees,
            'erreurs' => $erreurs,
            'employes' => $this->employes->findActifs(),
            'postes' => $this->postes->findBy([], ['libelle' => 'ASC']),
            'types' => Contrat::TYPES,
            'statuts' => Contrat::STATUTS,
            'estCreation' => true,
        ]);
    }

    #[Route('/{id}/modifier', name: '_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, string $id): Response
    {
        $this->acces->exigerRh();

        $contrat = $this->contrats->find($id);
        if ($contrat === null) {
            throw $this->createNotFoundException('Contrat introuvable.');
        }

        $donnees = $this->donnees($contrat);
        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('rh_contrat', (string) $request->request->get('_token'))) {
                $erreurs[] = 'Le formulaire a expire. Rechargez la page puis recommencez.';
            } else {
                $donnees = $request->request->all();
                $erreurs = $this->appliquer($contrat, $donnees);

                if ($erreurs === []) {
                    $this->em->flush();
                    $this->addFlash('success', 'Le contrat ' . $contrat->getReference() . ' a ete mis a jour.');

                    return $this->redirectToRoute('rh_employes_fiche', ['id' => $contrat->getEmploye()?->getId()]);
                }
            }
        }

        return $this->render('rh/contrats/form.html.twig', [
            'contrat' => $contrat,
            'donnees' => $donnees,
            'erreurs' => $erreurs,
            'employes' => $this->employes->findActifs(),
            'postes' => $this->postes->findBy([], ['libelle' => 'ASC']),
            'types' => Contrat::TYPES,
            'statuts' => Contrat::STATUTS,
            'estCreation' => false,
        ]);
    }

    #[Route('/{id}/fin', name: '_fin', methods: ['POST'])]
    public function fin(Request $request, string $id): Response
    {
        $this->acces->exigerRh();

        $contrat = $this->contrats->find($id);
        if ($contrat === null) {
            throw $this->createNotFoundException('Contrat introuvable.');
        }

        if (!$this->isCsrfTokenValid('rh_contrat_fin_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Le formulaire a expire. Rechargez la page puis recommencez.');
            return $this->redirectToRoute('rh_contrats');
        }

        $dateFin = $this->date($request->request->get('date_fin', ''));
        if ($dateFin === null) {
            $this->addFlash('danger', 'Indiquez la date de fin du contrat.');
            return $this->redirectToRoute('rh_contrats');
        }

        $contrat->setStatut(Contrat::TERMINE)
            ->setDateFin($dateFin)
            ->setMotifFin(trim((string) $request->request->get('motif_fin', '')) ?: null);

        $this->em->flush();
        $this->addFlash('success', 'Le contrat ' . $contrat->getReference() . ' est clos au ' . $dateFin->format('d/m/Y') . '.');

        return $this->redirectToRoute('rh_employes_fiche', ['id' => $contrat->getEmploye()?->getId()]);
    }

    #[Route('/pdf', name: '_pdf', methods: ['GET'])]
    public function pdf(pdfService $pdf): Response
    {
        $this->acces->exigerRh();

        $lignes = [];
        foreach ($this->contrats->findRecents(500) as $contrat) {
            $ligne = [
                (string) $contrat->getReference(),
                $contrat->getEmploye()?->getNomComplet() ?? '-',
                (string) $contrat->getTypeLibelle(),
                $contrat->getDateDebut()?->format('d/m/Y') ?? '-',
                $contrat->getDateFin()?->format('d/m/Y') ?? 'Indeterminee',
                (string) $contrat->getStatutLibelle(),
            ];
            if ($this->acces->peutVoirSalaires()) {
                $ligne[] = number_format((float) $contrat->getSalaire(), 0, ',', ' ') . ' FCFA';
            }
            $lignes[] = $ligne;
        }

        $entetes = ['Reference', 'Employe', 'Type', 'Debut', 'Fin', 'Statut'];
        $poids = [0.16, 0.28, 0.14, 0.14, 0.14, 0.14];
        if ($this->acces->peutVoirSalaires()) {
            $entetes[] = 'Salaire';
            $poids = [0.14, 0.24, 0.12, 0.12, 0.12, 0.12, 0.14];
        }

        $contenu = $pdf->genererTableau(
            'Situation des contrats',
            $entetes,
            $poids,
            $lignes,
            'L',
            'Etat au ' . date('d/m/Y') . ' - ' . count($lignes) . ' contrat(s)'
        );

        return new Response($contenu, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="contrats-rh.pdf"',
        ]);
    }

    private function donnees(Contrat $contrat): array
    {
        return [
            'reference' => $contrat->getReference(),
            'employe' => (string) $contrat->getEmploye()?->getId(),
            'type' => $contrat->getType(),
            'fonction' => $contrat->getFonction(),
            'poste' => (string) $contrat->getPoste()?->getId(),
            'date_debut' => $contrat->getDateDebut()?->format('Y-m-d'),
            'date_fin' => $contrat->getDateFin()?->format('Y-m-d'),
            'salaire' => $contrat->getSalaire(),
            'statut' => $contrat->getStatut(),
            'motif_fin' => $contrat->getMotifFin(),
        ];
    }

    private function appliquer(Contrat $contrat, array $donnees): array
    {
        $erreurs = [];

        $reference = trim((string) ($donnees['reference'] ?? ''));
        $employe = $this->employes->find((string) ($donnees['employe'] ?? ''));
        $dateDebut = $this->date($donnees['date_debut'] ?? '');
        $dateFin = $this->date($donnees['date_fin'] ?? '');
        $salaire = trim((string) ($donnees['salaire'] ?? ''));

        if ($employe === null) {
            $erreurs[] = 'Choisissez l\'employe concerne par le contrat.';
        }
        if ($reference === '') {
            $erreurs[] = 'La reference du contrat est obligatoire.';
        }
        if ($dateDebut === null) {
            $erreurs[] = 'La date de debut est obligatoire.';
        }
        if ($dateFin !== null && $dateDebut !== null && $dateFin < $dateDebut) {
            $erreurs[] = 'La date de fin (' . $dateFin->format('d/m/Y') . ') ne peut pas preceder la date de debut ('
                . $dateDebut->format('d/m/Y') . ').';
        }
        if ($salaire !== '' && !is_numeric(str_replace([' ', ','], ['', '.'], $salaire))) {
            $erreurs[] = 'Le salaire "' . $salaire . '" doit etre un montant en chiffres.';
        }

        $type = (string) ($donnees['type'] ?? Contrat::CDI);
        if (in_array($type, [Contrat::CDD, Contrat::STAGE, Contrat::INTERIM], true) && $dateFin === null) {
            $erreurs[] = 'Un contrat de type ' . (Contrat::TYPES[$type] ?? $type) . ' doit avoir une date de fin.';
        }

        $poste = null;
        $posteId = (string) ($donnees['poste'] ?? '');
        if ($posteId !== '') {
            $poste = $this->postes->find($posteId);
        }

        if ($erreurs !== []) {
            return $erreurs;
        }

        $contrat
            ->setReference($reference)
            ->setEmploye($employe)
            ->setType($type)
            ->setFonction(trim((string) ($donnees['fonction'] ?? '')) ?: null)
            ->setPoste($poste)
            ->setDateDebut($dateDebut)
            ->setDateFin($dateFin)
            ->setSalaire($salaire !== '' ? str_replace([' ', ','], ['', '.'], $salaire) : null)
            ->setStatut((string) ($donnees['statut'] ?? Contrat::EN_COURS))
            ->setMotifFin(trim((string) ($donnees['motif_fin'] ?? '')) ?: null);

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
