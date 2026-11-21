<?php

namespace App\Controller\Dashboard;

use App\Entity\Dashboard\PreferenceGadget;
use App\Entity\User;
use App\Repository\Dashboard\PreferenceGadgetRepository;
use App\Service\Dashboard\DonneesTableauDeBord;
use App\Service\Dashboard\OptionsGraphique;
use App\Service\Dashboard\RegistreGadgets;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Tableau de bord propre au role de l'utilisateur connecte.
 *
 * Chaque compte voit le tableau de bord de son role metier (RH, stock,
 * logistique, courrier, maintenance, direction, securite, sante, encadrement)
 * et peut masquer, desactiver ou reorganiser ses gadgets. Les choix sont
 * enregistres par compte et par role : un utilisateur qui cumule deux roles
 * conserve deux dispositions distinctes.
 */
#[Route('/mon-tableau-de-bord')]
#[IsGranted('ROLE_USER')]
final class TableauDeBordRoleController extends AbstractController
{
    public function __construct(
        private RegistreGadgets $registre,
        private PreferenceGadgetRepository $preferences
    ) {
    }

    #[Route('', name: 'tdb_role', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $roles = $this->registre->rolesDisponibles($utilisateur);
        $role = $this->roleCourant($request, $utilisateur, $roles);

        $definitionRole = $this->registre->definitionRole($role);
        $couleurRole = OptionsGraphique::couleurRole($role);
        $preferences = $this->preferences->pourUtilisateur($utilisateur, $role);
        $definitions = $this->trier($this->registre->gadgetsPour($role), $preferences);

        $cartes = [];
        $graphiques = [];
        $cachees = 0;
        $desactivees = 0;

        foreach ($definitions as $rang => $gadget) {
            $preference = $preferences[$gadget['code']] ?? null;
            $couleur = OptionsGraphique::couleur($role, (int) $rang);
            $gadget['teinte'] = $couleur['teinte'];
            $visible = $preference ? $preference->isVisible() : true;
            $actif = $preference ? $preference->isActif() : true;

            $carte = [
                'code' => $gadget['code'],
                'libelle' => $gadget['libelle'],
                'description' => $gadget['description'],
                'type' => $gadget['type'],
                'largeur' => (int) $gadget['largeur'],
                'visible' => $visible,
                'actif' => $actif,
                'donnees' => null,
                'couleur' => $couleur['base'],
                'couleurAccent' => $couleur['accent'],
                'couleurDouce' => $couleur['douce'],
            ];

            if (!$visible) {
                $cachees++;
            } elseif (!$actif) {
                $desactivees++;
            } else {
                try {
                    $carte['donnees'] = ($gadget['calcul'])($utilisateur);
                } catch (\Throwable $e) {
                    $carte['donnees'] = DonneesTableauDeBord::erreur($e);
                }

                $estGraphique = in_array($gadget['type'], ['donut', 'barres', 'ligne', 'aire'], true);
                if ($estGraphique && !isset($carte['donnees']['erreur'])) {
                    $graphiques['graphe-' . $gadget['code']] = OptionsGraphique::pour($gadget, $carte['donnees']);
                }
            }

            $cartes[] = $carte;
        }

