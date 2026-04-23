<template>
<div class="min-h-screen bg-gray-950 text-white">
  <div class="max-w-7xl mx-auto px-4 py-6 space-y-6">

    <!-- Header -->
    <div class="text-center relative">
      <button @click="$router.push('/games/casino')" class="text-white/50 hover:text-white text-lg absolute left-0 top-1/2 -translate-y-1/2" title="카지노 로비">&#9664;</button>
      <div class="flex items-center justify-center gap-3 mb-1">
        <span class="text-4xl">&#127920;</span>
        <h1 class="text-3xl font-black text-amber-400 tracking-wider">AwesomeKorean 포커</h1>
      </div>
      <p class="text-sm text-gray-400">토너먼트 &middot; AI 연습 &middot; 블랙잭 &mdash; 실력을 겨뤄보세요</p>
    </div>

    <!-- Main Grid: 2/3 + 1/3 -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- ============ LEFT: Main Content ============ -->
      <div class="lg:col-span-2 space-y-6">

        <!-- ===== 1. 토너먼트 섹션 ===== -->
        <section class="space-y-4">
          <h2 class="text-lg font-black text-amber-400 flex items-center gap-2">
            <span>&#127942;</span> 토너먼트
          </h2>

          <!-- 예정된 토너먼트 -->
          <div>
            <h3 class="text-sm font-bold text-gray-300 mb-3 flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-blue-400 animate-pulse"></span>
              예정된 토너먼트
            </h3>

            <div v-if="tournamentsLoading" class="text-center py-8">
              <div class="inline-block w-6 h-6 border-2 border-amber-400 border-t-transparent rounded-full animate-spin"></div>
              <p class="text-xs text-gray-300 mt-2">로딩 중...</p>
            </div>

            <div v-else-if="upcomingTournaments.length === 0" class="bg-gray-900 rounded-xl border border-gray-800 p-6 text-center">
              <span class="text-3xl">&#128566;</span>
              <p class="text-sm text-gray-300 mt-2">예정된 토너먼트가 없습니다</p>
            </div>

            <div v-else class="space-y-3">
              <div v-for="t in upcomingTournaments" :key="t.id"
                @click="goToWaitingRoom(t)"
                class="bg-gray-900 rounded-xl border border-gray-800 hover:border-amber-400/30 p-4 transition cursor-pointer">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                  <!-- Info -->
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                      <span class="text-base font-bold text-white truncate">{{ t.title }}</span>
                      <span class="shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full"
                        :class="typeBadgeClass(t.type)">
                        {{ typeBadgeLabel(t.type) }}
                      </span>
                      <span v-if="isRegistered(t)" class="shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full bg-green-500/20 text-green-400 border border-green-500/30">
                        참가중
                      </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 text-xs text-gray-400">
                      <span>&#128197; {{ formatTournamentTime(t.scheduled_at) }}</span>
                      <span>&#128176; {{ Number(t.buy_in || 0).toLocaleString() }} 칩</span>
                      <span>&#128101; {{ t.registered_count || 0 }}/{{ t.max_players }}명</span>
                      <span v-if="t.online_count" class="text-green-400">
                        &#128994; {{ t.online_count }}명 접속
                      </span>
                    </div>
                    <!-- Countdown -->
                    <div class="mt-2 text-xs font-mono text-amber-300">
                      &#9200; {{ getCountdown(t.scheduled_at) }}
                    </div>
                  </div>
                  <!-- Action -->
                  <div class="shrink-0" @click.stop>
                    <button v-if="isRegistered(t)"
                      @click="unregisterTournament(t.id)"
                      :disabled="t._actionLoading"
                      class="bg-red-600/20 hover:bg-red-600/40 text-red-400 border border-red-500/30 text-xs font-bold px-4 py-2 rounded-lg transition disabled:opacity-50">
                      {{ t._actionLoading ? '처리 중...' : '취소' }}
                    </button>
                    <button v-else-if="isPast(t)" disabled
                      class="bg-gray-700 text-gray-500 text-xs font-bold px-4 py-2 rounded-lg cursor-not-allowed">
                      종료됨
                    </button>
                    <button v-else
                      @click="registerTournament(t.id)"
                      :disabled="t._actionLoading || (t.registered_count >= t.max_players)"
                      class="bg-amber-500 hover:bg-amber-600 disabled:bg-gray-700 text-gray-950 text-xs font-bold px-4 py-2 rounded-lg transition disabled:text-gray-400">
                      {{ t._actionLoading ? '처리 중...' : t.registered_count >= t.max_players ? '마감' : '참가 신청' }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- 진행 중인 토너먼트 -->
          <div v-if="liveTournaments.length > 0">
            <h3 class="text-sm font-bold text-gray-300 mb-3 flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
              진행 중인 토너먼트
            </h3>
            <div class="space-y-3">
              <div v-for="t in liveTournaments" :key="t.id"
                @click="isRegistered(t) ? enterTournamentGame(t) : null"
                class="bg-gray-900 rounded-xl border border-red-500/20 p-4 transition"
                :class="isRegistered(t) ? 'hover:border-green-400/40 cursor-pointer' : ''">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                      <span class="text-base font-bold text-white truncate">{{ t.title }}</span>
                      <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-500/20 text-red-400 border border-red-500/30">LIVE</span>
                      <span v-if="isRegistered(t)" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-green-500/20 text-green-400 border border-green-500/30">
                        참가중
                      </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 text-xs text-gray-400">
                      <span>&#128101; {{ t.remaining_count || t.registered_count || '?' }}/{{ t.max_players }}명</span>
                      <span v-if="t.online_count" class="text-green-400">&#128994; {{ t.online_count }}명 접속</span>
                    </div>
                  </div>
                  <button v-if="isRegistered(t)"
                    @click.stop="enterTournamentGame(t)"
                    class="bg-green-600 hover:bg-green-500 text-white text-xs font-bold px-5 py-2 rounded-lg transition animate-pulse">
                    &#127918; 게임 입장
                  </button>
                  <button v-else disabled
                    class="bg-gray-700 text-gray-400 text-xs font-bold px-4 py-2 rounded-lg cursor-not-allowed">
                    관전 (준비 중)
                  </button>
                </div>
              </div>
            </div>
          </div>

        </section>

        <!-- ===== 2. 게임 모드 카드 (토너먼트 바로 아래) ===== -->
        <section>
          <h2 class="text-lg font-black text-amber-400 flex items-center gap-2 mb-4">
            <span>&#127918;</span> 게임 모드
          </h2>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

            <!-- 솔로 AI 연습 -->
            <RouterLink to="/games/poker/play"
              class="group bg-gray-900 rounded-xl border border-gray-800 hover:border-green-400/40 p-5 transition hover:shadow-lg hover:shadow-green-500/5 block">
              <div class="text-3xl mb-3">&#129302;</div>
              <h3 class="text-sm font-bold text-green-400 group-hover:text-green-300 mb-1">솔로 AI 연습</h3>
              <p class="text-xs text-gray-300">AI 상대 연습 모드. 부담 없이 실력을 키워보세요.</p>
            </RouterLink>

            <!-- 멀티플레이어 대전 -->
            <RouterLink to="/games/poker/multi"
              class="group bg-gray-900 rounded-xl border border-gray-800 hover:border-amber-400/40 p-5 transition hover:shadow-lg hover:shadow-amber-500/5 block">
              <div class="text-3xl mb-3">👥</div>
              <h3 class="text-sm font-bold text-amber-400 group-hover:text-amber-300 mb-1">멀티플레이어 대전</h3>
              <p class="text-xs text-gray-300">실제 플레이어와 실시간 대전! 일반(15초) / 스피드(10초)</p>
            </RouterLink>

            <!-- 블랙잭 -->
            <RouterLink to="/games/blackjack"
              class="group bg-gray-900 rounded-xl border border-gray-800 hover:border-purple-400/40 p-5 transition hover:shadow-lg hover:shadow-purple-500/5 block">
              <div class="text-3xl mb-3">&#127183;</div>
              <h3 class="text-sm font-bold text-purple-400 group-hover:text-purple-300 mb-1">솔로 블랙잭</h3>
              <p class="text-xs text-gray-300">딜러와 1:1 블랙잭. 21에 가까워지세요!</p>
            </RouterLink>

            <!-- 룰 & 튜토리얼 -->
            <RouterLink to="/games/poker/tutorial"
              class="group bg-gray-900 rounded-xl border border-gray-800 hover:border-blue-400/40 p-5 transition hover:shadow-lg hover:shadow-blue-500/5 block">
              <div class="text-3xl mb-3">&#128218;</div>
              <h3 class="text-sm font-bold text-blue-400 group-hover:text-blue-300 mb-1">룰 &amp; 튜토리얼</h3>
              <p class="text-xs text-gray-300">포커 규칙과 전략을 배워보세요.</p>
            </RouterLink>
          </div>
        </section>

        <!-- ===== 3. 종료된 토너먼트 (접기 + 페이징) ===== -->
        <section v-if="pastTournaments.length > 0">
          <div class="flex items-center justify-between cursor-pointer" @click="showPastTournaments = !showPastTournaments">
            <h2 class="text-sm font-bold text-gray-500 flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-gray-600"></span>
              종료된 토너먼트 ({{ pastTournaments.length }})
            </h2>
            <span class="text-gray-500 text-xs">{{ showPastTournaments ? '&#9650; 접기' : '&#9660; 펼치기' }}</span>
          </div>
          <div v-if="showPastTournaments" class="mt-3 space-y-2">
            <div v-for="t in pagedPastTournaments" :key="t.id"
              class="bg-gray-900/50 rounded-xl border border-gray-800/50 p-3 opacity-60 hover:opacity-80 transition">
              <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2">
                    <span class="text-sm font-bold text-gray-400 truncate">{{ t.title }}</span>
                    <span class="text-[9px] px-1.5 py-0.5 rounded-full bg-gray-700 text-gray-500 font-bold">
                      {{ t.status === 'finished' ? '완료' : '취소' }}
                    </span>
                  </div>
                  <div class="text-[10px] text-gray-600 mt-0.5">
                    {{ formatTournamentTime(t.scheduled_at || t.finished_at) }} · {{ t.buy_in }}칩 · {{ t.registered_count || 0 }}명
                  </div>
                </div>
                <button v-if="t.status === 'finished'" @click.stop="showResults(t)" class="text-[10px] text-amber-600 hover:text-amber-400 font-bold">결과보기</button>
              </div>
            </div>
            <!-- 페이징 -->
            <div v-if="pastTournaments.length > pastPageSize" class="flex justify-center gap-2 pt-2">
              <button @click="pastPage = Math.max(1, pastPage - 1)" :disabled="pastPage <= 1"
                class="text-xs text-gray-400 hover:text-white disabled:opacity-30 px-2 py-1">&#9664; 이전</button>
              <span class="text-xs text-gray-500">{{ pastPage }} / {{ pastTotalPages }}</span>
              <button @click="pastPage = Math.min(pastTotalPages, pastPage + 1)" :disabled="pastPage >= pastTotalPages"
                class="text-xs text-gray-400 hover:text-white disabled:opacity-30 px-2 py-1">다음 &#9654;</button>
            </div>
          </div>
        </section>
      </div>

      <!-- ============ RIGHT: Sidebar ============ -->
      <div class="space-y-6">

        <!-- ===== 3. 칩 지갑 ===== -->
        <div class="bg-gray-900 rounded-xl border border-amber-400/20 p-5">
          <h2 class="text-sm font-bold text-amber-400 mb-4 flex items-center gap-2">
            <span>&#128176;</span> 칩 지갑
          </h2>

          <div v-if="wallet" class="space-y-4">
            <!-- Chip Balance -->
            <div class="text-center">
              <div class="text-4xl font-black text-amber-400 font-mono leading-tight">
                {{ (wallet.chips_balance || 0).toLocaleString() }}
              </div>
              <div class="text-xs text-gray-300 mt-1">보유 칩</div>
            </div>

            <!-- Points -->
            <div class="text-center">
              <div class="text-lg font-bold text-blue-400 font-mono">
                {{ (auth.user?.points || 0).toLocaleString() }}P
              </div>
              <div class="text-xs text-gray-300">포인트 잔액</div>
            </div>

            <!-- Deposit / Withdraw -->
            <div class="space-y-2">
              <input v-model.number="walletAmount" type="number" min="100" step="100"
                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white text-center focus:border-amber-400 focus:outline-none"
                placeholder="금액 입력">
              <div class="flex gap-2">
                <button @click="handleDeposit" :disabled="walletLoading || !walletAmount"
                  class="flex-1 bg-green-600 hover:bg-green-700 disabled:bg-gray-700 text-white text-xs font-bold py-2 rounded-lg transition">
                  입금
                </button>
                <button @click="handleWithdraw" :disabled="walletLoading || !walletAmount"
                  class="flex-1 bg-red-600 hover:bg-red-700 disabled:bg-gray-700 text-white text-xs font-bold py-2 rounded-lg transition">
                  출금
                </button>
              </div>
            </div>
          </div>

          <div v-else class="text-center py-4">
            <p class="text-sm text-gray-300">로그인 후 이용 가능</p>
          </div>

          <div v-if="walletError" class="text-xs text-red-400 mt-3 text-center">{{ walletError }}</div>
        </div>

        <!-- ===== 4. 내 기록 ===== -->
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
          <h2 class="text-sm font-bold text-amber-400 mb-3 flex items-center gap-2">
            <span>&#128202;</span> 내 기록
          </h2>
          <div v-if="auth.isLoggedIn" class="space-y-2">
            <div class="flex justify-between text-xs">
              <span class="text-gray-300">참가 횟수</span>
              <span class="text-white font-bold font-mono">{{ myStats.gamesPlayed || myStats.games_played || 0 }}</span>
            </div>
            <div class="flex justify-between text-xs">
              <span class="text-gray-300">최고 성적</span>
              <span class="text-amber-400 font-bold font-mono">{{ bestPlacement || '-' }}</span>
            </div>
            <div class="flex justify-between text-xs">
              <span class="text-gray-300">총 상금</span>
              <span class="text-green-400 font-bold font-mono">{{ (myStats.totalWinnings || myStats.total_prize_won || 0).toLocaleString() }}</span>
            </div>
            <div class="flex justify-between text-xs">
              <span class="text-gray-300">승률</span>
              <span class="text-blue-400 font-bold font-mono">{{ myStats.winRate ? myStats.winRate + '%' : '-' }}</span>
            </div>
            <div class="flex justify-between text-xs">
              <span class="text-gray-300">바운티</span>
              <span class="text-yellow-400 font-bold font-mono">{{ myStats.bounties || myStats.total_bounties || 0 }}</span>
            </div>
          </div>
          <div v-else class="text-xs text-gray-400 text-center py-3">로그인 후 확인 가능</div>
        </div>

        <!-- ===== 5. 리더보드 (Top 5) ===== -->
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-bold text-amber-400 flex items-center gap-2">
              <span>&#127941;</span> 리더보드
            </h2>
            <RouterLink to="/games/leaderboard" class="text-xs text-blue-400 hover:text-blue-300">전체 보기 &rarr;</RouterLink>
          </div>
          <div v-if="leaderboard.length" class="space-y-2">
            <div v-for="(entry, i) in leaderboard.slice(0, 5)" :key="i"
              class="flex items-center gap-2 text-xs">
              <span class="w-6 text-center font-bold"
                :class="i === 0 ? 'text-amber-400' : i === 1 ? 'text-gray-300' : i === 2 ? 'text-orange-400' : 'text-gray-300'">
                {{ i === 0 ? '&#127942;' : i === 1 ? '&#129352;' : i === 2 ? '&#129353;' : (i + 1) }}
              </span>
              <span class="flex-1 text-gray-300 truncate">{{ entry.user_name || entry.user?.name || entry.name || '익명' }}</span>
              <span class="text-amber-400 font-bold font-mono">{{ (entry.total_winnings || entry.total_prize_won || 0).toLocaleString() }}</span>
            </div>
          </div>
          <div v-else class="text-xs text-gray-400 text-center py-3">아직 데이터 없음</div>
        </div>
      </div>
    </div>
  </div>

  <!-- 결과 모달 -->
  <div v-if="resultsModal" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50" @click.self="resultsModal=null">
    <div class="bg-gray-900 rounded-2xl border border-amber-500/30 p-6 max-w-md w-full mx-4">
      <div class="text-center mb-4">
        <div class="text-3xl mb-1">&#127942;</div>
        <h3 class="text-lg font-black text-amber-400">{{ resultsModal.tournament?.title }}</h3>
        <p class="text-xs text-gray-500">{{ formatTournamentTime(resultsModal.tournament?.scheduled_at) }} · {{ resultsModal.tournament?.registered_count || resultsModal.results?.length || 0 }}명</p>
      </div>
      <div class="space-y-1.5 max-h-[300px] overflow-y-auto">
        <div v-for="(r, i) in resultsModal.results" :key="i"
          class="flex items-center justify-between text-sm px-3 py-2 rounded-lg"
          :class="i === 0 ? 'bg-amber-500/10 border border-amber-500/20' : i === 1 ? 'bg-gray-800/50' : i === 2 ? 'bg-orange-500/5' : 'bg-gray-800/30'">
          <span class="font-bold w-8" :class="i===0?'text-amber-400':i===1?'text-gray-300':i===2?'text-orange-400':'text-gray-500'">
            {{ i===0?'&#129351;':i===1?'&#129352;':i===2?'&#129353;':(r.finish_position||i+1) }}
          </span>
          <span class="flex-1 text-white truncate">{{ r.name }}</span>
          <span v-if="r.prize_won > 0" class="text-amber-400 font-bold font-mono text-xs">+{{ r.prize_won.toLocaleString() }}</span>
        </div>
      </div>
      <button @click="resultsModal=null" class="mt-4 w-full bg-gray-800 hover:bg-gray-700 text-gray-300 font-bold py-2 rounded-lg text-sm">닫기</button>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { usePokerWallet } from '@/composables/usePokerWallet'
import axios from 'axios'

const router = useRouter()
const auth = useAuthStore()
const {
  wallet, stats, leaderboard, loading: walletLoading, error: walletError,
  fetchWallet, deposit, withdraw, fetchStats, fetchLeaderboard
} = usePokerWallet()

// ─── Wallet ───
const walletAmount = ref(500)

async function handleDeposit() {
  if (!walletAmount.value || walletAmount.value < 100) return
  const result = await deposit(walletAmount.value)
  if (result?.success) {
    auth.user.points = result.data.points
  }
}

async function handleWithdraw() {
  if (!walletAmount.value || walletAmount.value < 100) return
  const result = await withdraw(walletAmount.value)
  if (result?.success) {
    auth.user.points = result.data.points
  }
}

// ─── Stats ───
const myStats = computed(() => stats.value || {})
const bestPlacement = computed(() => {
  const s = stats.value || {}
  const v = s.bestPlacement || s.best_place
  return v ? v + '위' : null
})

// ─── Tournaments ───
const tournaments = ref([])
const tournamentsLoading = ref(false)

const now = ref(Date.now())
setInterval(() => now.value = Date.now(), 10000) // 10초마다 갱신

const upcomingTournaments = computed(() =>
  tournaments.value
    .filter(t => ['scheduled', 'registering', 'starting'].includes(t.status) && new Date(t.scheduled_at) > new Date(now.value - 3600000))
    .sort((a, b) => new Date(a.scheduled_at) - new Date(b.scheduled_at))
)
const liveTournaments = computed(() =>
  tournaments.value.filter(t => t.status === 'running')
)
const pastTournaments = computed(() =>
  tournaments.value
    .filter(t => ['finished', 'cancelled'].includes(t.status))
    .sort((a, b) => new Date(b.finished_at || b.scheduled_at) - new Date(a.finished_at || a.scheduled_at))
)
const showPastTournaments = ref(false)
const pastPage = ref(1)
const pastPageSize = 10
const pastTotalPages = computed(() => Math.ceil(pastTournaments.value.length / pastPageSize))
const pagedPastTournaments = computed(() => {
  const start = (pastPage.value - 1) * pastPageSize
  return pastTournaments.value.slice(start, start + pastPageSize)
})
function isPast(t) { return ['finished', 'cancelled'].includes(t.status) }

const myRegisteredIds = ref([])

async function fetchMyRegistrations() {
  try {
    const { data } = await axios.get('/api/poker/my-registrations')
    if (data.success) {
      myRegisteredIds.value = data.data || []
    }
  } catch {
    // 비로그인 시 401 — 무시
  }
}

async function fetchTournaments() {
  tournamentsLoading.value = true
  try {
    // 먼저 내 참가 목록, 그 다음 토너먼트 목록
    await fetchMyRegistrations()
    const { data } = await axios.get('/api/poker/tournaments')
    if (data.success) {
      const raw = Array.isArray(data.data)
        ? data.data
        : [...(data.data.upcoming || []), ...(data.data.running || []), ...(data.data.finished || [])]
      const regIds = myRegisteredIds.value
      tournaments.value = raw.map(t => ({
        ...t,
        _actionLoading: false,
        is_registered: regIds.includes(t.id),
      }))
    } else {
      tournaments.value = []
    }
  } catch {
    tournaments.value = []
  } finally {
    tournamentsLoading.value = false
  }
}

function isRegistered(tournament) {
  return !!tournament.is_registered
}

function goToWaitingRoom(t) {
  router.push(`/games/poker/tournament/${t.id}`)
}

const resultsModal = ref(null)

async function showResults(t) {
  try {
    const { data } = await axios.get(`/api/poker/tournaments/${t.id}/results`)
    if (data.success) resultsModal.value = data.data
  } catch {
    alert('결과를 불러올 수 없습니다.')
  }
}

async function enterTournamentGame(t) {
  try {
    const { data } = await axios.get(`/api/poker/tournaments/${t.id}/game`)
    if (data.success && data.data?.gameId) {
      router.push(`/games/poker/multi?tournament=${t.id}&game=${data.data.gameId}`)
    } else {
      // 게임이 아직 준비 안 됨 → 대기실로
      router.push(`/games/poker/tournament/${t.id}`)
    }
  } catch {
    router.push(`/games/poker/tournament/${t.id}`)
  }
}

async function registerTournament(id) {
  const t = tournaments.value.find(x => x.id === id)
  if (t) t._actionLoading = true
  try {
    await axios.post(`/api/poker/tournaments/${id}/register`)
  } catch (e) {
    const msg = e.response?.data?.message || ''
    if (!msg.includes('이미')) {
      alert(msg || '참가 신청에 실패했습니다.')
    }
  }
  // 성공이든 실패든 항상 새로고침
  await fetchTournaments()
  if (auth.isLoggedIn) await fetchWallet()
  if (t) t._actionLoading = false
}

async function unregisterTournament(id) {
  const t = tournaments.value.find(x => x.id === id)
  if (t) t._actionLoading = true
  try {
    await axios.delete(`/api/poker/tournaments/${id}/register`)
    await fetchTournaments()
    if (auth.isLoggedIn) await fetchWallet()
  } catch (e) {
    alert(e.response?.data?.message || '취소에 실패했습니다.')
  } finally {
    if (t) t._actionLoading = false
  }
}

// ─── Tournament Badge Helpers ───
function typeBadgeClass(type) {
  const map = {
    freezeout: 'bg-blue-500/20 text-blue-400 border border-blue-500/30',
    freeroll: 'bg-green-500/20 text-green-400 border border-green-500/30',
    rebuy: 'bg-green-500/20 text-green-400 border border-green-500/30',
    bounty: 'bg-red-500/20 text-red-400 border border-red-500/30',
    turbo: 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30',
    satellite: 'bg-purple-500/20 text-purple-400 border border-purple-500/30',
    micro: 'bg-blue-500/20 text-blue-400 border border-blue-500/30',
    regular: 'bg-amber-500/20 text-amber-400 border border-amber-500/30',
    high_roller: 'bg-red-500/20 text-red-400 border border-red-500/30',
  }
  return map[type] || 'bg-gray-500/20 text-gray-400 border border-gray-500/30'
}

function typeBadgeLabel(type) {
  const map = {
    freezeout: '프리즈아웃',
    freeroll: '프리롤',
    rebuy: '리바이',
    bounty: '바운티',
    turbo: '터보',
    satellite: '새틀라이트',
    micro: '마이크로',
    regular: '일반',
    high_roller: '하이롤러',
  }
  return map[type] || type || '일반'
}

// ─── Date / Countdown Formatting ───
// now는 위에서 이미 선언됨
let clockInterval = null

function formatTournamentTime(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  const today = new Date()
  const tomorrow = new Date()
  tomorrow.setDate(today.getDate() + 1)

  const hours = String(d.getHours()).padStart(2, '0')
  const minutes = String(d.getMinutes()).padStart(2, '0')
  const time = `${hours}:${minutes}`

  if (d.toDateString() === today.toDateString()) return `오늘 ${time}`
  if (d.toDateString() === tomorrow.toDateString()) return `내일 ${time}`

  const month = d.getMonth() + 1
  const day = d.getDate()
  return `${month}/${day} ${time}`
}

function getCountdown(dateStr) {
  if (!dateStr) return ''
  const diff = new Date(dateStr).getTime() - now.value
  if (diff <= 0) return '곧 시작!'

  const days = Math.floor(diff / 86400000)
  const hours = Math.floor((diff % 86400000) / 3600000)
  const mins = Math.floor((diff % 3600000) / 60000)
  const secs = Math.floor((diff % 60000) / 1000)

  if (days > 0) return `${days}일 ${hours}시간 ${mins}분`
  if (hours > 0) return `${hours}시간 ${mins}분 ${secs}초`
  return `${mins}분 ${secs}초`
}

// ─── Real-time: Echo ───
let echoChannel = null

function setupEcho() {
  if (typeof window !== 'undefined' && window.Echo) {
    echoChannel = window.Echo.channel('poker.lobby')
    echoChannel.listen('.tournament.updated', () => {
      fetchTournaments()
    })
    echoChannel.listen('.tournament.started', (e) => {
      // 내가 참가한 토너먼트가 시작됨 → 자동 이동
      if (e.player_ids?.includes(auth.user?.id) && e.tournament_id && e.game_id) {
        router.push(`/games/poker/multi?tournament=${e.tournament_id}&game=${e.game_id}`)
      } else {
        fetchTournaments()
      }
    })
  }
}

function teardownEcho() {
  if (echoChannel && window.Echo) {
    window.Echo.leave('poker.lobby')
    echoChannel = null
  }
}

// ─── Heartbeat ───
let heartbeatInterval = null

function startHeartbeat() {
  stopHeartbeat()
  heartbeatInterval = setInterval(() => {
    const registered = tournaments.value.filter(t =>
      t.is_registered && (t.status === 'upcoming' || t.status === 'open')
    )
    registered.forEach(t => {
      axios.post(`/api/poker/tournaments/${t.id}/heartbeat`).catch(() => {})
    })
  }, 30000)
}

function stopHeartbeat() {
  if (heartbeatInterval) {
    clearInterval(heartbeatInterval)
    heartbeatInterval = null
  }
}

// ─── Lifecycle ───
onMounted(async () => {
  // Start countdown clock (updates every second)
  clockInterval = setInterval(() => { now.value = Date.now() }, 1000)

  // Setup real-time tournament updates
  setupEcho()

  // Fetch all data in parallel
  const promises = [fetchTournaments()]
  if (auth.isLoggedIn) {
    promises.push(fetchWallet(), fetchStats(), fetchLeaderboard())
  }
  await Promise.all(promises)

  // Start heartbeat for registered tournaments
  if (auth.isLoggedIn) startHeartbeat()
})

onUnmounted(() => {
  if (clockInterval) clearInterval(clockInterval)
  teardownEcho()
  stopHeartbeat()
})
</script>
