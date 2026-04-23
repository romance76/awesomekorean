<template>
  <div class="min-h-screen bg-green-900 text-white pb-20">
    <!-- 헤더 -->
    <div class="bg-green-800 px-4 py-3 flex items-center justify-between">
      <button v-if="currentView !== 'menu'" @click="goBack" class="text-green-300 hover:text-white">
        ← 뒤로
      </button>
      <router-link v-else to="/games/casino" class="text-green-300 hover:text-white">← 카지노</router-link>
      <h1 class="font-bold text-lg">🎴 맞고</h1>
      <span class="text-green-300 text-sm">{{ room?.code || '' }}</span>
    </div>

    <!-- ========== 화면 1: 모드 선택 ========== -->
    <div v-if="currentView === 'menu'" class="max-w-md mx-auto px-4 py-8">
      <div class="text-center mb-8">
        <div class="text-6xl mb-4">🎴</div>
        <h2 class="text-3xl font-bold mb-2">맞고</h2>
        <p class="text-green-300">1:1 대전 카드 게임</p>
      </div>

      <div class="space-y-4">
        <!-- 컴퓨터 대전 -->
        <router-link to="/games/gostop/solo"
          class="block w-full bg-green-700 hover:bg-green-600 rounded-2xl p-5 transition-all transform hover:scale-[1.02]">
          <div class="flex items-center gap-4">
            <div class="text-4xl">🤖</div>
            <div>
              <div class="font-bold text-lg">컴퓨터 대전</div>
              <div class="text-green-300 text-sm">AI와 연습 게임</div>
            </div>
          </div>
        </router-link>

        <!-- 빠른 대전 -->
        <button @click="startQuickMatch"
          class="block w-full bg-orange-600 hover:bg-orange-500 rounded-2xl p-5 transition-all transform hover:scale-[1.02] text-left">
          <div class="flex items-center gap-4">
            <div class="text-4xl">⚡</div>
            <div>
              <div class="font-bold text-lg">빠른 대전</div>
              <div class="text-orange-200 text-sm">자동 매칭으로 바로 시작</div>
            </div>
          </div>
        </button>

        <!-- 친구와 대전 -->
        <button @click="currentView = 'friend'"
          class="block w-full bg-blue-600 hover:bg-blue-500 rounded-2xl p-5 transition-all transform hover:scale-[1.02] text-left">
          <div class="flex items-center gap-4">
            <div class="text-4xl">👥</div>
            <div>
              <div class="font-bold text-lg">친구와 대전</div>
              <div class="text-blue-200 text-sm">방 코드로 초대하기</div>
            </div>
          </div>
        </button>
      </div>

      <!-- 게임머니 잔액 + 베팅 설정 -->
      <div class="mt-8 bg-green-800 rounded-2xl p-4 space-y-3">
        <div class="flex items-center justify-between">
          <span class="text-green-300 text-sm">🎰 게임머니 잔액</span>
          <span class="text-yellow-300 font-bold text-lg">{{ chipBalance.toLocaleString() }}</span>
        </div>
        <div v-if="chipBalance === 0" class="bg-orange-600/30 border border-orange-500/50 rounded-xl p-3 text-center">
          <p class="text-orange-200 text-sm mb-2">게임머니가 부족합니다</p>
          <button @click="$router.push('/points')" class="bg-orange-500 hover:bg-orange-400 text-white text-sm px-4 py-1.5 rounded-lg font-bold">
            🎡 룰렛 돌리러 가기
          </button>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-green-300 text-sm">베팅 게임머니</span>
          <select v-model="betPoints" class="bg-green-700 rounded-lg px-3 py-1 text-sm">
            <option :value="50">50 (연습)</option>
            <option :value="100">100</option>
            <option :value="500">500</option>
            <option :value="1000">1,000</option>
          </select>
        </div>
      </div>
    </div>

    <!-- ========== 화면 2: 빠른 대전 (매칭 중) ========== -->
    <div v-else-if="currentView === 'quickmatch'" class="max-w-md mx-auto px-4 py-8">
      <div class="bg-green-800 rounded-2xl p-8 text-center space-y-6">
        <div class="text-5xl animate-pulse">⚡</div>
        <h2 class="text-2xl font-bold">매칭 중...</h2>
        <p class="text-green-300">상대를 찾고 있습니다</p>
        <div class="flex justify-center">
          <div class="flex gap-1">
            <div class="w-3 h-3 bg-yellow-400 rounded-full animate-bounce" style="animation-delay: 0ms"></div>
            <div class="w-3 h-3 bg-yellow-400 rounded-full animate-bounce" style="animation-delay: 150ms"></div>
            <div class="w-3 h-3 bg-yellow-400 rounded-full animate-bounce" style="animation-delay: 300ms"></div>
          </div>
        </div>
        <p class="text-yellow-300 text-sm">베팅: {{ betPoints.toLocaleString() }} 게임머니</p>
        <button @click="cancelMatch" class="bg-gray-600 hover:bg-gray-500 rounded-xl px-6 py-3 font-bold">
          취소
        </button>
      </div>
    </div>

    <!-- ========== 화면 3: 친구와 대전 ========== -->
    <div v-else-if="currentView === 'friend'" class="max-w-md mx-auto px-4 py-8 space-y-4">
      <!-- 방 만들기 -->
      <div class="bg-green-800 rounded-2xl p-6 space-y-4">
        <h2 class="text-lg font-bold text-center">새 방 만들기</h2>
        <button @click="createFriendRoom" :disabled="creating"
          class="w-full bg-blue-600 hover:bg-blue-700 rounded-xl py-3 font-bold disabled:opacity-50">
          {{ creating ? '만드는 중...' : '방 만들기' }}
        </button>
      </div>

      <!-- 코드로 입장 -->
      <div class="bg-green-800 rounded-2xl p-6 space-y-3">
        <h2 class="text-lg font-bold text-center">코드로 입장</h2>
        <input v-model="joinCode" placeholder="방 코드 6자리" maxlength="6"
          class="w-full bg-green-700 rounded-lg px-3 py-3 text-center font-mono text-xl uppercase placeholder-green-400 tracking-widest" />
        <button @click="joinByCode" :disabled="!joinCode"
          class="w-full bg-green-600 hover:bg-green-500 disabled:opacity-50 rounded-xl py-3 font-bold">
          입장하기
        </button>
      </div>
    </div>

    <!-- ========== 화면 4: 대기실 (방 코드 공유) ========== -->
    <div v-else-if="currentView === 'waiting'" class="max-w-md mx-auto px-4 py-8 space-y-4">
      <div class="bg-green-800 rounded-2xl p-6 text-center space-y-4">
        <div class="text-4xl">🀄</div>
        <h2 class="text-xl font-bold">
          방 코드: <span class="font-mono text-yellow-300 text-2xl tracking-widest">{{ room?.code }}</span>
        </h2>
        <p class="text-green-300 text-sm">친구에게 코드를 알려주세요</p>

        <button @click="copyCode"
          class="bg-green-700 hover:bg-green-600 rounded-lg px-4 py-2 text-sm">
          {{ copied ? '✅ 복사됨!' : '📋 코드 복사' }}
        </button>

        <div class="space-y-2 mt-4">
          <div v-for="p in players" :key="p.id"
            class="flex items-center justify-between bg-green-700 rounded-lg px-4 py-2">
            <span>{{ p.user?.name || p.user?.username }}</span>
            <span class="text-green-400">✅ 입장</span>
          </div>
          <div v-for="i in (2 - players.length)" :key="'empty-'+i"
            class="flex items-center justify-center bg-green-700/50 rounded-lg px-4 py-3 text-green-500">
            <span class="animate-pulse">상대를 기다리는 중...</span>
          </div>
        </div>

        <p class="text-yellow-300 text-sm">베팅: {{ (room?.bet_points || betPoints).toLocaleString() }} 게임머니</p>
      </div>
    </div>

    <!-- ========== 화면 5: 게임 플레이 ========== -->
    <div v-else-if="currentView === 'game' && gameState" class="px-2 py-3 space-y-3">

      <!-- 상대방 손패 수 -->
      <div class="flex justify-around">
        <div v-for="(count, uid) in opponentHands" :key="uid"
          class="bg-green-800 rounded-xl px-4 py-2 text-center">
          <div class="text-xs text-green-400">{{ getPlayerName(uid) }}</div>
          <div class="flex gap-1 mt-1">
            <div v-for="i in count" :key="i"
              class="w-6 h-8 bg-red-800 border border-red-600 rounded text-center text-xs flex items-center justify-center">🀄</div>
          </div>
          <div class="text-xs text-yellow-300 mt-1">{{ gameState.scores[uid] || 0 }}점</div>
        </div>
      </div>

      <!-- 바닥 패 (테이블) -->
      <div class="bg-green-700 rounded-2xl p-3">
        <div class="text-xs text-green-300 mb-2 flex items-center justify-between">
          <span>바닥 패 ({{ gameState.table?.length || 0 }}장)</span>
          <span class="text-yellow-300">덱: {{ deckSize }}장</span>
        </div>
        <div class="flex flex-wrap gap-1">
          <div v-for="card in gameState.table" :key="card.id"
            :style="{backgroundColor: card.bg}"
            class="w-12 h-16 rounded-lg border-2 border-white/30 flex flex-col items-center justify-center text-xs text-white shadow cursor-default"
            :class="{'ring-2 ring-yellow-400': selectedCard && selectedCard.month === card.month}">
            <div class="font-bold">{{ card.month_name }}</div>
            <div class="text-xs opacity-80">{{ typeEmoji(card.type) }}</div>
            <div class="text-xs truncate px-0.5">{{ card.name }}</div>
          </div>
        </div>
      </div>

      <!-- 뽑은 카드 -->
      <div v-if="gameState.last_drawn" class="text-center">
        <div class="text-xs text-green-300 mb-1">뽑은 카드</div>
        <div :style="{backgroundColor: gameState.last_drawn.bg}"
          class="inline-block w-12 h-16 rounded-lg border-2 border-yellow-300 flex flex-col items-center justify-center text-xs text-white shadow">
          <div class="font-bold">{{ gameState.last_drawn.month_name }}</div>
          <div>{{ typeEmoji(gameState.last_drawn.type) }}</div>
        </div>
      </div>

      <!-- 내 점수 & 획득 패 -->
      <div class="bg-green-800 rounded-xl px-4 py-2">
        <div class="flex items-center justify-between">
          <span class="text-sm font-bold">내 점수: <span class="text-yellow-300">{{ myScore }}점</span></span>
          <span class="text-xs text-green-400">고: {{ myGoCount }}회</span>
        </div>
        <div class="flex gap-3 mt-1 text-xs text-green-300">
          <span>광 {{ myCaptured.gwang.length }}</span>
          <span>열끗 {{ myCaptured.yeol.length }}</span>
          <span>띠 {{ myCaptured.tti.length }}</span>
          <span>피 {{ piCount }}</span>
        </div>
      </div>

      <!-- 내 손패 -->
      <div class="bg-green-800 rounded-2xl p-3">
        <div class="text-xs text-green-300 mb-2">내 패 ({{ myHand.length }}장) — 카드를 눌러 내세요</div>
        <div class="flex flex-wrap gap-1">
          <div v-for="card in myHand" :key="card.id"
            :style="{backgroundColor: card.bg}"
            class="w-12 h-16 rounded-lg border-2 flex flex-col items-center justify-center text-xs text-white shadow cursor-pointer transition-transform"
            :class="selectedCard?.id === card.id ? 'border-yellow-400 -translate-y-2 scale-105' : 'border-white/30 hover:scale-105'"
            @click="selectCard(card)">
            <div class="font-bold">{{ card.month_name }}</div>
            <div class="text-xs opacity-80">{{ typeEmoji(card.type) }}</div>
            <div class="text-xs truncate px-0.5">{{ card.name }}</div>
          </div>
        </div>
      </div>

      <!-- 액션 버튼 -->
      <div v-if="isMyTurn && gameState.phase === 'playing'" class="flex gap-2">
        <button v-if="selectedCard" @click="playCard"
          :disabled="playing"
          class="flex-1 bg-red-600 hover:bg-red-700 disabled:opacity-50 rounded-xl py-3 font-bold">
          {{ playing ? '내는 중...' : '카드 내기' }}
        </button>
        <div v-else class="flex-1 text-center text-green-300 py-3">카드를 선택하세요</div>
      </div>

      <div v-if="gameState.phase === 'go_decision' && isMyTurn" class="flex gap-2">
        <button @click="doStop" class="flex-1 bg-blue-600 hover:bg-blue-700 rounded-xl py-3 font-bold">
          🛑 스톱 ({{ myScore }}점 획득)
        </button>
        <button @click="doGo" class="flex-1 bg-orange-500 hover:bg-orange-600 rounded-xl py-3 font-bold">
          고! ({{ myGoCount + 1 }}회)
        </button>
      </div>

      <div v-if="!isMyTurn && gameState.phase === 'playing'" class="text-center text-green-300 py-2">
        {{ getCurrentPlayerName() }}님의 차례...
      </div>

      <!-- 게임 결과 -->
      <div v-if="gameState.phase === 'finished'"
        class="fixed inset-0 bg-black/80 flex items-center justify-center z-50">
        <div class="bg-green-800 rounded-2xl p-8 text-center max-w-sm w-full mx-4">
          <div class="text-5xl mb-3">{{ gameState.winner == myId ? '🎉' : '😢' }}</div>
          <h2 class="text-2xl font-bold mb-2">
            {{ gameState.winner == myId ? '승리!' : '패배...' }}
          </h2>
          <div class="text-green-300 text-sm space-y-1">
            <div v-for="(score, uid) in gameState.scores" :key="uid">
              {{ getPlayerName(uid) }}: {{ score }}점
            </div>
          </div>
          <div class="mt-4 text-yellow-300 font-bold">
            {{ gameState.winner == myId ? `+${(room?.bet_points || betPoints) * 1} 게임머니 획득!` : `-${room?.bet_points || betPoints} 게임머니` }}
          </div>
          <div class="mt-6 flex gap-2">
            <button @click="resetToMenu" class="flex-1 bg-red-600 rounded-xl py-3 font-bold">다시 하기</button>
            <router-link to="/games" class="flex-1 bg-gray-600 rounded-xl py-3 font-bold text-center">로비</router-link>
          </div>
        </div>
      </div>
    </div>

    <!-- 에러 토스트 -->
    <div v-if="error" class="fixed bottom-20 left-4 right-4 bg-red-600 rounded-xl px-4 py-3 text-sm z-50 text-center">
      {{ error }}
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'

