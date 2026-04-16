<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">

    <div class="grid grid-cols-12 gap-4">
      <!-- 왼쪽: 지역 채팅방 목록 (모바일: 채팅방 선택 전에만 표시) -->
      <div class="col-span-12 lg:col-span-3" :class="{ 'hidden': activeRoom && isMobile }">
        <div class="flex items-center justify-between mb-4">
          <h1 class="text-xl font-black text-gray-800">💬 채팅</h1>
          <button @click="showCreate = true" class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-lg text-sm hover:bg-amber-500">+ 새 채팅</button>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">🌐 공개 채팅방</div>
          <div v-if="loading" class="py-4 text-center text-xs text-gray-400">로딩중...</div>
          <button v-for="room in rooms" :key="room.id" @click="selectRoom(room)"
            class="w-full text-left px-3 py-2.5 border-b last:border-0 transition text-xs"
            :class="activeRoom?.id === room.id ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">
            <div class="flex items-center justify-between">
              <span class="truncate">{{ room.name }}</span>
              <span v-if="room.messages?.length" class="w-2 h-2 bg-green-400 rounded-full flex-shrink-0"></span>
            </div>
          </button>
          <div v-if="!rooms.length && !loading" class="px-3 py-4 text-xs text-gray-400 text-center">채팅방 없음</div>
        </div>
      </div>

      <!-- 메인: 채팅 창 -->
      <!-- 모바일: fixed 전체화면 / PC: 그리드 내 -->
      <div v-if="!activeRoom && !isMobile" class="col-span-12 lg:col-span-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
          <div class="text-4xl mb-3">💬</div>
          <div class="text-gray-500 font-semibold">채팅방을 선택해주세요</div>
          <div class="text-xs text-gray-400 mt-1">왼쪽에서 지역 채팅방을 클릭하세요</div>
        </div>
      </div>

      <div v-if="activeRoom" :class="isMobile ? 'fixed left-0 right-0 top-0 bg-white flex flex-col' : 'col-span-12 lg:col-span-6'"
        :style="isMobile ? 'bottom: 56px; z-index: 40;' : ''">
        <div :class="isMobile ? 'flex flex-col h-full overflow-hidden' : 'bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden flex flex-col'" :style="isMobile ? '' : 'height: 70vh'">
          <!-- 채팅방 헤더 -->
          <div class="px-4 py-3 border-b bg-amber-50 flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-2">
              <!-- 모바일 뒤로가기 -->
              <button @click="activeRoom = null; activeMessages = []" class="lg:hidden text-amber-700 text-sm font-bold mr-1">←</button>
              <div class="font-bold text-sm text-amber-900">{{ activeRoom.name }}</div>
            </div>
            <span class="text-[10px] text-green-600 bg-green-100 px-2 py-0.5 rounded-full">🟢 공개 채팅방</span>
          </div>

          <!-- 📌 활성 공지 배너 -->
          <div v-if="pinnedAnnouncements.length" class="border-b bg-amber-50/80 px-4 py-2 space-y-1.5 flex-shrink-0 max-h-32 overflow-y-auto">
            <div v-for="a in pinnedAnnouncements" :key="'pin-'+a.id" class="flex items-start gap-2">
              <span class="text-amber-600 flex-shrink-0">📢</span>
              <div class="flex-1 min-w-0">
                <div class="text-xs text-amber-900 font-semibold break-words">{{ cleanAnnounceContent(a.content) }}</div>
                <div class="text-[9px] text-amber-600">⏰ {{ timeRemaining(a.pinned_until) }} 남음</div>
              </div>
            </div>
          </div>

          <!-- 메시지 영역 -->
          <div ref="msgArea" class="flex-1 overflow-y-auto px-4 py-3 space-y-3">
            <div v-for="msg in activeMessages" :key="msg.id"
              class="flex" :class="msg.user_id === auth.user?.id ? 'justify-end' : 'justify-start'">
              <div class="max-w-[75%]">
                <div v-if="msg.user_id !== auth.user?.id" class="text-[10px] mb-0.5 flex items-center gap-1">
                  <span v-if="isAdminUser(msg.user)" class="bg-red-500 text-white px-1.5 py-0.5 rounded-full font-bold">👑 관리자</span>
                  <span v-else class="text-gray-400">{{ msg.user?.nickname || msg.user?.name }}</span>
                </div>
                <!-- 이미지 메시지 -->
                <div v-if="msg.type === 'image' && msg.file_url" class="rounded-xl overflow-hidden max-w-[160px]"
                  :class="isAdminUser(msg.user) ? 'border-2 border-red-300' : ''">
                  <img :src="msg.file_url" @click="lightboxSrc = msg.file_url"
                    class="block w-full h-auto cursor-pointer hover:opacity-90 transition" />
                  <div v-if="msg.content" class="px-2 py-1 text-xs bg-gray-50">{{ msg.content }}</div>
                </div>
                <!-- 압축파일(file) 메시지 -->
                <a v-else-if="msg.type === 'file' && msg.file_url" :href="msg.file_url" target="_blank" download
                  class="flex items-center gap-2 px-3 py-2 rounded-xl text-sm bg-blue-50 border border-blue-200 hover:bg-blue-100 transition no-underline"
                  :class="msg.user_id === auth.user?.id ? 'text-amber-900' : 'text-gray-800'">
                  <span class="text-xl">📦</span>
                  <div class="flex-1 min-w-0">
                    <div class="text-xs font-semibold truncate">{{ msg.content || '파일' }}</div>
                    <div class="text-[10px] text-blue-600">📥 다운로드</div>
                  </div>
                </a>
                <!-- 일반 텍스트 메시지 -->
                <div v-else class="px-3 py-2 rounded-xl text-sm"
                  :class="[
                    msg.user_id === auth.user?.id ? 'bg-amber-400 text-amber-900' : (isAdminUser(msg.user) ? 'bg-red-50 text-red-900 border border-red-200' : 'bg-gray-100 text-gray-800')
                  ]">
                  {{ msg.content }}
                </div>
                <div class="text-[9px] text-gray-300 mt-0.5" :class="msg.user_id === auth.user?.id ? 'text-right' : ''">
                  {{ formatTime(msg.created_at) }}
                </div>
              </div>
            </div>
            <div v-if="!activeMessages.length" class="text-center py-8 text-sm text-gray-400">첫 메시지를 보내보세요! 👋</div>
          </div>

          <!-- 선택된 파일 미리보기 (다중) -->
          <div v-if="selectedFiles.length" class="border-t px-3 py-2 bg-blue-50 flex-shrink-0">
            <div class="flex gap-2 overflow-x-auto">
              <div v-for="(item, idx) in selectedFiles" :key="idx"
                class="flex-shrink-0 relative group">
                <div v-if="item.type === 'image'" class="w-14 h-14 rounded overflow-hidden border border-blue-300 bg-white">
                  <img :src="item.preview" class="w-full h-full object-cover" />
                </div>
                <div v-else class="w-14 h-14 rounded border border-blue-300 bg-white flex flex-col items-center justify-center p-1">
                  <span class="text-lg">📦</span>
                  <span class="text-[8px] text-gray-600 truncate w-full text-center">{{ item.file.name.split('.').pop().toUpperCase() }}</span>
                </div>
                <button @click="removeSelectedFile(idx)"
                  class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-4 h-4 text-[9px] flex items-center justify-center font-bold opacity-0 group-hover:opacity-100 transition">✕</button>
                <div class="text-[8px] text-gray-500 text-center mt-0.5 max-w-[56px] truncate" :title="item.file.name">{{ formatSize(item.file.size) }}</div>
              </div>
            </div>
            <div class="text-[9px] text-blue-700 mt-1">
              📎 {{ selectedFiles.length }}개 선택됨 · 이미지는 자동 압축됨
              <button @click="clearFiles" class="ml-2 text-red-500 font-bold">모두 취소</button>
            </div>
          </div>

          <!-- 입력 (아이폰 safe-area 대응) -->
          <div class="border-t px-3 py-2 flex-shrink-0" style="padding-bottom: max(0.5rem, env(safe-area-inset-bottom));">
            <form @submit.prevent="sendMsg" class="flex gap-2 items-center">
              <label class="bg-gray-100 text-gray-600 w-9 h-9 flex items-center justify-center rounded-full text-sm hover:bg-gray-200 cursor-pointer flex-shrink-0" :class="!auth.isLoggedIn ? 'opacity-50 cursor-not-allowed' : ''" title="이미지·압축파일 첨부">
                📎
                <input type="file" accept="image/*,.zip,.rar,.7z,.tar,.gz,.tgz,application/zip,application/x-rar-compressed,application/x-7z-compressed,application/gzip" multiple @change="onSelectFiles" class="hidden" :disabled="!auth.isLoggedIn" />
              </label>
              <input v-model="newMsg" type="text" :placeholder="auth.isLoggedIn ? '메시지 입력...' : '로그인 후 참여 가능'" :disabled="!auth.isLoggedIn"
                class="flex-1 min-w-0 border rounded-full px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 outline-none disabled:bg-gray-100" />
              <button type="submit" :disabled="(!newMsg.trim() && !selectedFiles.length) || !auth.isLoggedIn || sending"
                class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-full text-sm hover:bg-amber-500 disabled:opacity-50 flex-shrink-0 whitespace-nowrap">{{ sending ? '...' : '전송' }}</button>
            </form>
          </div>
        </div>
      </div>

      <!-- 오른쪽: 다른 채팅방 눈팅 -->
      <div class="col-span-12 lg:col-span-3 hidden lg:block space-y-3">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">👀 다른 채팅방 엿보기</div>
          <div v-for="room in peekRooms" :key="room.id" class="px-3 py-2 border-b last:border-0">
            <button @click="selectRoom(room)" class="text-left w-full">
              <div class="text-xs font-semibold text-gray-700 truncate">{{ room.name }}</div>
              <div v-if="room.lastMsg" class="text-[10px] text-gray-400 truncate mt-0.5">
                {{ room.lastMsg.user?.name }}: {{ room.lastMsg.content }}
              </div>
            </button>
          </div>
          <div v-if="!peekRooms.length" class="px-3 py-3 text-xs text-gray-400 text-center">채팅방 없음</div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3">
          <div class="font-bold text-xs text-gray-800 mb-2">📢 채팅 안내</div>
          <div class="text-[10px] text-gray-500 space-y-1">
            <div>• 누구나 참여할 수 있는 공개 채팅방입니다</div>
            <div>• 로그인 후 메시지를 보낼 수 있어요</div>
            <div>• 욕설/광고는 자동 차단됩니다</div>
          </div>
        </div>
      </div>
    </div>

    <!-- 🖼️ 이미지 라이트박스 -->
    <div v-if="lightboxSrc" class="fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-4" @click="lightboxSrc = null">
      <img :src="lightboxSrc" class="max-w-full max-h-full object-contain" />
      <button class="absolute top-4 right-4 text-white text-3xl">✕</button>
    </div>

    <!-- 새 채팅 모달 -->
    <div v-if="showCreate" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" @click.self="showCreate = false">
      <div class="bg-white rounded-xl p-5 w-full max-w-sm shadow-xl">
        <h3 class="font-bold text-gray-800 mb-3">새 채팅방</h3>
        <input v-model="newRoomName" type="text" placeholder="채팅방 이름" class="w-full border rounded-lg px-3 py-2 text-sm mb-3 focus:ring-2 focus:ring-amber-400 outline-none" />
        <div class="flex gap-2">
          <button @click="createRoom" class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-lg text-sm flex-1 hover:bg-amber-500">만들기</button>
          <button @click="showCreate = false" class="text-gray-500 px-4 py-2">취소</button>
        </div>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'
