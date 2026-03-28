<template>
  <div class="min-h-screen bg-emerald-900 text-white pb-20">
    <!-- 헤더 -->
    <div class="bg-emerald-800 px-4 py-3 flex items-center justify-between">
      <router-link to="/games" class="text-emerald-300">← 로비</router-link>
      <h1 class="font-bold">🃏 텍사스 홀덤</h1>
      <span class="text-emerald-300 text-sm font-mono">{{ room?.code || '' }}</span>
    </div>

    <!-- 방 없음: 생성/입장 -->
    <div v-if="!roomId && !room" class="max-w-md mx-auto px-4 py-8 space-y-4">
      <div class="bg-emerald-800 rounded-2xl p-6 space-y-4">
        <h2 class="text-lg font-bold text-center">방 만들기</h2>
        <div>
          <label class="text-emerald-300 text-sm">최대 인원 (2~6명)</label>
          <select v-model="newRoom.max_players" class="w-full mt-1 bg-emerald-700 rounded-lg px-3 py-2">
            <option :value="2">2인</option>
            <option :value="3">3인</option>
            <option :value="4">4인</option>
            <option :value="6">6인</option>
          </select>
        </div>
        <div>
          <label class="text-emerald-300 text-sm">바이인 (Buy-in)</label>
          <select v-model="newRoom.buy_in" class="w-full mt-1 bg-emerald-700 rounded-lg px-3 py-2">
            <option :value="100">100P (연습)</option>
            <option :value="500">500P</option>
            <option :value="1000">1,000P</option>
            <option :value="5000">5,000P</option>
          </select>
        </div>
        <button @click="createRoom" :disabled="creating"
          class="w-full bg-green-600 hover:bg-green-700 rounded-xl py-3 font-bold disabled:opacity-50">
          {{ creating ? '만드는 중...' : '방 만들기' }}
        </button>
      </div>
      <div class="bg-emerald-800 rounded-2xl p-6 space-y-3">
        <h2 class="text-lg font-bold text-center">코드로 입장</h2>
        <input v-model="joinCode" placeholder="방 코드 6자리" maxlength="6"
          class="w-full bg-emerald-700 rounded-lg px-3 py-2 text-center font-mono text-lg uppercase placeholder-emerald-400" />
        <button @click="joinByCode" class="w-full bg-blue-600 hover:bg-blue-700 rounded-xl py-3 font-bold">입장</button>
      </div>
    </div>

    <!-- 대기실 -->
    <div v-else-if="room && room.status === 'waiting'" class="max-w-md mx-auto px-4 py-8">
      <div class="bg-emerald-800 rounded-2xl p-6 text-center space-y-4">
        <div class="text-4xl">🃏</div>
        <h2 class="text-xl font-bold">방 코드: <span class="text-yellow-300 font-mono">{{ room.code }}</span></h2>
        <p class="text-emerald-300 text-sm">바이인: {{ room.bet_points.toLocaleString() }}P</p>
        <div class="space-y-2">
          <div v-for="p in players" :key="p.id"
            class="flex items-center justify-between bg-emerald-700 rounded-lg px-4 py-2">
            <span>{{ p.user?.name || p.user?.username }}</span>
            <span :class="p.is_ready ? 'text-green-400' : 'text-gray-400'">
              {{ p.is_ready ? '✅ 준비' : '⏳ 대기' }}
            </span>
          </div>
          <div v-for="i in (room.max_players - players.length)" :key="'e'+i"
            class="text-emerald-600 text-center py-2 bg-emerald-700/40 rounded-lg">빈 자리</div>
        </div>
        <button @click="readyUp" :disabled="isReady"
          class="w-full bg-yellow-500 hover:bg-yellow-600 disabled:bg-gray-600 rounded-xl py-3 font-bold">
          {{ isReady ? '✅ 준비완료' : '준비하기' }}
        </button>
      </div>
    </div>

    <!-- 게임 화면 -->
    <div v-else-if="gameState" class="px-3 py-3 space-y-3">

      <!-- 팟 & 페이즈 -->
      <div class="flex items-center justify-between bg-emerald-800 rounded-xl px-4 py-2">
        <span class="text-yellow-300 font-bold text-lg">팟: {{ gameState.pot?.toLocaleString() }}P</span>
        <span class="text-emerald-300 text-sm uppercase bg-emerald-700 px-2 py-0.5 rounded">{{ phaseLabel }}</span>
        <span class="text-xs text-emerald-400">현재 베팅: {{ gameState.current_bet }}</span>
      </div>

      <!-- 상대방들 -->
      <div class="grid grid-cols-2 gap-2">
        <div v-for="(hand, uid) in opponentHands" :key="uid"
          class="bg-emerald-800 rounded-xl p-3 text-center"
          :class="parseInt(uid) === parseInt(gameState.current_player) ? 'ring-2 ring-yellow-400' : ''">
          <div class="text-xs text-emerald-400 mb-1">{{ getPlayerName(uid) }}</div>
          <div class="flex gap-1 justify-center">
            <div v-for="i in 2" :key="i"
              class="w-8 h-11 bg-blue-800 border border-blue-600 rounded text-lg flex items-center justify-center">🂠</div>
          </div>
          <div class="text-xs mt-1">칩: {{ gameState.chips?.[uid]?.toLocaleString() }}</div>
          <div v-if="gameState.bets?.[uid]" class="text-xs text-yellow-300">베팅: {{ gameState.bets[uid] }}</div>
          <div v-if="gameState.folded?.includes(parseInt(uid))" class="text-xs text-red-400 mt-0.5">폴드</div>
        </div>
      </div>

      <!-- 커뮤니티 카드 -->
      <div class="bg-emerald-700 rounded-2xl p-3">
        <div class="text-xs text-emerald-300 mb-2">커뮤니티 카드</div>
        <div class="flex gap-2 justify-center min-h-[72px]">
          <div v-for="card in gameState.community" :key="card.id"
            class="w-12 h-16 bg-white rounded-lg flex flex-col items-center justify-center shadow">
            <span :style="{color: card.color}" class="text-xl font-bold leading-none">{{ card.rank }}</span>
            <span :style="{color: card.color}" class="text-sm">{{ card.suit }}</span>
          </div>
          <div v-for="i in (5 - (gameState.community?.length || 0))" :key="'ph'+i"
            class="w-12 h-16 border-2 border-dashed border-emerald-500 rounded-lg opacity-40"></div>
        </div>
      </div>

      <!-- 내 카드 & 상태 -->
      <div class="bg-emerald-800 rounded-2xl p-3">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs text-emerald-300">내 패</span>
          <span class="text-xs">칩: <span class="text-yellow-300 font-bold">{{ myChips?.toLocaleString() }}</span>P</span>
        </div>
        <div class="flex gap-3 justify-center">
          <div v-for="card in myHand" :key="card.id"
            class="w-16 h-22 bg-white rounded-xl shadow-lg flex flex-col items-center justify-center p-1">
            <span :style="{color: card.color}" class="text-2xl font-black">{{ card.rank }}</span>
            <span :style="{color: card.color}" class="text-lg">{{ card.suit }}</span>
          </div>
        </div>
        <div v-if="myBet" class="text-center text-xs text-yellow-300 mt-1">베팅 중: {{ myBet }}P</div>
      </div>

      <!-- 내 차례 액션 -->
      <div v-if="isMyTurn && !isFolded" class="space-y-2">
        <div class="flex gap-2">
          <button @click="doAction('fold')"
            class="flex-1 bg-red-700 hover:bg-red-800 rounded-xl py-3 font-bold text-sm">
            폴드
          </button>
          <button v-if="canCheck" @click="doAction('check')"
            class="flex-1 bg-gray-600 hover:bg-gray-700 rounded-xl py-3 font-bold text-sm">
            체크
          </button>
          <button v-else @click="doAction('call')"
            :disabled="myChips <= 0"
            class="flex-1 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 rounded-xl py-3 font-bold text-sm">
            콜 {{ callAmount > 0 ? `(${callAmount})` : '' }}
          </button>
          <button @click="doAction('allin')"
            :disabled="myChips <= 0"
            class="flex-1 bg-purple-600 hover:bg-purple-700 disabled:opacity-50 rounded-xl py-3 font-bold text-sm">
            올인
          </button>
        </div>
        <div class="flex gap-2 items-center">
          <input v-model.number="raiseAmount" type="number"
            :min="gameState.current_bet * 2" :max="myChips + myBet"
            class="flex-1 bg-emerald-700 rounded-lg px-3 py-2 text-sm"
            placeholder="레이즈 금액" />
          <button @click="doAction('raise')"
            :disabled="raiseAmount < gameState.current_bet + (gameState.big_blind || 1)"
            class="flex-shrink-0 bg-orange-500 hover:bg-orange-600 disabled:opacity-50 rounded-xl px-4 py-2 font-bold text-sm">
            레이즈
          </button>
        </div>
      </div>
      <div v-else-if="!isFolded" class="text-center text-emerald-300 py-2 text-sm">
        {{ getCurrentPlayerName() }}님의 차례...
      </div>
      <div v-else class="text-center text-red-400 py-2 text-sm">폴드했습니다</div>

      <!-- 쇼다운 / 결과 -->
      <div v-if="gameState.phase === 'finished'"
        class="fixed inset-0 bg-black/80 flex items-center justify-center z-50">
        <div class="bg-emerald-800 rounded-2xl p-8 text-center max-w-sm w-full mx-4">
          <div class="text-5xl mb-3">{{ gameState.winner == myId ? '🎉' : '😔' }}</div>
          <h2 class="text-2xl font-bold">{{ gameState.winner == myId ? '승리!' : '패배' }}</h2>
          <div v-if="gameState.winner_hand" class="text-yellow-300 mt-1">{{ gameState.winner_hand }}</div>
          <div class="mt-3 text-emerald-300 text-sm">승자: {{ getPlayerName(gameState.winner) }}</div>
          <div class="mt-1 text-yellow-300 font-bold text-lg">
            팟: {{ gameState.pot?.toLocaleString() }}P
          </div>
          <!-- 쇼다운 - 모든 패 공개 -->
          <div v-if="gameState.phase === 'finished'" class="mt-4 space-y-2">
            <div v-for="(hand, uid) in gameState.hands" :key="uid">
              <div v-if="!hand.hidden" class="flex items-center gap-2 justify-center">
                <span class="text-xs text-emerald-400">{{ getPlayerName(uid) }}:</span>
                <div v-for="c in hand" :key="c.id" class="w-8 h-11 bg-white rounded flex flex-col items-center justify-center">
                  <span :style="{color: c.color}" class="font-bold text-xs">{{ c.rank }}{{ c.suit }}</span>
                </div>
              </div>
            </div>
          </div>
          <div class="mt-6 flex gap-2">
            <button @click="playAgain" class="flex-1 bg-green-600 rounded-xl py-3 font-bold">다시</button>
            <router-link to="/games" class="flex-1 bg-gray-600 rounded-xl py-3 font-bold text-center">로비</router-link>
          </div>
        </div>
      </div>
    </div>

    <!-- 에러 토스트 -->
    <div v-if="error" class="fixed bottom-20 left-4 right-4 bg-red-600 rounded-xl px-4 py-3 text-sm text-center">
      {{ error }}
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'

