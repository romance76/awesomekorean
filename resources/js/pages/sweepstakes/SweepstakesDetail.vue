<template>
<div class="min-h-screen">
  <div class="max-w-2xl mx-auto px-4 py-5">
    <RouterLink to="/sweepstakes" class="text-sm text-ink-muted hover:text-ink">← Sweepstakes 목록</RouterLink>

    <div v-if="loading" class="py-16 text-center text-ink-muted">로딩중...</div>

    <template v-else-if="s">
      <div class="aspect-[16/9] bg-gray-100 rounded-2xl overflow-hidden mt-3 mb-4">
        <img v-if="s.prize_image" :src="s.prize_image" :alt="s.prize_name" class="w-full h-full object-cover">
        <div v-else class="w-full h-full flex items-center justify-center text-6xl">🎁</div>
      </div>

      <div class="flex items-center gap-1.5 text-xs font-bold text-violet-600 mb-1">
        <AppIcon name="ticket" :size="12" />{{ statusLabel(s.status) }}
      </div>
      <h1 class="text-xl font-bold text-ink">{{ s.title }}</h1>
      <div class="text-base text-ink-light mt-1">🎁 {{ s.prize_name }}<span v-if="s.prize_value"> (${{ s.prize_value }})</span></div>
      <p v-if="s.description" class="text-sm text-ink-muted mt-2 whitespace-pre-wrap">{{ s.description }}</p>

      <!-- 당첨자 발표 -->
      <div v-if="s.status === 'winner_selected'" class="bg-violet-50 border border-violet-200 rounded-xl p-4 mt-4 text-center">
        <div class="text-3xl mb-1">🏆</div>
        <div class="font-bold text-violet-700">당첨자: {{ s.winner_display_name || '-' }}</div>
        <div class="text-xs text-ink-muted mt-1">{{ formatDate(s.winner_selected_at) }} 선정</div>
      </div>

      <!-- 참가 현황 + 확률 휠 -->
      <div class="card p-5 mt-4">
        <div class="flex items-center justify-between text-sm mb-3">
          <span class="text-ink-muted">Total Entries</span>
          <span class="font-bold text-ink">{{ (s.total_entries || 0).toLocaleString() }}</span>
        </div>
        <div class="flex items-center justify-between text-sm mb-4">
          <span class="text-ink-muted">My Entries</span>
          <span class="font-bold text-violet-600">{{ s.my_entries || 0 }}</span>
        </div>

        <!-- 확률 휠 (내 Entry vs 다른 참가자 전체) -->
        <div class="relative w-[160px] h-[160px] mx-auto mb-3">
          <svg viewBox="0 0 160 160" class="w-full h-full -rotate-90">
            <circle cx="80" cy="80" r="70" fill="none" stroke="#f1f5f9" stroke-width="20" />
            <circle
              cx="80" cy="80" r="70" fill="none" stroke="#7c3aed" stroke-width="20"
              :stroke-dasharray="`${myArc} ${circumference - myArc}`"
              stroke-linecap="round"
            />
          </svg>
          <div class="absolute inset-0 flex flex-col items-center justify-center">
            <div class="text-2xl font-black text-violet-700">{{ probability }}%</div>
            <div class="text-[10px] text-ink-muted">당첨 확률</div>
          </div>
        </div>
        <p class="text-xs text-ink-faint text-center mb-4">현재 참가 현황 기준이며, 다른 회원이 추가로 응모하면 확률은 계속 변경됩니다.</p>

        <!-- 참가 UI -->
        <template v-if="s.status === 'active'">
          <div class="text-xs text-ink-muted text-center mb-2">보유 Entry: 🎟 {{ auth.user?.entries || 0 }}</div>
          <div class="grid grid-cols-3 gap-2">
            <button @click="enter(1)" :disabled="entering || (auth.user?.entries||0) < 1" class="btn-secondary text-sm py-2 disabled:opacity-40">1 Entry</button>
            <button @click="enter(5)" :disabled="entering || (auth.user?.entries||0) < 5" class="btn-secondary text-sm py-2 disabled:opacity-40">5 Entries</button>
            <button @click="enter(auth.user?.entries || 0)" :disabled="entering || !(auth.user?.entries > 0)" class="btn-primary text-sm py-2 disabled:opacity-40">ALL IN</button>
          </div>
          <RouterLink v-if="!auth.isLoggedIn" to="/login" class="block text-center text-sm text-violet-600 font-bold mt-3">로그인하고 참가하기</RouterLink>
          <RouterLink v-else-if="(auth.user?.entries||0) === 0" to="/entries" class="block text-center text-sm text-violet-600 font-bold mt-3">출석체크하고 Entry 받기 →</RouterLink>
        </template>
        <div v-else class="text-center text-sm text-ink-muted py-2">{{ statusLabel(s.status) }} — 지금은 참가할 수 없습니다</div>
      </div>
    </template>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useSiteStore } from '../../stores/site'
import AppIcon from '../../components/AppIcon.vue'
import axios from 'axios'

const route = useRoute()
const auth = useAuthStore()
const siteStore = useSiteStore()

const s = ref(null)
const loading = ref(true)
const entering = ref(false)
const circumference = 2 * Math.PI * 70

const probability = ref(0)
const myArc = ref(0)

function statusLabel(status) {
  return { draft: '준비중', active: '진행중', ended: '마감', winner_selected: '당첨자 발표', cancelled: '취소됨' }[status] || status
}
function formatDate(dt) {
  if (!dt) return ''
  return new Date(dt).toLocaleString('ko-KR')
}

function updateWheel() {
  probability.value = s.value?.my_win_probability_pct ?? 0
  myArc.value = (probability.value / 100) * circumference
}

async function load() {
  const { data } = await axios.get(`/api/sweepstakes/${route.params.id}`)
  s.value = data.data
  updateWheel()
}

async function enter(amount) {
  if (!amount || amount < 1) return
  entering.value = true
  try {
    const idempotencyKey = crypto.randomUUID()
    const { data } = await axios.post(`/api/sweepstakes/${route.params.id}/enter`, { amount, idempotency_key: idempotencyKey })
    if (auth.user) auth.user.entries = data.data.remaining_entries
    siteStore.toast(`🎟 ${amount} Entry 참가 완료!`, 'success')
    await load()
  } catch (e) {
    siteStore.toast(e.response?.data?.message || '참가 실패', 'error')
  }
  entering.value = false
}

onMounted(async () => {
  try {
    await load()
  } catch {}
  loading.value = false
})
</script>
