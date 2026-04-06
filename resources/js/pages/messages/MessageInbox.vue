<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-3xl mx-auto px-4 py-5">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-xl font-black text-gray-800">✉️ 쪽지</h1>
      <button @click="showCompose=true" class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-lg text-sm hover:bg-amber-500">✏️ 새 쪽지</button>
    </div>

    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="!messages.length" class="text-center py-12 text-gray-400">받은 쪽지가 없습니다</div>
    <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div v-for="msg in messages" :key="msg.id" @click="openMsg(msg)"
        class="px-4 py-3 border-b last:border-0 cursor-pointer hover:bg-amber-50/50 transition"
        :class="msg.is_read ? '' : 'bg-amber-50'">
        <div class="flex items-center gap-2 mb-1">
          <span v-if="!msg.is_read" class="w-2 h-2 bg-amber-500 rounded-full flex-shrink-0"></span>
          <span class="text-sm font-semibold text-gray-800">{{ msg.sender?.name || '시스템' }}</span>
          <span class="text-[10px] text-gray-400 ml-auto">{{ formatDate(msg.created_at) }}</span>
        </div>
        <div class="text-sm text-gray-600 truncate">{{ msg.content }}</div>
      </div>
    </div>

    <!-- 쪽지 상세 모달 -->
    <div v-if="activeMsg" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" @click.self="activeMsg=null">
      <div class="bg-white rounded-xl p-5 w-full max-w-md shadow-xl max-h-[80vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-3">
          <div class="text-sm font-bold text-gray-800">{{ activeMsg.sender?.name || '시스템' }}</div>
          <span class="text-[10px] text-gray-400">{{ formatDate(activeMsg.created_at) }}</span>
        </div>
        <div class="text-sm text-gray-700 whitespace-pre-wrap mb-4">{{ activeMsg.content }}</div>
        <div class="flex gap-2">
          <button @click="replyToMsg(activeMsg)" class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-lg text-sm flex-1 hover:bg-amber-500">답장</button>
          <button @click="activeMsg=null" class="text-gray-500 px-4 py-2">닫기</button>
        </div>
      </div>
    </div>

    <!-- 쪽지 작성 모달 -->
    <div v-if="showCompose" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" @click.self="showCompose=false">
      <div class="bg-white rounded-xl p-5 w-full max-w-md shadow-xl">
        <h3 class="font-bold text-gray-800 mb-3">✏️ 새 쪽지</h3>
        <input v-model="composeForm.receiver_id" type="number" placeholder="받는 사람 ID" class="w-full border rounded-lg px-3 py-2 text-sm mb-2 focus:ring-2 focus:ring-amber-400 outline-none" />
        <textarea v-model="composeForm.content" rows="4" placeholder="내용을 입력하세요..." class="w-full border rounded-lg px-3 py-2 text-sm mb-3 resize-none focus:ring-2 focus:ring-amber-400 outline-none"></textarea>
        <div v-if="composeError" class="text-red-500 text-sm mb-2">{{ composeError }}</div>
        <div class="flex gap-2">
          <button @click="sendMessage" :disabled="sending" class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-lg text-sm flex-1 hover:bg-amber-500 disabled:opacity-50">{{ sending ? '전송중...' : '보내기' }}</button>
          <button @click="showCompose=false" class="text-gray-500 px-4 py-2">취소</button>
        </div>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'
const messages = ref([])
const loading = ref(true)
const activeMsg = ref(null)
const showCompose = ref(false)
const sending = ref(false)
const composeError = ref('')
const composeForm = reactive({ receiver_id: '', content: '' })

function formatDate(dt) {
  if (!dt) return ''
  const h = Math.floor((Date.now() - new Date(dt).getTime()) / 3600000)
  if (h < 1) return '방금'
  if (h < 24) return h + '시간 전'
  return Math.floor(h/24) + '일 전'
}

function openMsg(msg) {
  activeMsg.value = msg
  msg.is_read = true
}

function replyToMsg(msg) {
  activeMsg.value = null
  showCompose.value = true
  composeForm.receiver_id = msg.sender_id || msg.sender?.id || ''
  composeForm.content = ''
}

async function sendMessage() {
  if (!composeForm.receiver_id || !composeForm.content.trim()) { composeError.value = '받는 사람과 내용을 입력하세요'; return }
  sending.value = true; composeError.value = ''
  try {
    await axios.post('/api/messages', { receiver_id: composeForm.receiver_id, content: composeForm.content })
    showCompose.value = false; composeForm.receiver_id = ''; composeForm.content = ''
    alert('쪽지가 전송되었습니다!')
  } catch (e) { composeError.value = e.response?.data?.message || '전송 실패' }
  sending.value = false
}

onMounted(async () => {
  try { const { data } = await axios.get('/api/messages'); messages.value = data.data?.data || data.data || [] } catch {}
  loading.value = false
})
</script>
