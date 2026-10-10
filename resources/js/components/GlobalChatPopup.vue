<template>
<!-- 채팅 버튼 (접힌 상태) -->
<Teleport to="body">
  <div v-if="chatStore.hasRooms && !chatStore.isOpen" class="fixed bottom-20 right-4 z-[90] w-14 h-14">
    <button @click="chatStore.toggleOpen()" aria-label="채팅 열기"
      class="w-14 h-14 bg-amber-500 hover:bg-amber-600 text-white rounded-full shadow-lift flex items-center justify-center transition-all hover:scale-110">
      <AppIcon name="message-circle" :size="26" />
    </button>
    <!-- 안 읽은 수 (왼쪽 위) -->
    <span v-if="totalUnread" class="absolute -top-1 -left-1 bg-red-500 text-white text-[11px] font-bold w-5 h-5 rounded-full flex items-center justify-center pointer-events-none">
      {{ totalUnread > 9 ? '9+' : totalUnread }}
    </span>
    <!-- 닫기 (오른쪽 위 작은 X): 떠 있는 채팅 아이콘을 없앤다 -->
    <button @click.stop="chatStore.closeAll()" aria-label="채팅 닫기" title="채팅 닫기"
      class="absolute -top-1.5 -right-1.5 w-6 h-6 rounded-full bg-gray-700 text-white border-2 border-white shadow flex items-center justify-center hover:bg-gray-900 active:scale-95 transition">
      <AppIcon name="x" :size="12" />
    </button>
  </div>

  <!-- 채팅 팝업 -->
  <div v-if="chatStore.hasRooms && chatStore.isOpen"
    class="fixed z-[91] bg-white flex flex-col overflow-hidden
           inset-0 sm:inset-auto sm:bottom-20 sm:right-4 sm:w-[360px] sm:h-[500px] sm:rounded-2xl sm:shadow-lift sm:border sm:border-gray-100"
    :style="'max-height: 100vh;' + kbStyle">

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
        <button @click="chatStore.minimize()" class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/35 flex items-center justify-center text-white transition ml-1 flex-shrink-0" title="작게 접기" aria-label="작게 접기"><AppIcon name="minus" :size="16" /></button>
        <button @click="chatStore.closeAll()" class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/35 flex items-center justify-center text-white transition ml-1 flex-shrink-0" title="채팅 닫기" aria-label="채팅 닫기"><AppIcon name="x" :size="16" /></button>
      </div>
    </div>

    <!-- 활성 채팅방 -->
    <template v-if="chatStore.activeRoom">
      <!-- 📌 방장 고정 글(공지, 최대 3개): 같은 자리에서 돌아가며 보여주고, 누르면 전체 글을 본다 -->
      <div v-if="roomPins.length" class="border-b border-gray-100 bg-white px-3 py-2 flex items-center gap-2.5 flex-shrink-0 cursor-pointer select-none" @click="pinModal = currentPin">
        <span class="w-8 h-8 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center flex-shrink-0"><AppIcon name="pin" :size="15" /></span>
        <div class="flex-1 min-w-0">
          <div class="text-[11px] font-bold text-amber-600 flex items-center gap-1.5">공지<span v-if="roomPins.length > 1" class="text-ink-faint font-semibold">{{ (pinIdx % roomPins.length) + 1 }}/{{ roomPins.length }}</span></div>
          <Transition name="pinfade" mode="out-in">
            <div :key="currentPin?.id" class="text-sm text-ink truncate">{{ pinPreview(currentPin) }}</div>
          </Transition>
        </div>
        <div v-if="roomPins.length > 1" class="flex flex-col gap-1 flex-shrink-0">
          <span v-for="(p, i) in roomPins" :key="'dot'+p.id" class="w-1.5 h-1.5 rounded-full transition-colors" :class="i === (pinIdx % roomPins.length) ? 'bg-amber-500' : 'bg-gray-200'"></span>
        </div>
        <AppIcon name="chevron-right" :size="16" class="text-ink-faint flex-shrink-0" />
      </div>

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
        <div v-for="msg in sortedMessages" :key="msg.id" :id="'gcp-msg-' + msg.id"
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
            <div v-else class="text-[11px] mt-0.5 px-1 flex items-center gap-1 text-ink-faint"
              :class="msg.user_id === userId ? 'justify-end' : ''">
              <span v-if="msg.pinned_at" class="text-amber-500 inline-flex items-center" title="공지로 고정됨"><AppIcon name="pin" :size="11" /></span>
              {{ msg._pending ? '보내는 중...' : formatTime(msg.created_at) }}
              <!-- 방장 전용: 내가 쓴 글을 공지로 고정 / 해제 (최대 3개) -->
              <button v-if="isOwner && msg.user_id === userId && !msg._local && typeof msg.id === 'number' && msg.type !== 'system'"
                type="button" @click.stop="togglePin(msg)"
                class="ml-1 px-1.5 py-0.5 rounded-full font-semibold border transition-colors"
                :class="msg.pinned_at ? 'text-amber-600 border-amber-200 bg-amber-50' : 'text-ink-muted border-gray-200 hover:text-amber-600 hover:border-amber-200'">
                {{ msg.pinned_at ? '고정 해제' : '공지 고정' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- 입력 -->
      <div class="border-t border-gray-100 bg-white px-3 py-2 flex-shrink-0" :class="keyboardOpen ? '' : 'safe-bottom'">
        <ChatComposer v-model="newMessage" placeholder="메시지 입력..." @send="sendMessage" />
      </div>
    </template>

    <!-- 📌 고정된 글(공지) 전체 보기 -->
    <div v-if="pinModal" class="absolute inset-0 z-20 bg-black/50 flex items-end sm:items-center justify-center sm:p-4" @click.self="pinModal = null">
      <div class="bg-white w-full sm:max-w-sm rounded-t-2xl sm:rounded-2xl shadow-lift p-5" style="padding-bottom: calc(20px + env(safe-area-inset-bottom))">
        <div class="flex items-center gap-2.5 mb-3">
          <span class="w-9 h-9 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center flex-shrink-0"><AppIcon name="pin" :size="17" /></span>
          <div class="min-w-0">
            <div class="text-sm font-bold text-ink truncate">공지 · {{ pinModal.user?.nickname || pinModal.user?.name || '방장' }}</div>
            <div class="text-[11px] text-ink-faint">{{ formatTime(pinModal.created_at) }}</div>
          </div>
        </div>
        <img v-if="pinModal.type === 'image' && pinModal.file_url" :src="pinModal.file_url" class="w-full max-h-60 object-contain rounded-xl mb-3 bg-gray-50" />
        <a v-else-if="pinModal.type === 'file' && pinModal.file_url" :href="pinModal.file_url" target="_blank" download class="flex items-center gap-2 px-3 py-2 rounded-xl text-sm bg-blue-50 border border-blue-200 mb-3 no-underline text-ink"><AppIcon name="paperclip" :size="16" />{{ pinModal.content || '파일 다운로드' }}</a>
        <div v-if="pinModal.content && pinModal.type !== 'file'" class="text-sm text-ink whitespace-pre-wrap break-words max-h-[45vh] overflow-y-auto leading-relaxed">{{ pinModal.content }}</div>
        <div class="flex gap-2 mt-4">
          <button @click="goToPinned(pinModal)" class="btn-secondary flex-1 text-sm">대화에서 보기</button>
          <button v-if="isOwner" @click="unpinFromModal" class="btn-ghost text-sm text-red-500">고정 해제</button>
          <button @click="pinModal = null" class="btn-primary flex-1 text-sm">닫기</button>
        </div>
      </div>
    </div>
  </div>
</Teleport>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useAuthStore } from '../stores/auth'
import { useChatStore } from '../stores/chat'
import axios from 'axios'
import AppIcon from './AppIcon.vue'
import ChatComposer from './ChatComposer.vue'
import { useModal } from '../composables/useModal'
import { useKeyboardViewport } from '../composables/useKeyboardViewport'

const auth = useAuthStore()
const chatStore = useChatStore()

// 모바일(전체화면)에서 키보드가 올라오면 입력창이 키보드 바로 위에 붙도록 보이는 영역에 맞춘다
const isMobileView = ref(typeof window !== 'undefined' && window.innerWidth < 640)
function onWinResize() { isMobileView.value = window.innerWidth < 640 }
const { style: kbStyle, keyboardOpen } = useKeyboardViewport(() => chatStore.hasRooms && chatStore.isOpen && isMobileView.value)
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

// ─── 방장 고정 글(공지) — 채팅방 화면(ChatRooms)과 같은 방식 ───
const { showAlert } = useModal()
const roomPins = ref([])          // 고정된 글 (최대 3개)
const isOwner = ref(false)        // 내가 이 방(동호회)의 방장인지 (서버가 알려줌)
const pinIdx = ref(0)             // 배너에서 지금 보여주는 순번 (4초마다 돌아감)
const pinModal = ref(null)
const currentPin = computed(() => roomPins.value.length ? roomPins.value[pinIdx.value % roomPins.value.length] : null)
function pinPreview(p) {
  if (!p) return ''
  if (p.type === 'image') return '📷 ' + (p.content || '사진')
  if (p.type === 'file') return '📎 ' + (p.content || '파일')
  return (p.content || '').replace(/\s+/g, ' ')
}
function applyPins(data) {
  roomPins.value = data.pins || []
  if (data.is_owner !== undefined) isOwner.value = !!data.is_owner
  const ids = new Set(roomPins.value.map(p => p.id))
  messages.value.forEach(m => { if (typeof m.id === 'number') m.pinned_at = ids.has(m.id) ? (m.pinned_at || new Date().toISOString()) : null })
}
async function togglePin(msg) {
  const rid = chatStore.activeRoomId
  if (!rid) return
  try {
    const url = `/api/chat/rooms/${rid}/messages/${msg.id}/pin`
    const { data } = msg.pinned_at ? await axios.delete(url) : await axios.post(url)
    applyPins(data)
  } catch (e) {
    showAlert(e.response?.data?.message || '처리하지 못했어요. 잠시 후 다시 시도해 주세요.')
  }
}
async function unpinFromModal() {
  const m = pinModal.value
  if (!m) return
  pinModal.value = null
  await togglePin({ id: m.id, pinned_at: true })
}
function goToPinned(p) {
  pinModal.value = null
  nextTick(() => {
    const el = document.getElementById('gcp-msg-' + p.id)
    if (el) el.scrollIntoView({ block: 'center', behavior: 'smooth' })
  })
}
let pinRotateTimer = null

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
    applyPins(data)
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
  roomPins.value = []; isOwner.value = false; pinIdx.value = 0; pinModal.value = null
  if (roomId) {
    messages.value = messages.value.filter(m => m._local && m._roomId === roomId)
    loadMessages(roomId)
    setupEcho(roomId)
  }
})

// 키보드가 열리고 닫혀 메시지 영역 높이가 바뀌면 맨 아래(최신 메시지)로
watch(keyboardOpen, () => scrollToBottom())

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
      applyPins(data)
    } catch {}
  }, 5000)
}

onMounted(() => {
  startPolling(); window.addEventListener('resize', onWinResize)
  pinRotateTimer = setInterval(() => { if (roomPins.value.length > 1) pinIdx.value++ }, 4000)
})
onUnmounted(() => {
  window.removeEventListener('resize', onWinResize)
  if (pinRotateTimer) clearInterval(pinRotateTimer)
  if (pollTimer) clearInterval(pollTimer)
  Object.keys(echoChannels).forEach(cleanupEcho)
})
</script>

<style scoped>
.safe-top { padding-top: env(safe-area-inset-top, 0px); }
.safe-bottom { padding-bottom: env(safe-area-inset-bottom, 0px); }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
.pinfade-enter-active, .pinfade-leave-active { transition: opacity .25s ease, transform .25s ease; }
.pinfade-enter-from { opacity: 0; transform: translateY(6px); }
.pinfade-leave-to { opacity: 0; transform: translateY(-6px); }
.scrollbar-hide::-webkit-scrollbar { display: none; }
</style>
