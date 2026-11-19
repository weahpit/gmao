<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Messagerie : elargit la colonne du canal de lecture.
 *
 * Le canal d'une liste de diffusion s'ecrit "liste:<uuid>" et mesure
 * 42 caracteres, alors que la colonne avait ete creee en VARCHAR(40).
 * L'enregistrement du "dernier lu" echouait donc avec :
 * "value too long for type character varying(40)".
 *
 * La migration est IDEMPOTENTE : elle ne fait rien si la colonne est deja
 * assez large, ni si la table n'existe pas encore.
 */
final class Version20261119160000 extends AbstractMigration
{
    private const LONGUEUR = 64;

    public function getDescription(): string
    {
        return 'Messagerie : canal de lecture elargi a 64 caracteres (canaux de listes).';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $tables = array_map('strtolower', $sm->listTableNames());

        if (!in_array('chat_lecture', $tables, true)) {
            // La table n'existe pas encore : les autres migrations la creeront
            // avec la bonne taille, rien a faire ici.
            return;
        }

        $longueurActuelle = $this->longueurColonne('chat_lecture', 'canal');
        if ($longueurActuelle !== null && $longueurActuelle >= self::LONGUEUR) {
            return;
        }

        $platform = $this->connection->getDatabasePlatform()->getName();

        if ($platform === 'postgresql') {
            // PostgreSQL conserve NOT NULL et la contrainte unique existants.
            $this->addSql('ALTER TABLE chat_lecture ALTER COLUMN canal TYPE VARCHAR(' . self::LONGUEUR . ')');
        } elseif (in_array($platform, ['mysql', 'mariadb'], true)) {
            $this->addSql('ALTER TABLE chat_lecture MODIFY canal VARCHAR(' . self::LONGUEUR . ') NOT NULL');
        }
        // SQLite : la longueur des VARCHAR n'est pas appliquee, aucune action.
    }

    public function down(Schema $schema): void
    {
        // Retour arriere volontairement neutre : on ne retrecit jamais une
        // colonne qui contient des donnees (perte de reperes de lecture).
    }

    /** Longueur declaree d'une colonne, ou null si l'information est indisponible. */
    private function longueurColonne(string $table, string $colonne): ?int
    {
        try {
            $valeur = $this->connection->fetchOne(
                'SELECT character_maximum_length FROM information_schema.columns '
                . 'WHERE table_name = ? AND column_name = ?',
                [$table, $colonne]
            );
        } catch (\Throwable) {
            return null;
        }

        return $valeur === null || $valeur === false ? null : (int) $valeur;
    }
}
