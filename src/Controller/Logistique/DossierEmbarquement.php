<?php

namespace App\Controller\Logistique;

use App\Entity\Logistique\Client;
use App\Entity\Logistique\Dossier;
use App\Entity\Logistique\Destination;
use App\Entity\Logistique\Navire;
use App\Entity\Logistique\Proprietaire;
use App\Entity\Production\Produit;
use App\Repository\Logistique\DossierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Dossiers d'embarquement (shipping).
 *
 *  - /logistique/embarquements            : liste des dossiers (filtres + compteurs)
 *  - /logistique/embarquements/{id}/statut : clôture / réouverture d'un dossier
 *  - /logistique/enregistrement-dossier   : formulaire de création (page existante)
 */
#[IsGranted('ROLE_USER')]
class DossierEmbarquement extends AbstractController
{
    public function __construct(
        private DossierRepository $dossiers,
        private EntityManagerInterface $em,
    ) {}

    // ------------------------------------------------------------------
    //  Liste des dossiers d'embarquement
    // ------------------------------------------------------------------
    #[Route('logistique/embarquements', name: 'embarquements_index', methods: ['GET'])]
    public function liste(Request $request): Response
    {
        $filtres = [
            'q'      => trim((string) $request->query->get('q', '')),
            'statut' => (string) $request->query->get('statut', ''),
            'navire' => (string) $request->query->get('navire', ''),
            'du'     => (string) $request->query->get('du', ''),
            'au'     => (string) $request->query->get('au', ''),
        ];

        $page    = max(1, (int) $request->query->get('page', 1));
        $parPage = 25;

        $qb = $this->dossiers->createQueryBuilder('d')
            ->leftJoin('d.client', 'cl')->addSelect('cl')
            ->leftJoin('d.produit', 'p')->addSelect('p')
            ->leftJoin('d.proprietaire', 'pr')->addSelect('pr')
            ->leftJoin('d.code_navire', 'n')->addSelect('n')
            ->leftJoin('d.destination', 'de')->addSelect('de');

        if ($filtres['q'] !== '') {
            $qb->andWhere('d.numero_ot LIKE :q OR cl.nomClient LIKE :q OR n.nom LIKE :q OR de.denomination LIKE :q OR pr.denomination LIKE :q')
               ->setParameter('q', '%' . $filtres['q'] . '%');
        }

        if ($filtres['statut'] === 'encours') {
            $qb->andWhere('d.cloture = false OR d.cloture IS NULL');
        } elseif ($filtres['statut'] === 'cloture') {
            $qb->andWhere('d.cloture = true');
        } elseif ($filtres['statut'] === 'a_planifier') {
            $qb->andWhere('d.date_embarquement IS NULL');
        }

        if ($filtres['navire'] !== '') {
            try {
                $qb->andWhere('d.code_navire = :navire')
                   ->setParameter('navire', Uuid::fromString($filtres['navire']));
            } catch (\Throwable) {
                // identifiant invalide : on ignore le filtre plutot que de planter
                $filtres['navire'] = '';
            }
        }

        if ($filtres['du'] !== '') {
            try {
                $qb->andWhere('d.created_at >= :du')
                   ->setParameter('du', new \DateTimeImmutable($filtres['du'] . ' 00:00:00'));
            } catch (\Throwable) {
                $filtres['du'] = '';
            }
        }

        if ($filtres['au'] !== '') {
            try {
                $qb->andWhere('d.created_at <= :au')
                   ->setParameter('au', new \DateTimeImmutable($filtres['au'] . ' 23:59:59'));
            } catch (\Throwable) {
                $filtres['au'] = '';
            }
        }

        // Nombre total de dossiers correspondant aux filtres (avant pagination)
        $total = (int) (clone $qb)
            ->select('COUNT(DISTINCT d.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $dossiers = $qb
            ->orderBy('d.created_at', 'DESC')
            ->setFirstResult(($page - 1) * $parPage)
            ->setMaxResults($parPage)
            ->getQuery()
            ->getResult();

        $nbPages = max(1, (int) ceil($total / $parPage));

        return $this->render('logistique/embarquement/liste.html.twig', [
            'dossiers' => $dossiers,
            'filtres'  => $filtres,
            'navires'  => $this->em->getRepository(Navire::class)->findBy([], ['nom' => 'ASC']),
            'total'    => $total,
            'page'     => $page,
            'nbPages'  => $nbPages,
            'parPage'  => $parPage,
            'stats'    => $this->statistiques(),
        ]);
    }

    // ------------------------------------------------------------------
    //  Clôturer / rouvrir un dossier
    // ------------------------------------------------------------------
    #[Route('logistique/embarquements/{id}/statut', name: 'embarquement_statut', methods: ['POST'])]
    public function changerStatut(Request $request, string $id): Response
    {
        try {
            $dossier = $this->dossiers->find(Uuid::fromString($id));
        } catch (\Throwable) {
            $dossier = null;
        }

        if (!$dossier instanceof Dossier) {
            throw $this->createNotFoundException('Dossier d\'embarquement introuvable.');
        }

        if (!$this->isCsrfTokenValid('embarquement_statut_' . $dossier->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Action refusée : jeton de sécurité invalide. Rechargez la page et réessayez.');

            return $this->redirigerVersListe($request);
        }

        $cloture = !$dossier->isCloture();
        $dossier->setCloture($cloture);

        $utilisateur = $this->getUser();
        $nom = '';
        if ($utilisateur && method_exists($utilisateur, 'getNom')) {
            $nom = (string) $utilisateur->getNom();
            if (method_exists($utilisateur, 'getPrenoms')) {
                $nom .= ' ' . (string) $utilisateur->getPrenoms();
            }
            $nom = trim($nom);
        }
        $dossier->setUpdatedAt(new \DateTimeImmutable());
        if ($nom !== '') {
            $dossier->setUpdatedBy($nom);
        }

        $this->em->flush();

        $this->addFlash('success', $cloture
            ? 'Dossier ' . $dossier->getNumeroOt() . ' clôturé.'
            : 'Dossier ' . $dossier->getNumeroOt() . ' rouvert.');

        return $this->redirigerVersListe($request);
    }

    // ------------------------------------------------------------------
    //  Formulaire de création (page existante)
    // ------------------------------------------------------------------
    #[Route('logistique/enregistrement-dossier', name: 'enregistrement_embarquement')]
    public function enregistrements_dossier(ManagerRegistry $registry): Response
    {
        return $this->render('logistique/embarquement/index.html.twig',
            [
                'produits'     => $registry->getRepository(Produit::class)->findAll(),
                'clients'      => $registry->getRepository(Client::class)->findAll(),
                'destinations' => $registry->getRepository(Destination::class)->findAll(),
                'navires'      => $registry->getRepository(Navire::class)->findAll(),
                'proprietaires'=> $registry->getRepository(Proprietaire::class)->findAll(),
            ]
        );
    }

    // ------------------------------------------------------------------
    //  Compteurs du bandeau
    // ------------------------------------------------------------------
    /** @return array{total:int, en_cours:int, clotures:int, a_planifier:int, ce_mois:int} */
    private function statistiques(): array
    {
        $debutMois = new \DateTimeImmutable('first day of this month 00:00:00');
        $finMois   = new \DateTimeImmutable('last day of this month 23:59:59');

        $total = (int) $this->dossiers->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $clotures = (int) $this->dossiers->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.cloture = true')
            ->getQuery()
            ->getSingleScalarResult();

        $aPlanifier = (int) $this->dossiers->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.date_embarquement IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $ceMois = (int) $this->dossiers->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.date_embarquement BETWEEN :debut AND :fin')
            ->setParameter('debut', $debutMois)
            ->setParameter('fin', $finMois)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total'       => $total,
            'en_cours'    => max(0, $total - $clotures),
            'clotures'    => $clotures,
            'a_planifier' => $aPlanifier,
            'ce_mois'     => $ceMois,
        ];
    }

    private function redirigerVersListe(Request $request): Response
    {
        $retour = (string) $request->request->get('retour', '');
        if ($retour !== '' && str_starts_with($retour, '/')) {
            return $this->redirect($retour);
        }

        return $this->redirectToRoute('embarquements_index');
    }
}
