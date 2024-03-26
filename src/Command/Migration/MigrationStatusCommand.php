<?php

namespace PixelTrack\Command\Migration;

use PixelTrack\Database\MigrationProvider;
use PixelTrack\Service\Config;
use PixelTrack\Service\Database;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrationStatusCommand extends Command
{
    protected static $defaultName = 'migration:status';

    protected function configure(): void
    {
        $this
            ->setDescription('Migration status')
            ->setHelp('Migration status');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configService = new Config();
        $database = new Database();

        $migrationProvider = new MigrationProvider($database, $configService);
        $migrationStatus = $migrationProvider->status();

        $table = new Table($output);
        $table
            ->setHeaders(['Migration', 'Ran?']);

        foreach ($migrationStatus as $migration => $status) {
            $table->addRow([$migration, $status ? 'Yes' : 'No']);
        }
        $table->render();

        return Command::SUCCESS;
    }
}
