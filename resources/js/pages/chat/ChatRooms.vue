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

          <!-- 메시지 영역 -->
          <div ref="msgArea" class="flex-1 overflow-y-auto px-4 py-3 space-y-3">
            <div v-for="msg in activeMessages" :key="msg.id"
              class="flex" :class="msg.user_id === auth.user?.id ? 'justify-end' : 'justify-start'">
              <div class="max-w-[75%]">
                <div v-if="msg.user_id !== auth.user?.id" class="text-[10px] text-gray-400 mb-0.5">{{ msg.user?.name || msg.user?.nickname }}</div>
                <div class="px-3 py-2 rounded-xl text-sm"
                  :class="msg.user_id === auth.user?.id ? 'bg-amber-400 text-amber-900' : 'bg-gray-100 text-gray-800'">
                  {{ msg.content }}
                </div>
                <div class="text-[9px] text-gray-300 mt-0.5" :class="msg.user_id === auth.user?.id ? 'text-right' : ''">
                  {{ formatTime(msg.created_at) }}
                </div>
              </div>
            </div>
            <div v-if="!activeMessages.length" class="text-center py-8 text-sm text-gray-400">첫 메시지를 보내보세요! 👋</div>
          </div>

          <!-- 입력 -->
          <div class="border-t px-4 py-3 flex-shrink-0">
            <form @submit.prevent="sendMsg" class="flex gap-2">
              <input v-model="newMsg" type="text" :placeholder="auth.isLoggedIn ? '메시지 입력...' : '로그인 후 참여 가능'" :disabled="!auth.isLoggedIn"
                class="flex-1 border rounded-full px-4 py-2 text-sm focus:ring-2 focus:ring-amber-400 outline-none disabled:bg-gray-100" />
              <button type="submit" :disabled="!newMsg.trim() || !auth.isLoggedIn"
                class="bg-amber-400 text-amber-900 font-bold px-5 py-2 rounded-full text-sm hover:bg-amber-500 disabled:opacity-50">전송</button>
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
import { ref, computed, onMounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'

const router = useRouter()
const auth = useAuthStore()
const rooms = ref([])
const activeRoom = ref(null)
const activeMessages = ref([])
const loading = ref(true)
const showCreate = ref(false)
const newRoomName = ref('')
const newMsg = ref('')
const msgArea = ref(null)

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

async function selectRoom(room) {
  activeRoom.value = room
  try {
    const { data } = await axios.get(`/api/chat/rooms/${room.id}/messages`)
    activeMessages.value = (data.data?.data || data.data || []).reverse()
    await nextTick()
    if (msgArea.value) msgArea.value.scrollTop = msgArea.value.scrollHeight
  } catch {}
}

async function sendMsg() {
  if (!newMsg.value.trim() || !auth.isLoggedIn || !activeRoom.value) return
  try {
    const { data } = await axios.post(`/api/chat/rooms/${activeRoom.value.id}/messages`, { content: newMsg.value })
    activeMessages.value.push(data.data)
    newMsg.value = ''
    await nextTick()
    if (msgArea.value) msgArea.value.scrollTop = msgArea.value.scrollHeight
  } catch {}
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
</script>
