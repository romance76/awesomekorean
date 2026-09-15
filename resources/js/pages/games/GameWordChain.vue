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
      <HowToPlay :lines="[
        '제시된 단어의 마지막 글자로 시작하는 단어를 보기 중에서 고르세요.',
        '두음법칙(예: 녀→여) 등은 문제에서 미리 알려줘요.',
        '이미 나온 단어는 다시 쓸 수 없어요.',
        '제한된 문제를 모두 이어가면 클리어!',
      ]" />
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
  <ConfettiBurst ref="confettiRef" />
  </GameShell>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import GameShell from '../../components/GameShell.vue'
import HowToPlay from '../../components/HowToPlay.vue'
import GameResultExtras from '../../components/GameResultExtras.vue'
import ConfettiBurst from '../../components/ConfettiBurst.vue'
import { useGameRecord } from '../../composables/useGameRecord'
import { useGameSound } from '../../composables/useGameSound'
const router = useRouter()
const rec = useGameRecord('word_chain')
const sound = useGameSound()
const confettiRef = ref(null)
const level = ref(parseInt(localStorage.getItem('wordchain_level')||'1'))
const score = ref(0); const qIdx = ref(0); const correct = ref(0)
const leveled = ref(false); const answered = ref(false); const phase = ref('start')
const showFeedback = ref(false); const lastCorrect = ref(false); const fbProgress = ref(100)
const chain = ref([]); const opts = ref([]); const correctAns = ref('')
let fbTimer = null
const totalQ = 8

// 끝말잇기 퀴즈 세트 — 예전엔 실제 쓰이지 않는 죽은 데이터(chainData, 24개)가
// 방치돼 있었고, 레벨이 표시만 될 뿐 난이도(문제 구성)에 전혀 영향을 못 줬음.
// level 태그를 추가해 레벨이 오를수록 더 어려운(오답이 주제상 비슷해 헷갈리는)
// 문제가 섞이도록 수정.
const quizSets = [
  { level:1, start:'기차', correct:'차도', wrong:['바람','나무','학교'] },
  { level:1, start:'도서관', correct:'관광', wrong:['나무','자동차','학교'] },
  { level:1, start:'나무', correct:'무지개', wrong:['학교','구름','도서관'] },
  { level:1, start:'학교', correct:'교실', wrong:['나무','바다','도서관'] },
  { level:1, start:'수박', correct:'박물관', wrong:['나무','학교','구름'] },
  { level:1, start:'고양이', correct:'이름', wrong:['나무','학교','박물관'] },
  { level:1, start:'바나나', correct:'나라', wrong:['학교','구름','도서관'] },
  { level:1, start:'어린이', correct:'이야기', wrong:['나무','학교','박물관'] },
  { level:1, start:'책상', correct:'상자', wrong:['나무','이야기','교실'] },
  { level:1, start:'지구', correct:'구름', wrong:['이름','나라','상자'] },
  { level:1, start:'모자', correct:'자전거', wrong:['이름','나라','교실'] },
  { level:1, start:'하늘', correct:'늘푸른나무', wrong:['이름','자전거','구름'] },
  { level:2, start:'컴퓨터', correct:'터널', wrong:['자동차','나무','학교'] },
  { level:2, start:'냉장고', correct:'고구마', wrong:['바나나','도서관','상자'] },
  { level:2, start:'양말', correct:'말투', wrong:['구름','상자','자전거'] },
  { level:2, start:'거북이', correct:'이불', wrong:['나무','교실','상자'] },
  { level:2, start:'토마토', correct:'토끼', wrong:['구름','학교','자전거'] },
  { level:2, start:'딸기', correct:'기린', wrong:['나무','상자','구름'] },
  { level:2, start:'무지개', correct:'개구리', wrong:['교실','자전거','이불'] },
  { level:2, start:'호랑이', correct:'이슬비', wrong:['구름','자전거','토끼'] },
  { level:3, start:'경찰서', correct:'서점', wrong:['도서관','우체국','병원'] },
  { level:3, start:'미술관', correct:'관악기', wrong:['박물관','음악실','전시회'] },
  { level:3, start:'환경보호', correct:'호수', wrong:['보호소','환경청','자연'] },
  { level:3, start:'국립공원', correct:'원숭이', wrong:['공원지기','산림청','동물원'] },
  { level:3, start:'졸업식', correct:'식당', wrong:['졸업장','시험장','학예회'] },
  { level:3, start:'환영회', correct:'회의실', wrong:['동창회','축하연','모임터'] },
]

