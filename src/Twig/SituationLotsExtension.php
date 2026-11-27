<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Production\Lot;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** Affichage uniquement : le PN théorique historique n'est jamais modifié. */
final class SituationLotsExtension extends AbstractExtension
{
    /** @var array<string, list<string>> Cache des OT pour la requête HTTP courante. */
    private array $ots = [];

    public function __construct(private readonly EntityManagerInterface $em) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('situation_lots', [$this, 'situations']),
            new TwigFunction('situation_lot', [$this, 'situation']),
        ];
    }

    /** Accepte des lots ou les lignes ['lot' => Lot] des écrans de traçabilité. */
    public function situations(?iterable $lots): array
    {
        $liste = [];
        foreach ($lots ?? [] as $entree) {
            if ($entree instanceof Lot) {
                $lot = $entree;
            } elseif (is_array($entree)) {
                $lot = $entree['lot'] ?? null;
            } elseif (is_object($entree) && method_exists($entree, 'getLot')) {
                $lot = $entree->getLot(); // lignes de tracabilite : lien camion/lot ou produit fini
            } else {
                $lot = null;
            }
            if ($lot instanceof Lot && $lot->getId() !== null) {
                $liste[(string) $lot->getId()] = $lot;
            }
        }
        $ids = array_values(array_filter(array_keys($liste), fn (string $id): bool => !array_key_exists($id, $this->ots)));
        if ($ids !== []) {
            // Une seule requête pour tous les lots affichés : aucune requête par ligne.
            $rows = $this->em->createQueryBuilder()
                ->select('l.id AS lotId', 'd.numero_ot AS numero')
                ->from(Lot::class, 'l')->leftJoin('l.code_dossier', 'd')
                ->where('l.id IN (:ids)')
                ->setParameter('ids', array_map(static fn (string $id) => Uuid::fromString($id), $ids))
                ->orderBy('d.numero_ot', 'ASC')->getQuery()->getArrayResult();
            foreach ($ids as $id) $this->ots[$id] = [];
            foreach ($rows as $row) {
                $id = (string) $row['lotId'];
                $numero = trim((string) ($row['numero'] ?? ''));
                if ($numero !== '' && isset($this->ots[$id]) && !in_array($numero, $this->ots[$id], true)) $this->ots[$id][] = $numero;
            }
        }
        $out = [];
        foreach ($liste as $id => $lot) {
            $out[$id] = [
                'restant' => $lot->getPoidsNetDispo() ?? $lot->getPnTh(),
                'theorique' => $lot->getPnTh(),
                'sacs' => $lot->getSacsRestants() ?? $lot->getNbSacs(),
                'ots' => $this->ots[$id] ?? [],
            ];
        }
        return $out;
    }

    public function situation(?Lot $lot): array
    {
        if (!$lot || $lot->getId() === null) return ['restant' => null, 'theorique' => null, 'sacs' => null, 'ots' => []];
        return $this->situations([$lot])[(string) $lot->getId()];
    }
}
