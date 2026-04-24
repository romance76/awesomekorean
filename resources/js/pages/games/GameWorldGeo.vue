<template>
  <div class="geo-game">
    <div class="game-header">
      <button class="back-btn" @click="goBack">← 뒤로</button>
      <div class="level-badge">레벨 {{ level }} 🌍</div>
      <div class="score-badge">⭐ {{ score }}점</div>
    </div>

    <div v-if="phase==='start'" class="start-screen">
      <div class="globe-icon">🌍</div>
      <h1 class="game-title">세계 지리 퀴즈</h1>
      <p class="game-desc">나라 이름, 수도, 국기를 맞춰봐요!</p>
      <div class="level-card">
        <div class="lv-row" :class="{active:level>=1}"><span class="lv-badge">Lv.1</span>아시아 · 유럽 국기</div>
        <div class="lv-row" :class="{active:level>=3}"><span class="lv-badge">Lv.3</span>아메리카 · 아프리카</div>
        <div class="lv-row" :class="{active:level>=5}"><span class="lv-badge">Lv.5</span>수도 + 국기 종합</div>
      </div>
      <button class="play-btn" @click="startGame">탐험 시작! 🗺️</button>
    </div>

    <div v-if="phase==='play'" class="play-screen">
      <div class="play-header">
        <div class="q-counter">{{ qIdx + 1 }} / {{ totalQ }}</div>
        <div class="timer-ring">
          <svg width="48" height="48" viewBox="0 0 48 48">
            <circle cx="24" cy="24" r="20" fill="none" stroke="rgba(255,255,255,0.2)" stroke-width="4"/>
            <circle cx="24" cy="24" r="20" fill="none" :stroke="timeLeft<=4?'#f87171':'#34d399'" stroke-width="4"
              :stroke-dasharray="125.6" :stroke-dashoffset="125.6*(1-timeLeft/maxTime)"
              transform="rotate(-90 24 24)" stroke-linecap="round"/>
          </svg>
          <span class="timer-text">{{ timeLeft }}</span>
        </div>
        <div class="score-small">{{ score }}점</div>
      </div>

      <div class="question-card">
        <!-- 국기 문제 -->
        <div v-if="curQ.type==='flag'" class="flag-question">
          <div class="flag-wrap">
            <img :src="`https://flagcdn.com/w160/${curQ.code}.png`"
              :alt="curQ.country" class="flag-img"
              @error="(e) => e.target.style.display='none'"/>
          </div>
          <div class="q-text">이 국기는 어느 나라의 것일까요?</div>
        </div>
        <!-- 수도 문제 -->
        <div v-else class="capital-question">
          <div class="country-flag-small">
            <img :src="`https://flagcdn.com/w80/${curQ.code}.png`" :alt="curQ.country" class="flag-sm"/>
          </div>
          <div class="country-name-big">{{ curQ.country }}</div>
          <div class="q-text">이 나라의 수도는 어디일까요?</div>
        </div>
      </div>

      <div class="choices-grid">
        <button v-for="opt in options" :key="opt.id" class="choice-btn"
          :class="{
            correct: showFeedback && opt.id===curQ.id,
            wrong: showFeedback && opt.id===selectedId && opt.id!==curQ.id
          }"
          :disabled="selectedId!==null"
          @click="selectAnswer(opt)">
          <img v-if="curQ.type==='flag'" :src="`https://flagcdn.com/w40/${opt.code}.png`"
            :alt="opt.country" class="choice-flag"/>
          <span class="choice-text">{{ curQ.type==='flag' ? opt.country : opt.capital }}</span>
        </button>
      </div>
    </div>

    <!-- 피드백 오버레이 -->
    <Transition name="fb">
      <div v-if="showFeedback" class="feedback-overlay" :class="lastCorrect ? 'fb-correct' : 'fb-wrong'">
        <div class="fb-content">
          <div class="fb-emoji">{{ lastCorrect ? '🎉' : '😢' }}</div>
          <div class="fb-title">{{ lastCorrect ? '정답이에요!' : '아쉬워요!' }}</div>
          <div v-if="!lastCorrect" class="fb-answer">
            정답은 「<strong>{{ lastCorrect ? '' : correctAnswerText }}</strong>」이에요
          </div>
          <div v-if="lastCorrect && factText" class="fb-fact">💡 {{ factText }}</div>
          <div class="fb-bar-wrap"><div class="fb-bar" :style="{width:fbProgress+'%'}"></div></div>
        </div>
      </div>
    </Transition>

    <div v-if="phase==='result'" class="result-screen">
      <img src="https://fonts.gstatic.com/s/e/notoemoji/latest/1f3c6/512.png" alt="trophy" class="trophy"/>
      <div class="result-score">{{ score }}점</div>
      <div class="result-detail">{{ correct }} / {{ totalQ }} 정답</div>
      <div v-if="leveled" class="level-up-badge">🎉 레벨업! → 레벨 {{ level }}</div>
      <GameResultExtras :rec="rec" slug="world_geo" />
      <div class="result-btns">
        <button class="rbtn retry" @click="startGame">다시 하기 🔄</button>
        <button class="rbtn home" @click="goBack">목록으로 🏠</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import GameResultExtras from '../../components/GameResultExtras.vue'
