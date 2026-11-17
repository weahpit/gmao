<?php

namespace App\Twig;

use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(
        private RoleHierarchyInterface $roleHierarchy
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_all_roles', [$this, 'getAllRoles']),
        ];
    }

    public function getAllRoles(): array
    {
        // Le service RoleHierarchy stocke les rôles dans une propriété protégée ou via getReachableRoleNames
        // On utilise la réflexion pour extraire proprement la map de configuration
        $reflection = new \ReflectionClass($this->roleHierarchy);
        $property = $reflection->getProperty('map');
        $property->setAccessible(true);
        $map = $property->getValue($this->roleHierarchy);

        $roles = array_keys($map);
        foreach ($map as $subRoles) {
            $roles = array_merge($roles, $subRoles);
        }

        return array_unique($roles);
    }
}
