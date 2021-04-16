<?php

namespace DiabloMedia\Bundle\Doctrine1Bundle\Controller;

use Doctrine_Connection;
use Doctrine_Manager;
use Exception;
use PDO;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\VarDumper\Cloner\Data;

class ProfilerController implements ContainerAwareInterface
{
    /** @var ContainerInterface|null */
    private $container;

    /**
     * Renders the profiler panel for the given token.
     *
     * @param string $token          The profiler token
     *
     * @return Response A Response instance
     */
    public function explainAction(string $token, string $connectionName, int $query): Response
    {
        /** @var Profiler $profiler */
        $profiler = $this->container->get('profiler');
        $profiler->disable();

        $profile = $profiler->loadProfile($token);
        /** @var \DiabloMedia\Bundle\Doctrine1Bundle\DataCollector\DoctrineDataCollector */
        $collector = $profile->getCollector('doctrine1');
        $queries = $collector->getQueries();

        if (! isset($queries[$connectionName][$query])) {
            return new Response('This query does not exist.');
        }

        $query = $queries[$connectionName][$query];
        if (! $query['explainable']) {
            return new Response('This query cannot be explained.');
        }

        /** @var Doctrine_Manager $manager */
        $manager = $this->container->get('doctrine1');
        $connection = $manager->getConnection($connectionName);
        try {
            /** @var string $platform */
            $platform = $connection->getDriverName();
            if ($platform === 'Sqlite') {
                $results = $this->explainSQLitePlatform($connection, $query);
            } elseif ($platform === 'Mssql') {
                $results = $this->explainSQLServerPlatform($connection, $query);
            } else {
                $results = $this->explainOtherPlatform($connection, $query);
            }
        } catch (Exception $e) {
            return new Response('This query cannot be explained.');
        }

        return new Response($this->container->get('twig')->render('@Doctrine1/Collector/explain.html.twig', [
            'data'  => $results,
            'query' => $query,
        ]));
    }

    /**
     * {@inheritDoc}
     */
    public function setContainer(ContainerInterface $container = null): void
    {
        $this->container = $container;
    }

    private function explainOtherPlatform(Doctrine_Connection $connection, $query)
    {
        $params = $query['params'];

        if ($params instanceof Data) {
            $params = $params->getValue(true);
        }

        return $connection->standaloneQuery('EXPLAIN ' . $query['sql'], $params ?? [])
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param mixed[] $query
     */
    private function explainSQLitePlatform(Doctrine_Connection $connection, array $query)
    {
        $params = $query['params'];

        if ($params instanceof Data) {
            $params = $params->getValue(true);
        }

        return $connection->standaloneQuery('EXPLAIN QUERY PLAN ' . $query['sql'], $params ?? [])
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    private function explainSQLServerPlatform(Doctrine_Connection $connection, $query)
    {
        if (stripos($query['sql'], 'SELECT') === 0) {
            $sql = 'SET STATISTICS PROFILE ON; ' . $query['sql'] . '; SET STATISTICS PROFILE OFF;';
        } else {
            $sql = 'SET SHOWPLAN_TEXT ON; GO; SET NOEXEC ON; ' . $query['sql'] . '; SET NOEXEC OFF; GO; SET SHOWPLAN_TEXT OFF;';
        }

        $params = $query['params'];

        if ($params instanceof Data) {
            $params = $params->getValue(true);
        }

        $stmt = $connection->standaloneQuery($sql, $params ?? []);
        $stmt->nextRowset();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
