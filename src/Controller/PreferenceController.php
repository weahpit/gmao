<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Preferences d'affichage de l'utilisateur CONNECTE.
 *
 * Pour l'instant : enregistre le theme de couleurs choisi dans le
 * selecteur de theme, afin qu'il soit reaffiche automatiquement a la
 * prochaine connexion (sur n'importe quel navigateur).
 *
 * Securite :
 *  - #[IsGranted('ROLE_USER')] : accessible uniquement si authentifie.
 *  - On agit TOUJOURS sur $this->getUser() (l'utilisateur de la session),
 *    jamais sur un id passe en parametre -> impossible de modifier les
 *    preferences d'un autre utilisateur.
 *  - Jeton CSRF verifie sur la requete POST.
 *  - La valeur recue est validee par setTheme() (liste blanche).
 */
final class PreferenceController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    #[Route('/preferences/theme', name: 'app_pref_theme', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function saveTheme(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('theme_pref', (string) $request->request->get('_token'))) {
            return new JsonResponse(['ok' => false, 'message' => 'Jeton de securite invalide.'], 419);
        }

        /** @var User $user */
        $user = $this->getUser();

        $theme = (string) $request->request->get('theme', 'base');
        $user->setTheme($theme);
        $this->em->flush();

        return new JsonResponse(['ok' => true, 'theme' => $user->getTheme()]);
    }
}
