<?php

namespace App\Security;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

/**
 * Exception levee lorsque la page de connexion est verrouillee a la suite
 * de trop nombreuses tentatives echouees.
 *
 * Elle est distincte des echecs d'identifiants classiques : le compteur de
 * tentatives NE doit PAS etre incremente quand c'est ce verrouillage qui
 * bloque la connexion (sinon le verrou s'auto-entretiendrait indefiniment).
 */
class AccountLockedException extends CustomUserMessageAuthenticationException
{
}
