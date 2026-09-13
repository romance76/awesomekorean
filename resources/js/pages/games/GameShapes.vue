<template>
  <GameShell title="도형 맞추기" icon="🔷" theme="dark" :level="level" :score="score"
    bg="linear-gradient(135deg,#1e1b4b,#312e81,#4c1d95)">
  <div class="shapes-game">
    <div v-if="phase==='start'" class="center-box">
      <div style="font-size:90px">🔷</div>
      <h1 class="title">도형 맞추기</h1>
      <p class="subtitle">어떤 도형일까요?</p>
      <button class="start-btn" @click="startGame">시작하기 ▶</button>
    </div>
    <div v-if="phase==='play'" class="play-box">
      <div class="progress-bar"><div class="progress-fill" :style="{width: (qIdx/totalQ*100)+'%'}"></div></div>
      <div class="q-count">{{ qIdx+1 }} / {{ totalQ }}</div>
      <div class="shape-display">
        <svg viewBox="0 0 200 200" class="shape-svg">
          <polygon v-if="cur.shape==='triangle'" points="100,20 180,180 20,180"
            :fill="cur.color" stroke="white" stroke-width="4"/>
          <rect v-if="cur.shape==='square'" x="30" y="30" width="140" height="140"
            :fill="cur.color" stroke="white" stroke-width="4" rx="8"/>
          <circle v-if="cur.shape==='circle'" cx="100" cy="100" r="75"
            :fill="cur.color" stroke="white" stroke-width="4"/>
          <ellipse v-if="cur.shape==='oval'" cx="100" cy="100" rx="85" ry="55"
            :fill="cur.color" stroke="white" stroke-width="4"/>
          <polygon v-if="cur.shape==='pentagon'" points="100,15 185,70 155,170 45,170 15,70"
            :fill="cur.color" stroke="white" stroke-width="4"/>
          <polygon v-if="cur.shape==='star'" points="100,10 120,70 185,70 130,110 150,170 100,135 50,170 70,110 15,70 80,70"
            :fill="cur.color" stroke="white" stroke-width="4"/>
          <rect v-if="cur.shape==='rectangle'" x="20" y="60" width="160" height="80"
            :fill="cur.color" stroke="white" stroke-width="4" rx="6"/>
          <polygon v-if="cur.shape==='diamond'" points="100,15 185,100 100,185 15,100"
            :fill="cur.color" stroke="white" stroke-width="4"/>
        </svg>
      </div>
      <p class="question-text">이 도형의 이름은 무엇인가요?</p>
      <div class="choices">
        <button v-for="opt in cur.opts" :key="opt"
          class="choice-btn" :disabled="answered" @click="answer(opt)">
          {{ opt }}
        </button>
      </div>
    </div>
    <div v-if="phase==='end'" class="end-box">
      <div style="font-size:80px">🎉</div>
      <h2 class="end-title">훌륭해요!</h2>
      <p class="end-score">{{ score }}점 · {{ correct }}/{{ totalQ }} 정답</p>
      <div v-if="leveled" class="levelup-badge">🌟 레벨업! 레벨 {{ level }}!</div>
      <GameResultExtras :rec="rec" slug="shapes" />
      <button class="start-btn" @click="startGame">다시 하기 🔄</button>
    </div>

    <!-- 피드백 오버레이 -->
    <Transition name="fb">
      <div v-if="showFeedback" class="feedback-overlay" :class="lastCorrect?'fb-correct':'fb-wrong'">
        <div class="fb-content">
          <div class="fb-emoji">{{ lastCorrect ? '🎉' : '😢' }}</div>
          <div class="fb-title">{{ lastCorrect ? '정답이에요!' : '아쉬워요!' }}</div>
          <div v-if="!lastCorrect" class="fb-answer">정답은 「<strong>{{ cur?.korName }}</strong>」이에요</div>
          <div class="fb-bar-wrap"><div class="fb-bar" :style="{width:fbProgress+'%'}"></div></div>
        </div>
      </div>
    </Transition>
  </div>
  </GameShell>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import GameShell from '../../components/GameShell.vue'
import GameResultExtras from '../../components/GameResultExtras.vue'
import { useGameRecord } from '../../composables/useGameRecord'
const router = useRouter()
const rec = useGameRecord('shapes')
const level = ref(parseInt(localStorage.getItem('shapes_level')||'1'))
const score = ref(0)
const qIdx = ref(0)
const correct = ref(0)
const leveled = ref(false)
const answered = ref(false)
const phase = ref('start')
const showFeedback = ref(false)
const lastCorrect = ref(false)
const fbProgress = ref(100)
let fbTimer = null
const totalQ = 10

const allShapes = [
  {shape:'circle',    korName:'원',        opts:['원','삼각형','사각형','별']},
  {shape:'triangle',  korName:'삼각형',    opts:['삼각형','원','마름모','오각형']},
  {shape:'square',    korName:'사각형',    opts:['사각형','직사각형','원','삼각형']},
  {shape:'rectangle', korName:'직사각형',  opts:['직사각형','사각형','타원','별']},
  {shape:'oval',      korName:'타원',      opts:['타원','원','사각형','삼각형']},
  {shape:'pentagon',  korName:'오각형',    opts:['오각형','육각형','사각형','삼각형']},
  {shape:'star',      korName:'별',        opts:['별','오각형','삼각형','원']},
  {shape:'diamond',   korName:'마름모',    opts:['마름모','사각형','별','삼각형']},
]
const colors = ['#ef4444','#f97316','#eab308','#22c55e','#3b82f6','#8b5cf6','#ec4899']
const questions = ref([])
const cur = computed(() => questions.value[qIdx.value])

