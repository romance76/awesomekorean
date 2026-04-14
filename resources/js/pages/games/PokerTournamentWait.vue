<template>
<div class="min-h-screen bg-gray-950 text-white flex flex-col">

  <!-- Top Bar -->
  <div class="bg-gray-900 border-b border-gray-800 px-4 py-3">
    <div class="max-w-6xl mx-auto">
      <div class="flex items-center justify-between mb-3">
        <router-link to="/games/poker" class="text-gray-400 hover:text-white text-sm flex items-center gap-1">
          ◀ 로비로
        </router-link>
        <div class="flex items-center gap-2">
          <h1 class="text-lg font-bold text-white truncate">{{ tournament?.title || '토너먼트' }}</h1>
          <span v-if="tournament?.type" class="text-[10px] font-bold px-2 py-0.5 rounded-full"
            :class="typeBadgeClass">
            {{ typeBadgeText }}
          </span>
        </div>
        <div class="w-16"></div>
      </div>

      <!-- Countdown + Status -->
      <div class="text-center">
        <div class="text-4xl font-black font-mono tracking-widest"
          :class="statusColor">
          {{ countdownDisplay }}
        </div>
        <div class="mt-1">
          <span class="text-xs font-bold px-3 py-1 rounded-full"
            :class="statusBadgeClass">
            {{ statusText }}
          </span>
        </div>
      </div>
    </div>
  </div>

  <!-- Main Content -->
  <div class="flex-1 max-w-6xl mx-auto w-full px-4 py-4">
    <div class="flex flex-col lg:flex-row gap-4 h-full">

      <!-- Left: 대기자 명단 (60%) -->
      <div class="lg:w-[60%] space-y-3">
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-bold text-blue-400">👥 대기자 명단</h2>
            <span class="text-xs text-gray-400">
              <span class="text-white font-bold">{{ entries.length }}</span>/<span class="text-amber-400 font-bold">{{ tournament?.max_players || '—' }}</span>명 등록
            </span>
          </div>

          <!-- My registration status -->
          <div v-if="myEntry" class="mb-3 bg-green-900/30 border border-green-500/30 rounded-lg px-3 py-2 flex items-center gap-2">
            <span class="text-green-400 text-xs">✔</span>
            <span class="text-xs text-green-300 font-bold">참가 등록 완료</span>
            <span class="text-[10px] text-gray-500 ml-auto">{{ formatTime(myEntry.registered_at) }}</span>
          </div>

          <!-- Players Grid -->
          <div v-if="entries.length" class="grid grid-cols-2 sm:grid-cols-3 gap-2 max-h-[400px] overflow-y-auto pr-1">
            <div v-for="entry in entries" :key="entry.user_id"
              class="bg-gray-800 rounded-lg p-3 flex items-center gap-2"
              :class="entry.user_id === auth.user?.id ? 'ring-1 ring-amber-400/50' : ''">
              <!-- Avatar -->
              <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold shrink-0"
                :class="entry.user_id === auth.user?.id ? 'bg-amber-500 text-gray-950' : 'bg-gray-700 text-gray-300'">
                <img v-if="entry.avatar" :src="entry.avatar" class="w-8 h-8 rounded-full object-cover"
                  @error="$event.target.style.display='none'">
                <span v-else>{{ (entry.nickname || '?').charAt(0) }}</span>
              </div>
              <div class="flex-1 min-w-0">
                <div class="text-xs font-bold text-white truncate">{{ entry.nickname || '익명' }}</div>
                <div class="text-[10px] text-gray-500">{{ formatTime(entry.registered_at) }}</div>
              </div>
              <!-- Online indicator -->
              <span class="text-[10px] shrink-0" :title="entry.is_online ? '온라인' : '오프라인'">
                {{ entry.is_online ? '🟢' : '🔴' }}
              </span>
            </div>
          </div>
          <div v-else class="text-center py-8 text-gray-600 text-sm">
            아직 등록한 참가자가 없습니다
          </div>
        </div>
      </div>

      <!-- Right: 토너먼트 정보 (40%) -->
      <div class="lg:w-[40%] space-y-3">

        <!-- Buy-in & Chips -->
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-4 space-y-3">
          <h2 class="text-sm font-bold text-amber-400">📋 토너먼트 정보</h2>
          <div class="grid grid-cols-2 gap-2">
            <div class="bg-gray-800 rounded-lg p-3 text-center">
              <div class="text-[10px] text-gray-500">바이인</div>
              <div class="text-lg font-black text-amber-400 font-mono">{{ (tournament?.buy_in || 0).toLocaleString() }}</div>
              <div class="text-[10px] text-gray-500">칩</div>
            </div>
            <div class="bg-gray-800 rounded-lg p-3 text-center">
              <div class="text-[10px] text-gray-500">시작 칩</div>
              <div class="text-lg font-black text-green-400 font-mono">{{ (tournament?.starting_chips || 0).toLocaleString() }}</div>
              <div class="text-[10px] text-gray-500">칩</div>
            </div>
          </div>
          <div v-if="tournament?.bounty_amount" class="bg-yellow-900/20 border border-yellow-500/20 rounded-lg p-2 text-center">
            <span class="text-[10px] text-yellow-500">바운티</span>
            <div class="text-sm font-bold text-yellow-400 font-mono">{{ tournament.bounty_amount.toLocaleString() }} 칩</div>
          </div>
        </div>

        <!-- Prize Structure -->
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
          <h2 class="text-sm font-bold text-amber-400 mb-3">💰 상금 구조</h2>
          <div class="space-y-1.5">
            <div v-for="(prize, i) in prizeStructure" :key="i"
              class="flex items-center justify-between text-xs bg-gray-800/50 rounded-lg px-3 py-2">
              <span class="font-bold" :class="i === 0 ? 'text-amber-400' : i === 1 ? 'text-gray-300' : i === 2 ? 'text-orange-400' : 'text-gray-400'">
                {{ prize.label }}
              </span>
              <span class="text-amber-400 font-mono font-bold">{{ prize.percent }}%</span>
            </div>
          </div>
          <div class="mt-2 text-center">
            <span class="text-[10px] text-gray-500">총 상금풀: </span>
            <span class="text-sm font-bold text-amber-400 font-mono">{{ totalPrizePool.toLocaleString() }} 칩</span>
          </div>
        </div>

        <!-- Blind Schedule -->
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
          <h2 class="text-sm font-bold text-blue-400 mb-3">⏱ 블라인드 스케줄</h2>
          <div class="space-y-1">
            <div v-for="(level, i) in blindSchedule" :key="i"
              class="flex items-center justify-between text-xs"
              :class="i === 0 ? 'text-white' : 'text-gray-400'">
              <span class="w-14">Lv {{ level.level }}</span>
              <span class="flex-1 font-mono text-center">{{ level.small }}/{{ level.big }}</span>
              <span class="text-[10px] text-gray-500 w-14 text-right">{{ level.duration }}분</span>
            </div>
          </div>
          <div v-if="tournament?.blind_schedule?.length > 5" class="text-center mt-2">
            <span class="text-[10px] text-gray-600">+{{ tournament.blind_schedule.length - 5 }}개 레벨 더</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Bottom Action Bar -->
  <div class="bg-gray-900 border-t border-gray-800 px-4 py-3">
    <div class="max-w-6xl mx-auto flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span class="text-xs text-gray-500">보유 칩:</span>
        <span class="text-sm font-bold text-amber-400 font-mono">{{ (wallet?.chips_balance || 0).toLocaleString() }}</span>
      </div>

      <div>
        <button v-if="!myEntry"
          @click="registerForTournament"
          :disabled="registering || tournamentStatus === 'started'"
          class="bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 disabled:from-gray-700 disabled:to-gray-700 text-white font-bold text-sm px-6 py-2.5 rounded-xl shadow-lg shadow-green-500/20 transition-all hover:scale-105 disabled:hover:scale-100">
          {{ registering ? '처리 중...' : '참가 신청' }}
        </button>
        <button v-else
          @click="cancelRegistration"
          :disabled="cancelling || tournamentStatus === 'started'"
          class="bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 disabled:from-gray-700 disabled:to-gray-700 text-white font-bold text-sm px-6 py-2.5 rounded-xl shadow-lg shadow-red-500/20 transition-all hover:scale-105 disabled:hover:scale-100">
          {{ cancelling ? '처리 중...' : '참가 취소' }}
        </button>
      </div>
    </div>
  </div>

  <!-- Error Toast -->
  <Transition name="fade">
    <div v-if="errorMsg" class="fixed bottom-20 left-1/2 -translate-x-1/2 bg-red-600 text-white text-xs font-bold px-4 py-2 rounded-lg shadow-lg z-50">
      {{ errorMsg }}
    </div>
  </Transition>

  <!-- Tournament Starting Overlay -->
  <Transition name="fade">
    <div v-if="startingAnimation" class="fixed inset-0 bg-black/90 flex items-center justify-center z-[60]">
      <div class="text-center animate-pulse">
        <div class="text-6xl mb-4">🎮</div>
        <h2 class="text-3xl font-black text-amber-400 mb-2">토너먼트 시작!</h2>
        <p class="text-gray-400 text-sm">테이블로 이동 중...</p>
        <div class="mt-4 w-12 h-12 border-4 border-amber-400 border-t-transparent rounded-full animate-spin mx-auto"></div>
      </div>
    </div>
  </Transition>

