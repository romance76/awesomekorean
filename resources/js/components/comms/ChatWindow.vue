<template>
  <div class="flex flex-col h-full bg-gray-900 font-sans">
    <!-- Header -->
    <div class="flex items-center gap-3 px-4 py-3 bg-gray-800 border-b border-gray-700 sticky top-0 z-10"
         :style="{ paddingTop: 'calc(12px + env(safe-area-inset-top))' }">
      <button @click="$emit('close')"
              class="flex-shrink-0 p-1 rounded-md text-gray-400 hover:text-white hover:bg-gray-700 transition-colors">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M19 12H5M12 5l-7 7 7 7"/>
        </svg>
      </button>

      <div class="flex items-center gap-3 flex-1 min-w-0">
        <div class="relative flex-shrink-0">
          <img :src="partner.avatar || '/images/default-avatar.svg'"
               :alt="partner.name"
               class="w-9 h-9 rounded-full object-cover"
               @error="$event.target.src = '/images/default-avatar.svg'">
          <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full border-2 border-gray-800"
                :class="partner.online ? 'bg-green-500' : 'bg-gray-500'"></span>
        </div>
        <div class="min-w-0">
          <p class="text-sm font-semibold text-white truncate">{{ partner.name }}</p>
          <p class="text-xs text-gray-400">{{ partner.online ? '온라인' : '오프라인' }}</p>
        </div>
      </div>

      <button v-if="VOICE_CALL_ENABLED" @click="$emit('start-call', partner)"
              class="flex-shrink-0 p-2 rounded-full bg-green-500/10 text-green-400 hover:bg-green-500/20 transition-colors">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81 19.79 19.79 0 01.01 1.18 2 2 0 012 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
        </svg>
      </button>
    </div>

    <!-- Messages area -->
    <div ref="messagesEl"
         class="flex-1 overflow-y-auto px-4 py-4 flex flex-col gap-2"
         style="-webkit-overflow-scrolling: touch"
         @scroll="onScroll">

      <!-- Load more -->
      <div v-if="hasMore" class="text-center mb-2">
        <button @click="loadOlder"
                :disabled="isLoading"
                class="text-xs text-gray-500 hover:text-gray-300 transition-colors disabled:opacity-50">
          {{ isLoading ? '불러오는 중...' : '이전 메시지 보기' }}
        </button>
      </div>

      <!-- Message list -->
      <div v-for="msg in messages"
           :key="msg.id"
           class="flex items-end gap-1.5"
           :class="isMine(msg) ? 'flex-row-reverse' : ''">
        <!-- Partner avatar (only for their messages) -->
        <img v-if="!isMine(msg)"
             :src="partner.avatar || '/images/default-avatar.svg'"
             class="w-7 h-7 rounded-full object-cover flex-shrink-0"
             @error="$event.target.src = '/images/default-avatar.svg'">

        <div class="flex flex-col max-w-[70%]"
             :class="isMine(msg) ? 'items-end' : 'items-start'">
          <!-- Bubble -->
          <div class="px-3.5 py-2.5 text-sm leading-relaxed break-words"
               :class="[
                 isMine(msg)
                   ? 'bg-green-600 text-white rounded-2xl rounded-br-sm'
                   : 'bg-gray-800 text-gray-100 rounded-2xl rounded-bl-sm',
                 msg.isPending ? 'opacity-60' : ''
               ]">
            {{ msg.body }}
          </div>
          <!-- Time + read badge -->
          <span class="flex items-center gap-1 mt-0.5 text-[11px] text-gray-500">
            {{ formatTime(msg.created_at) }}
            <span v-if="isMine(msg) && msg.read_at"
                  class="text-green-500">읽음</span>
          </span>
        </div>
      </div>

      <!-- Empty state -->
      <div v-if="messages.length === 0 && !isLoading"
           class="flex-1 flex flex-col items-center justify-center text-center text-gray-500 my-auto">
        <p class="text-sm">{{ partner.name }}님과 대화를 시작해보세요</p>
        <p class="text-xs mt-1 text-gray-600">안심 서비스를 통해 안전하게 연락하세요</p>
      </div>
    </div>

    <!-- Input bar -->
    <div class="flex items-end gap-2 px-4 pt-2.5 bg-gray-800 border-t border-gray-700"
         :style="{ paddingBottom: 'calc(10px + env(safe-area-inset-bottom))' }">
      <textarea v-model="inputText"
                ref="inputEl"
                rows="1"
                placeholder="메시지 입력..."
                @keydown="onKeydown"
                @input="autoResize"
                class="flex-1 bg-gray-700 border border-gray-600 rounded-2xl px-4 py-2.5 text-sm text-white placeholder-gray-400
                       resize-none outline-none leading-snug transition-colors
                       focus:border-green-500 focus:bg-gray-700/80
                       disabled:opacity-50"></textarea>
      <button @click="send"
              :disabled="!inputText.trim() || isSending"
              class="flex-shrink-0 w-10 h-10 rounded-full bg-green-600 text-white flex items-center justify-center
                     transition-colors hover:bg-green-500
                     disabled:bg-gray-600 disabled:cursor-not-allowed">
        <svg v-if="!isSending" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="22" y1="2" x2="11" y2="13"/>
          <polygon points="22 2 15 22 11 13 2 9 22 2"/>
        </svg>
        <span v-else class="w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin"></span>
      </button>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { useChat } from '@/composables/useChat'
