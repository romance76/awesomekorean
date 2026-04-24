<template>
  <div class="math-game">
    <div class="game-header">
      <button class="back-btn" @click="$router.push('/games')">← 뒤로</button>
      <div class="level-badge">레벨 {{ level }} ⭐</div>
      <div class="score-badge">{{ score }}점</div>
    </div>

    <!-- 시작 화면 -->
    <div v-if="phase==='start'" class="center-box">
      <div style="font-size:90px">🔢</div>
      <h1 class="title">기초 수학</h1>
      <p class="subtitle">덧셈과 뺄셈을 배워요!</p>
      <div class="level-preview">
        <span v-if="level<=2">⊕ 1~9 더하기 빼기</span>
        <span v-else-if="level<=4">⊕ 10~20 범위 계산</span>
        <span v-else>⊕ 받아올림/받아내림</span>
      </div>
      <button class="start-btn" @click="startGame">시작하기 ▶</button>
    </div>

    <!-- 게임 화면 -->
    <div v-if="phase==='play'" class="play-box">
      <div class="progress-row">
        <div class="progress-bar"><div class="progress-fill" :style="{width:(qIdx/totalQ*100)+'%'}"></div></div>
        <span class="q-count">{{ qIdx+1 }}/{{ totalQ }}</span>
      </div>

      <!-- 시각적 수 표현 (레벨1) -->
      <div v-if="level<=1 && cur" class="visual-row">
        <span v-for="i in cur.a" :key="'a'+i" class="dot">●</span>
        <span class="op-label">{{ cur.op }}</span>
        <span v-for="i in (cur.op==='+'?cur.b:0)" :key="'b'+i" class="dot green-dot">●</span>
      </div>

      <div v-if="cur" class="equation-box">
        <span class="eq-num">{{ cur.a }}</span>
        <span class="eq-op">{{ cur.op }}</span>
        <span class="eq-num">{{ cur.b }}</span>
        <span class="eq-op">=</span>
        <span class="eq-ans">?</span>
      </div>

      <div class="choices-grid">
        <button v-for="opt in cur?.opts" :key="opt"
          class="choice-btn" :disabled="answered" @click="answer(opt)">
          {{ opt }}
        </button>
      </div>

      <div class="streak-row" v-if="streak>=2">
        🔥 {{ streak }}연속 정답!
      </div>
    </div>

    <!-- 결과 화면 -->
    <div v-if="phase==='end'" class="end-box">
      <div style="font-size:80px">{{ correct>=totalQ*0.7?'🏆':'💪' }}</div>
      <h2 class="end-title">{{ correct>=totalQ*0.7?'훌륭해요!':'잘 했어요!' }}</h2>
      <p class="end-score">{{ score }}점 · {{ correct }}/{{ totalQ }} 정답</p>
      <div v-if="leveled" class="levelup-badge">🌟 레벨업! 레벨 {{ level }}!</div>
      <GameResultExtras :rec="rec" slug="math_basic" />
      <button class="start-btn" @click="startGame">다시 하기 🔄</button>
      <button class="home-btn" @click="$router.push('/games')">홈으로 🏠</button>
    </div>

    <!-- 피드백 오버레이 -->
    <Transition name="fb">
      <div v-if="showFeedback" class="feedback-overlay" :class="lastCorrect?'fb-correct':'fb-wrong'">
        <div class="fb-content">
          <div class="fb-emoji">{{ lastCorrect ? (streak>=3?'🔥':'🎉') : '😢' }}</div>
          <div class="fb-title">{{ lastCorrect ? (streak>=3?streak+'연속 정답!':'정답이에요!') : '아쉬워요!' }}</div>
          <div v-if="!lastCorrect && cur" class="fb-answer">
            정답은 <strong>{{ cur.a }} {{ cur.op }} {{ cur.b }} = {{ cur.ans }}</strong>
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
const rec = useGameRecord('math_basic')
const level = ref(parseInt(localStorage.getItem('mathbasic_level')||'1'))
const score = ref(0); const qIdx = ref(0); const correct = ref(0)
const streak = ref(0); const leveled = ref(false); const answered = ref(false)
const phase = ref('start')
const showFeedback = ref(false); const lastCorrect = ref(false); const fbProgress = ref(100)
let fbTimer = null
const totalQ = 10
const questions = ref([])
const cur = computed(() => questions.value[qIdx.value])

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
  let a, b, op, ans
  if (lv <= 2) { a=rand(1,9); b=rand(1,Math.min(a,9)); op=Math.random()>.5?'+':'-'; ans=op==='+'?a+b:a-b }
  else if (lv <= 4) { a=rand(5,15); b=rand(1,10); op=Math.random()>.5?'+':'-'; if(op==='-')b=rand(1,a); ans=op==='+'?a+b:a-b }
  else { a=rand(10,30); b=rand(5,20); op=Math.random()>.5?'+':'-'; if(op==='-')b=rand(1,a); ans=op==='+'?a+b:a-b }
  const wrongs = new Set()
  while(wrongs.size<3){ const w=ans+rand(-5,5); if(w!==ans&&w>=0)wrongs.add(w) }
  return { a, b, op, ans, opts: shuffle([ans,...wrongs]) }
}

