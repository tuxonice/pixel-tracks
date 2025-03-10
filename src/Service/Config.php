<?php

namespace PixelTrack\Service;

class Config
{
    public static function getLogsFolder(): string
    {
        return dirname(__DIR__, 2) . '/var/logs';
    }

    public function getDataPath(): string
    {
        return dirname(__DIR__, 2) . '/var/data';
    }

    public function getUserDataPath(int $userId): string
    {
        return sprintf("%s/profile-%03d", $this->getDataPath(), $userId);
    }

    public function getSchemaPath(): string
    {
        return dirname(__DIR__) . '/Schemas';
    }

    public function getBaseUrl(): string
    {
        return $_ENV['BASE_URL'];
    }

    public function getDatabaseFile(): string
    {
        return dirname(__DIR__, 2) . '/var/database/' . $_ENV['DATABASE_NAME'];
    }

    public function getMigrationsPath(): string
    {
        return dirname(__DIR__, 2) . '/src/Database/Migrations';
    }

    public function getLoginToleranceTime(): int
    {
        return (int)$_ENV['LOGIN_TOLERANCE_TIME'];
    }

    public static function getAllowCountryCode(): string
    {
        return $_ENV['ALLOW_COUNTRY_CODE'];
    }

    public static function isProductionEnvironment(): bool
    {
        return isset($_ENV['APPLICATION_MODE']) &&
            str_contains($_ENV['APPLICATION_MODE'], 'prod');
    }
}
