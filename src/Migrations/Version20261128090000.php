<?php
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261128090000 extends AbstractMigration
{
    public function getDescription(): string { return 'Reception durable et idempotente des passages Access Req_UserCheck.'; }
    public function up(Schema $schema): void {
        $this->abortIf(!($this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform),'Migration reservee a PostgreSQL.');
        $this->addSql('CREATE TABLE IF NOT EXISTS rh_zkteco_passage (empreinte VARCHAR(64) PRIMARY KEY, source VARCHAR(60) NOT NULL, badge VARCHAR(60) NOT NULL, quand TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, recu_le TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $this->addSql('ALTER TABLE rh_zkteco_passage ADD COLUMN IF NOT EXISTS userid VARCHAR(60)');
        $this->addSql('ALTER TABLE rh_zkteco_passage ADD COLUMN IF NOT EXISTS nom VARCHAR(255)');
        $this->addSql('ALTER TABLE rh_zkteco_passage ADD COLUMN IF NOT EXISTS genre VARCHAR(30)');
        $this->addSql('ALTER TABLE rh_zkteco_passage ADD COLUMN IF NOT EXISTS type_pointage VARCHAR(10)');
        $this->addSql('ALTER TABLE rh_zkteco_passage ADD COLUMN IF NOT EXISTS capteur VARCHAR(60)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_zkteco_badge_quand ON rh_zkteco_passage (badge,quand)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_zkteco_source_quand ON rh_zkteco_passage (source,quand)');
    }
    public function down(Schema $schema): void { $this->throwIrreversibleMigrationException('Les passages de presence ne doivent pas etre supprimes.'); }
}