import { compressImage, isImage, isArchive } from '../../utils/imageCompress'

const router = useRouter()
const auth = useAuthStore()
const windowWidth = ref(window.innerWidth)
const isMobile = computed(() => windowWidth.value < 1024)
const rooms = ref([])
const activeRoom = ref(null)
const activeMessages = ref([])
const pinnedAnnouncements = ref([])
const loading = ref(true)
const showCreate = ref(false)
const newRoomName = ref('')
const newMsg = ref('')
const msgArea = ref(null)
const selectedFiles = ref([])   // [{file, preview, type}]
const sending = ref(false)
const lightboxSrc = ref(null)

function isAdminUser(u) {
  return u && ['admin', 'super_admin'].includes(u.role)
}

function cleanAnnounceContent(content) {
  if (!content) return ''
  return content.replace(/^📢\s*\[공지\]\s*/, '')
}

function timeRemaining(until) {
  if (!until) return '-'
  const diff = new Date(until) - new Date()
  if (diff <= 0) return '만료됨'
  const m = Math.floor(diff / 60000)
  if (m < 60) return `${m}분`
  const h = Math.floor(m / 60)
  const mm = m % 60
  if (h < 24) return mm > 0 ? `${h}시간 ${mm}분` : `${h}시간`
  const d = Math.floor(h / 24)
  const hh = h % 24
  return hh > 0 ? `${d}일 ${hh}시간` : `${d}일`
}

