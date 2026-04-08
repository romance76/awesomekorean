// SomeKorean Service Worker v6

// ── Firebase Cloud Messaging ──────────────────────────────────────
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
  console.warn('[SW] Firebase init skipped:', e.message);
}

// ── 캐시 비활성화 (SPA는 캐시 불필요 — Vite가 해시로 관리) ───────
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(keys.map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

// fetch 가로채기 없음 — 모든 요청은 네트워크 직접
// (이전 캐시 문제로 사이트 안 열리는 현상 방지)

// ── 푸시 알림 수신 ──────────────────────────────────────────────
self.addEventListener('push', (event) => {
  if (!event.data) return;
  let data = {};
  try { data = event.data.json(); } catch { data = { title: 'SomeKorean', body: event.data.text() }; }

  const notification = data.notification || {};
  const payload = data.data || {};

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

  event.waitUntil(
    self.registration.showNotification(notification.title || data.title || 'SomeKorean', {
      body: notification.body || data.body || '새 알림이 있습니다',
      icon: '/images/icons/icon-192.png',
      vibrate: [200, 100, 200],
      data: payload,
    })
  );
});

// ── 알림 클릭 ───────────────────────────────────────────────────
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const payload = event.notification.data || {};

  if (payload.type === 'incoming_call' && event.action === 'decline') {
    if (payload.call_id) fetch('/api/comms/calls/' + payload.call_id + '/end', { method: 'POST' });
    return;
  }

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(clientList => {
      for (const client of clientList) {
        if (client.url.includes(self.location.origin) && 'focus' in client) {
          client.postMessage({ type: 'NOTIFICATION_CLICK', payload });
          return client.focus();
        }
      }
      return clients.openWindow(payload.url || '/');
    })
  );
});
