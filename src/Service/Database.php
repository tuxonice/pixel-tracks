<?php

namespace PixelTrack\Service;

use SQLite3;

class Database
{
    private SQLite3 $dbInstance;

    public function __construct(private readonly Config $configService)
    {
        $this->dbInstance = new SQLite3(
            $this->configService->getDatabaseFile(),
            SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE
        );
        $this->dbInstance->enableExceptions(true);
    }

    public function getDbInstance(): SQLite3
    {
        return $this->dbInstance;
    }
}
