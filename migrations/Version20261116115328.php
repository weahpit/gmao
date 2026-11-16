<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261116115328 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bon_sortie ADD refus_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE bon_sortie ADD refus_par VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE bon_sortie ADD refus_motif VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE sortie_stock ADD cloture_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE sortie_stock ADD cloture_par VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE sortie_stock ADD poste_controle VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE sortie_stock ADD refus_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE sortie_stock ADD refus_par VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE sortie_stock ADD refus_motif VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bon_sortie DROP refus_at');
        $this->addSql('ALTER TABLE bon_sortie DROP refus_par');
        $this->addSql('ALTER TABLE bon_sortie DROP refus_motif');
        $this->addSql('ALTER TABLE sortie_stock DROP cloture_at');
        $this->addSql('ALTER TABLE sortie_stock DROP cloture_par');
        $this->addSql('ALTER TABLE sortie_stock DROP poste_controle');
        $this->addSql('ALTER TABLE sortie_stock DROP refus_at');
        $this->addSql('ALTER TABLE sortie_stock DROP refus_par');
        $this->addSql('ALTER TABLE sortie_stock DROP refus_motif');
    }
}