async function onSelectFiles(e) {
  const files = Array.from(e.target.files || [])
  e.target.value = '' // 같은 파일 재선택 가능
  if (!files.length) return

  const rejected = []
  for (const file of files) {
    // 이미지: 압축 (내부에서 자동으로 1600px, JPEG 0.8 품질)
    if (isImage(file)) {
      try {
        const compressed = await compressImage(file, { maxDim: 1600, quality: 0.8 })
        // 압축 후에도 10MB 초과면 거절
        if (compressed.size > 10 * 1024 * 1024) {
          rejected.push(file.name + ' (압축 후에도 10MB 초과)')
          continue
        }
        selectedFiles.value.push({
          file: compressed,
          preview: URL.createObjectURL(compressed),
          type: 'image',
          originalSize: file.size,
        })
      } catch (err) {
        rejected.push(file.name + ' (압축 실패)')
      }
      continue
    }
    // 압축파일만 허용
    if (isArchive(file)) {
      if (file.size > 10 * 1024 * 1024) {
        rejected.push(file.name + ' (10MB 초과)')
        continue
      }
      selectedFiles.value.push({
        file,
        preview: null,
        type: 'archive',
        originalSize: file.size,
      })
      continue
    }
    // 그 외(문서 등) 거부
    rejected.push(file.name + ' (이미지 또는 압축파일만 가능)')
  }

  if (rejected.length) {
    alert('다음 파일은 업로드할 수 없습니다:\n\n• ' + rejected.join('\n• '))
  }
}