const route  = useRoute()
const router = useRouter()
const auth   = useAuthStore()
const myId   = auth.user?.id

// ---- State ----
const currentView  = ref('menu')  // menu | quickmatch | friend | waiting | game
const room         = ref(null)
const roomId       = ref(null)
const players      = ref([])
const gameState    = ref(null)
const selectedCard = ref(null)
const creating     = ref(false)
const playing      = ref(false)
const error        = ref('')
const joinCode     = ref('')
const betPoints    = ref(100)
const copied       = ref(false)
const chipBalance  = ref(0)
let matchTimer     = null

// ---- Computed (game play) ----
const myHand = computed(() => {
  if (!gameState.value?.hands) return []
  return gameState.value.hands[myId] ?? []
})

const opponentHands = computed(() => {
  if (!gameState.value?.hands) return {}
  const h = {}
  for (const [uid, hand] of Object.entries(gameState.value.hands)) {
    if (parseInt(uid) !== myId) h[uid] = typeof hand === 'number' ? hand : hand.length
  }
  return h
})

const myCaptured = computed(() => gameState.value?.captured?.[myId] ?? { gwang:[], yeol:[], tti:[], pi:[] })
const myScore    = computed(() => gameState.value?.scores?.[myId] ?? 0)
const myGoCount  = computed(() => gameState.value?.go_count?.[myId] ?? 0)
const isMyTurn   = computed(() => parseInt(gameState.value?.current_player) === myId)
const deckSize   = computed(() => {
  const d = gameState.value?.deck
  return typeof d === 'number' ? d : (d?.length ?? 0)
})
const piCount = computed(() => {
  const pi = myCaptured.value.pi
  return pi.reduce((s, c) => s + (c.is_ssang ? 2 : 1), 0)
})

