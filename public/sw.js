// AwesomeKorean Service Worker v6

// ── Firebase Cloud Messaging ──────────────────────────────────────
try {
  importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js');
  importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-messaging-compat.js');
  // Firebase 웹 설정은 관리자 > API 키 관리에 입력한 값을 서버에서 받아 쓴다 (예전엔 여기에 옛 프로젝트 값이 박혀 있었음).
  // 알림 표시는 아래 push 이벤트가 직접 처리하므로(data-only 메시지), 설정을 받기 전에 푸시가 와도 문제없다.
  fetch('/api/push/config').then(r => r.json()).then(c => {
    if (c && c.enabled && c.config && !firebase.apps.length) {
      firebase.initializeApp(c.config);
      firebase.messaging();
    }
  }).catch(() => {});
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

// fetch 핸들러는 "설치 가능한 앱(PWA)" 조건을 맞추기 위한 빈 통과용.
// respondWith 를 호출하지 않으므로 모든 요청은 브라우저가 네트워크로 직접 처리 (캐시 없음)
self.addEventListener('fetch', () => {});
// (이전 캐시 문제로 사이트 안 열리는 현상 방지)

// ── 푸시 알림 수신 ──────────────────────────────────────────────
self.addEventListener('push', (event) => {
  console.log('[SW] Push received!', event.data?.text()?.substring(0, 100));
  if (!event.data) return;
  let data = {};
  try { data = event.data.json(); } catch { data = { title: 'AwesomeKorean', body: event.data.text() }; }

  // data-only 메시지: payload가 data 안에 있거나 최상위에 있을 수 있음
  const payload = data.data || data;

  if (payload.type === 'incoming_call') {
    event.waitUntil(
      self.registration.showNotification(payload.title || '전화 수신', {
        body: payload.body || '안심 서비스 음성 통화 수신 중...',
        icon: '/storage/branding/icon-192x192.png',
        badge: '/storage/branding/icon-72x72.png',
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
      self.registration.showNotification(payload.sender_name || '새 메시지', {
        body: payload.body || '',
        icon: '/storage/branding/icon-192x192.png',
        tag: 'message-' + (payload.conversation_id || ''),
        renotify: true,
        data: payload,
      })
    );
    return;
  }

  event.waitUntil(
    self.registration.showNotification(payload.title || data.title || 'AwesomeKorean', {
      body: payload.body || data.body || '새 알림이 있습니다',
      icon: '/storage/branding/icon-192x192.png',
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
