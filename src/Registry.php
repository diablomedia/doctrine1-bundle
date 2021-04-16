<?php

namespace DiabloMedia\Bundle\Doctrine1Bundle;

use Doctrine_Connection;
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
}
