<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="max-w-[1200px] mx-auto px-4 pt-4">
      <div class="bg-gradient-to-r from-blue-600 to-blue-500 text-white px-6 py-5 rounded-2xl">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-xl font-black">💬 채팅방</h1>
            <p class="text-blue-100 text-sm mt-0.5">실시간 커뮤니티 채팅</p>
          </div>
          <div class="flex gap-2">
            <button v-if="activeRoom" @click="activeRoom = null" class="lg:hidden bg-white/20 text-white px-3 py-1.5 rounded-lg text-sm font-bold">
              ← 목록
            </button>
            <button @click="showCreateModal = true" class="bg-white text-blue-600 px-4 py-2 rounded-lg text-sm font-bold hover:bg-blue-50 flex-shrink-0">
              + 채팅방 개설
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="max-w-[1200px] mx-auto px-4 py-4">
      <!-- Category tabs -->
      <div class="flex gap-2 mb-4 overflow-x-auto pb-1" :class="{ 'hidden lg:flex': activeRoom }">
        <button v-for="cat in categories" :key="cat.id"
          @click="activeCat = cat.id"
          class="flex-shrink-0 px-4 py-2 rounded-full text-sm font-semibold transition"
          :class="activeCat === cat.id ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:border-blue-300'">
          {{ cat.icon }} {{ cat.label }}
        </button>
      </div>

      <!-- Main layout: rooms + chat panel -->
      <div class="flex gap-4" style="min-height: calc(100vh - 220px);">

        <!-- Left: Room cards -->
        <div class="w-full lg:w-[360px] flex-shrink-0 space-y-3 overflow-y-auto"
          :class="{ 'hidden lg:block': activeRoom }">

          <!-- Room cards -->
          <div v-if="loading" class="text-center py-12 text-gray-400">불러오는 중...</div>
          <template v-else>
            <div v-for="room in filteredRooms" :key="room.id || room.slug"
              @click="openRoom(room)"
              class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition cursor-pointer border-2"
              :class="activeRoom?.slug === room.slug ? 'border-blue-500' : 'border-transparent'">
              <!-- Card banner -->
              <div class="h-16 flex items-center px-4 gap-3"
                :class="room.type === 'regional'
                  ? 'bg-gradient-to-r from-blue-400 to-cyan-400'
                  : room.type === 'theme'
                    ? 'bg-gradient-to-r from-purple-400 to-pink-400'
                    : 'bg-gradient-to-r from-green-400 to-emerald-400'">
                <span class="text-2xl">{{ room.icon || '💬' }}</span>
                <div class="text-white min-w-0">
                  <p class="font-bold text-sm truncate">{{ room.name }}</p>
                  <p class="text-white/70 text-xs truncate">{{ room.description }}</p>
                </div>
              </div>
              <!-- Card footer -->
              <div class="px-4 py-2.5 flex items-center justify-between">
                <div class="flex items-center gap-1.5">
                  <span class="w-2 h-2 rounded-full bg-green-400"></span>
                  <span class="text-xs text-gray-400">실시간</span>
                </div>
                <span class="text-xs text-blue-500 font-semibold">입장 →</span>
              </div>
            </div>

            <!-- Empty state -->
            <div v-if="filteredRooms.length === 0" class="text-center py-12 text-gray-400">
              <p class="text-3xl mb-2">🔍</p>
              <p class="text-sm">해당 카테고리의 채팅방이 없습니다</p>
            </div>
          </template>
        </div>

        <!-- Right: Chat panel -->
        <div class="flex-1 min-w-0" :class="{ 'hidden lg:flex': !activeRoom, 'flex': activeRoom }">
          <!-- No room selected -->
          <div v-if="!activeRoom" class="w-full bg-white rounded-2xl shadow-sm flex flex-col items-center justify-center text-gray-400 py-24">
            <p class="text-5xl mb-4">💬</p>
            <p class="font-semibold text-gray-600 mb-1">채팅방을 선택하세요</p>
            <p class="text-sm">왼쪽에서 채팅방을 클릭하면 여기에 대화가 표시됩니다</p>
          </div>

          <!-- Active chat -->
          <div v-else class="w-full bg-white rounded-2xl shadow-sm flex flex-col overflow-hidden" style="height: calc(100vh - 220px);">
            <!-- Chat header -->
            <div class="px-4 py-3 border-b flex items-center gap-3 flex-shrink-0">
              <span class="text-xl">{{ activeRoom.icon || '💬' }}</span>
              <div class="flex-1 min-w-0">
                <p class="font-bold text-gray-800 truncate text-sm">{{ activeRoom.name }}</p>
                <p class="text-xs text-gray-400 truncate">{{ activeRoom.description }}</p>
              </div>
              <span class="flex items-center gap-1 text-xs" :class="chatConnected ? 'text-green-500' : 'text-gray-400'">
                <span class="w-2 h-2 rounded-full" :class="chatConnected ? 'bg-green-400' : 'bg-gray-300'"></span>
                {{ chatConnected ? '실시간' : '연결 중' }}
              </span>
            </div>

            <!-- Messages -->
            <div ref="msgContainer" class="flex-1 overflow-y-auto px-4 py-3 space-y-3 min-h-0 bg-gray-50">
              <div v-if="chatLoading" class="text-center py-8 text-gray-400 text-sm">불러오는 중...</div>
              <div v-else-if="!chatMessages.length" class="text-center py-12 text-gray-400">
                <p class="text-3xl mb-2">💬</p>
                <p class="text-sm">첫 메시지를 남겨보세요!</p>
              </div>
              <div v-for="msg in chatMessages" :key="msg.id" class="flex gap-2"
                :class="isMe(msg) ? 'justify-end' : 'justify-start'">
                <template v-if="!isMe(msg)">
                  <div class="w-7 h-7 rounded-full bg-blue-100 flex items-center justify-center text-xs font-bold text-blue-700 flex-shrink-0 mt-1">
                    {{ (msg.user?.name || '?')[0] }}
                  </div>
                  <div class="max-w-[70%]">
                    <p class="text-xs text-gray-500 mb-0.5 ml-1">{{ msg.user?.name || '?' }}</p>
                    <div class="bg-white rounded-2xl rounded-tl-none px-3 py-2 shadow-sm text-gray-800 text-sm">{{ msg.message }}</div>
                    <p class="text-xs text-gray-400 mt-0.5 ml-1">{{ formatTime(msg.created_at) }}</p>
                  </div>
                </template>
                <template v-else>
                  <div class="max-w-[70%]">
                    <div class="bg-blue-500 text-white rounded-2xl rounded-tr-none px-3 py-2 shadow-sm text-sm">{{ msg.message }}</div>
                    <p class="text-xs text-gray-400 mt-0.5 text-right">{{ formatTime(msg.created_at) }}</p>
                  </div>
                </template>
              </div>
            </div>

            <!-- Input -->
            <div class="border-t px-3 py-2 flex gap-2 flex-shrink-0 bg-white">
              <input v-model="chatInput" @keyup.enter="sendMessage" type="text"
                placeholder="메시지를 입력하세요..." maxlength="500" :disabled="!auth.isLoggedIn"
                class="flex-1 border border-gray-200 rounded-full px-4 py-2 text-sm focus:outline-none focus:border-blue-400 bg-gray-50" />
              <label class="p-2 text-gray-400 hover:text-blue-500 cursor-pointer flex items-center">
                <span class="text-lg">📎</span>
                <input type="file" class="hidden" @change="attachFile" accept="image/*,.pdf,.doc,.docx,.zip" />
              </label>
              <button @click="sendMessage" :disabled="!chatInput.trim() || chatSending"
                class="bg-blue-500 text-white rounded-full px-4 py-2 text-sm font-semibold disabled:opacity-50 flex-shrink-0">
                전송
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 채팅방 개설 모달 -->
    <Transition name="fade">
      <div v-if="showCreateModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 px-4" @click.self="showCreateModal = false">
        <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl overflow-hidden">
          <div class="bg-gradient-to-r from-blue-600 to-blue-500 px-6 py-4 text-white">
            <h2 class="font-black text-lg">💬 채팅방 개설</h2>
            <p class="text-blue-100 text-xs mt-0.5">친구를 초대해서 대화를 시작하세요</p>
          </div>
          <div class="p-5 space-y-4">
            <div>
              <label class="block text-xs text-gray-500 mb-1 font-semibold">채팅방 이름 *</label>
              <input v-model="newRoom.name" type="text" maxlength="30" placeholder="예: 주말 모임, 골프 친구들..."
                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400" />
            </div>

            <!-- 친구 초대 -->
            <div>
              <label class="block text-xs text-gray-500 mb-1 font-semibold">친구 초대</label>
              <input v-model="friendSearch" type="text" placeholder="친구 이름 검색..."
                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 mb-2"
                @input="searchFriends" />

              <!-- 선택된 친구 -->
              <div v-if="selectedFriends.length" class="flex flex-wrap gap-1.5 mb-2">
                <span v-for="f in selectedFriends" :key="f.id"
                  class="bg-blue-100 text-blue-700 text-xs px-2.5 py-1 rounded-full flex items-center gap-1">
                  {{ f.name }}
                  <button @click="removeFriend(f.id)" class="text-blue-400 hover:text-red-500 font-bold">×</button>
                </span>
              </div>

              <!-- 검색 결과 -->
              <div v-if="friendResults.length" class="border border-gray-100 rounded-xl max-h-40 overflow-y-auto">
                <div v-for="f in friendResults" :key="f.id"
                  @click="addFriend(f)"
                  class="flex items-center gap-2 px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                  <div class="w-7 h-7 rounded-full bg-blue-100 flex items-center justify-center text-xs font-bold text-blue-600">
                    {{ (f.name || '?')[0] }}
                  </div>
                  <span class="text-sm text-gray-800">{{ f.name }}</span>
                  <span class="text-xs text-gray-400">@{{ f.username }}</span>
                </div>
              </div>
              <p v-if="friendSearch && !friendResults.length && !friendSearching" class="text-xs text-gray-400 mt-1">검색 결과 없음</p>
            </div>

            <div class="flex gap-3 pt-2">
              <button @click="showCreateModal = false" class="flex-1 border border-gray-200 text-gray-600 py-2.5 rounded-xl font-semibold text-sm">취소</button>
              <button @click="createRoom" :disabled="!newRoom.name.trim() || creating"
                class="flex-1 bg-blue-600 text-white py-2.5 rounded-xl font-bold text-sm disabled:opacity-50 hover:bg-blue-700">
                {{ creating ? '생성 중...' : '개설하기' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'

const auth = useAuthStore()

// Categories
const activeCat = ref('all')
const categories = [
  { id: 'all',      icon: '🌐', label: '전체' },
  { id: 'regional', icon: '📍', label: '지역별' },
  { id: 'theme',    icon: '🎨', label: '테마별' },
  { id: 'friend',   icon: '👥', label: '내 친구' },
]

// Rooms
const rooms = ref([])
const loading = ref(true)
const activeRoom = ref(null)

const filteredRooms = computed(() => {
  if (activeCat.value === 'all') return rooms.value
  return rooms.value.filter(r => r.type === activeCat.value)
})

// Chat state
const chatMessages  = ref([])
const chatInput     = ref('')
const chatSending   = ref(false)
const chatLoading   = ref(false)
const chatConnected = ref(false)
const msgContainer  = ref(null)
let chatChannel = null

function isMe(msg) {
  return msg.user?.id === auth.user?.id || msg.user_id === auth.user?.id
}

function formatTime(dt) {
  if (!dt) return ''
  return new Date(dt).toLocaleTimeString('ko-KR', { hour: '2-digit', minute: '2-digit' })
}

async function scrollBottom() {
  await nextTick()
  if (msgContainer.value) msgContainer.value.scrollTop = msgContainer.value.scrollHeight
}

async function openRoom(room) {
  if (activeRoom.value?.slug === room.slug) return
  // Leave previous channel
  if (activeRoom.value && chatChannel) {
    window.Echo?.leaveChannel(`chat.${activeRoom.value.id}`)
    chatChannel = null
  }
  activeRoom.value = room
  chatMessages.value = []
  chatLoading.value = true
  chatConnected.value = false

  try {
    const { data } = await axios.get(`/api/chat/rooms/${room.slug}`)
    if (data.room) activeRoom.value = { ...room, ...data.room }
    chatMessages.value = data.messages ?? []
    await scrollBottom()
  } catch (e) {
    console.error(e)
  } finally {
    chatLoading.value = false
  }

  // Subscribe to realtime
  if (window.Echo && activeRoom.value?.id) {
    chatChannel = window.Echo.channel(`chat.${activeRoom.value.id}`)
    chatChannel
      .listen('.message.sent', (msg) => {
        if (!chatMessages.value.some(m => m.id === msg.id)) {
          chatMessages.value.push(msg)
          scrollBottom()
        }
      })
      .subscribed(() => { chatConnected.value = true })
      .error(() => { chatConnected.value = false })
  }
}

async function sendMessage() {
  if (!chatInput.value.trim() || chatSending.value || !activeRoom.value) return
  const text = chatInput.value.trim()
  chatInput.value = ''
  chatSending.value = true
  try {
    const socketId = window.Echo?.socketId?.()
    const headers = socketId ? { 'X-Socket-ID': socketId } : {}
    const { data } = await axios.post(`/api/chat/rooms/${activeRoom.value.slug}/messages`, { message: text }, { headers })
    chatMessages.value.push(data)
    await scrollBottom()
  } catch (e) {
    chatInput.value = text
  } finally {
    chatSending.value = false
  }
}

async function attachFile(e) {
  const file = e.target.files[0]
  if (!file || !auth.isLoggedIn || !activeRoom.value) return
  const fd = new FormData()
  fd.append('file', file)
  chatSending.value = true
  try {
    const { data } = await axios.post(`/api/chat/rooms/${activeRoom.value.slug}/messages`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    chatMessages.value.push(data)
    await scrollBottom()
  } catch { alert('파일 전송 실패') } finally {
    chatSending.value = false
    e.target.value = ''
  }
}

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/chat/rooms')
    rooms.value = data
  } catch {} finally {
    loading.value = false
  }
})

