<template>
  <GameShell title="퍼즐 맞추기" icon="🧩" theme="dark"
    bg="linear-gradient(135deg,#4c1d95,#5b21b6,#7c3aed)">
    <template #meta>
      <span class="meta-badge">Lv.{{ level }}</span>
      <span class="meta-badge">⭐ {{ moves }}수</span>
    </template>
  <div class="puzzle-game">
    <div v-if="phase==='start'" class="center-box">
      <div style="font-size:80px">🧩</div>
      <h1 class="title">퍼즐 맞추기</h1>
      <p class="subtitle">숫자를 순서대로 맞춰요!</p>
      <div class="level-info">
        <div>레벨 1-2: 3×3 (8칸)</div>
        <div>레벨 3-4: 4×4 (15칸)</div>
        <div>레벨 5+: 5×5 (24칸)</div>
        <div style="margin-top:8px">현재 레벨: {{ level }}</div>
      </div>
      <button class="start-btn" @click="startGame">시작! 🎮</button>
    </div>

    <div v-if="phase==='play'" class="play-area">
      <div class="puzzle-info">
        <span>{{ moves }}수</span>
        <span>⏱ {{ timeLeft }}초</span>
        <span>{{ gridSize }}×{{ gridSize }}</span>
      </div>
      <div class="puzzle-grid" :style="gridStyle">
        <div v-for="(val, idx) in tiles" :key="idx"
          class="puzzle-tile" :class="{empty: val===0, correct: isCorrect(val,idx)}"
          :style="tileStyle(val, idx)" @click="clickTile(idx)">
          <span v-if="val !== 0">{{ val }}</span>
        </div>
      </div>
      <button class="shuffle-btn" @click="shuffleTiles">🔀 섞기</button>
    </div>

    <div v-if="phase==='solved'" class="solved-box">
      <div style="font-size:80px">🎊</div>
      <h2 class="solved-title">완성!</h2>
      <div class="solved-stats">{{ moves }}수 · {{ elapsedTime }}초</div>
      <div v-if="leveled" class="levelup">🎉 레벨업! 레벨 {{ level }}!</div>
      <div class="res-btns">
        <button class="rbtn" @click="startGame">다시 🔄</button>
        <button class="rbtn home" @click="goBack">홈 🏠</button>
      </div>
    </div>
  </div>
  </GameShell>
</template>

