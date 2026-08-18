<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Migrations\AbstractMigration;

final class Version20260727183000 extends AbstractMigration
{
    private const TABLE_NAME = 'user';
    private const PROVIDER_ID_LENGTH = 191;

    private const PROVIDER_COLUMNS = [
        'facebook_id' => 'uniq_user_facebook_id',
        'apple_id' => 'uniq_user_apple_id',
        'microsoft_id' => 'uniq_user_microsoft_id',
    ];

    public function getDescription(): string
    {
        return 'Sécurise les identifiants OAuth avec des index uniques compatibles MySQL/MariaDB.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Cette migration est prévue pour MySQL ou MariaDB.'
        );

        $schemaManager = $this->connection->createSchemaManager();
        $this->abortIf(
            !$schemaManager->tablesExist([self::TABLE_NAME]),
            'La table user est introuvable.'
        );

        $table = $schemaManager->introspectTable(self::TABLE_NAME);

        foreach (self::PROVIDER_COLUMNS as $column => $indexName) {
            if (!$table->hasColumn($column)) {
                $this->addSql(sprintf(
                    'ALTER TABLE `%s` ADD `%s` VARCHAR(%d) DEFAULT NULL',
                    self::TABLE_NAME,
                    $column,
                    self::PROVIDER_ID_LENGTH
                ));

                $this->addSql(sprintf(
                    'CREATE UNIQUE INDEX `%s` ON `%s` (`%s`)',
                    $indexName,
                    self::TABLE_NAME,
                    $column
                ));

                continue;
            } else {
                $tooLongCount = (int) $this->connection->fetchOne(sprintf(
                    'SELECT COUNT(*) FROM `%s` WHERE `%s` IS NOT NULL AND CHAR_LENGTH(`%s`) > %d',
                    self::TABLE_NAME,
                    $column,
                    $column,
                    self::PROVIDER_ID_LENGTH
                ));

                $this->abortIf(
                    $tooLongCount > 0,
                    sprintf(
                        'Certaines valeurs de user.%s dépassent %d caractères.',
                        $column,
                        self::PROVIDER_ID_LENGTH
                    )
                );

                $columnLength = $table->getColumn($column)->getLength();
                if ($columnLength === null || $columnLength > self::PROVIDER_ID_LENGTH) {
                    $this->addSql(sprintf(
                        'ALTER TABLE `%s` MODIFY `%s` VARCHAR(%d) DEFAULT NULL',
                        self::TABLE_NAME,
                        $column,
                        self::PROVIDER_ID_LENGTH
                    ));
                }
            }

            $duplicateCount = (int) $this->connection->fetchOne(sprintf(
                'SELECT COUNT(*) FROM ('
                .'SELECT `%1$s` FROM `%2$s` WHERE `%1$s` IS NOT NULL '
                .'GROUP BY `%1$s` HAVING COUNT(*) > 1'
                .') AS duplicated_provider_ids',
                $column,
                self::TABLE_NAME
            ));

            $this->abortIf(
                $duplicateCount > 0,
                sprintf('Des doublons existent dans user.%s.', $column)
            );

            if (!$this->hasUniqueIndexForColumn($table, $column)) {
                $this->addSql(sprintf(
                    'CREATE UNIQUE INDEX `%s` ON `%s` (`%s`)',
                    $indexName,
                    self::TABLE_NAME,
                    $column
                ));
            }
        }
    }

    public function down(Schema $schema): void
    {
        if (!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            return;
        }

        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist([self::TABLE_NAME])) {
            return;
        }

        $table = $schemaManager->introspectTable(self::TABLE_NAME);

        foreach (self::PROVIDER_COLUMNS as $indexName) {
            if ($table->hasIndex($indexName)) {
                $this->addSql(sprintf(
                    'DROP INDEX `%s` ON `%s`',
                    $indexName,
                    self::TABLE_NAME
                ));
            }
        }
    }

    private function hasUniqueIndexForColumn(Table $table, string $column): bool
    {
        foreach ($table->getIndexes() as $index) {
            if (
                $index->isUnique()
                && array_map('strtolower', $index->getColumns()) === [strtolower($column)]
            ) {
                return true;
            }
        }

        return false;
    }
}
