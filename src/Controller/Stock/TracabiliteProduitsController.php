<?php

namespace App\Controller\Stock;

use App\Entity\Production\LigneOrdreProduction;
use App\Entity\Production\Lot;
use App\Entity\Production\OrdreProduction;
use App\Repository\Production\LigneOrdreProductionRepository;
use App\Repository\Production\LotRepository;
use App\Repository\Production\OrdreProductionRepository;
use App\Repository\Stock\LotDechargementRepository;
use App\Service\Production\NatureLot;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * ORDRE DE PRODUCTION (ex « tracabilite des produits finis »).
 *
 * Meme ergonomie que l'ordre d'usinage, cote produits finis :
 *  - on coche des lots de produit fini (masse, beurre, tourteau, poudre) ;
 *  - on coche les lots de feves CITRAC qui les ont produits ;
 *  - l'application genere un numero (OP-AAAA-0001) et cree un rattachement
 *    pour chaque couple produit fini x lot de feves.
 *
 * Chaine restituee : ordre de production -> lots de feves -> camions.
 *
 *  - /Stock/OrdreProduction                    : ecran de saisie + historique
 *  - /Stock/OrdreProduction/Ordre              : enregistrement (POST)
 *  - /Stock/OrdreProduction/Ordre/{id}/Supprimer : annulation (POST)
 *  - /Stock/OrdreProduction/{id}               : fiche du produit fini
 */
#[Route('/Stock/OrdreProduction')]
#[IsGranted('ROLE_USER')]
final class TracabiliteProduitsController extends AbstractController
{
    private const PAR_PAGE = 25;
    private const FEVES_MAX = 40;
    private const SITUATIONS = ['tous', 'sans_feve', 'avec_feve'];

    private const MOTIFS_FINIS = ['%MASSE%', '%BEURRE%', '%TOURTEAU%', '%POUDRE%'];
    private const MOTIFS_FEVE = ['%FEVE%', '%FÈVE%', '%FÊVE%', '%FËVE%'];

