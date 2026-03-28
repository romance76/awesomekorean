<template>
  <div class="min-h-screen bg-gradient-to-br from-indigo-950 via-purple-950 to-blue-950 text-white p-4">
    <div class="max-w-6xl mx-auto">

      <!-- Header -->
      <div class="text-center mb-6">
        <h1 class="text-4xl font-bold tracking-widest text-yellow-300 drop-shadow-lg">🎯 빙고 게임</h1>
        <p class="text-purple-300 mt-1 text-sm">한인 커뮤니티 빙고 · 혼자서 컴퓨터와 대결!</p>
      </div>

      <!-- Status Bar -->
      <div class="flex flex-wrap items-center justify-center gap-4 mb-6">
        <div class="bg-purple-900/60 border border-purple-500 rounded-xl px-5 py-2 text-center">
          <div class="text-xs text-purple-300">호출된 번호</div>
          <div class="text-2xl font-bold text-yellow-300">{{ calledNumbers.length }} / 75</div>
        </div>
        <div class="bg-blue-900/60 border border-blue-500 rounded-xl px-5 py-2 text-center">
          <div class="text-xs text-blue-300">내 빙고</div>
          <div class="text-2xl font-bold text-green-400">{{ playerBingos }}</div>
        </div>
        <div class="bg-red-900/60 border border-red-500 rounded-xl px-5 py-2 text-center">
          <div class="text-xs text-red-300">컴퓨터 빙고</div>
          <div class="text-2xl font-bold text-red-400">{{ computerBingos }}</div>
        </div>
        <div v-if="lastCalledNumber !== null"
          class="bg-yellow-500/20 border border-yellow-400 rounded-xl px-5 py-2 text-center"
          :class="{ 'animate-bounce': justCalled }">
          <div class="text-xs text-yellow-300">최근 호출</div>
          <div class="text-3xl font-bold text-yellow-300">{{ lastCalledNumber }}</div>
        </div>
      </div>

      <!-- Control Buttons -->
      <div class="flex flex-wrap justify-center gap-3 mb-6">
        <button
          @click="callNumber"
          :disabled="gameOver || autoPlay || calledNumbers.length >= 75"
          class="bg-gradient-to-r from-yellow-500 to-orange-500 hover:from-yellow-400 hover:to-orange-400
                 disabled:opacity-40 disabled:cursor-not-allowed text-black font-bold px-8 py-3
                 rounded-xl text-lg shadow-lg transition-all active:scale-95"
        >
          🎱 번호 뽑기
        </button>

        <button
          @click="toggleAutoPlay"
          :disabled="gameOver || calledNumbers.length >= 75"
          :class="autoPlay
            ? 'bg-red-700 hover:bg-red-600 border-red-500 text-white'
            : 'bg-green-700 hover:bg-green-600 border-green-500 text-white'"
          class="border font-bold px-6 py-3 rounded-xl text-lg shadow-lg transition-all
                 active:scale-95 disabled:opacity-40 disabled:cursor-not-allowed"
        >
          {{ autoPlay ? '⏹ AUTO 중지' : '▶ AUTO 시작' }}
        </button>

        <button
          @click="restartGame"
          class="bg-purple-700 hover:bg-purple-600 border border-purple-500 text-white
                 font-bold px-6 py-3 rounded-xl text-lg shadow-lg transition-all active:scale-95"
        >
          🔄 새 게임
        </button>
      </div>

      <!-- Cards Side by Side -->
      <div class="flex flex-col lg:flex-row gap-8 justify-center items-center lg:items-start mb-8">

        <!-- Player Card -->
        <div>
          <div class="text-center mb-3">
            <span class="bg-blue-700 text-white font-bold px-4 py-1 rounded-full text-sm shadow">👤 나의 카드</span>
            <span v-if="playerBingos > 0" class="ml-2 text-green-400 text-sm font-bold">✨ {{ playerBingos }}빙고!</span>
          </div>
          <div class="bg-blue-950/60 border-2 border-blue-600 rounded-2xl p-3 shadow-2xl">
            <!-- Column Headers -->
            <div class="grid grid-cols-5 mb-1">
              <div v-for="col in COLS" :key="col"
                class="w-12 h-8 sm:w-14 flex items-center justify-center font-black text-xl text-yellow-300">
                {{ col }}
              </div>
            </div>
            <!-- Rows -->
            <div v-for="(row, ri) in playerCard" :key="ri" class="grid grid-cols-5 gap-1 mb-1">
              <div
                v-for="(val, ci) in row"
                :key="ci"
                :class="cellClass(val, ri, ci, playerWinningLines)"
              >
                <span v-if="val === 'FREE'" class="text-xs font-black">FREE</span>
                <span v-else>{{ val }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- VS Divider -->
        <div class="flex lg:flex-col items-center justify-center gap-2 text-2xl font-black text-purple-400 lg:mt-20">
          <span>VS</span>
        </div>

        <!-- Computer Card -->
        <div>
          <div class="text-center mb-3">
            <span class="bg-red-700 text-white font-bold px-4 py-1 rounded-full text-sm shadow">🤖 컴퓨터 카드</span>
            <span v-if="computerBingos > 0" class="ml-2 text-red-400 text-sm font-bold">✨ {{ computerBingos }}빙고!</span>
          </div>
          <div class="bg-red-950/60 border-2 border-red-700 rounded-2xl p-3 shadow-2xl">
            <!-- Column Headers -->
            <div class="grid grid-cols-5 mb-1">
              <div v-for="col in COLS" :key="col"
                class="w-12 h-8 sm:w-14 flex items-center justify-center font-black text-xl text-yellow-300">
                {{ col }}
              </div>
            </div>
            <!-- Rows -->
            <div v-for="(row, ri) in computerCard" :key="ri" class="grid grid-cols-5 gap-1 mb-1">
              <div
                v-for="(val, ci) in row"
                :key="ci"
                :class="cellClass(val, ri, ci, computerWinningLines)"
              >
                <span v-if="val === 'FREE'" class="text-xs font-black">FREE</span>
                <span v-else>{{ val }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Called Numbers Grid -->
      <div v-if="calledNumbers.length > 0" class="max-w-3xl mx-auto">
        <h3 class="text-center text-purple-300 text-sm mb-3 font-semibold">
          📋 호출된 번호 목록 ({{ calledNumbers.length }}/75)
        </h3>
        <div class="bg-purple-900/40 border border-purple-700 rounded-xl p-4">
          <div class="grid grid-cols-5 gap-2">
            <div v-for="(colNums, colName) in calledByColumn" :key="colName">
              <div class="text-center font-black text-yellow-300 text-sm mb-1">{{ colName }}</div>
              <div v-if="colNums.length === 0" class="text-center text-purple-700 text-xs">-</div>
              <div v-for="n in colNums" :key="n"
                class="text-center bg-yellow-500/20 border border-yellow-600/40 rounded text-xs
                       font-bold text-yellow-300 py-0.5 mb-0.5">
                {{ n }}
              </div>
            </div>
          </div>
        </div>
      </div>

    </div><!-- end max-w -->

    <!-- Win Modal -->
    <Transition name="modal">
      <div v-if="gameOver" class="fixed inset-0 bg-black/75 flex items-center justify-center z-50 p-4"
        @click.self="() => {}">
        <div class="bg-gradient-to-br from-purple-900 via-indigo-900 to-blue-900
                    border-2 border-yellow-400 rounded-2xl p-8 text-center max-w-sm w-full shadow-2xl">
          <div class="text-7xl mb-4">{{ winner === 'player' ? '🏆' : '😢' }}</div>
          <h2 class="text-3xl font-black mb-3"
            :class="winner === 'player' ? 'text-yellow-300' : 'text-red-400'">
            {{ winner === 'player' ? '빙고! 내가 이겼다!' : '컴퓨터 빙고!' }}
          </h2>
          <p class="text-purple-300 mb-1">
            {{ winner === 'player'
              ? '축하합니다! 먼저 빙고를 달성하셨습니다! 🎉'
              : '컴퓨터가 먼저 빙고를 완성했습니다. 다시 도전해보세요!' }}
          </p>
          <div class="bg-black/30 rounded-lg p-3 mb-6 text-sm text-purple-300">
            <div>호출된 번호: <strong class="text-white">{{ calledNumbers.length }}개</strong></div>
            <div>내 빙고: <strong class="text-green-400">{{ playerBingos }}줄</strong>
              | 컴퓨터: <strong class="text-red-400">{{ computerBingos }}줄</strong></div>
          </div>
          <button
            @click="restartGame"
            class="bg-gradient-to-r from-yellow-500 to-orange-500 hover:from-yellow-400 hover:to-orange-400
                   text-black font-black px-8 py-3 rounded-xl text-xl w-full transition-all active:scale-95"
          >
            🔄 다시 하기
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onUnmounted } from 'vue'

// ────────────────────────────────────────────────────────────────────────────
// Constants
// ────────────────────────────────────────────────────────────────────────────
const COLS = ['B', 'I', 'N', 'G', 'O']

// B=1-15, I=16-30, N=31-45, G=46-60, O=61-75
const RANGES = [
  { min: 1,  max: 15 },
  { min: 16, max: 30 },
  { min: 31, max: 45 },
  { min: 46, max: 60 },
  { min: 61, max: 75 },
]

// All 12 lines: 5 rows + 5 cols + 2 diagonals
const ALL_LINES = [
  [[0,0],[0,1],[0,2],[0,3],[0,4]],
  [[1,0],[1,1],[1,2],[1,3],[1,4]],
  [[2,0],[2,1],[2,2],[2,3],[2,4]],
  [[3,0],[3,1],[3,2],[3,3],[3,4]],
  [[4,0],[4,1],[4,2],[4,3],[4,4]],
  [[0,0],[1,0],[2,0],[3,0],[4,0]],
  [[0,1],[1,1],[2,1],[3,1],[4,1]],
  [[0,2],[1,2],[2,2],[3,2],[4,2]],
  [[0,3],[1,3],[2,3],[3,3],[4,3]],
  [[0,4],[1,4],[2,4],[3,4],[4,4]],
  [[0,0],[1,1],[2,2],[3,3],[4,4]],
  [[0,4],[1,3],[2,2],[3,1],[4,0]],
]

// ────────────────────────────────────────────────────────────────────────────
// Audio (Web Audio API)
// ────────────────────────────────────────────────────────────────────────────
let _audioCtx = null

function audioCtx() {
  if (!_audioCtx) {
    _audioCtx = new (window.AudioContext || window.webkitAudioContext)()
  }
  return _audioCtx
}

function playTone(freq, duration, type = 'sine', gain = 0.25) {
  try {
    const ctx = audioCtx()
    const osc = ctx.createOscillator()
    const gainNode = ctx.createGain()
    osc.connect(gainNode)
    gainNode.connect(ctx.destination)
    osc.type = type
    osc.frequency.setValueAtTime(freq, ctx.currentTime)
    gainNode.gain.setValueAtTime(gain, ctx.currentTime)
    gainNode.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + duration)
    osc.start(ctx.currentTime)
    osc.stop(ctx.currentTime + duration)
  } catch (_) { /* ignore */ }
}

