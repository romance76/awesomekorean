<template>
  <div class="mult-game">
    <div class="game-header">
      <button class="back-btn" @click="$router.push('/games')">← 뒤로</button>
      <div class="level-badge">레벨 {{ level }} ✖</div>
      <div class="score-badge">{{ score }}점</div>
    </div>

    <div v-if="phase==='start'" class="center-box">
      <div style="font-size:90px">✖️</div>
      <h1 class="title">구구단</h1>
      <p class="subtitle">곱셈을 마스터해요!</p>
      <div class="level-preview">{{ levelRange }}</div>
      <button class="start-btn" @click="startGame">시작하기 ▶</button>
    </div>

    <div v-if="phase==='play'" class="play-box">
      <div class="progress-row">
        <div class="progress-bar"><div class="progress-fill" :style="{width:(qIdx/totalQ*100)+'%'}"></div></div>
        <span class="q-count">{{ qIdx+1 }}/{{ totalQ }}</span>
      </div>
      <div v-if="cur" class="equation-box">
        <span class="eq-num">{{ cur.a }}</span>
        <span class="eq-op">×</span>
        <span class="eq-num">{{ cur.b }}</span>
        <span class="eq-op">=</span>
        <span class="eq-ans">?</span>
      </div>
      <div class="mult-hint" v-if="level<=2">
        💡 {{ cur?.a }} × {{ cur?.b }} 를 계산해요
      </div>
      <div class="choices-grid">
        <button v-for="opt in cur?.opts" :key="opt"
          class="choice-btn" :disabled="answered" @click="answer(opt)">{{ opt }}</button>
      </div>
    </div>

    <div v-if="phase==='end'" class="end-box">
      <div style="font-size:80px">{{ correct>=totalQ*0.7?'🏅':'📚' }}</div>
      <h2 class="end-title">{{ correct>=totalQ*0.7?'구구단 마스터!':'잘 했어요!' }}</h2>
      <p class="end-score">{{ score }}점 · {{ correct }}/{{ totalQ }} 정답</p>
      <div v-if="leveled" class="levelup-badge">🎉 레벨업! 레벨 {{ level }}!</div>
      <GameResultExtras :rec="rec" slug="multiplication" />
      <button class="start-btn" @click="startGame">다시 하기 🔄</button>
      <button class="home-btn" @click="$router.push('/games')">홈으로 🏠</button>
    </div>

    <Transition name="fb">
      <div v-if="showFeedback" class="feedback-overlay" :class="lastCorrect?'fb-correct':'fb-wrong'">
        <div class="fb-content">
          <div class="fb-emoji">{{ lastCorrect ? '🎉' : '😢' }}</div>
          <div class="fb-title">{{ lastCorrect ? '정답이에요!' : '아쉬워요!' }}</div>
          <div v-if="!lastCorrect && cur" class="fb-answer">
            {{ cur.a }} × {{ cur.b }} = <strong>{{ cur.ans }}</strong>
          </div>
          <div class="fb-bar-wrap"><div class="fb-bar" :style="{width:fbProgress+'%'}"></div></div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import GameResultExtras from '../../components/GameResultExtras.vue'
import { useGameRecord } from '../../composables/useGameRecord'
const router = useRouter()
const rec = useGameRecord('multiplication')
const level = ref(parseInt(localStorage.getItem('mult_level')||'1'))
const score = ref(0); const qIdx = ref(0); const correct = ref(0)
const leveled = ref(false); const answered = ref(false); const phase = ref('start')
const showFeedback = ref(false); const lastCorrect = ref(false); const fbProgress = ref(100)
let fbTimer = null
const totalQ = 12
const questions = ref([])
const cur = computed(() => questions.value[qIdx.value])

const levelRange = computed(() => {
  if (level.value <= 2) return '2단 ~ 4단'
  if (level.value <= 4) return '5단 ~ 7단'
  return '8단 ~ 9단'
})

function speak(t) {
  if (!window.speechSynthesis) return
  window.speechSynthesis.cancel()
  const u = new SpeechSynthesisUtterance(t); u.lang='ko-KR'; u.rate=0.9
  window.speechSynthesis.speak(u)
}
function rand(a,b){ return Math.floor(Math.random()*(b-a+1))+a }
function shuffle(a){ return [...a].sort(()=>Math.random()-.5) }

function genQ() {
  const lv = level.value
  let tableRange
  if (lv <= 2) tableRange = [2,3,4]
  else if (lv <= 4) tableRange = [5,6,7]
  else tableRange = [8,9,6,7]
  const a = tableRange[rand(0,tableRange.length-1)]
  const b = rand(2,9)
  const ans = a*b
  const wrongs = new Set()
  while(wrongs.size<3){ const w=ans+rand(-8,8)*1; if(w!==ans&&w>0&&w<=81)wrongs.add(w) }
  return { a, b, ans, opts: shuffle([ans,...wrongs]) }
}

function startGame() {
  score.value=0; qIdx.value=0; correct.value=0; leveled.value=false
  answered.value=false; showFeedback.value=false; phase.value='play'
  rec.start(level.value)
  questions.value = Array.from({length:totalQ}, genQ)
  speak('구구단을 풀어봐요!')
}

