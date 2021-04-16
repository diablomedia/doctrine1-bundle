<?php

namespace DiabloMedia\Bundle\Doctrine1Bundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class Doctrine1Extension extends Extension
{
    /**
     * @var string
     */
    private $defaultConnection = '';

    public function getConfiguration(array $config, ContainerBuilder $container): Configuration
    {
        return new Configuration((bool) $container->getParameter('kernel.debug'));
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('doctrine1.xml');

        $configuration = $this->getConfiguration($configs, $container);
        $config        = $this->processConfiguration($configuration, $configs);

        if (empty($config['default_connection'])) {
            $keys                         = array_keys($config['connections']);
            $config['default_connection'] = reset($keys);
        }

        $this->defaultConnection = $config['default_connection'];

        $container->setAlias('doctrine1_manager', 'doctrine1.manager');
        $container->getAlias('doctrine1_manager')->setPublic(true);

        $connections = [];

        foreach (array_keys($config['connections']) as $name) {
            $connections[$name] = sprintf('doctrine1.%s_connection', $name);
        }

        $container->setParameter('doctrine1.connections', $connections);
        $container->setParameter('doctrine1.default_connection', $this->defaultConnection);

        foreach ($config['connections'] as $name => $connection) {
            $this->loadConnection($name, $connection, $container);
        }

        $container->setDefinition('doctrine1.manager.configuration', new Definition(\DiabloMedia\Bundle\Doctrine1Bundle\Configuration::class))
            ->addMethodCall('setManagerConfig', [$config['manager']]);
    }

    public function loadConnection(string $name, array $connection, ContainerBuilder $container): void
    {
        $configuration = $container->setDefinition(
            sprintf('doctrine1.%s_connection.configuration', $name),
            new ChildDefinition('doctrine1.connection.configuration')
        );

        $logger = null;

        if ($connection['profiling']) {
            $profilingAbstractId = 'doctrine1.logger.profiling';

            $profilingLoggerId = $profilingAbstractId . '.' . $name;
            $container->setDefinition($profilingLoggerId, new ChildDefinition($profilingAbstractId));
            $profilingLogger = new Reference($profilingLoggerId);
            $container->getDefinition('data_collector.doctrine1')->addMethodCall('addLogger', [$name, $profilingLogger]);
            $logger = $profilingLogger;
        }
        unset($connection['profiling']);

        if ($logger) {
            $configuration->addMethodCall('setSQLLogger', [$logger]);
        }

        // connection
        $options = $this->getConnectionOptions($connection);

        $options['connection_name'] = $name;

        $container
            ->setDefinition(sprintf('doctrine1.%s_connection', $name), new ChildDefinition('doctrine1.connection'))
            ->setPublic(true)
            ->setArguments([
                $options,
                new Reference(sprintf('doctrine1.%s_connection.configuration', $name)),
            ]);
    }

    protected function getConnectionOptions(array $connection): array
    {
        $options = $connection;

        foreach ([
            'options' => 'driverOptions',
        ] as $old => $new) {
            if (! isset($options[$old])) {
                continue;
            }

            $options[$new] = $options[$old];
            unset($options[$old]);
        }

        return $options;
    }
}
