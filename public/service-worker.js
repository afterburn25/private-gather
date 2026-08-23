const CACHE = 'private-gather-redesign-static-v4';
const STATIC_FILES = [
  './assets/app.css',
  './assets/redesign.css',
  './assets/redesign-compat.css',
  './assets/platform-themes.css',
  './assets/tenant-themes.css',
  './assets/product-completion.css',
  './assets/redesign.js',
  './assets/branding/private-gather-logo.png',
  './offline.html'
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(STATIC_FILES)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((key) => key !== CACHE && key.startsWith('private-gather-redesign-static-')).map((key) => caches.delete(key))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') return;
  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  if (request.mode === 'navigate') {
    // Never cache authenticated HTML. Navigation remains network-first and the
    // offline page contains no member, event, location, or account data.
    event.respondWith(fetch(request).catch(() => caches.match('./offline.html')));
    return;
  }

  const scope = new URL(self.registration.scope);
  const relative = './' + url.pathname.slice(scope.pathname.length);
  if (!STATIC_FILES.includes(relative)) return;
  event.respondWith(caches.match(request).then((cached) => cached || fetch(request)));
});

self.addEventListener('push', (event) => {
  let payload = {};
  try { payload = event.data ? event.data.json() : {}; } catch (_) { payload = {}; }

  // Push payloads should remain deliberately generic on a lock screen. Rich or
  // sensitive community/event context belongs behind the authenticated app.
  const title = typeof payload.title === 'string' && payload.title.trim() ? payload.title.trim().slice(0, 80) : 'Private Gather';
  const body = typeof payload.body === 'string' && payload.body.trim() ? payload.body.trim().slice(0, 180) : 'You have a new Private Gather update.';
  const requestedUrl = typeof payload.url === 'string' ? payload.url : './notifications';
  const target = new URL(requestedUrl, self.registration.scope);
  const scope = new URL(self.registration.scope);
  const safeUrl = target.origin === scope.origin && target.pathname.startsWith(scope.pathname) ? target.href : new URL('./notifications', scope).href;

  event.waitUntil(self.registration.showNotification(title, {
    body,
    icon: new URL('./assets/branding/private-gather-logo.png', scope).href,
    badge: new URL('./assets/branding/private-gather-logo.png', scope).href,
    tag: typeof payload.tag === 'string' ? payload.tag.slice(0, 120) : 'private-gather-update',
    renotify: false,
    data: { url: safeUrl },
  }));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const scope = new URL(self.registration.scope);
  const target = new URL(event.notification.data?.url || './notifications', scope);
  const safeUrl = target.origin === scope.origin && target.pathname.startsWith(scope.pathname) ? target.href : new URL('./notifications', scope).href;
  event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
    const existing = windows.find((client) => client.url.startsWith(scope.origin) && 'focus' in client);
    if (existing) {
      existing.navigate(safeUrl);
      return existing.focus();
    }
    return clients.openWindow ? clients.openWindow(safeUrl) : undefined;
  }));
});
