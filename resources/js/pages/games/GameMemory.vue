<template>
  <div class="memory-game">
    <div class="game-header">
      <button class="back-btn" @click="goBack">← 뒤로</button>
      <div class="level-badge">레벨 {{ level }} 🧠</div>
      <div class="score">⭐ {{ score }}</div>
    </div>

    <div v-if="phase==='start'" class="center-box">
      <div style="font-size:80px">🧠</div>
      <h1 class="title">기억력 카드</h1>
      <p class="subtitle">같은 그림을 찾아 짝을 맞춰요!</p>
      <div class="level-info">
        <div>레벨 1-2: 8쌍 카드 (4×4)</div>
        <div>레벨 3-4: 12쌍 카드 (4×6)</div>
        <div>레벨 5+: 18쌍 카드 (6×6)</div>
      </div>
      <div class="best-time" v-if="bestTime">🏆 최단시간: {{ bestTime }}초</div>
      <!-- 레벨 선택 (잠금 UI) -->
      <div class="level-picker">
        <div class="level-picker-label">레벨 선택</div>
        <div class="level-picker-row">
          <button v-for="lv in 6" :key="lv" type="button"
            class="level-pick-btn"
            :class="{ active: level===lv, locked: lv > Math.max(1, rec.maxUnlockedLevel.value || 1) }"
            :disabled="lv > Math.max(1, rec.maxUnlockedLevel.value || 1)"
            @click="level = lv">
            <span v-if="lv > Math.max(1, rec.maxUnlockedLevel.value || 1)">🔒</span>
            <span v-else>Lv.{{ lv }}</span>
          </button>
        </div>
      </div>
      <div class="progress-info" v-if="rec.maxCompletedLevel.value > 0">
        🎯 최고 클리어: Lv.{{ rec.maxCompletedLevel.value }} · 다음 도전: Lv.{{ rec.maxUnlockedLevel.value }}
      </div>
      <button class="start-btn" @click="startGame">시작! 🃏</button>
    </div>

    <div v-if="phase==='play'" class="play-area">
      <div class="game-info">
        <div class="moves-display">👆 {{ moves }}번</div>
        <div class="timer-display">⏱ {{ elapsed }}초</div>
        <div class="pairs-display">✅ {{ matched }}/{{ totalPairs }}</div>
      </div>
      <div class="card-grid" :style="gridStyle">
        <div v-for="(card, i) in cards" :key="i"
          class="memory-card" :class="{flipped: card.flipped || card.matched, matched: card.matched}"
          @click="flipCard(i)">
          <div class="card-face front">❓</div>
          <div class="card-face back">{{ card.emoji }}</div>
        </div>
      </div>
    </div>

    <div v-if="phase==='complete'" class="result-box">
      <div style="font-size:80px">🎊</div>
      <div class="res-title">완성!</div>
      <div class="res-stats">{{ moves }}번 · {{ elapsed }}초</div>
      <div v-if="isNewBest" class="new-best">🏆 새 기록!</div>
      <div v-if="leveled" class="levelup">🎉 레벨업! 레벨 {{ level }}!</div>
      <GameResultExtras :rec="rec" slug="memory" />
      <div class="res-btns">
        <button class="rbtn" @click="startGame">다시 🔄</button>
        <button class="rbtn home" @click="goBack">홈 🏠</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onUnmounted, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import GameResultExtras from '../../components/GameResultExtras.vue'
import { useGameRecord } from '../../composables/useGameRecord'
const router = useRouter()
const rec = useGameRecord('memory')

const allEmojis = ['🐶','🐱','🦊','🐸','🦁','🐘','🦋','🌺','🍎','🍌','⭐','🌙','🚗','✈️','🎸','🎁','🌈','🏆','🎯','🎲','🌍','🔥','💎','🦄']

const level = ref(parseInt(localStorage.getItem('memory_level') || '1'))
const bestTime = ref(parseInt(localStorage.getItem('memory_best') || '0') || null)
const phase = ref('start')
const cards = ref([])
const moves = ref(0)
const elapsed = ref(0)
const matched = ref(0)
const score = ref(0)
const leveled = ref(false)
const isNewBest = ref(false)
let flipped1 = ref(-1)
let flipped2 = ref(-1)
let checking = false
let timerInterval = null
let startTime = null

