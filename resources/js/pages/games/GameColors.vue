<template>
  <div class="colors-game">
    <div class="game-header">
      <button class="back-btn" @click="goBack">← 뒤로</button>
      <div class="level-badge">레벨 {{ level }} 🎨</div>
      <div class="score-badge">⭐ {{ score }}점</div>
    </div>

    <div v-if="phase==='start'" class="start-screen">
      <div style="font-size:80px">🎨</div>
      <h1 class="game-title">색깔 맞추기</h1>
      <p class="game-desc">화면에 나타나는 색깔 이름을 맞춰요!</p>
      <button class="play-btn" @click="startGame">시작하기! 🚀</button>
    </div>

    <div v-if="phase==='play'" class="play-screen">
      <div class="progress-row">
        <div class="q-num">{{ qIdx + 1 }} / {{ totalQ }}</div>
        <div class="prog-wrap"><div class="prog-bar" :style="{width:(qIdx+1)/totalQ*100+'%'}"></div></div>
        <div class="streak" v-if="streak>1">🔥{{ streak }}</div>
      </div>

      <div class="color-display" :style="{background: curColor.hex}">
        <div class="color-question">무슨 색깔일까요?</div>
      </div>

      <div class="choices-grid">
        <button v-for="opt in choices" :key="opt.name"
          class="color-choice"
          :style="{borderColor: opt.hex}"
          :class="{
            correct: showFeedback && opt.name===curColor.name,
            wrong: showFeedback && opt.name===selectedName && opt.name!==curColor.name
          }"
          :disabled="selectedName!==null"
          @click="selectAnswer(opt)">
          <div class="color-dot" :style="{background:opt.hex}"></div>
          <span>{{ opt.name }}</span>
        </button>
      </div>
    </div>

    <!-- 피드백 오버레이 -->
    <Transition name="fb">
      <div v-if="showFeedback" class="feedback-overlay" :class="lastCorrect ? 'fb-correct' : 'fb-wrong'">
        <div class="fb-content">
          <div class="fb-emoji">{{ lastCorrect ? '🎉' : '😢' }}</div>
          <div class="fb-title">{{ lastCorrect ? '정답이에요!' : '오답이에요!' }}</div>
          <div v-if="!lastCorrect" class="fb-answer">정답은 「<strong>{{ curColor.name }}</strong>」이에요</div>
          <div class="fb-bar-wrap"><div class="fb-bar" :style="{width:fbProgress+'%'}"></div></div>
        </div>
      </div>
    </Transition>

    <div v-if="phase==='result'" class="result-screen">
      <div style="font-size:80px">🎊</div>
      <div class="result-score">{{ score }}점</div>
      <div class="result-detail">{{ correct }} / {{ totalQ }} 정답</div>
      <div v-if="leveled" class="level-up-badge">🎉 레벨업! → 레벨 {{ level }}</div>
      <GameResultExtras :rec="rec" slug="colors" />
      <div class="result-btns">
        <button class="rbtn retry" @click="startGame">다시 하기 🔄</button>
        <button class="rbtn home" @click="goBack">목록으로 🏠</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import GameResultExtras from '../../components/GameResultExtras.vue'
import { useGameRecord } from '../../composables/useGameRecord'
const router = useRouter()
const rec = useGameRecord('colors')

const allColors = [
  {name:'빨간색', hex:'#ef4444'},
  {name:'파란색', hex:'#3b82f6'},
  {name:'초록색', hex:'#10b981'},
  {name:'노란색', hex:'#fbbf24'},
  {name:'보라색', hex:'#8b5cf6'},
  {name:'주황색', hex:'#f97316'},
  {name:'분홍색', hex:'#ec4899'},
  {name:'하늘색', hex:'#38bdf8'},
  {name:'갈색', hex:'#92400e'},
  {name:'회색', hex:'#6b7280'},
]

const level = ref(parseInt(localStorage.getItem('color_level') || '1'))
const score = ref(0)
const correct = ref(0)
const streak = ref(0)
const phase = ref('start')
const qIdx = ref(0)
const totalQ = ref(12)
const curColor = ref({name:'',hex:''})
const choices = ref([])
const selectedName = ref(null)
const showFeedback = ref(false)
const lastCorrect = ref(false)
const fbProgress = ref(100)
const leveled = ref(false)
let fbTimer = null
let queue = []

function getPool() {
  if (level.value <= 2) return allColors.slice(0, 4)
  if (level.value <= 4) return allColors.slice(0, 6)
  return allColors
}

function speak(text) {
  if (!window.speechSynthesis) return
  window.speechSynthesis.cancel()
  const u = new SpeechSynthesisUtterance(text)
  u.lang = 'ko-KR'; u.rate = 0.9; u.pitch = 1.1
  window.speechSynthesis.speak(u)
}

function shuffle(a){const r=[...a];for(let i=r.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[r[i],r[j]]=[r[j],r[i]]}return r}

function startGame() {
  score.value=0; correct.value=0; streak.value=0; leveled.value=false; qIdx.value=0
  queue = shuffle(getPool()).slice(0, totalQ.value)
  rec.start(level.value)
  phase.value='play'; loadQuestion()
}

function loadQuestion() {
  if (qIdx.value >= totalQ.value) { endGame(); return }
  curColor.value = queue[qIdx.value]
  selectedName.value = null; showFeedback.value = false
  const pool = getPool()
  const wrongs = shuffle(pool.filter(c => c.name !== curColor.value.name)).slice(0, 3)
  choices.value = shuffle([curColor.value, ...wrongs])
  speak('무슨 색깔일까요?')
}

