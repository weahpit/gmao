<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261008090328 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE alerte_anomalie ADD preciser_emplacement VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE alerte_anomalie ADD zone_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE alerte_anomalie ADD CONSTRAINT FK_9135EBD99F2C3FAB FOREIGN KEY (zone_id) REFERENCES zone_exploitation (id)');
        $this->addSql('CREATE INDEX IDX_9135EBD99F2C3FAB ON alerte_anomalie (zone_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE alerte_anomalie DROP CONSTRAINT FK_9135EBD99F2C3FAB');
        $this->addSql('DROP INDEX IDX_9135EBD99F2C3FAB');
        $this->addSql('ALTER TABLE alerte_anomalie DROP preciser_emplacement');
        $this->addSql('ALTER TABLE alerte_anomalie DROP zone_id');
    }
}
