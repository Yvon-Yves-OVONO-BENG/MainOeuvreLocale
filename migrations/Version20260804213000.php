<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260804213000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute et normalise les slugs hashés uniques des contenus exposés dans les URL.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE annonce ADD slug VARCHAR(64) DEFAULT NULL');
        $this->addSql("UPDATE annonce SET slug = LOWER(SHA2(CONCAT(UUID(), '-', id, '-', RAND()), 256)) WHERE slug IS NULL OR slug = ''");
        $this->addSql('ALTER TABLE annonce MODIFY slug VARCHAR(64) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_annonce_slug ON annonce (slug)');

        // Une seule rotation est effectuée au déploiement pour rendre opaques
        // les anciennes URL de jobs qui contenaient encore un slug lisible.
        $this->addSql("UPDATE job SET slug = LOWER(SHA2(CONCAT(UUID(), '-', id, '-', RAND()), 256))");
        $this->addSql('ALTER TABLE job MODIFY slug VARCHAR(64) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_job_slug ON job (slug)');

        $this->addSql("UPDATE categorie SET slug = LOWER(SHA2(CONCAT(UUID(), '-', id, '-', RAND()), 256))");
        $this->addSql('ALTER TABLE categorie MODIFY slug VARCHAR(64) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_categorie_slug ON categorie (slug)');

        $this->addSql("UPDATE profession SET slug = LOWER(SHA2(CONCAT(UUID(), '-', id, '-', RAND()), 256))");
        $this->addSql('ALTER TABLE profession MODIFY slug VARCHAR(64) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_profession_slug ON profession (slug)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_profession_slug ON profession');
        $this->addSql('DROP INDEX uniq_categorie_slug ON categorie');
        $this->addSql('DROP INDEX uniq_job_slug ON job');
        $this->addSql('ALTER TABLE profession MODIFY slug VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE categorie MODIFY slug VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE job MODIFY slug VARCHAR(255) NOT NULL');
        $this->addSql('DROP INDEX uniq_annonce_slug ON annonce');
        $this->addSql('ALTER TABLE annonce DROP slug');
    }
}
