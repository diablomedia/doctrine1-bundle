<?php

namespace DiabloMedia\Bundle\Doctrine1Bundle\Command;

use Doctrine_Core;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'doctrine1:generate-models', description: 'Generates Doctrine 1 models from the YAML schema')]
final class GenerateModelsCommand extends Command
{
    /** @psalm-api */
    public function __construct(
        private readonly string $schemaPath,
        private readonly string $modelsPath,
        private readonly string $baseClass
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // The original Doctrine 1 YAML parser is not covered by its Composer autoloader.
        $autoload = static function (string $class): void {
            Doctrine_Core::autoload($class);
        };
        spl_autoload_register($autoload);

        try {
            Doctrine_Core::generateModelsFromYaml(
                $this->schemaPath,
                $this->modelsPath,
                ['baseClassName' => $this->baseClass]
            );
        } finally {
            spl_autoload_unregister($autoload);
        }

        $output->writeln('Generated models successfully from YAML schema');

        return self::SUCCESS;
    }
}
