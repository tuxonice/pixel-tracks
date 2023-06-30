<?php

namespace PixelTrack\Command\Migration;

use PixelTrack\Database\MigrationProvider;
use PixelTrack\Service\Config;
use PixelTrack\Service\Database;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrationApplyCommand extends Command
{
    protected static $defaultName = 'migration:apply';

    protected function configure(): void
    {
        $this
            ->setDescription('Migrate Database')
            ->setHelp('Apply migration to database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configService = new Config();
        $database = new Database($configService);
        $migrationProvider = new MigrationProvider($database, $configService);
        $migrationProvider->migrate();

        $output->writeln('Database migrated');
        return Command::SUCCESS;
    }
}
