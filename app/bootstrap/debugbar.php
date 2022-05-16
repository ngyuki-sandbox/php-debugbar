<?php
declare(strict_types = 1);

namespace App;

use App\Component\DebugBar\DataCollector\DatabaseCollector;
use App\Component\DebugBar\DataCollector\ServiceWorkerCollector;
use App\Component\DebugBar\DataCollector\SettingDataCollector;
use App\Component\DebugBar\Middleware\DebugBarMiddleware;
use App\Component\DebugBar\Storage\GarbageableFileStorage;
use DebugBar\DataCollector\ConfigCollector;
use DebugBar\DataCollector\ExceptionsCollector;
use DebugBar\DataCollector\MemoryCollector;
use DebugBar\DataCollector\MessagesCollector;
use DebugBar\DataCollector\RequestDataCollector;
use DebugBar\DataCollector\TimeDataCollector;
use DebugBar\DebugBar;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\StreamFactory;
use Psr\Container\ContainerInterface;

return [
    DebugBar::class => function (ContainerInterface $container) {
        $debugbar = new DebugBar();

        $renderer = $debugbar->getJavascriptRenderer();
        $renderer->setOpenHandlerUrl('/.php-debugbar/debugbar-open');

        $debugbar->addCollector((new RequestDataCollector())->useHtmlVarDumper());
        $debugbar->addCollector(new MemoryCollector());
        $debugbar->addCollector($container->get(MessagesCollector::class));
        $debugbar->addCollector($container->get(TimeDataCollector::class));
        $debugbar->addCollector($container->get(DatabaseCollector::class));
        $debugbar->addCollector(new ConfigCollector(['foo' => 'bar'], 'AppConfig'));
        $debugbar->addCollector(new SettingDataCollector());
        $debugbar->addCollector($container->get(ExceptionsCollector::class));

        $debugbar->setStorage(new GarbageableFileStorage(__DIR__ . '/../../data/'));

        $debugbar->addCollector(new ServiceWorkerCollector());
        $renderer->setBindAjaxHandlerToFetch(false);
        $renderer->setBindAjaxHandlerToJquery(false);
        $renderer->setBindAjaxHandlerToXHR(false);

        return $debugbar;
    },

    DatabaseCollector::class => function () {
        return (new DatabaseCollector(__DIR__ . '/../'))->setBacktraceLimit(4)->useExplain(true);
    },

    DebugBarMiddleware::class => function (DebugBar $debugbar) {
        $middleware = new DebugBarMiddleware($debugbar, new ResponseFactory(), new StreamFactory());
        $middleware->useHttpCache(true);
        $middleware->setBaseUrl('/.phpdebugbar');
        return $middleware;
    },
];
