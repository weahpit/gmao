<?php

namespace App\Controller\Api\stock;

use App\Entity\Stock\Rubrique;
use App\Service\Stock\SuppressionRubrique;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiRubriqueController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SuppressionRubrique $suppression,
    ) {}

    #[Route('api/getAllRubriques', name: 'app_api_get_rubrique')]
    public function index(): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        $reponse = [];
        $data = [];
        if ($this->isGranted('ROLE_USER')) {
            try {
                // Liste des natures d'equipements
                $rubriques = $this->em->getRepository(Rubrique::class)->findBy([], ['position' => 'ASC', 'libelle' => 'ASC']);
                foreach ($rubriques as $rubrique) {
                    $impact = $this->suppression->impact($rubrique);
                    $data[] = [
                        'id'       => (string) $rubrique->getId(),
                        'rubrique' => $rubrique->getLibelle(),
                        'active'   => $rubrique->isActive() ? 'OUI' : 'NON',
                        'position' => $rubrique->getPosition(),
                        'mouvements' => $impact['total'],
                    ];
                }
                $reponse = ['code' => 'success', 'msg' => 'Succès', 'data' => $data];
            } catch (\Throwable $throwable) {
                $reponse = ['code' => 'error', 'msg' => 'Erreur ! <br>' . $throwable->getMessage()];
            }
        } else {
            $reponse = ['code' => 'warning', 'msg' => 'Vous n\'êtes pas autorisé à accéder à cette ressource'];
        }

        return new JsonResponse($reponse);
    }

    #[Route('api/getSingleRubrique', name: 'app_api_get_single_rubrique')]
    public function app_api_get_single_rubrique(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        if ($this->isGranted('ROLE_USER')) {
            try {
                $id_rubrique = (string) $request->request->get('id_rubrique', '');
                $rubrique = $id_rubrique !== '' ? $this->em->getRepository(Rubrique::class)->find(Uuid::fromString($id_rubrique)) : null;

                if ($rubrique) {
                    $reponse = [
                        'code'     => 'success',
                        'msg'      => 'Success',
                        'rubrique' => $rubrique->getLibelle(),
                        'active'   => $rubrique->isActive() ? 'OUI' : 'NON',
                        'position' => $rubrique->getPosition(),
                    ];
                } else {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de sélectionner une rubrique dans la liste !'];
                }
            } catch (\Throwable $throwable) {
                $reponse = ['code' => 'error', 'msg' => 'Une erreur s\'est produite !<br>' . $throwable->getMessage()];
            }
        } else {
            $reponse = ['code' => 'warning', 'msg' => 'Vous n\'êtes pas autorisé à accéder à cette ressource'];
        }

        return new JsonResponse($reponse);
    }

    #[Route('api/saveRubrique', name: 'app_api_save_rubrique')]
    public function app_api_save_rubrique(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_DG')) {
            try {
                $id_rubrique = (string) $request->request->get('id_rubrique', '');
                $libelle_rubrique = trim((string) $request->request->get('rubrique', ''));
                $position = (int) $request->request->get('position', 0);

                if ($libelle_rubrique === '') {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir la rubrique !'];
                } else {
                    $rubrique = null;
                    if ($id_rubrique !== '' && $id_rubrique !== '0') {
                        $rubrique = $this->em->getRepository(Rubrique::class)->find(Uuid::fromString($id_rubrique));
                    }

                    // Doublon : meme libelle (insensible a la casse) sur une AUTRE fiche.
                    foreach ($this->em->getRepository(Rubrique::class)->findAll() as $existante) {
                        if ($rubrique instanceof Rubrique && $existante->getId() === $rubrique->getId()) {
                            continue;
                        }
                        if (mb_strtoupper((string) $existante->getLibelle()) === mb_strtoupper($libelle_rubrique)) {
                            return new JsonResponse(['code' => 'warning', 'msg' => 'Cette rubrique existe déjà !']);
                        }
                    }

                    $isNew = false;
                    if (!$rubrique instanceof Rubrique) {
                        $rubrique = new Rubrique();
                        $isNew = true;
                    }

                    $rubrique->setLibelle(mb_strtoupper($libelle_rubrique));
                    $rubrique->setPosition($position);

                    $this->em->persist($rubrique);
                    $this->em->flush();

                    $reponse = [
                        'code' => 'success',
                        'msg'  => $isNew ? 'Rubrique créée avec succès !' : 'Rubrique mise à jour avec succès !',
                    ];
                }
            } catch (\Throwable $throwable) {
                $reponse = ['code' => 'error', 'msg' => 'Une erreur s\'est produite !<br>' . $throwable->getMessage()];
            }
        } else {
            $reponse = ['code' => 'warning', 'msg' => 'Vous n\'êtes pas autorisé à accéder à cette ressource'];
        }

        return new JsonResponse($reponse);
    }

    /**
     * Combien de mouvements utilisent cette rubrique, et vers quoi les
     * reafecter ? Appele avant d'ouvrir la fenetre de confirmation.
     */
    #[Route('api/impactRubrique', name: 'app_api_impact_rubrique', methods: ['POST'])]
    public function app_api_impact_rubrique(Request $request): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Seul un administrateur peut supprimer une rubrique.']);
        }

        try {
            $rubrique = $this->trouver($request);
            if (!$rubrique instanceof Rubrique) {
                return new JsonResponse(['code' => 'warning', 'msg' => 'Rubrique introuvable. Rechargez la page.']);
            }

            $impact = $this->suppression->impact($rubrique);
            $cibles = [];
            foreach ($this->suppression->ciblesPossibles($rubrique) as $cible) {
                $cibles[] = ['id' => (string) $cible->getId(), 'libelle' => $cible->getLibelle()];
            }

            $details = [];
            foreach ($impact['details'] as $ligne) {
                $details[] = $ligne['nombre'] . ' ' . $ligne['libelle'];
            }

            return new JsonResponse([
                'code'    => 'success',
                'msg'     => 'Succès',
                'id'      => (string) $rubrique->getId(),
                'rubrique' => $rubrique->getLibelle(),
                'total'   => $impact['total'],
                'details' => $details,
                'cibles'  => $cibles,
                'divers'  => SuppressionRubrique::RUBRIQUE_DIVERS,
            ]);
        } catch (\Throwable $throwable) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Erreur : ' . $throwable->getMessage()]);
        }
    }

    /**
     * Supprime la rubrique. Si des mouvements existent, la rubrique de
     * destination (envoyee dans « cible ») les recoit avant la suppression.
     */
    #[Route('api/deleteRubrique', name: 'app_api_delete_rubrique', methods: ['POST'])]
    public function app_api_delete_rubrique(Request $request): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Seul un administrateur peut supprimer une rubrique.']);
        }

        try {
            $rubrique = $this->trouver($request);
            if (!$rubrique instanceof Rubrique) {
                return new JsonResponse(['code' => 'warning', 'msg' => 'Rubrique introuvable. Rechargez la page.']);
            }

            $cible = trim((string) $request->request->get('cible', ''));
            $resultat = $this->suppression->supprimer($rubrique, $cible !== '' ? $cible : null);

            $msg = 'Rubrique supprimée avec succès !';
            if (($resultat['deplaces'] ?? 0) > 0) {
                $msg = sprintf(
                    'Rubrique supprimée. %d mouvement(s) ont été transférés vers « %s ».',
                    $resultat['deplaces'],
                    $resultat['cible']
                );
            }

            return new JsonResponse(['code' => 'success', 'msg' => $msg]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['code' => 'warning', 'msg' => $e->getMessage()]);
        } catch (\Throwable $throwable) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Suppression impossible : ' . $throwable->getMessage()]);
        }
    }

    /** Retrouve la rubrique designee par la requete (id, sinon libelle). */
    private function trouver(Request $request): ?Rubrique
    {
        $id = (string) $request->request->get('id_rubrique', '');
        if ($id !== '') {
            try {
                $rubrique = $this->em->getRepository(Rubrique::class)->find(Uuid::fromString($id));
                if ($rubrique instanceof Rubrique) {
                    return $rubrique;
                }
            } catch (\Throwable) {
                // identifiant invalide : on tente par libelle
            }
        }

        $libelle = trim((string) $request->request->get('rubrique', ''));
        if ($libelle !== '') {
            foreach ($this->em->getRepository(Rubrique::class)->findAll() as $candidate) {
                if (mb_strtoupper((string) $candidate->getLibelle()) === mb_strtoupper($libelle)) {
                    return $candidate;
                }
            }
        }

        return null;
    }
}