function playCallSound() {
  playTone(660, 0.1)
  setTimeout(() => playTone(880, 0.08), 90)
}

function playWinSound() {
  const melody = [523, 659, 784, 1047, 1319]
  melody.forEach((f, i) => {
    setTimeout(() => playTone(f, 0.3, 'triangle', 0.3), i * 140)
  })
  setTimeout(() => {
    playTone(1568, 0.6, 'sine', 0.2)
  }, melody.length * 140 + 100)
}

// ────────────────────────────────────────────────────────────────────────────
// Card Generation
// ────────────────────────────────────────────────────────────────────────────
function shuffle(arr) {
  for (let i = arr.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [arr[i], arr[j]] = [arr[j], arr[i]]
  }
  return arr
}

function generateCard() {
  // card[row][col]
  const card = Array.from({ length: 5 }, () => new Array(5).fill(null))
  for (let col = 0; col < 5; col++) {
    const { min, max } = RANGES[col]
    const pool = []
    for (let n = min; n <= max; n++) pool.push(n)
    const picked = shuffle(pool).slice(0, 5)
    for (let row = 0; row < 5; row++) {
      card[row][col] = picked[row]
    }
  }
  card[2][2] = 'FREE'
  return card
}

// ────────────────────────────────────────────────────────────────────────────
// Bingo Logic
// ────────────────────────────────────────────────────────────────────────────
function getWinningLineIndices(card, markedSet) {
  return ALL_LINES.reduce((acc, line, idx) => {
    const complete = line.every(([r, c]) => {
      const val = card[r][c]
      return val === 'FREE' || markedSet.has(val)
    })
    if (complete) acc.push(idx)
    return acc
  }, [])
}

