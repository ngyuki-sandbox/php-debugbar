<?php
declare(strict_types = 1);

namespace App\Component\DebugBar\Integration;

if (version_compare(PHP_VERSION, '8.0.0') < 0) {
    require __DIR__ . '/ExtendsPdo7.php';
} else {
    require __DIR__ . '/ExtendsPdo8.php';
}
