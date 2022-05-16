<?php
declare(strict_types=1);

namespace App;

use App\Component\DebugBar\Middleware\DebugBarExceptionsMiddleware;
use App\Component\DebugBar\Middleware\DebugBarMiddleware;
use DI\ContainerBuilder;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiStreamEmitter;
use Laminas\Stratigility\Middleware\CallableMiddlewareDecorator;
use Laminas\Stratigility\Middleware\NotFoundHandler;
use Laminas\Stratigility\MiddlewarePipe;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class App
{
    public static function init()
    {
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);

        $files = glob(__DIR__ . '/bootstrap/*.php');
        foreach ($files as $file) {
            $builder->addDefinitions(require $file);
        }

        $container = $builder->build();
        return new static($container);
    }

    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function run()
    {
        $responseFactory = $this->container->get(ResponseFactory::class);

        $routes = $this->container->get('routes');

        $pipeline = new MiddlewarePipe();

        $pipeline->pipe($this->container->get(DebugBarMiddleware::class));

        $pipeline->pipe(new CallableMiddlewareDecorator(function (
            ServerRequestInterface $request,
            RequestHandlerInterface $handler,
        ) : ResponseInterface {
            try {
                return $handler->handle($request);
            } catch (Throwable $ex) {
                $renderer = $this->container->get(Renderer::class);
                return $renderer(['exception' => $ex])->withStatus(500);
            }
        }));

        $pipeline->pipe($this->container->get(DebugBarExceptionsMiddleware::class));

        $pipeline->pipe(new CallableMiddlewareDecorator(function (
            ServerRequestInterface $request,
            RequestHandlerInterface $handler,
        ): ResponseInterface {
                session_start();
                $headers = headers_list();
                $response = $handler->handle($request);
                foreach ($headers as $header) {
                    list($name, $value) = explode(':', $header, 2);
                    header_remove($name);
                    $response = $response->withHeader($name, $value);
                }

                return $response;
            })
        );

        $pipeline->pipe(new CallableMiddlewareDecorator(
            function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($routes): ResponseInterface {
                $method = strtolower($request->getMethod());
                $path = $request->getUri()->getPath();
                if (isset($routes[$method][$path])) {
                    return ($routes[$method][$path])($request, $handler);
                }
                return $handler->handle($request);
            }
        ));

        $pipeline->pipe(new NotFoundHandler(function () use ($responseFactory) {
            return $responseFactory->createResponse();
        }));

        $request = ServerRequestFactory::fromGlobals();
        $response = $pipeline->handle($request);
        $emitter = new SapiStreamEmitter();
        $emitter->emit($response);

    }
}
