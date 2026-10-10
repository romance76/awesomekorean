<template>
<!-- 채팅 버튼 (접힌 상태) -->
<Teleport to="body">
  <button v-if="chatStore.hasRooms && !chatStore.isOpen" @click="chatStore.toggleOpen()"
    class="fixed bottom-20 right-4 z-[90] w-14 h-14 bg-amber-500 hover:bg-amber-600 text-white rounded-full shadow-lift flex items-center justify-center transition-all hover:scale-110">
    <AppIcon name="message-circle" :size="26" />
    <span v-if="totalUnread" class="absolute -top-1 -right-1 bg-red-500 text-white text-[11px] font-bold w-5 h-5 rounded-full flex items-center justify-center">
      {{ totalUnread > 9 ? '9+' : totalUnread }}
    </span>
  </button>

  <!-- 채팅 팝업 -->
  <div v-if="chatStore.hasRooms && chatStore.isOpen"
    class="fixed z-[91] bg-white flex flex-col overflow-hidden
           inset-0 sm:inset-auto sm:bottom-20 sm:right-4 sm:w-[360px] sm:h-[500px] sm:rounded-2xl sm:shadow-lift sm:border sm:border-gray-100"
    style="max-height: 100vh;">

    <!-- 탭 헤더 -->
    <div class="bg-amber-500 flex-shrink-0 safe-top">
      <div class="flex items-center justify-between px-3 py-2">
        <div class="flex items-center gap-1 flex-1 overflow-x-auto scrollbar-hide">
          <button v-for="room in chatStore.openRooms" :key="room.id"
            @click="chatStore.activeRoomId = room.id"
            class="flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-bold whitespace-nowrap transition flex-shrink-0"
            :class="chatStore.activeRoomId === room.id ? 'bg-white/30 text-white' : 'text-white/70 hover:text-white hover:bg-white/10'">
            <AppIcon :name="room.type === 'club' ? 'users' : 'message-circle'" :size="12" />
            <span class="max-w-[80px] truncate">{{ room.name }}</span>
            <button @click.stop="chatStore.closeRoom(room.id)" class="ml-0.5 text-white/50 hover:text-white"><AppIcon name="x" :size="10" /></button>
          </button>
        </div>
        <button @click="chatStore.minimize()" class="w-7 h-7 rounded-full hover:bg-amber-600 flex items-center justify-center text-white transition ml-1 flex-shrink-0">−</button>
      </div>
    </div>

    <!-- 활성 채팅방 -->
    <template v-if="chatStore.activeRoom">
      <!-- 메시지 영역 -->
      <div ref="msgContainer" class="flex-1 overflow-y-auto p-3 space-y-2 bg-gray-50">
        <div v-if="loadError" class="text-center py-3 px-3 text-xs text-red-500 bg-red-50 rounded-xl">
          {{ loadError }}
          <button type="button" @click="loadMessages(chatStore.activeRoomId)" class="ml-1 underline font-bold">다시 시도</button>
        </div>
        <div v-else-if="loading && !messages.length" class="text-center py-8">
          <div class="inline-block w-5 h-5 border-2 border-amber-400 border-t-transparent rounded-full animate-spin"></div>
        </div>
        <div v-else-if="!messages.length" class="text-center py-8 text-ink-muted text-xs">
          아직 메시지가 없습니다.<br>첫 메시지를 보내보세요!
        </div>
        <div v-for="msg in sortedMessages" :key="msg.id"
          :class="msg.user_id === userId ? 'flex justify-end' : 'flex justify-start'">
          <div class="max-w-[75%]">
            <div v-if="msg.user_id !== userId" class="text-[11px] text-ink-muted mb-0.5 ml-1">
              {{ msg.user?.nickname || msg.user?.name || '알수없음' }}
            </div>
            <div class="px-3 py-2 rounded-2xl text-sm break-words whitespace-pre-wrap"
              :class="[msg.user_id === userId
                ? 'bg-amber-400 text-white rounded-br-md'
                : 'bg-white shadow-card text-ink rounded-bl-md',
                msg._pending ? 'opacity-60' : '', msg._failed ? '!bg-red-100 !text-red-700' : '']">
              <img v-if="msg.type === 'image' && msg.file_url" :src="msg.file_url" class="max-w-full rounded-lg max-h-32 mb-1" />
              <span v-if="msg.content">{{ msg.content }}</span>
            </div>
            <div v-if="msg._failed" class="text-[11px] mt-0.5 px-1 text-right text-red-500">
              {{ msg._error || '전송 실패' }}
              <button type="button" @click="resend(msg)" class="ml-1 font-bold underline">재전송</button>
              <button type="button" @click="discard(msg)" class="ml-1 text-ink-faint underline">삭제</button>
            </div>
            <div v-else class="text-[11px] mt-0.5 px-1"
              :class="msg.user_id === userId ? 'text-right text-ink-faint' : 'text-ink-faint'">
              {{ msg._pending ? '보내는 중...' : formatTime(msg.created_at) }}
            </div>
          </div>
        </div>
      </div>

      <!-- 입력 -->
      <div class="border-t border-gray-100 bg-white px-3 py-2 flex-shrink-0 safe-bottom">
        <form @submit.prevent="sendMessage" class="flex items-center gap-2">
          <input v-model="newMessage" type="text" placeholder="메시지 입력..."
            class="input-soft flex-1 rounded-full px-3 py-2 text-sm"
            maxlength="2000" enterkeyhint="send" autocomplete="off" />
          <button type="submit" :disabled="!newMessage.trim()"
            class="w-9 h-9 bg-amber-500 hover:bg-amber-600 text-white rounded-full shadow-btn flex items-center justify-center transition disabled:opacity-40 flex-shrink-0">
            <AppIcon name="send" :size="16" />
          </button>
        </form>
      </div>
    </template>
  </div>
