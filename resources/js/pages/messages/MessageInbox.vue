<template>
<div class="min-h-screen">
  <div class="max-w-3xl mx-auto px-4 py-5">
    <div class="flex items-center justify-between mb-4">
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
        <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="mail" :size="20" /></span>
        쪽지함
      </h1>
      <div class="flex items-center gap-2">
        <span v-if="unreadCount" class="bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">{{ unreadCount }}</span>
        <button @click="showCompose=true" class="btn-primary text-sm px-4 py-2 flex items-center gap-1.5"><AppIcon name="edit" :size="14" />새 쪽지</button>
      </div>
    </div>

    <!-- 받은/보낸 탭 -->
    <div class="flex gap-1 mb-4 bg-gray-100 rounded-xl p-1">
      <button @click="tab='received'; loadMessages()" class="flex-1 text-sm py-2 rounded-lg transition flex items-center justify-center gap-1.5"
        :class="tab==='received' ? 'bg-white text-ink font-semibold shadow-sm' : 'text-ink-muted'"><AppIcon name="download" :size="14" />받은 쪽지</button>
      <button @click="tab='sent'; loadMessages()" class="flex-1 text-sm py-2 rounded-lg transition flex items-center justify-center gap-1.5"
        :class="tab==='sent' ? 'bg-white text-ink font-semibold shadow-sm' : 'text-ink-muted'"><AppIcon name="upload" :size="14" />보낸 쪽지</button>
    </div>

    <div v-if="loading" class="text-center py-12 text-ink-muted">로딩중...</div>
    <div v-else-if="!messages.length" class="py-16 text-center">
      <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="mail" :size="28" :stroke-width="1.5" /></div>
      <p class="text-sm text-ink-muted">{{ tab==='received' ? '받은 쪽지가 없습니다' : '보낸 쪽지가 없습니다' }}</p>
    </div>
    <div v-else class="card overflow-hidden divide-y divide-gray-50">
      <div v-for="msg in messages" :key="msg.id" @click="openMsg(msg)"
        class="px-4 py-3 cursor-pointer hover:bg-amber-50/50 transition-colors"
        :class="tab==='received' && !msg.is_read ? 'bg-amber-50' : ''">
        <div class="flex items-center gap-2 mb-1">
          <span v-if="tab==='received' && !msg.is_read" class="w-2 h-2 bg-amber-500 rounded-full flex-shrink-0"></span>
          <div class="w-7 h-7 bg-amber-100 rounded-full flex items-center justify-center text-[11px] font-bold text-amber-700 flex-shrink-0">
            {{ (tab==='received' ? msg.sender?.name : msg.receiver?.name || '?')[0] }}
          </div>
          <span class="text-sm font-semibold text-ink">{{ tab==='received' ? msg.sender?.name : msg.receiver?.name || '알 수 없음' }}</span>
          <span v-if="tab==='sent'" class="text-[11px] text-blue-400">보냄</span>
          <span class="text-[11px] text-ink-muted ml-auto">{{ formatDate(msg.created_at) }}</span>
        </div>
        <div class="text-sm text-ink-light truncate pl-9">{{ msg.content }}</div>
      </div>
    </div>

    <!-- 쪽지 상세 모달 -->
    <div v-if="activeMsg" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" @click.self="activeMsg=null">
      <div class="bg-white rounded-2xl shadow-lift w-full max-w-md overflow-hidden">
        <div class="px-5 py-3 flex items-center justify-between border-b border-gray-100"
          :class="tab==='received' ? '' : ''">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold"
              :class="tab==='received' ? 'bg-amber-50 text-amber-600' : 'bg-blue-50 text-blue-600'">
              {{ (tab==='received' ? activeMsg.sender?.name : activeMsg.receiver?.name || '?')[0] }}
            </div>
            <div>
              <div class="text-sm font-bold" :class="tab==='received' ? 'text-ink' : 'text-ink'">
                {{ tab==='received' ? activeMsg.sender?.name : activeMsg.receiver?.name }}
              </div>
              <div class="text-[11px]" :class="tab==='received' ? 'text-ink-muted' : 'text-ink-muted'">{{ formatDate(activeMsg.created_at) }}</div>
            </div>
          </div>
          <button @click="activeMsg=null" :class="tab==='received' ? 'text-ink-muted hover:text-ink transition-colors' : 'text-ink-muted hover:text-ink transition-colors'"><AppIcon name="x" :size="18" /></button>
        </div>
        <div class="p-5">
          <div class="text-sm text-ink-light whitespace-pre-wrap leading-relaxed mb-4">{{ activeMsg.content }}</div>
          <div v-if="tab==='received'" class="flex gap-2">
            <button @click="replyToMsg(activeMsg)" class="btn-primary text-sm flex-1 px-4 py-2 flex items-center justify-center gap-1.5"><AppIcon name="send" :size="14" />답장</button>
            <button @click="activeMsg=null" class="btn-ghost px-4 py-2">닫기</button>
          </div>
          <div v-else class="text-right">
            <button @click="activeMsg=null" class="btn-ghost px-4 py-2">닫기</button>
          </div>
        </div>
      </div>
    </div>

    <!-- 쪽지 작성 모달 -->
    <div v-if="showCompose" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" @click.self="showCompose=false">
      <div class="bg-white rounded-2xl shadow-lift w-full max-w-md overflow-hidden">
        <div class="px-5 py-3 flex items-center justify-between border-b border-gray-100">
          <span class="text-ink font-bold text-sm flex items-center gap-2"><span class="icon-chip w-7 h-7 bg-blue-50 text-blue-600"><AppIcon name="edit" :size="14" /></span>새 쪽지</span>
          <button @click="showCompose=false" class="text-ink-muted hover:text-ink transition-colors"><AppIcon name="x" :size="18" /></button>
        </div>
        <div class="p-5">
          <label class="input-label mb-1 block">받는 사람 (사용자 ID)</label>
          <input v-model="composeForm.receiver_id" type="number" placeholder="받는 사람 ID" class="input-soft w-full px-3 py-2 text-sm mb-3" />
          <label class="input-label mb-1 block">내용</label>
          <textarea v-model="composeForm.content" rows="5" maxlength="500" placeholder="쪽지 내용을 입력하세요..." class="input-soft w-full px-3 py-2 text-sm mb-1 resize-none"></textarea>
          <div class="text-right text-xs text-ink-muted mb-3">{{ composeForm.content.length }}/500</div>
          <div v-if="composeError" class="text-red-500 text-sm mb-2">{{ composeError }}</div>
          <div class="flex gap-2">
            <button @click="showCompose=false" class="btn-secondary text-sm px-4 py-2">취소</button>
            <button @click="sendMessage" :disabled="sending" class="btn-primary text-sm flex-1 px-4 py-2 disabled:opacity-50">{{ sending ? '전송중...' : '보내기' }}</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const messages = ref([])
