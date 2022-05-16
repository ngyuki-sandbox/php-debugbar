<?php
declare(strict_types=1);

namespace App;

use Laminas\Diactoros\Response\HtmlResponse;

class Renderer
{
    public function __invoke(array $params = []): HtmlResponse
    {
        extract($params);
        ob_start();
        require __DIR__ . '/index.html.php';
        return new HtmlResponse(ob_get_clean());
    }
}
