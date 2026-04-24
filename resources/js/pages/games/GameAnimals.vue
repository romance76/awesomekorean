<template>
<GameShell title="동물 이름 퀴즈" icon="🦌" :level="level" :score="score"
  bg="linear-gradient(160deg,#ecfdf5 0%,#d1fae5 50%,#a7f3d0 100%)">

  <!-- 시작 화면 -->
  <div v-if="phase==='start'" class="start-screen">
    <div class="start-mascot">
      <img src="https://fonts.gstatic.com/s/e/notoemoji/latest/1f98c/512.png" alt="deer" class="mascot-img"/>
    </div>
    <h1 class="game-title">동물 이름 퀴즈</h1>
    <p class="game-desc">사진을 보고 동물 이름을 맞춰요!</p>
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
          <circle cx="24" cy="24" r="20" fill="none" :stroke="timeLeft<=3?'#f87171':'#059669'" stroke-width="4"
            :stroke-dasharray="125.6" :stroke-dashoffset="125.6*(1-timeLeft/maxTime)"
            transform="rotate(-90 24 24)" stroke-linecap="round"/>
        </svg>
        <span class="timer-text">{{ timeLeft }}</span>
      </div>
      <div class="streak-badge" v-if="streak>1">🔥 {{ streak }}연속</div>
    </div>

    <div class="animal-card">
      <div class="animal-photo-wrap">
        <img :src="curAnimal.image" :alt="curAnimal.answer" class="animal-photo"
          @error="(e) => e.target.src='https://fonts.gstatic.com/s/e/notoemoji/latest/1f43e/512.png'"/>
      </div>
      <div class="animal-sound" v-if="curAnimal.sound">
        <button class="sound-btn" @click="speakAnimal">🔊 {{ curAnimal.sound }}</button>
      </div>
      <div class="prompt-text">이 동물의 이름은?</div>
    </div>

    <div class="choices-row">
      <button v-for="(opt, i) in choices" :key="opt"
        class="choice-card"
        :class="{
          selected: selectedOpt===opt && !showFeedback,
          correct: showFeedback && opt===curAnimal.answer,
          wrong: showFeedback && opt===selectedOpt && opt!==curAnimal.answer
        }"
        :disabled="selectedOpt!==null"
        @click="selectAnswer(opt)">
        <span class="choice-num">{{ ['A','B','C','D'][i] }}</span>
        <span class="choice-name">{{ opt }}</span>
      </button>
    </div>
  </div>

  <!-- 피드백 오버레이 -->
  <Transition name="fb">
    <div v-if="showFeedback" class="feedback-overlay" :class="lastCorrect ? 'fb-correct' : 'fb-wrong'">
      <div class="fb-content">
        <div class="fb-emoji">{{ lastCorrect ? '🎉' : '😢' }}</div>
        <div class="fb-title">{{ lastCorrect ? '정답이에요!' : '아쉬워요!' }}</div>
        <div class="fb-answer" v-if="!lastCorrect">정답은 「<strong>{{ curAnimal?.answer }}</strong>」이에요</div>
        <div class="fb-bar-wrap"><div class="fb-bar" :style="{width:fbProgress+'%'}"></div></div>
      </div>
    </div>
  </Transition>

  <!-- 결과 화면 -->
  <div v-if="phase==='result'" class="result-screen">
    <div class="result-img">
      <img :src="`https://fonts.gstatic.com/s/e/notoemoji/latest/${correct>=8?'1f3c6':'1f44f'}/512.png`"
        alt="trophy" style="width:100px;height:100px"/>
    </div>
    <div class="result-score">{{ score }}점</div>
    <div class="result-detail">{{ correct }} / {{ totalQ }} 정답</div>
    <div v-if="leveled" class="level-up-badge">🎉 레벨업! → 레벨 {{ level }}</div>
    <GameResultExtras :rec="rec" slug="animals" />
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
const rec = useGameRecord('animals')

const levelDesc = [
  {lv:1, desc:'기본 동물 (8마리)'},
  {lv:2, desc:'중급 동물 (14마리)'},
  {lv:3, desc:'전체 동물 + 제한시간'},
  {lv:4, desc:'고급 동물 + 짧은 시간'},
  {lv:5, desc:'마스터 모드'},
]

