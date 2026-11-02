<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->isBloque() === true) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte a été bloqué par un administrateur.'
            );
        }

        if ($user->isActive() === false) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte est désactivé.'
            );
        }

        if ($user instanceof User && $user->isBloque()) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte a été bloqué par un administrateur.'
            );
        }
    }

    public function checkPostAuth(
        UserInterface $user,
        ?TokenInterface $token = null
    ): void {
        // Rien à faire
    }
}
