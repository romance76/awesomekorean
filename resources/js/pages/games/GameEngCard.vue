<template>
  <div class="engcard-game">
    <div class="game-header">
      <button class="back-btn" @click="$router.push('/games')">← 뒤로</button>
      <div class="level-badge">레벨 {{ level }} 🇺🇸</div>
      <div class="score-badge">{{ score }}점</div>
    </div>

    <div v-if="phase==='start'" class="center-box">
      <div style="font-size:90px">📖</div>
      <h1 class="title">영어 단어 카드</h1>
      <p class="subtitle">영어 단어와 한국어 뜻을 맞춰요!</p>
      <div class="mode-btns">
        <button :class="['mode-btn', mode==='eng2kor'?'active':'']" @click="mode='eng2kor'">영어 → 한국어</button>
        <button :class="['mode-btn', mode==='kor2eng'?'active':'']" @click="mode='kor2eng'">한국어 → 영어</button>
      </div>
      <button class="start-btn" @click="startGame">시작하기 ▶</button>
    </div>

    <div v-if="phase==='play'" class="play-box">
      <div class="progress-row">
        <div class="progress-bar"><div class="progress-fill" :style="{width:(qIdx/totalQ*100)+'%'}"></div></div>
        <span class="q-count">{{ qIdx+1 }}/{{ totalQ }}</span>
      </div>
      <div v-if="cur" class="word-card">
        <div class="word-category">{{ cur.category }}</div>
        <div class="word-display">{{ mode==='eng2kor' ? cur.eng : cur.kor }}</div>
        <div class="word-phonetic" v-if="mode==='eng2kor'">{{ cur.phonetic }}</div>
      </div>
      <p class="question-text">{{ mode==='eng2kor' ? '한국어 뜻은 무엇인가요?' : '영어 단어는 무엇인가요?' }}</p>
      <div class="choices-grid">
        <button v-for="opt in cur?.opts" :key="opt"
          class="choice-btn" :disabled="answered" @click="answer(opt)">{{ opt }}</button>
      </div>
    </div>

    <div v-if="phase==='end'" class="end-box">
      <div style="font-size:80px">{{ correct>=totalQ*0.7?'🌟':'📚' }}</div>
      <h2 class="end-title">{{ correct>=totalQ*0.7?'영어 실력 UP!':'계속 연습해요!' }}</h2>
      <p class="end-score">{{ score }}점 · {{ correct }}/{{ totalQ }} 정답</p>
      <div v-if="leveled" class="levelup-badge">🎉 레벨업! 레벨 {{ level }}!</div>
      <GameResultExtras :rec="rec" slug="engcard" />
      <button class="start-btn" @click="startGame">다시 하기 🔄</button>
      <button class="home-btn" @click="$router.push('/games')">홈으로 🏠</button>
    </div>

    <Transition name="fb">
      <div v-if="showFeedback" class="feedback-overlay" :class="lastCorrect?'fb-correct':'fb-wrong'">
        <div class="fb-content">
          <div class="fb-emoji">{{ lastCorrect ? '🎉' : '😢' }}</div>
          <div class="fb-title">{{ lastCorrect ? '정답이에요!' : '아쉬워요!' }}</div>
          <div v-if="!lastCorrect && cur" class="fb-answer">
            정답: <strong>{{ mode==='eng2kor' ? cur.kor : cur.eng }}</strong>
          </div>
          <div v-if="cur" class="fb-extra">{{ cur.eng }} = {{ cur.kor }}</div>
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
const rec = useGameRecord('engcard')
const level = ref(parseInt(localStorage.getItem('engcard_level')||'1'))
const score = ref(0); const qIdx = ref(0); const correct = ref(0)
const leveled = ref(false); const answered = ref(false); const phase = ref('start')
const showFeedback = ref(false); const lastCorrect = ref(false); const fbProgress = ref(100)
const mode = ref('eng2kor')
let fbTimer = null
const totalQ = 12
const questions = ref([])
const cur = computed(() => questions.value[qIdx.value])