const level = ref(parseInt(localStorage.getItem('animal_level') || '1'))
const score = ref(0)
const correct = ref(0)
const streak = ref(0)
const phase = ref('start')
const qIdx = ref(0)
const totalQ = ref(10)
const curAnimal = ref(null)
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
    const { data } = await axios.get(`/api/quiz/animals`, {
      params: { level: level.value, level_mode: 'lte', limit: 100 }
    })
    pool.value = data.data || []
  } catch { pool.value = [] }
  loadingPool.value = false
}

function speak(text, rate = 0.85) {
  if (!window.speechSynthesis) return
  window.speechSynthesis.cancel()
  const u = new SpeechSynthesisUtterance(text)
  u.lang = 'ko-KR'; u.rate = rate; u.pitch = 1.1
  window.speechSynthesis.speak(u)
}

function speakAnimal() {
  if (curAnimal.value) speak(curAnimal.value.answer + '! ' + (curAnimal.value.sound || ''))
}

function shuffle(a){const r=[...a];for(let i=r.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[r[i],r[j]]=[r[j],r[i]]}return r}

async function startGame() {
  if (!pool.value.length) await loadPool()
  if (!pool.value.length) { alert('문제가 없습니다'); return }
  score.value = 0; correct.value = 0; streak.value = 0; leveled.value = false
  qIdx.value = 0
  maxTime.value = level.value >= 3 ? 8 : 0
  queue = shuffle(pool.value).slice(0, Math.min(totalQ.value, pool.value.length))
  totalQ.value = queue.length
  rec.start(level.value)
  phase.value = 'play'
  speak('동물 이름을 맞춰봐요!')
  loadQuestion()
}

function loadQuestion() {
  if (qIdx.value >= totalQ.value) { endGame(); return }
  const q = queue[qIdx.value]
  curAnimal.value = q
  selectedOpt.value = null
  showFeedback.value = false

  // 선택지: 직접 지정된 오답이 있으면 사용, 없으면 pool에서 뽑기
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
  speak('이 동물의 이름은?')
}

function autoFail() { selectedOpt.value = '__timeout__'; triggerFeedback(false) }

function selectAnswer(opt) {
  if (selectedOpt.value !== null) return
  clearInterval(countTimer)
  selectedOpt.value = opt
  const isCorrect = opt === curAnimal.value.answer
  if (isCorrect) {
    correct.value++; streak.value++
    score.value += 10 + (streak.value > 1 ? streak.value * 3 : 0) + (maxTime.value > 0 ? timeLeft.value * 2 : 0)
    speak(opt + '! 맞아요!')
  } else {
    streak.value = 0
    speak('아쉬워요! 정답은 ' + curAnimal.value.answer)
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
    localStorage.setItem('animal_level', level.value)
    leveled.value = true
    speak('레벨업! 레벨 ' + level.value + '!')
    loadPool()
  } else {
    speak(correct.value + '개 맞았어요! 잘했어요!')
  }
  await rec.end({ won, leveledUp: leveled.value, score: score.value })
}

onUnmounted(() => { clearInterval(fbTimer); clearInterval(countTimer); window.speechSynthesis?.cancel() })
loadPool()
</script>