import { useGameRecord } from '../../composables/useGameRecord'
const router = useRouter()
const rec = useGameRecord('world_geo')

const geoData = [
  {id:'kr',code:'kr',country:'한국',capital:'서울',continent:'아시아',level:1,fact:'한국의 수도 서울은 600년 역사의 도시예요'},
  {id:'jp',code:'jp',country:'일본',capital:'도쿄',continent:'아시아',level:1,fact:'도쿄는 세계에서 가장 큰 도시 중 하나예요'},
  {id:'cn',code:'cn',country:'중국',capital:'베이징',continent:'아시아',level:1,fact:'중국은 세계에서 인구가 가장 많은 나라예요'},
  {id:'th',code:'th',country:'태국',capital:'방콕',continent:'아시아',level:1,fact:'태국은 불교 문화로 유명한 나라예요'},
  {id:'vn',code:'vn',country:'베트남',capital:'하노이',continent:'아시아',level:1,fact:'베트남은 쌀국수와 커피로 유명해요'},
  {id:'in',code:'in',country:'인도',capital:'뉴델리',continent:'아시아',level:1,fact:'인도는 세계 최대 민주주의 국가예요'},
  {id:'fr',code:'fr',country:'프랑스',capital:'파리',continent:'유럽',level:1,fact:'파리는 \'빛의 도시\'라 불려요'},
  {id:'de',code:'de',country:'독일',capital:'베를린',continent:'유럽',level:1,fact:'독일은 자동차 산업으로 유명해요'},
  {id:'it',code:'it',country:'이탈리아',capital:'로마',continent:'유럽',level:1,fact:'로마는 \'영원의 도시\'라 불려요'},
  {id:'es',code:'es',country:'스페인',capital:'마드리드',continent:'유럽',level:1,fact:'스페인은 투우와 플라멩코로 유명해요'},
  {id:'gb',code:'gb',country:'영국',capital:'런던',continent:'유럽',level:1,fact:'런던에는 빅벤과 버킹엄 궁전이 있어요'},
  {id:'ru',code:'ru',country:'러시아',capital:'모스크바',continent:'유럽',level:2,fact:'러시아는 세계에서 가장 넓은 나라예요'},
  {id:'us',code:'us',country:'미국',capital:'워싱턴 D.C.',continent:'아메리카',level:2,fact:'미국의 국기는 \'성조기\'라 해요'},
  {id:'ca',code:'ca',country:'캐나다',capital:'오타와',continent:'아메리카',level:2,fact:'캐나다는 세계 2위 영토를 가진 나라예요'},
  {id:'br',code:'br',country:'브라질',capital:'브라질리아',continent:'아메리카',level:2,fact:'브라질은 아마존 열대우림이 있어요'},
  {id:'mx',code:'mx',country:'멕시코',capital:'멕시코시티',continent:'아메리카',level:2,fact:'멕시코는 타코와 마리아치로 유명해요'},
  {id:'ar',code:'ar',country:'아르헨티나',capital:'부에노스아이레스',continent:'아메리카',level:2,fact:'아르헨티나는 축구와 탱고로 유명해요'},
  {id:'eg',code:'eg',country:'이집트',capital:'카이로',continent:'아프리카',level:2,fact:'이집트에는 피라미드와 스핑크스가 있어요'},
  {id:'za',code:'za',country:'남아공',capital:'프리토리아',continent:'아프리카',level:2,fact:'남아공은 무지개의 나라라 불려요'},
  {id:'au',code:'au',country:'호주',capital:'캔버라',continent:'오세아니아',level:3,fact:'호주에는 캥거루와 코알라가 살아요'},
  {id:'nz',code:'nz',country:'뉴질랜드',capital:'웰링턴',continent:'오세아니아',level:3,fact:'뉴질랜드는 반지의 제왕 촬영지예요'},
]

function buildQuestions(pool) {
  const qs = []
  for (const d of pool) {
    qs.push({...d, type:'flag', answer: d.country})
    if (level.value >= 3) qs.push({...d, type:'capital', answer: d.capital})
  }
  return qs
}

const level = ref(parseInt(localStorage.getItem('geo_level') || '1'))
const score = ref(0)
const correct = ref(0)
const leveled = ref(false)
const phase = ref('start')
const selectedId = ref(null)
const showFeedback = ref(false)
const lastCorrect = ref(false)
const correctAnswerText = ref('')
const factText = ref('')
const fbProgress = ref(100)
const curQ = ref({})
const options = ref([])
const qIdx = ref(0)
const totalQ = ref(10)
const timeLeft = ref(15)
const maxTime = ref(15)
let fbTimer = null
let countTimer = null
let queue = []

