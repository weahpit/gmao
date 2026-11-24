<?php
namespace App\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** Consultation AQ sans autorisation implicite d'enregistrer une entree/sortie. */
final class VisitesVoter extends Voter
{
    public const CONSULTER = 'VISITES_CONSULTER';
    public const GERER = 'VISITES_GERER';
    public const GESTION = ['ROLE_COURRIER','ROLE_VIGIL','ROLE_ADMIN','ROLE_DG','ROLE_DL','ROLE_TECHNICIAN','ROLE_PDR','ROLE_CHEF_SERVICE'];
    public const QUALITE = ['ROLE_AQ','ROLE_RESP_AQ'];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::CONSULTER, self::GERER], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (!$token->getUser() instanceof \Symfony\Component\Security\Core\User\UserInterface) return false;
        $roles = $attribute === self::GERER ? self::GESTION : array_merge(self::GESTION, self::QUALITE);
        return array_intersect($token->getRoleNames(), $roles) !== [];
    }
}
