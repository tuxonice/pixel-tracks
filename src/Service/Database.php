<?php

namespace PixelTrack\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;

class Database
{
    private Connection $dbConnection;

    public function __construct()
    {
        $dsnParser = new DsnParser();
        $connectionParams = $dsnParser
            ->parse($_ENV['DATABASE_DSN']);
        $this->dbConnection = DriverManager::getConnection($connectionParams);
    }

    public function getDbConnection(): Connection
    {
        return $this->dbConnection;
    }
}