function getPool() {
  const maxLv = level.value <= 2 ? 1 : level.value <= 4 ? 2 : 3
  return geoData.filter(d => d.level <= maxLv)
}

function speak(text) {
  if (!window.speechSynthesis) return
  window.speechSynthesis.cancel()
  const u = new SpeechSynthesisUtterance(text)
  u.lang = 'ko-KR'; u.rate = 0.9
  window.speechSynthesis.speak(u)
}

function shuffle(a){const r=[...a];for(let i=r.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[r[i],r[j]]=[r[j],r[i]]}return r}

function startGame() {
  score.value=0; correct.value=0; leveled.value=false; qIdx.value=0
  maxTime.value = level.value <= 2 ? 15 : level.value <= 4 ? 12 : 10
  const pool = getPool()
  queue = shuffle(buildQuestions(pool)).slice(0, totalQ.value)
  rec.start(level.value)
  phase.value = 'play'
  loadQuestion()
}

function loadQuestion() {
  if (qIdx.value >= totalQ.value) { endGame(); return }
  const q = queue[qIdx.value]
  curQ.value = q
  selectedId.value = null; showFeedback.value = false

  const pool = getPool()
  const wrongs = shuffle(pool.filter(d => d.id !== q.id)).slice(0, 3)
  options.value = shuffle([pool.find(d=>d.id===q.id) || q, ...wrongs])

  clearInterval(countTimer)
  timeLeft.value = maxTime.value
  countTimer = setInterval(() => {
    timeLeft.value--
    if (timeLeft.value <= 0) { clearInterval(countTimer); autoFail() }
  }, 1000)

  speak(q.type === 'flag' ? '이 국기는 어느 나라일까요?' : q.country + '의 수도는 어디일까요?')
}

function autoFail() {
  selectedId.value = '__timeout__'
  correctAnswerText.value = curQ.value.answer
  factText.value = ''
  triggerFeedback(false)
}

function selectAnswer(opt) {
  if (selectedId.value !== null) return
  clearInterval(countTimer)
  selectedId.value = opt.id
  const isCorrect = opt.id === curQ.value.id
  if (isCorrect) {
    correct.value++
    score.value += 10 + timeLeft.value
    factText.value = curQ.value.fact || ''
    speak('정답!')
  } else {
    correctAnswerText.value = curQ.value.answer
    factText.value = ''
    speak('아쉬워요! 정답은 ' + curQ.value.answer)
  }
  triggerFeedback(isCorrect)
}

function triggerFeedback(isCorrect) {
  lastCorrect.value = isCorrect
  showFeedback.value = true
  fbProgress.value = 100
  clearInterval(fbTimer)
  const duration = isCorrect ? 1600 : 2200
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
  clearInterval(countTimer); phase.value = 'result'
  const threshold = level.value <= 2 ? 7 : level.value <= 4 ? 8 : 9
  const won = correct.value >= threshold
  if (won) {
    level.value++; localStorage.setItem('geo_level', level.value)
    leveled.value = true; speak('레벨업!')
  }
  await rec.end({ won, leveledUp: leveled.value, score: score.value })
}

function goBack() { clearInterval(fbTimer); clearInterval(countTimer); router.push('/games') }
onUnmounted(() => { clearInterval(fbTimer); clearInterval(countTimer) })
</script>

