<?php
declare(strict_types = 1);

namespace App\Component\DebugBar\DataCollector;

use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\DataCollectorInterface;

/**
 * ServiceWorkerCollector
 *
 * ServiceWorker を用いて本来であればトレースできないようなアクセスもデバッグバーに表示する。
 *
 * 例えば `Content-Disposition: attachment` によるダウンロードは素のままではデバッグバーに表示させられない。
 * ServiceWorker を使えばダウンロードのレスポンスも js で処理できる。
 * ダウンロードのレスポンスに `$debugbar->sendDataInHeaders(true);` でデバッグバー情報を入れて、
 * ServiceWorker で取り出して ページ側の js に通知する。
 * ページ側の js は ServiceWorker からの通知を元に Ajax を表示するのと同じ要領でデバッグバーにデータを追加する。
 *
 * ただし、ServiceWorker の中で [FetchEvent](https://developer.mozilla.org/ja/docs/Web/API/FetchEvent) で
 * ダウンロードの呼び出し元ページを得ることができなさそうなので（Firefox ならできるかもしれない・・未確認）、
 * ServiceWorker 管理下の、同時に開いているすべてのページのデバッグバーに表示される。通常あまり問題にはならないと思う。
 *
 * なお Ajax であれば ServiceWorker の中で呼び出し元のページが得られるので、
 * Ajax も jQuery や window.fetch をラップするようなダーティな方法ではなく ServiceWorker で処理することもできる。
 * その場合は次のように標準の Ajax ハンドラは無効にしておく。
 *
 *     $debugbar->addCollector(new ServiceWorkerCollector());
 *     $debugbar->getJavascriptRenderer()->setBindAjaxHandlerToFetch(false);
 *     $debugbar->getJavascriptRenderer()->setBindAjaxHandlerToJquery(false);
 *     $debugbar->getJavascriptRenderer()->setBindAjaxHandlerToXHR(false);
 *
 * また、デバッグバーのようなページ下部に無理やり表示する方法ではなく、
 * 別タブでデバッグコンソールのようなものを開いてその画面にデバッグ情報を集約することもできます。
 * デバッグコンソールの画面は \App\Component\DebugBar\Utils\ConsoleRenderer でレンダリングできます。
 * API 専用のバックエンドだと Web 画面が存在しないため、この方法で専用のコンソール画面を用意するのがおすすめです。
 */
class ServiceWorkerCollector implements DataCollectorInterface, AssetProvider
{
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
            'base_path' => __DIR__ . '/../Resources',
            'base_url'  => '/vendor/ritz/php-debugbar/Resources',
            'js'        => [
                'service-worker/php-debugbar-sw.js',
                'service-worker/php-debugbar-sw-listener.js',
            ],
        ];
    }
}
