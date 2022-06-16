<?php

namespace PixelTrack\Command;

use PixelTrack\Repository\DatabaseRepository;
use PixelTrack\Service\Config;
use PixelTrack\Service\Database;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateDatabaseCommand extends Command
{
    protected static $defaultName = 'setup:create-database';

    protected function configure(): void
    {
        $this
            ->setDescription('Create Database')
            ->setHelp('Initial database creation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configService = new Config();
        $database = new Database($configService);
        $databaseRepository = new DatabaseRepository($database);
        $databaseRepository->createDatabase();

        $output->writeln('Database created');
        return Command::SUCCESS;
    }
}
