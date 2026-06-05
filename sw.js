// sw.js — Service Worker for Olievenhoutbosch Digital Hub
const CACHE_NAME = 'olieven-hub-v1';

const LOCAL_ASSETS = [
    '/',
    '/index.php',
    '/main.php',
    '/login.php',
    '/register.php',
    '/offline.html',
    '/styles.css'
];

const CDN_ASSETS = [
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'
];

// Install: Cache local assets (skip missing ones), CDN assets (best effort)
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                // Cache local assets — skip any that fail (404, missing, etc.)
                const localCache = Promise.allSettled(
                    LOCAL_ASSETS.map(url => 
                        cache.add(url).catch(err => {
                            console.warn('SW: Failed to cache local asset:', url, err.message);
                            return null; // Skip this one, don't break install
                        })
                    )
                );

                // Cache CDN assets — best effort, never fail install
                const cdnCache = Promise.allSettled(
                    CDN_ASSETS.map(url => 
                        cache.add(url).catch(err => {
                            console.warn('SW: Failed to cache CDN:', url, err.message);
                            return null;
                        })
                    )
                );

                return Promise.all([localCache, cdnCache]);
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

// Fetch: Smart strategy based on request type
self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;

    const url = new URL(event.request.url);

    // Skip cross-origin requests that aren't CDNs
    if (url.origin !== location.origin && !url.hostname.includes('cdn')) {
        return;
    }

    // PHP pages & navigation: Network first, cache fallback
    const isPHP = url.pathname.endsWith('.php') || url.pathname === '/';
    const isNavigate = event.request.mode === 'navigate';

    if (isPHP || isNavigate) {
        event.respondWith(
            fetch(event.request)
                .then((response) => {
                    // Update cache with fresh version
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, clone);
                    });
                    return response;
                })
                .catch(() => {
                    return caches.match(event.request).then((cached) => {
                        return cached || caches.match('/offline.html').then((offline) => {
                            return offline || new Response(
                                '<!DOCTYPE html><html><head><title>Offline</title></head><body style="font-family:sans-serif;text-align:center;padding:50px;"><h1>You are offline</h1><p>Please check your connection and try again.</p></body></html>',
                                { headers: { 'Content-Type': 'text/html' } }
                            );
                        });
                    });
                })
        );
        return;
    }

    // Static assets: Cache first, network fallback
    event.respondWith(
        caches.match(event.request).then((cached) => {
            if (cached) return cached;

            return fetch(event.request).then((response) => {
                if (!response || response.status !== 200) return response;

                const clone = response.clone();
                caches.open(CACHE_NAME).then((cache) => {
                    cache.put(event.request, clone);
                });
                return response;
            });
        })
    );
});