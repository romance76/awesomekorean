<template>
<div class="min-h-screen">
  <div class="max-w-3xl mx-auto px-4 py-5">
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-4">
      <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="coins" :size="20" /></span>
      포인트
    </h1>

    <!-- 잔액 카드 -->
    <div class="bg-gradient-to-r from-[#FF8A53] to-[#F2570F] rounded-2xl p-5 text-white mb-4 shadow-card">
      <div class="text-sm font-semibold opacity-90">내 포인트</div>
      <div class="text-3xl font-black mt-1">{{ balance.toLocaleString() }}P</div>
      <div class="flex gap-3 mt-3">
        <button @click="dailySpin" :disabled="spunToday" class="bg-white/20 px-4 py-1.5 rounded-lg text-sm font-bold hover:bg-white/30 transition-colors disabled:opacity-50 flex items-center gap-1.5">
          <AppIcon name="sparkles" :size="14" /> {{ spunToday ? '오늘 완료' : '일일 룰렛' }}
        </button>
        <RouterLink to="/points/rules" class="bg-white/20 px-4 py-1.5 rounded-lg text-sm font-bold hover:bg-white/30 transition-colors flex items-center gap-1.5">
          <AppIcon name="list" :size="14" /> 적립 규칙
        </RouterLink>
      </div>
    </div>

    <!-- 룰렛 결과 -->
    <div v-if="spinResult !== null" class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-4 text-center">
      <div class="text-3xl mb-2">🎉</div>
      <div class="font-bold text-amber-700">{{ spinResult > 0 ? spinResult + 'P 당첨!' : '다음 기회에!' }}</div>
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
import { useSiteStore } from '../../stores/site'
import AppIcon from '../../components/AppIcon.vue'
import axios from 'axios'
const siteStore = useSiteStore()
const balance = ref(0)
const logs = ref([])
const loading = ref(true)
const spunToday = ref(false)
const spinResult = ref(null)

function formatDate(dt) {
  if (!dt) return ''
  const h = Math.floor((Date.now() - new Date(dt).getTime()) / 3600000)
  if (h < 1) return '방금'
  if (h < 24) return h + '시간 전'
  return new Date(dt).toLocaleDateString('ko-KR')
}

async function dailySpin() {
  try {
    const { data } = await axios.post('/api/points/daily-spin')
    spinResult.value = data.data.points_won
    spunToday.value = true
    balance.value += data.data.points_won
    if (data.data.points_won > 0) siteStore.toast(`🎉 ${data.data.points_won}P 당첨!`, 'success')
  } catch (e) {
    siteStore.toast(e.response?.data?.message || '룰렛 실패', 'error')
  }
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
