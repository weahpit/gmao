<?php

declare(strict_types=1);

namespace App\Service\Logistique;

use App\Entity\Logistique\Client;
use App\Entity\Logistique\Dossier;
use App\Entity\Logistique\Navire;
use App\Entity\Logistique\Proprietaire;
use App\Entity\Production\Produit;
use App\Repository\Logistique\DossierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Coeur metier des dossiers d'embarquement.
 *
 * Tout ce qui touche a un dossier (recherche, compteurs, ouverture,
 * cloture/reouverture) est rassemble ici. Les controleurs redeviennent de
 * simples passe-plats : ils lisent la requete, appellent ce service et
 * affichent le resultat. Une seule regle metier par methode, donc plus facile
 * a corriger sans casser les ecrans.
 */
class EmbarquementManager
{
    /** Nombre de dossiers par page dans la liste. */
    public const PAR_PAGE = 25;

    public function __construct(
        private readonly DossierRepository $dossiers,
        private readonly EntityManagerInterface $em,
    ) {
    }

    // -----------------------------------------------------------------
    //  Recherche
    // -----------------------------------------------------------------

    /**
     * Nettoie les filtres recus de l'URL. Les valeurs invalides sont ignorees
     * (plutot que de faire echouer la page) et la valeur retenue est renvoyee
     * pour que le formulaire reste coherent avec le resultat affiche.
     *
     * @param array<string, mixed> $bruts
     *
     * @return array{q:string, statut:string, navire:string, du:string, au:string}
     */
    public function normaliserFiltres(array $bruts): array
    {
        $filtres = [
            'q'      => trim((string) ($bruts['q'] ?? '')),
            'statut' => (string) ($bruts['statut'] ?? ''),
            'navire' => (string) ($bruts['navire'] ?? ''),
            'du'     => (string) ($bruts['du'] ?? ''),
            'au'     => (string) ($bruts['au'] ?? ''),
        ];

        if (!in_array($filtres['statut'], ['', 'encours', 'cloture', 'a_planifier'], true)) {
            $filtres['statut'] = '';
        }

        // Un identifiant de navire invalide ne doit pas casser la liste.
        if ($filtres['navire'] !== '') {
            try {
                Uuid::fromString($filtres['navire']);
            } catch (\Throwable) {
                $filtres['navire'] = '';
            }
        }

        foreach (['du', 'au'] as $cle) {
            if ($filtres[$cle] === '') {
                continue;
            }
            try {
                new \DateTimeImmutable($filtres[$cle] . ' 00:00:00');
            } catch (\Throwable) {
                $filtres[$cle] = '';
            }
        }

        return $filtres;
    }

