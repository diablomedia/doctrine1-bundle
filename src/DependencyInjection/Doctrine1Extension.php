<?php

namespace DiabloMedia\Bundle\Doctrine1Bundle\DependencyInjection;

use DiabloMedia\Bundle\Doctrine1Bundle\Configuration as ManagerConfiguration;
use DiabloMedia\Bundle\Doctrine1Bundle\ConnectionFactory;
use DiabloMedia\Bundle\Doctrine1Bundle\Controller\ProfilerController;
use DiabloMedia\Bundle\Doctrine1Bundle\DataCollector\DoctrineDataCollector;
use DiabloMedia\Bundle\Doctrine1Bundle\ManagerFactory;
use DiabloMedia\Bundle\Doctrine1Bundle\Registry;
use DiabloMedia\Bundle\Doctrine1Bundle\Twig\Doctrine1Extension as TwigDoctrine1Extension;
use Doctrine_Connection;
use Doctrine_Connection_Profiler;
use Doctrine_Manager;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
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
        $this->loadServiceDefinitions($container);

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

    private function loadServiceDefinitions(ContainerBuilder $container): void
    {
        $container->setParameter('doctrine1.connection_factory.class', ConnectionFactory::class);
        $container->setParameter('doctrine1.manager_factory.class', ManagerFactory::class);
        $container->setParameter('doctrine1.class', Registry::class);
        $container->setParameter('doctrine1.configuration.class', ManagerConfiguration::class);
        $container->setParameter('doctrine1.data_collector.class', DoctrineDataCollector::class);
        $container->setParameter('doctrine1.logger.profiling.class', Doctrine_Connection_Profiler::class);
        $container->setParameter('doctrine1.manager.class', Doctrine_Manager::class);
        $container->setParameter('doctrine1.connection.class', Doctrine_Connection::class);

        $container->setAlias(Doctrine_Manager::class, 'doctrine1_manager')->setPublic(false);

        $container->setDefinition('doctrine1.connection_factory', new Definition('%doctrine1.connection_factory.class%'))
            ->setPublic(false);
        $container->setDefinition('doctrine1.manager_factory', new Definition('%doctrine1.manager_factory.class%'))
            ->setPublic(false);
        $container->setDefinition('doctrine1.logger.profiling', new Definition('%doctrine1.logger.profiling.class%'))
            ->setPublic(false)
            ->setAbstract(true);

        $container->setDefinition('doctrine1.manager', new Definition('%doctrine1.manager.class%'))
            ->setPublic(false)
            ->setFactory([new Reference('doctrine1.manager_factory'), '__invoke'])
            ->setArguments([
                new Reference('doctrine1.manager.configuration'),
                '%doctrine1.connections%',
                '%doctrine1.default_connection%',
                new Reference('service_container'),
            ]);

        $container->setDefinition('doctrine1.connection', new Definition('%doctrine1.connection.class%'))
            ->setPublic(false)
            ->setAbstract(true)
            ->setFactory([new Reference('doctrine1.connection_factory'), 'createConnection']);

        $container->setDefinition('data_collector.doctrine1', new Definition('%doctrine1.data_collector.class%'))
            ->setPublic(false)
            ->setArguments([new Reference('doctrine1')])
            ->addTag('data_collector', [
                'template' => '@Doctrine1/Collector/db.html.twig',
                'id'       => 'doctrine1',
                'priority' => 250,
            ]);

        $container->setDefinition('doctrine1.connection.configuration', new Definition('%doctrine1.configuration.class%'))
            ->setPublic(false)
            ->setAbstract(true);
        $container->setDefinition('doctrine1.manager.configuration', new Definition('%doctrine1.configuration.class%'))
            ->setPublic(false);

        $container->setDefinition('doctrine1', new Definition('%doctrine1.class%'))
            ->setPublic(true)
            ->setArguments([
                new Reference('service_container'),
                '%doctrine1.connections%',
                '%doctrine1.default_connection%',
            ])
            ->addTag('kernel.reset', ['method' => 'reset']);

        $container->setDefinition('doctrine1.twig.doctrine_extension', new Definition(TwigDoctrine1Extension::class))
            ->setPublic(false)
            ->addTag('twig.extension');

        $container->setDefinition(ProfilerController::class, new Definition(ProfilerController::class))
            ->setPublic(false)
            ->setArguments([
                new Reference('twig'),
                new Reference('doctrine1'),
                new Reference('profiler'),
            ])
            ->addTag('controller.service_arguments');
    }
}
