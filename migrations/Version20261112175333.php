<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261112175333 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_courrier_statut');
        $this->addSql('ALTER TABLE courrier ADD code_service_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE courrier ALTER service TYPE VARCHAR(150)');
        $this->addSql('COMMENT ON COLUMN courrier.date_courrier IS \'\'');
        $this->addSql('COMMENT ON COLUMN courrier.date_accuse IS \'\'');
        $this->addSql('COMMENT ON COLUMN courrier.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN courrier.updated_at IS \'\'');
        $this->addSql('ALTER TABLE courrier ADD CONSTRAINT FK_BEF47CAAC5F25400 FOREIGN KEY (code_service_id) REFERENCES services (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_BEF47CAAC5F25400 ON courrier (code_service_id)');
        $this->addSql('ALTER INDEX uniq_courrier_numero RENAME TO UNIQ_BEF47CAAF55AE19E');
        $this->addSql('COMMENT ON COLUMN courrier_piece_jointe.created_at IS \'\'');
        $this->addSql('ALTER INDEX idx_courrier_pj_courrier RENAME TO IDX_502418A88BF41DC7');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE courrier DROP CONSTRAINT FK_BEF47CAAC5F25400');
        $this->addSql('DROP INDEX IDX_BEF47CAAC5F25400');
        $this->addSql('ALTER TABLE courrier DROP code_service_id');
        $this->addSql('ALTER TABLE courrier ALTER service TYPE VARCHAR(120)');
        $this->addSql('COMMENT ON COLUMN courrier.date_courrier IS \'(DC2Type:date_immutable)\'');
        $this->addSql('COMMENT ON COLUMN courrier.date_accuse IS \'(DC2Type:date_immutable)\'');
        $this->addSql('COMMENT ON COLUMN courrier.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN courrier.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX idx_courrier_statut ON courrier (statut)');
        $this->addSql('ALTER INDEX uniq_bef47caaf55ae19e RENAME TO uniq_courrier_numero');
        $this->addSql('COMMENT ON COLUMN courrier_piece_jointe.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER INDEX idx_502418a88bf41dc7 RENAME TO idx_courrier_pj_courrier');
    }
}
