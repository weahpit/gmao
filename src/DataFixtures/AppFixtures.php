<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {

        // --- Admin User ---
        $admin = new User();
        $admin->setPrenoms('Abdoul Aziz');
        $admin->setEmail('aziz.ndia@citrac.ci');
        $admin->setNom('NDIA');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setActive(true);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, '123456789'));
        $admin->setMatricule('0135');
        $admin->setMobile('07 78 64 87 76');
        $manager->persist($admin);

        // --- Technician ---
        $tech = new User();
        $tech->setNom('DIEHI ');
        $tech->setEmail('edouard@citrac.ci');
        $tech->setPrenoms('DIEHI TOKOUEHI EDOUARD');
        $tech->setRoles(['ROLE_TECHNICIAN']);
        $tech->setActive(true);
        $tech->setPassword($this->passwordHasher->hashPassword($tech, 'CITRAC2024!'));
        $tech->setMatricule('0064');
        $tech->setMobile('07 78 65 25 33');
        $manager->persist($tech);

        $manager->flush();
    }
}
