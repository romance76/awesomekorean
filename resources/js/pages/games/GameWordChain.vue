<template>
  <GameShell title="끝말잇기" icon="🔗" theme="dark" :level="level" :score="score"
    bg="linear-gradient(135deg,#2d1b69,#4c1d95,#5b21b6)">
  <div class="chain-game">
    <div v-if="phase==='start'" class="center-box">
      <div style="font-size:90px">🔗</div>
      <h1 class="title">끝말잇기</h1>
      <p class="subtitle">마지막 글자로 시작하는 단어를 이어요!</p>
      <div class="rule-box">
        <p>예: <strong>사과</strong> → <strong>과자</strong> → <strong>자동차</strong></p>
      </div>
      <button class="start-btn" @click="startGame">시작하기 ▶</button>
    </div>

    <div v-if="phase==='play'" class="play-box">
      <div class="progress-row">
        <div class="progress-bar"><div class="progress-fill" :style="{width:(qIdx/totalQ*100)+'%'}"></div></div>
        <span class="q-count">{{ qIdx+1 }}/{{ totalQ }}</span>
      </div>

      <div class="chain-display">
        <div v-for="(w,i) in chain" :key="i" class="chain-word" :class="{latest: i===chain.length-1}">
          <span class="word-text">{{ w }}</span>
          <span v-if="i<chain.length-1" class="arrow">→</span>
        </div>
      </div>

      <div class="prompt-box">
        <span class="prompt-label">「</span>
        <span class="last-char">{{ lastChar }}</span>
        <span class="prompt-label">」으로 시작하는 단어는?</span>
      </div>

      <div class="choices-grid">
        <button v-for="opt in opts" :key="opt"
          class="choice-btn" :disabled="answered" @click="answer(opt)">{{ opt }}</button>
      </div>
    </div>

    <div v-if="phase==='end'" class="end-box">
      <div style="font-size:80px">{{ score>=totalQ*8?'🏆':'🎯' }}</div>
      <h2 class="end-title">{{ score>=totalQ*8?'끝말잇기 달인!':'잘 했어요!' }}</h2>
      <p class="end-score">{{ score }}점 · {{ correct }}/{{ totalQ }} 정답</p>
      <div class="chain-review">
        <p class="review-label">이번 체인:</p>
        <p class="review-chain">{{ chain.join(' → ') }}</p>
      </div>
      <div v-if="leveled" class="levelup-badge">🌟 레벨업! 레벨 {{ level }}!</div>
      <GameResultExtras :rec="rec" slug="word_chain" />
      <button class="start-btn" @click="startGame">다시 하기 🔄</button>
      <button class="home-btn" @click="$router.push('/games')">홈으로 🏠</button>
    </div>

    <Transition name="fb">
      <div v-if="showFeedback" class="feedback-overlay" :class="lastCorrect?'fb-correct':'fb-wrong'">
        <div class="fb-content">
          <div class="fb-emoji">{{ lastCorrect ? '🎉' : '😢' }}</div>
          <div class="fb-title">{{ lastCorrect ? '정답이에요!' : '아쉬워요!' }}</div>
          <div v-if="!lastCorrect" class="fb-answer">「{{ lastChar }}」으로 시작하는 단어: <strong>{{ correctAns }}</strong></div>
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
import axios from 'axios'
import GameShell from '../../components/GameShell.vue'
import GameResultExtras from '../../components/GameResultExtras.vue'
import { useGameRecord } from '../../composables/useGameRecord'
const router = useRouter()
const rec = useGameRecord('word_chain')
const level = ref(parseInt(localStorage.getItem('wordchain_level')||'1'))
const score = ref(0); const qIdx = ref(0); const correct = ref(0)
const leveled = ref(false); const answered = ref(false); const phase = ref('start')
const showFeedback = ref(false); const lastCorrect = ref(false); const fbProgress = ref(100)
const chain = ref([]); const opts = ref([]); const correctAns = ref('')
let fbTimer = null
const totalQ = 8

