<?php

namespace PixelTrack\Command;

use PixelTrack\DataTransfers\DefinitionBuilder;
use PixelTrack\DataTransfers\OutputBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use PixelTrack\DataTransfers\Generator\Generator;

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
        $generator = new Generator(new DefinitionBuilder(), new OutputBuilder());
        $generator->generate();
        return Command::SUCCESS;
    }
}
