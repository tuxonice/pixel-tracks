<?php

namespace PixelTrack\Database\Migrations;

use PixelTrack\Database\MigrationInterface;

return new class implements MigrationInterface
{
    public function up(): string
    {
        return 'CREATE TABLE `tracks` (
                "id" INTEGER PRIMARY KEY AUTOINCREMENT, 
                "user_id" INTEGER NOT NULL,
                "name" VARCHAR NOT NULL,
                "key" VARCHAR NOT NULL,
                "shared_key" VARCHAR DEFAULT NULL,
                "filename" VARCHAR NOT NULL
            );';
    }

    public function down(): string
    {
        return 'DROP TABLE `tracks`;';
    }
};
