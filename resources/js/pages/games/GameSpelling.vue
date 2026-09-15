<template>
  <GameShell title="한국어 맞춤법" icon="✍️" theme="dark" :level="level" :score="score"
    bg="linear-gradient(135deg,#7c2d12,#9a3412,#c2410c)">
  <div class="spelling-game">
    <div v-if="phase==='start'" class="center-box">
      <div style="font-size:80px">✍️</div>
      <h1 class="title">한국어 맞춤법</h1>
      <p class="subtitle">올바른 맞춤법을 골라요!</p>
      <div class="level-info">
        <div>레벨 1-2: 기초 맞춤법</div>
        <div>레벨 3-4: 띄어쓰기·외래어</div>
        <div>레벨 5+: 고급 문법</div>
      </div>
      <HowToPlay :lines="[
        '문장이나 단어가 나오면, 여러 보기 중 맞춤법이 올바른 것을 고르세요.',
        '헷갈리기 쉬운 맞춤법·띄어쓰기·외래어 표기 위주로 출제돼요.',
        '정답을 많이 맞힐수록 점수가 오르고 레벨이 올라가요.',
      ]" />
      <button class="start-btn" @click="startGame">시작! ✏️</button>
    </div>

    <div v-if="phase==='play'" class="play-area">
      <div class="progress-row">
        <span>{{ qIdx }}/{{ totalQ }}</span>
        <div class="prog-bar"><div class="prog-fill" :style="{width:(qIdx/totalQ*100)+'%'}"></div></div>
        <span :style="{color:timeLeft<5?'#ef4444':'#fff'}">{{ timeLeft }}초</span>
      </div>
      <div class="question-card">
        <div class="q-label">올바른 표현은?</div>
        <div class="q-context" v-if="curQ.context">
          <span v-html="curQ.context"></span>
        </div>
      </div>
      <div class="choices-col">
        <button v-for="opt in curQ.options" :key="opt.text" class="choice-btn"
          :class="{correct: answered && opt.correct, wrong: answered && !opt.correct && opt.text===picked, disabled: answered}"
          :disabled="answered" @click="selectAnswer(opt)">
          <span class="choice-text">{{ opt.text }}</span>
          <span v-if="answered && opt.correct" class="check-mark">✓</span>
        </button>
      </div>
      <div v-if="answered" class="feedback" :class="wasRight?'right':'wrong'">
        <div class="fb-main">{{ wasRight ? '정답! 🎉' : '오답!' }}</div>
        <div class="fb-explain">{{ curQ.explain }}</div>
      </div>
    </div>

    <div v-if="phase==='result'" class="result-box">
      <div style="font-size:80px">📝</div>
      <div class="res-score">{{ score }}점</div>
      <div class="res-detail">{{ correct }}/{{ totalQ }} 정답</div>
      <div v-if="leveled" class="levelup">🎉 레벨업! 레벨 {{ level }}!</div>
      <GameResultExtras :rec="rec" slug="spelling" />
      <div class="res-btns">
        <button class="rbtn" @click="startGame">다시 🔄</button>
        <button class="rbtn home" @click="goBack">홈 🏠</button>
      </div>
    </div>
  </div>
  <ConfettiBurst ref="confettiRef" />
  </GameShell>
</template>

