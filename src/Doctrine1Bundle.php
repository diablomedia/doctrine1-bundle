<?php

namespace DiabloMedia\Bundle\Doctrine1Bundle;

use DiabloMedia\Bundle\Doctrine1Bundle\DependencyInjection\Compiler\RemoveProfilerControllerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class Doctrine1Bundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new RemoveProfilerControllerPass());
    }
}
