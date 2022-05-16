<?php
declare(strict_types = 1);

namespace App\Component\DebugBar\Middleware;

use DebugBar\DataCollector\ExceptionsCollector;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * DebugBarErrorMiddleware
 *
 * 例外を ExceptionsCollector に渡すミドルウェアです。
 * Application では次のようなパイプラインで例外を処理することが望ましいです。
 *
 * - debugbar を有効化/表示するミドルウェア
 *      - DebugBarMiddleware
 * - catch-all ですべての例外を拾うエラーハンドラのミドルウェア
 *      - 開発時なら WhoopsMiddleware など
 *      - 本番時なら ErrorMiddleware のようなもの
 * - catch-all で ExceptionsCollector に例外を追加して re-throw するミドルウェア
 *     - DebugBarErrorMiddleware
 */
class DebugBarExceptionsMiddleware implements MiddlewareInterface
{
    private ExceptionsCollector $exceptionsCollector;

    public function __construct(ExceptionsCollector $exceptionsCollector)
    {
        $this->exceptionsCollector = $exceptionsCollector;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $ex) {
            $this->exceptionsCollector->addThrowable($ex);
            throw $ex;
        }
    }
}