// ---- Helpers ----
function typeEmoji(type) {
  return { gwang:'🌟', yeol:'🎯', tti:'🎀', pi:'🃏', ssang:'🃏🃏' }[type] ?? '🃏'
}

function getPlayerName(uid) {
  const p = players.value.find(p => parseInt(p.user_id ?? p.user?.id) === parseInt(uid))
  return p?.user?.name || p?.user?.username || `플레이어${uid}`
}

function getCurrentPlayerName() {
  return getPlayerName(gameState.value?.current_player)
}

function selectCard(card) {
  selectedCard.value = selectedCard.value?.id === card.id ? null : card
}

function showError(msg) {
  error.value = msg
  setTimeout(() => error.value = '', 3000)
}

function goBack() {
  if (currentView.value === 'quickmatch') {
    cancelMatch()
  } else if (currentView.value === 'waiting' || currentView.value === 'friend') {
    leaveRoom()
    currentView.value = 'menu'
  } else if (currentView.value === 'game') {
    // 게임 중에는 뒤로가기 제한
    return
  } else {
    currentView.value = 'menu'
  }
}

function resetToMenu() {
  leaveRoom()
  room.value = null
  roomId.value = null
  gameState.value = null
  players.value = []
  selectedCard.value = null
  currentView.value = 'menu'
}

