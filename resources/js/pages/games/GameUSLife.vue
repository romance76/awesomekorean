<template>
  <div class="uslife-game">
    <div class="game-header">
      <button class="back-btn" @click="goBack">← 뒤로</button>
      <div class="level-badge">레벨 {{ level }} 🇺🇸</div>
      <div class="score">⭐ {{ score }}</div>
    </div>

    <div v-if="phase==='start'" class="center-box">
      <div style="font-size:80px">🇺🇸</div>
      <h1 class="title">미국 생활 상식</h1>
      <p class="subtitle">미국 한인으로 알아야 할 상식!</p>
      <div class="level-info">
        <div>레벨 1-2: 기초 (운전·세금)</div>
        <div>레벨 3-4: 중급 (의료·금융)</div>
        <div>레벨 5+: 고급 (법률·이민)</div>
      </div>
      <button class="start-btn" @click="startGame">시작! 📋</button>
    </div>

    <div v-if="phase==='play'" class="play-area">
      <div class="progress-row">
        <span>{{ qIdx }}/{{ totalQ }}</span>
        <div class="prog-bar"><div class="prog-fill" :style="{width:(qIdx/totalQ*100)+'%'}"></div></div>
        <span :style="{color:timeLeft<6?'#ef4444':'#93c5fd'}">{{ timeLeft }}초</span>
      </div>
      <div class="category-tag">{{ curQ.category }}</div>
      <div class="question-card">
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
      <div style="font-size:80px">🏆</div>
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

const usQuiz = [
  {level:1,category:'운전',question:'미국에서 오른쪽에 빨간불이 있어도 할 수 있는 것은?',options:['우회전','유턴','좌회전','직진'],answer:'우회전',explain:'미국은 "No Turn On Red" 표지가 없으면 빨간불에도 우회전 가능해요.'},
  {level:1,category:'운전',question:'미국 운전면허 필기시험에서 음주운전 혈중 알코올 기준은?',options:['0.08%','0.1%','0.05%','0.03%'],answer:'0.08%',explain:'미국 대부분의 주에서 BAC 0.08% 이상이면 DUI로 처벌받아요.'},
  {level:1,category:'세금',question:'미국 연방 소득세 신고 마감일은?',options:['4월 15일','1월 31일','12월 31일','6월 15일'],answer:'4월 15일',explain:'Tax Day는 매년 4월 15일이에요. 주말이면 다음 월요일로 연장돼요.'},
  {level:1,category:'생활',question:'미국에서 팁(Tip)의 일반적인 비율은?',options:['15-20%','5-10%','25-30%','1-5%'],answer:'15-20%',explain:'레스토랑에서는 보통 15~20%의 팁을 남기는 것이 에티켓이에요.'},
  {level:1,category:'의료',question:'미국에서 119 응급전화 번호는?',options:['911','119','999','000'],answer:'911',explain:'미국 응급전화는 911이에요. 경찰·소방·구급 모두 911로 연결돼요.'},
  {level:2,category:'의료',question:'미국 의료보험 중 65세 이상 노인을 위한 것은?',options:['Medicare','Medicaid','CHIP','ACA'],answer:'Medicare',explain:'Medicare는 65세 이상 시민권자/영주권자를 위한 연방 의료보험이에요.'},
  {level:2,category:'금융',question:'미국 신용점수(FICO Score)에서 "Good"은 몇 점부터인가요?',options:['670점 이상','500점 이상','800점 이상','600점 이상'],answer:'670점 이상',explain:'FICO 점수: 670-739=Good, 740-799=Very Good, 800+=Exceptional이에요.'},
  {level:2,category:'금융',question:'미국 은행 계좌의 예금자 보호 한도는?',options:['$250,000','$100,000','$500,000','$1,000,000'],answer:'$250,000',explain:'FDIC가 인당 $250,000까지 예금을 보호해요.'},
  {level:3,category:'이민',question:'미국 영주권(Green Card) 취득 후 시민권 신청 가능 기간은?',options:['5년 후','3년 후 (배우자)','7년 후','10년 후'],answer:'3년 후 (배우자)',explain:'미국 시민권자 배우자는 3년, 일반 영주권자는 5년 후 시민권 신청 가능해요.'},
  {level:3,category:'법률',question:'미국에서 Miranda Rights(미란다 원칙)는 언제 고지해야 하나요?',options:['체포 후 심문 시','경찰 접촉 시','법원 출두 시','구금 24시간 후'],answer:'체포 후 심문 시',explain:'체포 후 심문 전 "당신은 묵비권을 행사할 수 있습니다..." 고지가 필요해요.'},
]

