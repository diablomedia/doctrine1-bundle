<?php

namespace DiabloMedia\Bundle\Doctrine1Bundle;

use Doctrine_Connection;
use Doctrine_Manager;
use Psr\Container\ContainerInterface;

class Registry
{
    /**
     * @var array
     */
    private $connections;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var string
     */
    private $defaultConnection;

    public function __construct(ContainerInterface $container, array $connections, string $defaultConnection)
    {
        $this->container         = $container;
        $this->connections       = $connections;
        $this->defaultConnection = $defaultConnection;
    }

    public function getConnection(string $name = null): Doctrine_Connection
    {
        return $this->container->get('doctrine1.' . $name . '_connection');
    }

    public function reset(): void
    {
        $manager = Doctrine_Manager::getInstance();
        foreach ($this->connections as $connectionName) {
            // Connection names come in as "doctrine1.name_connection", we want "name"
            $connectionName = preg_replace('|_connection$|', '', substr($connectionName, 10));
            $connection     = $manager->getConnection($connectionName);
            $connection->clear();
        }
    }
}
