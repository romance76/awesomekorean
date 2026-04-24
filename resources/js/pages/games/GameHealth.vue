<template>
  <div class="health-game">
    <div class="game-header">
      <button class="back-btn" @click="goBack">← 뒤로</button>
      <div class="level-badge">레벨 {{ level }} 🏥</div>
      <div class="score">⭐ {{ score }}</div>
    </div>

    <div v-if="phase==='start'" class="center-box">
      <div style="font-size:80px">🏥</div>
      <h1 class="title">건강 상식 퀴즈</h1>
      <p class="subtitle">건강한 생활을 위한 상식을 알아봐요!</p>
      <div class="level-info">현재 레벨: {{ level }}</div>
      <button class="start-btn" @click="startGame">시작! 💊</button>
    </div>

    <div v-if="phase==='play'" class="play-area">
      <div class="progress-row">
        <span>{{ qIdx }}/{{ totalQ }}</span>
        <div class="prog-bar"><div class="prog-fill" :style="{width:(qIdx/totalQ*100)+'%'}"></div></div>
        <span :style="{color:timeLeft<8?'#ef4444':'#6ee7b7'}">{{ timeLeft }}초</span>
      </div>
      <div class="question-card">
        <div class="q-emoji">{{ curQ.emoji }}</div>
        <div class="q-text">{{ curQ.question }}</div>
      </div>
      <div class="choices-col">
        <button v-for="opt in curQ.options" :key="opt" class="choice-btn"
          :class="{correct: answered && opt===curQ.answer, wrong: answered && opt===picked && opt!==curQ.answer, disabled: answered}"
          :disabled="answered" @click="selectAnswer(opt)">
          {{ opt }}
        </button>
      </div>
      <div v-if="answered" class="feedback" :class="wasRight?'right':'wrong'">
        <div>{{ wasRight ? '정답! 🎉' : '오답! 정답: ' + curQ.answer }}</div>
        <div class="explain">{{ curQ.explain }}</div>
      </div>
    </div>

    <div v-if="phase==='result'" class="result-box">
      <div style="font-size:80px">🌟</div>
      <div class="res-score">{{ score }}점</div>
      <div class="res-detail">{{ correct }}/{{ totalQ }} 정답</div>
      <div v-if="leveled" class="levelup">🎉 레벨업! 레벨 {{ level }}!</div>
      <GameResultExtras :rec="rec" slug="health" />
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
import GameResultExtras from '../../components/GameResultExtras.vue'
import { useGameRecord } from '../../composables/useGameRecord'
const router = useRouter()
const rec = useGameRecord('health')

