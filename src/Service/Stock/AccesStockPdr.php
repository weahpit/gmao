<?php

namespace App\Service\Stock;

use Symfony\Bundle\SecurityBundle\Security;

/**
 * Regles de visibilite du stock PDR (pieces de rechange).
 *
 *  - ROLE_PDR, ROLE_DG, ROLE_DL (et ROLE_ADMIN) : acces complet aux stocks
 *    et aux mouvements PDR, toutes familles confondues.
 *  - ROLE_AQ, ROLE_RESP_AQ : acces limite a la famille
 *    « EQUIPEMENTS DE PROTECTION INDIVIDUELLE » (mouvements, stock final,
 *    tableau de bord).
 *  - Tout autre profil : aucun acces.
 */
final class AccesStockPdr
{
    public const FAMILLE_AQ = 'EQUIPEMENTS DE PROTECTION INDIVIDUELLE';
    public const COMPLET = 'complet';
    public const RESTREINT = 'restreint';
    public const AUCUN = 'aucun';

    public function __construct(private readonly Security $security)
    {
    }

    public function profil(): string
    {
        if ($this->security->isGranted('ROLE_PDR')
            || $this->security->isGranted('ROLE_DG')
            || $this->security->isGranted('ROLE_DL')
            || $this->security->isGranted('ROLE_ADMIN')) {
            return self::COMPLET;
        }

        if ($this->security->isGranted('ROLE_RESP_AQ') || $this->security->isGranted('ROLE_AQ')) {
            return self::RESTREINT;
        }

        return self::AUCUN;
    }

    public function peutVoir(): bool
    {
        return self::AUCUN !== $this->profil();
    }

    public function voitTout(): bool
    {
        return self::COMPLET === $this->profil();
    }

    public function familleImposee(): ?string
    {
        return self::RESTREINT === $this->profil() ? self::FAMILLE_AQ : null;
    }

    public function familleAutorisee(?string $famille): bool
    {
        $imposee = $this->familleImposee();
        if (null === $imposee) {
            return true;
        }

        return self::normaliser((string) $famille) === self::normaliser($imposee);
    }

    public static function normaliser(string $valeur): string
    {
        $valeur = mb_strtoupper($valeur);
        $valeur = strtr($valeur, ['É' => 'E', 'È' => 'E', 'Ê' => 'E', 'À' => 'A', 'Ç' => 'C', 'Ô' => 'O', 'Î' => 'I', 'Û' => 'U']);

        return trim((string) preg_replace('/[^A-Z0-9]+/', ' ', $valeur));
    }
}