function leaveRoom() {
  if (roomId.value && window.Echo) {
    window.Echo.leave(`game.${roomId.value}`)
  }
}

async function copyCode() {
  if (!room.value?.code) return
  try {
    await navigator.clipboard.writeText(room.value.code)
    copied.value = true
    setTimeout(() => copied.value = false, 2000)
  } catch {
    // fallback
    showError('코드: ' + room.value.code)
  }
}

// ---- WebSocket ----
function subscribeToRoom(id) {
  if (!window.Echo) return
  window.Echo.channel(`game.${id}`)
    .listen('.state.changed', (e) => {
      loadState()
    })
}

async function loadState() {
  if (!roomId.value) return
  try {
    const { data } = await axios.get(`/api/games/rooms/${roomId.value}/state`)
    room.value    = data.room
    players.value = data.players

    if (data.state) {
      gameState.value = data.state
      if (currentView.value !== 'game') {
        currentView.value = 'game'
      }
    }

    // 2명 입장 + 아직 게임 시작 전이면 자동 ready
    if (data.room?.status === 'waiting' && data.players?.length >= 2) {
      autoReady()
    }
  } catch (e) {
    console.error('loadState error', e)
  }
}

async function autoReady() {
  try {
    await axios.post(`/api/games/rooms/${roomId.value}/ready`)
  } catch {
    // 이미 ready 상태이면 무시
  }
}

