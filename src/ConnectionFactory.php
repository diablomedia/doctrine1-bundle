<?php

namespace DiabloMedia\Bundle\Doctrine1Bundle;

use Doctrine_Cache_Interface;
use Doctrine_Connection;
use Doctrine_Core;
use Doctrine_Manager;
use PDO;

class ConnectionFactory
{
    public function createConnection(array $params, Configuration $config = null): Doctrine_Connection
    {
        if (isset($params['url'])) {
            $conn = Doctrine_Manager::connection(
                $params['url'],
                $params['connection_name']
            );
        } else {
            $conn = Doctrine_Manager::connection(
                $params['system'] . '://' . urlencode($params['user']) . ':'
                . urlencode($params['password']) . '@'
                . $params['host'] . ':'
                . $params['port'] . '/'
                . $params['database']
                . '?charset=' . urlencode($params['charset']),
                $params['connection_name']
            );
        }

        $cacheDriver = null;

        if ($params['cache_class'] !== null) {
            $cacheDriver = new $params['cache_class']();
            if (!$cacheDriver instanceof Doctrine_Cache_Interface) {
                throw new \InvalidArgumentException('Specified cache_class must implement Doctrine_Cache_Interface');
            }
        }

        if ($cacheDriver) {
            // Query cache (global)
            if ($params['enable_query_cache'] === true) {
                $conn->setAttribute(Doctrine_Core::ATTR_QUERY_CACHE, $cacheDriver);
            }

            // Result cache (enabled on a per-query basis)
            if ($params['enable_query_cache'] === true) {
                $conn->setAttribute(Doctrine_Core::ATTR_RESULT_CACHE, $cacheDriver);
            }
        }

        $lifespan = $params['cache_lifetime'];
        $conn->setAttribute(Doctrine_Core::ATTR_RESULT_CACHE_LIFESPAN, $lifespan);

        if ($params['timeout'] !== null) {
            $conn->setOption('other', [PDO::ATTR_TIMEOUT => $params['timeout']]);
        }

        // Identifier Quoting
        $conn->setAttribute(Doctrine_Core::ATTR_QUOTE_IDENTIFIER, $params['quote_identifiers']);

        // Callbacks (for timestampable and other behaviors)
        $conn->setAttribute(Doctrine_Core::ATTR_USE_DQL_CALLBACKS, $params['enable_dql_callbacks']);

        // Override default collection class so we can add our extensions
        if ($params['collection_class'] !== null) {
            $conn->setAttribute(Doctrine_Core::ATTR_COLLECTION_CLASS, $params['collection_class']);
        }

        if ($config) {
            $profiler = $config->getLogger();

            if ($profiler) {
                $conn->setListener($profiler);
            }
        }

        return $conn;
    }
}