// 끝말잇기 단어 체인 데이터
const chainData = [
  { word:'사과', next:['과자','과일','과학'] },
  { word:'과자', next:['자동차','자전거','자연'] },
  { word:'자동차', next:['차도','차표','차가'] },
  { word:'기차', next:['차도','차장','차표'] },
  { word:'도서관', next:['관광','관심','관계'] },
  { word:'나무', next:['무지개','무서움','무릎'] },
  { word:'학교', next:['교실','교과서','교사'] },
  { word:'나라', next:['라면','라디오','라켓'] },
  { word:'바람', next:['람쥐','람사'] },
  { word:'구름', next:['름직하다'] },
  { word:'수박', next:['박수','박물관','박사'] },
  { word:'고양이', next:['이름','이사','이야기'] },
  { word:'하늘', next:['늘봄','늘다'] },
  { word:'바나나', next:['나라','나무','나비'] },
  { word:'어린이', next:['이름','이야기','이사'] },
  { word:'사랑', next:['랑데부'] },
  { word:'우리', next:['리본','리듬','리조트'] },
  { word:'모자', next:['자전거','자동차','자연'] },
  { word:'눈사람', next:['람쥐'] },
  { word:'책상', next:['상자','상어','상점'] },
  { word:'아이스크림', next:['림프','림프절'] },
  { word:'지구', next:['구름','구경','구청'] },
  { word:'별빛', next:['빛나다'] },
  { word:'봄바람', next:['람사'] },
]

// 더 체계적인 끝말잇기 퀴즈 세트
const quizSets = [
  { start:'기차', correct:'차도', wrong:['바람','나무','학교'] },
  { start:'도서관', correct:'관광', wrong:['나무','자동차','학교'] },
  { start:'나무', correct:'무지개', wrong:['학교','구름','도서관'] },
  { start:'학교', correct:'교실', wrong:['나무','바다','도서관'] },
  { start:'수박', correct:'박물관', wrong:['나무','학교','구름'] },
  { start:'고양이', correct:'이름', wrong:['나무','학교','박물관'] },
  { start:'바나나', correct:'나라', wrong:['학교','구름','도서관'] },
  { start:'어린이', correct:'이야기', wrong:['나무','학교','박물관'] },
  { start:'책상', correct:'상자', wrong:['나무','이야기','교실'] },
  { start:'지구', correct:'구름', wrong:['이름','나라','상자'] },
  { start:'모자', correct:'자전거', wrong:['이름','나라','교실'] },
  { start:'하늘', correct:'늘푸른나무', wrong:['이름','자전거','구름'] },
]

const lastChar = computed(() => chain.value.length > 0 ? chain.value[chain.value.length-1].slice(-1) : '')
const questions = ref([])
const curQ = computed(() => questions.value[qIdx.value])

function speak(t) {
  if (!window.speechSynthesis) return
  window.speechSynthesis.cancel()
  const u = new SpeechSynthesisUtterance(t); u.lang='ko-KR'; u.rate=0.9
  window.speechSynthesis.speak(u)
}
function shuffle(a){ return [...a].sort(()=>Math.random()-.5) }

function startGame() {
  score.value=0; qIdx.value=0; correct.value=0; leveled.value=false
  answered.value=false; showFeedback.value=false; phase.value='play'
  rec.start(level.value)
  questions.value = shuffle(quizSets).slice(0, totalQ)
  chain.value = [questions.value[0].start]
  loadQuestion()
  speak('끝말잇기를 시작해요!')
}

function loadQuestion() {
  if (!curQ.value) return
  const q = curQ.value
  opts.value = shuffle([q.correct, ...q.wrong.slice(0,3)])
  correctAns.value = q.correct
}

function triggerFeedback(isCorrect) {
  lastCorrect.value = isCorrect; showFeedback.value = true; fbProgress.value = 100
  clearInterval(fbTimer)
  const dur = isCorrect ? 1300 : 2400; const step = 50/dur*100
  fbTimer = setInterval(() => {
    fbProgress.value = Math.max(0, fbProgress.value - step)
    if (fbProgress.value <= 0) {
      clearInterval(fbTimer); showFeedback.value = false; answered.value = false
      qIdx.value++
      if (qIdx.value >= totalQ) { endGame(); return }
      chain.value.push(questions.value[qIdx.value].start)
      loadQuestion()
    }
  }, 50)
}

function answer(opt) {
  if (answered.value) return
  answered.value = true
  const isOk = opt === curQ.value.correct
  if (isOk) {
    score.value += 10; correct.value++
    chain.value.push(opt)
    speak(`정답! ${opt}`)
  } else {
    speak(`아쉬워요! 정답은 ${curQ.value.correct}이에요`)
  }
  triggerFeedback(isOk)
}

async function endGame() {
  phase.value = 'end'
  const passed = correct.value >= Math.ceil(totalQ * 0.6)
  if (passed) {
    level.value++; localStorage.setItem('wordchain_level', level.value); leveled.value = true
    speak('끝말잇기 달인! 레벨업!')
  } else speak('잘 했어요! 다시 도전해봐요!')
  await rec.end({ won: passed, leveledUp: leveled.value, score: score.value })
  const token = localStorage.getItem('token')
  if (token) {
    try {
      await axios.post('/api/games/10/score',
        { score: score.value, level: level.value, result: passed?'win':'lose', duration: totalQ*8 },
        { headers: { Authorization: `Bearer ${token}` } })
    } catch(e) {}
  }
}
</script>

