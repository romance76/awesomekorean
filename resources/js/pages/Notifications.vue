<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <div class="max-w-[1200px] mx-auto px-4 pt-4">
      <div class="bg-gradient-to-r from-blue-600 to-blue-500 text-white px-6 py-6 rounded-2xl">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-xl font-black">🔔 알림</h1>
            <p class="text-blue-100 text-sm mt-0.5">새로운 소식을 확인하세요</p>
          </div>
          <button v-if="unread > 0" @click="markAll" class="bg-white text-blue-600 px-4 py-2 rounded-lg text-sm font-bold hover:bg-blue-50">모두 읽음</button>
        </div>
      </div>
    </div>

    <div class="max-w-[1200px] mx-auto px-4 py-4 space-y-2">
      <div v-if="loading" class="text-center py-10 text-gray-400">불러오는 중...</div>
      <div v-else-if="!notifications.length" class="text-center py-16 text-gray-400">
        <div class="text-5xl mb-3">🔔</div>
        <div>새로운 알림이 없습니다</div>
      </div>
      <div v-else>
        <div v-for="n in notifications" :key="n.id"
          @click="openNotification(n)"
          class="bg-white rounded-xl p-4 shadow-sm flex gap-3 cursor-pointer hover:bg-gray-50 transition"
          :class="!n.read_at ? 'border-l-4 border-blue-500' : ''">
          <div class="text-2xl flex-shrink-0">{{ typeIcon(n.type) }}</div>
          <div class="flex-1 min-w-0">
            <div class="font-medium text-gray-800 text-sm">{{ n.title }}</div>
            <div class="text-gray-500 text-xs mt-0.5 line-clamp-2">{{ n.body }}</div>
            <div class="text-gray-400 text-xs mt-1">{{ timeAgo(n.created_at) }}</div>
          </div>
          <div v-if="!n.read_at" class="w-2 h-2 bg-blue-500 rounded-full mt-1 flex-shrink-0"></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const notifications = ref([])
const unread = ref(0)
const loading = ref(false)

const typeIconMap = {
  message: '💬', post_liked: '👍', comment: '💬', match: '💝',
  ride: '🚗', quiz: '🧠', system: '📢', game: '🎮', event: '🎉',
}

function typeIcon(type) { return typeIconMap[type] ?? '🔔' }

function timeAgo(dt) {
  const d = new Date(dt), now = new Date()
  const s = Math.floor((now - d) / 1000)
  if (s < 60) return '방금 전'
  if (s < 3600) return `${Math.floor(s/60)}분 전`
  if (s < 86400) return `${Math.floor(s/3600)}시간 전`
  return `${Math.floor(s/86400)}일 전`
}

async function openNotification(n) {
  if (!n.read_at) {
    await axios.post(`/api/notifications/${n.id}/read`)
    n.read_at = new Date().toISOString()
    unread.value = Math.max(0, unread.value - 1)
  }
  if (n.url) router.push(n.url)
}

async function markAll() {
  await axios.post('/api/notifications/read-all')
  notifications.value.forEach(n => n.read_at = new Date().toISOString())
  unread.value = 0
}

onMounted(async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/api/notifications')
    notifications.value = data.notifications
    unread.value = data.unread
  } catch { }
  loading.value = false
})
</script>
