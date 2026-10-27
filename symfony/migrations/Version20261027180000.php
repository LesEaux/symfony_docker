<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remember the light or dark theme for each user.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE user ADD theme VARCHAR(5) DEFAULT 'dark' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP theme');
    }
}
