<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-xl font-black text-gray-800">💬 채팅</h1>
      <button @click="showCreate = true" class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-lg text-sm hover:bg-amber-500">+ 새 채팅</button>
    </div>

    <div class="grid grid-cols-12 gap-4">
      <!-- 왼쪽: 지역 채팅방 목록 -->
      <div class="col-span-12 lg:col-span-3">
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
      <div class="col-span-12 lg:col-span-6">
        <div v-if="!activeRoom" class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
          <div class="text-4xl mb-3">💬</div>
          <div class="text-gray-500 font-semibold">채팅방을 선택해주세요</div>
          <div class="text-xs text-gray-400 mt-1">왼쪽에서 지역 채팅방을 클릭하세요</div>
        </div>

        <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden flex flex-col" style="height: 70vh;">
          <!-- 채팅방 헤더 -->
          <div class="px-4 py-3 border-b bg-amber-50 flex items-center justify-between flex-shrink-0">
            <div class="font-bold text-sm text-amber-900">{{ activeRoom.name }}</div>
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

          <!-- 선택된 이미지 미리보기 -->
          <div v-if="selectedImage" class="border-t px-4 py-2 flex items-center gap-2 bg-blue-50 flex-shrink-0">
            <img :src="imagePreview" class="w-12 h-12 object-cover rounded" />
            <span class="text-xs text-gray-600 truncate flex-1">{{ selectedImage.name }}</span>
            <button @click="clearImage" class="text-red-500 text-xs font-bold">✕ 취소</button>
          </div>

          <!-- 입력 -->
          <div class="border-t px-4 py-3 flex-shrink-0">
            <form @submit.prevent="sendMsg" class="flex gap-2 items-center">
              <label class="bg-gray-100 text-gray-600 w-10 h-10 flex items-center justify-center rounded-full text-base hover:bg-gray-200 cursor-pointer flex-shrink-0" :class="!auth.isLoggedIn ? 'opacity-50 cursor-not-allowed' : ''" title="이미지 첨부">
                📷
                <input type="file" accept="image/*" @change="onSelectImage" class="hidden" :disabled="!auth.isLoggedIn" />
              </label>
              <input v-model="newMsg" type="text" :placeholder="auth.isLoggedIn ? '메시지 입력...' : '로그인 후 참여 가능'" :disabled="!auth.isLoggedIn"
                class="flex-1 border rounded-full px-4 py-2 text-sm focus:ring-2 focus:ring-amber-400 outline-none disabled:bg-gray-100" />
              <button type="submit" :disabled="(!newMsg.trim() && !selectedImage) || !auth.isLoggedIn || sending"
                class="bg-amber-400 text-amber-900 font-bold px-5 py-2 rounded-full text-sm hover:bg-amber-500 disabled:opacity-50">{{ sending ? '전송중' : '전송' }}</button>
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

const router = useRouter()
const auth = useAuthStore()
const rooms = ref([])
const activeRoom = ref(null)
const activeMessages = ref([])
const pinnedAnnouncements = ref([])
const loading = ref(true)
const showCreate = ref(false)
const newRoomName = ref('')
const newMsg = ref('')
const msgArea = ref(null)
const selectedImage = ref(null)
const imagePreview = ref('')
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

function onSelectImage(e) {
  const file = e.target.files[0]
  if (!file) return
  if (file.size > 10 * 1024 * 1024) {
    alert(`파일이 너무 큽니다 (${(file.size/1024/1024).toFixed(1)}MB). 최대 10MB`)
    e.target.value = ''
    return
  }
  selectedImage.value = file
  imagePreview.value = URL.createObjectURL(file)
  e.target.value = ''
}

function clearImage() {
  selectedImage.value = null
  if (imagePreview.value) URL.revokeObjectURL(imagePreview.value)
  imagePreview.value = ''
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

async function selectRoom(room) {
  activeRoom.value = room
  clearImage()
  subscribeToRoom(room.id)
  try {
    const { data } = await axios.get(`/api/chat/rooms/${room.id}/messages`)
    activeMessages.value = (data.data?.data || data.data || []).reverse()
    pinnedAnnouncements.value = data.pinned || []
    await nextTick()
    if (msgArea.value) msgArea.value.scrollTop = msgArea.value.scrollHeight
  } catch {}
}

async function sendMsg() {
  if ((!newMsg.value.trim() && !selectedImage.value) || !auth.isLoggedIn || !activeRoom.value) return
  sending.value = true
  try {
    const fd = new FormData()
    if (newMsg.value.trim()) fd.append('content', newMsg.value)
    if (selectedImage.value) fd.append('image', selectedImage.value)
    const { data } = await axios.post(`/api/chat/rooms/${activeRoom.value.id}/messages`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    activeMessages.value.push(data.data)
    newMsg.value = ''
    clearImage()
    await nextTick()
    if (msgArea.value) msgArea.value.scrollTop = msgArea.value.scrollHeight
  } catch (e) {
    alert(e.response?.data?.message || e.response?.data?.errors?.image?.[0] || '전송 실패')
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

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/chat/rooms')
    rooms.value = data.data || []
    // 첫 번째 방 자동 선택
    if (rooms.value.length) selectRoom(rooms.value[0])
  } catch {}
  loading.value = false
})

onUnmounted(() => {
  unsubscribeChannel()
})
</script>
