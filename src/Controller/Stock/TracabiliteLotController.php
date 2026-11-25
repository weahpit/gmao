<?php

namespace App\Controller\Stock;

use App\Entity\Production\Lot;
use App\Entity\Stock\DechargementCacao;
use App\Entity\Stock\LotDechargement;
use App\Entity\Stock\OrdreUsinage;
use App\Repository\Production\LotRepository;
use App\Repository\Stock\DechargementCacaoRepository;
use App\Repository\Stock\LotDechargementRepository;
use App\Service\Production\NatureLot;
use App\Repository\Stock\OrdreUsinageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * TRACABILITE SIMPLIFIEE : ORDRE D'USINAGE.
 *
 * Principe : on coche UN OU PLUSIEURS camions, on coche UN GROUPE DE LOTS, on
 * valide. L'application genere UN numero d'ordre d'usinage (OU-AAAA-0001) et
 * cree le rattachement de chaque lot du groupe a chacun des camions coches.
 *
 * Parcours :
 *   1. GET  /Stock/Tracabilite/Lots                 ecran unique (lots + camions + ordres)
 *   2. POST /Stock/Tracabilite/Ordre               creation d'un ordre d'usinage
 *   3. POST /Stock/Tracabilite/Ordre/{id}/Supprimer annulation d'un ordre
 *   4. GET  /Stock/Tracabilite/Lot/{id}             detail d'un lot
 *   5. POST /Stock/Tracabilite/Lot/{id}/Retirer/{lien}  retrait d'un camion
 *   6. GET  /Stock/Tracabilite/Dechargement/{id}   vue inverse (camion -> lots)
 */
#[Route('/Stock/Tracabilite')]
#[IsGranted('ROLE_USER')]
class TracabiliteLotController extends AbstractController
{
    private const LOTS_PAR_PAGE = 25;
    private const CAMIONS_MAX = 20;

    /**
     * L'ordre d'usinage ne concerne QUE les lots de feves : on ne propose donc
     * que les lots dont le produit contient "FEVE" (feve, feves, fève, féves...).
     * Les lots sans produit renseigne sont ecartes.
     */
    private const MOTIFS_FEVE = ['%FEVE%', '%FÈVE%', '%FÊVE%', '%FËVE%'];

    public function __construct(
        private readonly LotRepository $lots,
        private readonly DechargementCacaoRepository $dechargements,
        private readonly LotDechargementRepository $liens,
        private readonly OrdreUsinageRepository $ordres,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly NatureLot $nature,
    ) {
    }