function triggerFeedback(isCorrect) {
  lastCorrect.value = isCorrect; showFeedback.value = true; fbProgress.value = 100
  clearInterval(fbTimer)
  const dur = isCorrect ? 1200 : 2200; const step = 50/dur*100
  fbTimer = setInterval(() => {
    fbProgress.value = Math.max(0, fbProgress.value - step)
    if (fbProgress.value <= 0) {
      clearInterval(fbTimer); showFeedback.value = false; answered.value = false
      qIdx.value++
      if (qIdx.value >= totalQ) endGame()
    }
  }, 50)
}

function answer(opt) {
  if (answered.value) return
  answered.value = true
  const isOk = opt === cur.value.ans
  if (isOk) { score.value += 10; correct.value++ }
  speak(isOk ? '정답이에요!' : `정답은 ${cur.value.ans}이에요`)
  triggerFeedback(isOk)
}

async function endGame() {
  phase.value = 'end'
  const passed = correct.value >= Math.ceil(totalQ * 0.7)
  if (passed) {
    level.value++; localStorage.setItem('mult_level', level.value); leveled.value = true
    speak('구구단 마스터! 레벨업!')
  } else speak('다시 도전해봐요!')
  await rec.end({ won: passed, leveledUp: leveled.value, score: score.value })
  const token = localStorage.getItem('token')
  if (token) {
    try {
      await axios.post('/api/games/12/score',
        { score: score.value, level: level.value, result: passed?'win':'lose', duration: totalQ*5 },
        { headers: { Authorization: `Bearer ${token}` } })
    } catch(e) {}
  }
}
</script>

<style scoped>
.mult-game { min-height:100vh; background:linear-gradient(135deg,#1e3a5f,#1d4ed8,#2563eb); padding:16px; font-family:'Noto Sans KR',sans-serif; }
.game-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
.back-btn { background:rgba(255,255,255,.15); color:#fff; border:none; padding:8px 14px; border-radius:20px; cursor:pointer; font-size:14px; }
.level-badge,.score-badge { background:rgba(255,255,255,.15); color:#fff; padding:6px 14px; border-radius:20px; font-weight:700; font-size:14px; }
.center-box,.end-box { text-align:center; padding:40px 20px; }
.title { font-size:36px; color:#fff; font-weight:900; margin:10px 0; }
.subtitle { color:rgba(255,255,255,.8); font-size:16px; }
.level-preview { background:rgba(255,255,255,.1); color:#bfdbfe; padding:10px 24px; border-radius:20px; font-size:15px; font-weight:600; display:inline-block; margin:10px 0; }
.start-btn { background:#3b82f6; color:#fff; border:none; padding:14px 40px; border-radius:30px; font-size:20px; font-weight:800; cursor:pointer; margin:10px 6px; }
.home-btn { background:rgba(255,255,255,.2); color:#fff; border:none; padding:12px 28px; border-radius:30px; font-size:16px; font-weight:600; cursor:pointer; margin:10px 6px; }
.play-box { max-width:480px; margin:0 auto; }
.progress-row { display:flex; align-items:center; gap:10px; margin-bottom:20px; }
.progress-bar { flex:1; height:10px; background:rgba(255,255,255,.2); border-radius:5px; }
.progress-fill { height:100%; background:#93c5fd; border-radius:5px; transition:width .3s; }
.q-count { color:rgba(255,255,255,.8); font-size:13px; }
.equation-box { display:flex; align-items:center; justify-content:center; gap:12px; background:rgba(255,255,255,.15); border-radius:24px; padding:32px 20px; margin-bottom:16px; }
.eq-num { font-size:56px; font-weight:900; color:#fff; }
.eq-op { font-size:40px; font-weight:700; color:#93c5fd; }
.eq-ans { font-size:56px; font-weight:900; color:#fde68a; }
.mult-hint { text-align:center; color:rgba(255,255,255,.7); font-size:14px; margin-bottom:16px; }
.choices-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.choice-btn { background:rgba(255,255,255,.92); color:#1e3a5f; border:none; padding:20px; border-radius:16px; font-size:28px; font-weight:800; cursor:pointer; transition:all .15s; }
.choice-btn:hover:not(:disabled) { background:#fff; transform:scale(1.05); }
.choice-btn:disabled { cursor:default; }
.end-title { font-size:32px; color:#fff; font-weight:900; }
.end-score { color:rgba(255,255,255,.8); font-size:18px; }
.levelup-badge { background:#3b82f6; color:#fff; padding:10px 24px; border-radius:20px; font-weight:800; font-size:16px; display:inline-block; margin:14px 0; }
.feedback-overlay { position:fixed; inset:0; display:flex; align-items:center; justify-content:center; z-index:999; backdrop-filter:blur(4px); }
.fb-correct { background:rgba(16,185,129,.88); }
.fb-wrong { background:rgba(239,68,68,.88); }
.fb-content { text-align:center; color:#fff; padding:32px 48px; }
.fb-emoji { font-size:80px; margin-bottom:8px; }
.fb-title { font-size:34px; font-weight:900; margin-bottom:8px; }
.fb-answer { font-size:20px; margin-bottom:16px; }
.fb-bar-wrap { width:200px; height:7px; background:rgba(255,255,255,.3); border-radius:4px; margin:0 auto; }
.fb-bar { height:100%; background:#fff; border-radius:4px; transition:width .05s linear; }
.fb-enter-active,.fb-leave-active { transition:opacity .25s; }
.fb-enter-from,.fb-leave-to { opacity:0; }
</style>
