<?php

namespace PixelTrack\Command\Migration;

use Illuminate\Support\Str;
use PixelTrack\Service\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrationMakeCommand extends Command
{
    protected static $defaultName = 'migration:make';

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Migration name')
            ->setDescription('Create new database migration')
            ->setHelp('Create new database migration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configService = new Config();
        $migrationFileName = $configService->getMigrationsPath() . '/' . $this->getMigrationFileName($input->getArgument('name'));

        $template = $this->getFileTemplate();
        file_put_contents($migrationFileName, $template);
        chmod($migrationFileName, 0777);
        $output->writeln($migrationFileName);
        return Command::SUCCESS;
    }

    private function getMigrationFileName(string $name): string
    {
        $name = Str::snake($name);
        return date('YmdHis') . '_' . Str::slug($name, '_') . '.php';
    }

    private function getFileTemplate(): string
    {
        $template = <<<TEMPLATE
<?php

namespace PixelTrack\Database\Migrations;

use PixelTrack\Database\MigrationInterface;

return new class implements MigrationInterface
{
    public function up(): string
    {
        //Add your SQL migration here
    }

    public function down(): string
    {
        //Add your SQL migration here
    }
};
TEMPLATE;

        return $template;
    }
}
