<template>
  <div class="bingo-game">
    <div class="game-header">
      <button class="back-btn" @click="goBack">← 뒤로</button>
      <div class="level-badge">레벨 {{ level }} 🎯</div>
      <div class="score">빙고 {{ bingoCount }}줄</div>
    </div>

    <div v-if="phase==='start'" class="center-box">
      <div style="font-size:80px">🎯</div>
      <h1 class="title">빙고!</h1>
      <p class="subtitle">숫자가 불리면 체크하세요! 먼저 빙고를 완성해요!</p>
      <div class="level-info">
        <div>레벨 1-2: 3×3 빙고</div>
        <div>레벨 3-4: 4×4 빙고</div>
        <div>레벨 5+: 5×5 빙고</div>
      </div>
      <button class="start-btn" @click="startGame">게임 시작! 🎲</button>
    </div>

    <div v-if="phase==='play'" class="play-area">
      <div class="called-display">
        <div class="called-label">호출된 숫자</div>
        <div class="called-number" :class="{animate: justCalled}">{{ lastCalled || '?' }}</div>
      </div>
      <div class="bingo-card" :style="cardStyle">
        <div v-for="(cell, idx) in card" :key="idx"
          class="bingo-cell" :class="{marked: cell.marked, bingo: isBingoCell(idx)}"
          @click="markCell(idx)">
          {{ cell.num }}
        </div>
      </div>
      <div class="bingo-status">
        <div class="bingo-count">빙고 {{ bingoCount }}줄</div>
        <div class="called-count">{{ calledNums.length }}번 호출됨</div>
      </div>
      <div class="called-list">
        <span v-for="n in calledNums" :key="n" class="called-chip">{{ n }}</span>
      </div>
      <button class="call-btn" @click="callNext" :disabled="phase!=='play'">다음 숫자 뽑기 🎲</button>
    </div>

    <div v-if="phase==='bingo'" class="result-box">
      <div style="font-size:80px">🎊</div>
      <h2 class="res-title">빙고!</h2>
      <div class="res-detail">{{ bingoCount }}줄 빙고! {{ calledNums.length }}번 만에!</div>
      <div v-if="leveled" class="levelup">🎉 레벨업! 레벨 {{ level }}!</div>
      <div class="res-btns">
        <button class="rbtn" @click="startGame">다시 🔄</button>
        <button class="rbtn home" @click="goBack">홈 🏠</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
const router = useRouter()

const level = ref(parseInt(localStorage.getItem('bingo_level') || '1'))
const phase = ref('start')
const card = ref([])
const calledNums = ref([])
const lastCalled = ref(null)
const justCalled = ref(false)
const bingoCount = ref(0)
const leveled = ref(false)

const gridSize = computed(() => level.value <= 2 ? 3 : level.value <= 4 ? 4 : 5)
const maxNum = computed(() => gridSize.value * gridSize.value * 2)

const cardStyle = computed(() => ({
  gridTemplateColumns: `repeat(${gridSize.value}, 1fr)`
}))

function speak(text) {
  if (!window.speechSynthesis) return
  window.speechSynthesis.cancel()
  const u = new SpeechSynthesisUtterance(text)
  u.lang = 'ko-KR'; u.rate = 0.9
  window.speechSynthesis.speak(u)
}

function shuffle(a){const r=[...a];for(let i=r.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[r[i],r[j]]=[r[j],r[i]]}return r}

function startGame() {
  const size = gridSize.value
  const total = size * size
  const nums = shuffle([...Array(maxNum.value).keys()].map(i => i+1)).slice(0, total)
  card.value = nums.map(n => ({num: n, marked: false}))
  calledNums.value = []
  lastCalled.value = null
  bingoCount.value = 0
  leveled.value = false
  phase.value = 'play'
  speak(size + '곱하기 ' + size + ' 빙고 시작!')
}

function callNext() {
  const size = gridSize.value
  const remaining = [...Array(maxNum.value).keys()].map(i=>i+1).filter(n=>!calledNums.value.includes(n))
  if (remaining.length === 0) return
  const n = remaining[Math.floor(Math.random()*remaining.length)]
  calledNums.value.push(n)
  lastCalled.value = n
  justCalled.value = true
  setTimeout(() => justCalled.value = false, 800)
  // auto mark
  const idx = card.value.findIndex(c => c.num === n)
  if (idx >= 0) { card.value[idx] = {...card.value[idx], marked: true}; checkBingo() }
  speak(String(n))
}

function markCell(idx) {
  if (!card.value[idx].marked) return
}

function isBingoCell(idx) {
  const size = gridSize.value
  const r = Math.floor(idx/size), c = idx%size
  // check if this cell is in a completed line
  const rows = Array.from({length:size}, (_,i) => Array.from({length:size}, (_,j)=>i*size+j))
  const cols = Array.from({length:size}, (_,j) => Array.from({length:size}, (_,i)=>i*size+j))
  const diag1 = Array.from({length:size}, (_,i) => i*size+i)
  const diag2 = Array.from({length:size}, (_,i) => i*size+(size-1-i))
  const lines = [...rows, ...cols, diag1, diag2]
  return lines.some(line => line.includes(idx) && line.every(i => card.value[i]?.marked))
}

