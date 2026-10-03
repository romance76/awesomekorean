<template>
<div class="min-h-screen">
  <div class="max-w-5xl mx-auto px-4 py-5">
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-1">
      <span class="icon-chip w-9 h-9 bg-violet-50 text-violet-600"><AppIcon name="gift" :size="20" /></span>
      Sweepstakes
    </h1>
    <p class="text-sm text-ink-muted mb-5">무료로 모은 🎟 Entry로 경품 추첨에 참여해보세요. 구매는 당첨 확률에 전혀 영향을 주지 않습니다.</p>

    <div v-if="loading" class="py-16 text-center text-ink-muted">로딩중...</div>
    <div v-else-if="!items.length" class="card py-16 text-center">
      <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="gift" :size="28" :stroke-width="1.5" /></div>
      <p class="text-sm text-ink-muted">진행중인 Sweepstakes가 없습니다</p>
    </div>
    <div v-else class="grid sm:grid-cols-2 gap-4">
      <RouterLink v-for="s in items" :key="s.id" :to="`/sweepstakes/${s.id}`" class="card overflow-hidden group">
        <div class="aspect-[16/9] bg-gray-100 overflow-hidden">
          <img v-if="s.prize_image" :src="s.prize_image" :alt="s.prize_name" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
          <div v-else class="w-full h-full flex items-center justify-center text-4xl">🎁</div>
        </div>
        <div class="p-4">
          <div class="flex items-center gap-1.5 text-xs font-bold text-violet-600 mb-1">
            <AppIcon name="ticket" :size="12" />{{ statusLabel(s.status) }}
          </div>
          <div class="font-bold text-ink">{{ s.title }}</div>
          <div class="text-sm text-ink-muted mt-0.5">{{ s.prize_name }}</div>
          <div class="flex items-center justify-between mt-3 text-xs text-ink-faint">
            <span>참가 Entry: {{ (s.total_entries || 0).toLocaleString() }}</span>
            <span>{{ formatEnd(s.end_at) }}</span>
          </div>
        </div>
      </RouterLink>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import AppIcon from '../../components/AppIcon.vue'
import axios from 'axios'

const items = ref([])
const loading = ref(true)

function statusLabel(status) {
  return { draft: '준비중', active: '진행중', ended: '마감', winner_selected: '당첨자 발표', cancelled: '취소됨' }[status] || status
}
function formatEnd(dt) {
  if (!dt) return ''
  return new Date(dt).toLocaleDateString('ko-KR', { month: 'short', day: 'numeric' }) + ' 마감'
}

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/sweepstakes', { params: { status: 'active' } })
    items.value = data.data?.data || data.data || []
  } catch {}
  loading.value = false
})
</script>
