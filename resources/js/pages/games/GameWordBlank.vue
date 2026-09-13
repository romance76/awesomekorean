<template>
  <GameShell title="빈칸 채우기" icon="📝" theme="dark" :level="level" :score="score"
    bg="linear-gradient(135deg,#1a1a2e,#16213e,#0f3460)">
  <div class="wordblank-game">
    <div v-if="phase==='start'" class="center-box">
      <div style="font-size:90px">📝</div>
      <h1 class="title">빈칸 채우기</h1>
      <p class="subtitle">알맞은 단어로 빈칸을 채워요!</p>
      <button class="start-btn" @click="startGame">시작하기 ▶</button>
    </div>

    <div v-if="phase==='play'" class="play-box">
      <div class="progress-row">
        <div class="progress-bar"><div class="progress-fill" :style="{width:(qIdx/totalQ*100)+'%'}"></div></div>
        <span class="q-count">{{ qIdx+1 }}/{{ totalQ }}</span>
      </div>
      <div v-if="cur" class="sentence-box">
        <p class="sentence-text" v-html="cur.display"></p>
        <p class="sentence-hint">{{ cur.hint }}</p>
      </div>
      <div class="choices-grid">
        <button v-for="opt in cur?.opts" :key="opt"
          class="choice-btn" :disabled="answered" @click="answer(opt)">{{ opt }}</button>
      </div>
    </div>

    <div v-if="phase==='end'" class="end-box">
      <div style="font-size:80px">{{ correct>=totalQ*0.7?'🎊':'📖' }}</div>
      <h2 class="end-title">{{ correct>=totalQ*0.7?'완벽해요!':'계속 연습해요!' }}</h2>
      <p class="end-score">{{ score }}점 · {{ correct }}/{{ totalQ }} 정답</p>
      <div v-if="leveled" class="levelup-badge">🌟 레벨업! 레벨 {{ level }}!</div>
      <GameResultExtras :rec="rec" slug="word_blank" />
      <button class="start-btn" @click="startGame">다시 하기 🔄</button>
      <button class="home-btn" @click="$router.push('/games')">홈으로 🏠</button>
    </div>

    <Transition name="fb">
      <div v-if="showFeedback" class="feedback-overlay" :class="lastCorrect?'fb-correct':'fb-wrong'">
        <div class="fb-content">
          <div class="fb-emoji">{{ lastCorrect ? '🎉' : '😢' }}</div>
          <div class="fb-title">{{ lastCorrect ? '정답이에요!' : '아쉬워요!' }}</div>
          <div v-if="!lastCorrect && cur" class="fb-answer">정답은 「<strong>{{ cur.ans }}</strong>」이에요</div>
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
const rec = useGameRecord('word_blank')
const level = ref(parseInt(localStorage.getItem('wordblank_level')||'1'))
const score = ref(0); const qIdx = ref(0); const correct = ref(0)
const leveled = ref(false); const answered = ref(false); const phase = ref('start')
const showFeedback = ref(false); const lastCorrect = ref(false); const fbProgress = ref(100)
let fbTimer = null
const totalQ = 10
const questions = ref([])
const cur = computed(() => questions.value[qIdx.value])

const allQ = [
  { sentence:'나는 학교에 ___ 갑니다.', ans:'걸어서', hint:'도보로 이동하는 방법', opts:['걸어서','날아서','헤엄쳐서','기어서'] },
  { sentence:'하늘은 ___ 색입니다.', ans:'파란', hint:'맑은 날 하늘 색', opts:['파란','빨간','노란','초록'] },
  { sentence:'봄에는 ___ 꽃이 핍니다.', ans:'벚꽃', hint:'봄의 상징', opts:['벚꽃','해바라기','장미','국화'] },
  { sentence:'아침에 일어나서 ___ 을 합니다.', ans:'세수', hint:'얼굴을 씻는 것', opts:['세수','요리','청소','쇼핑'] },
  { sentence:'나는 배가 ___ 밥을 먹었습니다.', ans:'고파서', hint:'배고플 때', opts:['고파서','불러서','아파서','뜨거워서'] },
  { sentence:'도서관에서는 ___ 해야 합니다.', ans:'조용히', hint:'도서관 예절', opts:['조용히','크게','빠르게','자유롭게'] },
  { sentence:'여름은 ___ 계절입니다.', ans:'더운', hint:'여름의 특징', opts:['더운','추운','시원한','따뜻한'] },
  { sentence:'국어, 영어, 수학은 학교 ___ 입니다.', ans:'과목', hint:'학교에서 배우는 것', opts:['과목','친구','선생님','교실'] },
  { sentence:'버스를 타려면 ___ 에 가야 합니다.', ans:'정류장', hint:'버스가 멈추는 곳', opts:['정류장','공항','항구','역'] },
  { sentence:'바다에서 수영할 때 ___ 가 필요합니다.', ans:'수영복', hint:'수영할 때 입는 옷', opts:['수영복','외투','교복','잠옷'] },
  { sentence:'음식이 너무 ___ 물을 마셨어요.', ans:'매워서', hint:'맵게 느낄 때', opts:['매워서','달아서','써서','시어서'] },
  { sentence:'친구 생일에 ___ 을 줬어요.', ans:'선물', hint:'특별한 날에 주는 것', opts:['선물','숙제','편지','벌금'] },
  { sentence:'비가 오면 ___ 을 씁니다.', ans:'우산', hint:'비를 막는 도구', opts:['우산','선글라스','장갑','목도리'] },
  { sentence:'밤에는 ___ 이 빛납니다.', ans:'별', hint:'밤하늘에 반짝이는 것', opts:['별','태양','구름','비'] },
  { sentence:'병원에서 의사에게 ___ 을 받아요.', ans:'진료', hint:'병원에 가는 이유', opts:['진료','수업','재판','훈련'] },
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
  questions.value = shuffle(allQ).slice(0, totalQ).map(q=>({
    ...q,
    display: q.sentence.replace('___', '<span class="blank">　　　</span>'),
    opts: shuffle(q.opts)
  }))
  speak('빈칸에 알맞은 단어를 골라요!')
}

