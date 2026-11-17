<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261116175221 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE production.produit ADD nb_default DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE production.produit ADD poids_net_default DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE production.produit ADD tare_unite_default DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE production.produit ADD poids_brut_default DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE production.produit ADD unite_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE production.produit ADD CONSTRAINT FK_5C4AF0F4EC4A74AB FOREIGN KEY (unite_id) REFERENCES stock_unite (id)');
        $this->addSql('CREATE INDEX IDX_5C4AF0F4EC4A74AB ON production.produit (unite_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE production.produit DROP CONSTRAINT FK_5C4AF0F4EC4A74AB');
        $this->addSql('DROP INDEX production.IDX_5C4AF0F4EC4A74AB');
        $this->addSql('ALTER TABLE production.produit DROP nb_default');
        $this->addSql('ALTER TABLE production.produit DROP poids_net_default');
        $this->addSql('ALTER TABLE production.produit DROP tare_unite_default');
        $this->addSql('ALTER TABLE production.produit DROP poids_brut_default');
        $this->addSql('ALTER TABLE production.produit DROP unite_id');
    }
}