</div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import { usePokerWallet } from '@/composables/usePokerWallet'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const { wallet, fetchWallet } = usePokerWallet()

const tournamentId = computed(() => route.params.id)
const tournament = ref(null)
const entries = ref([])
const registering = ref(false)
const cancelling = ref(false)
const errorMsg = ref('')
const now = ref(Date.now())
const startingAnimation = ref(false)

let heartbeatInterval = null
let countdownInterval = null
let startCheckInterval = null
let echoChannel = null
let errorTimeout = null

// --- Computed ---

const myEntry = computed(() => {
  if (!auth.user) return null
  return entries.value.find(e => e.user_id === auth.user.id)
})

const scheduledAt = computed(() => {
  if (!tournament.value?.scheduled_at) return null
  return new Date(tournament.value.scheduled_at).getTime()
})

const timeRemaining = computed(() => {
  if (!scheduledAt.value) return null
  return Math.max(0, scheduledAt.value - now.value)
})

const countdownDisplay = computed(() => {
  if (timeRemaining.value === null) return '--:--:--'
  if (timeRemaining.value <= 0) return '00:00:00'
  const total = Math.floor(timeRemaining.value / 1000)
  const h = String(Math.floor(total / 3600)).padStart(2, '0')
  const m = String(Math.floor((total % 3600) / 60)).padStart(2, '0')
  const s = String(total % 60).padStart(2, '0')
  return `${h}:${m}:${s}`
})

