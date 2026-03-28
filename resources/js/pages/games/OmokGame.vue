<template>
  <div class="min-h-screen bg-gradient-to-br from-amber-950 via-stone-900 to-amber-950 text-white p-4 flex flex-col items-center">

    <!-- Header -->
    <div class="text-center mb-3">
      <h1 class="text-3xl font-black text-amber-300 drop-shadow-lg">⚫ 오목 ⚪</h1>
      <p class="text-amber-400/70 text-xs mt-1">5개를 먼저 이으면 승리!</p>
    </div>

    <!-- Info Bar -->
    <div class="flex gap-3 mb-3">
      <div class="bg-black/40 border border-amber-700/50 rounded-xl px-4 py-2 text-center">
        <div class="text-xs text-amber-400">수</div>
        <div class="text-xl font-bold text-amber-300">{{ moveCount }}</div>
      </div>
      <div class="bg-black/40 border rounded-xl px-4 py-2 text-center"
        :class="currentTurn === 'black' ? 'border-gray-400' : 'border-amber-500'">
        <div class="text-xs" :class="currentTurn === 'black' ? 'text-gray-300' : 'text-amber-300'">
          {{ currentTurn === 'black' ? '● 흑돌 (나)' : '○ 백돌 (AI)' }}
        </div>
        <div class="text-sm font-bold">
          {{ gameOver ? '게임 종료' : currentTurn === 'black' ? '당신의 차례' : 'AI 생각 중...' }}
        </div>
      </div>
    </div>

    <!-- Buttons -->
    <div class="flex gap-3 mb-3">
      <button @click="undoMove"
        :disabled="gameOver || moveHistory.length < 2 || currentTurn !== 'black'"
        class="bg-blue-800 hover:bg-blue-700 disabled:opacity-40 text-white font-bold px-4 py-2 rounded-xl text-sm">
        ↩ 무르기
      </button>
      <button @click="restartGame"
        class="bg-amber-700 hover:bg-amber-600 text-white font-bold px-4 py-2 rounded-xl text-sm">
        🔄 새 게임
      </button>
      <button @click="$router.back()" class="bg-gray-700 hover:bg-gray-600 text-white font-bold px-4 py-2 rounded-xl text-sm">
        ← 나가기
      </button>
    </div>

    <!-- Board -->
    <div class="relative select-none rounded-xl shadow-2xl overflow-hidden flex-shrink-0"
      :style="{ width: boardPx + 'px', height: boardPx + 'px', background: '#DEB887' }"
      @click="boardClick"
      @mousemove="boardHover"
      @mouseleave="hoverR = -1">

      <!-- Grid lines via SVG -->
      <svg class="absolute inset-0 w-full h-full pointer-events-none"
        :viewBox="`0 0 ${boardPx} ${boardPx}`">
        <!-- Lines -->
        <line v-for="i in 15" :key="'v'+i"
          :x1="linePos(i-1)" :y1="linePos(0)" :x2="linePos(i-1)" :y2="linePos(14)"
          stroke="#8B6914" stroke-width="1" opacity="0.7"/>
        <line v-for="i in 15" :key="'h'+i"
          :x1="linePos(0)" :y1="linePos(i-1)" :x2="linePos(14)" :y2="linePos(i-1)"
          stroke="#8B6914" stroke-width="1" opacity="0.7"/>
        <!-- Star points -->
        <circle v-for="sp in starPoints" :key="sp.r+'-'+sp.c"
          :cx="linePos(sp.c)" :cy="linePos(sp.r)" r="3" fill="#8B6914" opacity="0.8"/>
        <!-- Outer border -->
        <rect x="0" y="0" :width="boardPx" :height="boardPx" fill="none" stroke="#8B6914" stroke-width="3"/>
      </svg>

      <!-- Hover indicator -->
      <div v-if="hoverR >= 0 && hoverR < 15 && hoverC >= 0 && hoverC < 15 && !gameOver && currentTurn === 'black' && board[hoverR][hoverC] === null"
        class="absolute rounded-full bg-black/25 pointer-events-none transition-all duration-75"
        :style="stonePosStyle(hoverR, hoverC, 0.6)"/>

      <!-- Stones -->
      <template v-for="r in 15" :key="'sr'+r">
        <div v-for="c in 15" :key="'sc'+c"
          v-if="board[r-1][c-1] !== null"
          class="absolute rounded-full"
          :class="isWinningCell(r-1,c-1) ? 'ring-3 ring-yellow-400 z-20 scale-110' : 'z-10'"
          :style="stonePosStyle(r-1, c-1, 0.82, board[r-1][c-1])"/>
      </template>

      <!-- Last move dot -->
      <div v-if="lastMove" class="absolute rounded-full pointer-events-none z-30"
        :style="{
          left: (linePos(lastMove.c) - 3) + 'px',
          top: (linePos(lastMove.r) - 3) + 'px',
          width: '6px', height: '6px',
          background: lastMove.player === 'black' ? '#ffd700' : '#333',
        }"/>
    </div>

    <!-- Move History -->
    <div v-if="moveHistory.length > 0" class="mt-3 max-w-sm w-full">
      <div class="bg-black/30 border border-amber-800/50 rounded-xl p-3">
        <div class="text-xs text-amber-400 mb-2 font-semibold">📜 기보 (최근 {{ Math.min(moveHistory.length, 12) }}수)</div>
        <div class="flex flex-wrap gap-1">
          <span v-for="(mv, idx) in moveHistory.slice(-12)" :key="idx"
            class="text-xs px-1.5 py-0.5 rounded font-mono"
            :class="mv.player === 'black' ? 'bg-gray-800 text-gray-300' : 'bg-amber-100/20 text-amber-200'">
            {{ mv.player === 'black' ? '●' : '○' }}{{ colLabel(mv.c) }}{{ mv.r + 1 }}
          </span>
        </div>
      </div>
    </div>

    <!-- Win Modal -->
    <Transition name="modal">
      <div v-if="gameOver" class="fixed inset-0 bg-black/75 flex items-center justify-center z-50 p-4">
        <div class="rounded-2xl p-8 text-center max-w-xs w-full shadow-2xl"
          style="background: linear-gradient(135deg, #3d1a00, #5a2d00); border: 2px solid #DEB887;">
          <div class="text-6xl mb-4">{{ winnerPlayer === 'black' ? '🏆' : '🤖' }}</div>
          <h2 class="text-2xl font-black mb-2"
            :class="winnerPlayer === 'black' ? 'text-yellow-300' : 'text-amber-300'">
            {{ winnerPlayer === 'black' ? '승리! 오목!' : 'AI 승리!' }}
          </h2>
          <p class="text-amber-300/80 mb-4 text-sm">
            {{ winnerPlayer === 'black' ? '축하합니다! 🎉' : '다시 도전해보세요!' }}
          </p>
          <button @click="restartGame"
            class="font-black px-8 py-3 rounded-xl w-full"
            style="background: linear-gradient(135deg, #DEB887, #b8860b); color: #1a0a00;">
            🔄 다시 두기
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