const healthQuiz = [
  {emoji:'🚶',question:'하루 권장 걷기 목표는 몇 보인가요?',options:['5,000보','10,000보','3,000보','20,000보'],answer:'10,000보',explain:'하루 만 보 걷기가 건강에 도움이 돼요.',level:1},
  {emoji:'💧',question:'하루 물 권장 섭취량은?',options:['1리터','2리터','500ml','3리터'],answer:'2리터',explain:'성인은 하루 약 2리터(8잔)의 물을 마시는 게 좋아요.',level:1},
  {emoji:'🛌',question:'성인의 적정 수면 시간은?',options:['4-5시간','6-8시간','10-12시간','3-4시간'],answer:'6-8시간',explain:'성인은 하루 6~8시간 수면이 건강에 좋아요.',level:1},
  {emoji:'🧂',question:'고혈압 예방을 위해 줄여야 할 것은?',options:['나트륨(소금)','칼슘','단백질','비타민C'],answer:'나트륨(소금)',explain:'소금을 줄이면 혈압 조절에 도움이 돼요.',level:1},
  {emoji:'☀️',question:'비타민 D는 주로 어떻게 얻나요?',options:['햇빛','음식','수면','운동'],answer:'햇빛',explain:'햇볕을 쬐면 피부에서 비타민 D가 생성돼요.',level:1},
  {emoji:'🩺',question:'당뇨 예방에 가장 효과적인 것은?',options:['규칙적 운동과 식이조절','비타민 복용','충분한 수면만','약 복용'],answer:'규칙적 운동과 식이조절',explain:'적절한 운동과 균형 잡힌 식사가 당뇨 예방의 핵심이에요.',level:2},
  {emoji:'🧠',question:'치매 예방에 도움이 되는 활동은?',options:['독서와 두뇌 활동','장시간 TV 시청','과음','불규칙한 생활'],answer:'독서와 두뇌 활동',explain:'두뇌를 꾸준히 활동시키면 치매 예방에 도움이 돼요.',level:2},
  {emoji:'🦴',question:'골다공증 예방에 중요한 영양소는?',options:['칼슘과 비타민D','비타민C','철분','나트륨'],answer:'칼슘과 비타민D',explain:'칼슘과 비타민D는 뼈 건강에 필수적이에요.',level:2},
  {emoji:'❤️',question:'심장 건강에 나쁜 것은?',options:['흡연','규칙적 운동','과일 섭취','충분한 수면'],answer:'흡연',explain:'흡연은 심장 질환의 주요 위험 요인이에요.',level:2},
  {emoji:'🍎',question:'항산화 효과가 뛰어난 과일 색깔은?',options:['진한 빨강·파랑·보라','흰색','연한 노랑','갈색'],answer:'진한 빨강·파랑·보라',explain:'색이 진한 과채류일수록 항산화 성분이 풍부해요.',level:3},
]

const level = ref(parseInt(localStorage.getItem('health_level') || '1'))
const score = ref(0)
const correct = ref(0)
const leveled = ref(false)
const phase = ref('start')
const answered = ref(false)
const wasRight = ref(false)
const picked = ref('')
const curQ = ref({options:[],answer:'',explain:'',emoji:'',question:''})
const qIdx = ref(0)
const totalQ = ref(10)
const timeLeft = ref(25)
const maxTime = ref(25)
let timer = null
let queue = []

function getPool() {
  const maxLv=level.value<=2?1:level.value<=4?2:3
  return healthQuiz.filter(q=>q.level<=maxLv)
}

function speak(text) {
  if(!window.speechSynthesis) return
  window.speechSynthesis.cancel()
  const u = new SpeechSynthesisUtterance(text)
  u.lang='ko-KR'; u.rate=0.8; u.pitch=1.0
  window.speechSynthesis.speak(u)
}

function shuffle(a){const r=[...a];for(let i=r.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[r[i],r[j]]=[r[j],r[i]]}return r}

function startGame() {
  score.value=0; correct.value=0; leveled.value=false; qIdx.value=0
  queue=shuffle(getPool()).slice(0,totalQ.value)
  rec.start(level.value)
  phase.value='play'; nextQuestion()
}

function nextQuestion() {
  if(qIdx.value>=totalQ.value){endGame();return}
  const q=queue[qIdx.value]; qIdx.value++
  curQ.value={...q, options:shuffle([...q.options])}
  answered.value=false; wasRight.value=false; picked.value=''
  speak(q.question)
  startTimer()
}

function startTimer() {
  clearInterval(timer); timeLeft.value=maxTime.value
  timer=setInterval(()=>{ timeLeft.value--; if(timeLeft.value<=0){clearInterval(timer);timeOut()} },1000)
}

function timeOut() {
  answered.value=true; wasRight.value=false
  speak('시간 초과! 정답은 ' + curQ.value.answer)
  setTimeout(nextQuestion,3000)
}

function selectAnswer(opt) {
  if(answered.value) return
  clearInterval(timer); answered.value=true; picked.value=opt
  wasRight.value=opt===curQ.value.answer
  if(wasRight.value){ correct.value++; score.value+=10+timeLeft.value; speak('정답입니다!') }
  else speak('오답이에요. 정답은 ' + curQ.value.answer)
  setTimeout(nextQuestion,3000)
}

