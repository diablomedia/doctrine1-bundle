<?php

namespace DiabloMedia\Bundle\Doctrine1Bundle\Controller;

use DiabloMedia\Bundle\Doctrine1Bundle\DataCollector\DoctrineDataCollector;
use DiabloMedia\Bundle\Doctrine1Bundle\Registry;
use Doctrine_Connection;
use PDO;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\VarDumper\Cloner\Data;
use Throwable;
use Twig\Environment;

class ProfilerController
{
    private Profiler $profiler;

    private Registry $registry;

    private Environment $twig;

    public function __construct(Environment $twig, Registry $registry, Profiler $profiler)
    {
        $this->twig     = $twig;
        $this->registry = $registry;
        $this->profiler = $profiler;
    }

    /**
     * Renders the profiler panel for the given token.
     *
     * @param string $token          The profiler token
     *
     * @return Response A Response instance
     */
    public function explainAction(string $token, string $connectionName, int $query): Response
    {
        $this->profiler->disable();

        $profile = $this->profiler->loadProfile($token);

        if (!$profile) {
            throw new NotFoundHttpException(sprintf('Token "%s" is not found.', $token));
        }

        $collector = $profile->getCollector('doctrine1');

        assert($collector instanceof DoctrineDataCollector);

        $queries = $collector->getQueries();

        if (! isset($queries[$connectionName][$query])) {
            return new Response('This query does not exist.');
        }

        $query = $queries[$connectionName][$query];
        if (! $query['explainable']) {
            return new Response('This query cannot be explained.');
        }

        $connection = $this->registry->getConnection($connectionName);
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
        } catch (Throwable $e) {
            return new Response('This query cannot be explained.');
        }

        return new Response($this->twig->render('@Doctrine1/Collector/explain.html.twig', [
            'data'  => $results,
            'query' => $query,
        ]));
    }

    private function explainOtherPlatform(Doctrine_Connection $connection, array $query): array
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
    private function explainSQLitePlatform(Doctrine_Connection $connection, array $query): array
    {
        $params = $query['params'];

        if ($params instanceof Data) {
            $params = $params->getValue(true);
        }

        return $connection->standaloneQuery('EXPLAIN QUERY PLAN ' . $query['sql'], $params ?? [])
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    private function explainSQLServerPlatform(Doctrine_Connection $connection, array $query): array
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
