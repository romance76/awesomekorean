<template>
<GameShell title="사자성어 퀴즈" icon="📜" :level="level" :score="score"
  bg="linear-gradient(160deg,#fef3c7 0%,#fde68a 50%,#fcd34d 100%)">

  <!-- 시작 화면 -->
  <div v-if="phase==='start'" class="start-screen">
    <div class="start-scroll">📜</div>
    <h1 class="game-title">사자성어 퀴즈</h1>
    <p class="game-desc">뜻 풀이를 보고 알맞은 사자성어를 골라주세요</p>
    <div class="level-card">
      <div class="lv-row" v-for="lv in levelDesc" :key="lv.lv" :class="{active: level>=lv.lv}">
        <span class="lv-badge">Lv.{{ lv.lv }}</span>
        <span>{{ lv.desc }}</span>
      </div>
    </div>
    <button class="play-btn" @click="startGame" :disabled="loadingPool">
      {{ loadingPool ? '불러오는 중...' : '게임 시작! 🎮' }}
    </button>
  </div>

  <!-- 게임 화면 -->
  <div v-if="phase==='play'" class="play-screen">
    <div class="play-header">
      <div class="q-counter">{{ qIdx + 1 }} / {{ totalQ }}</div>
      <div class="timer-ring" v-if="maxTime > 0">
        <svg width="48" height="48" viewBox="0 0 48 48">
          <circle cx="24" cy="24" r="20" fill="none" stroke="rgba(0,0,0,0.1)" stroke-width="4"/>
          <circle cx="24" cy="24" r="20" fill="none" :stroke="timeLeft<=3?'#f87171':'#d97706'" stroke-width="4"
            :stroke-dasharray="125.6" :stroke-dashoffset="125.6*(1-timeLeft/maxTime)"
            transform="rotate(-90 24 24)" stroke-linecap="round"/>
        </svg>
        <span class="timer-text">{{ timeLeft }}</span>
      </div>
      <div class="streak-badge" v-if="streak>1">🔥 {{ streak }}연속</div>
    </div>

    <div class="hint-card">
      <div class="hint-label">다음 뜻에 해당하는 사자성어는?</div>
      <div class="hint-text">" {{ curIdiom.hint }} "</div>
    </div>

    <div class="choices-col">
      <button v-for="(opt, i) in choices" :key="opt"
        class="choice-card"
        :class="{
          selected: selectedOpt===opt && !showFeedback,
          correct: showFeedback && opt===curIdiom.answer,
          wrong: showFeedback && opt===selectedOpt && opt!==curIdiom.answer
        }"
        :disabled="selectedOpt!==null"
        @click="selectAnswer(opt)">
        <span class="choice-num">{{ ['A','B','C','D'][i] }}</span>
        <span class="choice-name">{{ opt }}</span>
      </button>
    </div>
  </div>

  <!-- 피드백 -->
  <Transition name="fb">
    <div v-if="showFeedback" class="feedback-overlay" :class="lastCorrect ? 'fb-correct' : 'fb-wrong'">
      <div class="fb-content">
        <div class="fb-emoji">{{ lastCorrect ? '🎉' : '😢' }}</div>
        <div class="fb-title">{{ lastCorrect ? '정답이에요!' : '아쉬워요!' }}</div>
        <div class="fb-answer">정답은 「<strong>{{ curIdiom?.answer }}</strong>」입니다</div>
        <div class="fb-hint" v-if="!lastCorrect">{{ curIdiom?.hint }}</div>
        <div class="fb-bar-wrap"><div class="fb-bar" :style="{width:fbProgress+'%'}"></div></div>
      </div>
    </div>
  </Transition>

  <!-- 결과 -->
  <div v-if="phase==='result'" class="result-screen">
    <div class="result-img">
      <img :src="`https://fonts.gstatic.com/s/e/notoemoji/latest/${correct>=8?'1f3c6':'1f44f'}/512.png`"
        alt="trophy" style="width:100px;height:100px"/>
    </div>
    <div class="result-score">{{ score }}점</div>
    <div class="result-detail">{{ correct }} / {{ totalQ }} 정답</div>
    <div v-if="leveled" class="level-up-badge">🎉 레벨업! → 레벨 {{ level }}</div>
    <GameResultExtras :rec="rec" slug="idiom" />
    <div class="result-btns">
      <button class="rbtn retry" @click="startGame">다시 하기 🔄</button>
      <button class="rbtn home" @click="$router.push('/games')">목록으로 🏠</button>
    </div>
  </div>
</GameShell>
</template>

<script setup>
import { ref, onUnmounted } from 'vue'
import axios from 'axios'
import GameShell from '../../components/GameShell.vue'
import GameResultExtras from '../../components/GameResultExtras.vue'
import { useGameRecord } from '../../composables/useGameRecord'
const rec = useGameRecord('idiom')