const allWords = [
  {eng:'apple',    kor:'사과',    phonetic:'애플',   category:'과일'},
  {eng:'banana',   kor:'바나나',  phonetic:'바나나', category:'과일'},
  {eng:'orange',   kor:'오렌지',  phonetic:'오렌지', category:'과일'},
  {eng:'grape',    kor:'포도',    phonetic:'그레입', category:'과일'},
  {eng:'cat',      kor:'고양이',  phonetic:'캣',    category:'동물'},
  {eng:'dog',      kor:'강아지',  phonetic:'독',    category:'동물'},
  {eng:'bird',     kor:'새',      phonetic:'버드',  category:'동물'},
  {eng:'fish',     kor:'물고기',  phonetic:'피쉬',  category:'동물'},
  {eng:'book',     kor:'책',      phonetic:'북',    category:'물건'},
  {eng:'pen',      kor:'펜',      phonetic:'펜',    category:'물건'},
  {eng:'bag',      kor:'가방',    phonetic:'백',    category:'물건'},
  {eng:'chair',    kor:'의자',    phonetic:'체어',  category:'물건'},
  {eng:'red',      kor:'빨간색',  phonetic:'레드',  category:'색상'},
  {eng:'blue',     kor:'파란색',  phonetic:'블루',  category:'색상'},
  {eng:'green',    kor:'초록색',  phonetic:'그린',  category:'색상'},
  {eng:'yellow',   kor:'노란색',  phonetic:'옐로우', category:'색상'},
  {eng:'school',   kor:'학교',    phonetic:'스쿨',  category:'장소'},
  {eng:'house',    kor:'집',      phonetic:'하우스', category:'장소'},
  {eng:'hospital', kor:'병원',    phonetic:'하스피틀', category:'장소'},
  {eng:'park',     kor:'공원',    phonetic:'파크',  category:'장소'},
  {eng:'happy',    kor:'행복한',  phonetic:'해피',  category:'감정'},
  {eng:'sad',      kor:'슬픈',    phonetic:'새드',  category:'감정'},
  {eng:'angry',    kor:'화난',    phonetic:'앵그리', category:'감정'},
  {eng:'tired',    kor:'피곤한',  phonetic:'타이어드', category:'감정'},
]

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
  const pool = shuffle(allWords).slice(0, totalQ)
  questions.value = pool.map(w => {
    const distractors = shuffle(allWords.filter(x=>x!==w)).slice(0,3)
    const opts = mode.value==='eng2kor'
      ? shuffle([w.kor, ...distractors.map(d=>d.kor)])
      : shuffle([w.eng, ...distractors.map(d=>d.eng)])
    return {...w, opts}
  })
  speak('영어 단어를 맞춰봐요!')
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
      if (qIdx.value >= totalQ) endGame()
    }
  }, 50)
}

function answer(opt) {
  if (answered.value) return
  answered.value = true
  const correctAns = mode.value==='eng2kor' ? cur.value.kor : cur.value.eng
  const isOk = opt === correctAns
  if (isOk) { score.value += 10; correct.value++ }
  speak(isOk ? '정답이에요!' : `정답은 ${correctAns}이에요`)
  triggerFeedback(isOk)
}

async function endGame() {
  phase.value = 'end'
  const passed = correct.value >= Math.ceil(totalQ * 0.7)
  if (passed) {
    level.value++; localStorage.setItem('engcard_level', level.value); leveled.value = true
    speak('영어 실력이 늘었어요! 레벨업!')
  } else speak('계속 연습하면 잘 할 수 있어요!')
  await rec.end({ won: passed, leveledUp: leveled.value, score: score.value })
  const token = localStorage.getItem('token')
  if (token) {
    try {
      await axios.post('/api/games/16/score',
        { score: score.value, level: level.value, result: passed?'win':'lose', duration: totalQ*6 },
        { headers: { Authorization: `Bearer ${token}` } })
    } catch(e) {}
  }
}
</script>