const route = useRoute()
const auth  = useAuthStore()
const myId  = auth.user?.id

const room      = ref(null)
const roomId    = ref(route.query.room ? parseInt(route.query.room) : null)
const players   = ref([])
const gameState = ref(null)
const isReady   = ref(false)
const creating  = ref(false)
const error     = ref('')
const joinCode  = ref('')
const raiseAmount = ref(0)
const newRoom   = ref({ max_players: 4, buy_in: 500 })

const phaseLabel = computed(() => ({
  preflop: '프리플랍', flop: '플랍', turn: '턴', river: '리버',
  showdown: '쇼다운', finished: '게임 종료'
}[gameState.value?.phase] ?? ''))

const myHand = computed(() => {
  const h = gameState.value?.hands?.[myId]
  return h?.hidden ? [] : (h ?? [])
})
const myChips     = computed(() => gameState.value?.chips?.[myId] ?? 0)
const myBet       = computed(() => gameState.value?.bets?.[myId] ?? 0)
const isMyTurn    = computed(() => parseInt(gameState.value?.current_player) === myId)
const isFolded    = computed(() => gameState.value?.folded?.includes(myId))
const callAmount  = computed(() => Math.min((gameState.value?.current_bet ?? 0) - myBet.value, myChips.value))
const canCheck    = computed(() => myBet.value >= (gameState.value?.current_bet ?? 0))