async function endGame() {
  clearInterval(timer); phase.value='result'
  const won = correct.value >= 7
  if(won){ level.value++; localStorage.setItem('health_level',level.value); leveled.value=true; speak('레벨업!') }
  await rec.end({ won, leveledUp: leveled.value, score: score.value })
}

function goBack() { clearInterval(timer); router.push('/games') }
onUnmounted(()=>clearInterval(timer))
</script>

<style scoped>
.health-game { min-height:100vh; background:linear-gradient(135deg,#ecfdf5,#d1fae5,#a7f3d0); padding:16px; font-family:'Noto Sans KR',sans-serif; }
.game-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
.back-btn { background:rgba(255,255,255,0.7); color:#065f46; border:none; padding:8px 14px; border-radius:20px; cursor:pointer; font-size:14px; font-weight:600; }
.level-badge,.score { background:rgba(255,255,255,0.7); color:#065f46; padding:6px 14px; border-radius:20px; font-weight:700; }
.center-box { text-align:center; padding:40px 20px; }
.title { font-size:34px; color:#065f46; font-weight:900; margin:10px 0; }
.subtitle { color:#047857; font-size:16px; }
.level-info { color:#059669; margin:10px 0; font-size:15px; }
.start-btn { background:#059669; color:#fff; border:none; padding:16px 44px; border-radius:30px; font-size:22px; font-weight:800; cursor:pointer; margin-top:20px; }
.play-area { max-width:500px; margin:0 auto; }
.progress-row { display:flex; align-items:center; gap:10px; margin-bottom:16px; color:#047857; font-size:15px; }
.prog-bar { flex:1; height:10px; background:rgba(5,150,105,0.2); border-radius:5px; overflow:hidden; }
.prog-fill { height:100%; background:#059669; border-radius:5px; transition:width 0.3s; }
.question-card { background:#fff; border-radius:20px; padding:24px 20px; text-align:center; margin-bottom:16px; box-shadow:0 4px 20px rgba(0,0,0,0.08); }
.q-emoji { font-size:56px; margin-bottom:12px; }
.q-text { color:#1f2937; font-size:19px; font-weight:700; line-height:1.5; }
.choices-col { display:flex; flex-direction:column; gap:10px; margin-bottom:12px; }
.choice-btn { background:#fff; color:#1f2937; border:2px solid #d1fae5; padding:14px 16px; border-radius:12px; font-size:16px; font-weight:600; cursor:pointer; text-align:left; transition:all 0.2s; box-shadow:0 2px 8px rgba(0,0,0,0.05); }
.choice-btn:hover:not(.disabled) { border-color:#059669; transform:translateX(4px); }
.choice-btn.correct { background:#059669; color:#fff; border-color:#059669; }
.choice-btn.wrong { background:#ef4444; color:#fff; border-color:#ef4444; }
.choice-btn.disabled { cursor:not-allowed; }
.feedback { padding:14px 16px; border-radius:14px; box-shadow:0 2px 8px rgba(0,0,0,0.08); }
.feedback.right { background:#d1fae5; color:#065f46; }
.feedback.wrong { background:#fee2e2; color:#991b1b; }
.explain { font-size:14px; font-weight:400; margin-top:6px; }
.result-box { text-align:center; padding:40px 20px; }
.res-score { font-size:52px; font-weight:900; color:#059669; }
.res-detail { color:#047857; font-size:16px; margin:8px 0; }
.levelup { background:#059669; color:#fff; padding:10px 20px; border-radius:20px; font-weight:800; font-size:18px; margin:14px auto; display:inline-block; }
.res-btns { display:flex; gap:12px; justify-content:center; margin-top:20px; }
.rbtn { background:#fff; color:#065f46; border:2px solid #059669; padding:12px 28px; border-radius:20px; font-size:16px; font-weight:700; cursor:pointer; }
.rbtn.home { background:#059669; color:#fff; border:none; }
</style>
