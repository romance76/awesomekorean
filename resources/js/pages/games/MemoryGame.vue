<template>
  <div class="min-h-screen bg-gradient-to-b from-indigo-900 to-purple-900 pb-24 select-none">
    <!-- 헤더 -->
    <div class="bg-black/30 px-4 py-3 flex items-center gap-3">
      <button @click="$router.back()" class="text-white/70 hover:text-white">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
      </button>
      <div>
        <h1 class="text-white font-bold">🧠 기억력 카드 게임</h1>
        <p class="text-white/50 text-xs">치매예방 · 두뇌훈련</p>
      </div>
      <div class="ml-auto flex items-center gap-3">
        <div class="text-center">
          <div class="text-yellow-300 font-bold text-lg">{{ matched }}/{{ totalPairs }}</div>
          <div class="text-white/50 text-xs">맞춘 쌍</div>
        </div>
        <div class="text-center">
          <div class="text-cyan-300 font-bold text-lg">{{ timerDisplay }}</div>
          <div class="text-white/50 text-xs">시간</div>
        </div>
        <div class="text-center">
          <div class="text-pink-300 font-bold text-lg">{{ moves }}</div>
          <div class="text-white/50 text-xs">시도</div>
        </div>
      </div>
    </div>

    <!-- 난이도 선택 -->
    <div v-if="phase === 'select'" class="px-4 py-8 max-w-sm mx-auto">
      <div class="text-center text-white mb-8">
        <div class="text-5xl mb-3">🧠</div>
        <h2 class="text-2xl font-black">기억력 훈련</h2>
        <p class="text-white/60 text-sm mt-2">카드를 뒤집어 같은 그림을 찾으세요</p>
      </div>

      <div class="space-y-3 mb-6">
        <div class="text-white/70 text-sm font-semibold mb-2">테마 선택</div>
        <div class="grid grid-cols-3 gap-2">
          <button v-for="t in themes" :key="t.id"
            @click="selectedTheme = t.id"
            class="py-3 rounded-xl text-center transition"
            :class="selectedTheme === t.id ? 'bg-white text-indigo-700 font-bold' : 'bg-white/10 text-white hover:bg-white/20'">
            <div class="text-2xl">{{ t.icon }}</div>
            <div class="text-xs mt-1">{{ t.name }}</div>
          </button>
        </div>
      </div>

      <div class="space-y-3">
        <div class="text-white/70 text-sm font-semibold">난이도 선택</div>
        <button v-for="d in difficulties" :key="d.level"
          @click="startGame(d)"
          class="w-full bg-white/10 hover:bg-white/20 active:scale-95 border border-white/20 rounded-2xl p-4 flex items-center gap-4 transition text-left">
          <div class="text-4xl">{{ d.emoji }}</div>
          <div>
            <div class="text-white font-bold">{{ d.label }}</div>
            <div class="text-white/60 text-sm">{{ d.grid }} · {{ d.pairs }}쌍</div>
            <div class="text-white/40 text-xs mt-0.5">{{ d.desc }}</div>
          </div>
          <div class="ml-auto">
            <span class="text-xs px-2 py-1 rounded-full" :class="d.color">{{ d.tag }}</span>
          </div>
        </button>
      </div>
    </div>

    <!-- 게임 보드 -->
    <div v-else-if="phase === 'playing' || phase === 'done'" class="px-3 py-4">
      <!-- 콤보 표시 -->
      <div v-if="comboCount >= 2" class="text-center mb-3">
        <span class="bg-yellow-400 text-yellow-900 font-black px-4 py-1 rounded-full text-sm animate-bounce inline-block">
          🔥 {{ comboCount }}연속 콤보!
        </span>
      </div>

      <div class="grid gap-2 mx-auto"
        :style="`grid-template-columns: repeat(${cols}, 1fr); max-width: ${cols * 72}px`">
        <div v-for="(card, i) in cards" :key="card.id"
          @click="flipCard(i)"
          class="aspect-square rounded-xl cursor-pointer transition-all duration-300 flex items-center justify-center text-3xl shadow-lg"
          :class="[
            card.flipped || card.matched
              ? 'bg-white scale-105'
              : 'bg-indigo-700 hover:bg-indigo-600 active:scale-95',
            card.matched ? 'opacity-60 cursor-default ring-2 ring-green-400' : '',
            card.shake ? 'animate-shake' : '',
            card.highlight ? 'ring-4 ring-yellow-400' : '',
          ]">
          <span v-if="card.flipped || card.matched">{{ card.symbol }}</span>
          <span v-else class="text-indigo-400 text-2xl">?</span>
        </div>
      </div>

      <!-- 완료 -->
      <div v-if="phase === 'done'" class="mt-6 mx-auto max-w-sm">
        <div class="bg-white rounded-3xl p-6 text-center shadow-2xl">
          <div class="text-6xl mb-3">🎉</div>
          <h2 class="text-2xl font-black text-gray-800 mb-1">완성!</h2>
          <p class="text-gray-500 text-sm mb-4">모든 카드를 맞추었습니다</p>

          <div class="grid grid-cols-3 gap-3 mb-5">
            <div class="bg-indigo-50 rounded-xl p-3">
              <div class="text-xl font-black text-indigo-600">{{ timerDisplay }}</div>
              <div class="text-xs text-gray-500">기록</div>
            </div>
            <div class="bg-pink-50 rounded-xl p-3">
              <div class="text-xl font-black text-pink-600">{{ moves }}</div>
              <div class="text-xs text-gray-500">시도</div>
            </div>
            <div class="bg-yellow-50 rounded-xl p-3">
              <div class="text-xl font-black text-yellow-600">{{ score }}</div>
              <div class="text-xs text-gray-500">점수</div>
            </div>
          </div>

          <div class="flex gap-2">
            <button @click="phase = 'select'" class="flex-1 bg-gray-100 text-gray-700 font-bold py-3 rounded-xl">
              메뉴
            </button>
            <button @click="startGame(currentDifficulty)" class="flex-1 bg-indigo-600 text-white font-bold py-3 rounded-xl">
              다시하기
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onUnmounted } from 'vue'