// ---- 빠른 대전 ----
async function startQuickMatch() {
  currentView.value = 'quickmatch'
  try {
    // 1. 대기 중인 방 찾기
    const { data: rooms } = await axios.get('/api/games/rooms')
    const waitingRoom = rooms.find(r =>
      r.status === 'waiting' &&
      r.max_players === 2 &&
      r.players_count < 2 &&
      !r.players?.some(p => p.user_id === myId)
    )

    if (waitingRoom) {
      // 대기 방 있으면 입장
      const { data } = await axios.post(`/api/games/rooms/${waitingRoom.id}/join`)
      room.value    = data
      roomId.value  = data.id
      players.value = data.players || []
      subscribeToRoom(data.id)
      // 입장 후 자동 ready
      await autoReady()
      currentView.value = 'waiting'
      return
    }

    // 2. 없으면 방 생성
    const { data } = await axios.post('/api/games/rooms', {
      max_players: 2,
      bet_points: betPoints.value
    })
    room.value    = data
    roomId.value  = data.id
    players.value = data.players || []
    subscribeToRoom(data.id)

    // 5초마다 방 상태 확인 (WebSocket 보완)
    matchTimer = setInterval(async () => {
      await loadState()
      if (players.value.length >= 2 || currentView.value === 'game') {
        clearInterval(matchTimer)
        matchTimer = null
      }
    }, 5000)
  } catch (e) {
    showError(e.response?.data?.message || '매칭 실패')
    currentView.value = 'menu'
  }
}