    public function __construct(
        private readonly LotRepository $lots,
        private readonly LotDechargementRepository $liens,
        private readonly OrdreProductionRepository $ordres,
        private readonly LigneOrdreProductionRepository $lignes,
        private readonly NatureLot $nature,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** Ecran unique : produits finis + lots de feves, puis historique. */
    #[Route('', name: 'stock_tracabilite_produits', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $terme = trim((string) $request->query->get('q', ''));
        $statut = (string) $request->query->get('statut', 'tous');
        if (!in_array($statut, self::SITUATIONS, true)) {
            $statut = 'tous';
        }
        $page = max(1, (int) $request->query->get('page', 1));
        $rechercheFeve = trim((string) $request->query->get('fq', ''));

        // ------ Produits finis proposes ------
        $qb = $this->lots->createQueryBuilder('l')
            ->leftJoin('l.code_produit', 'p')->addSelect('p')
            ->leftJoin('l.code_magasin', 'm')->addSelect('m');

        $conditions = [];
        foreach (self::MOTIFS_FINIS as $i => $motif) {
            $conditions[] = 'p.libelle LIKE :fini' . $i;
            $qb->setParameter('fini' . $i, $motif);
        }
        $qb->andWhere('(' . implode(' OR ', $conditions) . ')');

        if ($terme !== '') {
            $qb->andWhere('UPPER(l.numero_lot) LIKE :terme OR UPPER(p.libelle) LIKE :terme OR UPPER(m.libelle) LIKE :terme')
               ->setParameter('terme', '%' . mb_strtoupper($terme) . '%');
        }
        if ('sans_feve' === $statut) {
            $qb->andWhere('SIZE(l.lotsOrigine) = 0');
        } elseif ('avec_feve' === $statut) {
            $qb->andWhere('SIZE(l.lotsOrigine) > 0');
        }

        $total = (int) (clone $qb)->select('COUNT(l.id)')->getQuery()->getSingleScalarResult();
        $pages = max(1, (int) ceil($total / self::PAR_PAGE));
        if ($page > $pages) {
            $page = $pages;
        }

        $resultats = $qb->orderBy('l.date_prod', 'DESC')
            ->addOrderBy('l.numero_lot', 'DESC')
            ->setFirstResult(($page - 1) * self::PAR_PAGE)
            ->setMaxResults(self::PAR_PAGE)
            ->getQuery()
            ->getResult();

        $lignes = [];
        foreach ($resultats as $lot) {
            $lignes[] = [
                'lot' => $lot,
                'feves' => $lot->getLotsOrigine()->count(),
                'qualite' => $this->nature->libelleLot($lot),
                'estNc' => 'NC' === mb_strtoupper((string) $lot->getQualite()),
            ];
        }

        // ------ Lots de feves proposes ------
        $qbFeves = $this->lots->createQueryBuilder('l')
            ->leftJoin('l.code_produit', 'p')->addSelect('p')
            ->orderBy('l.numero_lot', 'DESC')
            ->setMaxResults(self::FEVES_MAX);

        $conditionsFeve = [];
        foreach (self::MOTIFS_FEVE as $i => $motif) {
            $conditionsFeve[] = 'p.libelle LIKE :feve' . $i;
            $qbFeves->setParameter('feve' . $i, $motif);
        }
        $qbFeves->andWhere('(' . implode(' OR ', $conditionsFeve) . ')');

        if ($rechercheFeve !== '') {
            // "grade 2" doit retrouver "GRADE2" : on compare aussi sans espaces
            // ni tirets.
            $motif = '%' . mb_strtoupper($rechercheFeve) . '%';
            $compact = '%' . str_replace([' ', '-'], '', mb_strtoupper($rechercheFeve)) . '%';
            $qbFeves->andWhere('UPPER(l.numero_lot) LIKE :fq OR UPPER(l.qualite) LIKE :fq OR UPPER(p.libelle) LIKE :fq OR REPLACE(REPLACE(UPPER(l.qualite), \'-\', \'\'), \' \', \'\') LIKE :fqc')
                    ->setParameter('fq', $motif)
                    ->setParameter('fqc', $compact);
        }

        $feves = [];
        foreach ($qbFeves->getQuery()->getResult() as $feve) {
            $feves[] = [
                'lot' => $feve,
                'qualite' => $this->nature->libelleLot($feve),
                'finis' => $feve->getLotsFils()->count(),
            ];
        }

        // ------ Historique ------
        $historique = [];
        foreach ($this->ordres->recents(25) as $ordre) {
            $historique[] = $ordre;
        }

        return $this->render('stock/tracabilite_produits.html.twig', [
            'lignes' => $lignes,
            'feves' => $feves,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'terme' => $terme,
            'statut' => $statut,
            'rechercheFeve' => $rechercheFeve,
            'ordres' => $historique,
            'nbOrdres' => $this->ordres->compter(),
            'totaux' => $this->lignes->totaux(),
            'peutModifier' => $this->peutRattacher(),
        ]);
    }

