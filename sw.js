// sw.js — Service Worker for Olievenhoutbosch Digital Hub
// Caches visited pages so they work offline

const CACHE_NAME = 'olieven-hub-v1';
const STATIC_ASSETS = [
    '/',
    '/index.php',
    '/main.php',
    '/login.php',
    '/register.php',
    '/offline.html',      // Fallback page
    '/styles.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'
];

// Install: Cache static assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('Caching static assets');
                return cache.addAll(STATIC_ASSETS);
            })
            .then(() => self.skipWaiting())
    );
});

// Activate: Clean up old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames
                    .filter((name) => name !== CACHE_NAME)
                    .map((name) => caches.delete(name))
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch: Serve from cache, or fetch and cache
self.addEventListener('fetch', (event) => {
    // Skip non-GET requests
    if (event.request.method !== 'GET') return;
    
    // Skip cross-origin requests (like Google Fonts, external APIs)
    const url = new URL(event.request.url);
    if (url.origin !== location.origin && !url.hostname.includes('cdn')) {
        return;
    }

    event.respondWith(
        caches.match(event.request).then((cachedResponse) => {
            // Return cached version if available
            if (cachedResponse) {
                return cachedResponse;
            }

            // Otherwise fetch from network
            return fetch(event.request).then((response) => {
                // Don't cache if not valid
                if (!response || response.status !== 200 || response.type === 'error') {
                    return response;
                }

                // Clone and cache the response
                const responseToCache = response.clone();
                caches.open(CACHE_NAME).then((cache) => {
                    cache.put(event.request, responseToCache);
                });

                return response;
            });
        }).catch(() => {
            // Network failed — show offline page for navigation
            if (event.request.mode === 'navigate') {
                return caches.match('/offline.html');
            }
        })
    );
});