function removeSelectedFile(idx) {
  const item = selectedFiles.value[idx]
  if (item?.preview) URL.revokeObjectURL(item.preview)
  selectedFiles.value.splice(idx, 1)
}

function clearFiles() {
  selectedFiles.value.forEach(f => { if (f.preview) URL.revokeObjectURL(f.preview) })
  selectedFiles.value = []
}

function formatSize(bytes) {
  if (!bytes) return ''
  if (bytes < 1024) return bytes + 'B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + 'KB'
  return (bytes / 1024 / 1024).toFixed(1) + 'MB'
}

// 눈팅용: 현재 선택 외 다른 방 3개 + 마지막 메시지
const peekRooms = computed(() => {
  return rooms.value
    .filter(r => r.id !== activeRoom.value?.id)
    .slice(0, 4)
    .map(r => ({
      ...r,
      lastMsg: r.messages?.[0] || null,
    }))
})

function formatTime(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  const now = new Date()
  const diff = now - d
  if (diff < 60000) return '방금'
  if (diff < 3600000) return Math.floor(diff / 60000) + '분 전'
  if (d.toDateString() === now.toDateString()) return d.toLocaleTimeString('ko-KR', { hour: '2-digit', minute: '2-digit' })
  return d.toLocaleDateString('ko-KR', { month: 'short', day: 'numeric' }) + ' ' + d.toLocaleTimeString('ko-KR', { hour: '2-digit', minute: '2-digit' })
}

let currentChannel = null

function unsubscribeChannel() {
  if (currentChannel && window.Echo) {
    try { window.Echo.leaveChannel('chat.' + currentChannel) } catch (e) {}
    currentChannel = null
  }
}