    /** Ecran unique : choisir les lots, cocher un ou plusieurs camions, generer l'ordre. */
    #[Route('/Lots', name: 'stock_tracabilite_lots', methods: ['GET'])]
    public function lots(Request $request): Response
    {
        $terme = trim((string) $request->query->get('q', ''));
        $statut = (string) $request->query->get('statut', 'tous');
        $page = max(1, (int) $request->query->get('page', 1));
        $rechercheCamion = trim((string) $request->query->get('cq', ''));

        $qb = $this->lots->createQueryBuilder('l')->leftJoin('l.code_produit', 'p')->addSelect('p');

        // ------ Lots proposes : uniquement les lots de feves ------
        // L'ordre d'usinage rattache des FEVE aux camions. Les produits finis se
        // rattachent aux lots de feves sur la page « Ordre de production ».
        $conditionsFeve = [];
        foreach (self::MOTIFS_FEVE as $i => $motif) {
            $conditionsFeve[] = 'p.libelle LIKE :feve' . $i;
            $qb->setParameter('feve' . $i, $motif);
        }
        $qb->andWhere('(' . implode(' OR ', $conditionsFeve) . ')');

        if ($terme !== '') {
            $qb->andWhere('UPPER(l.numero_lot) LIKE :terme OR UPPER(l.qualite) LIKE :terme')
               ->setParameter('terme', '%' . mb_strtoupper($terme) . '%');
        }
        if ($statut === 'sans_camion') {
            $qb->andWhere('NOT EXISTS (SELECT ld1.id FROM App\\Entity\\Stock\\LotDechargement ld1 WHERE ld1.lot = l)');
        } elseif ($statut === 'avec_camion') {
            $qb->andWhere('EXISTS (SELECT ld2.id FROM App\\Entity\\Stock\\LotDechargement ld2 WHERE ld2.lot = l)');
        }

        $total = (int) (clone $qb)->select('COUNT(l.id)')->getQuery()->getSingleScalarResult();

        $resultats = $qb->orderBy('l.numero_lot', 'DESC')
            ->setFirstResult(($page - 1) * self::LOTS_PAR_PAGE)
            ->setMaxResults(self::LOTS_PAR_PAGE)
            ->getQuery()
            ->getResult();

        $stats = $this->liens->statistiques();
        $lignes = [];
        foreach ($resultats as $lot) {
            $s = $stats[(string) $lot->getId()] ?? ['nb' => 0, 'poids' => 0.0, 'sacs' => 0];
            $theorique = (float) ($lot->getPnTh() ?? 0);
            $lignes[] = [
                'lot'        => $lot,
                'qualiteLibelle' => $this->nature->libelleLot($lot),
                'camions'    => $s['nb'],
                'poids'      => $s['poids'],
                'theorique'  => $theorique,
                'couverture' => $theorique > 0 ? round($s['poids'] / $theorique * 100, 1) : null,
            ];
        }

        // Camions proposes au choix (un ou plusieurs pour le groupe de lots).
        $dejaRattaches = $this->liens->compterParDechargement();
        $camions = [];
        $qbCamions = $this->dechargements->createQueryBuilder('d')
            ->orderBy('d.dateDechargement', 'DESC')
            ->addOrderBy('d.dateBon', 'DESC')
            ->setMaxResults(self::CAMIONS_MAX);

        if ($rechercheCamion !== '') {
            $motif = '%' . mb_strtoupper($rechercheCamion) . '%';
            $qbCamions->andWhere('UPPER(d.code) LIKE :cq OR UPPER(d.cooperative) LIKE :cq OR UPPER(d.immatriculation) LIKE :cq')
                      ->setParameter('cq', $motif);
        }

        foreach ($qbCamions->getQuery()->getResult() as $camion) {
            $camions[] = [
                'camion' => $camion,
                'lots'   => $dejaRattaches[(string) $camion->getId()] ?? 0,
            ];
        }

        // Derniers ordres d'usinage + leurs lots.
        $groupes = $this->liens->grouperParOrdre();
        $ordres = [];
        foreach ($this->ordres->recents(25) as $ordre) {
            $liens = $groupes[(string) $ordre->getId()] ?? [];
            $poids = 0.0;
            foreach ($liens as $lien) {
                $poids += (float) ($lien->getPoidsAffecte() ?? $lien->getDechargement()?->getPoidsNet() ?? 0);
            }
            $ordres[] = [
                'ordre' => $ordre,
                'liens' => $liens,
                'poids' => $poids,
            ];
        }

        return $this->render('stock/tracabilite_lots.html.twig', [
            'lignes'      => $lignes,
            'total'       => $total,
            'page'        => $page,
            'pages'       => max(1, (int) ceil($total / self::LOTS_PAR_PAGE)),
            'terme'       => $terme,
            'statut'      => $statut,
            'camions'     => $camions,
            'rechercheCamion' => $rechercheCamion,
            'ordres'      => $ordres,
            'globaux'     => $this->liens->totauxGlobaux(),
            'nbOrdres'    => $this->ordres->compter(),
        ]);
    }

    /** Vrai si le produit du lot est une feve (feve, feves, fève, féves...). */
    private function estLotFeve(Lot $lot): bool
    {
        $libelle = $lot->getCodeProduit()?->getLibelle();
        if ($libelle === null || trim($libelle) === '') {
            return false;
        }

        $normalise = strtr(mb_strtoupper($libelle, 'UTF-8'), [
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C', 'Œ' => 'OE', 'Æ' => 'AE',
        ]);

        return str_contains($normalise, 'FEVE');
    }

