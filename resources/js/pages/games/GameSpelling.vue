<template>
  <div class="spelling-game">
    <div class="game-header">
      <button class="back-btn" @click="goBack">← 뒤로</button>
      <div class="level-badge">레벨 {{ level }} ✍️</div>
      <div class="score">⭐ {{ score }}</div>
    </div>

    <div v-if="phase==='start'" class="center-box">
      <div style="font-size:80px">✍️</div>
      <h1 class="title">한국어 맞춤법</h1>
      <p class="subtitle">올바른 맞춤법을 골라요!</p>
      <div class="level-info">
        <div>레벨 1-2: 기초 맞춤법</div>
        <div>레벨 3-4: 띄어쓰기·외래어</div>
        <div>레벨 5+: 고급 문법</div>
      </div>
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
      <div class="res-btns">
        <button class="rbtn" @click="startGame">다시 🔄</button>
        <button class="rbtn home" @click="goBack">홈 🏠</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
const router = useRouter()

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
  if(wasRight.value){ correct.value++; score.value+=10+timeLeft.value; speak('정답!') }
  else { speak('오답! ' + curQ.value.explain) }
  setTimeout(nextQuestion,2800)
}

function endGame() {
  clearInterval(timer); phase.value='result'
  if(correct.value>=8){ level.value++; localStorage.setItem('spelling_level',level.value); leveled.value=true; speak('레벨업!') }
}

function goBack() { clearInterval(timer); router.push('/games') }
onUnmounted(()=>clearInterval(timer))
</script>

<style scoped>
.spelling-game { min-height:100vh; background:linear-gradient(135deg,#7c2d12,#9a3412,#c2410c); padding:16px; font-family:'Noto Sans KR',sans-serif; }
.game-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
.back-btn { background:rgba(255,255,255,0.15); color:#fff; border:none; padding:8px 14px; border-radius:20px; cursor:pointer; font-size:14px; }
.level-badge,.score { background:rgba(255,255,255,0.15); color:#fff; padding:6px 14px; border-radius:20px; font-weight:700; }
.center-box { text-align:center; padding:30px 20px; }
.title { font-size:36px; color:#fff; font-weight:900; margin:10px 0; }
.subtitle { color:rgba(255,255,255,0.85); font-size:16px; }
.level-info { background:rgba(0,0,0,0.2); border-radius:12px; padding:12px 20px; color:#fed7aa; font-size:14px; line-height:1.8; margin:12px auto; max-width:240px; text-align:left; }
.start-btn { background:#fff; color:#7c2d12; border:none; padding:14px 40px; border-radius:30px; font-size:20px; font-weight:800; cursor:pointer; margin-top:16px; }
.play-area { max-width:480px; margin:0 auto; }
.progress-row { display:flex; align-items:center; gap:10px; margin-bottom:16px; color:rgba(255,255,255,0.8); font-size:14px; }
.prog-bar { flex:1; height:8px; background:rgba(255,255,255,0.2); border-radius:4px; overflow:hidden; }
.prog-fill { height:100%; background:#f97316; border-radius:4px; transition:width 0.3s; }
.question-card { background:rgba(255,255,255,0.12); border-radius:20px; padding:24px 20px; text-align:center; margin-bottom:16px; }
.q-label { color:rgba(255,255,255,0.7); font-size:14px; margin-bottom:8px; }
.q-context { color:#fff; font-size:20px; font-weight:600; }
.choices-col { display:flex; flex-direction:column; gap:10px; margin-bottom:12px; }
.choice-btn { background:rgba(255,255,255,0.9); color:#7c2d12; border:none; padding:16px 20px; border-radius:12px; font-size:18px; font-weight:700; cursor:pointer; display:flex; justify-content:space-between; align-items:center; transition:all 0.2s; }
.choice-btn:hover:not(.disabled) { background:#fff; transform:translateX(4px); }
.choice-btn.correct { background:#10b981; color:#fff; }
.choice-btn.wrong { background:#ef4444; color:#fff; }
.choice-btn.disabled { cursor:not-allowed; }
.check-mark { font-size:20px; }
.feedback { padding:14px 16px; border-radius:12px; }
.feedback.right { background:rgba(16,185,129,0.2); color:#a7f3d0; }
.feedback.wrong { background:rgba(239,68,68,0.2); color:#fca5a5; }
.fb-main { font-size:18px; font-weight:700; margin-bottom:4px; }
.fb-explain { font-size:14px; font-weight:400; }
.result-box { text-align:center; padding:40px 20px; }
.res-score { font-size:56px; font-weight:900; color:#fff; }
.res-detail { color:rgba(255,255,255,0.8); font-size:16px; margin:8px 0; }
.levelup { background:#fff; color:#7c2d12; padding:10px 20px; border-radius:20px; font-weight:800; font-size:18px; margin:14px auto; display:inline-block; }
.res-btns { display:flex; gap:12px; justify-content:center; margin-top:20px; }
.rbtn { background:rgba(255,255,255,0.9); color:#7c2d12; border:none; padding:12px 28px; border-radius:20px; font-size:16px; font-weight:700; cursor:pointer; }
.rbtn.home { background:#c2410c; color:#fff; }
</style>