function triggerFeedback(isCorrect) {
  lastCorrect.value = isCorrect; showFeedback.value = true; fbProgress.value = 100
  clearInterval(fbTimer)
  const dur = isCorrect ? 1300 : 2200; const step = 50/dur*100
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
  const isOk = opt === cur.value.ans
  if (isOk) { score.value += 10; correct.value++ }
  speak(isOk ? '정답이에요!' : `정답은 ${cur.value.ans}이에요`)
  triggerFeedback(isOk)
}

async function endGame() {
  phase.value = 'end'
  const passed = correct.value >= Math.ceil(totalQ * 0.7)
  if (passed) {
    level.value++; localStorage.setItem('wordblank_level', level.value); leveled.value = true
    speak('완벽해요! 레벨업!')
  } else speak('잘 했어요! 다시 도전해봐요!')
  await rec.end({ won: passed, leveledUp: leveled.value, score: score.value })
  const token = localStorage.getItem('token')
  if (token) {
    try {
      await axios.post('/api/games/13/score',
        { score: score.value, level: level.value, result: passed?'win':'lose', duration: totalQ*6 },
        { headers: { Authorization: `Bearer ${token}` } })
    } catch(e) {}
  }
}
</script>

<style scoped>
.wordblank-game { flex:1; padding:16px; font-family:'Noto Sans KR',sans-serif; }
.center-box,.end-box { text-align:center; padding:40px 20px; }
.title { font-size:36px; color:#fff; font-weight:900; margin:10px 0; }
.subtitle { color:rgba(255,255,255,.8); font-size:16px; }
.start-btn { background:#e94560; color:#fff; border:none; padding:14px 40px; border-radius:30px; font-size:20px; font-weight:800; cursor:pointer; margin:10px 6px; }
.home-btn { background:rgba(255,255,255,.2); color:#fff; border:none; padding:12px 28px; border-radius:30px; font-size:16px; font-weight:600; cursor:pointer; margin:10px 6px; }
.play-box { max-width:480px; margin:0 auto; }
.progress-row { display:flex; align-items:center; gap:10px; margin-bottom:20px; }
.progress-bar { flex:1; height:10px; background:rgba(255,255,255,.2); border-radius:5px; }
.progress-fill { height:100%; background:#e94560; border-radius:5px; transition:width .3s; }
.q-count { color:rgba(255,255,255,.8); font-size:13px; }
.sentence-box { background:rgba(255,255,255,.1); border-radius:20px; padding:28px 20px; margin-bottom:20px; text-align:center; }
.sentence-text { font-size:22px; color:#fff; font-weight:700; line-height:1.8; margin:0 0 10px; }
.sentence-hint { color:rgba(255,255,255,.6); font-size:14px; margin:0; }
:deep(.blank) { display:inline-block; border-bottom:3px solid #e94560; min-width:80px; color:transparent; }
.choices-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.choice-btn { background:rgba(255,255,255,.92); color:#1a1a2e; border:none; padding:18px 12px; border-radius:16px; font-size:17px; font-weight:700; cursor:pointer; transition:all .15s; }
.choice-btn:hover:not(:disabled) { background:#fff; transform:scale(1.03); }
.choice-btn:disabled { cursor:default; }
.end-title { font-size:32px; color:#fff; font-weight:900; }
.end-score { color:rgba(255,255,255,.8); font-size:18px; }
.levelup-badge { background:#e94560; color:#fff; padding:10px 24px; border-radius:20px; font-weight:800; font-size:16px; display:inline-block; margin:14px 0; }
.feedback-overlay { position:fixed; inset:0; display:flex; align-items:center; justify-content:center; z-index:999; backdrop-filter:blur(4px); }
.fb-correct { background:rgba(16,185,129,.88); }
.fb-wrong { background:rgba(239,68,68,.88); }
.fb-content { text-align:center; color:#fff; padding:32px 48px; }
.fb-emoji { font-size:80px; margin-bottom:8px; }
.fb-title { font-size:34px; font-weight:900; margin-bottom:8px; }
.fb-answer { font-size:18px; margin-bottom:16px; }
.fb-bar-wrap { width:200px; height:7px; background:rgba(255,255,255,.3); border-radius:4px; margin:0 auto; }
.fb-bar { height:100%; background:#fff; border-radius:4px; transition:width .05s linear; }
.fb-enter-active,.fb-leave-active { transition:opacity .25s; }
.fb-enter-from,.fb-leave-to { opacity:0; }
</style>