</Teleport>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useAuthStore } from '../stores/auth'
import { useChatStore } from '../stores/chat'
import axios from 'axios'
import AppIcon from './AppIcon.vue'

const auth = useAuthStore()
const chatStore = useChatStore()
const userId = computed(() => auth.user?.id)

const messages = ref([])
const newMessage = ref('')
const loading = ref(false)
const loadError = ref('')
const msgContainer = ref(null)
const totalUnread = ref(0)

let pollTimer = null
let echoChannels = {}
let tmpSeq = 0

// 서버에서 받은 메시지는 시간순, 보내는 중/실패 메시지는 항상 맨 아래
const sortedMessages = computed(() => {
  const real = messages.value.filter(m => !m._local).sort((a, b) => new Date(a.created_at) - new Date(b.created_at))
  const local = messages.value.filter(m => m._local)
  return [...real, ...local]
})

function formatTime(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  const now = new Date()
  const diff = now - d
  if (diff < 60000) return '방금'
  if (diff < 3600000) return Math.floor(diff / 60000) + '분 전'
  if (d.toDateString() === now.toDateString())
    return d.toLocaleTimeString('ko-KR', { hour: '2-digit', minute: '2-digit' })
  return d.toLocaleDateString('ko-KR', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function scrollToBottom() {
  nextTick(() => { if (msgContainer.value) msgContainer.value.scrollTop = msgContainer.value.scrollHeight })
}

function errMessage(e) {
  const st = e?.response?.status
  const m = e?.response?.data?.message
  if (m) return m
  if (st === 404) return '채팅방을 찾을 수 없습니다.'
  if (st === 419 || st === 401) return '로그인이 필요합니다.'
  if (st === 429) return '너무 빠르게 보내고 있어요. 잠시 후 다시 시도해주세요.'
  if (!e?.response) return '네트워크 연결을 확인해주세요.'
  return '메시지를 보내지 못했습니다.'
}

// 서버 메시지 병합 (id 중복 제거, 보내는 중 메시지는 유지)
function mergeServer(list) {
  const have = new Set(messages.value.filter(m => !m._local).map(m => m.id))
  let added = false
  list.forEach(m => { if (m && !have.has(m.id)) { messages.value.push(m); have.add(m.id); added = true } })
  return added
}

async function loadMessages(roomId) {
  if (!roomId) return
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await axios.get(`/api/chat/rooms/${roomId}/messages`)
    if (roomId !== chatStore.activeRoomId) return   // 그 사이 다른 방으로 바뀜
    const list = data.data?.data || data.data || []
    const locals = messages.value.filter(m => m._local && m._roomId === roomId)
    messages.value = [...list, ...locals]
    scrollToBottom()
  } catch (e) {
    if (roomId === chatStore.activeRoomId) loadError.value = errMessage(e)
  } finally {
    loading.value = false
  }
}

async function postMessage(tmp) {
  tmp._pending = true
  tmp._failed = false
  tmp._error = ''
  try {
    const { data } = await axios.post(`/api/chat/rooms/${tmp._roomId}/messages`, { content: tmp.content })
    const sent = (Array.isArray(data.messages) && data.messages.length) ? data.messages : [data.data]
    // 임시 메시지를 서버 메시지로 교체 (실시간으로 이미 들어온 경우 중복 방지)
    messages.value = messages.value.filter(m => m.id !== tmp.id)
    if (tmp._roomId === chatStore.activeRoomId) mergeServer(sent.filter(Boolean))
    scrollToBottom()
  } catch (e) {
    tmp._pending = false
    tmp._failed = true
    tmp._error = errMessage(e)
  }
}

// 입력창은 즉시 비우고 메시지를 바로 화면에 올린 뒤(보내는 중) 서버 응답으로 확정한다.
// 실패하면 그 메시지가 빨간 상태로 남고 "재전송"/"삭제"를 고를 수 있다(입력한 글은 사라지지 않음).
function sendMessage() {
  const text = newMessage.value.trim()
  const roomId = chatStore.activeRoomId
  if (!text || !roomId) return
  newMessage.value = ''
  const tmp = reactive({
    id: 'tmp-' + (++tmpSeq), _local: true, _roomId: roomId,
    user_id: userId.value, user: auth.user, type: 'text', content: text,
    created_at: new Date().toISOString(),
  })
  messages.value.push(tmp)
  scrollToBottom()
  postMessage(tmp)
}

function resend(msg) { if (!msg._pending) postMessage(msg) }
function discard(msg) { messages.value = messages.value.filter(m => m.id !== msg.id) }

// Echo 실시간
function setupEcho(roomId) {
  if (!roomId || typeof window.Echo === 'undefined' || echoChannels[roomId]) return
  try {
    echoChannels[roomId] = window.Echo.channel(`chat.${roomId}`)
    echoChannels[roomId].listen('.message.sent', (event) => {
      const msg = event.message || event
      if (msg && chatStore.activeRoomId === roomId) {
        if (mergeServer([msg])) scrollToBottom()
      }
      if (!chatStore.isOpen) totalUnread.value++
    })
  } catch {}
}

function cleanupEcho(roomId) {
  if (echoChannels[roomId] && typeof window.Echo !== 'undefined') {
    try { window.Echo.leave(`chat.${roomId}`) } catch {}
    delete echoChannels[roomId]
  }
}

// 방 변경 시 메시지 로드
watch(() => chatStore.activeRoomId, (roomId) => {
  if (roomId) {
    messages.value = messages.value.filter(m => m._local && m._roomId === roomId)
    loadMessages(roomId)
    setupEcho(roomId)
  }
})

// 팝업 열릴 때
watch(() => chatStore.isOpen, (open) => {
  if (open) {
    totalUnread.value = 0
    if (chatStore.activeRoomId) loadMessages(chatStore.activeRoomId)
  }
})

// 방 추가/제거 시 Echo 관리
watch(() => chatStore.openRooms, (rooms) => {
  rooms.forEach(r => setupEcho(r.id))
}, { deep: true })

// 폴링
function startPolling() {
  pollTimer = setInterval(async () => {
    if (!chatStore.isOpen || !chatStore.activeRoomId) return
    try {
      const rid = chatStore.activeRoomId
      const { data } = await axios.get(`/api/chat/rooms/${rid}/messages`)
      if (rid !== chatStore.activeRoomId) return
      loadError.value = ''
      const msgs = data.data?.data || data.data || []
      if (mergeServer(msgs)) scrollToBottom()
    } catch {}
  }, 5000)
}

onMounted(() => startPolling())
onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer)
  Object.keys(echoChannels).forEach(cleanupEcho)
})
</script>

<style scoped>
.safe-top { padding-top: env(safe-area-inset-top, 0px); }
.safe-bottom { padding-bottom: env(safe-area-inset-bottom, 0px); }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
.scrollbar-hide::-webkit-scrollbar { display: none; }
</style>
