<?php

namespace DiabloMedia\Bundle\Doctrine1Bundle\Tests\DependencyInjection;

use DiabloMedia\Bundle\Doctrine1Bundle\DependencyInjection\Doctrine1Extension;
use DiabloMedia\Bundle\Doctrine1Bundle\Doctrine1Bundle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ContainerCompilationTest extends TestCase
{
    public static function modelGenerationProvider(): array
    {
        return [
            'defaults' => [[], [
                '%kernel.project_dir%/application/doctrine/schema',
                '%kernel.project_dir%/application/models',
                'Doctrine_Record',
            ]],
            'custom paths and record class' => [[
                'schema_path' => '%kernel.project_dir%/custom/schema.yml',
                'models_path' => '%kernel.project_dir%/custom/models',
                'base_class'  => 'Avt_Record',
            ], [
                '%kernel.project_dir%/custom/schema.yml',
                '%kernel.project_dir%/custom/models',
                'Avt_Record',
            ]],
        ];
    }

    #[DataProvider('modelGenerationProvider')]
    public function testBundleContainerCompiles(array $modelGeneration, array $commandArguments): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.project_dir', '/app');

        $bundle = new Doctrine1Bundle();
        $bundle->build($container);
        $container->addCompilerPass(new AddConsoleCommandPass());

        $extension = new Doctrine1Extension();
        $extension->load([[
            'model_generation'   => $modelGeneration,
            'default_connection' => 'default',
            'manager'            => [
                'hydrators' => [[
                    'name'  => 'array',
                    'class' => 'Doctrine_Hydrator_Array',
                ]],
            ],
            'connections' => [
                'default' => [
                    'url' => 'mysql://user:password@localhost/database',
                ],
            ],
        ]], $container);

        self::assertTrue($container->hasDefinition('doctrine1'));
        self::assertTrue($container->hasDefinition('doctrine1.manager'));
        self::assertTrue($container->hasDefinition('doctrine1.default_connection'));
        self::assertSame($commandArguments, $container->getDefinition('doctrine1.command.generate_models')->getArguments());

        $container->compile();

        self::assertTrue($container->has('doctrine1'));
        self::assertTrue($container->has('doctrine1_manager'));

        $loader = $container->get('console.command_loader');
        self::assertInstanceOf(CommandLoaderInterface::class, $loader);
        self::assertTrue($loader->has('doctrine:generate-models'));
        self::assertSame('doctrine:generate-models', $loader->get('doctrine:generate-models')->getName());
    }
}
