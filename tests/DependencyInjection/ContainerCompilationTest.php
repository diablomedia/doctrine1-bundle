<?php

namespace DiabloMedia\Bundle\Doctrine1Bundle\Tests\DependencyInjection;

use DiabloMedia\Bundle\Doctrine1Bundle\DependencyInjection\Doctrine1Extension;
use DiabloMedia\Bundle\Doctrine1Bundle\Doctrine1Bundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ContainerCompilationTest extends TestCase
{
    public function testBundleContainerCompiles(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);

        $bundle = new Doctrine1Bundle();
        $bundle->build($container);

        $extension = new Doctrine1Extension();
        $extension->load([[
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

        $container->compile();

        self::assertTrue($container->has('doctrine1'));
        self::assertTrue($container->has('doctrine1_manager'));
    }
}
