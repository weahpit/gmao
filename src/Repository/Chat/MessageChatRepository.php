<?php

namespace App\Repository\Chat;

use App\Entity\Chat\ListeDiffusion;
use App\Entity\Chat\MessageChat;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MessageChat>
 */
class MessageChatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageChat::class);
    }

    /**
     * Derniers messages du canal général, du plus ancien au plus récent.
     *
     * @return MessageChat[]
     */
    public function filGeneral(int $limite = 80): array
    {
        $messages = $this->createQueryBuilder('m')
            ->leftJoin('m.expediteur', 'e')->addSelect('e')
            ->andWhere('m.destinataire IS NULL')
            ->andWhere('m.listeDiffusion IS NULL')
            ->orderBy('m.created_at', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();

        return array_reverse($messages);
    }

    /**
     * Derniers messages d'une conversation privée (dans les deux sens).
     *
     * @return MessageChat[]
     */
    public function filPrive(User $moi, User $autre, int $limite = 80): array
    {
        $messages = $this->createQueryBuilder('m')
            ->leftJoin('m.expediteur', 'e')->addSelect('e')
            ->andWhere('(m.expediteur = :moi AND m.destinataire = :autre) OR (m.expediteur = :autre AND m.destinataire = :moi)')
            ->setParameter('moi', $moi)
            ->setParameter('autre', $autre)
            ->orderBy('m.created_at', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();

        return array_reverse($messages);
    }

    /**
     * Messages arrivés après une date (rafraîchissement automatique).
     *
     * @return MessageChat[]
     */
    public function nouveaux(?User $autre, User $moi, \DateTimeImmutable $apres, ?ListeDiffusion $liste = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.expediteur', 'e')->addSelect('e')
            ->andWhere('m.created_at >= :apres')
            ->setParameter('apres', $apres)
            ->orderBy('m.created_at', 'ASC')
            ->setMaxResults(100);

        if (null !== $liste) {
            $qb->andWhere('m.listeDiffusion = :liste')
                ->setParameter('liste', $liste);
        } elseif ($autre === null) {
            $qb->andWhere('m.destinataire IS NULL')
               ->andWhere('m.listeDiffusion IS NULL');
        } else {
            $qb->andWhere('(m.expediteur = :moi AND m.destinataire = :autre) OR (m.expediteur = :autre AND m.destinataire = :moi)')
               ->setParameter('moi', $moi)
               ->setParameter('autre', $autre);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Derniers messages où l'utilisateur est expéditeur ou destinataire
     * (sert à construire la liste des conversations).
     *
     * @return MessageChat[]
     */
    public function derniersEchanges(User $moi, int $limite = 400): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('(m.expediteur = :moi OR m.destinataire = :moi)')
            ->andWhere('m.listeDiffusion IS NULL')
            ->setParameter('moi', $moi)
            ->orderBy('m.created_at', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    /**
     * Nombre de messages privés non lus, par expéditeur.
     *
     * @return array<string, int>  [uuid de l'expéditeur => nombre]
     */
    public function nonLusParExpediteur(User $moi): array
    {
        $lignes = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.expediteur) AS exp', 'COUNT(m.id) AS n')
            ->andWhere('m.destinataire = :moi')
            ->andWhere('m.lu = false')
            ->setParameter('moi', $moi)
            ->groupBy('m.expediteur')
            ->getQuery()
            ->getArrayResult();

        $resultat = [];
        foreach ($lignes as $ligne) {
            $resultat[(string) $ligne['exp']] = (int) $ligne['n'];
        }

        return $resultat;
    }

    /** Total des messages privés non lus. */
    public function totalNonLusPrives(User $moi): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.destinataire = :moi')
            ->andWhere('m.lu = false')
            ->setParameter('moi', $moi)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Nombre de messages du canal général arrivés après une date. */
    public function nonLusGeneral(\DateTimeImmutable $apres, User $moi): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.destinataire IS NULL')
            ->andWhere('m.listeDiffusion IS NULL')
            ->andWhere('m.created_at > :apres')
            ->andWhere('m.expediteur != :moi')
            ->setParameter('apres', $apres)
            ->setParameter('moi', $moi)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Derniers messages d'une liste de diffusion (canal de groupe).
     *
     * @return MessageChat[]
     */
    public function filListe(ListeDiffusion $liste, int $limite = 80): array
    {
        $messages = $this->createQueryBuilder('m')
            ->leftJoin('m.expediteur', 'e')->addSelect('e')
            ->andWhere('m.listeDiffusion = :liste')
            ->setParameter('liste', $liste)
            ->orderBy('m.created_at', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();

        return array_reverse($messages);
    }

    /** Messages d'une liste arrives apres une date (rafraichissement). */
    public function nonLusListe(ListeDiffusion $liste, User $moi, \DateTimeImmutable $apres): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.listeDiffusion = :liste')
            ->andWhere('m.created_at > :apres')
            ->andWhere('m.expediteur != :moi')
            ->setParameter('liste', $liste)
            ->setParameter('apres', $apres)
            ->setParameter('moi', $moi)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Nombre de messages d'une liste (affiche dans l'ecran d'administration). */
    public function compterListe(ListeDiffusion $liste): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.listeDiffusion = :liste')
            ->setParameter('liste', $liste)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Dernier message d'une liste (apercu). */
    public function dernierDeListe(ListeDiffusion $liste): ?MessageChat
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.expediteur', 'e')->addSelect('e')
            ->andWhere('m.listeDiffusion = :liste')
            ->setParameter('liste', $liste)
            ->orderBy('m.created_at', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Marque comme lus tous les messages privés reçus d'un utilisateur.
     *
     * La date d'ouverture est enregistrée (`lu_at`) : c'est elle que
     * l'expéditeur voit dans l'accusé de lecture (« Lu à 14:32 »).
     */
    public function marquerLusDe(User $moi, User $autre): void
    {
        $maintenant = new \DateTimeImmutable();

        // 1) Les messages pas encore ouverts : on les marque lus maintenant.
        $this->createQueryBuilder('m')
            ->update()
            ->set('m.lu', 'true')
            ->set('m.lu_at', ':maintenant')
            ->andWhere('m.destinataire = :moi')
            ->andWhere('m.expediteur = :autre')
            ->andWhere('m.lu = false')
            ->setParameter('moi', $moi)
            ->setParameter('autre', $autre)
            ->setParameter('maintenant', $maintenant)
            ->getQuery()
            ->execute();

        // 2) Les messages remis mais jamais datés recoivent leur date d'arrivée.
        $this->createQueryBuilder('m')
            ->update()
            ->set('m.remis_at', ':maintenant')
            ->andWhere('m.destinataire = :moi')
            ->andWhere('m.expediteur = :autre')
            ->andWhere('m.remis_at IS NULL')
            ->setParameter('moi', $moi)
            ->setParameter('autre', $autre)
            ->setParameter('maintenant', $maintenant)
            ->getQuery()
            ->execute();
    }

    /**
     * Messages envoyés par l'utilisateur sur un canal, depuis une date.
     *
     * Sert à l'accusé de lecture : l'expéditeur interroge régulièrement
     * l'état de ses propres messages (envoyé / remis / lu).
     *
     * @return MessageChat[]
     */
    public function miensDepuis(
        User $moi,
        ?User $autre,
        ?ListeDiffusion $liste,
        \DateTimeImmutable $depuis,
        int $limite = 100
    ): array {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.expediteur = :moi')
            ->andWhere('m.created_at >= :depuis')
            ->setParameter('moi', $moi)
            ->setParameter('depuis', $depuis)
            ->orderBy('m.created_at', 'ASC')
            ->setMaxResults($limite);

        if (null !== $liste) {
            $qb->andWhere('m.listeDiffusion = :liste')->setParameter('liste', $liste);
        } elseif ($autre !== null) {
            $qb->andWhere('m.destinataire = :autre')->setParameter('autre', $autre);
        } else {
            $qb->andWhere('m.destinataire IS NULL')->andWhere('m.listeDiffusion IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

}
