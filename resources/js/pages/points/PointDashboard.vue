<template>
<div class="min-h-screen">
  <div class="page-main px-4 py-5">
    <PageHeader title="포인트" icon="coins" :back="false" />

    <!-- 잔액 카드 -->
    <div class="bg-gradient-to-r from-[#FF8A4D] to-[#F0266B] rounded-2xl p-5 text-white mb-4 shadow-card">
      <div class="text-sm font-semibold opacity-90">내 포인트</div>
      <div class="text-3xl font-black mt-1">{{ balance.toLocaleString() }}P</div>
      <div class="flex gap-3 mt-3">
        <RouterLink to="/points/rules" class="bg-white/20 px-4 py-1.5 rounded-lg text-sm font-bold hover:bg-white/30 transition-colors flex items-center gap-1.5">
          <AppIcon name="list" :size="14" /> 적립 규칙
        </RouterLink>
        <RouterLink to="/entries" class="bg-white/20 px-4 py-1.5 rounded-lg text-sm font-bold hover:bg-white/30 transition-colors flex items-center gap-1.5">
          <AppIcon name="ticket" :size="14" /> Entry 출석체크
        </RouterLink>
      </div>
    </div>

    <!-- 거래 내역 -->
    <div class="card overflow-hidden">
      <div class="px-4 py-3 border-b border-gray-50 font-bold text-sm text-ink flex items-center gap-2">
        <span class="icon-chip w-7 h-7 bg-amber-50 text-amber-600"><AppIcon name="chart-bar" :size="14" /></span>포인트 내역
      </div>
      <div v-if="loading" class="py-8 text-center text-ink-muted">로딩중...</div>
      <div v-else class="divide-y divide-gray-50">
        <div v-for="log in logs" :key="log.id" class="list-row flex justify-between items-center">
          <div>
            <div class="text-sm text-ink">{{ log.reason }}</div>
            <div class="text-xs text-ink-muted">{{ formatDate(log.created_at) }}</div>
          </div>
          <div class="font-bold text-sm" :class="log.amount > 0 ? 'text-green-600' : 'text-red-500'">
            {{ log.amount > 0 ? '+' : '' }}{{ log.amount }}P
          </div>
        </div>
        <div v-if="!logs.length" class="py-16 text-center">
          <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="coins" :size="28" :stroke-width="1.5" /></div>
          <p class="text-sm text-ink-muted">거래 내역이 없습니다</p>
        </div>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import AppIcon from '../../components/AppIcon.vue'
import PageHeader from '../../components/PageHeader.vue'
import axios from 'axios'
const balance = ref(0)
const logs = ref([])
const loading = ref(true)

function formatDate(dt) {
  if (!dt) return ''
  const h = Math.floor((Date.now() - new Date(dt).getTime()) / 3600000)
  if (h < 1) return '방금'
  if (h < 24) return h + '시간 전'
  return new Date(dt).toLocaleDateString('ko-KR')
}

onMounted(async () => {
  try {
    const [balRes, logRes] = await Promise.all([
      axios.get('/api/points/balance'),
      axios.get('/api/points/history'),
    ])
    balance.value = balRes.data.data?.points || 0
    logs.value = logRes.data.data?.data || logRes.data.data || []
  } catch {}
  loading.value = false
})
</script>
