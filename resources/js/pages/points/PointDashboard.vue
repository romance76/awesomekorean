<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-3xl mx-auto px-4 py-5">
    <h1 class="text-xl font-black text-gray-800 mb-4">⭐ 포인트</h1>

    <!-- 잔액 카드 -->
    <div class="bg-gradient-to-r from-amber-400 to-orange-400 rounded-xl p-5 text-amber-900 mb-4">
      <div class="text-sm font-semibold opacity-80">내 포인트</div>
      <div class="text-3xl font-black mt-1">{{ balance.toLocaleString() }}P</div>
      <div class="flex gap-3 mt-3">
        <button @click="dailySpin" :disabled="spunToday" class="bg-white/30 px-4 py-1.5 rounded-lg text-sm font-bold hover:bg-white/50 transition disabled:opacity-50">
          🎰 {{ spunToday ? '오늘 완료' : '일일 룰렛' }}
        </button>
        <RouterLink to="/points/rules" class="bg-white/30 px-4 py-1.5 rounded-lg text-sm font-bold hover:bg-white/50 transition">📋 적립 규칙</RouterLink>
      </div>
    </div>

    <!-- 룰렛 결과 -->
    <div v-if="spinResult !== null" class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-4 text-center">
      <div class="text-3xl mb-2">🎉</div>
      <div class="font-bold text-amber-800">{{ spinResult > 0 ? spinResult + 'P 당첨!' : '다음 기회에!' }}</div>
    </div>

    <!-- 거래 내역 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="px-4 py-3 border-b font-bold text-sm text-amber-900">📊 포인트 내역</div>
      <div v-if="loading" class="py-8 text-center text-gray-400">로딩중...</div>
      <div v-else>
        <div v-for="log in logs" :key="log.id" class="px-4 py-3 border-b last:border-0 flex justify-between items-center">
          <div>
            <div class="text-sm text-gray-800">{{ log.reason }}</div>
            <div class="text-[10px] text-gray-400">{{ formatDate(log.created_at) }}</div>
          </div>
          <div class="font-bold text-sm" :class="log.amount > 0 ? 'text-green-600' : 'text-red-500'">
            {{ log.amount > 0 ? '+' : '' }}{{ log.amount }}P
          </div>
        </div>
        <div v-if="!logs.length" class="py-6 text-center text-sm text-gray-400">거래 내역이 없습니다</div>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useSiteStore } from '../../stores/site'
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
