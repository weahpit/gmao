<?php

namespace App\Repository\Reunion;

use App\Entity\Reunion\ParticipantReunion;
use App\Entity\Reunion\Reunion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ParticipantReunion>
 */
class ParticipantReunionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ParticipantReunion::class);
    }

    /** @return ParticipantReunion[] */
    public function findParReunion(Reunion $reunion): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.reunion = :reunion')
            ->setParameter('reunion', $reunion)
            ->orderBy('p.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Empeche un double enregistrement dans la meme reunion : meme nom
     * (insensible a la casse) et meme telephone, ou a defaut de telephone,
     * meme adresse email.
     *
     * ATTENTION : aucun appel a une fonction SQL (REPLACE, TRIM...) dans le
     * DQL. La comparaison des numeros se fait en PHP, car DQL n'accepte pas
     * REPLACE (erreur « Expected known function, got 'REPLACE' »).
     */
    public function existeDeja(Reunion $reunion, string $nom, ?string $telephone, ?string $email = null): bool
    {
        $candidats = $this->createQueryBuilder('p')
            ->andWhere('p.reunion = :reunion')
            ->andWhere('LOWER(p.nom) = :nom')
            ->setParameter('reunion', $reunion)
            ->setParameter('nom', mb_strtolower(trim($nom)))
            ->getQuery()
            ->getResult();

        if ($candidats === []) {
            return false;
        }

        $tel   = $this->normaliserTelephone($telephone);
        $email = $email !== null ? mb_strtolower(trim($email)) : '';

        foreach ($candidats as $candidat) {
            /** @var ParticipantReunion $candidat */
            $telExistant   = $this->normaliserTelephone($candidat->getTelephone());
            $emailExistant = mb_strtolower(trim((string) $candidat->getEmail()));

            if ($tel !== '' && $tel === $telExistant) {
                return true;
            }

            if ($tel === '' && $email !== '' && $email === $emailExistant) {
                return true;
            }
        }

        return false;
    }

    /** « 07 00-00 00 00 » et « +2250700000000 » -> « 0700000000 » (chiffres seuls). */
    private function normaliserTelephone(?string $telephone): string
    {
        $chiffres = preg_replace('/[^0-9]/', '', (string) $telephone);

        // Retire l'indicatif pays eventuel pour comparer les numeros ivoiriens.
        if (strlen($chiffres) > 10 && str_starts_with($chiffres, '225')) {
            $chiffres = substr($chiffres, 3);
        }

        return $chiffres;
    }
}
