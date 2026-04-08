// ── Firebase Cloud Messaging (백그라운드 푸시) ──────────────────
// try/catch로 감싸서 Firebase 로드 실패해도 SW가 죽지 않도록
try {
  importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js');
  importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-messaging-compat.js');
  firebase.initializeApp({
    apiKey: 'AIzaSyAOfIdUvVXqblgb7NrmPGWViIawuZNpDTA',
    projectId: 'somekorean-c9430',
    messagingSenderId: '430136797121',
    appId: '1:430136797121:web:768cffa39c96a35e81f140',
  });
  firebase.messaging();
} catch (e) {
  console.warn('[SW] Firebase init failed (non-fatal):', e);
}

const CACHE_NAME = 'somekorean-v4';
const STATIC_ASSETS = ['/', '/manifest.json'];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS))
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
  if (event.request.url.includes('/api/')) return;
  event.respondWith(
    fetch(event.request).catch(() => caches.match(event.request))
  );
});

// 푸시 알림 수신 (안심 전화/메시지 포함)
self.addEventListener('push', (event) => {
  if (!event.data) return;
  let data = {};
  try { data = event.data.json(); } catch { data = { title: 'SomeKorean', body: event.data.text() }; }

  const notification = data.notification || {};
  const payload = data.data || {};

  // 안심 전화 수신
  if (payload.type === 'incoming_call') {
    event.waitUntil(
      self.registration.showNotification(notification.title || '전화 수신', {
        body: notification.body || '안심 서비스 음성 통화 수신 중...',
        icon: '/images/icons/icon-192.png',
        badge: '/images/icons/icon-72.png',
        vibrate: [500, 200, 500, 200, 500],
        tag: 'incoming-call',
        renotify: true,
        requireInteraction: true,
        data: payload,
        actions: [
          { action: 'answer', title: '수락' },
          { action: 'decline', title: '거절' },
        ],
      })
    );
    return;
  }

  // 안심 메시지 수신
  if (payload.type === 'new_message') {
    event.waitUntil(
      self.registration.showNotification(notification.title || '새 메시지', {
        body: notification.body || '',
        icon: '/images/icons/icon-192.png',
        tag: 'message-' + (payload.conversation_id || ''),
        renotify: true,
        data: payload,
      })
    );
    return;
  }

  // 일반 알림
  event.waitUntil(
    self.registration.showNotification(notification.title || data.title || 'SomeKorean', {
      body: notification.body || data.body || '새 알림이 있습니다',
      icon: '/images/icons/icon-192.png',
      vibrate: [200, 100, 200],
      data: payload,
    })
  );
});

// 알림 클릭
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const payload = event.notification.data || {};

  // 전화 거절
  if (payload.type === 'incoming_call' && event.action === 'decline') {
    if (payload.call_id) fetch('/api/comms/calls/' + payload.call_id + '/end', { method: 'POST' });
    return;
  }

  const targetUrl = payload.url || '/';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(clientList => {
      for (const client of clientList) {
        if (client.url.includes(self.location.origin) && 'focus' in client) {
          // 앱이 열려있으면 메시지 전달
          client.postMessage({ type: 'NOTIFICATION_CLICK', payload });
          return client.focus();
        }
      }
      return clients.openWindow(targetUrl);
    })
  );
});