<style scoped>
.geo-game { min-height:100vh; background:linear-gradient(160deg,#ecfeff 0%,#cffafe 50%,#a5f3fc 100%); font-family:'Noto Sans KR',sans-serif; }
.game-header { display:flex; justify-content:space-between; align-items:center; padding:12px 16px; background:rgba(255,255,255,0.7); backdrop-filter:blur(10px); border-bottom:1px solid rgba(255,255,255,0.5); }
.back-btn { background:#0891b2; color:#fff; border:none; padding:8px 14px; border-radius:20px; cursor:pointer; font-size:14px; font-weight:600; }
.level-badge,.score-badge { background:#0891b2; color:#fff; padding:6px 14px; border-radius:20px; font-weight:700; font-size:14px; }
.start-screen { display:flex; flex-direction:column; align-items:center; padding:30px 20px; }
.globe-icon { font-size:80px; margin-bottom:8px; }
.game-title { font-size:32px; font-weight:900; color:#0e7490; margin:8px 0; }
.game-desc { color:#0891b2; font-size:16px; margin-bottom:16px; }
.level-card { background:rgba(255,255,255,0.8); border-radius:16px; padding:14px 20px; width:100%; max-width:320px; margin-bottom:20px; }
.lv-row { display:flex; align-items:center; gap:10px; padding:7px 0; color:#374151; font-size:14px; border-bottom:1px solid #cffafe; }
.lv-row:last-child { border-bottom:none; }
.lv-row.active { color:#0e7490; font-weight:700; }
.lv-badge { background:#0891b2; color:#fff; font-size:11px; font-weight:700; padding:2px 8px; border-radius:10px; min-width:36px; text-align:center; }
.play-btn { background:linear-gradient(135deg,#0891b2,#0e7490); color:#fff; border:none; padding:16px 48px; border-radius:30px; font-size:20px; font-weight:800; cursor:pointer; box-shadow:0 4px 20px rgba(8,145,178,0.4); }
.play-screen { padding:12px 16px; max-width:500px; margin:0 auto; }
.play-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; }
.q-counter,.score-small { background:rgba(255,255,255,0.8); padding:6px 14px; border-radius:20px; font-size:14px; font-weight:700; color:#0e7490; }
.timer-ring { position:relative; width:48px; height:48px; }
.timer-text { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); font-size:13px; font-weight:800; color:#374151; }
.question-card { background:rgba(255,255,255,0.9); border-radius:24px; padding:24px; text-align:center; margin-bottom:16px; box-shadow:0 8px 32px rgba(8,145,178,0.1); }
.flag-wrap { display:flex; justify-content:center; margin-bottom:14px; }
.flag-img { width:200px; height:auto; border-radius:8px; box-shadow:0 4px 16px rgba(0,0,0,0.15); border:1px solid rgba(0,0,0,0.08); }
.q-text { font-size:18px; font-weight:700; color:#1f2937; }
.capital-question { display:flex; flex-direction:column; align-items:center; gap:8px; }
.flag-sm { width:64px; height:auto; border-radius:6px; box-shadow:0 2px 8px rgba(0,0,0,0.1); }
.country-name-big { font-size:32px; font-weight:900; color:#0e7490; }
.choices-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.choice-btn { background:rgba(255,255,255,0.9); border:2px solid transparent; border-radius:16px; padding:12px 8px; display:flex; flex-direction:column; align-items:center; gap:6px; cursor:pointer; transition:all 0.2s; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
.choice-btn:hover:not([disabled]) { transform:translateY(-2px); border-color:#0891b2; }
.choice-flag { width:48px; height:auto; border-radius:4px; box-shadow:0 1px 4px rgba(0,0,0,0.1); }
.choice-text { font-size:15px; font-weight:700; color:#374151; text-align:center; }
.choice-btn.correct { border-color:#10b981; background:#d1fae5; }
.choice-btn.wrong { border-color:#ef4444; background:#fee2e2; }
.feedback-overlay { position:fixed; inset:0; z-index:100; display:flex; align-items:center; justify-content:center; }
.fb-correct { background:rgba(16,185,129,0.92); }
.fb-wrong { background:rgba(239,68,68,0.92); }
.fb-content { text-align:center; padding:32px 40px; border-radius:24px; background:rgba(255,255,255,0.15); }
.fb-emoji { font-size:72px; margin-bottom:8px; }
.fb-title { font-size:32px; font-weight:900; color:#fff; margin-bottom:6px; }
.fb-answer { font-size:17px; color:rgba(255,255,255,0.9); margin-bottom:8px; }
.fb-answer strong { font-size:20px; }
.fb-fact { font-size:14px; color:rgba(255,255,255,0.85); font-style:italic; margin-bottom:10px; max-width:280px; }
.fb-bar-wrap { width:200px; height:6px; background:rgba(255,255,255,0.3); border-radius:3px; overflow:hidden; margin:0 auto; }
.fb-bar { height:100%; background:#fff; border-radius:3px; transition:width 0.05s linear; }
.fb-enter-active,.fb-leave-active { transition:opacity 0.2s,transform 0.2s; }
.fb-enter-from,.fb-leave-to { opacity:0; transform:scale(0.95); }
.result-screen { display:flex; flex-direction:column; align-items:center; padding:40px 20px; text-align:center; }
.trophy { width:100px; height:100px; margin-bottom:12px; }
.result-score { font-size:56px; font-weight:900; color:#0e7490; }
.result-detail { color:#0891b2; font-size:18px; margin:6px 0 16px; }
.level-up-badge { background:#0891b2; color:#fff; padding:10px 24px; border-radius:20px; font-size:18px; font-weight:800; margin-bottom:20px; }
.result-btns { display:flex; gap:12px; }
.rbtn { padding:14px 28px; border-radius:20px; border:none; font-size:16px; font-weight:700; cursor:pointer; }
.rbtn.retry { background:#0891b2; color:#fff; }
.rbtn.home { background:#fff; color:#0e7490; border:2px solid #0891b2; }
</style>
