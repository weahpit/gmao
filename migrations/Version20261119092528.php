<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261119092528 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE chat_liste_diffusion (id UUID NOT NULL, nom VARCHAR(120) NOT NULL, description TEXT DEFAULT NULL, active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, cree_par_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_chat_liste_nom ON chat_liste_diffusion (nom)');
        $this->addSql('CREATE INDEX IDX_675C1D55FC29C013 ON chat_liste_diffusion (cree_par_id)');
        $this->addSql('CREATE TABLE chat_liste_diffusion_groupe (liste_diffusion_id UUID NOT NULL, groupe_id UUID NOT NULL, PRIMARY KEY (liste_diffusion_id, groupe_id))');
        $this->addSql('CREATE INDEX IDX_683D51876E6D126D ON chat_liste_diffusion_groupe (liste_diffusion_id)');
        $this->addSql('CREATE INDEX IDX_683D51877A45358C ON chat_liste_diffusion_groupe (groupe_id)');
        $this->addSql('CREATE TABLE chat_liste_diffusion_membre (liste_diffusion_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (liste_diffusion_id, user_id))');
        $this->addSql('CREATE INDEX IDX_9A30268F6E6D126D ON chat_liste_diffusion_membre (liste_diffusion_id)');
        $this->addSql('CREATE INDEX IDX_9A30268FA76ED395 ON chat_liste_diffusion_membre (user_id)');
        $this->addSql('ALTER TABLE chat_liste_diffusion ADD CONSTRAINT FK_675C1D55FC29C013 FOREIGN KEY (cree_par_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE chat_liste_diffusion_groupe ADD CONSTRAINT FK_683D51876E6D126D FOREIGN KEY (liste_diffusion_id) REFERENCES chat_liste_diffusion (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chat_liste_diffusion_groupe ADD CONSTRAINT FK_683D51877A45358C FOREIGN KEY (groupe_id) REFERENCES public.groupe (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chat_liste_diffusion_membre ADD CONSTRAINT FK_9A30268F6E6D126D FOREIGN KEY (liste_diffusion_id) REFERENCES chat_liste_diffusion (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chat_liste_diffusion_membre ADD CONSTRAINT FK_9A30268FA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message_chat ADD type VARCHAR(10) DEFAULT \'texte\' NOT NULL');
        $this->addSql('ALTER TABLE message_chat ADD fichier VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE message_chat ADD fichier_nom VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE message_chat ADD fichier_mime VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE message_chat ADD duree INT DEFAULT NULL');
        $this->addSql('ALTER TABLE message_chat ADD liste_diffusion_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE message_chat ADD CONSTRAINT FK_CC0869736E6D126D FOREIGN KEY (liste_diffusion_id) REFERENCES chat_liste_diffusion (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE INDEX idx_chat_liste ON message_chat (liste_diffusion_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chat_liste_diffusion DROP CONSTRAINT FK_675C1D55FC29C013');
        $this->addSql('ALTER TABLE chat_liste_diffusion_groupe DROP CONSTRAINT FK_683D51876E6D126D');
        $this->addSql('ALTER TABLE chat_liste_diffusion_groupe DROP CONSTRAINT FK_683D51877A45358C');
        $this->addSql('ALTER TABLE chat_liste_diffusion_membre DROP CONSTRAINT FK_9A30268F6E6D126D');
        $this->addSql('ALTER TABLE chat_liste_diffusion_membre DROP CONSTRAINT FK_9A30268FA76ED395');
        $this->addSql('DROP TABLE chat_liste_diffusion');
        $this->addSql('DROP TABLE chat_liste_diffusion_groupe');
        $this->addSql('DROP TABLE chat_liste_diffusion_membre');
        $this->addSql('ALTER TABLE message_chat DROP CONSTRAINT FK_CC0869736E6D126D');
        $this->addSql('DROP INDEX idx_chat_liste');
        $this->addSql('ALTER TABLE message_chat DROP type');
        $this->addSql('ALTER TABLE message_chat DROP fichier');
        $this->addSql('ALTER TABLE message_chat DROP fichier_nom');
        $this->addSql('ALTER TABLE message_chat DROP fichier_mime');
        $this->addSql('ALTER TABLE message_chat DROP duree');
        $this->addSql('ALTER TABLE message_chat DROP liste_diffusion_id');
    }
}