const levelDesc = [
  {lv:1, desc:'기초 사자성어 (8개)'},
  {lv:2, desc:'기본 사자성어 (16개)'},
  {lv:3, desc:'중급 사자성어 (24개)'},
  {lv:4, desc:'고급 + 제한시간'},
  {lv:5, desc:'마스터 + 짧은 시간'},
]

const level = ref(parseInt(localStorage.getItem('idiom_level') || '1'))
const score = ref(0)
const correct = ref(0)
const streak = ref(0)
const phase = ref('start')
const qIdx = ref(0)
const totalQ = ref(10)
const curIdiom = ref(null)
const choices = ref([])
const selectedOpt = ref(null)
const showFeedback = ref(false)
const lastCorrect = ref(false)
const fbProgress = ref(100)
const leveled = ref(false)
const timeLeft = ref(0)
const maxTime = ref(0)
const pool = ref([])
const loadingPool = ref(false)
let fbTimer = null
let countTimer = null
let queue = []

async function loadPool() {
  loadingPool.value = true
  try {
    const { data } = await axios.get(`/api/quiz/idiom`, {
      params: { level: level.value, level_mode: 'lte', limit: 100 }
    })
    pool.value = data.data || []
  } catch { pool.value = [] }
  loadingPool.value = false
}

function shuffle(a){const r=[...a];for(let i=r.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[r[i],r[j]]=[r[j],r[i]]}return r}

async function startGame() {
  if (!pool.value.length) await loadPool()
  if (!pool.value.length) { alert('문제가 없습니다'); return }
  score.value = 0; correct.value = 0; streak.value = 0; leveled.value = false
  qIdx.value = 0
  maxTime.value = level.value >= 4 ? (level.value >= 5 ? 8 : 12) : 0
  queue = shuffle(pool.value).slice(0, Math.min(totalQ.value, pool.value.length))
  totalQ.value = queue.length
  rec.start(level.value)
  phase.value = 'play'
  loadQuestion()
}

function loadQuestion() {
  if (qIdx.value >= totalQ.value) { endGame(); return }
  const q = queue[qIdx.value]
  curIdiom.value = q
  selectedOpt.value = null
  showFeedback.value = false

  let wrongs = []
  if (q.wrongs && q.wrongs.length >= 3) {
    wrongs = shuffle(q.wrongs).slice(0, 3)
  } else {
    wrongs = shuffle(pool.value.filter(a => a.answer !== q.answer).map(a => a.answer)).slice(0, 3)
  }
  choices.value = shuffle([q.answer, ...wrongs])

  clearInterval(countTimer)
  if (maxTime.value > 0) {
    timeLeft.value = maxTime.value
    countTimer = setInterval(() => {
      timeLeft.value--
      if (timeLeft.value <= 0) { clearInterval(countTimer); autoFail() }
    }, 1000)
  }
}

function autoFail() { selectedOpt.value = '__timeout__'; triggerFeedback(false) }

function selectAnswer(opt) {
  if (selectedOpt.value !== null) return
  clearInterval(countTimer)
  selectedOpt.value = opt
  const isCorrect = opt === curIdiom.value.answer
  if (isCorrect) {
    correct.value++; streak.value++
    score.value += 10 + (streak.value > 1 ? streak.value * 3 : 0) + (maxTime.value > 0 ? timeLeft.value * 2 : 0)
  } else {
    streak.value = 0
  }
  triggerFeedback(isCorrect)
}

function triggerFeedback(isCorrect) {
  lastCorrect.value = isCorrect
  showFeedback.value = true
  fbProgress.value = 100
  clearInterval(fbTimer)
  const duration = isCorrect ? 1400 : 2500
  const step = 50 / duration * 100
  fbTimer = setInterval(() => {
    fbProgress.value = Math.max(0, fbProgress.value - step)
    if (fbProgress.value <= 0) {
      clearInterval(fbTimer)
      showFeedback.value = false
      qIdx.value++
      loadQuestion()
    }
  }, 50)
}

async function endGame() {
  phase.value = 'result'
  const threshold = level.value <= 1 ? 7 : level.value <= 2 ? 8 : 9
  const won = correct.value >= threshold
  if (won && level.value < 5) {
    level.value++
    localStorage.setItem('idiom_level', level.value)
    leveled.value = true
    loadPool()
  }
  await rec.end({ won, leveledUp: leveled.value, score: score.value })
}

onUnmounted(() => { clearInterval(fbTimer); clearInterval(countTimer) })
loadPool()
</script>

