<template>
<div class="min-h-screen">
  <div class="max-w-3xl mx-auto px-4 py-5">
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-4">
      <span class="icon-chip w-9 h-9 bg-violet-50 text-violet-600"><AppIcon name="ticket" :size="20" /></span>
      Entry (Sweepstakes 응모권)
    </h1>

    <!-- 잔액 카드 -->
    <div class="bg-gradient-to-r from-violet-500 to-purple-600 rounded-2xl p-5 text-white mb-4 shadow-card">
      <div class="text-sm font-semibold opacity-90">내 Entry</div>
      <div class="text-3xl font-black mt-1">🎟 {{ entries.toLocaleString() }}</div>
      <div class="flex gap-3 mt-3">
        <RouterLink to="/sweepstakes" class="bg-white/20 px-4 py-1.5 rounded-lg text-sm font-bold hover:bg-white/30 transition-colors flex items-center gap-1.5">
          <AppIcon name="gift" :size="14" /> Sweepstakes 보기
        </RouterLink>
      </div>
    </div>

    <!-- 출석체크 카드 -->
    <div class="card p-5 mb-4">
      <div class="flex items-center justify-between mb-3">
        <div class="font-bold text-sm text-ink flex items-center gap-2">
          <span class="icon-chip w-7 h-7 bg-violet-50 text-violet-600"><AppIcon name="calendar" :size="14" /></span>
          출석체크
        </div>
        <button
          @click="doCheckin"
          :disabled="checkedInToday || checking"
          class="px-4 py-1.5 rounded-lg text-sm font-bold transition-colors flex items-center gap-1.5"
          :class="checkedInToday ? 'bg-gray-100 text-gray-400' : 'bg-violet-600 text-white hover:bg-violet-700'"
        >
          <AppIcon name="check" :size="14" v-if="checkedInToday" />
          {{ checkedInToday ? '오늘 완료' : '출석체크' }}
        </button>
      </div>

      <div class="flex items-center gap-2 mb-2">
        <span v-for="i in required" :key="i" class="text-2xl leading-none">
          {{ i <= progress ? '●' : '○' }}
        </span>
        <span class="ml-2 text-sm font-bold text-ink-muted">{{ progress }} / {{ required }}</span>
      </div>
      <p class="text-xs text-ink-muted">
        <template v-if="progress < required">
          {{ required - progress }}번 더 출석하면 Entry 1개를 받습니다.
        </template>
        <template v-else>
          오늘 출석을 완료하면 Entry 1개를 받습니다.
        </template>
      </p>
    </div>

    <!-- 체크인 결과 -->
    <div v-if="justEarned" class="bg-violet-50 border border-violet-200 rounded-xl p-4 mb-4 text-center">
      <div class="text-3xl mb-2">🎉</div>
      <div class="font-bold text-violet-700">축하합니다!<br>출석 {{ required }}회를 완료하여 🎟 Entry 1개를 받았습니다.</div>
    </div>

    <!-- 거래 내역 -->
    <div class="card overflow-hidden">
      <div class="px-4 py-3 border-b border-gray-50 font-bold text-sm text-ink flex items-center gap-2">
        <span class="icon-chip w-7 h-7 bg-violet-50 text-violet-600"><AppIcon name="chart-bar" :size="14" /></span>Entry 내역
      </div>
      <div v-if="loading" class="py-8 text-center text-ink-muted">로딩중...</div>
      <div v-else class="divide-y divide-gray-50">
        <div v-for="log in logs" :key="log.id" class="list-row flex justify-between items-center">
          <div>
            <div class="text-sm text-ink">{{ log.description }}</div>
            <div class="text-xs text-ink-muted">{{ formatDate(log.created_at) }}</div>
          </div>
          <div class="font-bold text-sm" :class="log.amount > 0 ? 'text-green-600' : 'text-red-500'">
            {{ log.amount > 0 ? '+' : '' }}{{ log.amount }}E
          </div>
        </div>
        <div v-if="!logs.length" class="py-16 text-center">
          <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="ticket" :size="28" :stroke-width="1.5" /></div>
          <p class="text-sm text-ink-muted">Entry 내역이 없습니다</p>
        </div>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useSiteStore } from '../../stores/site'
import { useAuthStore } from '../../stores/auth'
import AppIcon from '../../components/AppIcon.vue'
import axios from 'axios'

const siteStore = useSiteStore()
const auth = useAuthStore()

const entries = ref(0)
const progress = ref(0)
const required = ref(5)
const checkedInToday = ref(false)
const checking = ref(false)
const justEarned = ref(false)
const logs = ref([])
const loading = ref(true)

function formatDate(dt) {
  if (!dt) return ''
  const h = Math.floor((Date.now() - new Date(dt).getTime()) / 3600000)
  if (h < 1) return '방금'
  if (h < 24) return h + '시간 전'
  return new Date(dt).toLocaleDateString('ko-KR')
}

async function loadBalance() {
  const { data } = await axios.get('/api/entries/balance')
  entries.value = data.data.entries
  progress.value = data.data.checkin_progress
  required.value = data.data.checkin_required
  checkedInToday.value = data.data.checked_in_today
  if (auth.user) auth.user.entries = data.data.entries
}

async function loadHistory() {
  const { data } = await axios.get('/api/entries/history')
  logs.value = data.data?.data || data.data || []
}

async function doCheckin() {
  checking.value = true
  justEarned.value = false
  try {
    const { data } = await axios.post('/api/entries/checkin')
    progress.value = data.data.progress
    checkedInToday.value = true
    if (data.data.entry_awarded) {
      justEarned.value = true
      siteStore.toast('🎉 출석 완료! Entry 1개를 받았습니다', 'success')
    } else {
      siteStore.toast('출석체크 완료', 'success')
    }
    await Promise.all([loadBalance(), loadHistory()])
  } catch (e) {
    siteStore.toast(e.response?.data?.message || '출석체크 실패', 'error')
  }
  checking.value = false
}

onMounted(async () => {
  try {
    await Promise.all([loadBalance(), loadHistory()])
  } catch {}
  loading.value = false
})
</script>