    /** Enregistre un ordre de production : N produits finis x M lots de feves. */
    #[Route('/Ordre', name: 'stock_ordre_production_creer', methods: ['POST'])]
    public function creerOrdre(Request $request): Response
    {
        $retour = $this->redirectToRoute('stock_tracabilite_produits');

        if (!$this->peutRattacher()) {
            $this->addFlash('danger', 'Seuls les profils PDR, DG et administrateur peuvent enregistrer un ordre de production.');

            return $retour;
        }

        if (!$this->isCsrfTokenValid('ordre_production_creation', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée : rechargez la page puis recommencez.');

            return $retour;
        }

        $idsFinis = array_values(array_filter((array) $request->request->all('lots'), static fn ($v): bool => is_string($v) && Uuid::isValid($v)));
        $idsFeves = array_values(array_filter((array) $request->request->all('feves'), static fn ($v): bool => is_string($v) && Uuid::isValid($v)));

        if ($idsFinis === []) {
            $this->addFlash('danger', 'Cochez au moins un produit fini (masse, beurre, tourteau) à rattacher.');

            return $retour;
        }
        if ($idsFeves === []) {
            $this->addFlash('danger', 'Cochez au moins un lot de fèves CITRAC pour ce groupe de produits finis.');

            return $retour;
        }

        $finis = [];
        $rejetes = 0;
        foreach (array_unique($idsFinis) as $id) {
            $lot = $this->lots->find($id);
            if (!$lot instanceof Lot || !$this->nature->estFini($lot)) {
                ++$rejetes;

                continue;
            }
            $finis[(string) $lot->getId()] = $lot;
        }

        $feves = [];
        foreach (array_unique($idsFeves) as $id) {
            $lot = $this->lots->find($id);
            if (!$lot instanceof Lot || !$this->nature->estFeve($lot)) {
                ++$rejetes;

                continue;
            }
            $feves[(string) $lot->getId()] = $lot;
        }

        if ($finis === [] || $feves === []) {
            $this->addFlash('danger', 'Sélection invalide : cochez uniquement des produits finis (masse, beurre, tourteau) et des lots de fèves CITRAC.');

            return $retour;
        }

        $ordre = new OrdreProduction();
        $ordre->setNumero($this->numeroDisponible())
            ->setCreatedBy($this->utilisateur())
            ->setObservation($this->texte($request->request->get('observation')));

        $crees = 0;
        $ignores = 0;

        foreach ($finis as $fini) {
            foreach ($feves as $feve) {
                if ($this->lignes->coupleExiste($fini, $feve)) {
                    ++$ignores;

                    continue;
                }

                $ligne = new LigneOrdreProduction();
                $ligne->setLotFini($fini)
                    ->setLotFeve($feve)
                    ->setCreatedBy($this->utilisateur());

                $ordre->addLigne($ligne);

                // La fiche du produit fini s'appuie sur cette relation : on la
                // tient a jour en meme temps que la ligne d'ordre.
                $fini->addLotOrigine($feve);
                ++$crees;
            }
        }

        if ($crees === 0) {
            $this->addFlash('info', $ignores > 0
                ? 'Aucun nouveau rattachement : ces produits finis sont déjà reliés à ces lots de fèves.'
                : 'Aucun rattachement créé.');

            return $retour;
        }

        try {
            $this->em->persist($ordre);
            $this->em->flush();

            $this->addFlash('success', sprintf(
                'Ordre de production %s enregistré : %d produit(s) fini(s), %d lot(s) de fèves, %d rattachement(s).',
                (string) $ordre->getNumero(),
                count($finis),
                count($feves),
                $crees
            ));
            if ($ignores > 0) {
                $this->addFlash('info', $ignores . ' couple(s) existaient déjà et ont été ignorés (pas de doublon).');
            }
            if ($rejetes > 0) {
                $this->addFlash('warning', $rejetes . ' ligne(s) ignorée(s) : la sélection contenait un lot qui n\'est ni un produit fini ni un lot de fèves.');
            }
        } catch (\Throwable $e) {
            $this->logger->error('Ordre de production : échec enregistrement', ['erreur' => $e->getMessage()]);
            $this->addFlash('danger', 'Enregistrement impossible : ' . $e->getMessage());
        }

        return $retour;
    }

    /** Annule un ordre de production (retire ses rattachements). */
    #[Route('/Ordre/{id}/Supprimer', name: 'stock_ordre_production_supprimer', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function supprimerOrdre(OrdreProduction $ordre, Request $request): Response
    {
        if (!$this->peutRattacher()) {
            $this->addFlash('danger', 'Seuls les profils PDR, DG et administrateur peuvent annuler un ordre de production.');

            return $this->redirectToRoute('stock_tracabilite_produits');
        }

        if (!$this->isCsrfTokenValid('ordre_production_suppression', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée : rechargez la page puis recommencez.');

            return $this->redirectToRoute('stock_tracabilite_produits');
        }

        $numero = (string) $ordre->getNumero();
        $retires = 0;

        foreach ($ordre->getLignes() as $ligne) {
            $fini = $ligne->getLotFini();
            $feve = $ligne->getLotFeve();
            if (!$fini instanceof Lot || !$feve instanceof Lot) {
                continue;
            }
            // On ne retire le couple du produit fini que s'il n'est pas utilise
            // par un autre ordre : la tracabilite reste exacte.
            if (!$this->lignes->coupleExiste($fini, $feve, $ordre) && $fini->getLotsOrigine()->contains($feve)) {
                $fini->removeLotOrigine($feve);
                ++$retires;
            }
        }

        try {
            $this->em->remove($ordre);
            $this->em->flush();
            $this->addFlash('success', sprintf('Ordre de production %s annulé (%d rattachement(s) retiré(s)).', $numero, $retires));
        } catch (\Throwable $e) {
            $this->logger->error('Ordre de production : échec annulation', ['erreur' => $e->getMessage()]);
            $this->addFlash('danger', 'Annulation impossible : ' . $e->getMessage());
        }

        return $this->redirectToRoute('stock_tracabilite_produits');
    }

    /** Fiche d'un produit fini : feves d'origine, camions et ordres. */
    #[Route('/{id}', name: 'stock_tracabilite_produit', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function detail(string $id): Response
    {
        $lot = $this->lot($id);

        $feves = [];
        $camions = [];

        foreach ($lot->getLotsOrigine() as $feve) {
            $liens = $this->liens->parLot($feve);
            $lignes = [];
            foreach ($liens as $lien) {
                $camion = $lien->getDechargement();
                $ligne = [
                    'code' => (string) ($camion?->getCode() ?? '-'),
                    'date' => $camion?->getDateDechargement(),
                    'cooperative' => (string) ($camion?->getCooperative() ?? '-'),
                    'immatriculation' => (string) ($camion?->getImmatriculation() ?? '-'),
                    'transporteur' => (string) ($camion?->getTransporteur() ?? '-'),
                    'poids' => (float) ($lien->getPoidsAffecte() ?? $camion?->getPoidsNet() ?? 0),
                    'sacs' => $lien->getNbreSacs() ?? $camion?->getNbreSacs(),
                    'feve' => (string) $feve->getNumeroLot(),
                ];
                $lignes[] = $ligne;
                $cle = (string) ($camion?->getId() ?? $ligne['code']);
                $camions[$cle] = $ligne;
            }

            $feves[] = [
                'lot' => $feve,
                'camions' => $lignes,
                'nbCamions' => count($lignes),
                'qualite' => $this->nature->libelleLot($feve),
            ];
        }

        return $this->render('stock/tracabilite_produit.html.twig', [
            'lot' => $lot,
            'feves' => $feves,
            'camions' => array_values($camions),
            'poidsTotal' => array_sum(array_column(array_values($camions), 'poids')),
            'fevesDisponibles' => $this->fevesDisponibles($lot),
            'peutRattacher' => $this->peutRattacher(),
            'qualiteProduit' => $this->nature->libelleLot($lot),
            'ordreHisto' => $this->ordres->parLotFini($lot),
        ]);
    }

    /** Correction manuelle : ajout / retrait d'un lot de feves sur un produit fini. */
    #[Route('/{id}/Composition', name: 'stock_tracabilite_produit_composition', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function composition(string $id, Request $request): Response
    {
        $lot = $this->lot($id);

        if (!$this->peutRattacher()) {
            $this->addFlash('danger', 'Seuls les profils PDR, DG et administrateur peuvent modifier la traçabilité.');

            return $this->redirectToRoute('stock_tracabilite_produit', ['id' => (string) $lot->getId()]);
        }

        if (!$this->isCsrfTokenValid('lot_composition_' . $lot->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée : rechargez la page puis recommencez.');

            return $this->redirectToRoute('stock_tracabilite_produit', ['id' => (string) $lot->getId()]);
        }

        $action = (string) $request->request->get('action', 'ajouter');

        if ('retirer' === $action) {
            $feveId = (string) $request->request->get('feve', '');
            if (Uuid::isValid($feveId)) {
                $feve = $this->lots->find($feveId);
                if ($feve instanceof Lot) {
                    $lot->removeLotOrigine($feve);
                    $this->em->flush();
                    $this->addFlash('success', sprintf('Le lot de fèves %s a été retiré de la traçabilité.', $feve->getNumeroLot()));
                }
            }

            return $this->redirectToRoute('stock_tracabilite_produit', ['id' => (string) $lot->getId()]);
        }

        $ids = array_values(array_filter((array) $request->request->all('feves'), static fn ($v): bool => is_string($v) && Uuid::isValid($v)));
        $ajoutes = 0;
        $refuses = 0;

        foreach ($ids as $feveId) {
            $feve = $this->lots->find($feveId);
            if (!$feve instanceof Lot || !$this->nature->estFeve($feve)) {
                ++$refuses;

                continue;
            }
            if (!$lot->getLotsOrigine()->contains($feve)) {
                $lot->addLotOrigine($feve);
                ++$ajoutes;
            }
        }

        if ($ajoutes > 0) {
            $lot->setUpdatedAt(new \DateTimeImmutable())->setUpdatedBy($this->utilisateur());
            $this->em->flush();
        }

        $this->addFlash($ajoutes > 0 ? 'success' : 'warning', sprintf(
            '%d lot(s) de fèves rattaché(s). %d ignoré(s) : seuls les lots de fèves CITRAC sont acceptés.',
            $ajoutes,
            $refuses
        ));

        return $this->redirectToRoute('stock_tracabilite_produit', ['id' => (string) $lot->getId()]);
    }

    // ------------------------------------------------------------------
    //  Outils
    // ------------------------------------------------------------------

    private function lot(string $id): Lot
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException('Lot introuvable.');
        }

        $lot = $this->lots->find($id);
        if (!$lot instanceof Lot) {
            throw $this->createNotFoundException('Lot introuvable.');
        }

        return $lot;
    }

    /** @return list<Lot> */
    private function fevesDisponibles(Lot $lot): array
    {
        $qb = $this->lots->createQueryBuilder('l')
            ->leftJoin('l.code_produit', 'p')->addSelect('p')
            ->orderBy('l.numero_lot', 'ASC')
            ->setMaxResults(200);

        $conditions = [];
        foreach (self::MOTIFS_FEVE as $i => $motif) {
            $conditions[] = 'p.libelle LIKE :feve' . $i;
            $qb->setParameter('feve' . $i, $motif);
        }
        $qb->andWhere('(' . implode(' OR ', $conditions) . ')');

        return array_values(array_filter(
            $qb->getQuery()->getResult(),
            static fn (Lot $candidat): bool => (string) $candidat->getId() !== (string) $lot->getId()
        ));
    }

    private function numeroDisponible(): string
    {
        $numero = $this->ordres->prochainNumero();
        for ($i = 0; $i < 5; $i++) {
            if ($this->ordres->findOneBy(['numero' => $numero]) === null) {
                return $numero;
            }
            $numero = $this->ordres->prochainNumero();
        }

        return $numero;
    }

    private function peutRattacher(): bool
    {
        return $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_DG') || $this->isGranted('ROLE_PDR');
    }

    private function texte(mixed $valeur): ?string
    {
        $texte = trim((string) $valeur);

        return '' === $texte ? null : mb_substr($texte, 0, 255);
    }

    private function utilisateur(): string
    {
        $utilisateur = $this->getUser();

        return null !== $utilisateur && method_exists($utilisateur, 'getUserIdentifier')
            ? (string) $utilisateur->getUserIdentifier()
            : 'system';
    }
}
