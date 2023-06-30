<?php

namespace PixelTrack\Database;

interface MigrationInterface
{
    public function up(): string;

    public function down(): string;
}
