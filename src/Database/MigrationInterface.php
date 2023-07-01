<?php

namespace PixelTrack\Database;

interface MigrationInterface
{
    public function up(): string;
}
