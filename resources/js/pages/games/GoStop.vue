<template>
  <div class="min-h-screen bg-green-900 text-white pb-20">
    <!-- 헤더 -->
    <div class="bg-green-800 px-4 py-3 flex items-center justify-between">
      <router-link to="/games" class="text-green-300">← 로비</router-link>
      <h1 class="font-bold">🀄 고스톱</h1>
      <span class="text-green-300 text-sm">{{ room?.code || '' }}</span>
    </div>

    <!-- 방 없음: 방 생성/입장 -->
    <div v-if="!roomId && !room" class="max-w-md mx-auto px-4 py-8 space-y-4">
      <div class="bg-green-800 rounded-2xl p-6 space-y-4">
        <h2 class="text-lg font-bold text-center">방 만들기</h2>
        <div>
          <label class="text-green-300 text-sm">최대 인원</label>
          <select v-model="newRoom.max_players" class="w-full mt-1 bg-green-700 rounded-lg px-3 py-2">
            <option :value="2">2인</option>
            <option :value="3">3인</option>
          </select>
        </div>
        <div>
          <label class="text-green-300 text-sm">베팅 포인트</label>
          <select v-model="newRoom.bet_points" class="w-full mt-1 bg-green-700 rounded-lg px-3 py-2">
            <option :value="50">50P (연습)</option>
            <option :value="100">100P</option>
            <option :value="500">500P</option>
            <option :value="1000">1,000P</option>
          </select>
        </div>
        <button @click="createRoom" :disabled="creating"
          class="w-full bg-red-600 hover:bg-red-700 rounded-xl py-3 font-bold disabled:opacity-50">
          {{ creating ? '만드는 중...' : '방 만들기' }}
        </button>
      </div>

      <div class="bg-green-800 rounded-2xl p-6 space-y-3">
        <h2 class="text-lg font-bold text-center">코드로 입장</h2>
        <input v-model="joinCode" placeholder="방 코드 6자리" maxlength="6"
          class="w-full bg-green-700 rounded-lg px-3 py-2 text-center font-mono text-lg uppercase placeholder-green-400" />
        <button @click="joinByCode" class="w-full bg-blue-600 hover:bg-blue-700 rounded-xl py-3 font-bold">
          입장하기
        </button>
      </div>
    </div>

    <!-- 대기실 -->
    <div v-else-if="room && room.status === 'waiting'" class="max-w-md mx-auto px-4 py-8 space-y-4">
      <div class="bg-green-800 rounded-2xl p-6 text-center space-y-4">
        <div class="text-4xl">🀄</div>
        <h2 class="text-xl font-bold">방 코드: <span class="font-mono text-yellow-300">{{ room.code }}</span></h2>
        <p class="text-green-300 text-sm">친구에게 코드를 알려주세요</p>

        <div class="space-y-2">
          <div v-for="p in players" :key="p.id"
            class="flex items-center justify-between bg-green-700 rounded-lg px-4 py-2">
            <span>{{ p.user?.name || p.user?.username }}</span>
            <span :class="p.is_ready ? 'text-green-400' : 'text-gray-400'">
              {{ p.is_ready ? '✅ 준비완료' : '⏳ 대기중' }}
            </span>
          </div>
          <div v-for="i in (room.max_players - players.length)" :key="'empty-'+i"
            class="flex items-center justify-center bg-green-700/50 rounded-lg px-4 py-2 text-green-600">
            빈 자리
          </div>
        </div>

        <p class="text-yellow-300 text-sm">베팅: {{ room.bet_points.toLocaleString() }}P</p>
        <button @click="readyUp" :disabled="isReady"
          class="w-full bg-yellow-500 hover:bg-yellow-600 disabled:bg-gray-600 rounded-xl py-3 font-bold">
          {{ isReady ? '✅ 준비완료' : '준비하기' }}
        </button>
      </div>
    </div>

    <!-- 게임 화면 -->
    <div v-else-if="gameState" class="px-2 py-3 space-y-3">

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
      <div v-if="isMyTurn" class="flex gap-2">
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
            {{ gameState.winner == myId ? `+${room.bet_points * (players.length - 1)}P 획득!` : `-${room.bet_points}P` }}
          </div>
          <div class="mt-6 flex gap-2">
            <button @click="playAgain" class="flex-1 bg-red-600 rounded-xl py-3 font-bold">다시 하기</button>
            <router-link to="/games" class="flex-1 bg-gray-600 rounded-xl py-3 font-bold text-center">로비</router-link>
          </div>
        </div>
      </div>
    </div>

    <!-- 에러 -->
    <div v-if="error" class="fixed bottom-20 left-4 right-4 bg-red-600 rounded-xl px-4 py-3 text-sm">
      {{ error }}
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'
import Echo from 'laravel-echo'