const totalPairs = computed(() => level.value <= 2 ? 8 : level.value <= 4 ? 12 : 18)

const gridStyle = computed(() => {
  const cols = level.value <= 2 ? 4 : level.value <= 4 ? 6 : 6
  return { gridTemplateColumns: `repeat(${cols}, 1fr)` }
})

function speak(text) {
  if (!window.speechSynthesis) return
  window.speechSynthesis.cancel()
  const u = new SpeechSynthesisUtterance(text)
  u.lang = 'ko-KR'; u.rate = 0.9
  window.speechSynthesis.speak(u)
}

function shuffle(a){const r=[...a];for(let i=r.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[r[i],r[j]]=[r[j],r[i]]}return r}

function startGame() {
  const pairs = totalPairs.value
  const emojis = shuffle(allEmojis).slice(0, pairs)
  cards.value = shuffle([...emojis, ...emojis].map(e => ({emoji:e, flipped:false, matched:false})))
  moves.value = 0; matched.value = 0; score.value = 0
  leveled.value = false; isNewBest.value = false
  flipped1.value = -1; flipped2.value = -1; checking = false
  elapsed.value = 0; startTime = Date.now()
  clearInterval(timerInterval)
  timerInterval = setInterval(() => { elapsed.value = Math.round((Date.now()-startTime)/1000) }, 1000)
  rec.start(level.value)
  phase.value = 'play'
  speak('카드를 뒤집어 짝을 찾아요!')
}

function flipCard(i) {
  if (checking || cards.value[i].matched || cards.value[i].flipped) return
  if (flipped1.value >= 0 && flipped2.value >= 0) return
  cards.value[i] = {...cards.value[i], flipped: true}
  if (flipped1.value < 0) { flipped1.value = i; return }
  flipped2.value = i; moves.value++; checking = true
  setTimeout(() => {
    const c1 = cards.value[flipped1.value], c2 = cards.value[flipped2.value]
    if (c1.emoji === c2.emoji) {
      cards.value[flipped1.value] = {...c1, matched: true, flipped: false}
      cards.value[flipped2.value] = {...c2, matched: true, flipped: false}
      matched.value++
      score.value += Math.max(5, 20 - moves.value)
      if (matched.value === totalPairs.value) {
        clearInterval(timerInterval)
        const threshold = level.value <= 2 ? 20 : level.value <= 4 ? 30 : 50
        if (!bestTime.value || elapsed.value < bestTime.value) {
          bestTime.value = elapsed.value
          localStorage.setItem('memory_best', elapsed.value)
          isNewBest.value = true
        }
        if (moves.value <= threshold) {
          level.value++; localStorage.setItem('memory_level', level.value)
          leveled.value = true; speak('완성! 레벨업!')
        } else speak('완성!')
        phase.value = 'complete'
        rec.end({ won: true, leveledUp: leveled.value, score: score.value })
      }
    } else {
      cards.value[flipped1.value] = {...c1, flipped: false}
      cards.value[flipped2.value] = {...c2, flipped: false}
    }
    flipped1.value = -1; flipped2.value = -1; checking = false
  }, 800)
}

function goBack() { clearInterval(timerInterval); router.push('/games') }

onMounted(async () => {
  await rec.loadProgress()
  if (rec.maxUnlockedLevel.value > level.value) {
    level.value = rec.maxUnlockedLevel.value
    localStorage.setItem('memory_level', level.value)
  }
})
onUnmounted(() => clearInterval(timerInterval))
</script>

