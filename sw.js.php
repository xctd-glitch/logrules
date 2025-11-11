<?php

declare(strict_types=1);

header('Content-Type: application/javascript; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$cacheName = 'srp-cache-v2';
$precache = [
    './',
    './index.php',
    './realtime.php',
    './offline.php',
    './manifest.webmanifest',
    './assets/icons/icon-192.svg',
    './assets/icons/icon-512.svg',
];

printf("const CACHE_VERSION = '%s';\n", addslashes($cacheName));
printf("const PRECACHE_URLS = %s;\n", json_encode($precache, JSON_UNESCAPED_SLASHES));
echo "const OFFLINE_FALLBACK = './offline.php';\n";

echo <<<'JS'
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_VERSION).then((cache) => cache.addAll(PRECACHE_URLS))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((key) => key !== CACHE_VERSION).map((key) => caches.delete(key)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') {
    return;
  }

  const requestUrl = new URL(request.url);

  if (requestUrl.origin !== self.location.origin) {
    return;
  }

  if (
    requestUrl.pathname.endsWith('data.php') ||
    requestUrl.pathname.endsWith('clicks.php') ||
    requestUrl.pathname.endsWith('api.php') ||
    requestUrl.pathname.endsWith('decision.php') ||
    requestUrl.pathname.endsWith('sw.js.php')
  ) {
    return;
  }

  if (request.mode === 'navigate' || request.destination === 'document') {
    event.respondWith(
      fetch(request)
        .then((response) => {
          if (response && response.status === 200) {
            const clone = response.clone();
            caches.open(CACHE_VERSION).then((cache) => cache.put(request, clone));
          }
          return response;
        })
        .catch(() =>
          caches.match(request).then((cached) => cached || caches.match(OFFLINE_FALLBACK))
        )
    );
    return;
  }

  const isStaticAsset = /\.(?:css|js|png|svg|webmanifest|ico)$/i.test(requestUrl.pathname);

  event.respondWith(
    caches.match(request).then((cached) => {
      if (cached) {
        return cached;
      }

      return fetch(request)
        .then((response) => {
          if (isStaticAsset && response && response.status === 200) {
            const clone = response.clone();
            caches.open(CACHE_VERSION).then((cache) => cache.put(request, clone));
          }
          return response;
        })
        .catch(() => caches.match(OFFLINE_FALLBACK));
    })
  );
});
JS;
