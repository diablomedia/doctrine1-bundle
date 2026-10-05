<?php

namespace DiabloMedia\Bundle\Doctrine1Bundle\Tests\DependencyInjection;

use DiabloMedia\Bundle\Doctrine1Bundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testEmptyBaseClassIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(false), [[
            'url'              => 'sqlite::memory:',
            'model_generation' => ['base_class' => ''],
        ]]);
    }

    public function testModelGenerationConfigWithShorthandConnection(): void
    {
        $modelGeneration = [
            'schema_path' => '/app/schema.yml',
            'models_path' => '/app/models',
            'base_class'  => 'Avt_Record',
        ];
        $config = (new Processor())->processConfiguration(new Configuration(false), [[
            'url'              => 'sqlite::memory:',
            'model_generation' => $modelGeneration,
        ]]);

        self::assertSame($modelGeneration, $config['model_generation']);
        self::assertSame('sqlite::memory:', $config['connections']['default']['url']);
    }

    public function testModelGenerationDefaults(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(false), [['url' => 'sqlite::memory:']]);

        self::assertSame([
            'schema_path' => '%kernel.project_dir%/application/doctrine/schema',
            'models_path' => '%kernel.project_dir%/application/models',
            'base_class'  => 'Doctrine_Record',
        ], $config['model_generation']);
    }
}
