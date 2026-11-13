<?php

namespace App\DataFixtures;

use App\Entity\Criticite;
use App\Entity\EtatEquipement;
use App\Entity\NatureEquipement;
use App\Entity\Production\Produit;
use App\Entity\Stock\Rubrique;
use App\Entity\Stock\Unite;
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

        // --- Rubriques de stock (type de stock) ---
        // Liste de reference PARTAGEE web + mobile (table stock_rubrique).
        $rubriquesStock = [
            'Stock déchets Recyclables',
            'Stock déchets non recyclable',
            'Stock échantillothèque',
            'Stock Refonte',
            'Stock échantillons Client',
        ];
        foreach ($rubriquesStock as $i => $libelle) {
            $rubrique = new Rubrique();
            $rubrique->setLibelle($libelle);
            $rubrique->setActive(true);
            $rubrique->setPosition($i);
            $manager->persist($rubrique);
        }

        // --- Unites de mesure du stock (table stock_unite) ---
        // Liste de reference PARTAGEE web + mobile : les formulaires d'entree
        // (saisie) et de sortie lisent leurs valeurs ici. Ajouter une ligne
        // ici (ou dans la table) suffit a proposer une nouvelle unite.
        $unitesStock = [
            ['libelle' => 'Kg',      'symbole' => 'kg', 'defaut' => true],
            ['libelle' => 'Litres',  'symbole' => 'L',  'defaut' => false],
            ['libelle' => 'Sacs',    'symbole' => 'sac', 'defaut' => false],
            ['libelle' => 'Tonnes',  'symbole' => 't',  'defaut' => false],
            ['libelle' => 'Cartons', 'symbole' => 'crt', 'defaut' => false],
            ['libelle' => 'Pieces',  'symbole' => 'pce', 'defaut' => false],
        ];
        foreach ($unitesStock as $i => $u) {
            $unite = new Unite();
            $unite->setLibelle($u['libelle']);
            $unite->setSymbole($u['symbole']);
            $unite->setParDefaut($u['defaut']);
            $unite->setActive(true);
            $unite->setPosition($i);
            $manager->persist($unite);
        }

        // --- Produits (referentiel Production) ---
        // La "nature du produit" cote stock (web + mobile) est lue depuis cette
        // table. On y injecte les natures historiques si elles n'existent pas
        // deja ; completer/retirer ici suffit a faire evoluer la liste.
        $produitsNature = ['Fèves', 'Masse', 'Beurre', 'Tourteau', 'Tout venant'];
        foreach ($produitsNature as $libelle) {
            $produit = new Produit();
            $produit->setLibelle($libelle);
            $manager->persist($produit);
        }

        $manager->flush();
    }
}