function isWinningCell(row, col, winningLineIndices) {
  return winningLineIndices.some(li => {
    const line = ALL_LINES[li]
    return line.some(([r, c]) => r === row && c === col)
  })
}

// ────────────────────────────────────────────────────────────────────────────
// Reactive State
// ────────────────────────────────────────────────────────────────────────────
const playerCard    = ref(generateCard())
const computerCard  = ref(generateCard())
const calledNumbers = ref([])
const lastCalledNumber = ref(null)
const justCalled    = ref(false)
const gameOver      = ref(false)
const winner        = ref(null)
const autoPlay      = ref(false)
let   autoTimer     = null

const playerWinningLines   = ref([])
const computerWinningLines = ref([])

// ────────────────────────────────────────────────────────────────────────────
// Computed
// ────────────────────────────────────────────────────────────────────────────
const calledSet = computed(() => new Set(calledNumbers.value))

const playerBingos   = computed(() => playerWinningLines.value.length)
const computerBingos = computed(() => computerWinningLines.value.length)

const calledByColumn = computed(() => {
  const cols = { B: [], I: [], N: [], G: [], O: [] }
  const colKeys = ['B', 'I', 'N', 'G', 'O']
  calledNumbers.value.forEach(n => {
    const ci = RANGES.findIndex(r => n >= r.min && n <= r.max)
    if (ci !== -1) cols[colKeys[ci]].push(n)
  })
  return cols
})