function getPool() {
  const maxLv = level.value<=2?1:level.value<=4?2:3
  return quizSets.filter(q=>q.level<=maxLv)
}

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
  questions.value = shuffle(getPool()).slice(0, totalQ)
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
    sound.correct()
  } else {
    speak(`아쉬워요! 정답은 ${curQ.value.correct}이에요`)
    sound.wrong()
  }
  triggerFeedback(isOk)
}

async function endGame() {
  phase.value = 'end'
  const passed = correct.value >= Math.ceil(totalQ * 0.6)
  if (passed) {
    level.value++; localStorage.setItem('wordchain_level', level.value); leveled.value = true
    speak('끝말잇기 달인! 레벨업!')
    sound.levelUp(); confettiRef.value?.burst()
  } else {
    sound.gameOver()
    speak('잘 했어요! 다시 도전해봐요!')
  }
  await rec.end({ won: passed, leveledUp: leveled.value, score: score.value })
}
</script>

<style scoped>
.chain-game { flex:1; padding:16px; font-family:'Noto Sans KR',sans-serif; }
.center-box,.end-box { text-align:center; padding:40px 20px; }
.title { font-size:36px; color:#fff; font-weight:900; margin:10px 0; text-shadow:0 2px 12px rgba(0,0,0,.25); }
.subtitle { color:rgba(255,255,255,.8); font-size:16px; }
.rule-box { background:rgba(255,255,255,.1); backdrop-filter:blur(12px); border:1px solid rgba(255,255,255,.16); border-radius:18px; padding:14px 20px; margin:16px auto; max-width:320px; color:#c4b5fd; font-size:15px; box-shadow:0 8px 20px rgba(0,0,0,0.15); }
.rule-box strong { color:#fff; }
.start-btn { position:relative; overflow:hidden; background-image:linear-gradient(135deg,#a78bfa,#7c3aed); color:#fff; border:none; padding:16px 44px; border-radius:999px; font-size:20px; font-weight:800; cursor:pointer; margin:10px 6px; box-shadow:0 12px 28px -8px rgba(124,58,237,0.6), inset 0 1px 0 rgba(255,255,255,.35); transition:transform .15s ease, box-shadow .15s ease; }
.start-btn:hover { transform:translateY(-2px); box-shadow:0 16px 32px -8px rgba(124,58,237,0.65), inset 0 1px 0 rgba(255,255,255,.4); }
.start-btn:active { transform:translateY(0) scale(.97); }
.home-btn { background:rgba(255,255,255,.12); backdrop-filter:blur(10px); border:1px solid rgba(255,255,255,.2); color:#fff; padding:13px 30px; border-radius:999px; font-size:16px; font-weight:700; cursor:pointer; margin:10px 6px; transition:background .15s ease; }
.home-btn:hover { background:rgba(255,255,255,.2); }
.play-box { max-width:480px; margin:0 auto; }
.progress-row { display:flex; align-items:center; gap:10px; margin-bottom:16px; }
.progress-bar { flex:1; height:9px; background:rgba(255,255,255,.15); border-radius:999px; overflow:hidden; }
.progress-fill { height:100%; background-image:linear-gradient(90deg,#a78bfa,#f472b6); border-radius:999px; transition:width .3s; }
.q-count { color:rgba(255,255,255,.8); font-size:13px; font-weight:700; }
.chain-display { display:flex; flex-wrap:wrap; gap:6px; background:rgba(255,255,255,.06); backdrop-filter:blur(12px); border:1px solid rgba(255,255,255,.1); border-radius:18px; padding:14px; margin-bottom:16px; min-height:56px; align-items:center; }
.chain-word { display:flex; align-items:center; gap:6px; }
.word-text { background:rgba(255,255,255,.15); color:#fff; padding:6px 14px; border-radius:999px; font-size:14px; font-weight:600; }
.chain-word.latest .word-text { background-image:linear-gradient(135deg,#a78bfa,#7c3aed); font-size:15px; box-shadow:0 4px 12px -3px rgba(124,58,237,0.5); }
.arrow { color:rgba(255,255,255,.5); font-size:12px; }
.prompt-box { background:rgba(255,255,255,.08); backdrop-filter:blur(16px) saturate(160%); border:1px solid rgba(255,255,255,.15); border-radius:20px; padding:24px; text-align:center; margin-bottom:18px; box-shadow:0 12px 30px rgba(0,0,0,0.2), inset 0 1px 0 rgba(255,255,255,0.08); }
.prompt-label { color:rgba(255,255,255,.7); font-size:20px; }
.last-char { color:#fde68a; font-size:48px; font-weight:900; margin:0 4px; text-shadow:0 4px 16px rgba(253,230,138,0.35); }
.choices-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.choice-btn { background:rgba(255,255,255,.92); backdrop-filter:blur(10px); color:#2d1b69; border:1px solid rgba(255,255,255,0.5); padding:18px 10px; border-radius:16px; font-size:18px; font-weight:700; cursor:pointer; box-shadow:0 6px 16px rgba(0,0,0,0.1); transition:transform .15s ease, box-shadow .15s ease; }
.choice-btn:hover:not(:disabled) { background:#fff; transform:translateY(-2px) scale(1.02); box-shadow:0 10px 22px rgba(0,0,0,0.16); }
.choice-btn:disabled { cursor:default; }
.end-title { font-size:32px; color:#fff; font-weight:900; text-shadow:0 2px 12px rgba(0,0,0,.25); }
.end-score { color:rgba(255,255,255,.8); font-size:18px; }
.chain-review { background:rgba(255,255,255,.08); backdrop-filter:blur(12px); border:1px solid rgba(255,255,255,.12); border-radius:16px; padding:16px; margin:16px auto; max-width:400px; }
.review-label { color:rgba(255,255,255,.6); font-size:13px; margin-bottom:6px; }
.review-chain { color:#c4b5fd; font-size:14px; word-break:break-all; }
.levelup-badge { background-image:linear-gradient(135deg,#a78bfa,#7c3aed); color:#fff; padding:10px 24px; border-radius:999px; font-weight:800; font-size:17px; display:inline-block; margin:14px 0; box-shadow:0 10px 24px -6px rgba(124,58,237,0.55), inset 0 1px 0 rgba(255,255,255,.35); }
.feedback-overlay { position:fixed; inset:0; display:flex; align-items:center; justify-content:center; z-index:999; backdrop-filter:blur(8px); }
.fb-correct { background:rgba(16,185,129,.75); }
.fb-wrong { background:rgba(239,68,68,.75); }
.fb-content { text-align:center; color:#fff; padding:32px 48px; background:rgba(255,255,255,0.1); backdrop-filter:blur(10px); border:1px solid rgba(255,255,255,0.2); border-radius:24px; box-shadow:0 20px 50px rgba(0,0,0,0.3); }
.fb-emoji { font-size:80px; margin-bottom:8px; }
.fb-title { font-size:34px; font-weight:900; margin-bottom:8px; }
.fb-answer { font-size:17px; margin-bottom:16px; }
.fb-bar-wrap { width:200px; height:7px; background:rgba(255,255,255,.3); border-radius:999px; margin:0 auto; }
.fb-bar { height:100%; background:#fff; border-radius:999px; transition:width .05s linear; }
.fb-enter-active,.fb-leave-active { transition:opacity .25s; }
.fb-enter-from,.fb-leave-to { opacity:0; }
</style>
