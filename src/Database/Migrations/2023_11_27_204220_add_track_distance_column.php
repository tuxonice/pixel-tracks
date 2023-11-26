<?php

namespace PixelTrack\Database\Migrations;

use PixelTrack\Database\MigrationInterface;

return new class implements MigrationInterface
{
    public function up(): string
    {
        return 'ALTER TABLE tracks ADD COLUMN distance REAL DEFAULT NULL;';
    }
};