<style scoped>
/* 시작 화면 */
.start-screen { display:flex; flex-direction:column; align-items:center; padding:30px 20px; }
.start-mascot { margin-bottom:10px; }
.mascot-img { width:100px; height:100px; }
.game-title { font-size:32px; font-weight:900; color:#065f46; margin:8px 0; }
.game-desc { color:#047857; font-size:16px; margin-bottom:16px; }
.level-card { background:rgba(255,255,255,0.8); border-radius:16px; padding:14px 20px; width:100%; max-width:360px; margin-bottom:20px; }
.lv-row { display:flex; align-items:center; gap:10px; padding:6px 0; color:#374151; font-size:14px; border-bottom:1px solid #d1fae5; }
.lv-row:last-child { border-bottom:none; }
.lv-row.active { color:#065f46; font-weight:700; }
.lv-badge { background:#10b981; color:#fff; font-size:11px; font-weight:700; padding:2px 8px; border-radius:10px; min-width:36px; text-align:center; }
.play-btn { background:linear-gradient(135deg,#10b981,#059669); color:#fff; border:none; padding:16px 48px; border-radius:30px; font-size:20px; font-weight:800; cursor:pointer; box-shadow:0 4px 20px rgba(16,185,129,0.4); }
.play-btn:disabled { opacity: 0.6; cursor: default; }

/* 게임 화면 */
.play-screen { padding:12px 16px; max-width:500px; margin:0 auto; width:100%; }
.play-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; }
.q-counter { background:rgba(255,255,255,0.8); padding:6px 14px; border-radius:20px; font-size:15px; font-weight:700; color:#065f46; }
.timer-ring { position:relative; width:48px; height:48px; }
.timer-text { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); font-size:13px; font-weight:800; color:#374151; }
.streak-badge { background:#f59e0b; color:#fff; padding:6px 12px; border-radius:20px; font-size:13px; font-weight:700; }

.animal-card { background:rgba(255,255,255,0.95); border-radius:24px; padding:24px; text-align:center; margin-bottom:16px; box-shadow:0 8px 32px rgba(16,185,129,0.15); }
.animal-photo-wrap { width:220px; height:220px; margin:0 auto 12px; border-radius:20px; overflow:hidden; background:#f0fdf4; display:flex; align-items:center; justify-content:center; }
.animal-photo { width:200px; height:200px; object-fit:contain; }
.sound-btn { background:#ecfdf5; color:#059669; border:1px solid #a7f3d0; padding:8px 16px; border-radius:20px; cursor:pointer; font-size:14px; font-weight:600; }
.prompt-text { font-size: 15px; font-weight: 700; color: #065f46; margin-top: 10px; }

.choices-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.choice-card { background:rgba(255,255,255,0.95); border:2px solid transparent; border-radius:16px; padding:16px 12px; display:flex; align-items:center; gap:10px; cursor:pointer; transition:all 0.2s; box-shadow:0 2px 10px rgba(0,0,0,0.06); }
.choice-card:hover:not([disabled]) { transform:translateY(-2px); border-color:#10b981; box-shadow:0 6px 20px rgba(16,185,129,0.2); }
.choice-num { width:30px; height:30px; border-radius:50%; background:#10b981; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:13px; flex-shrink:0; }
.choice-name { font-size:17px; font-weight:700; color:#374151; }
.choice-card.correct { border-color:#10b981; background:#d1fae5; }
.choice-card.correct .choice-num { background:#059669; }
.choice-card.wrong { border-color:#ef4444; background:#fee2e2; }
.choice-card.wrong .choice-num { background:#dc2626; }

/* 피드백 오버레이 */
.feedback-overlay { position:fixed; inset:0; z-index:100; display:flex; align-items:center; justify-content:center; }
.fb-correct { background:rgba(16,185,129,0.92); }
.fb-wrong { background:rgba(239,68,68,0.92); }
.fb-content { text-align:center; padding:32px 40px; border-radius:24px; background:rgba(255,255,255,0.15); backdrop-filter:blur(4px); }
.fb-emoji { font-size:72px; margin-bottom:8px; }
.fb-title { font-size:32px; font-weight:900; color:#fff; margin-bottom:6px; }
.fb-answer { font-size:17px; color:rgba(255,255,255,0.9); margin-bottom:12px; }
.fb-answer strong { font-size:20px; }
.fb-bar-wrap { width:200px; height:6px; background:rgba(255,255,255,0.3); border-radius:3px; overflow:hidden; margin:0 auto; }
.fb-bar { height:100%; background:#fff; border-radius:3px; transition:width 0.05s linear; }
.fb-enter-active,.fb-leave-active { transition:opacity 0.2s,transform 0.2s; }
.fb-enter-from,.fb-leave-to { opacity:0; transform:scale(0.95); }

/* 결과 화면 */
.result-screen { display:flex; flex-direction:column; align-items:center; padding:40px 20px; text-align:center; }
.result-img { margin-bottom:10px; }
.result-score { font-size:56px; font-weight:900; color:#059669; }
.result-detail { color:#047857; font-size:18px; margin:6px 0 16px; }
.level-up-badge { background:#10b981; color:#fff; padding:10px 24px; border-radius:20px; font-size:18px; font-weight:800; margin-bottom:20px; }
.result-btns { display:flex; gap:12px; }
.rbtn { padding:14px 28px; border-radius:20px; border:none; font-size:16px; font-weight:700; cursor:pointer; }
.rbtn.retry { background:#10b981; color:#fff; }
.rbtn.home { background:#fff; color:#065f46; border:2px solid #10b981; }
</style>
