/**
 * PushService — Firebase Cloud Messaging (FCM) Web Push
 *
 * 1. Firebase 초기화
 * 2. FCM 토큰 발급 (VAPID key)
 * 3. 서버에 토큰 등록
 * 4. 포그라운드 메시지 처리
 */

import { initializeApp } from 'firebase/app'
import { getMessaging, getToken, onMessage } from 'firebase/messaging'
import axios from 'axios'

let messaging = null

// Firebase 웹 설정은 관리자 > API 키 관리에서 입력한 값을 서버(/api/push/config)에서 받아 쓴다 —
// 예전처럼 빌드 시점 환경변수에 박아 두면 값을 바꿀 때마다 사이트를 새로 만들어야 했음.
// (빌드 환경변수가 있으면 그것도 대체 수단으로 사용)
let firebaseConfig = null
let VAPID_KEY = null

async function loadPushConfig() {
  try {
    const { data } = await axios.get('/api/push/config')
    if (data?.enabled && data.config) { firebaseConfig = data.config; VAPID_KEY = data.vapidKey; return true }
  } catch {}
  const env = {
    apiKey: import.meta.env.VITE_FIREBASE_API_KEY, authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
    projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID, storageBucket: import.meta.env.VITE_FIREBASE_STORAGE_BUCKET,
    messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID, appId: import.meta.env.VITE_FIREBASE_APP_ID,
  }
  if (env.apiKey && import.meta.env.VITE_FIREBASE_VAPID_KEY) { firebaseConfig = env; VAPID_KEY = import.meta.env.VITE_FIREBASE_VAPID_KEY; return true }
  return false
}

/**
 * Initialize push notification support.
 */
export async function initPushService() {
  try {
    // Firebase 설정이 없으면 스킵
    if (!(await loadPushConfig())) {
      console.warn('[PushService] Firebase config missing — push disabled')
      return
    }

    if (window.Capacitor?.isNativePlatform()) {
      return await initCapacitorPush()
    }
    return await initWebPush()
  } catch (err) {
    console.warn('[PushService] init failed (non-fatal):', err.message || err)
  }
}

async function initWebPush() {
  if (!('serviceWorker' in navigator)) {
    console.warn('[PushService] Service workers not supported')
    return
  }

  // 1. Service Worker 등록
  const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' })
  console.info('[PushService] SW registered:', registration.scope)

  // 2. 알림 권한 요청
  if ('Notification' in window) {
    const permission = await Notification.requestPermission()
    console.info('[PushService] Notification permission:', permission)
    if (permission !== 'granted') {
      console.warn('[PushService] Permission denied — no push notifications')
      return registration
    }
  }

  // 3. Firebase 초기화
  console.info('[PushService] Firebase config:', firebaseConfig.projectId, 'VAPID:', VAPID_KEY?.substring(0, 10))
  const app = initializeApp(firebaseConfig)
  messaging = getMessaging(app)
  console.info('[PushService] Firebase initialized OK')

  // 4. FCM 토큰 발급 (기존 SW 사용)
  try {
    const token = await getToken(messaging, {
      vapidKey: VAPID_KEY,
      serviceWorkerRegistration: registration,
    })

    if (token) {
      console.info('[PushService] FCM token:', token.substring(0, 20) + '...')
      // 5. 서버에 토큰 등록
      await axios.post('/api/comms/push/register', {
        fcm_token: token,
        platform: 'web',
      })
      console.info('[PushService] Token registered with server')
    } else {
      console.warn('[PushService] No FCM token received')
    }
  } catch (err) {
    console.error('[PushService] ❌ Token error:', err.code || err.name, err.message || err)
  }

  // 6. 포그라운드 메시지 처리 (앱이 열려있을 때)
  onMessage(messaging, (payload) => {
    console.log('[PushService] Foreground message:', payload)
    // 포그라운드에서는 WebSocket(Echo)이 이미 처리하므로
    // 중복 알림은 보내지 않음. 필요시 여기서 UI 알림 표시 가능.
  })

  return registration
}

async function initCapacitorPush() {
  console.warn('[PushService] Capacitor push not available')
}