<style scoped>
.start-screen { display:flex; flex-direction:column; align-items:center; padding:30px 20px; }
.start-scroll { font-size:80px; margin-bottom:10px; filter: drop-shadow(0 4px 8px rgba(180,83,9,0.3)); }
.game-title { font-size:30px; font-weight:900; color:#78350f; margin:8px 0; }
.game-desc { color:#92400e; font-size:15px; margin-bottom:16px; text-align:center; max-width: 320px; }
.level-card { background:rgba(255,255,255,0.85); border-radius:16px; padding:14px 20px; width:100%; max-width:360px; margin-bottom:20px; }
.lv-row { display:flex; align-items:center; gap:10px; padding:6px 0; color:#374151; font-size:14px; border-bottom:1px solid #fde68a; }
.lv-row:last-child { border-bottom:none; }
.lv-row.active { color:#78350f; font-weight:700; }
.lv-badge { background:#d97706; color:#fff; font-size:11px; font-weight:700; padding:2px 8px; border-radius:10px; min-width:36px; text-align:center; }
.play-btn { background:linear-gradient(135deg,#d97706,#92400e); color:#fff; border:none; padding:16px 48px; border-radius:30px; font-size:20px; font-weight:800; cursor:pointer; box-shadow:0 4px 20px rgba(217,119,6,0.4); }
.play-btn:disabled { opacity: 0.6; cursor: default; }

.play-screen { padding:12px 16px; max-width:560px; margin:0 auto; width:100%; }
.play-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; }
.q-counter { background:rgba(255,255,255,0.85); padding:6px 14px; border-radius:20px; font-size:15px; font-weight:700; color:#78350f; }
.timer-ring { position:relative; width:48px; height:48px; }
.timer-text { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); font-size:13px; font-weight:800; color:#374151; }
.streak-badge { background:#f59e0b; color:#fff; padding:6px 12px; border-radius:20px; font-size:13px; font-weight:700; }

.hint-card { background:rgba(255,255,255,0.95); border-radius:20px; padding:28px 24px; text-align:center; margin-bottom:16px; box-shadow:0 8px 32px rgba(180,83,9,0.15); border-left: 4px solid #d97706; }
.hint-label { font-size:12px; color:#92400e; font-weight:700; margin-bottom:10px; letter-spacing: 0.3px; }
.hint-text { font-size:22px; font-weight:800; color:#451a03; line-height:1.5; font-family:'Noto Serif KR', serif; }

.choices-col { display:flex; flex-direction:column; gap:10px; }
.choice-card { background:rgba(255,255,255,0.95); border:2px solid transparent; border-radius:14px; padding:16px 18px; display:flex; align-items:center; gap:12px; cursor:pointer; transition:all 0.2s; box-shadow:0 2px 10px rgba(0,0,0,0.06); }
.choice-card:hover:not([disabled]) { transform:translateY(-2px); border-color:#d97706; box-shadow:0 6px 20px rgba(217,119,6,0.2); }
.choice-num { width:32px; height:32px; border-radius:50%; background:#d97706; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:14px; flex-shrink:0; }
.choice-name { font-size:20px; font-weight:800; color:#451a03; font-family:'Noto Serif KR', serif; letter-spacing:1px; }
.choice-card.correct { border-color:#10b981; background:#d1fae5; }
.choice-card.correct .choice-num { background:#059669; }
.choice-card.wrong { border-color:#ef4444; background:#fee2e2; }
.choice-card.wrong .choice-num { background:#dc2626; }

.feedback-overlay { position:fixed; inset:0; z-index:100; display:flex; align-items:center; justify-content:center; }
.fb-correct { background:rgba(16,185,129,0.92); }
.fb-wrong { background:rgba(239,68,68,0.92); }
.fb-content { text-align:center; padding:32px 40px; border-radius:24px; background:rgba(255,255,255,0.15); backdrop-filter:blur(4px); max-width:380px; }
.fb-emoji { font-size:64px; margin-bottom:6px; }
.fb-title { font-size:28px; font-weight:900; color:#fff; margin-bottom:4px; }
.fb-answer { font-size:17px; color:rgba(255,255,255,0.9); margin-bottom:6px; }
.fb-answer strong { font-size:22px; font-weight:900; }
.fb-hint { font-size:13px; color:rgba(255,255,255,0.8); font-style:italic; margin-bottom:12px; padding: 0 10px; }
.fb-bar-wrap { width:200px; height:6px; background:rgba(255,255,255,0.3); border-radius:3px; overflow:hidden; margin:10px auto 0; }
.fb-bar { height:100%; background:#fff; border-radius:3px; transition:width 0.05s linear; }
.fb-enter-active,.fb-leave-active { transition:opacity 0.2s,transform 0.2s; }
.fb-enter-from,.fb-leave-to { opacity:0; transform:scale(0.95); }

.result-screen { display:flex; flex-direction:column; align-items:center; padding:40px 20px; text-align:center; }
.result-img { margin-bottom:10px; }
.result-score { font-size:56px; font-weight:900; color:#92400e; }
.result-detail { color:#78350f; font-size:18px; margin:6px 0 16px; }
.level-up-badge { background:#d97706; color:#fff; padding:10px 24px; border-radius:20px; font-size:18px; font-weight:800; margin-bottom:20px; }
.result-btns { display:flex; gap:12px; }
.rbtn { padding:14px 28px; border-radius:20px; border:none; font-size:16px; font-weight:700; cursor:pointer; }
.rbtn.retry { background:#d97706; color:#fff; }
.rbtn.home { background:#fff; color:#78350f; border:2px solid #d97706; }
</style>
