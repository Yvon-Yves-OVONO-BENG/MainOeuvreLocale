<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260804224500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute un slug public hashé unique aux utilisateurs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD slug VARCHAR(64) DEFAULT NULL');
        $this->addSql("UPDATE user SET slug = LOWER(SHA2(CONCAT(UUID(), '-', id, '-', RAND()), 256)) WHERE slug IS NULL OR slug = ''");
        $this->addSql('ALTER TABLE user MODIFY slug VARCHAR(64) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_slug ON user (slug)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_user_slug ON user');
        $this->addSql('ALTER TABLE user DROP slug');
    }
}