// ── 테마 ──────────────────────────────────────────────────────────────────────
const themes = [
  { id: 'animals', name: '동물', icon: '🐾', symbols: ['🐶','🐱','🐭','🐹','🐰','🦊','🐻','🐼','🐨','🐯','🦁','🐮','🐷','🐸','🐵','🦄','🦋','🐢','🦀','🐬'] },
  { id: 'food',    name: '음식', icon: '🍱', symbols: ['🍎','🍊','🍋','🍇','🍓','🍑','🍒','🍍','🥝','🍉','🍕','🍔','🍜','🍣','🍰','🎂','🍩','🍦','🥐','🌮'] },
  { id: 'korean',  name: '한국', icon: '🇰🇷', symbols: ['🥢','🍚','🥘','🫕','🏮','🎋','🎍','🎎','🀄','🎲','🎯','🎪','🌸','⛩️','🎑','🧧','🥁','🪕','🏯','🎠'] },
]

const difficulties = [
  { level: 1, label: '쉬움 (아이들)',    grid: '3×4',  cols: 3, rows: 4, pairs: 6,  emoji: '🌱', tag: '추천', color: 'bg-green-100 text-green-700',  desc: '어린이·첫 시작·기억력 훈련 입문' },
  { level: 2, label: '보통',             grid: '4×4',  cols: 4, rows: 4, pairs: 8,  emoji: '🌿', tag: '기본', color: 'bg-blue-100 text-blue-700',    desc: '일반 성인·치매예방 기본 훈련' },
  { level: 3, label: '어려움',           grid: '4×5',  cols: 4, rows: 5, pairs: 10, emoji: '🌳', tag: '도전', color: 'bg-orange-100 text-orange-700', desc: '집중력 향상·고급 뇌 훈련' },
  { level: 4, label: '전문가',           grid: '5×6',  cols: 5, rows: 6, pairs: 15, emoji: '🏔️', tag: '고급', color: 'bg-red-100 text-red-700',      desc: '전문 두뇌 훈련·최고 난이도' },
]

const selectedTheme  = ref('animals')
const phase          = ref('select')
const cards          = ref([])
const cols           = ref(4)
const matched        = ref(0)
const totalPairs     = ref(0)
const moves          = ref(0)
const comboCount     = ref(0)
const score          = ref(0)
const currentDifficulty = ref(null)

let flipped1 = null
let flipped2 = null
let canFlip  = true

// ── 타이머 ─────────────────────────────────────────────────────────────────
const elapsed = ref(0)
let timerInterval = null
const timerDisplay = computed(() => {
  const m = Math.floor(elapsed.value / 60).toString().padStart(2,'0')
  const s = (elapsed.value % 60).toString().padStart(2,'0')
  return `${m}:${s}`
})

function startTimer() {
  clearInterval(timerInterval)
  elapsed.value = 0
  timerInterval = setInterval(() => elapsed.value++, 1000)
}
function stopTimer() { clearInterval(timerInterval) }
onUnmounted(stopTimer)

