<?php

namespace DiabloMedia\Bundle\Doctrine1Bundle\DataCollector;

use Doctrine_Connection_Profiler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Throwable;

use function array_map;
use function array_sum;
use function count;
use function usort;

class DoctrineDataCollector extends DataCollector
{
    private $connections = [];

    /**
     * @var mixed[][]|null
     */
    private $groupedQueries;

    private $loggers = [];

    public function addLogger(string $name, Doctrine_Connection_Profiler $logger): void
    {
        $this->loggers[$name] = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, Throwable $exception = null): void
    {
        $queries = [];

        foreach ($this->loggers as $name => $profiler) {
            $this->connections[] = $name;

            $queries[$name] = $this->sanitizeQueries($name, $profiler);

            $this->data = [
                'queries'     => $queries,
                'connections' => $this->connections,
            ];
        }

        $this->groupedQueries = null;
    }

    public function getConnections()
    {
        return $this->data['connections'];
    }

    public function getGroupedQueries()
    {
        if ($this->groupedQueries !== null) {
            return $this->groupedQueries;
        }

        $this->groupedQueries = [];
        $totalExecutionMS     = 0;
        foreach ($this->data['queries'] as $connection => $queries) {
            $connectionGroupedQueries = [];
            foreach ($queries as $i => $query) {
                $key = $query['sql'];
                if (! isset($connectionGroupedQueries[$key])) {
                    $connectionGroupedQueries[$key]                = $query;
                    $connectionGroupedQueries[$key]['executionMS'] = 0;
                    $connectionGroupedQueries[$key]['count']       = 0;
                    $connectionGroupedQueries[$key]['index']       = $i; // "Explain query" relies on query index in 'queries'.
                }
                $connectionGroupedQueries[$key]['executionMS'] += $query['executionMS'];
                $connectionGroupedQueries[$key]['count']++;
                $totalExecutionMS += $query['executionMS'];
            }
            usort($connectionGroupedQueries, static function ($a, $b) {
                if ($a['executionMS'] === $b['executionMS']) {
                    return 0;
                }

                return $a['executionMS'] < $b['executionMS'] ? 1 : -1;
            });
            $this->groupedQueries[$connection] = $connectionGroupedQueries;
        }

        foreach ($this->groupedQueries as $connection => $queries) {
            foreach ($queries as $i => $query) {
                $this->groupedQueries[$connection][$i]['executionPercent'] = $this->executionTimePercentage($query['executionMS'], $totalExecutionMS);
            }
        }

        return $this->groupedQueries;
    }

    public function getGroupedQueryCount()
    {
        $count = 0;
        foreach ($this->getGroupedQueries() as $connectionGroupedQueries) {
            $count += count($connectionGroupedQueries);
        }

        return $count;
    }

    public function getName()
    {
        return 'doctrine1';
    }

    public function getQueries()
    {
        return $this->data['queries'];
    }

    public function getQueryCount()
    {
        return array_sum(array_map('count', $this->data['queries']));
    }

    public function getTime()
    {
        $time = 0;
        foreach ($this->data['queries'] as $queries) {
            foreach ($queries as $query) {
                $time += $query['executionMS'];
            }
        }

        return $time;
    }

    public function reset(): void
    {
        $this->data = [];

        foreach ($this->loggers as $logger) {
            // Doctrine_Connection_Profiler doesn't have a way to reset, but popping all the events off
            // of the stack should clear the stack
            do {
                $event = $logger->pop();
            } while ($event !== null);
        }
    }

    private function executionTimePercentage($executionTimeMS, $totalExecutionTimeMS)
    {
        if ($totalExecutionTimeMS === 0.0 || $totalExecutionTimeMS === 0) {
            return 0;
        }

        return $executionTimeMS / $totalExecutionTimeMS * 100;
    }

    /**
     * Sanitizes a param.
     *
     * The return value is an array with the sanitized value and a boolean
     * indicating if the original value was kept (allowing to use the sanitized
     * value to explain the query).
     */
    private function sanitizeParam($var, ?\Throwable $error): array
    {
        if ($error) {
            return ['⚠ ' . $error->getMessage(), false, false];
        }

        if (\is_array($var)) {
            $a           = [];
            $explainable = $runnable = true;
            foreach ($var as $k => $v) {
                [$value, $e, $r] = $this->sanitizeParam($v, null);
                $explainable     = $explainable && $e;
                $runnable        = $runnable && $r;
                $a[$k]           = $value;
            }

            return [$a, $explainable, $runnable];
        }

        if (\is_resource($var)) {
            return [sprintf('/* Resource(%s) */', get_resource_type($var)), false, false];
        }

        return [$var, true, true];
    }

    private function sanitizeQueries(string $connectionName, Doctrine_Connection_Profiler $profiler): array
    {
        $queries        = [];
        $listenForTypes = ['exec', 'execute', 'query'];
        foreach ($profiler as $i => $event) {
            if (!in_array($event->getName(), $listenForTypes)) {
                continue;
            }
            $query                = [];
            $query['params']      = $event->getParams();
            $query['sql']         = $event->getQuery();
            $query['executionMS'] = $event->getElapsedSecs();

            $queries[$i] = $this->sanitizeQuery($connectionName, $query);
        }

        return $queries;
    }

    private function sanitizeQuery(string $connectionName, array $query): array
    {
        $query['explainable'] = true;
        $query['runnable']    = true;
        if (null === $query['params']) {
            $query['params'] = [];
        }
        if (!\is_array($query['params'])) {
            $query['params'] = [$query['params']];
        }
        foreach ($query['params'] as $j => $param) {
            $e = null;

            [$query['params'][$j], $explainable, $runnable] = $this->sanitizeParam($param, $e);
            if (!$explainable) {
                $query['explainable'] = false;
            }

            if (!$runnable) {
                $query['runnable'] = false;
            }
        }

        $query['params'] = $this->cloneVar($query['params']);

        return $query;
    }
}
