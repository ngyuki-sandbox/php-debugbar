<?php
declare(strict_types = 1);

namespace App\Component\DebugBar\Example;

$root = realpath(__DIR__) . DIRECTORY_SEPARATOR;
$file = realpath(__DIR__ . $_SERVER['SCRIPT_NAME']);

if (($root !== false) && ($file !== false) && is_file($file) && (substr($file, 0, strlen($root)) === $root)) {
    require $file;
} else {
    require __DIR__ . '/index.php';
}