<script setup>
import { ref, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import GameShell from '../../components/GameShell.vue'
import HowToPlay from '../../components/HowToPlay.vue'
import GameResultExtras from '../../components/GameResultExtras.vue'
import ConfettiBurst from '../../components/ConfettiBurst.vue'
import { useGameRecord } from '../../composables/useGameRecord'
import { useGameSound } from '../../composables/useGameSound'
const router = useRouter()
const rec = useGameRecord('spelling')
const sound = useGameSound()
const confettiRef = ref(null)

const quizDB = [
  {level:1,context:'밥을 <u>먹었어요</u> / <u>먹었어요</u>',options:[{text:'먹었어요',correct:true},{text:'먹었서요',correct:false}],explain:'"먹었어요"가 올바른 표현이에요.'},
  {level:1,context:'학교에 <u>갔어요</u> / <u>갔어요</u>',options:[{text:'갔어요',correct:true},{text:'갓어요',correct:false}],explain:'"갔어요"가 맞아요. 받침 ㅅ이 겹칩니다.'},
  {level:1,context:'',options:[{text:'되어야',correct:true},{text:'돼야',correct:false}],explain:'"되어야"와 "돼야" 둘 다 맞지만 "돼야"가 더 자연스러워요.'},
  {level:1,context:'',options:[{text:'안 돼요',correct:true},{text:'안 되요',correct:false}],explain:'"돼요"는 "되어요"의 줄임말이에요. "되요"는 틀려요.'},
  {level:1,context:'',options:[{text:'않아요',correct:true},{text:'안아요',correct:false}],explain:'"않아요" = 아니 하여요. "안아요" = 포옹하다.'},
  {level:1,context:'',options:[{text:'왠지',correct:true},{text:'웬지',correct:false}],explain:'"왠지"가 맞아요. 이유를 모를 때 씁니다.'},
  {level:1,context:'',options:[{text:'어떡해',correct:true},{text:'어떻해',correct:false}],explain:'"어떡해"가 맞아요. "어떻게 해"의 줄임말이에요.'},
  {level:1,context:'',options:[{text:'맞히다',correct:true},{text:'맞추다',correct:false}],explain:'"맞히다" = 정답을 맞추는 것. "맞추다" = 틈새를 채우는 것.'},
  {level:2,context:'',options:[{text:'오랜만에',correct:true},{text:'오랫만에',correct:false}],explain:'"오랜만에"가 맞아요. 오랜 + 만 + 에.'},
  {level:2,context:'',options:[{text:'일찍이',correct:true},{text:'일찌기',correct:false}],explain:'"일찍이"가 맞아요. "일찍 + 이".'},
  {level:2,context:'',options:[{text:'금세',correct:true},{text:'금새',correct:false}],explain:'"금세"가 맞아요. "금시에"의 줄임말이에요.'},
  {level:2,context:'',options:[{text:'이따가',correct:true},{text:'있다가',correct:false}],explain:'"이따가"는 "잠시 후에"라는 뜻이에요.'},
  {level:2,context:'',options:[{text:'역할',correct:true},{text:'역활',correct:false}],explain:'"역할"이 맞아요. 외워두세요!'},
  {level:2,context:'',options:[{text:'깨끗이',correct:true},{text:'깨끗히',correct:false}],explain:'ㅅ 받침 뒤에는 "-이" 씁니다.'},
  {level:3,context:'',options:[{text:'통째로',correct:true},{text:'통채로',correct:false}],explain:'"통째로"가 맞아요. 전체를 뜻해요.'},
  {level:3,context:'',options:[{text:'굳이',correct:true},{text:'구지',correct:false}],explain:'"굳이"가 맞아요. 발음은 [구지].'},
  {level:1,context:'',options:[{text:'어이없다',correct:true},{text:'어의없다',correct:false}],explain:'"어이없다"가 맞아요. "어이"는 어처구니를 뜻해요.'},
  {level:1,context:'',options:[{text:'설거지',correct:true},{text:'설겆이',correct:false}],explain:'"설거지"가 맞아요.'},
  {level:1,context:'',options:[{text:'며칠',correct:true},{text:'몇일',correct:false}],explain:'"며칠"이 맞아요. "몇일"은 표준어가 아니에요.'},
  {level:1,context:'',options:[{text:'오랫동안',correct:true},{text:'오랜동안',correct:false}],explain:'"오랫동안"이 맞아요. 사이시옷이 들어갑니다.'},
  {level:1,context:'',options:[{text:'웃어른',correct:true},{text:'윗어른',correct:false}],explain:'위아래 대립이 없는 말은 "웃-"을 써요.'},
  {level:1,context:'',options:[{text:'개구쟁이',correct:true},{text:'개구장이',correct:false}],explain:'기술자를 뜻하지 않으면 "-쟁이"를 써요.'},
  {level:1,context:'',options:[{text:'얘기',correct:true},{text:'예기',correct:false}],explain:'"얘기"가 맞아요. "이야기"의 준말이에요.'},
  {level:1,context:'',options:[{text:'무릅쓰다',correct:true},{text:'무릎쓰다',correct:false}],explain:'"무릅쓰다"가 맞아요. 어려움을 참고 견딘다는 뜻이에요.'},
  {level:2,context:'',options:[{text:'케이크',correct:true},{text:'케잌',correct:false}],explain:'외래어 표기법상 "케이크"가 맞아요.'},
  {level:2,context:'',options:[{text:'초콜릿',correct:true},{text:'초콜렛',correct:false}],explain:'외래어 표기법상 "초콜릿"이 맞아요.'},
  {level:2,context:'',options:[{text:'리더십',correct:true},{text:'리더쉽',correct:false}],explain:'외래어 표기법상 "리더십"이 맞아요.'},
  {level:2,context:'',options:[{text:'액세서리',correct:true},{text:'악세사리',correct:false}],explain:'외래어 표기법상 "액세서리"가 맞아요.'},
  {level:2,context:'',options:[{text:'알코올',correct:true},{text:'알콜',correct:false}],explain:'외래어 표기법상 "알코올"이 맞아요.'},
  {level:2,context:'',options:[{text:'텔레비전',correct:true},{text:'텔레비젼',correct:false}],explain:'외래어 표기법상 "텔레비전"이 맞아요.'},
  {level:2,context:'',options:[{text:'콘테스트',correct:true},{text:'컨테스트',correct:false}],explain:'외래어 표기법상 "콘테스트"가 맞아요.'},
  {level:2,context:'',options:[{text:'파이팅',correct:true},{text:'화이팅',correct:false}],explain:'표준국어대사전에는 "파이팅"이 올라 있어요.'},
  {level:2,context:'',options:[{text:'할 수 있다',correct:true},{text:'할수있다',correct:false}],explain:'의존명사 "수"는 띄어 써요.'},
  {level:2,context:'',options:[{text:'폭발',correct:true},{text:'폭팔',correct:false}],explain:'"폭발"이 맞아요.'},
  {level:3,context:'',options:[{text:'웬만하면',correct:true},{text:'왠만하면',correct:false}],explain:'"웬"이 맞아요. "왠지"만 예외적으로 "왠"을 써요.'},
  {level:3,context:'',options:[{text:'봬요',correct:true},{text:'뵈요',correct:false}],explain:'"봬요"가 맞아요. "뵈어요"의 준말이에요.'},
  {level:3,context:'',options:[{text:'몇째',correct:true},{text:'몇 째',correct:false}],explain:'"몇째"는 한 단어로 붙여 써요.'},
  {level:3,context:'',options:[{text:'희한하다',correct:true},{text:'희안하다',correct:false}],explain:'"희한하다"가 맞아요.'},
  {level:3,context:'',options:[{text:'낭떠러지',correct:true},{text:'낭떨어지',correct:false}],explain:'"낭떠러지"가 맞아요.'},
  {level:3,context:'',options:[{text:'곤란하다',correct:true},{text:'곤난하다',correct:false}],explain:'"곤란하다"가 맞아요.'},
  {level:3,context:'',options:[{text:'어쭙잖다',correct:true},{text:'어줍잖다',correct:false}],explain:'"어쭙잖다"가 맞아요.'},
  {level:3,context:'',options:[{text:'부리나케',correct:true},{text:'불이나케',correct:false}],explain:'"부리나케"가 맞아요. 매우 급하게라는 뜻이에요.'},
  {level:3,context:'',options:[{text:'삼가다',correct:true},{text:'삼가하다',correct:false}],explain:'"삼가다"가 기본형이에요. "삼가해"는 틀려요.'},
  {level:3,context:'',options:[{text:'되뇌다',correct:true},{text:'되뇌이다',correct:false}],explain:'"되뇌다"가 맞아요.'},
  {level:3,context:'',options:[{text:'걸맞은',correct:true},{text:'걸맞는',correct:false}],explain:'"걸맞다"는 형용사라 "걸맞은"이 맞아요.'},
  {level:3,context:'',options:[{text:'안절부절못하다',correct:true},{text:'안절부절하다',correct:false}],explain:'"안절부절못하다"가 맞아요. "못"이 빠지면 틀려요.'},
  {level:3,context:'',options:[{text:'뒤처지다',correct:true},{text:'뒤쳐지다',correct:false}],explain:'경쟁에서 밀린다는 뜻은 "뒤처지다"예요.'},
  {level:3,context:'',options:[{text:'넉넉지 않다',correct:true},{text:'넉넉치 않다',correct:false}],explain:'안울림소리 받침 뒤에서는 "하"가 줄어요.'},
]

const level = ref(parseInt(localStorage.getItem('spelling_level') || '1'))
const score = ref(0)
const correct = ref(0)
const leveled = ref(false)
const phase = ref('start')
const answered = ref(false)
const wasRight = ref(false)
const picked = ref('')
const curQ = ref({options:[],explain:''})
const qIdx = ref(0)
const totalQ = ref(10)
const timeLeft = ref(20)
const maxTime = ref(20)
let timer = null
let queue = []

function getPool() {
  const maxLv = level.value<=2?1:level.value<=4?2:3
  return quizDB.filter(q=>q.level<=maxLv)
}

function speak(text) {
  if(!window.speechSynthesis) return
  window.speechSynthesis.cancel()
  const u = new SpeechSynthesisUtterance(text)
  u.lang='ko-KR'; u.rate=0.85
  window.speechSynthesis.speak(u)
}

function shuffle(a){const r=[...a];for(let i=r.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[r[i],r[j]]=[r[j],r[i]]}return r}

function startGame() {
  score.value=0; correct.value=0; leveled.value=false; qIdx.value=0
  maxTime.value = level.value<=2?20:level.value<=4?15:10
  const pool = getPool()
  queue = shuffle(pool).slice(0,totalQ.value)
  totalQ.value = queue.length // 낮은 레벨은 문제은행이 10개보다 적을 수 있어 실제 큐 길이로 맞춤 — 안 맞추면 배열 끝을 넘어가 크래시남
  rec.start(level.value)
  phase.value='play'
  nextQuestion()
}

function nextQuestion() {
  if(qIdx.value>=totalQ.value){endGame();return}
  const q = queue[qIdx.value]; qIdx.value++
  curQ.value = {...q, options: shuffle([...q.options])}
  answered.value=false; wasRight.value=false; picked.value=''
  speak('올바른 표현을 골라요')
  startTimer()
}

function startTimer() {
  clearInterval(timer); timeLeft.value=maxTime.value
  timer=setInterval(()=>{
    timeLeft.value--
    if(timeLeft.value<=0){clearInterval(timer);timeOut()}
  },1000)
}

function timeOut() {
  answered.value=true; wasRight.value=false
  const correctOpt = curQ.value.options.find(o=>o.correct)
  speak('시간 초과! 정답은 ' + correctOpt?.text)
  setTimeout(nextQuestion,2500)
}

function selectAnswer(opt) {
  if(answered.value) return
  clearInterval(timer)
  answered.value=true; picked.value=opt.text
  wasRight.value=opt.correct
  if(wasRight.value){ correct.value++; score.value+=10+timeLeft.value; speak('정답!'); sound.correct() }
  else { speak('오답! ' + curQ.value.explain); sound.wrong() }
  setTimeout(nextQuestion,2800)
}

async function endGame() {
  clearInterval(timer); phase.value='result'
  const won = correct.value >= 8
  if(won){
    level.value++; localStorage.setItem('spelling_level',level.value); leveled.value=true; speak('레벨업!')
    sound.levelUp(); confettiRef.value?.burst()
  } else { sound.gameOver() }
  await rec.end({ won, leveledUp: leveled.value, score: score.value })
}

function goBack() { clearInterval(timer); router.push('/games') }
onUnmounted(()=>clearInterval(timer))
</script>

<style scoped>
.spelling-game { flex:1; padding:16px; font-family:'Noto Sans KR',sans-serif; }
.center-box { text-align:center; padding:30px 20px; }
.title { font-size:36px; color:#fff; font-weight:900; margin:10px 0; text-shadow:0 2px 12px rgba(0,0,0,.25); }
.subtitle { color:rgba(255,255,255,0.85); font-size:16px; }
.level-info { background:rgba(255,255,255,0.1); backdrop-filter:blur(12px); border:1px solid rgba(255,255,255,0.18); border-radius:16px; padding:14px 20px; color:#fed7aa; font-size:14px; line-height:1.8; margin:16px auto; max-width:240px; text-align:left; box-shadow:0 8px 24px rgba(0,0,0,0.15); }
.start-btn { position:relative; overflow:hidden; background-image:linear-gradient(135deg,#fb923c,#ea580c); color:#fff; border:none; padding:16px 44px; border-radius:999px; font-size:20px; font-weight:800; cursor:pointer; margin-top:20px; box-shadow:0 12px 28px -8px rgba(234,88,12,0.6), inset 0 1px 0 rgba(255,255,255,.35); transition:transform .15s ease, box-shadow .15s ease; }
.start-btn:hover { transform:translateY(-2px); box-shadow:0 16px 32px -8px rgba(234,88,12,0.65), inset 0 1px 0 rgba(255,255,255,.4); }
.start-btn:active { transform:translateY(0) scale(.97); }
.play-area { max-width:480px; margin:0 auto; }
.progress-row { display:flex; align-items:center; gap:10px; margin-bottom:16px; color:rgba(255,255,255,0.8); font-size:14px; font-weight:700; }
.prog-bar { flex:1; height:9px; background:rgba(255,255,255,0.15); border-radius:999px; overflow:hidden; }
.prog-fill { height:100%; background-image:linear-gradient(90deg,#fb923c,#facc15); border-radius:999px; transition:width 0.3s; }
.question-card { background:rgba(255,255,255,0.1); backdrop-filter:blur(16px) saturate(160%); border:1px solid rgba(255,255,255,0.2); border-radius:22px; padding:26px 20px; text-align:center; margin-bottom:18px; box-shadow:0 12px 30px rgba(0,0,0,0.18), inset 0 1px 0 rgba(255,255,255,0.15); }
.q-label { color:rgba(255,255,255,0.7); font-size:14px; margin-bottom:8px; }
.q-context { color:#fff; font-size:20px; font-weight:600; }
.choices-col { display:flex; flex-direction:column; gap:12px; margin-bottom:14px; }
.choice-btn { background:rgba(255,255,255,0.92); backdrop-filter:blur(10px); color:#7c2d12; border:1px solid rgba(255,255,255,0.5); padding:17px 22px; border-radius:16px; font-size:18px; font-weight:700; cursor:pointer; display:flex; justify-content:space-between; align-items:center; box-shadow:0 6px 16px rgba(0,0,0,0.1); transition:transform .15s ease, box-shadow .15s ease, background .15s ease; }
.choice-btn:hover:not(.disabled) { background:#fff; transform:translateY(-2px) translateX(2px); box-shadow:0 10px 22px rgba(0,0,0,0.16); }
.choice-btn.correct { background-image:linear-gradient(135deg,#10b981,#059669); color:#fff; box-shadow:0 10px 24px -6px rgba(16,185,129,0.55); }
.choice-btn.wrong { background-image:linear-gradient(135deg,#f43f5e,#e11d48); color:#fff; box-shadow:0 10px 24px -6px rgba(244,63,94,0.5); }
.choice-btn.disabled { cursor:not-allowed; }
.check-mark { font-size:20px; }
.feedback { padding:16px 18px; border-radius:16px; backdrop-filter:blur(10px); }
.feedback.right { background:rgba(16,185,129,0.18); border:1px solid rgba(16,185,129,0.35); color:#a7f3d0; }
.feedback.wrong { background:rgba(239,68,68,0.18); border:1px solid rgba(239,68,68,0.35); color:#fca5a5; }
.fb-main { font-size:18px; font-weight:700; margin-bottom:4px; }
.fb-explain { font-size:14px; font-weight:400; }
.result-box { text-align:center; padding:40px 20px; }
.res-score { font-size:58px; font-weight:900; color:#fff; text-shadow:0 2px 16px rgba(0,0,0,0.3); }
.res-detail { color:rgba(255,255,255,0.8); font-size:16px; margin:8px 0; }
.levelup { background-image:linear-gradient(135deg,#fbbf24,#f59e0b); color:#fff; padding:10px 24px; border-radius:999px; font-weight:800; font-size:17px; margin:14px auto; display:inline-block; box-shadow:0 10px 24px -6px rgba(245,158,11,0.55), inset 0 1px 0 rgba(255,255,255,.35); }
.res-btns { display:flex; gap:12px; justify-content:center; margin-top:24px; }
.rbtn { background:rgba(255,255,255,0.92); backdrop-filter:blur(10px); color:#7c2d12; border:none; padding:13px 30px; border-radius:999px; font-size:16px; font-weight:800; cursor:pointer; box-shadow:0 6px 16px rgba(0,0,0,0.12); transition:transform .15s ease; }
.rbtn:hover { transform:translateY(-2px); }
.rbtn.home { background-image:linear-gradient(135deg,#f97316,#c2410c); color:#fff; }
</style>
