<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261014085527 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE widget (id UUID NOT NULL, libelle VARCHAR(255) DEFAULT NULL, width INT DEFAULT NULL, reference VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE widget_groupe (widget_id UUID NOT NULL, groupe_id UUID NOT NULL, PRIMARY KEY (widget_id, groupe_id))');
        $this->addSql('CREATE INDEX IDX_F0367F29FBE885E2 ON widget_groupe (widget_id)');
        $this->addSql('CREATE INDEX IDX_F0367F297A45358C ON widget_groupe (groupe_id)');
        $this->addSql('ALTER TABLE widget_groupe ADD CONSTRAINT FK_F0367F29FBE885E2 FOREIGN KEY (widget_id) REFERENCES widget (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE widget_groupe ADD CONSTRAINT FK_F0367F297A45358C FOREIGN KEY (groupe_id) REFERENCES public.groupe (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE widget_groupe DROP CONSTRAINT FK_F0367F29FBE885E2');
        $this->addSql('ALTER TABLE widget_groupe DROP CONSTRAINT FK_F0367F297A45358C');
        $this->addSql('DROP TABLE widget');
        $this->addSql('DROP TABLE widget_groupe');
    }
}