const BOARD_SIZE = 15

// Responsive board sizing
const boardPx = ref(400)
const pad = ref(22) // padding from board edge to first/last line

function calcSizes() {
  // Use document.documentElement.clientWidth to exclude scrollbar width on desktop
  const clientW = document.documentElement.clientWidth || window.innerWidth
  const vw = Math.min(clientW - 32, 520) // max 520px, with 16px margins
  boardPx.value = Math.max(280, Math.min(vw, 520))
  pad.value = Math.round(boardPx.value * 0.05) + 8
}

// Grid gap between lines
const gap = computed(() => (boardPx.value - 2 * pad.value) / 14)

// Get pixel position for grid intersection (row/col 0-14)
function linePos(idx) {
  return pad.value + idx * gap.value
}

// Style for a stone at board position (r, c)
function stonePosStyle(r, c, sizeFactor = 0.82, player = null) {
  const sz = gap.value * sizeFactor
  const x = linePos(c) - sz / 2
  const y = linePos(r) - sz / 2
  const style = {
    left: x + 'px',
    top: y + 'px',
    width: sz + 'px',
    height: sz + 'px',
    transition: 'all 0.1s',
  }
  if (player === 'black') {
    style.background = 'radial-gradient(circle at 35% 35%, #555, #111, #000)'
    style.boxShadow = '2px 3px 6px rgba(0,0,0,0.7)'
  } else if (player === 'white') {
    style.background = 'radial-gradient(circle at 35% 30%, #fff, #eee, #bbb)'
    style.boxShadow = '2px 3px 6px rgba(0,0,0,0.4)'
    style.border = '1px solid #aaa'
  }
  return style
}

