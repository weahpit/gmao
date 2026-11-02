<?php

namespace App\DataFixtures;

use App\Entity\Criticite;
use App\Entity\EtatEquipement;
use App\Entity\NatureEquipement;
use App\Entity\TypeZoneExploitation;
use App\Entity\User;
use App\Entity\ZoneExploitation;
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

        // Criticité
        $chriticite = new Criticite();
        $chriticite->setLibelle("A");
        $manager->persist($chriticite);

        $chriticite = new Criticite();
        $chriticite->setLibelle("B");
        $manager->persist($chriticite);

        $chriticite = new Criticite();
        $chriticite->setLibelle("C");
        $manager->persist($chriticite);


        //Etat Equipement
        $etat = new EtatEquipement();
        $etat->setLibelle("EN SERVICE");
        $manager->persist($etat);

        $etat = new EtatEquipement();
        $etat->setLibelle("EN PANNE");
        $manager->persist($etat);

        $etat = new EtatEquipement();
        $etat->setLibelle("EN ARRET");
        $manager->persist($etat);

        $etat = new EtatEquipement();
        $etat->setLibelle("OBSOLETE");
        $manager->persist($etat);


        // Nature Equipement
        $nature = new NatureEquipement();
        $nature->setLibelle("UNIQUE [Avec SN]");
        $manager->persist($nature);

        $nature = new NatureEquipement();
        $nature->setLibelle("QUANTITE");
        $manager->persist($nature);

        // Zone Exploitation
        $ze = new TypeZoneExploitation();
        $ze->setLibelle("LIGNE");
        $manager->persist($ze);

        $ze = new TypeZoneExploitation();
        $ze->setLibelle("ESPACE / ZONE DE TRAVAIL");
        $manager->persist($ze);

        $manager->flush();
    }
}
