<?php

namespace DiabloMedia\Bundle\Doctrine1Bundle;

use Doctrine_Manager;

class ManagerFactory
{
    public function __invoke(Configuration $config = null, $connections, $defaultConnection, $service): Doctrine_Manager
    {
        if (!$config) {
            throw new \Exception('Configuration is required');
        }

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
