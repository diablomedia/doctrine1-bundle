<?php

namespace DiabloMedia\Bundle\Doctrine1Bundle;

use Doctrine_Manager;

class ManagerFactory
{
    public function __invoke(
        Configuration $config,
        array $connections,
        string $defaultConnection,
        \Symfony\Component\DependencyInjection\Container $service
    ): Doctrine_Manager {
        $config = $config->getManagerConfig();

        $dm = Doctrine_Manager::getInstance();

        foreach ($config['hydrators'] as $hydrator) {
            $dm->registerHydrator(
                $hydrator['name'],
                $hydrator['class']
            );
        }

        foreach ($connections as $connection) {
            $service->get($connection);
        }

        $dm->setCurrentConnection($defaultConnection);

        return $dm;
    }
}
