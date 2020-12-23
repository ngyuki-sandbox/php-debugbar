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
            const clients = event.clientId ?
                [await self.clients.get(event.clientId)] :
                await self.clients.matchAll({type: 'window'});
            for (const client of clients) {
                client.postMessage({phpdebugbar: data});
                console.log(`[phpdebugbar-sw] post`, { id: client.id, url: event.request.url });
            }
            return response;
        })());
    });
}
