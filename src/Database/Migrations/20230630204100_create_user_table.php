<?php

namespace PixelTrack\Database\Migrations;

use PixelTrack\Database\MigrationInterface;

return new class implements MigrationInterface
{
    public function up(): string
    {
        return 'CREATE TABLE `users` (
                "id" INTEGER PRIMARY KEY AUTOINCREMENT,            
                "key" VARCHAR NOT NULL,
                "email" VARCHAR NOT NULL
            );';
    }

    public function down(): string
    {
        return 'DROP table `users`;';
    }
};
