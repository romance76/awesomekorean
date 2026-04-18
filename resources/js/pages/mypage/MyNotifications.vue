<template>
  <!-- /mypage/notifications (Phase 2-C 묶음 3) -->
  <div class="bg-white rounded-xl shadow-sm p-4">
    <div class="flex items-center justify-between mb-3">
      <h3 class="font-bold">🔔 알림</h3>
      <button v-if="unreadCount > 0" @click="markAllRead" class="text-xs px-3 py-1.5 bg-amber-100 text-amber-700 hover:bg-amber-200 rounded">전체 읽음</button>
    </div>

    <div v-if="loading" class="p-6 text-center text-sm text-gray-400">로딩 중...</div>
    <div v-else-if="!notifications.length" class="p-10 text-center text-sm text-gray-500">
      <p class="text-3xl mb-2">🔔</p>
      <p>알림이 없습니다.</p>
    </div>
    <ul v-else class="divide-y">
      <li
        v-for="n in notifications" :key="n.id"
        @click="handleClick(n)"
        :class="['py-3 px-2 cursor-pointer hover:bg-amber-50 rounded', !n.read_at ? 'bg-amber-50/40' : '']"
      >
        <div class="flex items-start gap-3">
          <span class="text-xl">{{ typeIcon(n.type) }}</span>
          <div class="flex-1 min-w-0">
            <p class="text-sm">
              <span v-if="!n.read_at" class="inline-block w-2 h-2 bg-amber-400 rounded-full mr-1.5"></span>
              {{ n.message || n.title || n.data?.message || '알림' }}
            </p>
            <p class="text-xs text-gray-400 mt-0.5">{{ fmtDate(n.created_at) }}</p>
          </div>
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useRouter } from 'vue-router'

const router = useRouter()
const notifications = ref([])
const loading = ref(true)

const unreadCount = computed(() => notifications.value.filter(n => !n.read_at).length)
const fmtDate = (s) => s ? new Date(s).toLocaleString('ko-KR') : ''
const typeIcon = (t) => ({
  like: '❤️', comment: '💬', friend_request: '👫', friend_accept: '👫',
  answer: '✅', message: '✉️', booking: '📅', checkin: '💙', sos: '🚨', event: '🎉',
}[t] || '🔔')

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/notifications')
    notifications.value = data?.data?.data || data?.data || []
  } finally { loading.value = false }
}

async function markAllRead() {
  try {
    await axios.post('/api/notifications/read')
    notifications.value.forEach(n => { n.read_at = new Date().toISOString() })
  } catch {}
}

async function handleClick(n) {
  // 읽음 처리
  if (!n.read_at) {
    try { await axios.post(`/api/notifications/${n.id}/read`) } catch {}
    n.read_at = new Date().toISOString()
  }
  // 링크 이동 (있는 경우)
  const link = n.link || n.data?.link || n.url
  if (link) router.push(link)
}

onMounted(load)
</script>
