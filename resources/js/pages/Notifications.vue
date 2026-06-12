<template>
<div class="min-h-screen">
  <div class="max-w-3xl mx-auto px-4 py-5">
    <div class="flex items-center justify-between mb-4">
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
        <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="bell" :size="20" /></span>
        알림
      </h1>
      <button @click="markAllRead" class="btn-ghost text-xs">전체 읽음</button>
    </div>
    <div v-if="loading" class="text-center py-12 text-ink-faint">로딩중...</div>
    <div v-else-if="!notifs.length" class="py-16 text-center">
      <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="bell" :size="28" :stroke-width="1.5" /></div>
      <p class="text-sm text-ink-muted">알림이 없습니다</p>
    </div>
    <div v-else class="card overflow-hidden divide-y divide-gray-50">
      <div v-for="n in notifs" :key="n.id"
        class="px-4 py-3 transition-colors"
        :class="n.read_at ? 'bg-white' : 'bg-amber-50'">
        <div class="flex items-start gap-3">
          <span class="icon-chip w-8 h-8 bg-amber-50 text-amber-600 flex-shrink-0"><AppIcon :name="typeIcons[n.type] || 'megaphone'" :size="15" /></span>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium text-ink">{{ n.title }}</div>
            <div v-if="n.content" class="text-xs text-ink-muted mt-0.5">{{ n.content }}</div>
            <div class="text-xs text-ink-faint mt-1">{{ formatDate(n.created_at) }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../components/AppIcon.vue'
const notifs = ref([])
const loading = ref(true)
const typeIcons = { like:'heart', comment:'message-circle', friend:'user-plus', answer:'help-circle', reservation:'shopping-cart', checkin:'check', sos:'alert-circle' }
function formatDate(dt) {
  if (!dt) return ''
  const h = Math.floor((Date.now() - new Date(dt).getTime()) / 3600000)
  if (h < 1) return '방금'
  if (h < 24) return h + '시간 전'
  return Math.floor(h/24) + '일 전'
}
async function markAllRead() {
  try { await axios.post('/api/notifications/read'); notifs.value.forEach(n => n.read_at = new Date()) } catch {}
}
onMounted(async () => {
  try { const { data } = await axios.get('/api/notifications'); notifs.value = data.data?.data || data.data || [] } catch {}
  loading.value = false
})
</script>