const level = ref(parseInt(localStorage.getItem('uslife_level') || '1'))
const score = ref(0)
const correct = ref(0)
const leveled = ref(false)
const phase = ref('start')
const answered = ref(false)
const wasRight = ref(false)
const picked = ref('')
const curQ = ref({options:[],answer:'',explain:'',category:'',question:''})
const qIdx = ref(0)
const totalQ = ref(10)
const timeLeft = ref(25)
const maxTime = ref(25)
let timer = null
let queue = []

function getPool() {
  const maxLv=level.value<=2?1:level.value<=4?2:3
  return usQuiz.filter(q=>q.level<=maxLv)
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
  queue=shuffle(getPool()).slice(0,totalQ.value)
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
  if(wasRight.value){ correct.value++; score.value+=10+timeLeft.value; speak('정답!') }
  else speak('오답!')
  setTimeout(nextQuestion,3000)
}

function endGame() {
  clearInterval(timer); phase.value='result'
  if(correct.value>=7){ level.value++; localStorage.setItem('uslife_level',level.value); leveled.value=true; speak('레벨업!') }
}

function goBack() { clearInterval(timer); router.push('/games') }
onUnmounted(()=>clearInterval(timer))
</script>

<style scoped>
.uslife-game { min-height:100vh; background:linear-gradient(135deg,#1e3a5f,#1d4ed8,#2563eb); padding:16px; font-family:'Noto Sans KR',sans-serif; }
.game-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
.back-btn { background:rgba(255,255,255,0.15); color:#fff; border:none; padding:8px 14px; border-radius:20px; cursor:pointer; font-size:14px; }
.level-badge,.score { background:rgba(255,255,255,0.15); color:#fff; padding:6px 14px; border-radius:20px; font-weight:700; }
.center-box { text-align:center; padding:30px 20px; }
.title { font-size:34px; color:#fff; font-weight:900; margin:10px 0; }
.subtitle { color:rgba(255,255,255,0.85); font-size:16px; }
.level-info { background:rgba(0,0,0,0.2); border-radius:12px; padding:12px 20px; color:#bfdbfe; font-size:14px; line-height:1.8; margin:12px auto; max-width:240px; text-align:left; }
.start-btn { background:#fff; color:#1e3a5f; border:none; padding:14px 40px; border-radius:30px; font-size:20px; font-weight:800; cursor:pointer; margin-top:16px; }
.play-area { max-width:500px; margin:0 auto; }
.progress-row { display:flex; align-items:center; gap:10px; margin-bottom:12px; color:rgba(255,255,255,0.7); font-size:14px; }
.prog-bar { flex:1; height:8px; background:rgba(255,255,255,0.2); border-radius:4px; overflow:hidden; }
.prog-fill { height:100%; background:#3b82f6; border-radius:4px; transition:width 0.3s; }
.category-tag { display:inline-block; background:rgba(59,130,246,0.4); color:#93c5fd; font-size:12px; font-weight:700; padding:4px 12px; border-radius:12px; margin-bottom:10px; text-transform:uppercase; letter-spacing:1px; }
.question-card { background:rgba(255,255,255,0.1); border-radius:18px; padding:22px 20px; margin-bottom:16px; }
.q-text { color:#fff; font-size:19px; font-weight:600; line-height:1.5; }
.choices-col { display:flex; flex-direction:column; gap:8px; margin-bottom:12px; }
.choice-btn { background:rgba(255,255,255,0.9); color:#1e3a5f; border:none; padding:14px 16px; border-radius:12px; font-size:15px; font-weight:600; cursor:pointer; text-align:left; transition:all 0.2s; }
.choice-btn:hover:not(.disabled) { background:#fff; transform:translateX(4px); }
.choice-btn.correct { background:#10b981; color:#fff; }
.choice-btn.wrong { background:#ef4444; color:#fff; }
.choice-btn.disabled { cursor:not-allowed; }
.feedback { padding:14px 16px; border-radius:12px; }
.feedback.right { background:rgba(16,185,129,0.2); color:#a7f3d0; }
.feedback.wrong { background:rgba(239,68,68,0.2); color:#fca5a5; }
.explain { font-size:13px; font-weight:400; margin-top:6px; }
.result-box { text-align:center; padding:40px 20px; }
.res-score { font-size:52px; font-weight:900; color:#93c5fd; }
.res-detail { color:rgba(255,255,255,0.8); font-size:16px; margin:8px 0; }
.levelup { background:#3b82f6; color:#fff; padding:10px 20px; border-radius:20px; font-weight:800; font-size:18px; margin:14px auto; display:inline-block; }
.res-btns { display:flex; gap:12px; justify-content:center; margin-top:20px; }
.rbtn { background:rgba(255,255,255,0.9); color:#1e3a5f; border:none; padding:12px 28px; border-radius:20px; font-size:16px; font-weight:700; cursor:pointer; }
.rbtn.home { background:#1d4ed8; color:#fff; }
</style>
