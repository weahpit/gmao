<?php

declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261127120000 extends AbstractMigration
{
    public function getDescription(): string { return 'CITRAC V5 : embarquement, photos, BL, références, soldes sacs, vivier et API de présence.'; }
    public function up(Schema $schema): void {
        $this->abortIf(!str_contains(strtolower(get_class($this->connection->getDatabasePlatform())),'postgresql'), 'PostgreSQL requis.');
        foreach(require __DIR__.'/evolutions_v5_sql.php' as $sql) $this->addSql($sql);
    }
    public function down(Schema $schema): void {
        // Neutre : conservation des données métier et des photos.
    }
}
