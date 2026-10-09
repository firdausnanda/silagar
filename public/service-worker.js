const PAGE_CACHE = 'sensus-pages-v1';
const ASSET_CACHE = 'sensus-assets-v1';
const OFFLINE_CACHE = 'sensus-offline-v3';
const OFFLINE_PAGES = new Set(['/dashboard', '/input-sensus']);

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(OFFLINE_CACHE);
        await cache.add('/offline.html');
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const names = await caches.keys();
        await Promise.all(names
            .filter((name) => name.startsWith('sensus-') && ![PAGE_CACHE, ASSET_CACHE, OFFLINE_CACHE].includes(name))
            .map((name) => caches.delete(name)));
        await self.clients.claim();
    })());
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'PURGE_PRIVATE') {
        event.waitUntil(caches.delete(PAGE_CACHE).then(() => {
            event.ports[0]?.postMessage('done');
        }));
    }
});

function pageKey(request) {
    const url = new URL(request.url);
    url.search = '';
    if (request.headers.get('X-Inertia')) {
        url.searchParams.set('__offline_inertia', '1');
    }
    return new Request(url.toString());
}

async function networkFirstPage(request) {
    const cache = await caches.open(PAGE_CACHE);
    const key = pageKey(request);

    try {
        const response = await fetch(request);
        const responseUrl = new URL(response.url);
        const expectedType = request.headers.get('X-Inertia') ? 'application/json' : 'text/html';

        if (response.ok && responseUrl.pathname === new URL(request.url).pathname
            && response.headers.get('Content-Type')?.includes(expectedType)) {
            await cache.put(key, response.clone());
        }

        return response;
    } catch (error) {
        return (await cache.match(key)) ?? offlineResponse();
    }
}

async function offlineResponse() {
    return (await caches.match('/offline.html')) ?? Response.error();
}

async function cacheFirstAsset(request) {
    const cache = await caches.open(ASSET_CACHE);
    const cached = await cache.match(request);
    if (cached) {
        return cached;
    }

    const response = await fetch(request);
    if (response.ok) {
        await cache.put(request, response.clone());
    }
    return response;
}

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    if (request.method === 'POST' && url.pathname === '/logout') {
        event.respondWith(fetch(request).then(async (response) => {
            if (response.ok) {
                await caches.delete(PAGE_CACHE);
            }
            return response;
        }));
        return;
    }

    if (request.method !== 'GET') {
        return;
    }

    if (url.pathname === '/build/manifest.json') {
        return;
    }

    if (url.pathname.startsWith('/build/')) {
        event.respondWith(cacheFirstAsset(request));
    } else if (OFFLINE_PAGES.has(url.pathname)) {
        event.respondWith(networkFirstPage(request));
    } else if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(offlineResponse));
    }
});
