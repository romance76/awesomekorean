<template>
  <div class="flex flex-col bg-gray-100" style="position:fixed;inset:0;">
    <!-- Header -->
    <div class="bg-white shadow-sm px-4 py-3 flex items-center gap-3 flex-shrink-0">
      <button @click="$router.back()" class="text-gray-500 hover:text-gray-700">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
      </button>
      <div class="flex-1 min-w-0">
        <p class="font-bold text-gray-800 truncate">{{ room?.name || '채팅방' }}</p>
        <p class="text-xs text-gray-400 truncate">{{ room?.description }}</p>
      </div>
      <span class="flex items-center gap-1 text-xs flex-shrink-0" :class="connected ? 'text-green-500' : 'text-gray-400'">
        <span class="w-2 h-2 rounded-full" :class="connected ? 'bg-green-400' : 'bg-gray-300'"></span>
        {{ connected ? '실시간' : '연결 중' }}
      </span>
    </div>

    <!-- Messages -->
    <div ref="msgContainer" class="flex-1 overflow-y-auto px-4 py-3 space-y-3 min-h-0">
      <div v-if="loading" class="text-center py-8 text-gray-400">불러오는 중...</div>
      <div v-else-if="!messages.length" class="text-center py-12 text-gray-400">
        <div class="text-4xl mb-2">💬</div>
        <p class="text-sm">첫 메시지를 남겨보세요!</p>
      </div>
      <div v-for="msg in messages" :key="msg.id" class="flex gap-2"
        :class="isMe(msg) ? 'justify-end' : 'justify-start'">
        <template v-if="!isMe(msg)">
          <div class="w-8 h-8 rounded-full bg-blue-200 flex items-center justify-center text-xs font-bold text-blue-700 flex-shrink-0 mt-1">
            {{ userName(msg.user)[0] }}
          </div>
          <div class="max-w-[70%]">
            <p class="text-xs text-gray-500 mb-1 ml-1">{{ userName(msg.user) }}</p>
            <div class="bg-white rounded-2xl rounded-tl-none px-4 py-2 shadow-sm text-gray-800 text-sm leading-relaxed">{{ msg.message }}</div>
            <p class="text-xs text-gray-400 mt-1 ml-1">{{ formatTime(msg.created_at) }}</p>
          </div>
        </template>
        <template v-else>
          <div class="max-w-[70%]">
            <div class="bg-blue-500 text-white rounded-2xl rounded-tr-none px-4 py-2 shadow-sm text-sm leading-relaxed">{{ msg.message }}</div>
            <p class="text-xs text-gray-400 mt-1 text-right">{{ formatTime(msg.created_at) }}</p>
          </div>
        </template>
      </div>
    </div>

    <!-- Input — pb accounts for mobile bottom nav -->
    <div class="bg-white border-t px-3 py-2 pb-[calc(0.5rem+env(safe-area-inset-bottom))] flex gap-2 flex-shrink-0 md:pb-2">
      <input
        v-model="input"
        @keyup.enter="sendMessage"
        type="text"
        placeholder="메시지를 입력하세요..."
        class="flex-1 border border-gray-200 rounded-full px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 bg-gray-50"
        maxlength="500"
        :disabled="!auth.isLoggedIn"
      />
      <label class="p-2 text-gray-400 hover:text-blue-500 cursor-pointer flex items-center">
        <span class="text-xl">📎</span>
        <input type="file" class="hidden" @change="attachFile" accept="image/*,.pdf,.doc,.docx,.zip" />
      </label>
      <button
        @click="auth.isLoggedIn ? sendMessage() : $router.push('/auth/login')"
        :disabled="auth.isLoggedIn && (!input.trim() || sending)"
        class="bg-blue-500 text-white rounded-full px-4 py-2 text-sm font-semibold disabled:opacity-50 flex-shrink-0"
      >
        {{ auth.isLoggedIn ? '전송' : '로그인' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'

const route = useRoute()
const auth  = useAuthStore()
const slug  = route.params.id

const room         = ref(null)
const messages     = ref([])
const input        = ref('')
const sending      = ref(false)
const loading      = ref(true)
const connected    = ref(false)
const msgContainer = ref(null)

let channel = null

function userName(user) {
  return user?.name || user?.username || '?'
}

function isMe(msg) {
  return msg.user?.id === auth.user?.id || msg.user_id === auth.user?.id
}

function formatTime(dt) {
  if (!dt) return ''
  return new Date(dt).toLocaleTimeString('ko-KR', { hour: '2-digit', minute: '2-digit' })
}

async function scrollBottom() {
  await nextTick()
  if (msgContainer.value) {
    msgContainer.value.scrollTop = msgContainer.value.scrollHeight
  }
}

async function sendMessage() {
  if (!input.value.trim() || sending.value) return
  const text = input.value.trim()
  input.value = ''
  sending.value = true
  try {
    const socketId = window.Echo?.socketId?.()
    const headers  = socketId ? { 'X-Socket-ID': socketId } : {}
    const { data } = await axios.post(`/api/chat/rooms/${slug}/messages`, { message: text }, { headers })
    messages.value.push(data)
    await scrollBottom()
  } catch (e) {
    console.error(e)
    input.value = text
  } finally {
    sending.value = false
  }
}

async function attachFile(e) {
  const file = e.target.files[0]
  if (!file) return
  if (!auth.isLoggedIn) return
  const fd = new FormData()
  fd.append('file', file)
  sending.value = true
  try {
    const socketId = window.Echo?.socketId?.()
    const headers = { 'Content-Type': 'multipart/form-data' }
    if (socketId) headers['X-Socket-ID'] = socketId
    const { data } = await axios.post(`/api/chat/rooms/${slug}/messages`, fd, { headers })
    messages.value.push(data)
    await scrollBottom()
  } catch (err) {
    console.error('파일 전송 실패:', err)
    alert('파일 전송에 실패했습니다')
  } finally {
    sending.value = false
    e.target.value = ''
  }
}

onMounted(async () => {
  try {
    const { data } = await axios.get(`/api/chat/rooms/${slug}`)
    room.value     = data.room
    messages.value = data.messages ?? []
    await scrollBottom()
  } catch (e) {
    console.error('채팅방 로드 실패:', e)
  } finally {
    loading.value = false
  }

  if (!window.Echo || !room.value?.id) return

  channel = window.Echo.channel(`chat.${room.value.id}`)
  channel
    .listen('.message.sent', (data) => {
      if (!messages.value.some(m => m.id === data.id)) {
        messages.value.push(data)
        scrollBottom()
      }
    })
    .subscribed(() => { connected.value = true })
    .error(() => { connected.value = false })
})

onUnmounted(() => {
  if (room.value?.id) window.Echo?.leaveChannel(`chat.${room.value.id}`)
})
</script>