function startGame() {
  score.value=0; qIdx.value=0; correct.value=0; streak.value=0; leveled.value=false
  answered.value=false; showFeedback.value=false; phase.value='play'
  rec.start(level.value)
  questions.value = Array.from({length:totalQ}, genQ)
  speak('수학 문제를 풀어봐요!')
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
  if (isOk) {
    score.value += 10 + (streak.value >= 2 ? 5 : 0)
    correct.value++; streak.value++
    speak(streak.value >= 3 ? `${streak.value}연속 정답!` : '정답이에요!')
  } else {
    streak.value = 0
    speak(`아쉬워요! 정답은 ${cur.value.ans}이에요`)
  }
  triggerFeedback(isOk)
}

async function endGame() {
  phase.value = 'end'
  const passed = correct.value >= Math.ceil(totalQ * 0.7)
  if (passed) {
    level.value++; localStorage.setItem('mathbasic_level', level.value); leveled.value = true
    speak('훌륭해요! 레벨업!')
  } else speak('잘 했어요! 다시 도전해봐요!')
  await rec.end({ won: passed, leveledUp: leveled.value, score: score.value })
  // 점수 저장
  const token = localStorage.getItem('token')
  if (token) {
    try {
      await axios.post('/api/games/8/score',
        { score: score.value, level: level.value, result: passed?'win':'lose', duration: totalQ*5 },
        { headers: { Authorization: `Bearer ${token}` } })
    } catch(e) {}
  }
}
</script>

<style scoped>
.math-game { min-height:100vh; background:linear-gradient(135deg,#134e4a,#0f766e,#0d9488); padding:16px; font-family:'Noto Sans KR',sans-serif; }
.game-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
.back-btn { background:rgba(255,255,255,.15); color:#fff; border:none; padding:8px 14px; border-radius:20px; cursor:pointer; font-size:14px; }
.level-badge,.score-badge { background:rgba(255,255,255,.15); color:#fff; padding:6px 14px; border-radius:20px; font-weight:700; font-size:14px; }
.center-box,.end-box { text-align:center; padding:40px 20px; }
.title { font-size:36px; color:#fff; font-weight:900; margin:10px 0; }
.subtitle { color:rgba(255,255,255,.8); font-size:16px; }
.level-preview { background:rgba(255,255,255,.1); color:rgba(255,255,255,.9); padding:10px 20px; border-radius:20px; font-size:14px; margin:12px auto; display:inline-block; }
.start-btn { background:#14b8a6; color:#fff; border:none; padding:14px 40px; border-radius:30px; font-size:20px; font-weight:800; cursor:pointer; margin:10px 6px; }
.home-btn { background:rgba(255,255,255,.2); color:#fff; border:none; padding:12px 28px; border-radius:30px; font-size:16px; font-weight:600; cursor:pointer; margin:10px 6px; }
.play-box { max-width:480px; margin:0 auto; }
.progress-row { display:flex; align-items:center; gap:10px; margin-bottom:16px; }
.progress-bar { flex:1; height:10px; background:rgba(255,255,255,.2); border-radius:5px; }
.progress-fill { height:100%; background:#5eead4; border-radius:5px; transition:width .3s; }
.q-count { color:rgba(255,255,255,.8); font-size:13px; white-space:nowrap; }
.visual-row { display:flex; flex-wrap:wrap; gap:4px; justify-content:center; align-items:center; margin-bottom:12px; min-height:36px; background:rgba(255,255,255,.1); border-radius:14px; padding:10px; }
.dot { font-size:18px; color:#fef3c7; }
.green-dot { color:#86efac; }
.op-label { font-size:20px; color:#fff; font-weight:700; margin:0 8px; }
.equation-box { display:flex; align-items:center; justify-content:center; gap:16px; background:rgba(255,255,255,.15); border-radius:20px; padding:28px 20px; margin-bottom:24px; }
.eq-num { font-size:52px; font-weight:900; color:#fff; }
.eq-op { font-size:36px; font-weight:700; color:#5eead4; }
.eq-ans { font-size:52px; font-weight:900; color:#fde68a; }
.choices-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.choice-btn { background:rgba(255,255,255,.92); color:#134e4a; border:none; padding:20px; border-radius:16px; font-size:26px; font-weight:800; cursor:pointer; transition:all .15s; box-shadow:0 4px 10px rgba(0,0,0,.15); }
.choice-btn:hover:not(:disabled) { background:#fff; transform:scale(1.05); }
.choice-btn:disabled { cursor:default; }
.streak-row { text-align:center; color:#fde68a; font-size:15px; font-weight:700; margin-top:12px; }
.end-title { font-size:32px; color:#fff; font-weight:900; }
.end-score { color:rgba(255,255,255,.8); font-size:18px; }
.levelup-badge { background:#14b8a6; color:#fff; padding:10px 24px; border-radius:20px; font-weight:800; font-size:16px; display:inline-block; margin:14px 0; }
.feedback-overlay { position:fixed; inset:0; display:flex; align-items:center; justify-content:center; z-index:999; backdrop-filter:blur(4px); }
.fb-correct { background:rgba(16,185,129,.88); }
.fb-wrong { background:rgba(239,68,68,.88); }
.fb-content { text-align:center; color:#fff; padding:32px 48px; }
.fb-emoji { font-size:80px; margin-bottom:8px; }
.fb-title { font-size:34px; font-weight:900; margin-bottom:8px; }
.fb-answer { font-size:18px; margin-bottom:16px; opacity:.95; }
.fb-bar-wrap { width:200px; height:7px; background:rgba(255,255,255,.3); border-radius:4px; margin:0 auto; }
.fb-bar { height:100%; background:#fff; border-radius:4px; transition:width .05s linear; }
.fb-enter-active,.fb-leave-active { transition:opacity .25s; }
.fb-enter-from,.fb-leave-to { opacity:0; }
</style>
