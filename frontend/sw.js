const CACHE_NAME = 'hr-dfx-v1';

// Files to cache for offline use (frontend only)
const STATIC_FILES = [
  '/',
  '/index.php',
  '/manifest.json',
  '/icon-192.png',
  '/icon-512.png'
];

// Install: cache static files
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      return cache.addAll(STATIC_FILES).catch(() => {
        // Silently fail if some files aren't available yet
      });
    })
  );
  self.skipWaiting();
});

// Activate: clean up old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
    )
  );
  self.clients.claim();
});

// Fetch: network first, fall back to cache for frontend files
// Always go to network for API calls (/parse-to-json, /pdf-to-dxf, etc.)
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // API calls — always go to network, never cache
  if (url.port === '8000' || url.pathname.startsWith('/pdf-to-dxf') ||
      url.pathname.startsWith('/parse-to-json') || url.pathname.startsWith('/export') ||
      url.pathname.startsWith('/download')) {
    event.respondWith(fetch(event.request));
    return;
  }

  // Frontend files — network first, cache fallback
  event.respondWith(
    fetch(event.request)
      .then(response => {
        // Cache successful responses
        if (response && response.status === 200) {
          const clone = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
        }
        return response;
      })
      .catch(() => caches.match(event.request))
  );
});
