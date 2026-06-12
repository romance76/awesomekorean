<template>
<GameShell title="국기 퀴즈" icon="🏳️" :level="level" :score="score"
  bg="linear-gradient(160deg,#dbeafe 0%,#bfdbfe 50%,#93c5fd 100%)">

  <!-- 시작 화면 -->
  <div v-if="phase==='start'" class="start-screen">
    <div class="start-flags">
      <img src="https://flagcdn.com/w80/kr.png" alt="kr" class="sfl sfl1" />
      <img src="https://flagcdn.com/w80/us.png" alt="us" class="sfl sfl2" />
      <img src="https://flagcdn.com/w80/jp.png" alt="jp" class="sfl sfl3" />
      <img src="https://flagcdn.com/w80/fr.png" alt="fr" class="sfl sfl4" />
    </div>
    <h1 class="game-title">국기 퀴즈</h1>
    <p class="game-desc">세계 국기를 보고 나라 이름을 맞춰요!</p>
    <div class="level-card">
      <button v-for="lv in levelDesc" :key="lv.lv"
        type="button"
        class="lv-row lv-row-btn"
        :class="{ active: level===lv.lv, locked: lv.lv > Math.max(1, maxUnlockedLevel || 1) }"
        :disabled="lv.lv > Math.max(1, maxUnlockedLevel || 1)"
        @click="level = lv.lv">
        <span class="lv-badge">{{ lv.lv > Math.max(1, maxUnlockedLevel || 1) ? '🔒' : 'Lv.' + lv.lv }}</span>
        <span>{{ lv.desc }}</span>
      </button>
    </div>
    <div class="progress-info" v-if="maxCompletedLevel > 0">
      🎯 최고 클리어: Lv.{{ maxCompletedLevel }} · 다음 도전: Lv.{{ maxUnlockedLevel }}
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
          <circle cx="24" cy="24" r="20" fill="none" :stroke="timeLeft<=3?'#f87171':'#3b82f6'" stroke-width="4"
            :stroke-dasharray="125.6" :stroke-dashoffset="125.6*(1-timeLeft/maxTime)"
            transform="rotate(-90 24 24)" stroke-linecap="round"/>
        </svg>
        <span class="timer-text">{{ timeLeft }}</span>
      </div>
      <div class="streak-badge" v-if="streak>1">🔥 {{ streak }}연속</div>
    </div>

    <div class="flag-card">
      <div class="flag-photo-wrap">
        <img :src="curFlag.image" :alt="curFlag.answer" class="flag-photo"
          @error="onImgError"/>
      </div>
      <div class="prompt-text">이 국기는 어느 나라인가요?</div>
    </div>

    <div class="choices-row">
      <button v-for="(opt, i) in choices" :key="opt"
        class="choice-card"
        :class="{
          selected: selectedOpt===opt && !showFeedback,
          correct: showFeedback && opt===curFlag.answer,
          wrong: showFeedback && opt===selectedOpt && opt!==curFlag.answer
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
        <div class="fb-answer" v-if="!lastCorrect">정답은 「<strong>{{ curFlag?.answer }}</strong>」이에요</div>
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
    <div class="result-detail">{{ correct }} / {{ totalQ }} 정답 · ⏱️ {{ formatTime(elapsedMs) }}</div>
    <div v-if="leveled" class="level-up-badge">🎉 레벨업! → 레벨 {{ level }}</div>
    <div v-if="pointsEarned > 0" class="points-earned-badge">+{{ pointsEarned }}P 획득!</div>
    <div v-if="newRecord" class="new-record-badge">⭐ 신기록! (이전: {{ prevTimeMs ? formatTime(prevTimeMs) : '처음 기록' }})</div>
    <!-- 리더보드 -->
    <GameLeaderboard ref="lbRef" slug="flag" :level="recordLevel" />
    <div class="result-btns">
      <button class="rbtn retry" @click="startGame">다시 하기 🔄</button>
      <button class="rbtn home" @click="$router.push('/games')">목록으로 🏠</button>
    </div>
  </div>
</GameShell>
</template>

<script setup>
import { ref, onUnmounted, onMounted } from 'vue'
import axios from 'axios'
import GameShell from '../../components/GameShell.vue'
import GameLeaderboard from '../../components/GameLeaderboard.vue'
import { useAuthStore } from '../../stores/auth'
import { useSiteStore } from '../../stores/site'
import { useGameRecord } from '../../composables/useGameRecord'

const progressRec = useGameRecord('flag')
const maxCompletedLevel = progressRec.maxCompletedLevel
const maxUnlockedLevel = progressRec.maxUnlockedLevel

const levelDesc = [
  {lv:1, desc:'친숙한 국가 (10개국)'},
  {lv:2, desc:'주요 국가 (25개국)'},
  {lv:3, desc:'중급 국가 (40개국)'},
  {lv:4, desc:'고급 국가 + 제한시간'},
  {lv:5, desc:'마스터 (전 세계)'},
]