<style scoped>
.engcard-game { min-height:100vh; background:linear-gradient(135deg,#0f2027,#203a43,#2c5364); padding:16px; font-family:'Noto Sans KR',sans-serif; }
.game-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
.back-btn { background:rgba(255,255,255,.15); color:#fff; border:none; padding:8px 14px; border-radius:20px; cursor:pointer; font-size:14px; }
.level-badge,.score-badge { background:rgba(255,255,255,.15); color:#fff; padding:6px 14px; border-radius:20px; font-weight:700; font-size:14px; }
.center-box,.end-box { text-align:center; padding:40px 20px; }
.title { font-size:36px; color:#fff; font-weight:900; margin:10px 0; }
.subtitle { color:rgba(255,255,255,.8); font-size:16px; }
.mode-btns { display:flex; gap:10px; justify-content:center; margin:16px 0; }
.mode-btn { background:rgba(255,255,255,.15); color:rgba(255,255,255,.7); border:none; padding:10px 20px; border-radius:20px; font-size:14px; font-weight:600; cursor:pointer; transition:all .2s; }
.mode-btn.active { background:#06b6d4; color:#fff; }
.start-btn { background:#06b6d4; color:#fff; border:none; padding:14px 40px; border-radius:30px; font-size:20px; font-weight:800; cursor:pointer; margin:10px 6px; }
.home-btn { background:rgba(255,255,255,.2); color:#fff; border:none; padding:12px 28px; border-radius:30px; font-size:16px; font-weight:600; cursor:pointer; margin:10px 6px; }
.play-box { max-width:480px; margin:0 auto; }
.progress-row { display:flex; align-items:center; gap:10px; margin-bottom:20px; }
.progress-bar { flex:1; height:10px; background:rgba(255,255,255,.2); border-radius:5px; }
.progress-fill { height:100%; background:#06b6d4; border-radius:5px; transition:width .3s; }
.q-count { color:rgba(255,255,255,.8); font-size:13px; }
.word-card { background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.2); border-radius:24px; padding:36px 24px; margin-bottom:20px; text-align:center; }
.word-category { font-size:12px; color:#67e8f9; font-weight:600; text-transform:uppercase; letter-spacing:2px; margin-bottom:12px; }
.word-display { font-size:48px; font-weight:900; color:#fff; margin-bottom:8px; }
.word-phonetic { font-size:16px; color:rgba(255,255,255,.6); }
.question-text { color:rgba(255,255,255,.85); font-size:16px; font-weight:600; text-align:center; margin-bottom:16px; }
.choices-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.choice-btn { background:rgba(255,255,255,.9); color:#0f2027; border:none; padding:16px 10px; border-radius:16px; font-size:16px; font-weight:700; cursor:pointer; transition:all .15s; }
.choice-btn:hover:not(:disabled) { background:#fff; transform:scale(1.03); }
.choice-btn:disabled { cursor:default; }
.end-title { font-size:32px; color:#fff; font-weight:900; }
.end-score { color:rgba(255,255,255,.8); font-size:18px; }
.levelup-badge { background:#06b6d4; color:#fff; padding:10px 24px; border-radius:20px; font-weight:800; font-size:16px; display:inline-block; margin:14px 0; }
.feedback-overlay { position:fixed; inset:0; display:flex; align-items:center; justify-content:center; z-index:999; backdrop-filter:blur(4px); }
.fb-correct { background:rgba(16,185,129,.88); }
.fb-wrong { background:rgba(239,68,68,.88); }
.fb-content { text-align:center; color:#fff; padding:32px 48px; }
.fb-emoji { font-size:80px; margin-bottom:8px; }
.fb-title { font-size:34px; font-weight:900; margin-bottom:8px; }
.fb-answer { font-size:18px; margin-bottom:6px; }
.fb-extra { font-size:15px; opacity:.85; margin-bottom:12px; }
.fb-bar-wrap { width:200px; height:7px; background:rgba(255,255,255,.3); border-radius:4px; margin:0 auto; }
.fb-bar { height:100%; background:#fff; border-radius:4px; transition:width .05s linear; }
.fb-enter-active,.fb-leave-active { transition:opacity .25s; }
.fb-enter-from,.fb-leave-to { opacity:0; }
</style>
