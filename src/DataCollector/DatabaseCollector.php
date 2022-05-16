<?php
declare(strict_types = 1);

namespace App\Component\DebugBar\DataCollector;

use App\Component\DebugBar\Utils\ProfiledStatement;
use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

class DatabaseCollector extends DataCollector implements Renderable, AssetProvider
{
    /**
     * @var ProfiledStatement[]
     */
    private $statements = [];

    /**
     * @var string|null
     */
    private $appRootDir;

    /**
     * @var int
     */
    private $backtraceLimit = 10;

    /**
     * @var bool
     */
    private $useExplain = false;

    public function __construct(string $appRootDir = null)
    {
        $this->appRootDir = $appRootDir;
    }

    /**
     * スタックトレースの表示数のリミットを設定
     *
     *   -1 ... スタックトレースをすべて表示
     *    0 ... スタックトレースを表示しない
     *   >0 ... 指定件数を超えたスタックトレースを省略表示
     *
     * @param int $backtraceLimit
     * @return $this
     */
    public function setBacktraceLimit(int $backtraceLimit)
    {
        $this->backtraceLimit = $backtraceLimit;
        return $this;
    }

    /**
     * EXPLAIN の有効/無効を設定
     *
     * @param bool $useExplain
     * @return $this
     */
    public function useExplain(bool $useExplain)
    {
        $this->useExplain = $useExplain;
        return $this;
    }

    public function addStatement(ProfiledStatement $statement)
    {
        $this->statements[] = $statement;
    }

    public function collect()
    {
        $formatter = $this->getDataFormatter();
        $statements = [];
        foreach ($this->statements as $stmt) {
            $statements[] = [
                'sql'           => $stmt->sql,
                'params'        => $stmt->params,
                'row_count'     => $stmt->rowCount,
                'duration_str'  => $stmt->duration !== null ? $formatter->formatDuration($stmt->duration) : null,
                // @phan-suppress-next-line PhanTypeMismatchArgument
                'memory_str'    => $stmt->memory !== null ? $formatter->formatBytes($stmt->memory) : null,
                'backtrace'     => $this->collectBacktrace($stmt->backtrace),
                'explain'       => $this->collectExplain($stmt),
                'error'         => $stmt->error,
            ];
        }
        return [
            'nb_statements' => count($statements),
            'statements'    => $statements,
        ];
    }

    private function collectBacktrace(array $backtrace): array
    {
        if ($this->backtraceLimit === 0) {
            return [];
        }

        $omit = 0;

        if ($this->backtraceLimit > 0) {
            $omit = count($backtrace) - $this->backtraceLimit;
            if ($omit < 0) {
                $omit = 0;
            } elseif ($omit > 0) {
                $backtrace = array_slice($backtrace, 0, $this->backtraceLimit);
            }
        }

        $appRootDir = $this->appRootDir;
        if ($appRootDir !== null) {
            $realDir = realpath($appRootDir);
            if ($realDir !== false) {
                $appRootDir = $realDir;
            }
        }

        $ret = [];

        foreach ($backtrace as $f) {
            $file = $f['file'] ?? '-';
            if ($file !== '-') {
                if ($appRootDir !== null) {
                    $real = realpath($file);
                    if ($real !== false) {
                        if (strpos($real, $appRootDir) === 0) {
                            $file = substr($real, strlen($appRootDir) + 1);
                        }
                    }
                }
            }
            $ret[] = [
                'file' => $file,
                'line' => $f['line'] ?? 0,
                'name' => ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? ''),
            ];
        }

        if ($omit) {
            $ret[] = [
                'omit' => $omit,
            ];
        }

        return $ret;
    }

    private function collectExplain(ProfiledStatement $stmt)
    {
        if (!$this->useExplain) {
            return [];
        }

        $explain = $stmt->explain;

        if (is_callable($explain)) {
            if (!preg_match('/^\s*SELECT\b/i', $stmt->sql)) {
                return [];
            }
            $explain = $explain($stmt);
        }

        if (!$explain) {
            return [];
        }

        $columns = [];
        foreach ($explain as $row) {
            $columns = $columns + array_flip(array_keys($row));
        }
        $columns = array_flip($columns);
        $rows = [];
        foreach ($explain as $row) {
            $arr = [];
            foreach ($columns as $column) {
                $arr[] = $row[$column] ?? null;
            }
            $rows[] = $arr;
        }
        return [
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    public function getName()
    {
        return 'database';
    }

    public function getWidgets()
    {
        $name = $this->getName();
        return [
            $name => [
                'tooltip' => 'database',
                'widget'  => 'PhpDebugBar.Widgets.Ritz.Database',
                'map'     => $name,
                'default' => '[]',
            ],
            "$name:badge" => [
                'map'     => "$name.nb_statements",
                'default' => 0,
            ],
        ];
    }

    public function getAssets()
    {
        return [
            'base_path' => __DIR__ . '/../Resources',
            'base_url'  => '/vendor/ritz/php-debugbar/Resources',
            'css'       => 'widgets/database.css',
            'js'        => 'widgets/database.js'
        ];
    }
}
