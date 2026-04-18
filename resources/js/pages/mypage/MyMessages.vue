<template>
  <!-- /mypage/messages (Phase 2-C 묶음 3) -->
  <div class="space-y-3">
    <div class="bg-white rounded-xl shadow-sm">
      <!-- 탭 -->
      <div class="flex border-b">
        <button
          v-for="t in tabs" :key="t.key"
          @click="tab = t.key; load()"
          :class="['flex-1 py-3 text-sm font-semibold', tab === t.key ? 'border-b-2 border-amber-400 text-amber-600' : 'text-gray-500']"
        >
          {{ t.label }}
          <span v-if="t.key === 'inbox' && unreadCount > 0" class="ml-1 px-1.5 py-0.5 bg-red-500 text-white rounded-full text-xs">{{ unreadCount }}</span>
        </button>
      </div>

      <div v-if="loading" class="p-6 text-center text-sm text-gray-400">로딩 중...</div>
      <div v-else-if="!messages.length" class="p-10 text-center text-sm text-gray-500">
        <p class="text-3xl mb-2">✉️</p>
        <p>쪽지가 없습니다.</p>
      </div>
      <ul v-else class="divide-y">
        <li
          v-for="m in messages" :key="m.id"
          @click="open(m)"
          :class="['p-4 cursor-pointer hover:bg-amber-50', !m.read_at && tab === 'inbox' ? 'bg-amber-50/50' : '']"
        >
          <div class="flex items-start justify-between gap-2">
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold truncate">
                <span v-if="!m.read_at && tab === 'inbox'" class="inline-block w-2 h-2 bg-amber-400 rounded-full mr-2"></span>
                {{ tab === 'inbox' ? (m.sender?.nickname || m.sender?.name || '익명') : (m.recipient?.nickname || m.recipient?.name || '-') }}
              </p>
              <p class="text-sm text-gray-600 truncate mt-0.5">{{ m.content || m.body || m.message }}</p>
            </div>
            <span class="text-xs text-gray-400 whitespace-nowrap">{{ fmtDate(m.created_at) }}</span>
          </div>
        </li>
      </ul>
    </div>

    <div class="flex justify-end">
      <button @click="showCompose = true" class="px-4 py-2 bg-amber-400 hover:bg-amber-500 text-white rounded-lg text-sm font-semibold">
        ✏️ 새 쪽지
      </button>
    </div>

    <!-- 상세 모달 -->
    <div v-if="selected" @click.self="selected = null" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl max-w-lg w-full p-5">
        <div class="flex items-center justify-between mb-3 pb-3 border-b">
          <div>
            <p class="text-xs text-gray-500">{{ tab === 'inbox' ? '보낸이' : '받는이' }}</p>
            <p class="font-semibold">{{ tab === 'inbox' ? (selected.sender?.nickname || selected.sender?.name) : (selected.recipient?.nickname || selected.recipient?.name) }}</p>
            <p class="text-xs text-gray-400">{{ fmtDate(selected.created_at) }}</p>
          </div>
          <button @click="selected = null" class="text-gray-400 hover:text-gray-600 text-xl">×</button>
        </div>
        <p class="text-sm whitespace-pre-wrap leading-relaxed py-3">{{ selected.content || selected.body || selected.message }}</p>
        <div class="flex justify-end gap-2 pt-3 border-t">
          <button v-if="tab === 'inbox'" @click="reply" class="px-3 py-1.5 bg-amber-400 hover:bg-amber-500 text-white rounded text-sm">💬 답장</button>
          <button @click="del" class="px-3 py-1.5 bg-red-100 text-red-700 rounded text-sm hover:bg-red-200">🗑️ 삭제</button>
        </div>
      </div>
    </div>

    <!-- 새 쪽지 / 답장 -->
    <div v-if="showCompose" @click.self="showCompose = false" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl max-w-lg w-full p-5">
        <h3 class="font-bold mb-3">✏️ 쪽지 작성</h3>
        <input v-model="compose.recipient" placeholder="받는 사람 닉네임 또는 이메일" class="w-full border rounded px-3 py-2 text-sm mb-2" />
        <textarea v-model="compose.content" placeholder="내용 (500자 이하)" maxlength="500" rows="5" class="w-full border rounded px-3 py-2 text-sm"></textarea>
        <p class="text-xs text-gray-400 mt-1">{{ compose.content.length }}/500</p>
        <div class="flex justify-end gap-2 mt-3">
          <button @click="showCompose = false" class="px-3 py-1.5 bg-gray-100 rounded text-sm">취소</button>
          <button @click="send" class="px-3 py-1.5 bg-amber-400 hover:bg-amber-500 text-white rounded text-sm font-semibold">전송</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useSiteStore } from '../../stores/site'

const site = useSiteStore()
const tabs = [{ key: 'inbox', label: '📥 받은 쪽지' }, { key: 'sent', label: '📤 보낸 쪽지' }]
const tab = ref('inbox')
const messages = ref([])
const loading = ref(false)
const selected = ref(null)
const showCompose = ref(false)
const compose = ref({ recipient: '', content: '' })

const unreadCount = computed(() => tab.value === 'inbox' ? messages.value.filter(m => !m.read_at).length : 0)
const fmtDate = (s) => s ? new Date(s).toLocaleString('ko-KR') : ''

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get(`/api/messages?tab=${tab.value}`)
    messages.value = data?.data?.data || data?.data || []
  } finally { loading.value = false }
}

async function open(m) {
  selected.value = m
  if (tab.value === 'inbox' && !m.read_at) {
    try { await axios.post(`/api/messages/${m.id}/read`); m.read_at = new Date().toISOString() } catch {}
  }
}

function reply() {
  if (!selected.value) return
  compose.value = { recipient: selected.value.sender?.nickname || selected.value.sender?.email || '', content: '' }
  selected.value = null
  showCompose.value = true
}

async function del() {
  if (!selected.value) return
  if (!confirm('이 쪽지를 삭제하시겠습니까?')) return
  try {
    await axios.delete(`/api/messages/${selected.value.id}`)
    selected.value = null
    await load()
    site.toast('삭제되었습니다', 'success')
  } catch { site.toast('삭제 실패', 'error') }
}

async function send() {
  if (!compose.value.recipient || !compose.value.content) { site.toast('받는 사람과 내용을 입력하세요', 'error'); return }
  try {
    await axios.post('/api/messages', { recipient: compose.value.recipient, content: compose.value.content })
    showCompose.value = false
    compose.value = { recipient: '', content: '' }
    site.toast('전송되었습니다', 'success')
    if (tab.value === 'sent') await load()
  } catch (e) {
    site.toast(e.response?.data?.message || '전송 실패', 'error')
  }
}

onMounted(load)
</script>
