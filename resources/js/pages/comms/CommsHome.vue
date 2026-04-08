<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-3xl mx-auto px-4 py-5">
    <h1 class="text-xl font-black text-gray-800 mb-4">💬 안심 메시지</h1>

    <!-- 탭: 메시지 / 통화 기록 -->
    <div class="flex gap-2 mb-4">
      <button @click="tab='messages'" :class="tab==='messages' ? 'bg-amber-500 text-white' : 'bg-white text-gray-600 border'" class="px-4 py-2 rounded-lg text-sm font-bold transition">💬 메시지</button>
      <button @click="tab='calls'; loadCalls()" :class="tab==='calls' ? 'bg-amber-500 text-white' : 'bg-white text-gray-600 border'" class="px-4 py-2 rounded-lg text-sm font-bold transition">📞 통화 기록</button>
    </div>

    <!-- 메시지 목록 -->
    <div v-if="tab === 'messages'" class="space-y-2">
      <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
      <div v-else-if="conversations.length === 0" class="text-center py-12 text-gray-400">
        <div class="text-4xl mb-3">💬</div>
        <p>아직 대화가 없습니다</p>
        <p class="text-xs mt-1">친구 목록에서 채팅을 시작해보세요</p>
      </div>
      <div v-for="conv in conversations" :key="conv.id"
        @click="openChat(conv)"
        class="bg-white rounded-xl border p-4 flex items-center gap-3 cursor-pointer hover:bg-amber-50 transition">
        <div class="relative shrink-0">
          <img :src="conv.partner?.avatar || '/images/default-avatar.svg'" class="w-12 h-12 rounded-full object-cover" @error="$event.target.src='/images/default-avatar.svg'" />
          <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full border-2 border-white" :class="conv.partner?.online ? 'bg-green-500' : 'bg-gray-300'" />
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center justify-between">
            <span class="font-bold text-gray-800 text-sm truncate">{{ conv.partner?.name }}</span>
            <span class="text-[10px] text-gray-400 shrink-0">{{ formatTime(conv.last_at) }}</span>
          </div>
          <p class="text-xs text-gray-500 truncate mt-0.5">{{ conv.last_message || '대화 시작' }}</p>
        </div>
        <div v-if="conv.unread_count > 0" class="bg-red-500 text-white text-[10px] font-bold rounded-full w-5 h-5 flex items-center justify-center shrink-0">
          {{ conv.unread_count > 9 ? '9+' : conv.unread_count }}
        </div>
      </div>
    </div>

    <!-- 통화 기록 -->
    <div v-if="tab === 'calls'" class="space-y-2">
      <div v-if="callsLoading" class="text-center py-12 text-gray-400">로딩중...</div>
      <div v-else-if="callHistory.length === 0" class="text-center py-12 text-gray-400">
        <div class="text-4xl mb-3">📞</div>
        <p>통화 기록이 없습니다</p>
      </div>
      <div v-for="call in callHistory" :key="call.id" class="bg-white rounded-xl border p-4 flex items-center gap-3">
        <img :src="call.partner_avatar || '/images/default-avatar.svg'" class="w-10 h-10 rounded-full object-cover shrink-0" @error="$event.target.src='/images/default-avatar.svg'" />
        <div class="flex-1 min-w-0">
          <div class="font-bold text-gray-800 text-sm truncate">{{ call.partner_name }}</div>
          <div class="text-xs text-gray-400 flex items-center gap-1 mt-0.5">
            <span :class="call.direction === 'outgoing' ? 'text-blue-500' : call.status === 'missed' ? 'text-red-500' : 'text-green-500'">
              {{ call.direction === 'outgoing' ? '📤' : '📥' }}
            </span>
            <span>{{ statusLabel(call.status) }}</span>
            <span v-if="call.duration !== '00:00'" class="text-gray-400">· {{ call.duration }}</span>
            <span class="text-gray-300">· {{ formatTime(call.created_at) }}</span>
          </div>
        </div>
        <button @click="callBack(call)" class="bg-green-100 text-green-700 text-xs font-bold px-3 py-1.5 rounded-lg hover:bg-green-200">📞</button>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const tab = ref('messages')
const conversations = ref([])
const callHistory = ref([])
const loading = ref(false)
const callsLoading = ref(false)

onMounted(async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/api/comms/conversations')
    conversations.value = Array.isArray(data) ? data : data.data || []
  } catch (e) { console.error(e) }
  finally { loading.value = false }
})

async function loadCalls() {
  callsLoading.value = true
  try {
    const { data } = await axios.get('/api/comms/calls/history')
    callHistory.value = Array.isArray(data) ? data : data.data || []
  } catch (e) { console.error(e) }
  finally { callsLoading.value = false }
}

function openChat(conv) {
  if (window.openCommChat) {
    window.openCommChat(conv.partner, conv.id)
  }
}

function callBack(call) {
  if (window.startCommCall) {
    window.startCommCall({ id: call.partner_id, name: call.partner_name, avatar: call.partner_avatar })
  }
}

function statusLabel(s) {
  const m = { ringing: '발신중', answered: '통화', ended: '완료', missed: '부재중', declined: '거절', failed: '실패' }
  return m[s] || s
}

function formatTime(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  const now = new Date()
  if (d.toDateString() === now.toDateString()) return d.toLocaleTimeString('ko-KR', { hour: '2-digit', minute: '2-digit' })
  return d.toLocaleDateString('ko-KR', { month: 'short', day: 'numeric' })
}
</script>
