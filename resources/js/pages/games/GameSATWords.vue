<template>
  <div class="sat-game">
    <div class="game-header">
      <button class="back-btn" @click="goBack">← 뒤로</button>
      <div class="level-badge">레벨 {{ level }} 📚</div>
      <div class="score">⭐ {{ score }}</div>
    </div>

    <div v-if="phase==='start'" class="center-box">
      <div style="font-size:80px">📚</div>
      <h1 class="title">SAT 영단어</h1>
      <p class="subtitle">대학 수준 영어 단어를 정복해요!</p>
      <div class="level-info">
        <div>레벨 1-2: 기초 수준</div>
        <div>레벨 3-4: 중급 수준</div>
        <div>레벨 5+: SAT 고급</div>
      </div>
      <button class="start-btn" @click="startGame">시작! 📖</button>
    </div>

    <div v-if="phase==='play'" class="play-area">
      <div class="progress-row">
        <div class="prog-bar"><div class="prog-fill" :style="{width:(qIdx/totalQ*100)+'%'}"></div></div>
        <span>{{ qIdx }}/{{ totalQ }}</span>
      </div>
      <div class="timer-row">
        <div class="timer-bar">
          <div class="timer-fill" :style="{width:(timeLeft/maxTime*100)+'%', background:timeLeft<5?'#ef4444':'#6366f1'}"></div>
        </div>
        <span class="timer-num">{{ timeLeft }}</span>
      </div>
      <div class="word-card">
        <div class="word-eng">{{ curQ.word }}</div>
        <div class="word-phonetic">{{ curQ.phonetic }}</div>
        <div class="word-pos">{{ curQ.pos }}</div>
        <button class="listen-btn" @click="listenWord">🔊</button>
      </div>
      <div class="choices-col">
        <button v-for="opt in options" :key="opt" class="choice-btn"
          :class="{correct: answered && opt===curQ.kor, wrong: answered && opt===picked && opt!==curQ.kor, disabled: answered}"
          :disabled="answered" @click="selectAnswer(opt)">
          {{ opt }}
        </button>
      </div>
      <div v-if="answered" class="feedback" :class="wasRight?'right':'wrong'">
        {{ wasRight ? '정답! 🎉' : '정답: ' + curQ.kor }}
        <div class="example" v-if="curQ.example">예문: {{ curQ.example }}</div>
      </div>
    </div>

    <div v-if="phase==='result'" class="result-box">
      <div style="font-size:80px">🎓</div>
      <div class="res-score">{{ score }}점</div>
      <div class="res-detail">{{ correct }}/{{ totalQ }} 정답</div>
      <div v-if="leveled" class="levelup">🎉 레벨업! 레벨 {{ level }}!</div>
      <div class="res-btns">
        <button class="rbtn" @click="startGame">다시 📖</button>
        <button class="rbtn home" @click="goBack">홈 🏠</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
const router = useRouter()

const wordBank = [
  // Level 1 (basic)
  {word:'Abundant',phonetic:'어번던트',pos:'형용사',kor:'풍부한',example:'Food is abundant here.',level:1},
  {word:'Benevolent',phonetic:'베네볼런트',pos:'형용사',kor:'자비로운',example:'She is a benevolent person.',level:1},
  {word:'Candid',phonetic:'캔디드',pos:'형용사',kor:'솔직한',example:'He gave a candid answer.',level:1},
  {word:'Diligent',phonetic:'딜리전트',pos:'형용사',kor:'부지런한',example:'She is a diligent student.',level:1},
  {word:'Eloquent',phonetic:'엘로퀀트',pos:'형용사',kor:'웅변적인',example:'He gave an eloquent speech.',level:1},
  {word:'Frugal',phonetic:'프루걸',pos:'형용사',kor:'검소한',example:'She lives a frugal life.',level:1},
  {word:'Generous',phonetic:'제너러스',pos:'형용사',kor:'관대한',example:'He is very generous.',level:1},
  {word:'Humble',phonetic:'험블',pos:'형용사',kor:'겸손한',example:'Stay humble.',level:1},
  // Level 2
  {word:'Ambiguous',phonetic:'앰비귀어스',pos:'형용사',kor:'모호한',example:'The instructions were ambiguous.',level:2},
  {word:'Brevity',phonetic:'브레비티',pos:'명사',kor:'간결함',example:'Brevity is the soul of wit.',level:2},
  {word:'Concise',phonetic:'컨사이스',pos:'형용사',kor:'간결한',example:'Write in a concise manner.',level:2},
  {word:'Deduce',phonetic:'디듀스',pos:'동사',kor:'추론하다',example:'She deduced the answer.',level:2},
  {word:'Empirical',phonetic:'엠피리컬',pos:'형용사',kor:'경험적인',example:'Empirical evidence is needed.',level:2},
  {word:'Feasible',phonetic:'피저블',pos:'형용사',kor:'실현가능한',example:'Is this plan feasible?',level:2},
  {word:'Inherent',phonetic:'인히런트',pos:'형용사',kor:'내재적인',example:'There are inherent risks.',level:2},
  {word:'Juxtapose',phonetic:'저스타포즈',pos:'동사',kor:'나란히 놓다',example:'Juxtapose the two images.',level:2},
  // Level 3 (SAT)
  {word:'Ameliorate',phonetic:'어밀리어레이트',pos:'동사',kor:'개선하다',example:'We must ameliorate conditions.',level:3},
  {word:'Capricious',phonetic:'커프리셔스',pos:'형용사',kor:'변덕스러운',example:'She is capricious.',level:3},
  {word:'Disparate',phonetic:'디스퍼릿',pos:'형용사',kor:'이질적인',example:'These are disparate ideas.',level:3},
  {word:'Ephemeral',phonetic:'이페머럴',pos:'형용사',kor:'일시적인',example:'Fame is ephemeral.',level:3},
  {word:'Fastidious',phonetic:'패스티디어스',pos:'형용사',kor:'까다로운',example:'He is fastidious about details.',level:3},
  {word:'Gregarious',phonetic:'그레게리어스',pos:'형용사',kor:'사교적인',example:'She is very gregarious.',level:3},
  {word:'Laconic',phonetic:'러코닉',pos:'형용사',kor:'간결한 말투의',example:'His reply was laconic.',level:3},
  {word:'Mendacious',phonetic:'멘데이셔스',pos:'형용사',kor:'거짓말하는',example:'He is mendacious.',level:3},
]

