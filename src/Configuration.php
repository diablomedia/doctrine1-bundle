<?php

namespace DiabloMedia\Bundle\Doctrine1Bundle;

class Configuration
{
    private $logger;

    private $managerConfig;

    public function getLogger()
    {
        return $this->logger;
    }

    public function getManagerConfig()
    {
        return $this->managerConfig;
    }

    public function setManagerConfig($config): void
    {
        $this->managerConfig = $config;
    }

    public function setSQLLogger($logger): void
    {
        $this->logger = $logger;
    }
}
