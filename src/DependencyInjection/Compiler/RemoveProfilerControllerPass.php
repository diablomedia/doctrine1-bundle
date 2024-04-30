<?php

namespace DiabloMedia\Bundle\Doctrine1Bundle\DependencyInjection\Compiler;

use DiabloMedia\Bundle\Doctrine1Bundle\Controller\ProfilerController;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/** @internal */
final class RemoveProfilerControllerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->has('twig') && $container->has('profiler')) {
            return;
        }

        $container->removeDefinition(ProfilerController::class);
    }
}