const level = ref(parseInt(localStorage.getItem('sat_level') || '1'))
const score = ref(0)
const correct = ref(0)
const leveled = ref(false)
const phase = ref('start')
const answered = ref(false)
const wasRight = ref(false)
const picked = ref('')
const curQ = ref({word:'',kor:'',phonetic:'',pos:'',example:'',level:1})
const options = ref([])
const qIdx = ref(0)
const totalQ = ref(10)
const timeLeft = ref(20)
const maxTime = ref(20)
let timer = null
let queue = []

function getPool() {
  const maxLv = level.value <= 2 ? 1 : level.value <= 4 ? 2 : 3
  return wordBank.filter(w => w.level <= maxLv)
}

function speak(text, lang = 'ko-KR') {
  if (!window.speechSynthesis) return
  window.speechSynthesis.cancel()
  const u = new SpeechSynthesisUtterance(text)
  u.lang = lang; u.rate = 0.75; u.pitch = 1.0
  window.speechSynthesis.speak(u)
}

function listenWord() {
  speak(curQ.value.word, 'en-US')
}

function shuffle(a){const r=[...a];for(let i=r.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[r[i],r[j]]=[r[j],r[i]]}return r}

function startGame() {
  score.value = 0; correct.value = 0; leveled.value = false; qIdx.value = 0
  maxTime.value = level.value <= 2 ? 20 : level.value <= 4 ? 15 : 10
  queue = shuffle(getPool()).slice(0, totalQ.value)
  phase.value = 'play'
  nextQuestion()
}

function nextQuestion() {
  if (qIdx.value >= totalQ.value) { endGame(); return }
  const q = queue[qIdx.value]; qIdx.value++
  curQ.value = q; answered.value = false; wasRight.value = false; picked.value = ''
  const pool = getPool()
  // 보기 중복 방지
  const set = new Set([q.kor])
  for (const w of shuffle(pool)) {
    if (set.size >= 4) break
    if (w.kor && w.kor !== q.kor) set.add(w.kor)
  }
  options.value = shuffle([...set])
  speak(q.word, 'en-US')
  startTimer()
}

function startTimer() {
  clearInterval(timer)
  timeLeft.value = maxTime.value
  timer = setInterval(() => {
    timeLeft.value--
    if (timeLeft.value <= 0) { clearInterval(timer); timeOut() }
  }, 1000)
}

function timeOut() {
  answered.value = true; wasRight.value = false
  speak('정답은 ' + curQ.value.kor)
  setTimeout(nextQuestion, 2500)
}

function selectAnswer(opt) {
  if (answered.value) return
  clearInterval(timer)
  answered.value = true; picked.value = opt
  wasRight.value = opt === curQ.value.kor
  if (wasRight.value) {
    correct.value++
    score.value += 10 + timeLeft.value
    speak('정답!')
  } else {
    speak('정답은 ' + curQ.value.kor)
  }
  setTimeout(nextQuestion, 2500)
}

