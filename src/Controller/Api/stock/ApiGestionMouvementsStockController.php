<?php

namespace App\Controller\Api\stock;

use App\Entity\EquipementType;
use App\Entity\MouvementEquipement;
use App\Entity\Stock\SaisieStock;
use App\Entity\Stock\SortieStock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * API d'administration des mouvements de stock (reservee a ROLE_ADMIN).
 *
 * Deux familles de mouvements, traitees separement :
 *   - PIECES DE RECHANGE : entite MouvementEquipement. Le stock (quantite)
 *     est stocke sur EquipementType::qte ; toute modification ou suppression
 *     d'un mouvement RECALCULE ce stock.
 *   - CACAO (dechets / echantillons) : entites SaisieStock (entrees) et
 *     SortieStock (sorties). Le solde d'une rubrique est CALCULE a la volee
 *     (somme entrees - somme sorties) : aucun stock n'est stocke, donc aucun
 *     recalcul n'est necessaire a la suppression / modification.
 *
 * Convention type_mvt des pieces : "1" = entree, toute autre valeur = sortie.
 */
final class ApiGestionMouvementsStockController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    /** Effet d'un mouvement piece sur le stock : +value si entree, -value si sortie. */
    private function effetPiece(MouvementEquipement $m): float
    {
        $value = (float) ($m->getValue() ?? 0);
        return $m->getTypeMvt() === '1' ? $value : -$value;
    }

    /* =====================================================================
     *  PIECES DE RECHANGE  (MouvementEquipement)
     * ===================================================================== */

    #[Route('api/gestion-mvt-pieces/list', name: 'api_gestion_mvt_pieces_list', methods: ['GET'])]
    public function listPieces(): Response
    {
        if (!$this->getUser()) { return $this->redirectToRoute('app_login'); }
        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Acces reserve aux administrateurs.']);
        }
        try {
            $mouvements = $this->em->getRepository(MouvementEquipement::class)
                ->findBy([], ['created_at' => 'DESC']);
            $data = [];
            foreach ($mouvements as $m) {
                $eq = $m->getCodeEquipementType();
                $data[] = [
                    'id'          => (string) $m->getId(),
                    'type_mvt'    => $m->getTypeMvt(),
                    'type_libelle'=> $m->getTypeMvt() === '1' ? 'Entree' : 'Sortie',
                    'article'     => $eq ? $eq->getNomEquipement() : '(article supprime)',
                    'code_article'=> $eq ? $eq->getCode() : '',
                    'value'       => (float) ($m->getValue() ?? 0),
                    'numero_bon'  => $m->getNumeroBon(),
                    'service'     => $m->getCodeService() ? $m->getCodeService()->getLibelle() : '',
                    'emplacement' => $m->getEmplacement() ? $m->getEmplacement()->getLibelle() : '',
                    'observation' => $m->getObservation(),
                    'stock_apres' => $m->getStockApres(),
                    'date_operation' => $m->getDateOperation() ? $m->getDateOperation()->format('Y-m-d') : '',
                    'date_affiche'   => $m->getDateOperation() ? $m->getDateOperation()->format('d/m/Y') : '',
                    'cree_le'     => $m->getCreatedAt() ? $m->getCreatedAt()->format('d/m/Y H:i') : '',
                ];
            }
            return new JsonResponse(['code' => 'success', 'msg' => 'Success', 'data' => $data]);
        } catch (\Throwable $e) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Une erreur s\'est produite !<br>' . $e->getMessage()]);
        }
    }

    #[Route('api/gestion-mvt-pieces/update', name: 'api_gestion_mvt_pieces_update', methods: ['POST'])]
    public function updatePiece(Request $request): Response
    {
        if (!$this->getUser()) { return $this->redirectToRoute('app_login'); }
        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Acces reserve aux administrateurs.']);
        }
        $id = (string) $request->request->get('id', '');
        if ($id === '') {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Mouvement introuvable.']);
        }
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $m = $this->em->getRepository(MouvementEquipement::class)->find(Uuid::fromString($id));
            if (!$m) {
                $connection->rollBack();
                return new JsonResponse(['code' => 'warning', 'msg' => 'Mouvement introuvable.']);
            }

            $ancienneValeur = (float) ($m->getValue() ?? 0);
            $nouvelleValeur = $request->request->get('value');
            $nouvelleValeur = ($nouvelleValeur === null || $nouvelleValeur === '')
                ? $ancienneValeur : (float) $nouvelleValeur;
            if ($nouvelleValeur <= 0) {
                $connection->rollBack();
                return new JsonResponse(['code' => 'warning', 'msg' => 'La quantite doit etre superieure a zero.']);
            }

            // Recalcul du stock de l'article (EquipementType::qte).
            $eq = $m->getCodeEquipementType();
            if ($eq && $nouvelleValeur != $ancienneValeur) {
                $stock = (float) ($eq->getQte() ?? 0);
                // Difference d'effet : entree -> (new-old) ; sortie -> (old-new).
                $delta = $m->getTypeMvt() === '1'
                    ? ($nouvelleValeur - $ancienneValeur)
                    : ($ancienneValeur - $nouvelleValeur);
                $eq->setQte(max(0, $stock + $delta));
                $this->em->persist($eq);
            }
            $m->setValue($nouvelleValeur);

            if ($request->request->has('numero_bon')) {
                $m->setNumeroBon(trim((string) $request->request->get('numero_bon')) ?: null);
            }
            if ($request->request->has('observation')) {
                $m->setObservation(trim((string) $request->request->get('observation')) ?: null);
            }
            $dateOp = trim((string) $request->request->get('date_operation', ''));
            if ($dateOp !== '') {
                $d = \DateTime::createFromFormat('Y-m-d', $dateOp);
                if ($d) { $m->setDateOperation($d); }
            }

            $this->em->persist($m);
            $this->em->flush();
            $connection->commit();
            return new JsonResponse(['code' => 'success', 'msg' => 'Mouvement modifie et stock recalcule.']);
        } catch (\Throwable $e) {
            $connection->rollBack();
            return new JsonResponse(['code' => 'error', 'msg' => 'Une erreur s\'est produite !<br>' . $e->getMessage()]);
        }
    }

    #[Route('api/gestion-mvt-pieces/delete', name: 'api_gestion_mvt_pieces_delete', methods: ['POST'])]
    public function deletePieces(Request $request): Response
    {
        if (!$this->getUser()) { return $this->redirectToRoute('app_login'); }
        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Acces reserve aux administrateurs.']);
        }
        $ids = json_decode((string) $request->request->get('ids', '[]'), true);
        if (!is_array($ids) || count($ids) === 0) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Aucun mouvement selectionne.']);
        }
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $nb = 0;
            foreach ($ids as $id) {
                $m = $this->em->getRepository(MouvementEquipement::class)->find(Uuid::fromString((string) $id));
                if (!$m) { continue; }
                // On annule l'effet du mouvement sur le stock de l'article.
                $eq = $m->getCodeEquipementType();
                if ($eq) {
                    $stock = (float) ($eq->getQte() ?? 0);
                    $eq->setQte(max(0, $stock - $this->effetPiece($m)));
                    $this->em->persist($eq);
                }
                $this->em->remove($m);
                $nb++;
            }
            $this->em->flush();
            $connection->commit();
            return new JsonResponse(['code' => 'success', 'msg' => $nb . ' mouvement(s) supprime(s) et stock recalcule.']);
        } catch (\Throwable $e) {
            $connection->rollBack();
            return new JsonResponse(['code' => 'error', 'msg' => 'Une erreur s\'est produite !<br>' . $e->getMessage()]);
        }
    }

    /* =====================================================================
     *  CACAO  (SaisieStock = entrees, SortieStock = sorties)
     *  Le solde est calcule a la volee : aucun stock stocke a recalculer.
     * ===================================================================== */

    #[Route('api/gestion-mvt-cacao/list', name: 'api_gestion_mvt_cacao_list', methods: ['GET'])]
    public function listCacao(): Response
    {
        if (!$this->getUser()) { return $this->redirectToRoute('app_login'); }
        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Acces reserve aux administrateurs.']);
        }
        try {
            $data = [];
            foreach ($this->em->getRepository(SaisieStock::class)->findBy([], ['dateSaisie' => 'DESC']) as $s) {
                $data[] = [
                    'id'          => (string) $s->getId(),
                    'type'        => 'entree',
                    'type_libelle'=> 'Entree',
                    'date'        => $s->getDateSaisie() ? $s->getDateSaisie()->format('Y-m-d\\TH:i') : '',
                    'date_affiche'=> $s->getDateSaisie() ? $s->getDateSaisie()->format('d/m/Y H:i') : '',
                    'rubrique'    => $s->getRubrique(),
                    'nature'      => $s->getNature(),
                    'quantite'    => (float) ($s->getQuantite() ?? 0),
                    'unite'       => $s->getUnite(),
                    'service'     => $s->getServiceLibelle(),
                    'emplacement' => $s->getEmplacementLibelle(),
                    'motif'       => $s->getMotif(),
                    'source'      => $s->getSource(),
                    'agent'       => $s->getAgentLibelle(),
                    'photo'       => $s->getPhoto() ? true : false,
                ];
            }
            foreach ($this->em->getRepository(SortieStock::class)->findBy([], ['dateSortie' => 'DESC']) as $s) {
                $data[] = [
                    'id'          => (string) $s->getId(),
                    'type'        => 'sortie',
                    'type_libelle'=> 'Sortie',
                    'date'        => $s->getDateSortie() ? $s->getDateSortie()->format('Y-m-d\\TH:i') : '',
                    'date_affiche'=> $s->getDateSortie() ? $s->getDateSortie()->format('d/m/Y H:i') : '',
                    'rubrique'    => $s->getRubrique(),
                    'nature'      => $s->getNature(),
                    'quantite'    => (float) ($s->getQuantite() ?? 0),
                    'unite'       => $s->getUnite(),
                    'service'     => $s->getServiceLibelle(),
                    'emplacement' => $s->getEmplacementLibelle(),
                    'motif'       => $s->getMotif(),
                    'source'      => null,
                    'agent'       => $s->getAgentLibelle(),
                    'photo'       => $s->getPhoto() ? true : false,
                ];
            }
            // Tri global du plus recent au plus ancien.
            usort($data, static fn ($a, $b) => strcmp((string) $b['date'], (string) $a['date']));
            return new JsonResponse(['code' => 'success', 'msg' => 'Success', 'data' => $data]);
        } catch (\Throwable $e) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Une erreur s\'est produite !<br>' . $e->getMessage()]);
        }
    }

    #[Route('api/gestion-mvt-cacao/update', name: 'api_gestion_mvt_cacao_update', methods: ['POST'])]
    public function updateCacao(Request $request): Response
    {
        if (!$this->getUser()) { return $this->redirectToRoute('app_login'); }
        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Acces reserve aux administrateurs.']);
        }
        $type = (string) $request->request->get('type', '');
        $id   = (string) $request->request->get('id', '');
        if ($id === '' || !in_array($type, ['entree', 'sortie'], true)) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Mouvement introuvable.']);
        }
        try {
            $classe = $type === 'entree' ? SaisieStock::class : SortieStock::class;
            $m = $this->em->getRepository($classe)->find(Uuid::fromString($id));
            if (!$m) {
                return new JsonResponse(['code' => 'warning', 'msg' => 'Mouvement introuvable.']);
            }

            if ($request->request->has('quantite')) {
                $q = (float) $request->request->get('quantite');
                if ($q <= 0) {
                    return new JsonResponse(['code' => 'warning', 'msg' => 'La quantite doit etre superieure a zero.']);
                }
                $m->setQuantite($q);
            }
            if ($request->request->has('unite')) {
                $u = trim((string) $request->request->get('unite'));
                if ($u !== '') { $m->setUnite($u); }
            }
            if ($request->request->has('rubrique')) {
                $r = trim((string) $request->request->get('rubrique'));
                if ($r !== '') { $m->setRubrique($r); }
            }
            if ($request->request->has('nature')) {
                $m->setNature(trim((string) $request->request->get('nature')) ?: null);
            }
            if ($request->request->has('motif')) {
                $m->setMotif(trim((string) $request->request->get('motif')) ?: null);
            }
            // La source n'existe que pour les entrees (SaisieStock).
            if ($type === 'entree' && $request->request->has('source')) {
                $m->setSource(trim((string) $request->request->get('source')) ?: null);
            }
            $dateStr = trim((string) $request->request->get('date', ''));
            if ($dateStr !== '') {
                $d = \DateTime::createFromFormat('Y-m-d\\TH:i', $dateStr) ?: \DateTime::createFromFormat('Y-m-d', $dateStr);
                if ($d) {
                    if ($type === 'entree') { $m->setDateSaisie($d); } else { $m->setDateSortie($d); }
                }
            }

            $this->em->persist($m);
            $this->em->flush();
            return new JsonResponse(['code' => 'success', 'msg' => 'Mouvement modifie avec succes.']);
        } catch (\Throwable $e) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Une erreur s\'est produite !<br>' . $e->getMessage()]);
        }
    }

    #[Route('api/gestion-mvt-cacao/delete', name: 'api_gestion_mvt_cacao_delete', methods: ['POST'])]
    public function deleteCacao(Request $request): Response
    {
        if (!$this->getUser()) { return $this->redirectToRoute('app_login'); }
        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Acces reserve aux administrateurs.']);
        }
        // Format attendu : [{"type":"entree|sortie","id":"..."}, ...]
        $items = json_decode((string) $request->request->get('items', '[]'), true);
        if (!is_array($items) || count($items) === 0) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Aucun mouvement selectionne.']);
        }
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $nb = 0;
            foreach ($items as $it) {
                $type = isset($it['type']) ? (string) $it['type'] : '';
                $id   = isset($it['id']) ? (string) $it['id'] : '';
                if ($id === '' || !in_array($type, ['entree', 'sortie'], true)) { continue; }
                $classe = $type === 'entree' ? SaisieStock::class : SortieStock::class;
                $m = $this->em->getRepository($classe)->find(Uuid::fromString($id));
                if (!$m) { continue; }
                $this->em->remove($m);
                $nb++;
            }
            $this->em->flush();
            $connection->commit();
            return new JsonResponse(['code' => 'success', 'msg' => $nb . ' mouvement(s) supprime(s).']);
        } catch (\Throwable $e) {
            $connection->rollBack();
            return new JsonResponse(['code' => 'error', 'msg' => 'Une erreur s\'est produite !<br>' . $e->getMessage()]);
        }
    }
}
