<template>
  <div class="min-h-screen bg-[#faf8ef] select-none pb-20">
    <!-- 헤더 -->
    <div class="bg-[#bbada0] px-4 py-3 flex items-center gap-3">
      <button @click="$router.back()" class="text-white/70 hover:text-white">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
      </button>
      <h1 class="text-white font-black text-xl">2048</h1>
      <div class="ml-auto flex items-center gap-2">
        <div class="bg-[#eee4da] rounded-lg px-3 py-1 text-center">
          <div class="text-[#776e65] text-xs">점수</div>
          <div class="text-[#776e65] font-black text-lg">{{ score }}</div>
        </div>
        <div class="bg-[#eee4da] rounded-lg px-3 py-1 text-center">
          <div class="text-[#776e65] text-xs">최고</div>
          <div class="text-[#776e65] font-black text-lg">{{ bestScore }}</div>
        </div>
        <button @click="newGame" class="bg-[#8f7a66] text-white font-bold px-3 py-2 rounded-lg text-sm hover:bg-[#7a6959]">
          새게임
        </button>
      </div>
    </div>

    <div class="max-w-sm mx-auto px-4 pt-4">
      <p class="text-[#776e65] text-sm text-center mb-3">
        같은 숫자 타일을 합쳐 <strong>2048</strong>을 만드세요!
      </p>

      <!-- 게임 오버 / 승리 오버레이 -->
      <div v-if="gameOver || won"
        class="relative rounded-2xl overflow-hidden"
        @touchstart.stop @touchend.stop>
        <div class="absolute inset-0 flex items-center justify-center z-10 rounded-2xl"
          :class="won ? 'bg-yellow-400/80' : 'bg-gray-600/70'">
          <div class="text-center">
            <div class="text-5xl mb-2">{{ won ? '🏆' : '😢' }}</div>
            <div class="text-white font-black text-3xl">{{ won ? '성공!' : '게임 오버' }}</div>
            <div class="text-white/80 text-sm mt-1 mb-4">점수: {{ score }}</div>
            <div class="flex gap-2 justify-center">
              <button v-if="won" @click="won = false"
                class="bg-white text-yellow-800 font-bold px-5 py-2 rounded-xl">계속하기</button>
              <button @click="newGame"
                class="bg-white font-bold px-5 py-2 rounded-xl"
                :class="won ? 'text-yellow-800' : 'text-gray-800'">새 게임</button>
            </div>
          </div>
        </div>
        <GameBoard :cells="cells" />
      </div>

      <!-- 일반 게임 보드 -->
      <div v-else
        ref="boardRef"
        @touchstart="onTouchStart"
        @touchend="onTouchEnd"
        @keydown.prevent>
        <GameBoard :cells="cells" />
      </div>

      <!-- 조작 안내 -->
      <div class="mt-4 text-center text-[#776e65] text-xs">
        <p>🖥️ 화살표 키 또는 WASD | 📱 스와이프</p>
      </div>

      <!-- 방향 버튼 (모바일 편의) -->
      <div class="mt-4 grid grid-cols-3 gap-2 max-w-[160px] mx-auto">
        <div></div>
        <button @click="move('up')" class="bg-[#bbada0] text-white font-black py-3 rounded-xl text-xl hover:bg-[#a09186] active:scale-95">↑</button>
        <div></div>
        <button @click="move('left')" class="bg-[#bbada0] text-white font-black py-3 rounded-xl text-xl hover:bg-[#a09186] active:scale-95">←</button>
        <button @click="move('down')" class="bg-[#bbada0] text-white font-black py-3 rounded-xl text-xl hover:bg-[#a09186] active:scale-95">↓</button>
        <button @click="move('right')" class="bg-[#bbada0] text-white font-black py-3 rounded-xl text-xl hover:bg-[#a09186] active:scale-95">→</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, defineComponent, h } from 'vue'

