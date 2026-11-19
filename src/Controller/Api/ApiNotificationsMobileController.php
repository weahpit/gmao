<?php

namespace App\Controller\Api;

use App\Entity\Chat\DeviceToken;
use App\Entity\User;
use App\Repository\Chat\DeviceTokenRepository;
use App\Service\Chat\NotificationPush;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Enregistrement du telephone pour les notifications push.
 *
 * L'application mobile envoie ici le jeton Firebase qu'elle a recu au
 * demarrage. A la deconnexion, elle demande sa suppression.
 */
#[Route('/api/mobile/notifications')]
#[IsGranted('ROLE_USER')]
final class ApiNotificationsMobileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DeviceTokenRepository $jetons,
        private readonly NotificationPush $push,
    ) {
    }

    /** Enregistre (ou rafraichit) le jeton push de cet appareil. */
    #[Route('/token', name: 'api_mobile_notifications_token', methods: ['POST'])]
    public function enregistrer(Request $request): JsonResponse
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof User) {
            return new JsonResponse(['ok' => false, 'erreur' => 'Session invalide.'], 401);
        }

        $token = trim((string) $request->request->get('token', ''));
        $plateforme = (string) $request->request->get('plateforme', DeviceToken::PLATEFORME_ANDROID);

        if ($token === '' || mb_strlen($token) > 255) {
            return new JsonResponse(['ok' => false, 'erreur' => 'Jeton de notification invalide.'], 422);
        }

        $appareil = $this->jetons->parToken($token);
        if ($appareil === null) {
            $appareil = (new DeviceToken())->setToken($token);
            $this->em->persist($appareil);
        }

        $appareil->setUser($utilisateur)->setPlateforme($plateforme)->toucher();
        $this->em->flush();

        return new JsonResponse([
            'ok'       => true,
            'push'     => $this->push->estConfigure(),
            'message'  => $this->push->estConfigure()
                ? 'Appareil enregistre pour les notifications.'
                : 'Appareil enregistre, mais les notifications ne sont pas encore configurees sur le serveur.',
        ]);
    }

    /** Supprime le jeton (deconnexion, reinstallation). */
    #[Route('/token/supprimer', name: 'api_mobile_notifications_token_supprimer', methods: ['POST'])]
    public function supprimer(Request $request): JsonResponse
    {
        $token = trim((string) $request->request->get('token', ''));
        if ($token === '') {
            return new JsonResponse(['ok' => true]);
        }

        $appareil = $this->jetons->parToken($token);
        if ($appareil instanceof DeviceToken) {
            $this->em->remove($appareil);
            $this->em->flush();
        }

        return new JsonResponse(['ok' => true]);
    }
}
