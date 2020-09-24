<?php
declare(strict_types = 1);

namespace App\Component\DebugBar\Utils;

class BacktraceFilter
{
    private $prefixes;

    public function __construct(array $prefixes = [])
    {
        $prefixes[] = __NAMESPACE__;
        foreach ($prefixes as $prefix) {
            $pos = strrpos($prefix, '\\');
            if ($pos !== false) {
                $prefix = substr($prefix, 0, $pos + 1);
            }
            $this->prefixes[$prefix] = $prefix;
        }
    }

    public function filter(array $backtrace)
    {
        $skipNum = -1;
        foreach ($backtrace as $f) {
            $class = $f['class'] ?? null;
            if ($class === null) {
                break;
            }
            $hit = false;
            foreach ($this->prefixes as $skip) {
                if (substr($class, 0, strlen($skip)) === $skip) {
                    $hit = true;
                    continue;
                }
            }
            if (!$hit) {
                break;
            }
            $skipNum++;
        }
        if ($skipNum > 0) {
            $backtrace = array_slice($backtrace, $skipNum);
        }
        return $backtrace;
    }
}
