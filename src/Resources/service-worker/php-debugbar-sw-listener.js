requestAnimationFrame(function(){
    const scripts = document.getElementsByTagName('script');
    for (const script of scripts) {
        const src = script.getAttribute('src');
        // PHP 側で次のように BaseUrl を変更していると ServiceWorker のスクリプトの URL も変わるためベタ書きは出来ない
        //
        //   $renderer->setBaseUrl('/.phpdebugbar');
        //
        // ので、すべてのスクリプトから src がサフィックスで一致するものを探してそれを ServiceWorker として登録する
        if (src && src.endsWith('/vendor/ritz/php-debugbar/Resources/service-worker/php-debugbar-sw.js')) {
            navigator.serviceWorker.register(src, {scope: '/'});
            break;
        }
    }
    navigator.serviceWorker.addEventListener('message', ev => {
        const data = ev.data.phpdebugbar;
        console.log(`[phpdebugbar-sw] data`, data);
        if (data) {
            const response = new Response()
            for (const [name, value] of Object.entries(data)) {
                response.headers.append(name, value);
            }
            if (!phpdebugbar.ajaxHandler.loadFromId(response)) {
                phpdebugbar.ajaxHandler.loadFromData(response);
            }
        }
    });
});