// ────────────────────────────────────────────────────────────────────────────
// Cell Styling
// ────────────────────────────────────────────────────────────────────────────
function cellClass(val, row, col, winLines) {
  const isMarked = val === 'FREE' || calledSet.value.has(val)
  const isWin    = isWinningCell(row, col, winLines)
  const base = 'w-12 h-12 sm:w-14 sm:h-14 flex items-center justify-center rounded-lg font-bold text-sm transition-all duration-200 select-none '

  if (val === 'FREE') {
    return base + 'bg-yellow-500 text-black border-2 border-yellow-300 text-xs shadow-md'
  }
  if (isWin && isMarked) {
    return base + 'bg-yellow-400 text-black border-2 border-yellow-200 shadow-lg shadow-yellow-400/60 scale-105 z-10'
  }
  if (isMarked) {
    return base + 'bg-yellow-600 text-black border-2 border-yellow-500'
  }
  // Unmarked — player vs computer different tones
  if (winLines === playerWinningLines.value) {
    return base + 'bg-blue-900/80 text-blue-100 border border-blue-700'
  }
  return base + 'bg-red-900/80 text-red-100 border border-red-800'
}

// ────────────────────────────────────────────────────────────────────────────
// Game Actions
// ────────────────────────────────────────────────────────────────────────────
function remainingNumbers() {
  const all = []
  for (let n = 1; n <= 75; n++) {
    if (!calledSet.value.has(n)) all.push(n)
  }
  return all
}

function callNumber() {
  if (gameOver.value) return
  const pool = remainingNumbers()
  if (pool.length === 0) return

  const n = pool[Math.floor(Math.random() * pool.length)]
  calledNumbers.value = [...calledNumbers.value, n]
  lastCalledNumber.value = n

  // Flash animation
  justCalled.value = false
  setTimeout(() => { justCalled.value = true }, 10)
  setTimeout(() => { justCalled.value = false }, 600)

  playCallSound()

  // Recalculate bingos
  const cs = calledSet.value
  playerWinningLines.value   = getWinningLineIndices(playerCard.value, cs)
  computerWinningLines.value = getWinningLineIndices(computerCard.value, cs)

  const pWon = playerWinningLines.value.length > 0
  const cWon = computerWinningLines.value.length > 0

  if (pWon || cWon) {
    stopAutoPlay()
    gameOver.value = true
    if (pWon && cWon) {
      winner.value = playerWinningLines.value.length >= computerWinningLines.value.length ? 'player' : 'computer'
    } else if (pWon) {
      winner.value = 'player'
    } else {
      winner.value = 'computer'
    }
    setTimeout(playWinSound, 200)
  }
}

function toggleAutoPlay() {
  if (autoPlay.value) {
    stopAutoPlay()
  } else {
    startAutoPlay()
  }
}

function startAutoPlay() {
  if (gameOver.value || remainingNumbers().length === 0) return
  autoPlay.value = true
  autoTimer = setInterval(() => {
    if (gameOver.value || remainingNumbers().length === 0) {
      stopAutoPlay()
      return
    }
    callNumber()
  }, 2000)
}

function stopAutoPlay() {
  autoPlay.value = false
  if (autoTimer) {
    clearInterval(autoTimer)
    autoTimer = null
  }
}

function restartGame() {
  stopAutoPlay()
  playerCard.value           = generateCard()
  computerCard.value         = generateCard()
  calledNumbers.value        = []
  lastCalledNumber.value     = null
  gameOver.value             = false
  winner.value               = null
  playerWinningLines.value   = []
  computerWinningLines.value = []
  justCalled.value           = false
}

onUnmounted(() => stopAutoPlay())
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>
