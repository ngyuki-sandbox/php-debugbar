<?php
declare(strict_types = 1);

namespace App\Component\DebugBar\Utils;

class ProfiledStatement
{
    /**
     * @var string
     */
    public $sql;

    /**
     * @var array
     */
    public $params;

    /**
     * @var float|null
     */
    public $duration;

    /**
     * @var int|null
     */
    public $memory;

    /**
     * @var array
     */
    public $backtrace;

    /**
     * @var callable|null
     */
    public $explain;

    /**
     * @var int|null
     */
    public $rowCount;

    /**
     * @var string|null
     */
    public $error;

    public function __construct(
        string $sql,
        array $params,
        float $duration = null,
        int $memory = null,
        array $backtrace = [],
        callable $explain = null,
        int $rowCount = null,
        string $error = null
    ) {
        $this->sql = $sql;
        $this->params = $params;
        $this->duration = $duration;
        $this->memory = $memory;
        $this->backtrace = $this->filterBacktrace($backtrace);
        $this->explain = $explain;
        $this->rowCount = $rowCount;
        $this->error = $error;
    }

    private function filterBacktrace(array $backtrace): array
    {
        $allows = array_flip(['file', 'line', 'class', 'type', 'function']);

        $ret = [];
        foreach ($backtrace as $t) {
            $ret[] = array_intersect_key($t, $allows);
        }

        return $ret;
    }
}
