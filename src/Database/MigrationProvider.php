<?php

namespace PixelTrack\Database;

use PixelTrack\Service\Config;
use PixelTrack\Service\Database;
use SQLite3;

class MigrationProvider
{
    private SQLite3 $database;

    public function __construct(
        private readonly Database $databaseService,
        private readonly Config $config,
    ) {
        $this->database = $this->databaseService->getDbInstance();
        $this->createMigrationTable();
    }

    public function migrate(): void
    {
        $migrationFiles = $this->getMigrationFiles();
        $migrationInDatabase = $this->getMigrationFromDatabase();
        $migrationsToRun = array_diff($migrationFiles, $migrationInDatabase);

        $this->runMigration($migrationsToRun);
    }

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
        $this->database->query('CREATE TABLE IF NOT EXISTS `migrations` (
                "name" VARCHAR NOT NULL
            );');
    }

    private function getMigrationFiles(): array
    {
        $migrationsPath = $this->config->getMigrationsPath();
        $migrationFiles = [];
        foreach (glob($migrationsPath . '/*.php') as $filename) {
            $migrationFiles[] = str_replace('.php', '', basename($filename));
        }

        return $migrationFiles;
    }

    private function getMigrationFromDatabase(): array
    {
        $sql = 'SELECT * FROM `migrations`';
        $statement = $this->database->prepare($sql);
        $result = $statement->execute();

        if ($result === false) {
            return [];
        }

        $migrations = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $migrations[] = $row['name'];
        }

        return $migrations;
    }

    private function runMigration(array $migrationsToRun)
    {
        foreach ($migrationsToRun as $migration) {
            $object = require($this->config->getMigrationsPath() . '/' . $migration . '.php');
            $sql = $object->up();

            $statement = $this->database->prepare($sql);
            $result = $statement->execute();

            $this->updateMigrationTable($migration);
        }
    }

    private function updateMigrationTable(mixed $migration): bool
    {
        $sql = 'INSERT INTO `migrations` (name) VALUES (:name)';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':name', $migration);

        return $statement->execute() !== false;
    }
}
