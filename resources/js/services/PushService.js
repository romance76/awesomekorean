/**
 * PushService — STUB version (no Firebase)
 *
 * Requests notification permission and registers the service worker.
 * Firebase FCM can be added later by replacing this file.
 * For now, real-time notifications are handled by WebSocket (window.Echo).
 */

import axios from 'axios'

/**
 * Initialize push notification support.
 * - Registers the service worker (public/sw.js)
 * - Requests Notification permission
 * - Firebase integration placeholder for future use
 */
export async function initPushService() {
  try {
    if (window.Capacitor?.isNativePlatform()) {
      return await initCapacitorPush()
    }
    return await initWebPush()
  } catch (err) {
    console.warn('[PushService] init failed (optional feature):', err)
  }
}

async function initWebPush() {
  if (!('serviceWorker' in navigator)) {
    console.warn('[PushService] Service workers not supported')
    return
  }

  // Register service worker
  const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' })
  console.info('[PushService] SW registered:', registration.scope)

  // Request notification permission
  if ('Notification' in window) {
    const permission = await Notification.requestPermission()
    console.info('[PushService] Notification permission:', permission)

    if (permission !== 'granted') {
      console.warn('[PushService] Notification permission denied')
      return
    }
  }

  // TODO: Firebase FCM integration
  // When Firebase is configured, uncomment and add:
  // 1. import { initializeApp } from 'firebase/app'
  // 2. import { getMessaging, getToken, onMessage } from 'firebase/messaging'
  // 3. Initialize Firebase with config from env
  // 4. Get FCM token
  // 5. Register token with server: axios.post('/api/comms/push/register', { fcm_token, platform: 'web' })
  // 6. Handle foreground messages with onMessage()

  return registration
}

async function initCapacitorPush() {
  // Capacitor native push (iOS/Android)
  // TODO: Install @capacitor/push-notifications when building native apps
  console.warn('[PushService] Capacitor push not available — install @capacitor/push-notifications')
}
