<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Account activation and expiring, hashed password reset tokens.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD is_active TINYINT(1) DEFAULT 1 NOT NULL, ADD password_reset_token VARCHAR(64) DEFAULT NULL, ADD password_reset_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD password_reset_requested_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP is_active, DROP password_reset_token, DROP password_reset_expires_at, DROP password_reset_requested_at');
    }
}