<style scoped>
.memory-game { min-height:100vh; background:linear-gradient(135deg,#0c4a6e,#075985,#0369a1); padding:16px; font-family:'Noto Sans KR',sans-serif; }
.game-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
.back-btn { background:rgba(255,255,255,0.15); color:#fff; border:none; padding:8px 14px; border-radius:20px; cursor:pointer; font-size:14px; }
.level-badge,.score { background:rgba(255,255,255,0.15); color:#fff; padding:6px 14px; border-radius:20px; font-weight:700; }
.center-box { text-align:center; padding:30px 20px; }
.title { font-size:36px; color:#fff; font-weight:900; margin:10px 0; }
.subtitle { color:rgba(255,255,255,0.85); font-size:15px; }
.level-info { background:rgba(0,0,0,0.2); border-radius:12px; padding:12px 20px; color:#bae6fd; font-size:14px; line-height:1.8; margin:12px auto; max-width:220px; text-align:left; }
.best-time { color:#fbbf24; font-size:15px; margin:8px 0; }
.progress-info { color:#bae6fd; font-size:14px; margin:6px 0; background:rgba(0,0,0,0.2); padding:6px 14px; border-radius:14px; display:inline-block; }
.level-picker { margin:14px auto 6px; max-width:320px; }
.level-picker-label { color:#bae6fd; font-size:13px; font-weight:700; margin-bottom:8px; }
.level-picker-row { display:flex; gap:6px; justify-content:center; flex-wrap:wrap; }
.level-pick-btn { flex:1 1 auto; min-width:46px; max-width:60px; padding:8px 4px; border-radius:10px; border:2px solid rgba(255,255,255,0.2); background:rgba(255,255,255,0.08); color:#fff; font-size:12px; font-weight:700; cursor:pointer; transition:all 0.15s; }
.level-pick-btn:hover:not(:disabled) { border-color:#0ea5e9; background:rgba(14,165,233,0.25); }
.level-pick-btn.active { background:#0ea5e9; border-color:#0ea5e9; color:#fff; }
.level-pick-btn.locked { opacity:0.4; cursor:not-allowed; }
.level-pick-btn:disabled { cursor:not-allowed; }
.start-btn { background:#0ea5e9; color:#fff; border:none; padding:14px 40px; border-radius:30px; font-size:20px; font-weight:800; cursor:pointer; margin-top:16px; }
.play-area { display:flex; flex-direction:column; align-items:center; }
.game-info { display:flex; gap:16px; margin-bottom:14px; }
.moves-display,.timer-display,.pairs-display { color:#fff; font-size:15px; font-weight:600; background:rgba(255,255,255,0.1); padding:6px 12px; border-radius:12px; }
.card-grid { display:grid; gap:8px; }
.memory-card { width:56px; height:56px; cursor:pointer; perspective:600px; position:relative; }
.card-face { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; border-radius:10px; font-size:28px; backface-visibility:hidden; transition:transform 0.4s; }
.front { background:rgba(255,255,255,0.15); border:2px solid rgba(255,255,255,0.3); transform:rotateY(0deg); }
.back { background:rgba(14,165,233,0.4); border:2px solid #0ea5e9; transform:rotateY(180deg); }
.memory-card.flipped .front { transform:rotateY(180deg); }
.memory-card.flipped .back { transform:rotateY(0deg); }
.memory-card.matched .front { transform:rotateY(180deg); }
.memory-card.matched .back { background:rgba(16,185,129,0.5); border-color:#10b981; transform:rotateY(0deg); }
.result-box { text-align:center; padding:40px 20px; }
.res-title { font-size:40px; color:#fff; font-weight:900; margin:10px 0; }
.res-stats { color:rgba(255,255,255,0.8); font-size:18px; margin:8px 0; }
.new-best { color:#fbbf24; font-size:18px; font-weight:800; margin:8px 0; }
.levelup { background:#0ea5e9; color:#fff; padding:10px 20px; border-radius:20px; font-weight:800; font-size:18px; margin:14px auto; display:inline-block; }
.res-btns { display:flex; gap:12px; justify-content:center; margin-top:20px; }
.rbtn { background:rgba(255,255,255,0.9); color:#0c4a6e; border:none; padding:12px 28px; border-radius:20px; font-size:16px; font-weight:700; cursor:pointer; }
.rbtn.home { background:#0369a1; color:#fff; }
</style>
