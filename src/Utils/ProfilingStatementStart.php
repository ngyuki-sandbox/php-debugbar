<?php
declare(strict_types = 1);

namespace App\Component\DebugBar\Utils;

class ProfilingStatementStart
{
    /**
     * @var string
     */
    private $sql;

    /**
     * @var array
     */
    private $params;

    /**
     * @var int
     */
    private $memory;

    /**
     * @var float
     */
    private $time;

    /**
     * @var array
     */
    private $backtrace;

    /**
     * @var callable|null
     */
    private $explain;

    public function __construct(
        string $sql,
        array $params,
        array $backtrace = [],
        callable $explain = null
    ) {
        $this->sql = $sql;
        $this->params = $params;
        $this->backtrace = $backtrace;
        $this->explain = $explain;

        $this->memory = memory_get_usage(false);
        $this->time = microtime(true);
    }

    public function end(int $rowCount = null, string $error = null): ProfiledStatement
    {
        $time = microtime(true);
        $memory = memory_get_usage(false);

        return new ProfiledStatement(
            $this->sql,
            $this->params,
            $time - $this->time,
            $memory - $this->memory,
            $this->backtrace,
            $error === null ? $this->explain : null,
            $rowCount,
            $error
        );
    }
}
