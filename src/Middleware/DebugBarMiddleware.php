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

/**
 * DebugBarMiddleware
 *
 * 既に次のようなパッケージが存在していましたが、
 * それぞれ記載の通り制約があったため新たに実装しました
 *
 *      - https://github.com/middlewares/debugbar
 *          - Ajax で OpenHandlerUrl が使えない
 *      - https://github.com/php-middleware/phpdebugbar
 *          - リダイレクトも Ajax も対応していない
 */
class DebugBarMiddleware implements MiddlewareInterface
{
    /**
     * @var string
     */
    private $baseUrl = '/.phpdebugbar';

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

    public function __construct(
        DebugBar $debugbar,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory
    ) {
        $this->debugbar = $debugbar;
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
    }

    public function setBaseUrl(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        return $this;
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
            // リダイレクト時はヘッダーを付与しても ServiceWorker で扱えない
            // （fetch に { redirect: 'manual' } を付与すればできるのかもしれない）
            $res = $this->handleRedirect($response);
            if ($res) {
                return $res;
            }
        }

        $response = $this->injectHeader($response);
        $response = $this->injectHtml($response);
        return $response;
    }

    private function handleAsset(ServerRequestInterface $request): ?ResponseInterface
    {
        $renderer = $this->debugbar->getJavascriptRenderer();
        $path = $request->getUri()->getPath();

        // 例にあげた既存のパッケージでは `JavascriptRenderer::getBaseUrl()` と RequestURI を比較してアセットを返している。
        // `/vendor/maximebf/debugbar/` の中にあるアセットならそれで十分だけど、サードパーティのアセットはそれではロードできない。
        //
        // `dumpCssAssets()` と `dumpJsAssets()` を使えばすべてのサードパーティも含めたすべてのアセットがサポートできる・・
        // と思いきや、CSS で fontawesome のフォントが参照されており、それはアセット定義に含まれないので `dumpCssAssets()` では出力されない。
        //
        // フォントが Data スキームで埋め込まれていれば大丈夫なので AsseticCollection でフォントを CSS に埋め込んでしまえば・・
        // と思ったけど `url('../fonts/fontawesome-webfont.eot?v=4.7.0')` みたいなクエリを含む形式で参照されると埋め込めないっぽい。
        //
        // よって、以下の両方を独自に実装して対応する。
        //
        //   - `JavascriptRenderer::getBaseUrl()` と RequestURI を比較
        //   - `getAssets()` でアセットのURLとパスを取得して URL -> パス のマッピングを作る

        $base = $this->baseUrl . $renderer->getBaseUrl();
        if (substr($path, 0, strlen($base)) === $base) {
            $file = $renderer->getBasePath() . substr($path, strlen($base));
            if (is_file($file)) {
                return $this->generateAssetResponse($file);
            }
        }

        list($cssUrls, $jsUrls) = $this->getAssets();
        if (in_array($path, $cssUrls, true) || in_array($path, $jsUrls, true)) {
            // @phan-suppress-next-line PhanTypeMismatchArgumentProbablyReal
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
            'woff2' => 'application/font-woff2',
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

        return $response->withHeader('Service-Worker-Allowed', '/');
    }

    private function handleOpen(ServerRequestInterface $request): ?ResponseInterface
    {
        $renderer = $this->debugbar->getJavascriptRenderer();
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

    private function injectHeader(ResponseInterface $response): ResponseInterface
    {
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

    private function injectHtml(ResponseInterface $response): ResponseInterface
    {
        if (substr($response->getHeaderLine('Content-Type'), 0, strlen('text/html')) === 'text/html') {
            $html = (string)$response->getBody();
            $html = str_replace('</body>', $this->renderHead() . $this->renderBody() . '</body>', $html);
            $body = $this->streamFactory->createStream();
            $body->write($html);
            return $response->withBody($body)->withHeader('Content-Length', (string)strlen($html));
        }
        return $response;
    }

    private function getAssets(): array
    {
        $renderer = $this->debugbar->getJavascriptRenderer();
        // @phan-suppress-next-line PhanTypeMismatchArgumentProbablyReal
        list($cssFiles, $jsFiles, $inlineCss, $inlineJs, $inlineHead) = $renderer->getAssets(null, $renderer::RELATIVE_URL);
        $cssFiles = array_map(function ($file) {
            return $this->baseUrl . $file;
        }, $cssFiles);
        $jsFiles = array_map(function ($file) {
            return $this->baseUrl . $file;
        }, $jsFiles);
        return [$cssFiles, $jsFiles, $inlineCss, $inlineJs, $inlineHead];
    }

    public function renderHead()
    {
        // getAssets() で個別にファイルをロードするよりも dumpJsAssets() などでひとつのファイルにまとめてロードするほうが良いかも
        // ただ ServiceWorker はそれではロード出来ないので ServiceWorker のスクリプトだけはどうにか個別に読ませる必要がある
        // Collector に ExternalAssetProvider みたいなインタフェースを設けて dump ではロードできないアセットのリストを返すようにするか
        //
        // そもそも ServiceWorker を Collector ではなくこの Middleware で処理すれば良いかも
        // Collector にしているのはアセットのロードをどうにかするためであって、
        // 別に ServiceWorker は Collector である必要は無い

        $html = [];

        $renderer = $this->debugbar->getJavascriptRenderer();
        list($cssFiles, $jsFiles, $inlineCss, $inlineJs, $inlineHead) = $this->getAssets();

        foreach ($cssFiles as $file) {
            $html[] = sprintf('<link rel="stylesheet" type="text/css" href="%s">', $file);
        }

        foreach ($inlineCss as $content) {
            $html[] = sprintf('<style>%s</style>', $content);
        }

        foreach ($jsFiles as $file) {
            $html[] = sprintf('<script type="text/javascript" src="%s"></script>', $file);
        }

        foreach ($inlineJs as $content) {
            $html[] = sprintf('<script type="text/javascript">%s</script>', $content);
        }

        foreach ($inlineHead as $content) {
            $html[] = $content;
        }

        if ($renderer->isJqueryNoConflictEnabled()) {
            $html[] = '<script type="text/javascript">jQuery.noConflict(true);</script>';
        }

        return implode("\n", $html);
    }

    public function renderBody()
    {
        $renderer = $this->debugbar->getJavascriptRenderer();
        return $renderer->render();
    }
}
