if (typeof window === 'undefined') {
    self.addEventListener('install', (event) => {
        console.log('[phpdebugbar-sw] Install debugbar service worker');
        event.waitUntil(self.skipWaiting());
    });

    self.addEventListener('activate', (event) => {
        console.log('[phpdebugbar-sw] Activate debugbar service worker');
        event.waitUntil(self.clients.claim());
    });

    self.addEventListener('fetch', (event) => {
        event.respondWith((async () => {
            const response = await fetch(event.request);
            let data = {};
            for (const [name, value] of response.headers.entries()) {
                if (name.startsWith('phpdebugbar')) {
                    data[name] = value;
                }
            }
            if (Object.keys(data).length === 0) {
                return response;
            }
            console.log(`[phpdebugbar-sw] fetch`, {id: event.clientId, url: event.request.url});
            const clients = await self.clients.matchAll({type: 'window'});
            for (const client of clients) {
                let ok = false;

                // Content-Disposition: attachment によるダウンロードであれば event.clientId は空
                // → すべてのクライアントに通知
                ok ||= !event.clientId;

                // Ajax であれば client.id と event.clientId が一致する
                // → そのクライアントのみに通知
                ok ||= client.id === event.clientId

                // DebugBarConsole → 常に通知
                ok ||= new URL(client.url).hash === '#php-debugbar-console';

                if (ok) {
                    client.postMessage({phpdebugbar: data});
                    console.log(`[phpdebugbar-sw] post`, {id: client.id, url: event.request.url});
                }
            }
            return response;
        })());
    });
}
