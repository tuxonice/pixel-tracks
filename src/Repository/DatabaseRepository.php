<?php

namespace PixelTrack\Repository;

use PixelTrack\DataTransferObjects\TrackTransfer;
use PixelTrack\DataTransferObjects\UserTransfer;
use PixelTrack\Service\Database;
use SQLite3;

class DatabaseRepository
{
    private SQLite3 $database;


    public function __construct(private readonly Database $databaseService)
    {
        $this->database = $this->databaseService->getDbInstance();
    }

    public function createDatabase(): void
    {
        $this->database->query('CREATE TABLE IF NOT EXISTS "users" (
                "id" INTEGER PRIMARY KEY AUTOINCREMENT,            
                "key" VARCHAR NOT NULL,
                "email" VARCHAR NOT NULL
            )');

        $this->database->query('CREATE TABLE IF NOT EXISTS "tracks" (
                "id" INTEGER PRIMARY KEY AUTOINCREMENT, 
                "user_id" INTEGER NOT NULL,
                "name" VARCHAR NOT NULL,
                "key" VARCHAR NOT NULL,
                "shared_key" VARCHAR DEFAULT NULL,
                "filename" VARCHAR NOT NULL
            )');
    }
}