const opponentHands = computed(() => {
  if (!gameState.value?.hands) return {}
  const h = {}
  for (const uid of Object.keys(gameState.value.hands)) {
    if (parseInt(uid) !== myId) h[uid] = gameState.value.hands[uid]
  }
  return h
})

function getPlayerName(uid) {
  const p = players.value.find(p => parseInt(p.user_id ?? p.user?.id) === parseInt(uid))
  return p?.user?.name || p?.user?.username || `P${uid}`
}
function getCurrentPlayerName() { return getPlayerName(gameState.value?.current_player) }

async function createRoom() {
  creating.value = true
  try {
    const { data } = await axios.post('/api/poker/rooms', newRoom.value)
    room.value = data; roomId.value = data.id; players.value = data.players || []
    subscribeRoom(data.id)
  } catch (e) { showError(e.response?.data?.message || '방 생성 실패') }
  creating.value = false
}

async function joinByCode() {
  try {
    const { data: list } = await axios.get('/api/poker/rooms')
    const found = list.find(r => r.code === joinCode.value.toUpperCase())
    if (!found) { showError('방을 찾을 수 없습니다.'); return }
    const { data } = await axios.post(`/api/poker/rooms/${found.id}/join`)
    room.value = data; roomId.value = data.id; players.value = data.players || []
    subscribeRoom(data.id)
  } catch (e) { showError(e.response?.data?.message || '입장 실패') }
}

