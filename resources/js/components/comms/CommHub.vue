<template>
  <div>
    <slot />

    <!-- Chat window overlay (via Teleport) -->
    <Teleport to="body">
      <Transition name="comm-slide-up">
        <div v-if="activeChatPartner"
             class="fixed inset-0 z-[900]">
          <ChatWindow
            :partner="activeChatPartner"
            :conversation-id="activeConversationId"
            :my-user-id="myUserId"
            @close="closeChat"
            @start-call="handleStartCall"
          />
        </div>
      </Transition>
    </Teleport>

    <!-- Call screen overlay (via Teleport) -->
    <Teleport to="body">
      <CallScreen
        :show="callStatus !== 'idle'"
        :call-status="callStatus"
        :incoming-call="incomingCall"
        :remote-user="remoteUser"
        :is-muted="isMuted"
        :is-speaker="isSpeaker"
        :duration-formatted="durationFormatted"
        @answer="answerCall"
        @decline="declineCall"
        @end="endCall"
        @toggle-mute="toggleMute"
      />
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useCommsWebRTC } from '@/composables/useCommsWebRTC'
import { initPushService } from '@/services/PushService'
import { startRingtone } from '@/services/RingtoneService'
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
  listenForSignals,
  startCall,
  answerCall,
  declineCall,
  endCall,
  toggleMute,
} = useCommsWebRTC()

let heartbeatInterval = null

onMounted(async () => {
  if (!myUserId) return

  // Listen for incoming calls and WebRTC signals
  listenForSignals(myUserId)

  // Initialize push notifications (stub - no Firebase yet)
  await initPushService()

  // Presence heartbeat (every 25 seconds)
  heartbeatInterval = setInterval(() => {
    fetch('/api/comms/presence/ping', {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${auth.token}`,
        'Content-Type': 'application/json',
      },
    }).catch(() => {})
  }, 25000)

  // Handle Service Worker notification clicks forwarded to the app
  navigator.serviceWorker?.addEventListener('message', handleSwMessage)
})

onUnmounted(() => {
  if (heartbeatInterval) clearInterval(heartbeatInterval)
  navigator.serviceWorker?.removeEventListener('message', handleSwMessage)
})

// ── Service Worker message handler ────────────────────────────────
function handleSwMessage(event) {
  const { type, payload } = event.data || {}
  if (type === 'NOTIFICATION_CLICK' && payload?.type === 'incoming_call') {
    incomingCall.value = {
      call_id:       payload.call_id,
      room_id:       payload.room_id,
      caller_id:     parseInt(payload.caller_id),
      caller_name:   payload.caller_name,
      caller_avatar: payload.caller_avatar,
    }
    callStatus.value = 'ringing'
    startRingtone()
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