onMounted(() => { calcSizes(); window.addEventListener('resize', calcSizes) })
onUnmounted(() => window.removeEventListener('resize', calcSizes))

const starPoints = [
  { r: 3, c: 3 }, { r: 3, c: 7 }, { r: 3, c: 11 },
  { r: 7, c: 3 }, { r: 7, c: 7 }, { r: 7, c: 11 },
  { r: 11, c: 3 }, { r: 11, c: 7 }, { r: 11, c: 11 },
]

// Audio
let _audioCtx = null
function getAudio() { if (!_audioCtx) _audioCtx = new (window.AudioContext || window.webkitAudioContext)(); return _audioCtx }
function playTone(freq, dur, type = 'sine', gain = 0.15) {
  try {
    const ctx = getAudio(); const o = ctx.createOscillator(); const g = ctx.createGain()
    o.connect(g); g.connect(ctx.destination); o.type = type
    o.frequency.setValueAtTime(freq, ctx.currentTime)
    g.gain.setValueAtTime(gain, ctx.currentTime)
    g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + dur)
    o.start(); o.stop(ctx.currentTime + dur)
  } catch {}
}
function playStone(isBlack) { playTone(isBlack ? 180 : 280, 0.1, 'sawtooth', 0.12) }
function playWin() { [440,554,659,880,1109].forEach((f,i) => setTimeout(() => playTone(f,0.3,'triangle',0.2), i*150)) }

// Game state
function emptyBoard() { return Array.from({length:15}, () => new Array(15).fill(null)) }

const board = ref(emptyBoard())
const currentTurn = ref('black')
const gameOver = ref(false)
const winnerPlayer = ref(null)
const moveHistory = ref([])
const lastMove = ref(null)
const winningCells = ref([])
const hoverR = ref(-1)
const hoverC = ref(-1)

const moveCount = computed(() => moveHistory.value.length)

// Convert click/touch coordinates to nearest board intersection.
// We derive pad and gap from the actual rendered size (via getBoundingClientRect)
// instead of the computed boardPx, because CSS layout may cause the rendered
// size to differ from boardPx on desktop (e.g. flex constraints, rounding).
function pxToRowCol(e) {
  const rect = e.currentTarget.getBoundingClientRect()
  const renderedSize = rect.width          // actual rendered board width
  const scale = renderedSize / boardPx.value  // ratio of rendered vs logical
  const actualPad = pad.value * scale
  const actualGap = gap.value * scale
  const x = e.clientX - rect.left
  const y = e.clientY - rect.top
  const col = Math.round((x - actualPad) / actualGap)
  const row = Math.round((y - actualPad) / actualGap)
  return { row, col }
}

function boardClick(e) {
  if (gameOver.value || currentTurn.value !== 'black') return
  const { row, col } = pxToRowCol(e)
  if (row < 0 || row >= 15 || col < 0 || col >= 15) return
  if (board.value[row][col] !== null) return
  handleClick(row, col)
}

function boardHover(e) {
  const { row, col } = pxToRowCol(e)
  hoverC.value = col
  hoverR.value = row
}

// Win detection
const DIRS = [[0,1],[1,0],[1,1],[1,-1]]

function checkWin(b, r, c, player) {
  for (const [dr,dc] of DIRS) {
    let cells = [{r,c}]
    for (let i=1;i<5;i++) { const nr=r+dr*i,nc=c+dc*i; if(nr<0||nr>=15||nc<0||nc>=15||b[nr][nc]!==player)break; cells.push({r:nr,c:nc}) }
    for (let i=1;i<5;i++) { const nr=r-dr*i,nc=c-dc*i; if(nr<0||nr>=15||nc<0||nc>=15||b[nr][nc]!==player)break; cells.push({r:nr,c:nc}) }
    if (cells.length >= 5) return cells
  }
  return null
}

function countLine(b, r, c, dr, dc, player) {
  let count = 1, openEnds = 0
  for (let i=1;i<=5;i++) { const nr=r+dr*i,nc=c+dc*i; if(nr<0||nr>=15||nc<0||nc>=15)break; if(b[nr][nc]===player)count++; else{if(b[nr][nc]===null)openEnds++;break} }
  for (let i=1;i<=5;i++) { const nr=r-dr*i,nc=c-dc*i; if(nr<0||nr>=15||nc<0||nc>=15)break; if(b[nr][nc]===player)count++; else{if(b[nr][nc]===null)openEnds++;break} }
  return { count, openEnds }
}