// ── 사운드 ────────────────────────────────────────────────────────────────
let _ac = null
function getAc() { if(!_ac) _ac=new(window.AudioContext||window.webkitAudioContext)(); return _ac }
function playFlip() {
  try {
    const ctx = getAc()
    const o = ctx.createOscillator(), g = ctx.createGain()
    o.connect(g); g.connect(ctx.destination)
    o.type = 'triangle'; o.frequency.value = 800
    g.gain.setValueAtTime(0.1, ctx.currentTime)
    g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.1)
    o.start(); o.stop(ctx.currentTime + 0.1)
  } catch(e){}
}
function playMatch() {
  try {
    const ctx = getAc()
    ;[523, 659, 784].forEach((f, i) => {
      const o = ctx.createOscillator(), g = ctx.createGain()
      o.connect(g); g.connect(ctx.destination)
      o.type = 'sine'; o.frequency.value = f
      g.gain.setValueAtTime(0, ctx.currentTime + i*0.1)
      g.gain.linearRampToValueAtTime(0.2, ctx.currentTime + i*0.1 + 0.05)
      g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + i*0.1 + 0.25)
      o.start(ctx.currentTime + i*0.1); o.stop(ctx.currentTime + i*0.1 + 0.25)
    })
  } catch(e){}
}
function playWrong() {
  try {
    const ctx = getAc()
    const o = ctx.createOscillator(), g = ctx.createGain()
    o.connect(g); g.connect(ctx.destination)
    o.type = 'sawtooth'; o.frequency.value = 200
    g.gain.setValueAtTime(0.15, ctx.currentTime)
    g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.2)
    o.start(); o.stop(ctx.currentTime + 0.2)
  } catch(e){}
}
function playWin() {
  try {
    const ctx = getAc()
    ;[523,659,784,1047,1319].forEach((f,i) => {
      const o = ctx.createOscillator(), g = ctx.createGain()
      o.connect(g); g.connect(ctx.destination)
      o.type = 'triangle'; o.frequency.value = f
      g.gain.setValueAtTime(0, ctx.currentTime + i*0.1)
      g.gain.linearRampToValueAtTime(0.25, ctx.currentTime + i*0.1 + 0.05)
      g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + i*0.1 + 0.4)
      o.start(ctx.currentTime + i*0.1); o.stop(ctx.currentTime + i*0.1 + 0.4)
    })
  } catch(e){}
}

// ── 게임 시작 ─────────────────────────────────────────────────────────────
function startGame(difficulty) {
  currentDifficulty.value = difficulty
  const theme = themes.find(t => t.id === selectedTheme.value) || themes[0]
  const symbols = theme.symbols.slice(0, difficulty.pairs)

  // 페어 생성 + 셔플
  const allCards = []
  symbols.forEach((sym, i) => {
    allCards.push({ id: i*2,   pairId: i, symbol: sym, flipped: false, matched: false, shake: false, highlight: false })
    allCards.push({ id: i*2+1, pairId: i, symbol: sym, flipped: false, matched: false, shake: false, highlight: false })
  })
  // Fisher-Yates shuffle
  for (let i = allCards.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [allCards[i], allCards[j]] = [allCards[j], allCards[i]]
  }

  cards.value      = allCards
  cols.value       = difficulty.cols
  matched.value    = 0
  totalPairs.value = difficulty.pairs
  moves.value      = 0
  comboCount.value = 0
  score.value      = 0
  flipped1         = null
  flipped2         = null
  canFlip          = true
  phase.value      = 'playing'
  startTimer()
}

// ── 카드 뒤집기 ───────────────────────────────────────────────────────────
function flipCard(idx) {
  if (!canFlip) return
  const card = cards.value[idx]
  if (card.flipped || card.matched) return

  playFlip()
  card.flipped = true

  if (flipped1 === null) {
    flipped1 = idx
    return
  }

  flipped2 = idx
  canFlip  = false
  moves.value++

  const c1 = cards.value[flipped1]
  const c2 = cards.value[flipped2]

  if (c1.pairId === c2.pairId) {
    // 매칭 성공!
    setTimeout(() => {
      c1.matched = true
      c2.matched = true
      c1.highlight = true
      c2.highlight = true
      playMatch()
      matched.value++
      comboCount.value++

      const timeBonus = Math.max(0, 300 - elapsed.value * 2)
      const comboBonus = comboCount.value >= 3 ? comboCount.value * 50 : 0
      score.value += 100 + timeBonus + comboBonus

      setTimeout(() => {
        c1.highlight = false
        c2.highlight = false
        flipped1 = null
        flipped2 = null
        canFlip  = true

        if (matched.value === totalPairs.value) {
          stopTimer()
          phase.value = 'done'
          playWin()
        }
      }, 400)
    }, 300)
  } else {
    // 매칭 실패
    comboCount.value = 0
    setTimeout(() => {
      c1.shake = true
      c2.shake = true
      playWrong()
      setTimeout(() => {
        c1.flipped = false
        c2.flipped = false
        c1.shake   = false
        c2.shake   = false
        flipped1   = null
        flipped2   = null
        canFlip    = true
      }, 500)
    }, 600)
  }
}
</script>

<style scoped>
@keyframes shake {
  0%,100% { transform: translateX(0); }
  20%      { transform: translateX(-6px); }
  40%      { transform: translateX(6px); }
  60%      { transform: translateX(-4px); }
  80%      { transform: translateX(4px); }
}
.animate-shake { animation: shake 0.5s ease; }
</style>
