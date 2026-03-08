#!/usr/bin/env php
<?php

function copyDirectory(string $source, string $destination): void
{
    if (!is_dir($source)) {
        echo "Source directory does not exist: $source\n";
        return;
    }

    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }

    $dir = opendir($source);
    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $srcPath = $source . '/' . $file;
        $destPath = $destination . '/' . $file;

        if (is_dir($srcPath)) {
            copyDirectory($srcPath, $destPath);
        } else {
            copy($srcPath, $destPath);
        }
    }
    closedir($dir);
}

$baseDir = dirname(__DIR__);
$resourcesDir = $baseDir . '/src/Resources';
$publicDir = $baseDir . '/public';

echo "Copying static assets...\n";

$copies = [
    ['src' => $resourcesDir . '/plugins', 'dest' => $publicDir . '/plugins'],
    ['src' => $resourcesDir . '/css', 'dest' => $publicDir . '/css'],
    ['src' => $resourcesDir . '/js', 'dest' => $publicDir . '/js'],
    ['src' => $resourcesDir . '/images', 'dest' => $publicDir . '/img'],
];

foreach ($copies as $copy) {
    echo "  {$copy['src']} -> {$copy['dest']}\n";
    copyDirectory($copy['src'], $copy['dest']);
}

echo "Assets copied successfully!\n";