function speak(text) {
  if (!window.speechSynthesis) return
  window.speechSynthesis.cancel()
  const u = new SpeechSynthesisUtterance(text)
  u.lang='ko-KR'; u.rate=0.9
  window.speechSynthesis.speak(u)
}

function shuffle(arr) { return [...arr].sort(()=>Math.random()-.5) }

function buildQuestions() {
  const qs = []
  const pool = shuffle(allShapes)
  for (let i=0; i<totalQ; i++) {
    const s = pool[i % pool.length]
    qs.push({ ...s, color: colors[Math.floor(Math.random()*colors.length)], opts: shuffle(s.opts) })
  }
  return qs
}

function startGame() {
  score.value=0; qIdx.value=0; correct.value=0; leveled.value=false
  answered.value=false; showFeedback.value=false; phase.value='play'
  rec.start(level.value)
  questions.value = buildQuestions()
  speak('도형 이름을 맞춰보세요!')
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
      answered.value = false
      qIdx.value++
      if (qIdx.value >= totalQ) endGame()
    }
  }, 50)
}

function answer(opt) {
  if (answered.value) return
  answered.value = true
  const isCorrect = opt === cur.value.korName
  if (isCorrect) { score.value += 10; correct.value++ }
  speak(isCorrect ? '정답이에요!' : `정답은 ${cur.value.korName}이에요`)
  triggerFeedback(isCorrect)
}

async function endGame() {
  phase.value = 'end'
  const needed = Math.ceil(totalQ * 0.7)
  const won = correct.value >= needed
  if (won) {
    level.value++; localStorage.setItem('shapes_level', level.value)
    leveled.value = true
    speak('훌륭해요! 레벨업!')
  } else { speak('잘 했어요! 다시 도전해봐요!') }
  await rec.end({ won, leveledUp: leveled.value, score: score.value })
}
</script>

<style scoped>
.shapes-game { flex:1; padding:16px; font-family:'Noto Sans KR',sans-serif; }
.center-box,.end-box { text-align:center; padding:40px 20px; }
.title { font-size:36px; color:#fff; font-weight:900; margin:10px 0; }
.subtitle { color:rgba(255,255,255,.8); font-size:16px; }
.start-btn { background:#a855f7; color:#fff; border:none; padding:14px 40px; border-radius:30px; font-size:20px; font-weight:800; cursor:pointer; margin-top:20px; }
.play-box { max-width:480px; margin:0 auto; }
.progress-bar { height:8px; background:rgba(255,255,255,.2); border-radius:4px; margin-bottom:8px; }
.progress-fill { height:100%; background:#a855f7; border-radius:4px; transition:width .3s; }
.q-count { color:rgba(255,255,255,.7); font-size:13px; text-align:right; margin-bottom:16px; }
.shape-display { background:rgba(255,255,255,.1); border-radius:20px; padding:20px; margin-bottom:20px; }
.shape-svg { width:180px; height:180px; display:block; margin:0 auto; }
.question-text { color:#fff; font-size:18px; font-weight:700; text-align:center; margin-bottom:16px; }
.choices { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.choice-btn { background:rgba(255,255,255,.9); color:#1e1b4b; border:none; padding:16px; border-radius:14px; font-size:17px; font-weight:700; cursor:pointer; transition:all .15s; }
.choice-btn:hover:not(:disabled) { background:#fff; transform:scale(1.03); }
.choice-btn:disabled { cursor:default; opacity:.8; }
.end-title { font-size:32px; color:#fff; font-weight:900; }
.end-score { color:rgba(255,255,255,.8); font-size:18px; }
.levelup-badge { background:#a855f7; color:#fff; padding:10px 24px; border-radius:20px; font-weight:800; font-size:16px; display:inline-block; margin:14px 0; }
.feedback-overlay { position:fixed; inset:0; display:flex; align-items:center; justify-content:center; z-index:999; backdrop-filter:blur(4px); }
.fb-correct { background:rgba(16,185,129,.85); }
.fb-wrong { background:rgba(239,68,68,.85); }
.fb-content { text-align:center; color:#fff; padding:32px 48px; }
.fb-emoji { font-size:72px; margin-bottom:8px; }
.fb-title { font-size:32px; font-weight:900; margin-bottom:8px; }
.fb-answer { font-size:18px; margin-bottom:16px; }
.fb-bar-wrap { width:200px; height:6px; background:rgba(255,255,255,.3); border-radius:4px; margin:0 auto; }
.fb-bar { height:100%; background:#fff; border-radius:4px; transition:width .05s linear; }
.fb-enter-active,.fb-leave-active { transition:opacity .25s; }
.fb-enter-from,.fb-leave-to { opacity:0; }
</style>
