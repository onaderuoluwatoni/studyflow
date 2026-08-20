// StudyFlow service worker — light-touch caching. This intentionally does
// NOT try to cache PHP pages (they're dynamic and often need a fresh
// session), only static, unchanging assets like CSS/logo/icons. That
// keeps things fast without ever serving a stale dashboard or stale
// quiz/leaderboard data.
const CACHE_NAME = 'studyflow-static-v1';
const STATIC_ASSETS = [
    'assets/css/style.css',
    'assets/img/logo.svg',
    'assets/img/icons/icon-192.png',
    'assets/img/icons/icon-512.png',
    'manifest.json',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS)).catch(() => {})
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Only handle GET requests for same-origin static assets.
    if (event.request.method !== 'GET' || url.origin !== self.location.origin) return;

    const isStatic = STATIC_ASSETS.some((path) => url.pathname.endsWith(path));
    if (!isStatic) return; // let everything else (PHP pages, AJAX) go straight to the network

    event.respondWith(
        caches.match(event.request).then((cached) => {
            const networkFetch = fetch(event.request)
                .then((response) => {
                    const copy = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy));
                    return response;
                })
                .catch(() => cached);
            return cached || networkFetch;
        })
    );
});
