<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261116202253 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE production.lot ADD nb_sacs DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE production.lot RENAME COLUMN poids_net TO pb_th');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE production.lot ADD poids_net DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE production.lot DROP pb_th');
        $this->addSql('ALTER TABLE production.lot DROP nb_sacs');
    }
}
