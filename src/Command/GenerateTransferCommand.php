<?php

namespace PixelTrack\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tlab\TransferObjects\DataTransferBuilder;

class GenerateTransferCommand extends Command
{
    protected static $defaultName = 'transfer:generate';

    protected function configure(): void
    {
        $this
            ->setDescription('Generate transfer objects')
            ->setHelp('Generate transfer objects');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dataTransferDirectory = dirname(__DIR__) . '/DataTransfers/';
        $dataTransferBuilder = new DataTransferBuilder(
            $dataTransferDirectory . 'Definitions',
            $dataTransferDirectory .  'DataTransferObjects',
            'PixelTrack\\DataTransfers\\DataTransferObjects',
        );
        $dataTransferBuilder->build();

        return Command::SUCCESS;
    }
}