    /**
     * Page de dossiers correspondant aux filtres.
     *
     * @param array{q:string, statut:string, navire:string, du:string, au:string} $filtres
     *
     * @return array{dossiers: Dossier[], total:int, page:int, nbPages:int, parPage:int}
     */
    public function rechercher(array $filtres, int $page, int $parPage = self::PAR_PAGE): array
    {
        $page    = max(1, $page);
        $parPage = max(5, min(100, $parPage));

        $qb = $this->dossiers->createQueryBuilder('d')
            ->leftJoin('d.client', 'cl')->addSelect('cl')
            ->leftJoin('d.produit', 'p')->addSelect('p')
            ->leftJoin('d.proprietaire', 'pr')->addSelect('pr')
            ->leftJoin('d.code_navire', 'n')->addSelect('n')
            ->leftJoin('d.destination', 'de')->addSelect('de');

        $this->appliquerFiltres($qb, $filtres);

        // Le total est calcule sur la meme requete que la liste, sans les
        // objets hydrates : une seule requete de comptage, pas de collection
        // complete en memoire.
        $total = (int) (clone $qb)
            ->select('COUNT(DISTINCT d.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $dossiers = $qb
            ->orderBy('d.created_at', 'DESC')
            ->setFirstResult(($page - 1) * $parPage)
            ->setMaxResults($parPage)
            ->getQuery()
            ->getResult();

        return [
            'dossiers' => $dossiers,
            'total'    => $total,
            'page'     => $page,
            'nbPages'  => max(1, (int) ceil($total / $parPage)),
            'parPage'  => $parPage,
        ];
    }

    /**
     * @param array{q:string, statut:string, navire:string, du:string, au:string} $filtres
     */
    private function appliquerFiltres($qb, array $filtres): void
    {
        if ($filtres['q'] !== '') {
            $qb->andWhere('d.numero_ot LIKE :q OR cl.nomClient LIKE :q OR n.nom LIKE :q'
                    . ' OR de.denomination LIKE :q OR pr.denomination LIKE :q')
               ->setParameter('q', '%' . $filtres['q'] . '%');
        }

        if ($filtres['statut'] === 'encours') {
            $qb->andWhere('d.cloture = false OR d.cloture IS NULL');
        } elseif ($filtres['statut'] === 'cloture') {
            $qb->andWhere('d.cloture = true');
        } elseif ($filtres['statut'] === 'a_planifier') {
            $qb->andWhere('d.date_embarquement IS NULL');
        }

        if ($filtres['navire'] !== '') {
            $qb->andWhere('d.code_navire = :navire')
               ->setParameter('navire', Uuid::fromString($filtres['navire']));
        }

        if ($filtres['du'] !== '') {
            $qb->andWhere('d.created_at >= :du')
               ->setParameter('du', new \DateTimeImmutable($filtres['du'] . ' 00:00:00'));
        }

        if ($filtres['au'] !== '') {
            $qb->andWhere('d.created_at <= :au')
               ->setParameter('au', new \DateTimeImmutable($filtres['au'] . ' 23:59:59'));
        }
    }

    /**
     * Compteurs du bandeau, en une seule requete.
     *
     * @return array{total:int, en_cours:int, clotures:int, a_planifier:int, ce_mois:int}
     */
    public function statistiques(): array
    {
        return $this->dossiers->compterParStatut(
            new \DateTimeImmutable('first day of this month 00:00:00'),
            new \DateTimeImmutable('last day of this month 23:59:59')
        );
    }

    // -----------------------------------------------------------------
    //  Ouverture d'un dossier
    // -----------------------------------------------------------------

    /**
     * Cree un dossier d'embarquement a partir des donnees d'un formulaire.
     *
     * Le retour est toujours un tableau avec une cle `code` :
     *  - SUCCESS        : dossier cree (avec `dossier` et `id_dossier`) ;
     *  - EXISTS         : un dossier porte deja ce numero ;
     *  - MISSING_VALUES : au moins une reference manque ou est introuvable ;
     *  - BAD_DATA       : donnees illisibles.
     *
     * @param array<string, mixed> $donnees
     *
     * @return array{code:string, msg:string, id_dossier?:mixed, dossier?:Dossier}
     */
    public function ouvrir(array $donnees, ?string $auteur = null): array
    {
        if ($donnees === []) {
            return ['code' => 'BAD_DATA', 'msg' => "Données illisibles : aucun dossier d'embarquement n'a été créé."];
        }

        $numeroOt = strtoupper(trim((string) ($donnees['numero_ot'] ?? '')));
        if ($numeroOt === '') {
            return ['code' => 'MISSING_VALUES', 'msg' => "Le numéro d'OT est obligatoire."];
        }

        $references = [
            'client'       => ['cle' => 'code_clt',     'libelle' => 'le client',       'classe' => Client::class],
            'proprietaire' => ['cle' => 'code_proprio', 'libelle' => 'le propriétaire', 'classe' => Proprietaire::class],
            'produit'      => ['cle' => 'code_pdt',     'libelle' => 'le produit',      'classe' => Produit::class],
        ];

        $manquants = [];
        $entites   = [];
        foreach ($references as $nom => $ref) {
            $valeur = $donnees[$ref['cle']] ?? null;
            if ($valeur === null || $valeur === '' || (int) $valeur <= 0) {
                $manquants[] = $ref['libelle'];
                continue;
            }
            $entite = $this->em->getRepository($ref['classe'])->find((int) $valeur);
            if (!$entite) {
                $manquants[] = $ref['libelle'] . ' (identifiant ' . (int) $valeur . ' introuvable)';
                continue;
            }
            $entites[$nom] = $entite;
        }

        if ($manquants !== []) {
            return [
                'code' => 'MISSING_VALUES',
                'msg'  => 'Merci de renseigner ' . implode(', ', $manquants) . '.',
            ];
        }

        $existant = $this->dossiers->findOneBy(['numero_ot' => $numeroOt]);
        if ($existant instanceof Dossier) {
            $date = $existant->getCreatedAt();

            return [
                'code'       => 'EXISTS',
                'msg'        => 'Le dossier ' . $numeroOt . ' existe déjà'
                    . ($date ? ' (créé le ' . $date->format('d/m/Y') . ')' : '') . '.',
                'id_dossier' => $existant->getId(),
                'dossier'    => $existant,
            ];
        }

        $dossier = new Dossier();
        $dossier->setNumeroOt($numeroOt);
        $dossier->setClient($entites['client']);
        $dossier->setProprietaire($entites['proprietaire']);
        $dossier->setProduit($entites['produit']);
        $dossier->setTonnage(0);
        $dossier->setNbTc(0);
        $dossier->setCreatedAt(new \DateTimeImmutable());
        $dossier->setCreatedBy($auteur !== null && $auteur !== '' ? $auteur : 'Admin');

        $this->em->persist($dossier);
        $this->em->flush();

        return [
            'code'       => 'SUCCESS',
            'msg'        => 'Dossier ' . $numeroOt . ' créé.',
            'id_dossier' => $dossier->getId(),
            'dossier'    => $dossier,
        ];
    }

    // -----------------------------------------------------------------
    //  Cloture / reouverture
    // -----------------------------------------------------------------

    /**
     * Inverse l'etat du dossier (cloture <-> en cours) et enregistre l'auteur.
     *
     * @return bool nouvel etat : true = cloture
     */
    public function basculerCloture(Dossier $dossier, ?string $auteur = null): bool
    {
        $cloture = !$dossier->isCloture();
        $dossier->setCloture($cloture);
        $dossier->setUpdatedAt(new \DateTimeImmutable());

        if ($auteur !== null && trim($auteur) !== '') {
            $dossier->setUpdatedBy(trim($auteur));
        }

        $this->em->flush();

        return $cloture;
    }

    /** Liste des navires pour le filtre de la page. */
    public function navires(): array
    {
        return $this->em->getRepository(Navire::class)->findBy([], ['nom' => 'ASC']);
    }
}
