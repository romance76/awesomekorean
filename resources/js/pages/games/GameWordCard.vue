<template>
<GameShell title="단어 카드" icon="📇" :level="level" :score="score"
  bg="linear-gradient(160deg,#e0f2fe 0%,#bae6fd 50%,#7dd3fc 100%)">

  <!-- 시작 -->
  <div v-if="phase==='start'" class="start-screen">
    <div class="start-card">📇</div>
    <h1 class="title">한국어 단어 카드</h1>
    <p class="subtitle">그림을 보고 한국어 단어를 맞춰요!</p>
    <div class="level-card">
      <div class="lv-row" v-for="lv in levelDesc" :key="lv.lv" :class="{active: level>=lv.lv}">
        <span class="lv-badge">Lv.{{ lv.lv }}</span>
        <span>{{ lv.desc }}</span>
      </div>
    </div>
    <button class="start-btn" @click="startGame" :disabled="loadingPool">
      {{ loadingPool ? '불러오는 중...' : '시작하기 ▶' }}
    </button>
  </div>

  <!-- 플레이 -->
  <div v-if="phase==='play'" class="play-box">
    <div class="progress-bar"><div class="progress-fill" :style="{width:(qIdx/totalQ*100)+'%'}"></div></div>
    <div class="play-header">
      <div class="q-count">{{ qIdx+1 }} / {{ totalQ }}</div>
      <div class="streak-badge" v-if="streak>1">🔥 {{ streak }}</div>
    </div>
    <div class="card-display">
      <img v-if="cur.image" :src="cur.image" :alt="cur.answer" class="card-photo"
        @error="onImgError($event, cur)" />
      <div v-else class="card-emoji">{{ emojiFromHex(cur.emoji_hex) }}</div>
      <div v-if="cur.hint" class="card-hint">💡 {{ cur.hint }}</div>
    </div>
    <p class="question-text">이 그림의 한국어 이름은?</p>
    <div class="choices">
      <button v-for="(opt, i) in choices" :key="opt"
        class="choice-btn"
        :class="{
          correct: showFeedback && opt===cur.answer,
          wrong: showFeedback && opt===selectedOpt && opt!==cur.answer
        }"
        :disabled="answered" @click="answer(opt)">
        <span class="num">{{ ['A','B','C','D'][i] }}</span>
        <span>{{ opt }}</span>
      </button>
    </div>
  </div>

  <!-- 종료 -->
  <div v-if="phase==='end'" class="end-box">
    <div style="font-size:80px">🌟</div>
    <h2 class="end-title">잘 했어요!</h2>
    <p class="end-score">{{ score }}점 · {{ correct }}/{{ totalQ }} 정답</p>
    <div v-if="leveled" class="levelup-badge">🎉 레벨업! 레벨 {{ level }}!</div>
    <div class="end-btns">
      <button class="start-btn" @click="startGame">다시 하기 🔄</button>
      <button class="home-btn" @click="$router.push('/games')">목록으로 🏠</button>
    </div>
  </div>

  <!-- 피드백 -->
  <Transition name="fb">
    <div v-if="showFeedback" class="feedback-overlay" :class="lastCorrect?'fb-correct':'fb-wrong'">
      <div class="fb-content">
        <div class="fb-emoji">{{ lastCorrect ? '🎉' : '😢' }}</div>
        <div class="fb-title">{{ lastCorrect ? '정답이에요!' : '아쉬워요!' }}</div>
        <div v-if="!lastCorrect" class="fb-answer">정답은 「<strong>{{ cur?.answer }}</strong>」이에요</div>
        <div class="fb-bar-wrap"><div class="fb-bar" :style="{width:fbProgress+'%'}"></div></div>
      </div>
    </div>
  </Transition>
</GameShell>
</template>

<script setup>
import { ref, computed, onUnmounted } from 'vue'
import axios from 'axios'
import GameShell from '../../components/GameShell.vue'

const levelDesc = [
  {lv:1, desc:'기초 음식/과일 (15개)'},
  {lv:2, desc:'동물 (15개)'},
  {lv:3, desc:'일상 사물 (15개)'},
  {lv:4, desc:'자연/날씨 (10개)'},
  {lv:5, desc:'마스터 (전체)'},
]

const level = ref(parseInt(localStorage.getItem('wordcard_level')||'1'))
const score = ref(0); const qIdx = ref(0); const correct = ref(0); const streak = ref(0)
const leveled = ref(false); const answered = ref(false); const phase = ref('start')
const showFeedback = ref(false); const lastCorrect = ref(false); const fbProgress = ref(100)
const selectedOpt = ref(null)
const pool = ref([])
const loadingPool = ref(false)
const queue = ref([])
const choices = ref([])
const totalQ = ref(12)
let fbTimer = null

const cur = computed(() => queue.value[qIdx.value] || {})