const route  = useRoute()
const auth   = useAuthStore()
const myId   = auth.user?.id

const room      = ref(null)
const roomId    = ref(route.query.room ? parseInt(route.query.room) : null)
const players   = ref([])
const gameState = ref(null)
const selectedCard = ref(null)
const isReady   = ref(false)
const creating  = ref(false)
const playing   = ref(false)
const error     = ref('')
const joinCode  = ref('')
const newRoom   = ref({ max_players: 3, bet_points: 100 })

let echo = null

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

async function createRoom() {
  creating.value = true
  try {
    const { data } = await axios.post('/api/games/rooms', newRoom.value)
    room.value    = data
    roomId.value  = data.id
    players.value = data.players || []
    subscribeToRoom(data.id)
  } catch (e) {
    error.value = e.response?.data?.message || '방 생성 실패'
    setTimeout(() => error.value = '', 3000)
  }
  creating.value = false
}

async function joinByCode() {
  if (!joinCode.value) return
  try {
    const { data: list } = await axios.get('/api/games/rooms')
    const found = list.find(r => r.code === joinCode.value.toUpperCase())
    if (!found) { error.value = '방을 찾을 수 없습니다.'; return }
    const { data } = await axios.post(`/api/games/rooms/${found.id}/join`)
    room.value    = data
    roomId.value  = data.id
    players.value = data.players || []
    subscribeToRoom(data.id)
  } catch (e) {
    error.value = e.response?.data?.message || '입장 실패'
    setTimeout(() => error.value = '', 3000)
  }
}

async function readyUp() {
  try {
    await axios.post(`/api/games/rooms/${roomId.value}/ready`)
    isReady.value = true
  } catch (e) {
    error.value = e.response?.data?.message || '준비 실패'
  }
}

async function playCard() {
  if (!selectedCard.value || !isMyTurn.value) return
  playing.value = true
  try {
    const { data } = await axios.post(`/api/games/rooms/${roomId.value}/play`, { card_id: selectedCard.value.id })
    gameState.value = data
    selectedCard.value = null
  } catch (e) {
    error.value = e.response?.data?.message || '카드를 낼 수 없습니다.'
    setTimeout(() => error.value = '', 3000)
  }
  playing.value = false
}

async function doGo() {
  try {
    const { data } = await axios.post(`/api/games/rooms/${roomId.value}/go`)
    gameState.value = data
  } catch (e) {
    error.value = e.response?.data?.message || '고 선언 실패'
  }
}

async function doStop() {
  try {
    const { data } = await axios.post(`/api/games/rooms/${roomId.value}/stop`)
    gameState.value = data
  } catch (e) {
    error.value = e.response?.data?.message || '스톱 선언 실패'
  }
}

function playAgain() {
  room.value = null; roomId.value = null; gameState.value = null
  players.value = []; isReady.value = false
}

function subscribeToRoom(id) {
  const win = window
  if (!win.Echo) return
  win.Echo.channel(`game.${id}`)
    .listen('.state.changed', (e) => {
      if (e.event === 'player_joined') {
        loadState()
      } else if (e.event === 'player_ready') {
        loadState()
      } else if (e.event === 'game_started') {
        loadState()
      } else if (e.event === 'card_played') {
        loadState()
      } else if (e.event === 'player_go') {
        loadState()
      } else if (e.event === 'game_over') {
        loadState()
      }
    })
}

async function loadState() {
  if (!roomId.value) return
  try {
    const { data } = await axios.get(`/api/games/rooms/${roomId.value}/state`)
    room.value    = data.room
    players.value = data.players
    if (data.state) gameState.value = data.state
  } catch { }
}

onMounted(async () => {
  if (roomId.value) {
    await loadState()
    subscribeToRoom(roomId.value)
  }
})

onUnmounted(() => {
  if (roomId.value && window.Echo) {
    window.Echo.leave(`game.${roomId.value}`)
  }
})
</script>
