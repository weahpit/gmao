<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261111172754 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE journal_mouvement (id UUID NOT NULL, action VARCHAR(20) NOT NULL, famille VARCHAR(20) NOT NULL, cible_id UUID DEFAULT NULL, cible_libelle VARCHAR(255) DEFAULT NULL, avant JSON DEFAULT NULL, apres JSON DEFAULT NULL, auteur_id UUID DEFAULT NULL, auteur_nom VARCHAR(180) DEFAULT NULL, date_action TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_JOURNAL_MVT_DATE ON journal_mouvement (date_action)');
        $this->addSql('ALTER TABLE stock_rubrique ALTER active DROP DEFAULT');
        $this->addSql('ALTER TABLE stock_rubrique ALTER position DROP DEFAULT');
        $this->addSql('ALTER INDEX uniq_stock_rubrique_libelle RENAME TO UNIQ_C0625D3EA4D60759');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE journal_mouvement');
        $this->addSql('ALTER TABLE stock_rubrique ALTER active SET DEFAULT true');
        $this->addSql('ALTER TABLE stock_rubrique ALTER "position" SET DEFAULT 0');
        $this->addSql('ALTER INDEX uniq_c0625d3ea4d60759 RENAME TO uniq_stock_rubrique_libelle');
    }
}