const loading = ref(true)
const activeMsg = ref(null)
const showCompose = ref(false)
const sending = ref(false)
const composeError = ref('')
const composeForm = reactive({ receiver_id: '', content: '' })
const tab = ref('received')
const unreadCount = ref(0)

function formatDate(dt) {
  if (!dt) return ''
  const d = new Date(dt), now = new Date()
  const m = d.getMonth() + 1, day = d.getDate()
  const hh = String(d.getHours()).padStart(2, '0'), mm = String(d.getMinutes()).padStart(2, '0')
  if (d.getFullYear() === now.getFullYear() && m === now.getMonth() + 1 && day === now.getDate()) return `오늘 ${hh}:${mm}`
  if (d.getFullYear() === now.getFullYear()) return `${m}/${day} ${hh}:${mm}`
  return `${d.getFullYear()}.${m}.${day} ${hh}:${mm}`
}

async function loadMessages() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/messages', { params: { tab: tab.value } })
    messages.value = data.data?.data || data.data || []
    if (data.unread_count !== undefined) unreadCount.value = data.unread_count
  } catch {}
  loading.value = false
}

async function openMsg(msg) {
  activeMsg.value = msg
  if (tab.value === 'received' && !msg.is_read) {
    msg.is_read = true
    unreadCount.value = Math.max(0, unreadCount.value - 1)
    try { await axios.post(`/api/messages/${msg.id}/read`) } catch {}
  }
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
    if (tab.value === 'sent') await loadMessages()
  } catch (e) { composeError.value = e.response?.data?.message || '전송 실패' }
  sending.value = false
}

onMounted(() => loadMessages())
</script>
