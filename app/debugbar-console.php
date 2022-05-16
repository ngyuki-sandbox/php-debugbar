<?php
declare(strict_types = 1);

namespace App\Component\DebugBar\Example;

use App\App;
use App\Component\DebugBar\Utils\ConsoleRenderer;

require __DIR__ . '/../vendor/autoload.php';

$container = App::init()->getContainer();
$renderer = $container->get(ConsoleRenderer::class);
echo $renderer->render();
