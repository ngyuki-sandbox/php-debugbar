<?php
declare(strict_types = 1);

namespace App\Component\DebugBar\Integration;

use App\Component\DebugBar\Database\ProfilingStatementStart;
use App\Component\DebugBar\DataCollector\DatabaseCollector;
use App\Component\DebugBar\Utils\BacktraceFilter;
use DebugBar\DataCollector\TimeDataCollector;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Logging\SQLLogger;
use PDO;
use Throwable;

class DoctrineSQLLogger implements SQLLogger
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var DatabaseCollector
     */
    private $databaseCollector;

    /**
     * @var TimeDataCollector|null
     */
    private $timeDataCollector;

    /**
     * @var BacktraceFilter
     */
    private $backtraceFilter;

    /**
     * @var ProfilingStatementStart|null
     */
    private $profiling;

    public function __construct(
        Connection $connection,
        DatabaseCollector $databaseCollector,
        TimeDataCollector $timeDataCollector = null
    ) {
        $this->connection = $connection;
        $this->databaseCollector = $databaseCollector;
        $this->timeDataCollector = $timeDataCollector;
        $this->backtraceFilter = new BacktraceFilter([Connection::class]);
    }

    public function startQuery($sql, array $params = null, array $types = null)
    {
        if ($this->timeDataCollector) {
            $this->timeDataCollector->startMeasure($sql, null, 'database');
        }

        $this->profiling = new ProfilingStatementStart(
            $sql,
            (array)$params,
            $this->backtraceFilter->filter(debug_backtrace(0)),
            function () use ($sql, $params) {
                return $this->doExplain($sql, (array)$params);
            }
        );
    }

    public function stopQuery()
    {
        if ($this->profiling === null) {
            return;
        }

        $profiled = $this->profiling->end();
        $this->profiling = null;

        if ($this->timeDataCollector) {
            $this->timeDataCollector->stopMeasure($profiled->sql, $profiled->params);
        }

        $this->databaseCollector->addStatement($profiled);
    }

    private function doExplain(string $sql, array $params): array
    {
        $logger = $this->connection->getConfiguration()->getSQLLogger();
        $this->connection->getConfiguration()->setSQLLogger(null);
        try {
            $stmt = $this->connection->executeQuery("EXPLAIN $sql", $params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $ex) {
            return [[
                'exception' => get_class($ex),
                'code'      => $ex->getCode(),
                'message'   => $ex->getMessage(),
            ]];
        } finally {
            $this->connection->getConfiguration()->setSQLLogger($logger);
        }
    }
}