// ── 타일 색상 ──────────────────────────────────────────────────────────────
const TILE_COLORS = {
  0:    { bg: '#cdc1b4', text: '#776e65' },
  2:    { bg: '#eee4da', text: '#776e65' },
  4:    { bg: '#ede0c8', text: '#776e65' },
  8:    { bg: '#f2b179', text: '#f9f6f2' },
  16:   { bg: '#f59563', text: '#f9f6f2' },
  32:   { bg: '#f67c5f', text: '#f9f6f2' },
  64:   { bg: '#f65e3b', text: '#f9f6f2' },
  128:  { bg: '#edcf72', text: '#f9f6f2' },
  256:  { bg: '#edcc61', text: '#f9f6f2' },
  512:  { bg: '#edc850', text: '#f9f6f2' },
  1024: { bg: '#edc53f', text: '#f9f6f2' },
  2048: { bg: '#edc22e', text: '#f9f6f2' },
}

function tileColor(v) {
  return TILE_COLORS[v] || { bg: '#3c3a32', text: '#f9f6f2' }
}

function fontSize(v) {
  if (v >= 1024) return '1.1rem'
  if (v >= 128) return '1.4rem'
  return '1.8rem'
}

// ── GameBoard 컴포넌트 ────────────────────────────────────────────────────
const GameBoard = defineComponent({
  props: ['cells'],
  setup(props) {
    return () => h('div', {
      style: 'background:#bbada0;border-radius:12px;padding:8px;'
    }, [
      h('div', {
        style: 'display:grid;grid-template-columns:repeat(4,1fr);gap:8px;'
      }, props.cells.map((v, i) => {
        const c = tileColor(v)
        return h('div', {
          key: i,
          style: `
            background:${c.bg};
            color:${c.text};
            border-radius:6px;
            aspect-ratio:1;
            display:flex;
            align-items:center;
            justify-content:center;
            font-weight:900;
            font-size:${fontSize(v)};
            transition: background 0.1s;
          `
        }, v > 0 ? String(v) : '')
      }))
    ])
  }
})

// ── 게임 상태 ────────────────────────────────────────────────────────────
const cells     = ref(Array(16).fill(0))
const score     = ref(0)
const bestScore = ref(parseInt(localStorage.getItem('2048best') || '0'))
const gameOver  = ref(false)
const won       = ref(false)
const boardRef  = ref(null)

function newGame() {
  cells.value = Array(16).fill(0)
  score.value = 0
  gameOver.value = false
  won.value = false
  addTile()
  addTile()
}

function addTile() {
  const empty = []
  cells.value.forEach((v, i) => { if (v === 0) empty.push(i) })
  if (!empty.length) return
  const idx = empty[Math.floor(Math.random() * empty.length)]
  cells.value[idx] = Math.random() < 0.9 ? 2 : 4
}

function getGrid() {
  const g = []
  for (let r = 0; r < 4; r++) g.push(cells.value.slice(r * 4, r * 4 + 4))
  return g
}

function setGrid(g) {
  cells.value = g.flat()
}

function slideRow(row) {
  // Filter zeros
  let r = row.filter(v => v !== 0)
  let gained = 0
  for (let i = 0; i < r.length - 1; i++) {
    if (r[i] === r[i + 1]) {
      r[i] *= 2
      gained += r[i]
      r.splice(i + 1, 1)
      i++
    }
  }
  while (r.length < 4) r.push(0)
  return { row: r, gained }
}

