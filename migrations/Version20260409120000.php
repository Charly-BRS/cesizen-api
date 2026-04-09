<?php

// migrations/Version20260409120000.php
// Ajout des colonnes de réinitialisation de mot de passe sur la table utilisateurs.
//
// reset_password_token        : token alphanumérique généré lors d'une demande de reset
// reset_password_token_expiry : date d'expiration du token (1 heure après génération)

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des colonnes reset_password_token et reset_password_token_expiry sur la table utilisateurs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateurs ADD reset_password_token VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE utilisateurs ADD reset_password_token_expiry TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateurs DROP COLUMN reset_password_token');
        $this->addSql('ALTER TABLE utilisateurs DROP COLUMN reset_password_token_expiry');
    }
}