const tournamentStatus = computed(() => {
  if (!tournament.value) return 'loading'
  if (tournament.value.status === 'in_progress') return 'started'
  if (timeRemaining.value === null) return 'registering'
  if (timeRemaining.value <= 0) return 'starting'
  if (timeRemaining.value <= 60000) return 'soon'
  return 'registering'
})

const statusText = computed(() => {
  switch (tournamentStatus.value) {
    case 'registering': return '등록 중'
    case 'soon': return '곧 시작'
    case 'starting': return '시작!'
    case 'started': return '진행 중'
    default: return '대기 중'
  }
})

const statusColor = computed(() => {
  switch (tournamentStatus.value) {
    case 'registering': return 'text-blue-400'
    case 'soon': return 'text-yellow-400 animate-pulse'
    case 'starting': return 'text-green-400 animate-pulse'
    case 'started': return 'text-red-400'
    default: return 'text-gray-400'
  }
})

const statusBadgeClass = computed(() => {
  switch (tournamentStatus.value) {
    case 'registering': return 'bg-blue-500/20 text-blue-400 border border-blue-500/30'
    case 'soon': return 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30'
    case 'starting': return 'bg-green-500/20 text-green-400 border border-green-500/30'
    case 'started': return 'bg-red-500/20 text-red-400 border border-red-500/30'
    default: return 'bg-gray-500/20 text-gray-400 border border-gray-500/30'
  }
})

const typeBadgeClass = computed(() => {
  const type = tournament.value?.type
  if (type === 'bounty') return 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30'
  if (type === 'turbo') return 'bg-red-500/20 text-red-400 border border-red-500/30'
  if (type === 'freezeout') return 'bg-blue-500/20 text-blue-400 border border-blue-500/30'
  return 'bg-gray-500/20 text-gray-400 border border-gray-500/30'
})

const typeBadgeText = computed(() => {
  const type = tournament.value?.type
  if (type === 'bounty') return 'BOUNTY'
  if (type === 'turbo') return 'TURBO'
  if (type === 'freezeout') return 'FREEZEOUT'
  if (type === 'rebuy') return 'REBUY'
  return (type || '').toUpperCase()
})

const prizeStructure = computed(() => {
  if (tournament.value?.prize_structure?.length) {
    return tournament.value.prize_structure
  }
  // Default prize structure
  return [
    { label: '1등', percent: 25 },
    { label: '2등', percent: 16 },
    { label: '3등', percent: 11 },
    { label: '4등', percent: 8 },
    { label: '5등', percent: 6 },
    { label: '6~10등', percent: 4 },
  ]
})

const totalPrizePool = computed(() => {
  if (!tournament.value) return 0
  return (tournament.value.buy_in || 0) * entries.value.length
})

const blindSchedule = computed(() => {
  if (tournament.value?.blind_schedule?.length) {
    return tournament.value.blind_schedule.slice(0, 5)
  }
  // Default blind schedule
  return [
    { level: 1, small: 10, big: 20, duration: 10 },
    { level: 2, small: 20, big: 40, duration: 10 },
    { level: 3, small: 30, big: 60, duration: 10 },
    { level: 4, small: 50, big: 100, duration: 10 },
    { level: 5, small: 75, big: 150, duration: 10 },
  ]
})