function move(dir) {
  if (gameOver.value) return
  let grid = getGrid()
  let totalGained = 0
  let changed = false
  const oldCells = [...cells.value]

  if (dir === 'left') {
    grid = grid.map(row => {
      const { row: nr, gained } = slideRow(row)
      totalGained += gained
      return nr
    })
  } else if (dir === 'right') {
    grid = grid.map(row => {
      const { row: nr, gained } = slideRow([...row].reverse())
      totalGained += gained
      return nr.reverse()
    })
  } else if (dir === 'up') {
    for (let c = 0; c < 4; c++) {
      const col = [grid[0][c], grid[1][c], grid[2][c], grid[3][c]]
      const { row: nr, gained } = slideRow(col)
      totalGained += gained
      for (let r = 0; r < 4; r++) grid[r][c] = nr[r]
    }
  } else if (dir === 'down') {
    for (let c = 0; c < 4; c++) {
      const col = [grid[3][c], grid[2][c], grid[1][c], grid[0][c]]
      const { row: nr, gained } = slideRow(col)
      totalGained += gained
      for (let r = 0; r < 4; r++) grid[3 - r][c] = nr[r]
    }
  }

  setGrid(grid)
  if (cells.value.join(',') !== oldCells.join(',')) {
    score.value += totalGained
    if (score.value > bestScore.value) {
      bestScore.value = score.value
      localStorage.setItem('2048best', bestScore.value)
    }
    playMove(totalGained > 0)
    addTile()
    checkState()
    changed = true
  }
  return changed
}

function checkState() {
  if (cells.value.includes(2048) && !won.value) {
    won.value = true
    playWin()
    return
  }
  // Check if any moves remain
  if (!cells.value.includes(0)) {
    for (let r = 0; r < 4; r++) {
      for (let c = 0; c < 4; c++) {
        const v = cells.value[r * 4 + c]
        if (c < 3 && cells.value[r * 4 + c + 1] === v) return
        if (r < 3 && cells.value[(r + 1) * 4 + c] === v) return
      }
    }
    gameOver.value = true
  }
}

// ── 키보드 ────────────────────────────────────────────────────────────────
function onKeyDown(e) {
  const map = { ArrowUp:'up', ArrowDown:'down', ArrowLeft:'left', ArrowRight:'right',
                KeyW:'up', KeyS:'down', KeyA:'left', KeyD:'right' }
  if (map[e.code]) { e.preventDefault(); move(map[e.code]) }
}

onMounted(() => {
  window.addEventListener('keydown', onKeyDown)
  newGame()
})
onUnmounted(() => window.removeEventListener('keydown', onKeyDown))

// ── 터치 ──────────────────────────────────────────────────────────────────
let touchStart = null
function onTouchStart(e) {
  touchStart = { x: e.touches[0].clientX, y: e.touches[0].clientY }
}
function onTouchEnd(e) {
  if (!touchStart) return
  const dx = e.changedTouches[0].clientX - touchStart.x
  const dy = e.changedTouches[0].clientY - touchStart.y
  touchStart = null
  if (Math.abs(dx) < 20 && Math.abs(dy) < 20) return
  if (Math.abs(dx) > Math.abs(dy)) move(dx > 0 ? 'right' : 'left')
  else move(dy > 0 ? 'down' : 'up')
}

// ── 사운드 ────────────────────────────────────────────────────────────────
let _ac = null
function getAc() { if(!_ac) _ac=new(window.AudioContext||window.webkitAudioContext)(); return _ac }
function playMove(merged) {
  try {
    const ctx = getAc()
    const o = ctx.createOscillator(), g = ctx.createGain()
    o.connect(g); g.connect(ctx.destination)
    o.type = 'sine'
    o.frequency.value = merged ? 440 : 300
    g.gain.setValueAtTime(0.08, ctx.currentTime)
    g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.12)
    o.start(); o.stop(ctx.currentTime + 0.12)
  } catch(e){}
}
function playWin() {
  try {
    const ctx = getAc()
    ;[523,659,784,1047].forEach((f,i) => {
      const o = ctx.createOscillator(), g = ctx.createGain()
      o.connect(g); g.connect(ctx.destination)
      o.type = 'triangle'; o.frequency.value = f
      g.gain.setValueAtTime(0, ctx.currentTime + i*0.12)
      g.gain.linearRampToValueAtTime(0.2, ctx.currentTime + i*0.12 + 0.05)
      g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + i*0.12 + 0.3)
      o.start(ctx.currentTime + i*0.12); o.stop(ctx.currentTime + i*0.12 + 0.3)
    })
  } catch(e){}
}
</script>
