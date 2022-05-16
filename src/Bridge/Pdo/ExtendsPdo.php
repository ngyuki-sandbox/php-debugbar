<?php
declare(strict_types = 1);

namespace App\Component\DebugBar\Bridge\Pdo;

use App\Component\DebugBar\DataCollector\DatabaseCollector;
use App\Component\DebugBar\Utils\BacktraceFilter;
use App\Component\DebugBar\Utils\ProfilingStatementStart;
use PDO;
use PDOStatement;
use Throwable;

class ExtendsPdo extends PDO
{
    /**
     * @var DatabaseCollector|null
     */
    private $databaseCollector;

    /**
     * @var BacktraceFilter
     */
    private $backtraceFilter;

    public function __construct(string $dsn , string $username = null, string $passwd = null, array $options = null)
    {
        parent::__construct($dsn, $username, $passwd, $options);
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [ExtendsPdoStatement::class, [$this]]);
        $this->backtraceFilter = new BacktraceFilter();
    }

    public function setDatabaseCollector(DatabaseCollector $databaseCollector)
    {
        $this->databaseCollector = $databaseCollector;
        return $this;
    }

    public function beginTransaction(): bool
    {
        return $this->callProfiling('BEGIN', [], function () {
            return parent::beginTransaction();
        });
    }

    public function commit(): bool
    {
        return $this->callProfiling(strtoupper(__FUNCTION__), [], function () {
            return parent::commit();
        });
    }

    public function rollBack(): bool
    {
        return $this->callProfiling(strtoupper(__FUNCTION__), [], function () {
            return parent::rollBack();
        });
    }

    public function exec(string $statement): int | false
    {
        return $this->callProfiling($statement, [], function () use ($statement) {
            return parent::exec($statement);
        });
    }

    public function query($statement, $mode = PDO::ATTR_DEFAULT_FETCH_MODE, ...$fetch_mode_args): PDOStatement | false
    {
        $args = func_get_args();
        return $this->callProfiling($statement, [], function () use ($args) {
            return parent::query(...$args);
        });
    }

    private function startProfiling(string $sql, array $params = []): ProfilingStatementStart
    {
        return new ProfilingStatementStart(
            $sql,
            $params,
            $this->backtraceFilter->filter(debug_backtrace(0)),
            function () use ($sql, $params) {
                return $this->doExplain($sql, $params);
            }
        );
    }

    private function endProfiling(ProfilingStatementStart $start, int $rowCount = null, string $error = null)
    {
        $end = $start->end($rowCount, $error);
        if ($this->databaseCollector) {
            $this->databaseCollector->addStatement($end);
        }
    }

    public function callProfiling(string $sql, array $params, callable $callable)
    {
        $profiling = $this->startProfiling($sql, $params);
        try {
            $ret = $callable();
            if ($ret instanceof PDOStatement) {
                $this->endProfiling($profiling, $ret->rowCount());
            } elseif (is_int($ret)) {
                $this->endProfiling($profiling, $ret);
            } elseif ($ret === false) {
                list($state, $code, $message) = $this->errorInfo();
                $this->endProfiling($profiling, null, "SQLSTATE[$state]: $code $message");
            } else {
                $this->endProfiling($profiling);
            }
            return $ret;
        } catch (Throwable $ex) {
            $this->endProfiling($profiling, null, $ex->getMessage());
            throw $ex;
        }
    }

    private function doExplain(string $sql, array $params): array
    {
        try {
            $stmt = $this->prepare("EXPLAIN $sql");
            $stmt->execute($params);
            if (!$stmt) {
                return [[
                    'errorCode' => $this->errorCode(),
                    'errorInfo' => $this->errorInfo(),
                ]];
            }
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$result) {
                return [[
                    'errorCode' => $stmt->errorCode(),
                    'errorInfo' => $stmt->errorInfo(),
                ]];
            }
            return $result;
        } catch (Throwable $ex) {
            return [[
                'exception' => get_class($ex),
                'code'      => $ex->getCode(),
                'message'   => $ex->getMessage(),
            ]];
        }
    }
}