// --- Methods ---

function showError(msg) {
  errorMsg.value = msg
  if (errorTimeout) clearTimeout(errorTimeout)
  errorTimeout = setTimeout(() => { errorMsg.value = '' }, 3000)
}

function formatTime(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleTimeString('ko-KR', { hour: '2-digit', minute: '2-digit' })
}

async function fetchTournament() {
  try {
    const { data } = await axios.get(`/api/poker/tournaments/${tournamentId.value}`)
    if (data.success !== false) {
      const t = data.data?.tournament || data.data || data
      tournament.value = t
      entries.value = data.data?.entries || data.entries || []

      // 이미 시작된 토너먼트면 게임 화면으로 이동
      if (t.status === 'running' && auth.isLoggedIn) {
        checkAndRedirectToGame()
      }
    }
  } catch (e) {
    showError('토너먼트 정보를 불러올 수 없습니다')
  }
}

async function checkAndRedirectToGame() {
  try {
    const { data } = await axios.get(`/api/poker/tournaments/${tournamentId.value}/game`)
    if (data.success && data.data?.gameId) {
      startingAnimation.value = true
      setTimeout(() => {
        router.push(`/games/poker/multi?tournament=${tournamentId.value}&game=${data.data.gameId}`)
      }, 1500)
    }
  } catch {
    // 게임 아직 준비 안 됨
  }
}

async function registerForTournament() {
  registering.value = true
  try {
    const { data } = await axios.post(`/api/poker/tournaments/${tournamentId.value}/register`)
    if (data.success !== false) {
      await fetchTournament()
      await fetchWallet()
    } else {
      showError(data.message || '참가 신청에 실패했습니다')
    }
  } catch (e) {
    showError(e.response?.data?.message || '참가 신청에 실패했습니다')
  } finally {
    registering.value = false
  }
}

async function cancelRegistration() {
  cancelling.value = true
  try {
    const { data } = await axios.delete(`/api/poker/tournaments/${tournamentId.value}/register`)
    if (data.success !== false) {
      await fetchTournament()
      await fetchWallet()
    } else {
      showError(data.message || '참가 취소에 실패했습니다')
    }
  } catch (e) {
    showError(e.response?.data?.message || '참가 취소에 실패했습니다')
  } finally {
    cancelling.value = false
  }
}

async function sendHeartbeat() {
  try {
    await axios.post(`/api/poker/tournaments/${tournamentId.value}/heartbeat`)
  } catch {
    // Silent fail
  }
}

function setupEcho() {
  if (!window.Echo) return
  const channelName = `poker.tournament.${tournamentId.value}`
  echoChannel = window.Echo.channel(channelName)
  echoChannel.listen('.tournament.updated', () => {
    fetchTournament()
  })
  echoChannel.listen('.tournament.started', (e) => {
    // 토너먼트 시작! → 게임 화면으로 이동
    if (e.game_id && e.player_ids?.includes(auth.user?.id)) {
      startingAnimation.value = true
      setTimeout(() => {
        router.push(`/games/poker/multi?tournament=${tournamentId.value}&game=${e.game_id}`)
      }, 2000) // 2초 애니메이션 후 이동
    }
  })
}

function cleanupEcho() {
  if (echoChannel && window.Echo) {
    window.Echo.leaveChannel(`poker.tournament.${tournamentId.value}`)
    echoChannel = null
  }
}

// --- Lifecycle ---

onMounted(async () => {
  await fetchTournament()

  if (auth.isLoggedIn) {
    await fetchWallet()
  }

  // Countdown timer: update every second
  countdownInterval = setInterval(() => {
    now.value = Date.now()
  }, 1000)

  // Heartbeat every 30 seconds
  sendHeartbeat()
  heartbeatInterval = setInterval(sendHeartbeat, 30000)

  // WebSocket
  setupEcho()

  // Fallback: 5초마다 토너먼트 상태 폴링 (시작 시간 근처)
  startCheckInterval = setInterval(async () => {
    if (startingAnimation.value) return
    const remaining = timeRemaining.value
    if (remaining !== null && remaining <= 60000) {
      await fetchTournament()
    }
  }, 5000)
})

onUnmounted(() => {
  if (countdownInterval) clearInterval(countdownInterval)
  if (heartbeatInterval) clearInterval(heartbeatInterval)
  if (startCheckInterval) clearInterval(startCheckInterval)
  if (errorTimeout) clearTimeout(errorTimeout)
  cleanupEcho()
})
</script>

<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>