import { useAuthStore } from '@/stores/auth'
import { VOICE_CALL_ENABLED } from '@/config/features'

const props = defineProps({
  partner:        { type: Object, required: true },
  conversationId: { type: [Number, String], default: null },
  myUserId:       { type: [Number, String], default: null },
})

// 내 id 는 인증 스토어에서 반응형으로 (prop 이 비거나 문자열이어도 안전)
const auth = useAuthStore()
const meId = computed(() => Number(auth.user?.id ?? props.myUserId))
function isMine(msg) {
  return Number(msg.sender_id) === meId.value
}

const emit = defineEmits(['close', 'start-call'])

const inputText  = ref('')
const messagesEl = ref(null)
const inputEl    = ref(null)

const {
  messages,
  isLoading,
  isSending,
  hasMore,
  loadMessages,
  sendMessage,
  loadMore,
  subscribe,
  unsubscribe,
} = useChat(props.conversationId, props.partner?.id)

onMounted(async () => {
  try {
    await loadMessages()
  } catch (e) {
    console.error('[ChatWindow] loadMessages failed', e)
  }
  subscribe()
  await nextTick()
  scrollToBottom()
  inputEl.value?.focus?.({ preventScroll: true })
})

onUnmounted(() => unsubscribe())

// 맨 아래(새 메시지)가 바뀔 때만 아래로. 이전 메시지를 위에 붙일 땐 스크롤 위치 유지.
watch(
  () => messages.value[messages.value.length - 1]?.id,
  () => nextTick(scrollToBottom)
)

async function send() {
  const body = inputText.value.trim()
  if (!body) return
  if (isSending.value) return
  inputText.value = ''
  resetInputHeight()
  try {
    await sendMessage(props.partner.id, body)
  } catch (e) {
    inputText.value = body
    alert(e?.response?.data?.error || e?.response?.data?.message || '메시지를 보내지 못했습니다.')
  }
  await nextTick()
  scrollToBottom()
  inputEl.value?.focus?.({ preventScroll: true })
}

// Enter 전송 / Shift+Enter 줄바꿈 / 한글 조합 중(229)에는 무시
function onKeydown(e) {
  if (e.key !== 'Enter' || e.shiftKey) return
  if (e.isComposing || e.keyCode === 229) return
  e.preventDefault()
  send()
}

async function loadOlder() {
  const el = messagesEl.value
  const prevH = el ? el.scrollHeight : 0
  await loadMore()
  await nextTick()
  if (el) el.scrollTop = el.scrollHeight - prevH
}

function scrollToBottom() {
  if (messagesEl.value) {
    messagesEl.value.scrollTop = messagesEl.value.scrollHeight
  }
}

function onScroll() {
  if (messagesEl.value?.scrollTop < 60 && hasMore.value) loadOlder()
}

function autoResize(e) {
  const el = e.target
  el.style.height = 'auto'
  el.style.height = Math.min(el.scrollHeight, 120) + 'px'
}

function resetInputHeight() {
  if (inputEl.value) inputEl.value.style.height = 'auto'
}

function formatTime(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleTimeString('ko-KR', {
    hour:   '2-digit',
    minute: '2-digit',
  })
}
</script>