function checkBingo() {
  const size = gridSize.value
  const rows = Array.from({length:size}, (_,i) => Array.from({length:size}, (_,j)=>i*size+j))
  const cols = Array.from({length:size}, (_,j) => Array.from({length:size}, (_,i)=>i*size+j))
  const diag1 = Array.from({length:size}, (_,i)=>i*size+i)
  const diag2 = Array.from({length:size}, (_,i)=>i*size+(size-1-i))
  const lines = [...rows, ...cols, diag1, diag2]
  const newBingo = lines.filter(line => line.every(i => card.value[i]?.marked)).length
  if (newBingo > bingoCount.value) {
    bingoCount.value = newBingo
    speak('빙고!')
    const target = level.value <= 2 ? 1 : level.value <= 4 ? 2 : 3
    if (newBingo >= target) {
      phase.value = 'bingo'
      if (calledNums.value.length <= size*size+3) {
        level.value++
        localStorage.setItem('bingo_level', level.value)
        leveled.value = true
        speak('빙고! 레벨업!')
      }
    }
  }
}

function goBack() { router.push('/games') }
</script>

<style scoped>
.bingo-game { min-height:100vh; background:linear-gradient(135deg,#701a75,#86198f,#a21caf); padding:16px; font-family:'Noto Sans KR',sans-serif; }
.game-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
.back-btn { background:rgba(255,255,255,0.15); color:#fff; border:none; padding:8px 14px; border-radius:20px; cursor:pointer; font-size:14px; }
.level-badge,.score { background:rgba(255,255,255,0.15); color:#fff; padding:6px 14px; border-radius:20px; font-weight:700; }
.center-box { text-align:center; padding:30px 20px; }
.title { font-size:40px; color:#fff; font-weight:900; margin:10px 0; }
.subtitle { color:rgba(255,255,255,0.85); font-size:15px; }
.level-info { background:rgba(0,0,0,0.2); border-radius:12px; padding:12px 20px; color:#f0abfc; font-size:14px; line-height:1.8; margin:12px auto; max-width:200px; text-align:left; }
.start-btn { background:#e879f9; color:#fff; border:none; padding:14px 40px; border-radius:30px; font-size:20px; font-weight:800; cursor:pointer; margin-top:16px; }
.play-area { display:flex; flex-direction:column; align-items:center; }
.called-display { text-align:center; margin-bottom:16px; }
.called-label { color:rgba(255,255,255,0.6); font-size:12px; }
.called-number { font-size:72px; font-weight:900; color:#e879f9; transition:all 0.3s; }
.called-number.animate { transform:scale(1.3); color:#fff; }
.bingo-card { display:grid; gap:6px; margin-bottom:14px; }
.bingo-cell { width:64px; height:64px; display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,0.15); border-radius:10px; font-size:20px; font-weight:700; color:#fff; border:2px solid transparent; transition:all 0.2s; cursor:default; }
.bingo-cell.marked { background:rgba(232,121,249,0.4); border-color:#e879f9; color:#fce7f3; }
.bingo-cell.bingo { background:#e879f9; border-color:#fff; color:#fff; }
.bingo-status { display:flex; gap:16px; margin-bottom:10px; }
.bingo-count { color:#e879f9; font-size:18px; font-weight:700; }
.called-count { color:rgba(255,255,255,0.6); font-size:14px; }
.called-list { display:flex; flex-wrap:wrap; gap:4px; max-width:340px; margin-bottom:14px; justify-content:center; }
.called-chip { background:rgba(255,255,255,0.2); color:#fff; font-size:12px; padding:2px 8px; border-radius:10px; }
.call-btn { background:#e879f9; color:#fff; border:none; padding:14px 36px; border-radius:20px; font-size:17px; font-weight:700; cursor:pointer; }
.call-btn:disabled { opacity:0.5; cursor:not-allowed; }
.result-box { text-align:center; padding:40px 20px; }
.res-title { font-size:40px; color:#e879f9; font-weight:900; margin:10px 0; }
.res-detail { color:rgba(255,255,255,0.8); font-size:18px; }
.levelup { background:#e879f9; color:#fff; padding:10px 20px; border-radius:20px; font-weight:800; font-size:18px; margin:14px auto; display:inline-block; }
.res-btns { display:flex; gap:12px; justify-content:center; margin-top:20px; }
.rbtn { background:rgba(255,255,255,0.9); color:#701a75; border:none; padding:12px 28px; border-radius:20px; font-size:16px; font-weight:700; cursor:pointer; }
.rbtn.home { background:#a21caf; color:#fff; }
</style>
