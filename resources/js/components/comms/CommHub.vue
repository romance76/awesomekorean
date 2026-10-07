<template>
  <div>
    <slot />
    <!-- 원격 오디오 — display:none 대신 크기 0 (모바일에서 display:none이면 재생 안 됨) -->
    <audio id="sk-remote-audio" autoplay playsinline
           style="position:fixed;top:-9999px;left:-9999px;width:1px;height:1px;opacity:0" />
    <!-- 벨소리 -->
    <audio id="sk-ringtone" loop playsinline preload="none"
           style="position:fixed;top:-9999px;left:-9999px;width:1px;height:1px;opacity:0" />

    <!-- Chat window overlay -->
    <div v-if="activeChatPartner" class="fixed inset-0 z-[900]">
      <ChatWindow
        :partner="activeChatPartner"
        :conversation-id="activeConversationId"
        :my-user-id="myUserId"
        @close="closeChat"
        @start-call="handleStartCall"
      />
    </div>

    <!-- Call screen overlay -->
    <CallScreen
      :show="callStatus !== 'idle'"
      :call-status="callStatus"
      :incoming-call="incomingCall"
      :remote-user="remoteUser"
      :is-muted="isMuted"
      :is-speaker="isSpeaker"
      :duration-formatted="durationFormatted"
      :remote-audio-blocked="remoteAudioBlocked"
      :notice="callNotice"
      @answer="answerCall"
      @decline="declineCall"
      @end="endCall"
      @toggle-mute="toggleMute"
      @toggle-speaker="toggleSpeaker"
      @unblock-audio="unblockRemoteAudio"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useCommsWebRTC } from '@/composables/useCommsWebRTC'
import { initPushService } from '@/services/PushService'
import { startRingtone, preloadRingtone } from '@/services/RingtoneService'
import ChatWindow from './ChatWindow.vue'
import CallScreen from './CallScreen.vue'

const auth = useAuthStore()
const myUserId = auth.user?.id

// ── Chat state ────────────────────────────────────────────────────
const activeChatPartner    = ref(null)
const activeConversationId = ref(null)

// ── WebRTC call state ─────────────────────────────────────────────
const {
  callStatus,
  isMuted,
  isSpeaker,
  remoteUser,
  incomingCall,
  durationFormatted,
  remoteAudioBlocked,
  callNotice,
  unblockRemoteAudio,
  listenForSignals,
  ringFromPush,
  startCall,
  answerCall,
  declineCall,
  endCall,
  toggleMute,
  toggleSpeaker,
} = useCommsWebRTC()

let heartbeatInterval = null

// ── 벨소리 WAV 미리 로드 (첫 터치 시) ─────────────────────────────
function onFirstInteraction() {
  preloadRingtone()
  document.removeEventListener('touchstart', onFirstInteraction)
  document.removeEventListener('click', onFirstInteraction)
}
document.addEventListener('touchstart', onFirstInteraction, { once: true })
document.addEventListener('click', onFirstInteraction, { once: true })

onMounted(async () => {
  if (!myUserId) {
    console.warn('[CommHub] No user ID, skipping init')
    return
  }

  try {
  // Listen for incoming calls and WebRTC signals
  console.log('[CommHub] Initializing for user:', myUserId)
  listenForSignals(myUserId)

  // Initialize push notifications (stub - no Firebase yet)
  await initPushService()

  // Presence heartbeat — 접속 중이라는 신호 (전화를 거는 쪽이 "상대가 접속 중인지" 알 수 있게). 열자마자 한 번 + 25초마다 + 화면이 다시 보일 때
  const ping = () => fetch('/api/comms/presence/ping', {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${auth.token}`,
      'Content-Type': 'application/json',
    },
  }).catch(() => {})
  ping()
  heartbeatInterval = setInterval(ping, 25000)
  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') ping() })

  // Handle Service Worker notification clicks forwarded to the app
  navigator.serviceWorker?.addEventListener('message', handleSwMessage)
  } catch (err) {
    console.error('[CommHub] Init error (non-fatal):', err)
  }
})

onUnmounted(() => {
  if (heartbeatInterval) clearInterval(heartbeatInterval)
  navigator.serviceWorker?.removeEventListener('message', handleSwMessage)
})

// ── Service Worker message handler ────────────────────────────────
function handleSwMessage(event) {
  const { type, payload } = event.data || {}
  if (type === 'NOTIFICATION_CLICK' && payload?.type === 'incoming_call') {
    ringFromPush(payload)   // 아직 울리는 전화인지 서버에 확인한 뒤 벨 화면을 띄움
  }
}

// ── Public methods ────────────────────────────────────────────────

/**
 * Open a chat window with a partner.
 * @param {Object} partner - { id, name, avatar, online }
 * @param {Number} conversationId - Existing conversation ID
 */
function openChat(partner, conversationId) {
  activeChatPartner.value    = partner
  activeConversationId.value = conversationId
}

function closeChat() {
  activeChatPartner.value    = null
  activeConversationId.value = null
}

async function handleStartCall(partner) {
  closeChat()
  await startCall(partner)
}

// Expose openChat so parent components can call it via ref
defineExpose({ openChat, startCall })
</script>

<style>
/* Slide-up transition for chat window */
.comm-slide-up-enter-active,
.comm-slide-up-leave-active {
  transition: transform 0.3s ease;
}
.comm-slide-up-enter-from,
.comm-slide-up-leave-to {
  transform: translateY(100%);
}
</style>