function cancelMatch() {
  if (matchTimer) {
    clearInterval(matchTimer)
    matchTimer = null
  }
  leaveRoom()
  room.value = null
  roomId.value = null
  players.value = []
  currentView.value = 'menu'
}

// ---- 친구와 대전 ----
async function createFriendRoom() {
  creating.value = true
  try {
    const { data } = await axios.post('/api/games/rooms', {
      max_players: 2,
      bet_points: betPoints.value
    })
    room.value    = data
    roomId.value  = data.id
    players.value = data.players || []
    subscribeToRoom(data.id)
    currentView.value = 'waiting'
  } catch (e) {
    showError(e.response?.data?.message || '방 생성 실패')
  }
  creating.value = false
}

async function joinByCode() {
  if (!joinCode.value) return
  try {
    const { data: list } = await axios.get('/api/games/rooms')
    const found = list.find(r => r.code === joinCode.value.toUpperCase())
    if (!found) {
      showError('방을 찾을 수 없습니다.')
      return
    }
    const { data } = await axios.post(`/api/games/rooms/${found.id}/join`)
    room.value    = data
    roomId.value  = data.id
    players.value = data.players || []
    subscribeToRoom(data.id)
    await autoReady()
    currentView.value = 'waiting'
  } catch (e) {
    showError(e.response?.data?.message || '입장 실패')
  }
}

// ---- 게임 플레이 ----
async function playCard() {
  if (!selectedCard.value || !isMyTurn.value) return
  playing.value = true
  try {
    const { data } = await axios.post(`/api/games/rooms/${roomId.value}/play`, { card_id: selectedCard.value.id })
    gameState.value = data
    selectedCard.value = null
  } catch (e) {
    showError(e.response?.data?.message || '카드를 낼 수 없습니다.')
  }
  playing.value = false
}

async function doGo() {
  try {
    const { data } = await axios.post(`/api/games/rooms/${roomId.value}/go`)
    gameState.value = data
  } catch (e) {
    showError(e.response?.data?.message || '고 선언 실패')
  }
}

async function doStop() {
  try {
    const { data } = await axios.post(`/api/games/rooms/${roomId.value}/stop`)
    gameState.value = data
  } catch (e) {
    showError(e.response?.data?.message || '스톱 선언 실패')
  }
}

// ---- 게임머니 잔액 로드 ----
async function loadChipBalance() {
  try {
    const { data } = await axios.get('/api/wallet/balance')
    chipBalance.value = data.chip || 0
  } catch {}
}

// ---- URL 파라미터로 방 입장 ----
onMounted(async () => {
  loadChipBalance()
  const qBet = parseInt(route.query.bet)
  if ([50,100,500,1000].includes(qBet)) betPoints.value = qBet
  const qRoom = route.query.room ? parseInt(route.query.room) : null
  if (qRoom) {
    roomId.value = qRoom
    await loadState()
    subscribeToRoom(qRoom)
    if (gameState.value) {
      currentView.value = 'game'
    } else {
      currentView.value = 'waiting'
    }
  }
})

onUnmounted(() => {
  if (matchTimer) {
    clearInterval(matchTimer)
  }
  leaveRoom()
})
</script>
