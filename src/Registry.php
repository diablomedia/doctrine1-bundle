<?php

namespace DiabloMedia\Bundle\Doctrine1Bundle;

use Psr\Container\ContainerInterface;

class Registry
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var array
     */
    private $connections;

    private $defaultConnection;

    public function __construct(ContainerInterface $container, array $connections, $defaultConnection)
    {
        $this->container         = $container;
        $this->connections       = $connections;
        $this->defaultConnection = $defaultConnection;
    }

    public function getConnection($name = null)
    {
        return $this->container->get('doctrine1.' . $name . '_connection');
    }
}
