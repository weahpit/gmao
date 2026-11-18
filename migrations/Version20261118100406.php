<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261118100406 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE stock_ordre_usinage_camion (ordre_usinage_id UUID NOT NULL, dechargement_id UUID NOT NULL, PRIMARY KEY (ordre_usinage_id, dechargement_id))');
        $this->addSql('CREATE INDEX IDX_8C6EEA7B51856087 ON stock_ordre_usinage_camion (ordre_usinage_id)');
        $this->addSql('CREATE INDEX IDX_8C6EEA7BA06061A3 ON stock_ordre_usinage_camion (dechargement_id)');
        $this->addSql('ALTER TABLE stock_ordre_usinage_camion ADD CONSTRAINT FK_8C6EEA7B51856087 FOREIGN KEY (ordre_usinage_id) REFERENCES stock_ordre_usinage (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE stock_ordre_usinage_camion ADD CONSTRAINT FK_8C6EEA7BA06061A3 FOREIGN KEY (dechargement_id) REFERENCES stock_dechargement_cacao (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE stock_ordre_usinage DROP CONSTRAINT fk_c01635b7a06061a3');
        $this->addSql('ALTER TABLE stock_ordre_usinage ALTER dechargement_id DROP NOT NULL');
        $this->addSql('ALTER TABLE stock_ordre_usinage ADD CONSTRAINT FK_C01635B7A06061A3 FOREIGN KEY (dechargement_id) REFERENCES stock_dechargement_cacao (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_ordre_usinage_camion DROP CONSTRAINT FK_8C6EEA7B51856087');
        $this->addSql('ALTER TABLE stock_ordre_usinage_camion DROP CONSTRAINT FK_8C6EEA7BA06061A3');
        $this->addSql('DROP TABLE stock_ordre_usinage_camion');
        $this->addSql('ALTER TABLE stock_ordre_usinage DROP CONSTRAINT FK_C01635B7A06061A3');
        $this->addSql('ALTER TABLE stock_ordre_usinage ALTER dechargement_id SET NOT NULL');
        $this->addSql('ALTER TABLE stock_ordre_usinage ADD CONSTRAINT fk_c01635b7a06061a3 FOREIGN KEY (dechargement_id) REFERENCES stock_dechargement_cacao (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
