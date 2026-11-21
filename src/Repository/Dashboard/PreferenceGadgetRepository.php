<?php

namespace App\Repository\Dashboard;

use App\Entity\Dashboard\PreferenceGadget;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PreferenceGadget>
 */
class PreferenceGadgetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PreferenceGadget::class);
    }

    /**
     * Preferences enregistrees pour un utilisateur et un role, indexees par code de gadget.
     *
     * @return array<string, PreferenceGadget>
     */
    public function pourUtilisateur(User $utilisateur, string $role): array
    {
        $lignes = $this->createQueryBuilder('p')
            ->andWhere('p.utilisateur = :utilisateur')
            ->andWhere('p.role = :role')
            ->setParameter('utilisateur', $utilisateur)
            ->setParameter('role', $role)
            ->orderBy('p.ordre', 'ASC')
            ->getQuery()
            ->getResult();

        $preferences = [];
        foreach ($lignes as $ligne) {
            $preferences[$ligne->getCode()] = $ligne;
        }

        return $preferences;
    }

    public function pourGadget(User $utilisateur, string $role, string $code): ?PreferenceGadget
    {
        return $this->findOneBy([
            'utilisateur' => $utilisateur,
            'role' => $role,
            'code' => $code,
        ]);
    }

    /**
     * Enregistre un ordre complet de gadgets : les lignes manquantes sont creees
     * avec les valeurs par defaut (affiche et actif).
     *
     * @param list<string> $codesDansLOrdre
     */
    public function enregistrerOrdre(User $utilisateur, string $role, array $codesDansLOrdre): void
    {
        $existantes = $this->pourUtilisateur($utilisateur, $role);
        $position = 1;

        foreach ($codesDansLOrdre as $code) {
            $preference = $existantes[$code] ?? null;
            if (!$preference) {
                $preference = (new PreferenceGadget())
                    ->setUtilisateur($utilisateur)
                    ->setRole($role)
                    ->setCode($code);
                $this->getEntityManager()->persist($preference);
            }
            $preference->setOrdre($position++);
        }

        $this->getEntityManager()->flush();
    }

    public function supprimerPourRole(User $utilisateur, string $role): void
    {
        $lignes = $this->createQueryBuilder('p')
            ->andWhere('p.utilisateur = :utilisateur')
            ->andWhere('p.role = :role')
            ->setParameter('utilisateur', $utilisateur)
            ->setParameter('role', $role)
            ->getQuery()
            ->getResult();

        foreach ($lignes as $ligne) {
            $this->getEntityManager()->remove($ligne);
        }

        $this->getEntityManager()->flush();
    }
}