onUnmounted(() => {
  if (activeRoom.value?.id) window.Echo?.leaveChannel(`chat.${activeRoom.value.id}`)
})

// ── 채팅방 개설 모달 ──
const showCreateModal = ref(false)
const creating = ref(false)
const newRoom = ref({ name: '' })
const friendSearch = ref('')
const friendResults = ref([])
const selectedFriends = ref([])
const friendSearching = ref(false)
let searchTimer = null

function searchFriends() {
  clearTimeout(searchTimer)
  if (!friendSearch.value.trim()) { friendResults.value = []; return }
  searchTimer = setTimeout(async () => {
    friendSearching.value = true
    try {
      const { data } = await axios.get('/api/friends/search', { params: { q: friendSearch.value } })
      friendResults.value = data.filter(u => !selectedFriends.value.some(s => s.id === u.id))
    } catch {} finally { friendSearching.value = false }
  }, 300)
}

function addFriend(f) {
  if (!selectedFriends.value.some(s => s.id === f.id)) {
    selectedFriends.value.push(f)
  }
  friendResults.value = friendResults.value.filter(r => r.id !== f.id)
  friendSearch.value = ''
}

function removeFriend(id) {
  selectedFriends.value = selectedFriends.value.filter(f => f.id !== id)
}

async function createRoom() {
  if (!newRoom.value.name.trim()) return
  creating.value = true
  try {
    // Create room (API will handle this - for now use a simple POST)
    const { data } = await axios.post('/api/chat/rooms', {
      name: newRoom.value.name,
      type: 'friend',
      invite_users: selectedFriends.value.map(f => f.id),
    })
    // Send invite messages to selected friends
    for (const f of selectedFriends.value) {
      try {
        await axios.post('/api/messages', {
          recipient_id: f.id,
          content: `💬 "${newRoom.value.name}" 채팅방에 초대되었습니다! 채팅 페이지에서 참여해주세요.`,
        })
      } catch {}
    }
    // Add to rooms list and open
    if (data) {
      rooms.value.unshift(data)
      openRoom(data)
    }
    showCreateModal.value = false
    newRoom.value = { name: '' }
    selectedFriends.value = []
  } catch (e) {
    alert(e?.response?.data?.message || '채팅방 개설 실패')
  } finally {
    creating.value = false
  }
}
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