function selectAnswer(opt) {
  if (selectedName.value !== null) return
  selectedName.value = opt.name
  const isCorrect = opt.name === curColor.value.name
  if (isCorrect) {
    correct.value++; streak.value++
    score.value += 10 + (streak.value > 1 ? streak.value * 2 : 0)
    speak(opt.name + '! 정답이에요!')
  } else {
    streak.value = 0
    speak('아쉬워요! 정답은 ' + curColor.value.name)
  }
  triggerFeedback(isCorrect)
}

function triggerFeedback(isCorrect) {
  lastCorrect.value = isCorrect
  showFeedback.value = true
  fbProgress.value = 100
  clearInterval(fbTimer)
  const duration = isCorrect ? 1400 : 2000
  const step = 50 / duration * 100
  fbTimer = setInterval(() => {
    fbProgress.value = Math.max(0, fbProgress.value - step)
    if (fbProgress.value <= 0) {
      clearInterval(fbTimer); showFeedback.value = false
      qIdx.value++; loadQuestion()
    }
  }, 50)
}

async function endGame() {
  phase.value = 'result'
  const won = correct.value >= 9
  if (won) {
    level.value++; localStorage.setItem('color_level', level.value)
    leveled.value = true; speak('레벨업!')
  }
  await rec.end({ won, leveledUp: leveled.value, score: score.value })
}

function goBack() { clearInterval(fbTimer); router.push('/games') }
</script>

<style scoped>
.colors-game { min-height:100vh; background:linear-gradient(160deg,#fff7ed,#ffedd5,#fed7aa); font-family:'Noto Sans KR',sans-serif; }
.game-header { display:flex; justify-content:space-between; align-items:center; padding:12px 16px; background:rgba(255,255,255,0.7); backdrop-filter:blur(10px); border-bottom:1px solid rgba(255,255,255,0.5); }
.back-btn { background:#f97316; color:#fff; border:none; padding:8px 14px; border-radius:20px; cursor:pointer; font-size:14px; font-weight:600; }
.level-badge,.score-badge { background:#f97316; color:#fff; padding:6px 14px; border-radius:20px; font-weight:700; }
.start-screen { display:flex; flex-direction:column; align-items:center; padding:40px 20px; text-align:center; }
.game-title { font-size:32px; font-weight:900; color:#9a3412; margin:10px 0; }
.game-desc { color:#c2410c; font-size:16px; margin-bottom:20px; }
.play-btn { background:linear-gradient(135deg,#f97316,#ea580c); color:#fff; border:none; padding:16px 48px; border-radius:30px; font-size:20px; font-weight:800; cursor:pointer; }
.play-screen { padding:16px; max-width:480px; margin:0 auto; }
.progress-row { display:flex; align-items:center; gap:10px; margin-bottom:16px; }
.q-num { font-size:14px; font-weight:700; color:#9a3412; }
.prog-wrap { flex:1; height:8px; background:rgba(0,0,0,0.1); border-radius:4px; overflow:hidden; }
.prog-bar { height:100%; background:#f97316; border-radius:4px; transition:width 0.3s; }
.streak { font-size:14px; font-weight:700; color:#f97316; }
.color-display { height:200px; border-radius:24px; display:flex; align-items:center; justify-content:center; margin-bottom:20px; box-shadow:0 8px 32px rgba(0,0,0,0.15); }
.color-question { font-size:22px; font-weight:700; color:rgba(255,255,255,0.95); text-shadow:0 2px 8px rgba(0,0,0,0.3); }
.choices-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.color-choice { background:#fff; border:3px solid transparent; border-radius:16px; padding:14px; display:flex; align-items:center; gap:10px; cursor:pointer; font-size:17px; font-weight:700; color:#374151; transition:all 0.2s; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
.color-choice:hover:not([disabled]) { transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,0.12); }
.color-dot { width:28px; height:28px; border-radius:50%; flex-shrink:0; }
.color-choice.correct { border-color:#10b981; background:#d1fae5; }
.color-choice.wrong { border-color:#ef4444; background:#fee2e2; }
.feedback-overlay { position:fixed; inset:0; z-index:100; display:flex; align-items:center; justify-content:center; }
.fb-correct { background:rgba(16,185,129,0.92); }
.fb-wrong { background:rgba(239,68,68,0.92); }
.fb-content { text-align:center; padding:32px 40px; border-radius:24px; background:rgba(255,255,255,0.15); }
.fb-emoji { font-size:72px; }
.fb-title { font-size:32px; font-weight:900; color:#fff; margin:8px 0; }
.fb-answer { font-size:17px; color:rgba(255,255,255,0.9); margin-bottom:12px; }
.fb-answer strong { font-size:20px; }
.fb-bar-wrap { width:200px; height:6px; background:rgba(255,255,255,0.3); border-radius:3px; overflow:hidden; margin:0 auto; }
.fb-bar { height:100%; background:#fff; border-radius:3px; transition:width 0.05s linear; }
.fb-enter-active,.fb-leave-active { transition:opacity 0.2s,transform 0.2s; }
.fb-enter-from,.fb-leave-to { opacity:0; transform:scale(0.95); }
.result-screen { display:flex; flex-direction:column; align-items:center; padding:40px 20px; text-align:center; }
.result-score { font-size:56px; font-weight:900; color:#ea580c; }
.result-detail { color:#9a3412; font-size:18px; margin:6px 0 16px; }
.level-up-badge { background:#f97316; color:#fff; padding:10px 24px; border-radius:20px; font-size:18px; font-weight:800; margin-bottom:20px; }
.result-btns { display:flex; gap:12px; }
.rbtn { padding:14px 28px; border-radius:20px; border:none; font-size:16px; font-weight:700; cursor:pointer; }
.rbtn.retry { background:#f97316; color:#fff; }
.rbtn.home { background:#fff; color:#9a3412; border:2px solid #f97316; }
</style>