    /** Cree un ordre d'usinage : N camions x N lots selectionnes. */
    #[Route('/Ordre', name: 'stock_ordre_usinage_creer', methods: ['POST'])]
    public function creerOrdre(Request $request): Response
    {
        $retour = $this->redirectToRoute('stock_tracabilite_lots');

        if (!$this->isCsrfTokenValid('ordre_usinage_creation', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expiree, merci de recommencer.');

            return $retour;
        }

        $idsLots = $request->request->all('lots');
        $idsCamions = $request->request->all('camions');

        if (!is_array($idsLots) || $idsLots === []) {
            $this->addFlash('danger', 'Cochez au moins un lot a rattacher.');

            return $retour;
        }
        if (!is_array($idsCamions) || $idsCamions === []) {
            $this->addFlash('danger', 'Cochez au moins un camion utilise pour ce groupe de lots.');

            return $retour;
        }

        // ------ Camions coches (au moins un, doublons ignores) ------
        $camions = [];
        foreach (array_unique($idsCamions) as $idCamion) {
            if (!Uuid::isValid((string) $idCamion)) {
                continue;
            }
            /** @var DechargementCacao|null $camion */
            $camion = $this->dechargements->find(Uuid::fromString((string) $idCamion));
            if ($camion !== null) {
                $camions[(string) $camion->getId()] = $camion;
            }
        }

        if ($camions === []) {
            $this->addFlash('danger', 'Camion introuvable : rafraichissez la page puis recommencez.');

            return $retour;
        }

        $ordre = new OrdreUsinage();
        $ordre->setNumero($this->numeroDisponible())
            ->setCreatedBy($this->utilisateur())
            ->setObservation($this->texte($request->request->get('observation')));

        foreach ($camions as $camion) {
            $ordre->addCamion($camion);
        }
        $this->em->persist($ordre);

        // ------ Un rattachement par couple lot x camion ------
        $crees = 0;
        $ignores = 0;
        $lotsTraites = 0;
        $rejetes = 0;

        foreach (array_unique($idsLots) as $idLot) {
            if (!Uuid::isValid((string) $idLot)) {
                continue;
            }

            /** @var Lot|null $lot */
            $lot = $this->lots->find(Uuid::fromString((string) $idLot));
            if ($lot === null) {
                continue;
            }

            // Controle serveur : un ordre d'usinage ne concerne que les feves.
            if (!$this->estLotFeve($lot)) {
                $rejetes++;
                $this->addFlash('danger', sprintf(
                    'Le lot %s n\'est pas un lot de feves : il ne peut pas entrer dans un ordre d\'usinage.',
                    (string) $lot->getNumeroLot()
                ));
                continue;
            }

            // Un lot de feves n'est pas scindable : il ne peut pas etre reparti
            // entre plusieurs ordres d'usinage. Pour le deplacer, annulez d'abord
            // l'ordre auquel il est rattache.
            if ($this->liens->compterParLot($lot) > 0) {
                $rejetes++;
                $this->addFlash('danger', sprintf(
                    'Le lot de fèves %s est déjà rattaché à un ordre d\'usinage : un lot de fèves ne peut pas être scindé entre plusieurs ordres. Annulez d\'abord cet ordre pour le déplacer.',
                    (string) $lot->getNumeroLot()
                ));
                continue;
            }

            // Camions pas encore lies a ce lot (pas de doublon).
            $nouveaux = [];
            foreach ($camions as $camion) {
                if ($this->liens->pourLotEtDechargement($lot, $camion) !== null) {
                    $ignores++;
                    continue;
                }
                $nouveaux[] = $camion;
            }

            if ($nouveaux === []) {
                continue;
            }

            // Poids theorique du lot reparti a parts egales entre les camions,
            // pour que la somme des poids affectes reste coherente avec le lot.
            $theorique = (float) ($lot->getPnTh() ?? 0);
            $part = $theorique > 0 ? round($theorique / count($nouveaux), 2) : null;

            foreach ($nouveaux as $camion) {
                $lien = new LotDechargement();
                $lien->setLot($lot)
                    ->setDechargement($camion)
                    ->setOrdreUsinage($ordre)
                    ->setPoidsAffecte($part)
                    ->setCreatedBy($this->utilisateur());

                $this->em->persist($lien);
                $crees++;
            }
            $lotsTraites++;
        }

        if ($crees === 0) {
            $this->em->remove($ordre);
            $this->em->clear();
            $this->addFlash('info', $rejetes > 0
                ? sprintf('Aucun rattachement cree : %d lot(s) refuse(s) car ce ne sont pas des lots de feves.', $rejetes)
                : 'Aucun nouveau rattachement : ces lots sont deja lies a ces camions.');

            return $retour;
        }

        try {
            $this->em->flush();
            $this->addFlash('success', sprintf(
                'Ordre d\'usinage %s enregistre : %d camion(s), %d lot(s), %d rattachement(s).',
                $ordre->getNumero(),
                count($camions),
                $lotsTraites,
                $crees
            ));
            if ($rejetes > 0) {
                $this->addFlash('warning', sprintf('%d lot(s) ont ete ignores : ce ne sont pas des lots de feves.', $rejetes));
            }
            if ($ignores > 0) {
                $this->addFlash('info', $ignores . ' rattachement(s) existaient deja et ont ete ignores.');
            }
        } catch (\Throwable $e) {
            $this->logger->error('Ordre d\'usinage : echec enregistrement', ['erreur' => $e->getMessage()]);
            $this->addFlash('danger', 'Enregistrement impossible : ' . $e->getMessage());
        }

        return $retour;
    }

    /** Annule un ordre d'usinage (retire ses rattachements). */
    #[Route('/Ordre/{id}/Supprimer', name: 'stock_ordre_usinage_supprimer', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function supprimerOrdre(OrdreUsinage $ordre, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('ordre_usinage_suppression', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expiree, merci de recommencer.');

            return $this->redirectToRoute('stock_tracabilite_lots');
        }

