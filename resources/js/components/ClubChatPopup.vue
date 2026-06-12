<template>
<!-- 플로팅 채팅 버튼 + 팝업 -->
<Teleport to="body">
  <!-- 채팅 버튼 (접힌 상태) -->
  <button v-if="!open" @click="openChat"
    class="fixed bottom-20 right-4 z-[90] w-14 h-14 bg-amber-500 hover:bg-amber-600 text-white rounded-full shadow-xl flex items-center justify-center transition-all hover:scale-110">
    <AppIcon name="message-circle" :size="26" />
    <span v-if="unreadCount" class="absolute -top-1 -right-1 bg-red-500 text-white text-[11px] font-bold w-5 h-5 rounded-full flex items-center justify-center">
      {{ unreadCount > 9 ? '9+' : unreadCount }}
    </span>
  </button>

  <!-- 채팅 팝업 (데스크탑: 우측 하단 팝업, 모바일: 전체화면) -->
  <div v-if="open"
    class="fixed z-[91] bg-white flex flex-col overflow-hidden
           inset-0 sm:inset-auto sm:bottom-20 sm:right-4 sm:w-[340px] sm:h-[480px] sm:rounded-2xl sm:shadow-2xl sm:border sm:border-gray-200"
    style="max-height: 100vh;">

    <!-- 헤더 -->
    <div class="bg-amber-500 text-white px-4 py-3 flex items-center justify-between flex-shrink-0 safe-top">
      <div class="flex items-center gap-2 min-w-0">
        <AppIcon name="message-circle" :size="18" />
        <span class="font-bold text-sm truncate">{{ roomName }}</span>
      </div>
      <div class="flex items-center gap-1">
        <button @click="open = false" class="w-7 h-7 rounded-full hover:bg-amber-600 flex items-center justify-center transition-colors text-lg" title="최소화">−</button>
        <button @click="open = false" class="w-7 h-7 rounded-full hover:bg-amber-600 flex items-center justify-center transition-colors" title="닫기"><AppIcon name="x" :size="15" /></button>
      </div>
    </div>

    <!-- 메시지 영역 -->
    <div ref="msgContainer" class="flex-1 overflow-y-auto p-3 space-y-2 bg-gray-50" @scroll="onScroll">
      <div v-if="loadingMore" class="text-center py-2">
        <div class="inline-block w-5 h-5 border-2 border-amber-400 border-t-transparent rounded-full animate-spin"></div>
      </div>

      <div v-if="!messages.length && !loading" class="text-center py-8 text-ink-muted text-xs">
        <div class="icon-chip w-12 h-12 bg-gray-100 text-gray-300 mx-auto mb-2"><AppIcon name="message-circle" :size="24" :stroke-width="1.5" /></div>
        아직 메시지가 없습니다.<br>첫 메시지를 보내보세요!
      </div>

      <div v-for="msg in sortedMessages" :key="msg.id"
        :class="msg.user_id === userId ? 'flex justify-end' : 'flex justify-start'">
        <div :class="msg.user_id === userId ? 'max-w-[75%]' : 'max-w-[75%]'">
          <!-- 상대방 이름 -->
          <div v-if="msg.user_id !== userId" class="text-[11px] text-ink-muted mb-0.5 ml-1">
            {{ msg.user?.nickname || msg.user?.name || '알수없음' }}
          </div>
          <!-- 메시지 버블 -->
          <div class="px-3 py-2 rounded-2xl text-sm break-words"
            :class="msg.user_id === userId
              ? 'bg-amber-400 text-white rounded-br-md'
              : 'bg-white border border-gray-100 text-ink-light rounded-bl-md shadow-sm'">
            <div v-if="msg.type === 'image' && msg.file_url" class="mb-1">
              <img :src="msg.file_url" class="max-w-full rounded-lg max-h-32" @error="$event.target.style.display='none'" />
            </div>
            <span v-if="msg.content">{{ msg.content }}</span>
          </div>
          <!-- 시간 -->
          <div class="text-[11px] mt-0.5 px-1 text-ink-faint"
            :class="msg.user_id === userId ? 'text-right' : ''">
            {{ formatTime(msg.created_at) }}
          </div>
        </div>
      </div>
    </div>

    <!-- 입력 영역 -->
    <div class="border-t border-gray-50 bg-white px-3 py-2 flex-shrink-0">
      <form @submit.prevent="sendMessage" class="flex items-center gap-2">
        <input v-model="newMessage" type="text" placeholder="메시지 입력..."
          class="input-soft flex-1 !rounded-full !py-2"
          :disabled="sending" maxlength="2000" />
        <button type="submit" :disabled="!newMessage.trim() || sending"
          class="w-9 h-9 bg-amber-500 hover:bg-amber-600 text-white rounded-full flex items-center justify-center transition-colors disabled:opacity-40 disabled:cursor-not-allowed flex-shrink-0">
          <AppIcon name="send" :size="16" />
        </button>
      </form>
    </div>
  </div>
</Teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useAuthStore } from '../stores/auth'
import axios from 'axios'
import AppIcon from './AppIcon.vue'

const props = defineProps({
  roomId: { type: [Number, String], required: true },
  roomName: { type: String, default: '동호회 채팅' },
  autoOpen: { type: Boolean, default: false },
})

const emit = defineEmits(['close'])
const auth = useAuthStore()
const userId = computed(() => auth.user?.id)

const open = ref(false)
const messages = ref([])
const newMessage = ref('')
const loading = ref(false)
const loadingMore = ref(false)
const sending = ref(false)
const unreadCount = ref(0)
const msgContainer = ref(null)
const currentPage = ref(1)
const hasMore = ref(true)

let pollTimer = null
let echoChannel = null

const sortedMessages = computed(() => {
  return [...messages.value].sort((a, b) => new Date(a.created_at) - new Date(b.created_at))
})

function formatTime(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  const now = new Date()
  const diff = now - d
  if (diff < 60000) return '방금'
  if (diff < 3600000) return Math.floor(diff / 60000) + '분 전'
  if (d.toDateString() === now.toDateString()) {
    return d.toLocaleTimeString('ko-KR', { hour: '2-digit', minute: '2-digit' })
  }
  return d.toLocaleDateString('ko-KR', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

async function loadMessages(page = 1) {
  if (page === 1) loading.value = true
  else loadingMore.value = true
  try {
    const { data } = await axios.get(`/api/chat/rooms/${props.roomId}/messages`, { params: { page } })
    const msgs = data.data?.data || data.data || []
    if (page === 1) {
      messages.value = msgs
    } else {
      const existing = new Set(messages.value.map(m => m.id))
      messages.value = [...messages.value, ...msgs.filter(m => !existing.has(m.id))]
    }
    hasMore.value = msgs.length >= 50
    currentPage.value = page
  } catch {}
  loading.value = false
  loadingMore.value = false
}

async function sendMessage() {
  if (!newMessage.value.trim() || sending.value) return
  sending.value = true
  try {
    const { data } = await axios.post(`/api/chat/rooms/${props.roomId}/messages`, {
      content: newMessage.value.trim()
    })
    const sent = Array.isArray(data.data) ? data.data : [data.data]
    sent.forEach(m => {
      if (!messages.value.find(x => x.id === m.id)) messages.value.push(m)
    })
    newMessage.value = ''
    await nextTick()
    scrollToBottom()
  } catch {}
  sending.value = false
}

function scrollToBottom() {
  if (msgContainer.value) {
    msgContainer.value.scrollTop = msgContainer.value.scrollHeight
  }
}

function onScroll() {
  if (!msgContainer.value || !hasMore.value || loadingMore.value) return
  if (msgContainer.value.scrollTop < 50) {
    loadMessages(currentPage.value + 1)
  }
}

function openChat() {
  open.value = true
  unreadCount.value = 0
  loadMessages(1)
  nextTick(scrollToBottom)
}

// 실시간 수신
function setupEcho() {
  if (typeof window.Echo === 'undefined') return
  try {
    echoChannel = window.Echo.channel(`chat.${props.roomId}`)
    echoChannel.listen('.message.sent', (event) => {
      const msg = event.message || event
      if (msg && !messages.value.find(m => m.id === msg.id)) {
        messages.value.push(msg)
        if (!open.value) unreadCount.value++
        nextTick(scrollToBottom)
      }
    })
  } catch {}
}

// 폴링 폴백
function startPolling() {
  pollTimer = setInterval(async () => {
    if (!open.value) return
    try {
      const { data } = await axios.get(`/api/chat/rooms/${props.roomId}/messages`, { params: { page: 1 } })
      const msgs = data.data?.data || data.data || []
      msgs.forEach(m => {
        if (!messages.value.find(x => x.id === m.id)) {
          messages.value.push(m)
          nextTick(scrollToBottom)
        }
      })
    } catch {}
  }, 5000)
}

watch(open, (v) => {
  if (v) nextTick(scrollToBottom)
})

onMounted(() => {
  setupEcho()
  startPolling()
  if (props.autoOpen) openChat()
})

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer)
  if (echoChannel && typeof window.Echo !== 'undefined') {
    try { window.Echo.leave(`chat.${props.roomId}`) } catch {}
  }
})
</script>
