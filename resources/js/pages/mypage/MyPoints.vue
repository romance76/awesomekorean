<template>
  <!-- /mypage/points (Phase 2-C 묶음 3) -->
  <div class="space-y-4">
    <!-- 포인트 카드 -->
    <div class="bg-gradient-to-r from-amber-400 to-orange-400 rounded-xl p-5 text-white shadow-sm">
      <p class="text-xs opacity-80">현재 포인트</p>
      <p class="text-4xl font-bold my-1">{{ (auth.user?.points || 0).toLocaleString() }}</p>
      <p class="text-xs opacity-80">게임 포인트: {{ (auth.user?.game_points || 0).toLocaleString() }}</p>
    </div>

    <!-- 일일 룰렛 -->
    <div class="bg-white rounded-xl shadow-sm p-5">
      <div class="flex items-center justify-between mb-3">
        <h3 class="font-bold">🎰 일일 출석 체크</h3>
        <button @click="spin" :disabled="spinning || spunToday" class="px-4 py-2 bg-amber-400 hover:bg-amber-500 text-white rounded-lg text-sm font-semibold disabled:opacity-50">
          {{ spunToday ? '오늘 완료' : (spinning ? '돌리는 중...' : '룰렛 돌리기') }}
        </button>
      </div>
      <p v-if="lastSpin" class="text-sm text-green-600">{{ lastSpin }}</p>
      <p v-else class="text-xs text-gray-500">매일 1회 포인트 획득 기회.</p>
    </div>

    <!-- 규칙 보기 링크 -->
    <div class="bg-white rounded-xl shadow-sm p-5 flex items-center justify-between">
      <div>
        <p class="font-semibold text-sm">💡 포인트 적립 규칙</p>
        <p class="text-xs text-gray-500">가입·로그인·게시글·댓글·좋아요 등으로 획득</p>
      </div>
      <router-link to="/points/rules" class="text-amber-500 hover:text-amber-600 text-sm font-semibold">규칙 보기 →</router-link>
    </div>

    <!-- 거래 내역 -->
    <div class="bg-white rounded-xl shadow-sm p-5">
      <h3 class="font-bold mb-3">📋 거래 내역</h3>
      <div v-if="loading" class="text-sm text-gray-400 text-center py-6">로딩 중...</div>
      <div v-else-if="!history.length" class="text-sm text-gray-500 text-center py-6">거래 내역이 없습니다.</div>
      <ul v-else class="divide-y">
        <li v-for="h in history" :key="h.id" class="py-2.5 flex items-center justify-between">
          <div>
            <p class="text-sm">{{ h.reason }}</p>
            <p class="text-xs text-gray-400">{{ fmtDate(h.created_at) }}</p>
          </div>
          <div class="text-right">
            <p :class="['font-bold text-sm', h.amount >= 0 ? 'text-green-600' : 'text-red-500']">
              {{ h.amount >= 0 ? '+' : '' }}{{ h.amount.toLocaleString() }}
            </p>
            <p class="text-xs text-gray-400">잔액 {{ h.balance?.toLocaleString() }}</p>
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { useAuthStore } from '../../stores/auth'
import { useSiteStore } from '../../stores/site'

const auth = useAuthStore()
const site = useSiteStore()
const history = ref([])
const loading = ref(true)
const spinning = ref(false)
const spunToday = ref(false)
const lastSpin = ref('')

const fmtDate = (s) => s ? new Date(s).toLocaleString('ko-KR') : ''

async function loadHistory() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/points/history?per_page=30')
    history.value = data?.data?.data || data?.data || []
  } finally { loading.value = false }
}

async function spin() {
  spinning.value = true
  try {
    const { data } = await axios.post('/api/points/daily-spin')
    const earned = data?.data?.earned ?? data?.earned ?? 0
    lastSpin.value = `🎉 ${earned} 포인트 획득!`
    spunToday.value = true
    await auth.refreshBalance?.()
    await loadHistory()
  } catch (e) {
    if (e.response?.status === 429 || e.response?.data?.message?.includes('이미')) {
      spunToday.value = true
      lastSpin.value = '오늘은 이미 참여했습니다.'
    } else {
      site.toast('룰렛 실행 실패', 'error')
    }
  } finally { spinning.value = false }
}

onMounted(async () => {
  await loadHistory()
  await auth.refreshBalance?.()
})
</script>