function subscribeToRoom(roomId) {
  unsubscribeChannel()
  if (!window.Echo) return
  currentChannel = roomId
  window.Echo.channel('chat.' + roomId)
    .listen('.message.sent', (payload) => {
      // 가드: 현재 활성 방의 메시지만 받음 (다른 방 채널 잔존 방지)
      if (Number(activeRoom.value?.id) !== Number(roomId)) return
      if (payload.chat_room_id && Number(payload.chat_room_id) !== Number(roomId)) return
      // 중복 방지: 이미 같은 id가 있으면 무시
      if (activeMessages.value.some(m => m.id === payload.id)) return
      activeMessages.value.push(payload)
      // 공지면 핀 배너에도 반영
      if (payload.type === 'system' && payload.pinned_until) {
        pinnedAnnouncements.value = [payload, ...pinnedAnnouncements.value.filter(p => p.id !== payload.id)]
      }
      nextTick(() => {
        if (msgArea.value) msgArea.value.scrollTop = msgArea.value.scrollHeight
      })
    })
}

let selectRoomSeq = 0
async function selectRoom(room) {
  const seq = ++selectRoomSeq
  activeRoom.value = room
  // 클릭 즉시 이전 메시지 초기화 (이전 방 메시지가 잠깐 보이는 문제 방지)
  activeMessages.value = []
  pinnedAnnouncements.value = []
  clearFiles()
  subscribeToRoom(room.id)
  try {
    const { data } = await axios.get(`/api/chat/rooms/${room.id}/messages`, {
      params: { _ts: Date.now() }, // 캐시 방지
    })
    // 도착 사이 다른 방으로 이동했으면 결과 무시 (race condition)
    if (seq !== selectRoomSeq || activeRoom.value?.id !== room.id) return
    const msgs = (data.data?.data || data.data || []).reverse()
    // 안전: chat_room_id 가 다른 메시지 필터 (== 로 string/number 모두 매칭)
    const rid = Number(room.id)
    activeMessages.value = msgs.filter(m => !m.chat_room_id || Number(m.chat_room_id) === rid)
    pinnedAnnouncements.value = data.pinned || []
    await nextTick()
    if (msgArea.value) msgArea.value.scrollTop = msgArea.value.scrollHeight
  } catch (e) {
    if (seq === selectRoomSeq) {
      console.warn('[chat] failed to load messages for room', room.id, e?.message)
    }
  }
}

async function sendMsg() {
  if ((!newMsg.value.trim() && !selectedFiles.value.length) || !auth.isLoggedIn || !activeRoom.value) return
  sending.value = true
  try {
    const fd = new FormData()
    if (newMsg.value.trim()) fd.append('content', newMsg.value)
    selectedFiles.value.forEach(item => fd.append('files[]', item.file))
    const { data } = await axios.post(`/api/chat/rooms/${activeRoom.value.id}/messages`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    // 응답의 messages 배열이 있으면 여러 개 push, 없으면 data 하나
    const msgs = data.messages || (data.data ? [data.data] : [])
    msgs.forEach(m => {
      if (!activeMessages.value.some(x => x.id === m.id)) activeMessages.value.push(m)
    })
    newMsg.value = ''
    clearFiles()
    await nextTick()
    if (msgArea.value) msgArea.value.scrollTop = msgArea.value.scrollHeight
  } catch (e) {
    const err = e.response?.data
    const msg = err?.message || err?.errors?.['files.0']?.[0] || err?.errors?.files?.[0] || '전송 실패'
    alert(msg)
  }
  sending.value = false
}

async function createRoom() {
  if (!newRoomName.value.trim()) return
  try {
    const { data } = await axios.post('/api/chat/rooms', { name: newRoomName.value, type: 'group' })
    rooms.value.unshift(data.data)
    showCreate.value = false
    newRoomName.value = ''
    selectRoom(data.data)
  } catch {}
}

const onResize = () => { windowWidth.value = window.innerWidth }

onMounted(async () => {
  window.addEventListener('resize', onResize)
  try {
    const { data } = await axios.get('/api/chat/rooms')
    rooms.value = data.data || []
    // PC에서만 첫 번째 방 자동 선택 (모바일은 목록 먼저 보여줌)
    if (rooms.value.length && !isMobile.value) selectRoom(rooms.value[0])
  } catch {}
  loading.value = false
})

onUnmounted(() => {
  unsubscribeChannel()
  window.removeEventListener('resize', onResize)
})
</script>