function scoreCell(b, r, c, player) {
  let s = 0
  const opp = player === 'white' ? 'black' : 'white'
  for (const [dr,dc] of DIRS) {
    const {count:pc,openEnds:po} = countLine(b,r,c,dr,dc,player)
    const {count:oc,openEnds:oo} = countLine(b,r,c,dr,dc,opp)
    if(pc>=5) s+=1000000; else if(pc===4&&po>=1) s+=50000; else if(pc===3&&po>=2) s+=5000; else if(pc===3&&po===1) s+=2000; else if(pc===2&&po>=2) s+=500; else if(pc===2) s+=100
    if(oc>=5) s+=800000; else if(oc===4&&oo>=1) s+=80000; else if(oc===3&&oo>=2) s+=4000; else if(oc===3&&oo===1) s+=1500; else if(oc===2&&oo>=2) s+=400
  }
  return s
}

function aiMove(b) {
  const candidates = new Set()
  let hasStones = false
  for (let r=0;r<15;r++) for (let c=0;c<15;c++) {
    if (b[r][c]!==null) {
      hasStones = true
      for (let dr=-2;dr<=2;dr++) for (let dc=-2;dc<=2;dc++) {
        const nr=r+dr,nc=c+dc
        if(nr>=0&&nr<15&&nc>=0&&nc<15&&b[nr][nc]===null) candidates.add(nr*15+nc)
      }
    }
  }
  if (!hasStones) return { r: 7 + Math.floor(Math.random()*3)-1, c: 7 + Math.floor(Math.random()*3)-1 }

  let best = -Infinity, move = null
  for (const key of candidates) {
    const r=Math.floor(key/15), c=key%15
    if(b[r][c]!==null) continue
    b[r][c]='white'; let s=scoreCell(b,r,c,'white'); if(checkWin(b,r,c,'white'))s+=10000000; b[r][c]=null
    b[r][c]='black'; if(checkWin(b,r,c,'black'))s+=5000000; b[r][c]=null
    s += Math.random()*10
    if(s>best){best=s;move={r,c}}
  }
  return move
}

function placeStone(r, c, player) {
  const b = board.value.map(row=>[...row])
  b[r][c] = player
  board.value = b
  moveHistory.value = [...moveHistory.value, {r,c,player}]
  lastMove.value = {r,c,player}
  playStone(player==='black')
  const win = checkWin(b,r,c,player)
  if (win) { winningCells.value=win; gameOver.value=true; winnerPlayer.value=player; setTimeout(playWin,150); return true }
  return false
}

function handleClick(r, c) {
  if(gameOver.value||currentTurn.value!=='black'||board.value[r][c]!==null) return
  if(placeStone(r,c,'black')) return
  currentTurn.value = 'white'
  setTimeout(() => {
    if(gameOver.value) return
    const b=board.value.map(row=>[...row])
    const m=aiMove(b)
    if(!m) return
    if(!placeStone(m.r,m.c,'white')) currentTurn.value='black'
  }, 300)
}

function undoMove() {
  if(moveHistory.value.length<2||gameOver.value||currentTurn.value!=='black') return
  const h=[...moveHistory.value]
  const ai=h.pop(), pl=h.pop()
  const b=board.value.map(row=>[...row])
  b[ai.r][ai.c]=null; b[pl.r][pl.c]=null
  board.value=b; moveHistory.value=h
  lastMove.value=h.length>0?h[h.length-1]:null
  winningCells.value=[]; gameOver.value=false; winnerPlayer.value=null; currentTurn.value='black'
}

function restartGame() {
  board.value=emptyBoard(); currentTurn.value='black'; gameOver.value=false
  winnerPlayer.value=null; moveHistory.value=[]; lastMove.value=null; winningCells.value=[]
}

function isWinningCell(r,c) { return winningCells.value.some(w=>w.r===r&&w.c===c) }
function colLabel(c) { return String.fromCharCode(65+c) }
</script>

<style scoped>
.modal-enter-active,.modal-leave-active{transition:opacity 0.3s}
.modal-enter-from,.modal-leave-to{opacity:0}
</style>