<style scoped>
.chain-game { flex:1; padding:16px; font-family:'Noto Sans KR',sans-serif; }
.center-box,.end-box { text-align:center; padding:40px 20px; }
.title { font-size:36px; color:#fff; font-weight:900; margin:10px 0; }
.subtitle { color:rgba(255,255,255,.8); font-size:16px; }
.rule-box { background:rgba(255,255,255,.1); border-radius:16px; padding:14px 20px; margin:16px auto; max-width:320px; color:#c4b5fd; font-size:15px; }
.rule-box strong { color:#fff; }
.start-btn { background:#7c3aed; color:#fff; border:none; padding:14px 40px; border-radius:30px; font-size:20px; font-weight:800; cursor:pointer; margin:10px 6px; }
.home-btn { background:rgba(255,255,255,.2); color:#fff; border:none; padding:12px 28px; border-radius:30px; font-size:16px; font-weight:600; cursor:pointer; margin:10px 6px; }
.play-box { max-width:480px; margin:0 auto; }
.progress-row { display:flex; align-items:center; gap:10px; margin-bottom:16px; }
.progress-bar { flex:1; height:10px; background:rgba(255,255,255,.2); border-radius:5px; }
.progress-fill { height:100%; background:#a78bfa; border-radius:5px; transition:width .3s; }
.q-count { color:rgba(255,255,255,.8); font-size:13px; }
.chain-display { display:flex; flex-wrap:wrap; gap:6px; background:rgba(255,255,255,.08); border-radius:16px; padding:14px; margin-bottom:16px; min-height:56px; align-items:center; }
.chain-word { display:flex; align-items:center; gap:6px; }
.word-text { background:rgba(255,255,255,.15); color:#fff; padding:5px 12px; border-radius:20px; font-size:14px; font-weight:600; }
.chain-word.latest .word-text { background:#7c3aed; font-size:15px; }
.arrow { color:rgba(255,255,255,.5); font-size:12px; }
.prompt-box { background:rgba(255,255,255,.12); border-radius:18px; padding:22px; text-align:center; margin-bottom:18px; }
.prompt-label { color:rgba(255,255,255,.7); font-size:20px; }
.last-char { color:#fde68a; font-size:48px; font-weight:900; margin:0 4px; }
.choices-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.choice-btn { background:rgba(255,255,255,.92); color:#2d1b69; border:none; padding:18px 10px; border-radius:16px; font-size:18px; font-weight:700; cursor:pointer; transition:all .15s; }
.choice-btn:hover:not(:disabled) { background:#fff; transform:scale(1.03); }
.choice-btn:disabled { cursor:default; }
.end-title { font-size:32px; color:#fff; font-weight:900; }
.end-score { color:rgba(255,255,255,.8); font-size:18px; }
.chain-review { background:rgba(255,255,255,.1); border-radius:14px; padding:16px; margin:16px auto; max-width:400px; }
.review-label { color:rgba(255,255,255,.6); font-size:13px; margin-bottom:6px; }
.review-chain { color:#c4b5fd; font-size:14px; word-break:break-all; }
.levelup-badge { background:#7c3aed; color:#fff; padding:10px 24px; border-radius:20px; font-weight:800; font-size:16px; display:inline-block; margin:14px 0; }
.feedback-overlay { position:fixed; inset:0; display:flex; align-items:center; justify-content:center; z-index:999; backdrop-filter:blur(4px); }
.fb-correct { background:rgba(16,185,129,.88); }
.fb-wrong { background:rgba(239,68,68,.88); }
.fb-content { text-align:center; color:#fff; padding:32px 48px; }
.fb-emoji { font-size:80px; margin-bottom:8px; }
.fb-title { font-size:34px; font-weight:900; margin-bottom:8px; }
.fb-answer { font-size:17px; margin-bottom:16px; }
.fb-bar-wrap { width:200px; height:7px; background:rgba(255,255,255,.3); border-radius:4px; margin:0 auto; }
.fb-bar { height:100%; background:#fff; border-radius:4px; transition:width .05s linear; }
.fb-enter-active,.fb-leave-active { transition:opacity .25s; }
.fb-enter-from,.fb-leave-to { opacity:0; }
</style>
