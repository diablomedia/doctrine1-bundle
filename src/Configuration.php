<?php

namespace DiabloMedia\Bundle\Doctrine1Bundle;

use Doctrine_Connection_Profiler;

class Configuration
{
    /**
     * @var Doctrine_Connection_Profiler|null
     */
    private $logger;

    /**
     * @var array
     */
    private $managerConfig = [];

    public function getLogger(): ?Doctrine_Connection_Profiler
    {
        return $this->logger;
    }

    public function getManagerConfig(): array
    {
        return $this->managerConfig;
    }

    public function setManagerConfig(array $config): void
    {
        $this->managerConfig = $config;
    }

    public function setSQLLogger(Doctrine_Connection_Profiler $logger): void
    {
        $this->logger = $logger;
    }
}
