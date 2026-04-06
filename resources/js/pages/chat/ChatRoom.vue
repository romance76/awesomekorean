<template>
<div class="min-h-screen bg-gray-50 flex flex-col">
  <!-- 헤더 -->
  <div class="bg-white border-b px-4 py-3 flex items-center gap-3 sticky top-0 z-10">
    <button @click="$router.push('/chat')" class="text-gray-500 hover:text-amber-600">←</button>
    <h1 class="font-bold text-gray-800 text-sm">💬 {{ roomName }}</h1>
  </div>

  <!-- 메시지 영역 -->
  <div ref="msgArea" class="flex-1 overflow-y-auto px-4 py-3 space-y-3">
    <div v-if="loading" class="text-center py-8 text-gray-400">로딩중...</div>
    <div v-for="msg in messages" :key="msg.id"
      class="flex" :class="msg.user_id === auth.user?.id ? 'justify-end' : 'justify-start'">
      <div class="max-w-[70%]">
        <div v-if="msg.user_id !== auth.user?.id" class="text-[10px] text-gray-400 mb-0.5">{{ msg.user?.name }}</div>
        <div class="px-3 py-2 rounded-xl text-sm"
          :class="msg.user_id === auth.user?.id ? 'bg-amber-400 text-amber-900' : 'bg-white border border-gray-200 text-gray-800'">
          {{ msg.content }}
        </div>
        <div class="text-[9px] text-gray-300 mt-0.5" :class="msg.user_id === auth.user?.id ? 'text-right' : ''">
          {{ formatTime(msg.created_at) }}
        </div>
      </div>
    </div>
    <div v-if="!loading && !messages.length" class="text-center py-8 text-gray-400 text-sm">메시지가 없습니다. 첫 메시지를 보내보세요!</div>
  </div>

  <!-- 입력 -->
  <div class="bg-white border-t px-4 py-3">
    <form @submit.prevent="sendMsg" class="flex gap-2">
      <input v-model="newMsg" type="text" placeholder="메시지 입력..." autofocus
        class="flex-1 border rounded-full px-4 py-2 text-sm focus:ring-2 focus:ring-amber-400 outline-none" />
      <button type="submit" :disabled="!newMsg.trim()" class="bg-amber-400 text-amber-900 font-bold px-5 py-2 rounded-full text-sm hover:bg-amber-500 disabled:opacity-50">전송</button>
    </form>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'
const route = useRoute()
const auth = useAuthStore()
const messages = ref([])
const loading = ref(true)
const newMsg = ref('')
const msgArea = ref(null)
const roomName = ref('채팅방')

function formatTime(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  return d.toLocaleTimeString('ko-KR', { hour: '2-digit', minute: '2-digit' })
}

async function sendMsg() {
  if (!newMsg.value.trim()) return
  try {
    const { data } = await axios.post(`/api/chat/rooms/${route.params.id}/messages`, { content: newMsg.value })
    messages.value.push(data.data)
    newMsg.value = ''
    await nextTick()
    if (msgArea.value) msgArea.value.scrollTop = msgArea.value.scrollHeight
  } catch {}
}

let pollTimer = null
async function pollMessages() {
  try {
    const { data } = await axios.get(`/api/chat/rooms/${route.params.id}/messages`)
    const newMsgs = (data.data?.data || data.data || []).reverse()
    if (newMsgs.length !== messages.value.length) {
      messages.value = newMsgs
      await nextTick()
      if (msgArea.value) msgArea.value.scrollTop = msgArea.value.scrollHeight
    }
  } catch {}
}

onMounted(async () => {
  try {
    const { data } = await axios.get(`/api/chat/rooms/${route.params.id}/messages`)
    messages.value = (data.data?.data || data.data || []).reverse()
    await nextTick()
    if (msgArea.value) msgArea.value.scrollTop = msgArea.value.scrollHeight
  } catch {}
  loading.value = false
  // 5초마다 새 메시지 폴링
  pollTimer = setInterval(pollMessages, 5000)
})

import { onUnmounted } from 'vue'
onUnmounted(() => { if (pollTimer) clearInterval(pollTimer) })
</script>