<script setup>
import { ref, computed, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import GameShell from '../../components/GameShell.vue'
const router = useRouter()

const level = ref(parseInt(localStorage.getItem('puzzle_level') || '1'))
const moves = ref(0)
const phase = ref('start')
const tiles = ref([])
const timeLeft = ref(300)
const elapsedTime = ref(0)
const leveled = ref(false)
let timer = null
let startTime = null

const gridSize = computed(() => level.value <= 2 ? 3 : level.value <= 4 ? 4 : 5)

const gridStyle = computed(() => ({
  gridTemplateColumns: `repeat(${gridSize.value}, 1fr)`,
  width: `${gridSize.value * 72}px`
}))

function tileStyle(val, idx) {
  if (val === 0) return {}
  return { background: isCorrect(val, idx) ? '#10b981' : '#6366f1' }
}

function isCorrect(val, idx) {
  return val === idx + 1 || (val === 0 && idx === tiles.value.length - 1)
}

function speak(text) {
  if (!window.speechSynthesis) return
  window.speechSynthesis.cancel()
  const u = new SpeechSynthesisUtterance(text)
  u.lang = 'ko-KR'; u.rate = 0.9; u.pitch = 1.0
  window.speechSynthesis.speak(u)
}

function createSolved() {
  const size = gridSize.value
  const total = size * size
  return [...Array(total - 1).keys()].map(i => i + 1).concat(0)
}

function isSolvable(arr, size) {
  let inv = 0
  for (let i = 0; i < arr.length; i++)
    for (let j = i+1; j < arr.length; j++)
      if (arr[i] && arr[j] && arr[i] > arr[j]) inv++
  if (size % 2 === 1) return inv % 2 === 0
  const emptyRow = Math.floor(arr.indexOf(0) / size)
  return (inv + emptyRow) % 2 === 1
}

function shuffleTiles() {
  const size = gridSize.value
  let arr = createSolved()
  do { arr = [...arr].sort(()=>Math.random()-0.5) } while (!isSolvable(arr, size))
  tiles.value = arr
  moves.value = 0
}

function startGame() {
  leveled.value = false
  shuffleTiles()
  startTime = Date.now()
  timeLeft.value = level.value <= 2 ? 300 : level.value <= 4 ? 240 : 180
  phase.value = 'play'
  clearInterval(timer)
  timer = setInterval(() => {
    timeLeft.value--
    if (timeLeft.value <= 0) { clearInterval(timer); speak('시간 초과!') }
  }, 1000)
  speak(gridSize.value + '곱하기 ' + gridSize.value + ' 퍼즐 시작!')
}

function clickTile(idx) {
  if (phase.value !== 'play') return
  const emptyIdx = tiles.value.indexOf(0)
  const size = gridSize.value
  const r1 = Math.floor(idx/size), c1 = idx%size
  const r2 = Math.floor(emptyIdx/size), c2 = emptyIdx%size
  if (Math.abs(r1-r2)+Math.abs(c1-c2) !== 1) return
  const arr = [...tiles.value]
  arr[emptyIdx] = arr[idx]; arr[idx] = 0
  tiles.value = arr; moves.value++
  if (checkSolved()) {
    clearInterval(timer)
    elapsedTime.value = Math.round((Date.now() - startTime) / 1000)
    const movesThreshold = level.value <= 2 ? 30 : level.value <= 4 ? 60 : 100
    if (moves.value <= movesThreshold) {
      level.value++
      localStorage.setItem('puzzle_level', level.value)
      leveled.value = true
    }
    phase.value = 'solved'
    speak(leveled.value ? '완성! 레벨업!' : '완성!')
  }
}

function checkSolved() {
  const sol = createSolved()
  return tiles.value.every((v, i) => v === sol[i])
}

function goBack() { clearInterval(timer); router.push('/games') }
onUnmounted(() => clearInterval(timer))
</script>

<style scoped>
.puzzle-game { flex:1; padding:16px; font-family:'Noto Sans KR',sans-serif; }
.meta-badge { font-size:11px; font-weight:800; padding:4px 10px; border-radius:14px; background:rgba(255,255,255,0.15); color:#fff; white-space:nowrap; }
.center-box { text-align:center; padding:30px 20px; }
.title { font-size:36px; color:#fff; font-weight:900; margin:10px 0; }
.subtitle { color:rgba(255,255,255,0.8); font-size:16px; }
.level-info { background:rgba(0,0,0,0.2); border-radius:12px; padding:12px 20px; color:#ddd6fe; font-size:14px; line-height:1.8; margin:12px auto; max-width:240px; text-align:left; }
.start-btn { background:#fff; color:#5b21b6; border:none; padding:14px 40px; border-radius:30px; font-size:20px; font-weight:800; cursor:pointer; margin-top:16px; }
.play-area { display:flex; flex-direction:column; align-items:center; }
.puzzle-info { display:flex; gap:16px; margin-bottom:16px; color:#fff; font-size:15px; font-weight:600; }
.puzzle-grid { display:grid; gap:4px; margin-bottom:16px; }
.puzzle-tile { width:68px; height:68px; display:flex; align-items:center; justify-content:center; border-radius:10px; font-size:22px; font-weight:800; color:#fff; cursor:pointer; transition:all 0.15s; user-select:none; }
.puzzle-tile:not(.empty):hover { transform:scale(0.96); }
.puzzle-tile.empty { background:rgba(255,255,255,0.1); cursor:default; }
.shuffle-btn { background:rgba(255,255,255,0.2); color:#fff; border:none; padding:10px 24px; border-radius:20px; font-size:15px; cursor:pointer; }
.solved-box { text-align:center; padding:40px 20px; }
.solved-title { font-size:36px; color:#fff; font-weight:900; margin:10px 0; }
.solved-stats { color:rgba(255,255,255,0.8); font-size:18px; margin:8px 0; }
.levelup { background:#fff; color:#5b21b6; padding:10px 20px; border-radius:20px; font-weight:800; font-size:18px; margin:14px auto; display:inline-block; }
.res-btns { display:flex; gap:12px; justify-content:center; margin-top:20px; }
.rbtn { background:rgba(255,255,255,0.9); color:#4c1d95; border:none; padding:12px 28px; border-radius:20px; font-size:16px; font-weight:700; cursor:pointer; }
.rbtn.home { background:#7c3aed; color:#fff; }
</style>
