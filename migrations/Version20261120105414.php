<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261120105414 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rh_employe ADD code_pointage VARCHAR(30) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_employe_code_pointage ON rh_employe (code_pointage)');
        $this->addSql('ALTER TABLE rh_formation ADD support VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE rh_formation ADD support_nom VARCHAR(200) DEFAULT NULL');
        $this->addSql('ALTER TABLE rh_formation ADD liste_presences VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE rh_formation ADD liste_presences_nom VARCHAR(200) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_employe_code_pointage');
        $this->addSql('ALTER TABLE rh_employe DROP code_pointage');
        $this->addSql('ALTER TABLE rh_formation DROP support');
        $this->addSql('ALTER TABLE rh_formation DROP support_nom');
        $this->addSql('ALTER TABLE rh_formation DROP liste_presences');
        $this->addSql('ALTER TABLE rh_formation DROP liste_presences_nom');
    }
}
