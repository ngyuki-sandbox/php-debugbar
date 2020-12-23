<?php
declare(strict_types = 1);

namespace App\Component\DebugBar\DataCollector;

use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\DataCollectorInterface;

/**
 * ServiceWorkerCollector
 *
 * Use ServiceWorker to display ajax and Download.
 *
 *      $debugbar->addCollector(new ServiceWorkerCollector());
 *      $debugbar->getJavascriptRenderer()->setBindAjaxHandlerToFetch(false);
 *      $debugbar->getJavascriptRenderer()->setBindAjaxHandlerToJquery(false);
 *      $debugbar->getJavascriptRenderer()->setBindAjaxHandlerToXHR(false);
 */
class ServiceWorkerCollector implements DataCollectorInterface, AssetProvider
{
    /**
     * @var string[]
     */
    private $types;

    public function __construct(array $types = ['ajax', 'attachment'])
    {
        $this->types = $types;
    }

    public function collect()
    {
        return [];
    }

    public function getName()
    {
        return 'service-worker';
    }

    public function getAssets()
    {
        return [
            'base_path' => __DIR__ . '/../Resources/service-worker',
            'base_url'  => '/vendor/ngyuki/php-debugbar/service-worker',
            'js'        => 'php-debugbar-sw.js',
            'inline_js' => $this->getInlineJs(),
        ];
    }

    private function getInlineJs()
    {
        $types = json_encode($this->types);
        return <<<EOS
            (function(){
                const types = $types;
                navigator.serviceWorker.register('/vendor/ngyuki/php-debugbar/service-worker/php-debugbar-sw.js', {scope: '/'});
                navigator.serviceWorker.addEventListener('message', ev => {
                    const data = ev.data.phpdebugbar;
                    if (data && types.includes(data['phpdebugbar-type'])) {
                        console.log(`[phpdebugbar-sw] data`, data);
                        const response = new Response()
                        for (const [name, value] of Object.entries(data)) {
                            response.headers.append(name, value);
                        }
                        if (!phpdebugbar.ajaxHandler.loadFromId(response)) {
                            phpdebugbar.ajaxHandler.loadFromData(response);
                        }
                    }
                });
            }())
EOS;
    }
}
