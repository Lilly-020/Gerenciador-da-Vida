// Minimal service worker: exists mainly to satisfy the browser's PWA
// "installable" criteria (Chrome/Android requires a fetch handler).
//
// This app is server-rendered and session/CSRF-driven, so we deliberately
// do NOT cache HTML pages or non-GET requests — a stale cached page could
// carry an expired CSRF token and break form submissions. We only cache
// static build assets (hashed filenames, safe to cache aggressively) and
// fall back to the network for everything else.

const CACHE_NAME = 'gerenciador-static-v1';

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))),
        ),
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);
    const isStaticAsset = url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/');

    if (!isStaticAsset) {
        return;
    }

    event.respondWith(
        caches.open(CACHE_NAME).then(async (cache) => {
            const cached = await cache.match(request);
            if (cached) {
                return cached;
            }

            const response = await fetch(request);
            if (response.ok) {
                cache.put(request, response.clone());
            }
            return response;
        }),
    );
});