        $numero = (string) $ordre->getNumero();

        try {
            foreach ($this->liens->parOrdre($ordre) as $lien) {
                $this->em->remove($lien);
            }
            $this->em->remove($ordre);
            $this->em->flush();
            $this->addFlash('success', 'Ordre d\'usinage ' . $numero . ' annule : les lots ne sont plus rattaches a ce camion.');
        } catch (\Throwable $e) {
            $this->logger->error('Ordre d\'usinage : echec suppression', ['numero' => $numero, 'erreur' => $e->getMessage()]);
            $this->addFlash('danger', 'Annulation impossible : ' . $e->getMessage());
        }

        return $this->redirectToRoute('stock_tracabilite_lots');
    }

    /** Detail d'un lot : ordres d'usinage et camions rattaches. */
    #[Route('/Lot/{id}', name: 'stock_tracabilite_lot', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function lot(Lot $lot): Response
    {
        $liens = $this->liens->parLot($lot);
        $totalAffecte = 0.0;
        foreach ($liens as $lien) {
            $totalAffecte += (float) ($lien->getPoidsAffecte() ?? $lien->getDechargement()?->getPoidsNet() ?? 0);
        }
        $theorique = (float) ($lot->getPnTh() ?? 0);

        return $this->render('stock/tracabilite_lot.html.twig', [
            'lot'          => $lot,
            'liens'        => $liens,
            'totalAffecte' => $totalAffecte,
            'theorique'    => $theorique,
            'couverture'   => $theorique > 0 ? round($totalAffecte / $theorique * 100, 1) : null,
            'qualiteLibelle' => $this->nature->libelleLot($lot),
        ]);
    }

    /** Retire un camion d'un lot. */
    #[Route('/Lot/{id}/Retirer/{lien}', name: 'stock_tracabilite_lot_retirer', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}', 'lien' => '[0-9a-fA-F-]{36}'])]
    public function retirer(Lot $lot, string $lien, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('tracabilite_lot_retrait', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expiree, merci de recommencer.');

            return $this->redirectToRoute('stock_tracabilite_lot', ['id' => $lot->getId()]);
        }

        $association = $this->liens->find(Uuid::fromString($lien));
        if ($association === null || $association->getLot()?->getId()?->equals($lot->getId()) !== true) {
            $this->addFlash('danger', 'Rattachement introuvable pour ce lot.');

            return $this->redirectToRoute('stock_tracabilite_lot', ['id' => $lot->getId()]);
        }

        $code = $association->getDechargement()?->getCode() ?? '-';
        $this->em->remove($association);
        $this->em->flush();

        $this->addFlash('success', 'Le camion ' . $code . ' a ete retire du lot ' . $lot->getNumeroLot() . '.');

        return $this->redirectToRoute('stock_tracabilite_lot', ['id' => $lot->getId()]);
    }

    /** Vue inverse : lots fabriques avec ce camion, regroupes par ordre d'usinage. */
    #[Route('/Dechargement/{id}', name: 'stock_tracabilite_dechargement', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function dechargement(DechargementCacao $dechargement): Response
    {
        $liens = $this->liens->parDechargement($dechargement);
        $total = 0.0;
        foreach ($liens as $lien) {
            $total += (float) ($lien->getPoidsAffecte() ?? $dechargement->getPoidsNet() ?? 0);
        }

        return $this->render('stock/tracabilite_dechargement.html.twig', [
            'dechargement' => $dechargement,
            'liens'        => $liens,
            'totalAffecte' => $total,
            'qualites'     => NatureLot::GRADES + NatureLot::QUALITES_FINIES,
        ]);
    }

    /** Numero libre, avec quelques essais en cas de collision. */
    private function numeroDisponible(): string
    {
        $numero = $this->ordres->prochainNumero();
        for ($i = 0; $i < 5; $i++) {
            if ($this->ordres->findOneBy(['numero' => $numero]) === null) {
                return $numero;
            }
            $numero = $this->ordres->prochainNumero();
        }

        return 'OU-' . date('YmdHis');
    }

    private function texte(mixed $valeur): ?string
    {
        $texte = is_scalar($valeur) ? trim((string) $valeur) : '';

        return $texte === '' ? null : mb_substr($texte, 0, 255);
    }

    private function utilisateur(): string
    {
        $utilisateur = $this->getUser();

        return null !== $utilisateur && method_exists($utilisateur, 'getUserIdentifier')
            ? (string) $utilisateur->getUserIdentifier()
            : 'system';
    }
}
