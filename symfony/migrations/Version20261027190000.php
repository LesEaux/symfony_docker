<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the company logo filename in shared company settings.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE company_settings (id INT NOT NULL, logo_filename VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('INSERT INTO company_settings (id, logo_filename) VALUES (1, NULL)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE company_settings');
    }
}
