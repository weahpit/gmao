<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/Authentification', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()){return  $this->redirectToRoute("app_dashboard");}

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function apiLogin(#[CurrentUser] ?User $user): JsonResponse
    {
        // json_login authentifie AVANT le contrôleur ; en cas d'échec il renvoie
        // automatiquement un 401 JSON. Ici on n'arrive que si succès.
        if (!$user) {
            return $this->json(['code' => 'error', 'msg' => 'Identifiants invalides'], 401);
        }
        return $this->json([
            'code' => 'success',
            'msg'  => 'Connexion réussie',
            'data' => ['id' => (string) $user->getId(), 'email' => $user->getEmail()],
        ]);
    }
}