function endGame() {
  clearInterval(timer)
  phase.value = 'result'
  if (correct.value >= 8) {
    level.value++
    localStorage.setItem('sat_level', level.value)
    leveled.value = true
    speak('레벨업!')
  }
}

function goBack() { clearInterval(timer); router.push('/games') }
onUnmounted(() => clearInterval(timer))
</script>

<style scoped>
.sat-game { min-height:100vh; background:linear-gradient(135deg,#1e1b4b,#312e81,#4338ca); padding:16px; font-family:'Noto Sans KR',sans-serif; }
.game-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
.back-btn { background:rgba(255,255,255,0.15); color:#fff; border:none; padding:8px 14px; border-radius:20px; cursor:pointer; font-size:14px; }
.level-badge,.score { background:rgba(255,255,255,0.15); color:#fff; padding:6px 14px; border-radius:20px; font-weight:700; }
.center-box { text-align:center; padding:30px 20px; }
.title { font-size:36px; color:#fff; font-weight:900; margin:10px 0; }
.subtitle { color:rgba(255,255,255,0.8); font-size:16px; }
.level-info { background:rgba(0,0,0,0.2); border-radius:12px; padding:12px 20px; color:#c7d2fe; font-size:14px; line-height:1.8; margin:12px auto; max-width:240px; text-align:left; }
.start-btn { background:#6366f1; color:#fff; border:none; padding:14px 40px; border-radius:30px; font-size:20px; font-weight:800; cursor:pointer; margin-top:16px; }
.play-area { max-width:480px; margin:0 auto; }
.progress-row { display:flex; align-items:center; gap:10px; margin-bottom:8px; color:rgba(255,255,255,0.7); font-size:13px; }
.prog-bar { flex:1; height:6px; background:rgba(255,255,255,0.2); border-radius:3px; overflow:hidden; }
.prog-fill { height:100%; background:#6366f1; border-radius:3px; transition:width 0.3s; }
.timer-row { display:flex; align-items:center; gap:10px; margin-bottom:16px; }
.timer-bar { flex:1; height:10px; background:rgba(255,255,255,0.2); border-radius:5px; overflow:hidden; }
.timer-fill { height:100%; border-radius:5px; transition:width 1s linear; }
.timer-num { color:#fff; font-weight:700; font-size:18px; min-width:28px; text-align:right; }
.word-card { background:rgba(255,255,255,0.12); border-radius:20px; padding:28px 20px; text-align:center; margin-bottom:16px; }
.word-eng { font-size:38px; font-weight:900; color:#fff; letter-spacing:1px; }
.word-phonetic { color:#a5b4fc; font-size:16px; margin:4px 0; }
.word-pos { color:#818cf8; font-size:13px; background:rgba(99,102,241,0.3); display:inline-block; padding:2px 10px; border-radius:10px; margin:4px 0; }
.listen-btn { background:rgba(255,255,255,0.15); color:#fff; border:none; padding:8px 16px; border-radius:20px; cursor:pointer; font-size:16px; margin-top:8px; }
.choices-col { display:flex; flex-direction:column; gap:8px; margin-bottom:12px; }
.choice-btn { background:rgba(255,255,255,0.9); color:#1e1b4b; border:none; padding:14px 20px; border-radius:12px; font-size:16px; font-weight:700; cursor:pointer; text-align:left; transition:all 0.2s; }
.choice-btn:hover:not(.disabled) { background:#fff; transform:translateX(4px); }
.choice-btn.correct { background:#10b981; color:#fff; }
.choice-btn.wrong { background:#ef4444; color:#fff; }
.choice-btn.disabled { cursor:not-allowed; }
.feedback { padding:12px 16px; border-radius:12px; font-size:15px; font-weight:700; }
.feedback.right { background:rgba(16,185,129,0.2); color:#a7f3d0; }
.feedback.wrong { background:rgba(239,68,68,0.2); color:#fca5a5; }
.example { font-size:13px; font-weight:400; font-style:italic; margin-top:6px; opacity:0.85; }
.result-box { text-align:center; padding:40px 20px; }
.res-score { font-size:56px; font-weight:900; color:#a5b4fc; }
.res-detail { color:rgba(255,255,255,0.8); font-size:16px; margin:8px 0; }
.levelup { background:#6366f1; color:#fff; padding:10px 20px; border-radius:20px; font-weight:800; font-size:18px; margin:14px auto; display:inline-block; }
.res-btns { display:flex; gap:12px; justify-content:center; margin-top:20px; }
.rbtn { background:rgba(255,255,255,0.9); color:#1e1b4b; border:none; padding:12px 28px; border-radius:20px; font-size:16px; font-weight:700; cursor:pointer; }
.rbtn.home { background:#4338ca; color:#fff; }
</style>
