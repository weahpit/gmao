<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * RH : le matricule d'un employe devient facultatif (colonne NULL autorisee).
 * L'unicite est conservee : plusieurs employes sans matricule sont permis.
 * Migration idempotente (sans effet si la table n'existe pas).
 */
final class Version20261124090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'RH : matricule employe facultatif.';
    }

    public function up(Schema $schema): void
    {
        $tables = array_map('strtolower', $this->connection->createSchemaManager()->listTableNames());
        if (!in_array('rh_employe', $tables, true)) {
            return;
        }

        $plateforme = strtolower(get_class($this->connection->getDatabasePlatform()));
        if (str_contains($plateforme, 'postgre')) {
            $this->addSql('ALTER TABLE rh_employe ALTER matricule DROP NOT NULL');
        } else {
            $this->addSql('ALTER TABLE rh_employe MODIFY matricule VARCHAR(20) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        // Retour arriere neutre : des employes sans matricule peuvent exister.
    }
}
