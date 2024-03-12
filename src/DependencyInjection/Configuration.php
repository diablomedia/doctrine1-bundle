<?php

namespace DiabloMedia\Bundle\Doctrine1Bundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /** @var bool */
    private $debug;

    /**
     * @param bool $debug Whether to use the debug mode
     */
    public function __construct(bool $debug)
    {
        $this->debug = $debug;
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('doctrine1');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->beforeNormalization()
                ->ifTrue(
                    /**
                     * @param mixed $v
                     */
                    static function ($v): bool {
                        return is_array($v) && ! array_key_exists('connections', $v) && ! array_key_exists('connection', $v);
                    }
                )
                ->then(static function (array $v): array {
                    // Key that should not be rewritten to the connection config
                    $excludedKeys = ['default_connection' => true, 'hydrators' => true];
                    $connection   = [];
                    foreach ($v as $key => $value) {
                        if (isset($excludedKeys[$key])) {
                            continue;
                        }
                        $connection[$key] = $v[$key];
                        unset($v[$key]);
                    }
                    $v['default_connection'] = isset($v['default_connection']) ? (string) $v['default_connection'] : 'default';
                    $v['connections']        = [$v['default_connection'] => $connection];

                    return $v;
                })
            ->end()
            ->children()
                ->scalarNode('default_connection')->end()
                ->arrayNode('manager')
                    ->fixXmlConfig('hydrator')
                    ->children()
                        ->arrayNode('hydrators')
                            ->requiresAtLeastOneElement()->arrayPrototype()
                                ->children()
                                    ->scalarNode('name')->end()
                                    ->scalarNode('class')->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->fixXmlConfig('connection')
            ->append($this->getConnectionsNode())
            ->end();

        return $treeBuilder;
    }

    private function configureDriverNode(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->scalarNode('url')->info('A URL with connection information; any parameter value parsed from this string will override explicitly set parameters')->end()
                ->scalarNode('dbname')->end()
                ->booleanNode('logging')->defaultValue($this->debug)->end()
                ->booleanNode('profiling')->defaultValue($this->debug)->end()
                ->scalarNode('host')->defaultValue('localhost')->end()
                ->scalarNode('port')->defaultNull()->end()
                ->scalarNode('user')->defaultValue('root')->end()
                ->scalarNode('password')->defaultNull()->end()
                ->scalarNode('charset')->defaultValue('utf8')->end()
                ->scalarNode('cache_class')->defaultNull()->end()
                ->booleanNode('enable_query_cache')->defaultValue(false)->end()
                ->booleanNode('enable_result_cache')->defaultValue(false)->end()
                ->booleanNode('quote_identifiers')->defaultValue(true)->end()
                ->booleanNode('enable_dql_callbacks')->defaultValue(true)->end()
                ->scalarNode('cache_lifetime')->defaultValue(60 * 60 * 2)->info('Cache expiration, defaults to 2 hours')->end()
                ->scalarNode('timeout')->defaultNull()->end()
                ->scalarNode('collection_class')->defaultNull()->end()
                ->booleanNode('disable_record_cache')->defaultValue(false)->end()
                ->booleanNode('auto_free_query_objects')->defaultValue(false)->end()
            ->end();
    }

    private function getConnectionsNode(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('connections');
        /** @var ArrayNodeDefinition $node */
        $node = $treeBuilder->getRootNode();

        /** @var ArrayNodeDefinition $connectionNode */
        $connectionNode = $node
            ->requiresAtLeastOneElement()
            ->useAttributeAsKey('name')
            ->prototype('array');

        $this->configureDriverNode($connectionNode);

        return $node;
    }
}