function emojiFromHex(hex) {
  if (!hex) return '❓'
  // hex like "1f436" or "1f1f0-1f1f7"
  try {
    return String.fromCodePoint(...hex.split('-').map(h => parseInt(h, 16)))
  } catch { return '❓' }
}

function onImgError(e, item) {
  // 이미지 실패 시 이모지로 교체
  const emoji = emojiFromHex(item.emoji_hex)
  const span = document.createElement('span')
  span.className = 'card-emoji'
  span.textContent = emoji
  e.target.replaceWith(span)
}

async function loadPool() {
  loadingPool.value = true
  try {
    const { data } = await axios.get(`/api/quiz/wordcard`, {
      params: { level: level.value, level_mode: 'lte', limit: 100 }
    })
    // API data: { answer, image, emoji_hex 필드는 resolved_image 로 image 로 옴 }
    // emoji_hex 를 별도로 받을 수 없으니 image URL 에서 추출 필요 — 일단 image 만 사용
    pool.value = (data.data || []).map(q => ({
      answer: q.answer,
      image: q.image || null,
      emoji_hex: extractHexFromImage(q.image),
      hint: q.hint,
      wrongs: q.wrongs || [],
    }))
  } catch { pool.value = [] }
  loadingPool.value = false
}

function extractHexFromImage(url) {
  if (!url) return null
  // Noto emoji URL 패턴: /notoemoji/latest/{hex}/512.png
  const m = url.match(/notoemoji\/latest\/([0-9a-f-]+)\//)
  return m ? m[1] : null
}

function speak(t) {
  if (!window.speechSynthesis) return
  window.speechSynthesis.cancel()
  const u = new SpeechSynthesisUtterance(t); u.lang='ko-KR'; u.rate=0.9
  window.speechSynthesis.speak(u)
}

function shuffle(a) { return [...a].sort(()=>Math.random()-.5) }

async function startGame() {
  if (!pool.value.length) await loadPool()
  if (!pool.value.length) { alert('문제가 없습니다'); return }
  score.value=0; qIdx.value=0; correct.value=0; streak.value=0; leveled.value=false
  answered.value=false; showFeedback.value=false
  queue.value = shuffle(pool.value).slice(0, Math.min(totalQ.value, pool.value.length))
  totalQ.value = queue.value.length
  phase.value = 'play'
  loadChoices()
  speak('단어 카드를 시작해요!')
}

function loadChoices() {
  const q = cur.value
  if (!q) return
  let wrongs = []
  if (q.wrongs && q.wrongs.length >= 3) {
    wrongs = shuffle(q.wrongs).slice(0, 3)
  } else {
    wrongs = shuffle(pool.value.filter(a => a.answer !== q.answer).map(a => a.answer)).slice(0, 3)
  }
  choices.value = shuffle([q.answer, ...wrongs])
  selectedOpt.value = null
}

function triggerFeedback(isCorrect) {
  lastCorrect.value = isCorrect; showFeedback.value = true; fbProgress.value = 100
  clearInterval(fbTimer)
  const dur = isCorrect ? 1200 : 2000; const step = 50/dur*100
  fbTimer = setInterval(() => {
    fbProgress.value = Math.max(0, fbProgress.value - step)
    if (fbProgress.value <= 0) {
      clearInterval(fbTimer); showFeedback.value = false; answered.value = false
      qIdx.value++
      if (qIdx.value >= totalQ.value) endGame()
      else loadChoices()
    }
  }, 50)
}

function answer(opt) {
  if (answered.value) return
  answered.value = true
  selectedOpt.value = opt
  const ok = opt === cur.value.answer
  if (ok) { score.value += 10 + streak.value * 2; correct.value++; streak.value++ }
  else { streak.value = 0 }
  speak(ok ? '정답이에요!' : `정답은 ${cur.value.answer}이에요`)
  triggerFeedback(ok)
}

function endGame() {
  phase.value = 'end'
  if (correct.value >= Math.ceil(totalQ.value * 0.7) && level.value < 5) {
    level.value++
    localStorage.setItem('wordcard_level', level.value)
    leveled.value = true
    loadPool()
    speak('레벨업! 잘 했어요!')
  } else speak('다시 한번 도전해봐요!')
}

onUnmounted(() => { clearInterval(fbTimer); window.speechSynthesis?.cancel() })
loadPool()
</script>

<style scoped>
.start-screen { display:flex; flex-direction:column; align-items:center; padding:30px 20px; }
.start-card { font-size:100px; margin-bottom:10px; filter: drop-shadow(0 4px 12px rgba(2,132,199,0.3)); }
.title { font-size:32px; font-weight:900; color:#0c4a6e; margin:8px 0; }
.subtitle { color:#0369a1; font-size:15px; margin-bottom:16px; }
.level-card { background:rgba(255,255,255,0.85); border-radius:16px; padding:14px 20px; width:100%; max-width:360px; margin-bottom:20px; }
.lv-row { display:flex; align-items:center; gap:10px; padding:6px 0; color:#374151; font-size:14px; border-bottom:1px solid #e0f2fe; }
.lv-row:last-child { border-bottom:none; }
.lv-row.active { color:#0c4a6e; font-weight:700; }
.lv-badge { background:#0ea5e9; color:#fff; font-size:11px; font-weight:700; padding:2px 8px; border-radius:10px; min-width:36px; text-align:center; }
.start-btn { background:linear-gradient(135deg,#0ea5e9,#0369a1); color:#fff; border:none; padding:16px 40px; border-radius:30px; font-size:18px; font-weight:800; cursor:pointer; box-shadow:0 4px 20px rgba(14,165,233,0.4); }
.start-btn:disabled { opacity: 0.6; cursor: default; }

.play-box { max-width:520px; margin:0 auto; padding:12px 16px; width:100%; }
.progress-bar { height:8px; background:rgba(255,255,255,0.5); border-radius:4px; margin-bottom:8px; overflow:hidden; }
.progress-fill { height:100%; background:linear-gradient(90deg,#0ea5e9,#0369a1); border-radius:4px; transition:width .3s; }
.play-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
.q-count { color:#0c4a6e; font-size:13px; font-weight:700; }
.streak-badge { background:#f59e0b; color:#fff; padding:4px 10px; border-radius:14px; font-size:12px; font-weight:700; }

.card-display { background:rgba(255,255,255,0.95); border-radius:20px; padding:24px; margin-bottom:16px; text-align:center; box-shadow:0 8px 32px rgba(2,132,199,0.15); }
.card-photo { width:180px; height:180px; object-fit:cover; border-radius:14px; }
.card-emoji { font-size:120px; line-height:1; }
.card-hint { color:#0369a1; font-size:14px; margin-top:12px; font-weight:600; }
.question-text { color:#0c4a6e; font-size:17px; font-weight:800; text-align:center; margin-bottom:12px; }
.choices { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.choice-btn { background:rgba(255,255,255,0.95); color:#0c4a6e; border:2px solid transparent; padding:14px 12px; border-radius:14px; font-size:16px; font-weight:700; cursor:pointer; transition:all .15s; display:flex; align-items:center; gap:10px; box-shadow:0 2px 10px rgba(0,0,0,0.06); }
.choice-btn .num { width:28px; height:28px; border-radius:50%; background:#0ea5e9; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:12px; flex-shrink:0; }
.choice-btn:hover:not(:disabled) { background:#fff; transform:translateY(-2px); border-color:#0ea5e9; }
.choice-btn.correct { border-color:#10b981; background:#d1fae5; }
.choice-btn.correct .num { background:#059669; }
.choice-btn.wrong { border-color:#ef4444; background:#fee2e2; }
.choice-btn.wrong .num { background:#dc2626; }
.choice-btn:disabled { cursor:default; }

.end-box { text-align:center; padding:40px 20px; }
.end-title { font-size:32px; color:#0c4a6e; font-weight:900; }
.end-score { color:#0369a1; font-size:18px; margin:8px 0 14px; }
.levelup-badge { background:#0ea5e9; color:#fff; padding:10px 24px; border-radius:20px; font-weight:800; font-size:16px; display:inline-block; margin:10px 0 18px; }
.end-btns { display:flex; gap:12px; justify-content:center; flex-wrap:wrap; }
.home-btn { background:#fff; color:#0c4a6e; border:2px solid #0ea5e9; padding:14px 28px; border-radius:30px; font-size:16px; font-weight:700; cursor:pointer; }

.feedback-overlay { position:fixed; inset:0; display:flex; align-items:center; justify-content:center; z-index:999; backdrop-filter:blur(4px); }
.fb-correct { background:rgba(16,185,129,.92); }
.fb-wrong { background:rgba(239,68,68,.92); }
.fb-content { text-align:center; color:#fff; padding:32px 40px; border-radius:24px; background:rgba(255,255,255,0.15); }
.fb-emoji { font-size:72px; margin-bottom:8px; }
.fb-title { font-size:32px; font-weight:900; margin-bottom:8px; }
.fb-answer { font-size:17px; margin-bottom:12px; }
.fb-answer strong { font-size:20px; }
.fb-bar-wrap { width:200px; height:6px; background:rgba(255,255,255,.3); border-radius:4px; margin:0 auto; overflow:hidden; }
.fb-bar { height:100%; background:#fff; border-radius:4px; transition:width .05s linear; }
.fb-enter-active,.fb-leave-active { transition:opacity .25s; }
.fb-enter-from,.fb-leave-to { opacity:0; }
</style>
