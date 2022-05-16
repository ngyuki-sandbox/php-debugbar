<?php
declare(strict_types = 1);

namespace App\Component\DebugBar\Utils;

use App\Component\DebugBar\Middleware\DebugBarMiddleware;

class ConsoleRenderer
{
    private DebugBarMiddleware $middleware;

    public function __construct(DebugBarMiddleware $middleware)
    {
        $this->middleware = $middleware;
    }

    public function render()
    {
        ob_start();
        try {
            require __DIR__ . '/../Resources/template/debugbar-console.php';
            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
