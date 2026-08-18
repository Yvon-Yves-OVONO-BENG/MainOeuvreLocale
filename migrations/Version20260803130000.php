<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Migrations\AbstractMigration;

final class Version20260803130000 extends AbstractMigration
{
    private const SUBSCRIBER_TABLE = 'newsletter_subscriber';
    private const PROFESSION_TABLE = 'profession';
    private const USER_TABLE = 'user';

    public function getDescription(): string
    {
        return 'Associe les alertes offres à une profession et à un compte utilisateur (migration relançable).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Cette migration est prévue pour MySQL ou MariaDB.'
        );

        $schemaManager = $this->connection->createSchemaManager();

        foreach ([self::SUBSCRIBER_TABLE, self::PROFESSION_TABLE, self::USER_TABLE] as $tableName) {
            $this->abortIf(
                !$schemaManager->tablesExist([$tableName]),
                sprintf('La table %s est introuvable.', $tableName)
            );

            $this->ensureInnoDb($tableName);
        }

        $table = $schemaManager->introspectTable(self::SUBSCRIBER_TABLE);

        if (!$table->hasColumn('profession_id')) {
            $this->addSql('ALTER TABLE `newsletter_subscriber` ADD `profession_id` INT DEFAULT NULL');
        }

        if (!$table->hasColumn('user_id')) {
            $this->addSql('ALTER TABLE `newsletter_subscriber` ADD `user_id` INT DEFAULT NULL');
        }

        /*
         * Les bases MySQL 8 et MariaDB peuvent utiliser deux collations
         * différentes. Cette instruction évite l'erreur 1267 lors du lien
         * par email et garde la longueur compatible avec les anciens index.
         */
        $this->addSql(
            'ALTER TABLE `newsletter_subscriber` '
            .'MODIFY `email` VARCHAR(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL'
        );

        $this->addSql(
            'UPDATE `newsletter_subscriber` n '
            .'INNER JOIN `user` u ON '
            .'CONVERT(n.`email` USING utf8mb4) COLLATE utf8mb4_unicode_ci '
            .'= CONVERT(u.`email` USING utf8mb4) COLLATE utf8mb4_unicode_ci '
            .'SET n.`user_id` = u.`id` '
            .'WHERE n.`user_id` IS NULL'
        );

        if (!$this->hasIndexStartingWith($table, ['profession_id'])) {
            $this->addSql('CREATE INDEX `IDX_ALERT_PROFESSION` ON `newsletter_subscriber` (`profession_id`)');
        }

        if (!$this->hasIndexStartingWith($table, ['user_id'])) {
            $this->addSql('CREATE INDEX `IDX_ALERT_USER` ON `newsletter_subscriber` (`user_id`)');
        }

        if (!$this->hasIndexStartingWith($table, ['is_active', 'profession_id'])) {
            $this->addSql('CREATE INDEX `IDX_ALERT_ACTIVE_PROFESSION` ON `newsletter_subscriber` (`is_active`, `profession_id`)');
        }

        if (!$this->hasForeignKeyForColumn($table, 'profession_id')) {
            $this->addSql(
                'ALTER TABLE `newsletter_subscriber` '
                .'ADD CONSTRAINT `FK_ALERT_PROFESSION` FOREIGN KEY (`profession_id`) '
                .'REFERENCES `profession` (`id`) ON DELETE SET NULL'
            );
        }

        if (!$this->hasForeignKeyForColumn($table, 'user_id')) {
            $this->addSql(
                'ALTER TABLE `newsletter_subscriber` '
                .'ADD CONSTRAINT `FK_ALERT_USER` FOREIGN KEY (`user_id`) '
                .'REFERENCES `user` (`id`) ON DELETE SET NULL'
            );
        }
    }

    public function down(Schema $schema): void
    {
        if (!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            return;
        }

        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist([self::SUBSCRIBER_TABLE])) {
            return;
        }

        $table = $schemaManager->introspectTable(self::SUBSCRIBER_TABLE);

        foreach ($table->getForeignKeys() as $foreignKey) {
            $columns = array_map('strtolower', $foreignKey->getLocalColumns());
            if ($columns === ['profession_id'] || $columns === ['user_id']) {
                $this->addSql(sprintf(
                    'ALTER TABLE `newsletter_subscriber` DROP FOREIGN KEY `%s`',
                    $foreignKey->getName()
                ));
            }
        }

        foreach (['IDX_ALERT_ACTIVE_PROFESSION', 'IDX_ALERT_PROFESSION', 'IDX_ALERT_USER'] as $indexName) {
            if ($table->hasIndex($indexName)) {
                $this->addSql(sprintf(
                    'DROP INDEX `%s` ON `newsletter_subscriber`',
                    $indexName
                ));
            }
        }

        if ($table->hasColumn('profession_id')) {
            $this->addSql('ALTER TABLE `newsletter_subscriber` DROP COLUMN `profession_id`');
        }

        if ($table->hasColumn('user_id')) {
            $this->addSql('ALTER TABLE `newsletter_subscriber` DROP COLUMN `user_id`');
        }
    }

    private function ensureInnoDb(string $tableName): void
    {
        $engine = $this->connection->fetchOne(
            'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$tableName]
        );

        if (is_string($engine) && strtoupper($engine) !== 'INNODB') {
            $this->addSql(sprintf('ALTER TABLE `%s` ENGINE = InnoDB', $tableName));
        }
    }

    /** @param list<string> $columns */
    private function hasIndexStartingWith(Table $table, array $columns): bool
    {
        $columns = array_map('strtolower', $columns);

        foreach ($table->getIndexes() as $index) {
            $indexedColumns = array_map('strtolower', $index->getColumns());
            if (array_slice($indexedColumns, 0, count($columns)) === $columns) {
                return true;
            }
        }

        return false;
    }

    private function hasForeignKeyForColumn(Table $table, string $column): bool
    {
        foreach ($table->getForeignKeys() as $foreignKey) {
            if (array_map('strtolower', $foreignKey->getLocalColumns()) === [strtolower($column)]) {
                return true;
            }
        }

        return false;
    }
}
