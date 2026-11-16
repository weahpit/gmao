<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rend le scan des bons de sortie ROBUSTE :
 *
 *   1. colonne « token » sur bon_sortie (jeton encode dans le QR) ;
 *   2. colonnes du suivi a la guerite : cloture / refus / poste de controle,
 *      sur bon_sortie ET sur sortie_stock ;
 *   3. index sur les colonnes de recherche (token, reference).
 *
 * POURQUOI : la page /scan lit un code (jeton ou reference) et interroge
 * `token` puis `reference` dans les deux tables. Si une de ces colonnes
 * n'existe pas encore en base (entites livrees sans migration), la requete
 * leve une erreur SQL et la page affiche « Bon introuvable » alors que le
 * bon est bien enregistre.
 *
 * La migration est IDEMPOTENTE : elle ne fait rien sur une base deja a jour
 * (schema:update, export SQL importe, etc.).
 */
final class Version20261116120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Colonnes token/cloture/refus des bons de sortie + index de recherche (scan QR robuste).';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $tables = array_map('strtolower', $sm->listTableNames());

        // ------------------------------------------------------------------
        //  1. bon_sortie : jeton du QR + suivi guerite
        // ------------------------------------------------------------------
        if (in_array('bon_sortie', $tables, true)) {
            $this->ajouterColonne('bon_sortie', 'token', 'VARCHAR(64) DEFAULT NULL');
            $this->ajouterColonne('bon_sortie', 'cloture_at', 'DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
            $this->ajouterColonne('bon_sortie', 'cloture_par', 'VARCHAR(255) DEFAULT NULL');
            $this->ajouterColonne('bon_sortie', 'poste_controle', 'VARCHAR(120) DEFAULT NULL');
            $this->ajouterColonne('bon_sortie', 'refus_at', 'DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
            $this->ajouterColonne('bon_sortie', 'refus_par', 'VARCHAR(255) DEFAULT NULL');
            $this->ajouterColonne('bon_sortie', 'refus_motif', 'VARCHAR(255) DEFAULT NULL');
            $this->ajouterColonne('bon_sortie', 'observation_signature', 'VARCHAR(255) DEFAULT NULL');
            $this->ajouterIndex('bon_sortie', 'idx_bon_sortie_token', ['token']);
            $this->ajouterIndex('bon_sortie', 'idx_bon_sortie_reference', ['reference']);
        }

        // ------------------------------------------------------------------
        //  2. sortie_stock : le bon est porte par la sortie elle-meme
        // ------------------------------------------------------------------
        if (in_array('sortie_stock', $tables, true)) {
            $this->ajouterColonne('sortie_stock', 'client_uuid', 'VARCHAR(64) DEFAULT NULL');
            $this->ajouterColonne('sortie_stock', 'bon_reference', 'VARCHAR(50) DEFAULT NULL');
            $this->ajouterColonne('sortie_stock', 'bon_token', 'VARCHAR(64) DEFAULT NULL');
            $this->ajouterColonne('sortie_stock', 'bon_statut', 'VARCHAR(20) DEFAULT NULL');
            $this->ajouterColonne('sortie_stock', 'signe_at', 'DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
            $this->ajouterColonne('sortie_stock', 'signe_par', 'VARCHAR(255) DEFAULT NULL');
            $this->ajouterColonne('sortie_stock', 'observation_signature', 'VARCHAR(255) DEFAULT NULL');
            $this->ajouterColonne('sortie_stock', 'cloture_at', 'DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
            $this->ajouterColonne('sortie_stock', 'cloture_par', 'VARCHAR(255) DEFAULT NULL');
            $this->ajouterColonne('sortie_stock', 'poste_controle', 'VARCHAR(120) DEFAULT NULL');
            $this->ajouterColonne('sortie_stock', 'refus_at', 'DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
            $this->ajouterColonne('sortie_stock', 'refus_par', 'VARCHAR(255) DEFAULT NULL');
            $this->ajouterColonne('sortie_stock', 'refus_motif', 'VARCHAR(255) DEFAULT NULL');
            $this->ajouterIndex('sortie_stock', 'idx_sortie_stock_bon_token', ['bon_token']);
            $this->ajouterIndex('sortie_stock', 'idx_sortie_stock_bon_reference', ['bon_reference']);
        }
    }

    public function down(Schema $schema): void
    {
        // Aucune suppression : la migration ne fait qu'ajouter des colonnes
        // utilisees par le scan. Un retour arriere casserait la page /scan.
    }

    /** Ajoute une colonne si elle n'existe pas deja (idempotent). */
    private function ajouterColonne(string $table, string $colonne, string $definition): void
    {
        if ($this->colonneExiste($table, $colonne)) {
            return;
        }

        $this->addSql(sprintf('ALTER TABLE %s ADD %s %s', $table, $colonne, $definition));
    }

    /** Ajoute un index si une colonne de reference n'en a pas encore (idempotent). */
    private function ajouterIndex(string $table, string $nom, array $colonnes): void
    {
        foreach ($colonnes as $colonne) {
            if (!$this->colonneExiste($table, $colonne)) {
                return;
            }
        }

        try {
            $index = $this->connection->createSchemaManager()->listTableIndexes($table);
            foreach ($index as $idx) {
                if (strtolower($idx->getName()) === strtolower($nom)) {
                    return;
                }
            }
        } catch (\Throwable) {
            return; // table illisible : on ne tente rien de plus
        }

        $this->addSql(sprintf(
            'CREATE INDEX %s ON %s (%s)',
            $nom,
            $table,
            implode(', ', $colonnes)
        ));
    }

    /** La colonne existe-t-elle en base ? */
    private function colonneExiste(string $table, string $colonne): bool
    {
        try {
            $colonnes = $this->connection->createSchemaManager()->listTableColumns($table);
        } catch (\Throwable) {
            return false;
        }

        foreach (array_keys($colonnes) as $nom) {
            if (strtolower((string) $nom) === strtolower($colonne)) {
                return true;
            }
        }

        return false;
    }
}