const level = ref(parseInt(localStorage.getItem('flag_level') || '1'))
const score = ref(0)
const correct = ref(0)
const streak = ref(0)
const phase = ref('start')
const qIdx = ref(0)
const totalQ = ref(10)
const curFlag = ref(null)
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
// 시간 측정 + 기록 전송
const startAt = ref(0)
const elapsedMs = ref(0)
const recordLevel = ref(1)      // 이번 판의 레벨 (레벨업 후에도 기록 조회용)
const pointsEarned = ref(0)
const newRecord = ref(false)
const prevTimeMs = ref(null)
const lbRef = ref(null)
const auth = useAuthStore()
const siteStore = useSiteStore()
function formatTime(ms) { return (Math.round(ms / 10) / 100).toFixed(2) + '초' }

function onImgError(e) {
  e.target.src = 'https://fonts.gstatic.com/s/e/notoemoji/latest/1f3f3/512.png'
}

async function loadPool() {
  loadingPool.value = true
  try {
    const { data } = await axios.get(`/api/quiz/flag`, {
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
  maxTime.value = level.value >= 4 ? 10 : 0
  queue = shuffle(pool.value).slice(0, Math.min(totalQ.value, pool.value.length))
  totalQ.value = queue.length
  recordLevel.value = level.value
  pointsEarned.value = 0; newRecord.value = false; prevTimeMs.value = null
  elapsedMs.value = 0
  startAt.value = Date.now()
  phase.value = 'play'
  loadQuestion()
}

function loadQuestion() {
  if (qIdx.value >= totalQ.value) { endGame(); return }
  const q = queue[qIdx.value]
  curFlag.value = q
  selectedOpt.value = null
  showFeedback.value = false

  // 보기 중복 방지 — 정답 먼저 넣고 Set 으로 유일한 오답 3개까지 수집
  const set = new Set([q.answer])
  if (Array.isArray(q.wrongs)) {
    for (const w of shuffle(q.wrongs)) { if (set.size >= 4) break; if (w && w !== q.answer) set.add(w) }
  }
  if (set.size < 4) {
    for (const a of shuffle(pool.value)) {
      if (set.size >= 4) break
      if (a.answer && a.answer !== q.answer) set.add(a.answer)
    }
  }
  choices.value = shuffle([...set])

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
  const isCorrect = opt === curFlag.value.answer
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
  const duration = isCorrect ? 1200 : 2000
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
  elapsedMs.value = Date.now() - startAt.value
  phase.value = 'result'
  const threshold = level.value <= 1 ? 7 : level.value <= 2 ? 8 : 9
  const won = correct.value >= threshold
  const clearedLevel = recordLevel.value
  if (won && level.value < 5) {
    level.value++
    localStorage.setItem('flag_level', level.value)
    leveled.value = true
    loadPool()
  }
  // 기록 저장 + 포인트 처리 (로그인한 사람만, 이겼을 때만)
  if (auth.isLoggedIn && won) {
    try {
      const { data } = await axios.post('/api/games/result', {
        game_slug: 'flag',
        level: clearedLevel,
        time_ms: elapsedMs.value,
        score: score.value,
        won: true,
        leveled_up: leveled.value,
      })
      const r = data.data || {}
      pointsEarned.value = r.points_earned || 0
      newRecord.value = !!r.new_record
      prevTimeMs.value = r.prev_time_ms
      if (pointsEarned.value > 0) {
        siteStore.toast(`+${pointsEarned.value}P 획득!`, 'success')
        auth.user && (auth.user.points = r.balance ?? auth.user.points)
      }
      lbRef.value?.reload?.()
    } catch {}
  }
}

onMounted(async () => {
  await progressRec.loadProgress()
  if (maxUnlockedLevel.value > level.value) {
    level.value = maxUnlockedLevel.value
    localStorage.setItem('flag_level', level.value)
  }
})
onUnmounted(() => { clearInterval(fbTimer); clearInterval(countTimer) })
loadPool()
</script>

<style scoped>
.start-screen { display:flex; flex-direction:column; align-items:center; padding:30px 20px; }
.start-flags { display:flex; gap:6px; margin-bottom:14px; }
.sfl { width:56px; height:40px; object-fit:cover; border-radius:4px; box-shadow:0 2px 8px rgba(30,64,175,0.2); transition: transform 0.5s; }
.sfl1 { transform: rotate(-10deg); }
.sfl2 { transform: rotate(-3deg) translateY(-4px); }
.sfl3 { transform: rotate(3deg) translateY(-4px); }
.sfl4 { transform: rotate(10deg); }
.game-title { font-size:32px; font-weight:900; color:#1e3a8a; margin:8px 0; }
.game-desc { color:#1e40af; font-size:16px; margin-bottom:16px; }
.level-card { background:rgba(255,255,255,0.8); border-radius:16px; padding:14px 20px; width:100%; max-width:360px; margin-bottom:20px; }
.lv-row { display:flex; align-items:center; gap:10px; padding:6px 0; color:#374151; font-size:14px; border-bottom:1px solid #dbeafe; }
.lv-row:last-child { border-bottom:none; }
.lv-row.active { color:#1e3a8a; font-weight:700; }
.lv-badge { background:#3b82f6; color:#fff; font-size:11px; font-weight:700; padding:2px 8px; border-radius:10px; min-width:36px; text-align:center; }
.play-btn { background:linear-gradient(135deg,#3b82f6,#1d4ed8); color:#fff; border:none; padding:16px 48px; border-radius:30px; font-size:20px; font-weight:800; cursor:pointer; box-shadow:0 4px 20px rgba(59,130,246,0.4); }
.play-btn:disabled { opacity: 0.6; cursor: default; }
.progress-info { background:rgba(59,130,246,0.15); color:#1e3a8a; padding:8px 16px; border-radius:14px; font-size:13px; font-weight:600; margin-bottom:14px; display:inline-block; }
.lv-row-btn { width:100%; text-align:left; background:transparent; border:none; cursor:pointer; transition:all 0.15s; }
.lv-row-btn:hover:not(:disabled) { background:rgba(59,130,246,0.1); }
.lv-row-btn.active { background:rgba(59,130,246,0.2); color:#1e3a8a; font-weight:700; }
.lv-row-btn.locked { opacity:0.45; cursor:not-allowed; }
.lv-row-btn:disabled { cursor:not-allowed; }

.play-screen { padding:12px 16px; max-width:500px; margin:0 auto; width:100%; }
.play-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; }
.q-counter { background:rgba(255,255,255,0.8); padding:6px 14px; border-radius:20px; font-size:15px; font-weight:700; color:#1e3a8a; }
.timer-ring { position:relative; width:48px; height:48px; }
.timer-text { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); font-size:13px; font-weight:800; color:#374151; }
.streak-badge { background:#f59e0b; color:#fff; padding:6px 12px; border-radius:20px; font-size:13px; font-weight:700; }

.flag-card { background:rgba(255,255,255,0.95); border-radius:24px; padding:24px; text-align:center; margin-bottom:16px; box-shadow:0 8px 32px rgba(59,130,246,0.15); }
.flag-photo-wrap { width:280px; height:188px; margin:0 auto 12px; border-radius:12px; overflow:hidden; background:#f1f5f9; display:flex; align-items:center; justify-content:center; border: 1px solid #cbd5e1; }
.flag-photo { width:100%; height:100%; object-fit:cover; }
.prompt-text { font-size:15px; font-weight:700; color:#1e3a8a; margin-top:10px; }

.choices-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.choice-card { background:rgba(255,255,255,0.95); border:2px solid transparent; border-radius:16px; padding:14px 12px; display:flex; align-items:center; gap:10px; cursor:pointer; transition:all 0.2s; box-shadow:0 2px 10px rgba(0,0,0,0.06); }
.choice-card:hover:not([disabled]) { transform:translateY(-2px); border-color:#3b82f6; box-shadow:0 6px 20px rgba(59,130,246,0.2); }
.choice-num { width:30px; height:30px; border-radius:50%; background:#3b82f6; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:13px; flex-shrink:0; }
.choice-name { font-size:16px; font-weight:700; color:#374151; }
.choice-card.correct { border-color:#10b981; background:#d1fae5; }
.choice-card.correct .choice-num { background:#059669; }
.choice-card.wrong { border-color:#ef4444; background:#fee2e2; }
.choice-card.wrong .choice-num { background:#dc2626; }

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

.result-screen { display:flex; flex-direction:column; align-items:center; padding:40px 20px; text-align:center; }
.result-img { margin-bottom:10px; }
.result-score { font-size:56px; font-weight:900; color:#1d4ed8; }
.result-detail { color:#1e40af; font-size:18px; margin:6px 0 16px; }
.level-up-badge { background:#3b82f6; color:#fff; padding:10px 24px; border-radius:20px; font-size:18px; font-weight:800; margin-bottom:8px; }
.points-earned-badge { background:linear-gradient(135deg,#f59e0b,#d97706); color:#fff; padding:8px 20px; border-radius:18px; font-size:16px; font-weight:800; margin-bottom:8px; }
.new-record-badge { background:linear-gradient(135deg,#ec4899,#be185d); color:#fff; padding:8px 20px; border-radius:18px; font-size:14px; font-weight:800; margin-bottom:12px; }
.result-btns { display:flex; gap:12px; }
.rbtn { padding:14px 28px; border-radius:20px; border:none; font-size:16px; font-weight:700; cursor:pointer; }
.rbtn.retry { background:#3b82f6; color:#fff; }
.rbtn.home { background:#fff; color:#1e3a8a; border:2px solid #3b82f6; }
</style>