async function readyUp() {
  try {
    await axios.post(`/api/poker/rooms/${roomId.value}/ready`)
    isReady.value = true
  } catch (e) { showError(e.response?.data?.message) }
}

async function doAction(act) {
  try {
    const payload = { action: act }
    if (act === 'raise') payload.amount = raiseAmount.value
    const { data } = await axios.post(`/api/poker/rooms/${roomId.value}/action`, payload)
    gameState.value = data
  } catch (e) { showError(e.response?.data?.message || '액션 실패') }
}

function playAgain() { room.value = null; roomId.value = null; gameState.value = null; players.value = [] }

function showError(msg) { error.value = msg || '오류'; setTimeout(() => error.value = '', 3000) }

function subscribeRoom(id) {
  if (!window.Echo) return
  window.Echo.channel(`game.${id}`).listen('.state.changed', () => loadState())
}

async function loadState() {
  if (!roomId.value) return
  try {
    const { data } = await axios.get(`/api/poker/rooms/${roomId.value}/state`)
    room.value = data.room; players.value = data.players
    if (data.state) gameState.value = data.state
    if (data.state?.current_bet && !raiseAmount.value) {
      raiseAmount.value = (data.state.current_bet || 0) * 2
    }
  } catch { }
}

onMounted(() => { if (roomId.value) { loadState(); subscribeRoom(roomId.value) } })
onUnmounted(() => { if (roomId.value && window.Echo) window.Echo.leave(`game.${roomId.value}`) })
</script>
