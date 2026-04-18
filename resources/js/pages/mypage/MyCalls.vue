<template>
  <div class="bg-white rounded-xl shadow-sm p-4">
    <h3 class="font-bold mb-3">📞 통화 내역</h3>
    <div v-if="loading" class="p-6 text-center text-sm text-gray-400">로딩 중...</div>
    <div v-else-if="!items.length" class="p-10 text-center text-sm text-gray-500">
      <p class="text-3xl mb-2">📞</p><p>통화 내역이 없습니다.</p>
    </div>
    <ul v-else class="divide-y">
      <li v-for="c in items" :key="c.id" class="py-3 flex items-center justify-between gap-2">
        <div class="flex items-center gap-3 flex-1 min-w-0">
          <span class="text-xl">{{ directionIcon(c) }}</span>
          <div class="min-w-0">
            <p class="font-semibold text-sm truncate">{{ other(c)?.nickname || other(c)?.name || '알 수 없음' }}</p>
            <p class="text-xs text-gray-500">
              <span v-if="c.is_elder" class="px-1.5 py-0.5 bg-blue-100 text-blue-700 rounded text-xs mr-1">안심</span>
              <span :class="statusClass(c.status)">{{ statusLabel(c.status) }}</span>
              <span class="ml-1">{{ durationStr(c.duration) }}</span>
            </p>
          </div>
        </div>
        <span class="text-xs text-gray-400 whitespace-nowrap">{{ fmtDate(c.created_at) }}</span>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { useAuthStore } from '../../stores/auth'
const auth = useAuthStore()
const items = ref([])
const loading = ref(true)
const fmtDate = (s) => s ? new Date(s).toLocaleString('ko-KR') : ''
const other = (c) => auth.user?.id === c.caller_id ? c.callee : c.caller
const directionIcon = (c) => auth.user?.id === c.caller_id ? '📤' : '📥'
const statusLabel = (s) => ({ completed: '완료', answered: '응답', missed: '부재중', rejected: '거절', ongoing: '진행중' }[s] || s)
const statusClass = (s) => s === 'missed' || s === 'rejected' ? 'text-red-500' : s === 'completed' || s === 'answered' ? 'text-green-600' : 'text-gray-500'
const durationStr = (s) => {
  if (!s) return ''
  const m = Math.floor(s / 60), sec = s % 60
  return `· ${m}:${sec.toString().padStart(2, '0')}`
}
onMounted(async () => {
  try {
    const { data } = await axios.get('/api/comms/calls/history?per_page=50').catch(() => ({ data: { data: [] } }))
    items.value = data?.data?.data || data?.data || []
  } finally { loading.value = false }
})
</script>
