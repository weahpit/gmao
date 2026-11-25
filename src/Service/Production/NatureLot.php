<?php

namespace App\Service\Production;

use App\Entity\Production\Lot;
use App\Entity\Production\Produit;

/**
 * Nature d'un lot et qualites autorisees.
 *
 *  - FEVES          : la qualite se saisit en Grade 1, Grade 2 ou Sous-grade.
 *                     Un lot de feves n'est jamais scinde.
 *  - PRODUITS FINIS : masse, beurre, tourteau, poudre ; qualite C (conforme)
 *                     ou NC (non conforme) ; seuls ces lots se scindent.
 *
 * La nature est deduite du libelle du produit (comme le magasin automatique),
 * ce qui evite d'ajouter une colonne a la base.
 */
final class NatureLot
{
    public const FEVE = 'FEVE';
    public const FINI = 'FINI';
    public const INCONNU = 'INCONNU';

    /** Grades des feves : cle stockee en base => libelle affiche. */
    public const GRADES = [
        'GRADE1' => 'Grade 1',
        'GRADE2' => 'Grade 2',
        'SOUS-GRADE' => 'Sous-grade',
    ];

    /** Qualites des produits finis : cle stockee en base => libelle affiche. */
    public const QUALITES_FINIES = [
        'C' => 'Conforme (C)',
        'NC' => 'Non conforme (NC)',
    ];

    public function natureProduit(?Produit $produit): string
    {
        if (!$produit instanceof Produit) {
            return self::INCONNU;
        }

        $nom = $this->normaliserTexte((string) $produit->getLibelle());
        if ('' === $nom) {
            return self::INCONNU;
        }

        if (str_contains($nom, 'FEVE')) {
            return self::FEVE;
        }

        foreach (['BEURRE', 'MASSE', 'TOURTEAU', 'POUDRE'] as $motif) {
            if (str_contains($nom, $motif)) {
                return self::FINI;
            }
        }

        return self::INCONNU;
    }

    public function natureLot(Lot $lot): string
    {
        return $this->natureProduit($lot->getCodeProduit());
    }

    public function estFeve(Lot $lot): bool
    {
        return self::FEVE === $this->natureLot($lot);
    }

    public function estFini(Lot $lot): bool
    {
        return self::FINI === $this->natureLot($lot);
    }

    /**
     * Qualites proposees pour un produit (ordre d'affichage).
     *
     * @return array<string, string>
     */
    public function qualitesPour(?Produit $produit): array
    {
        return match ($this->natureProduit($produit)) {
            self::FEVE => self::GRADES,
            self::FINI => self::QUALITES_FINIES,
            default => self::GRADES + self::QUALITES_FINIES,
        };
    }

    /**
     * Convertit une saisie libre en valeur stockee, ou null si elle n'a pas de
     * sens pour la nature du produit.
     */
    public function normaliser(string $saisie, ?Produit $produit = null): ?string
    {
        $nature = $this->natureProduit($produit);
        $cle = $this->normaliserTexte($saisie);

        if ('' === $cle) {
            $cle = self::FEVE === $nature ? 'GRADE1' : 'C';
        }

        $valeur = match (true) {
            in_array($cle, ['C', 'CONFORME', 'CONF'], true) => 'C',
            in_array($cle, ['NC', 'NONCONFORME', 'NONCONF', 'NONCONFORME1'], true) => 'NC',
            in_array($cle, ['G1', 'GRADE1', '1', 'PREMIERGRADE', 'GRADEUN'], true) => 'GRADE1',
            in_array($cle, ['G2', 'GRADE2', '2', 'SECONDGRADE', 'GRADEDEUX'], true) => 'GRADE2',
            in_array($cle, ['SG', 'SOUSGRADE', 'SSGRADE', 'GRADE3', '3', 'TROISIEMEGRADE'], true) => 'SOUS-GRADE',
            default => null,
        };

        if (null === $valeur) {
            return null;
        }

        // Anciennes valeurs C / NC restees sur des lots de feves : on les
        // convertit vers le grade correspondant plutot que de bloquer la fiche.
        if (self::FEVE === $nature) {
            if ('C' === $valeur) {
                return 'GRADE1';
            }
            if ('NC' === $valeur) {
                return 'SOUS-GRADE';
            }
        }

        return match ($nature) {
            self::FEVE => isset(self::GRADES[$valeur]) ? $valeur : null,
            self::FINI => isset(self::QUALITES_FINIES[$valeur]) ? $valeur : null,
            default => $valeur,
        };
    }

    /**
     * Libelle lisible de la qualite d'un lot (gere les anciennes valeurs C/NC
     * restees sur des lots de feves).
     */
    public function libelleLot(Lot $lot): string
    {
        $qualite = mb_strtoupper(trim((string) $lot->getQualite()));

        if ('' === $qualite) {
            return '—';
        }

        if ($this->estFeve($lot)) {
            if (isset(self::GRADES[$qualite])) {
                return self::GRADES[$qualite];
            }

            return match ($qualite) {
                'C' => 'Grade 1 (à confirmer)',
                'NC' => 'Sous-grade (à confirmer)',
                default => $qualite,
            };
        }

        return self::QUALITES_FINIES[$qualite] ?? $qualite;
    }

    public function libelle(?string $qualite): string
    {
        $qualite = mb_strtoupper(trim((string) $qualite));

        return self::GRADES[$qualite] ?? self::QUALITES_FINIES[$qualite] ?? ($qualite ?: '—');
    }

    private function normaliserTexte(string $texte): string
    {
        $texte = strtr(mb_strtoupper(trim($texte)), [
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C', 'Œ' => 'OE', 'Æ' => 'AE',
        ]);

        return (string) preg_replace('/[^A-Z0-9]+/', '', $texte);
    }
}