        return $this->render('dashboard_role/index.html.twig', [
            'role' => $role,
            'roleLibelle' => $definitionRole['libelle'],
            'roleIcone' => $definitionRole['icone'],
            'roleAccroche' => $definitionRole['accroche'],
            'roleCouleur' => $couleurRole['base'],
            'roleCouleurDouce' => $couleurRole['douce'],
            'roles' => $roles,
            'cartes' => $cartes,
            'graphiques' => $graphiques,
            'nb_masquees' => $cachees,
            'nb_desactivees' => $desactivees,
            'nb_affichees' => count(array_filter($cartes, static fn (array $c): bool => $c['visible'] && $c['actif'])),
        ]);
    }

    #[Route('/role', name: 'tdb_role_changer', methods: ['POST'])]
    public function changerRole(Request $request): Response
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('tdb_role', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton de securite invalide : le changement de tableau de bord a ete refuse.');
        }

        $role = (string) $request->request->get('role');
        $roles = $this->registre->rolesDisponibles($utilisateur);
        if (!isset($roles[$role])) {
            throw $this->createAccessDeniedException('Ce tableau de bord ne fait pas partie de vos roles.');
        }

        $request->getSession()->set('tdb_role_actif', $role);

        return $this->redirectToRoute('tdb_role');
    }

    #[Route('/gadget', name: 'tdb_role_gadget', methods: ['POST'])]
    public function gadget(Request $request): Response
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('tdb_gadget', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton de securite invalide : la modification du gadget a ete refusee.');
        }

        $role = (string) $request->request->get('role');
        $code = (string) $request->request->get('code');
        $action = (string) $request->request->get('action');

        $roles = $this->registre->rolesDisponibles($utilisateur);
        if (!isset($roles[$role])) {
            throw $this->createAccessDeniedException('Ce tableau de bord ne fait pas partie de vos roles.');
        }
        if (!$this->registre->existe($role, $code)) {
            throw $this->createNotFoundException('Gadget inconnu pour ce tableau de bord : ' . $code);
        }

        $preference = $this->preferences->pourGadget($utilisateur, $role, $code);
        if (!$preference) {
            $preference = (new PreferenceGadget())
                ->setUtilisateur($utilisateur)
                ->setRole($role)
                ->setCode($code);
            $this->preferences->getEntityManager()->persist($preference);
        }

        switch ($action) {
            case 'masquer':
                $preference->setVisible(false);
                break;
            case 'afficher':
                $preference->setVisible(true);
                break;
            case 'desactiver':
                $preference->setActif(false);
                break;
            case 'activer':
                $preference->setActif(true);
                break;
            case 'monter':
            case 'descendre':
                $this->preferences->getEntityManager()->flush();
                $this->deplacer($utilisateur, $role, $code, $action === 'monter' ? -1 : 1);

                return $this->redirectToRoute('tdb_role', ['role' => $role]);
            default:
                throw $this->createNotFoundException('Action inconnue : ' . $action);
        }

        $this->preferences->getEntityManager()->flush();

        return $this->redirectToRoute('tdb_role', ['role' => $role]);
    }

    #[Route('/reinitialiser', name: 'tdb_role_reinitialiser', methods: ['POST'])]
    public function reinitialiser(Request $request): Response
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('tdb_gadget', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton de securite invalide : la reinitialisation a ete refusee.');
        }

        $role = (string) $request->request->get('role');
        $roles = $this->registre->rolesDisponibles($utilisateur);
        if (!isset($roles[$role])) {
            throw $this->createAccessDeniedException('Ce tableau de bord ne fait pas partie de vos roles.');
        }

        $this->preferences->supprimerPourRole($utilisateur, $role);

        return $this->redirectToRoute('tdb_role', ['role' => $role]);
    }

    private function roleCourant(Request $request, User $utilisateur, array $roles): string
    {
        $demande = (string) $request->query->get('role', '');
        if ($demande !== '' && isset($roles[$demande])) {
            $request->getSession()->set('tdb_role_actif', $demande);

            return $demande;
        }

        $enregistre = $request->getSession()->get('tdb_role_actif');
        if (is_string($enregistre) && isset($roles[$enregistre])) {
            return $enregistre;
        }

        $defaut = array_key_first($roles);
        if ($defaut === null) {
            $defaut = 'MON_ESPACE';
        }
        $request->getSession()->set('tdb_role_actif', $defaut);

        return $defaut;
    }

    /**
     * Applique l'ordre choisi par l'utilisateur, puis l'ordre du catalogue.
     *
     * @param list<array<string, mixed>> $definitions
     * @param array<string, PreferenceGadget> $preferences
     *
     * @return list<array<string, mixed>>
     */
    private function trier(array $definitions, array $preferences): array
    {
        usort($definitions, static function (array $a, array $b) use ($preferences): int {
            $ordreA = isset($preferences[$a['code']]) ? $preferences[$a['code']]->getOrdre() : PHP_INT_MAX;
            $ordreB = isset($preferences[$b['code']]) ? $preferences[$b['code']]->getOrdre() : PHP_INT_MAX;

            return $ordreA <=> $ordreB;
        });

        return $definitions;
    }

    private function deplacer(User $utilisateur, string $role, string $code, int $decalage): void
    {
        $preferences = $this->preferences->pourUtilisateur($utilisateur, $role);
        $codes = array_map(
            static fn (array $gadget): string => $gadget['code'],
            $this->trier($this->registre->gadgetsPour($role), $preferences)
        );

        $position = array_search($code, $codes, true);
        if ($position === false) {
            return;
        }

        $cible = $position + $decalage;
        if ($cible < 0 || $cible >= count($codes)) {
            return;
        }

        [$codes[$position], $codes[$cible]] = [$codes[$cible], $codes[$position]];
        $this->preferences->enregistrerOrdre($utilisateur, $role, $codes);
    }
}
