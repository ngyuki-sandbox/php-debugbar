<?php
declare(strict_types = 1);

namespace App\Component\DebugBar\Middleware;

use DateTimeImmutable;
use DateTimeZone;
use DebugBar\DebugBar;
use DebugBar\OpenHandler;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DebugbarMiddleware implements MiddlewareInterface
{
    /**
     * @var DebugBar
     */
    private $debugbar;

    /**
     * @var ResponseFactoryInterface
     */
    private $responseFactory;

    /**
     * @var StreamFactoryInterface
     */
    private $streamFactory;

    /**
     * @var bool
     */
    private $useHttpCache = true;

    /**
     * @var bool
     */
    private $useRedirectCapture = true;

    /**
     * @var bool
     */
    private $useAjaxCapture = true;

    public function __construct(
        DebugBar $debugbar,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory
    ) {
        $this->debugbar = $debugbar;
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
    }

    public function useHttpCache(bool $useHttpCache)
    {
        $this->useHttpCache = $useHttpCache;
        return $this;
    }

    public function useRedirectCapture(bool $useRedirectCapture)
    {
        $this->useRedirectCapture = $useRedirectCapture;
        return $this;
    }

    public function useAjaxCapture(bool $useAjaxCapture)
    {
        $this->useAjaxCapture = $useAjaxCapture;
        return $this;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $res = $this->handleAsset($request);
        if ($res) {
            return $res;
        }

        $res = $this->handleOpen($request);
        if ($res) {
            return $res;
        }

        $response = $handler->handle($request);

        if ($this->useRedirectCapture) {
            $res = $this->handleRedirect($response);
            if ($res) {
                return $res;
            }
        }

        if ($this->useAjaxCapture) {
            $res = $this->handleAjax($request, $response);
            if ($res) {
                return $res;
            }
        }

        $res = $this->handleHtml($response);
        if ($res) {
            return $res;
        }

        return $response;
    }

    private function handleAsset(ServerRequestInterface $request): ?ResponseInterface
    {
        $renderer = $this->debugbar->getJavascriptRenderer();

        $path = $request->getUri()->getPath();
        $base = $renderer->getBaseUrl();
        if (substr($path, 0, strlen($base)) === $base) {
            $file = $renderer->getBasePath() . substr($path, strlen($base));
            if (is_file($file)) {
                return $this->generateAssetResponse($file);
            }
        }

        list($cssUrls, $jsUrls) = $renderer->getAssets(null, $renderer::RELATIVE_URL);
        if (in_array($path, $cssUrls, true) || in_array($path, $jsUrls, true)) {
            list($cssPaths, $jsPaths) = $renderer->getAssets(null, $renderer::RELATIVE_PATH);
            $cssMap = array_combine($cssUrls, $cssPaths);
            $jsMap = array_combine($jsUrls, $jsPaths);
            $file = $cssMap[$path] ?? $jsMap[$path];
            if (is_file($file)) {
                return $this->generateAssetResponse($file);
            }
        }

        return null;
    }

    public function generateAssetResponse(string $file): ResponseInterface
    {
        static $mimes = [
            'js' => 'application/javascript',
            'css' => 'text/css',
        ];

        $response = $this->responseFactory->createResponse();
        $response->getBody()->write(file_get_contents($file));

        $extension = pathinfo($file, PATHINFO_EXTENSION);
        if (isset($mimes[$extension])) {
            $response = $response->withHeader('Content-Type', $mimes[$extension]);
        }

        if ($this->useHttpCache) {
            $response = $response->withHeader('Cache-Control', 'max-age=86400, public');
            $date = (new DateTimeImmutable('+1 day'))->setTimezone(new DateTimeZone('UTC'));
            $response = $response->withHeader('Expires', $date->format('D, d M Y H:i:s') . ' GMT');
            $response = $response->withoutHeader('Pragma');
        }

        return $response;
    }

    private function handleOpen(ServerRequestInterface $request): ?ResponseInterface
    {
        $renderer = $this->debugbar->getJavascriptRenderer();
        $renderer->setOpenHandlerUrl('/vendor/ngyuki/php-debugbar/debugbar-open');

        $path = $request->getUri()->getPath();
        if ($path === $renderer->getOpenHandlerUrl()) {
            $openHandler = new OpenHandler($this->debugbar);
            $html = $openHandler->handle(null, false, false);
            $response = $this->responseFactory->createResponse();
            $response->getBody()->write($html);
            return $response->withHeader('Content-Type', 'application/json');
        }

        return null;
    }

    private function handleRedirect(ResponseInterface $response): ?ResponseInterface
    {
        if (in_array((int)$response->getStatusCode(), [301, 302, 303, 307], true)) {
            if ($this->debugbar->getHttpDriver()->isSessionStarted()) {
                $this->debugbar->stackData();
                return $response;
            }
        }
        return null;
    }

    private function handleAjax(ServerRequestInterface $request, ResponseInterface $response): ?ResponseInterface
    {
        if (strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest') {
            if ($this->debugbar->isDataPersisted()) {
                $this->debugbar->getData();
                $response = $response->withHeader('phpdebugbar-id', $this->debugbar->getCurrentRequestId());
            } else {
                $headers = $this->debugbar->getDataAsHeaders();
                foreach ($headers as $name => $value) {
                    $response = $response->withHeader($name, $value);
                }
            }
            return $response;
        }
        return null;
    }

    private function handleHtml(ResponseInterface $response): ?ResponseInterface
    {
        if (substr($response->getHeaderLine('Content-Type'), 0, strlen('text/html')) === 'text/html') {
            $renderer = $this->debugbar->getJavascriptRenderer();
            $html = (string)$response->getBody();
            $html = str_replace('</body>', $renderer->renderHead() . $renderer->render() . '</body>', $html);
            $body = $this->streamFactory->createStream();
            $body->write($html);
            return $response->withBody($body)->withHeader('Content-Length', strlen($html));
        }
        return null;
    }
}
