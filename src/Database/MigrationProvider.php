<?php

namespace PixelTrack\Database;

use Doctrine\DBAL\Connection;
use PixelTrack\Service\Config;
use PixelTrack\Service\Database;

class MigrationProvider
{
    private Connection $database;

    public function __construct(
        private readonly Database $databaseService,
        private readonly Config $config,
    ) {
        $this->database = $this->databaseService->getDbConnection();
        $this->createMigrationTable();
    }

    public function migrate(): void
    {
        $migrationFiles = $this->getMigrationFiles();
        $migrationInDatabase = $this->getMigrationFromDatabase();
        $migrationsToRun = array_diff($migrationFiles, $migrationInDatabase);

        $this->runMigration($migrationsToRun);
    }

    /**
     * @return array<string,bool>
     */
    public function status(): array
    {
        $migrationFiles = $this->getMigrationFiles();
        $migrationInDatabase = $this->getMigrationFromDatabase();
        $migrationsToRun = array_diff($migrationFiles, $migrationInDatabase);

        $migrations = [];
        foreach ($migrationInDatabase as $item) {
            $migrations[$item] = true;
        }

        foreach ($migrationsToRun as $item) {
            $migrations[$item] = false;
        }

        return $migrations;
    }

    private function createMigrationTable(): void
    {
        $this->database->executeQuery('CREATE TABLE IF NOT EXISTS `migrations` (
                "name" VARCHAR NOT NULL
            );');
    }

    /**
     * @return array<int,string>
     */
    private function getMigrationFiles(): array
    {
        $migrationsPath = $this->config->getMigrationsPath();
        $migrationFiles = [];
        foreach (glob($migrationsPath . '/*.php') as $filename) {
            $migrationFiles[] = str_replace('.php', '', basename($filename));
        }

        return $migrationFiles;
    }

    /**
     * @return array<int,string>
     */
    private function getMigrationFromDatabase(): array
    {
        $sql = 'SELECT * FROM `migrations`';
        $statement = $this->database->prepare($sql);
        $result = $statement->executeQuery();

        $migrations = [];
        $rows = $result->fetchAllAssociative();
        foreach ($rows as $row) {
            $migrations[] = $row['name'];
        }

        return $migrations;
    }

    /**
     * @param array<int,string> $migrationsToRun
     *
     * @return void
     */
    private function runMigration(array $migrationsToRun): void
    {
        foreach ($migrationsToRun as $migration) {
            $object = require($this->config->getMigrationsPath() . '/' . $migration . '.php');
            $sql = $object->up();

            $statement = $this->database->prepare($sql);
            $result = $statement->executeQuery();

            $this->updateMigrationTable($migration);
        }
    }

    private function updateMigrationTable(mixed $migration): void
    {
        $sql = 'INSERT INTO `migrations` (name) VALUES (:name)';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':name', $migration);
        $statement->executeQuery();
    }
}
