<template>
  <div class="mathchallenge-game">
    <div class="game-header">
      <button class="back-btn" @click="goBack">← 뒤로</button>
      <div class="level-badge">레벨 {{ level }} 🧮</div>
      <div class="score">⭐ {{ score }}</div>
    </div>

    <div v-if="phase==='start'" class="center-box">
      <div style="font-size:80px">🧮</div>
      <h1 class="title">수학 챌린지</h1>
      <p class="subtitle">빠르게 계산해서 정답을 맞춰요!</p>
      <div class="level-info">
        <div>레벨 1-2: 사칙연산</div>
        <div>레벨 3-4: 괄호·분수</div>
        <div>레벨 5+: 방정식·제곱</div>
      </div>
      <button class="start-btn" @click="startGame">도전! 🚀</button>
    </div>

    <div v-if="phase==='play'" class="play-area">
      <div class="top-row">
        <div class="combo-badge" v-if="combo > 1">🔥 {{ combo }}콤보</div>
        <div class="timer-circle">
          <svg width="60" height="60" viewBox="0 0 60 60">
            <circle cx="30" cy="30" r="26" fill="none" stroke="rgba(255,255,255,0.2)" stroke-width="5"/>
            <circle cx="30" cy="30" r="26" fill="none" :stroke="timeLeft < 4 ? '#ef4444' : '#6366f1'" stroke-width="5"
              stroke-dasharray="163" :stroke-dashoffset="163*(1-timeLeft/maxTime)" stroke-linecap="round"
              transform="rotate(-90 30 30)"/>
          </svg>
          <span class="timer-text">{{ timeLeft }}</span>
        </div>
        <div class="qcount">{{ qIdx }}/{{ totalQ }}</div>
      </div>
      <div class="equation-card" :class="{shake: shaking}">
        <div class="equation-text">{{ equation }}</div>
        <div class="equation-sub" v-if="subHint">({{ subHint }})</div>
      </div>
      <div class="choices-grid">
        <button v-for="opt in options" :key="opt" class="choice-btn"
          :class="{correct: answered && opt===correctAns, wrong: answered && opt===picked && opt!==correctAns, disabled: answered}"
          :disabled="answered" @click="selectAnswer(opt)">
          {{ opt }}
        </button>
      </div>
      <div v-if="answered" class="feedback" :class="wasRight?'right':'wrong'">
        {{ wasRight ? '정답! 🎉' : '정답: ' + correctAns }}
      </div>
    </div>

    <div v-if="phase==='result'" class="result-box">
      <div style="font-size:80px">🏆</div>
      <div class="res-score">{{ score }}점</div>
      <div class="res-detail">{{ correct }}/{{ totalQ }} · 최대콤보 {{ maxCombo }}🔥</div>
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

const level = ref(parseInt(localStorage.getItem('mathchallenge_level') || '1'))
const score = ref(0)
const correct = ref(0)
const combo = ref(0)
const maxCombo = ref(0)
const leveled = ref(false)
const phase = ref('start')
const answered = ref(false)
const wasRight = ref(false)
const picked = ref(null)
const correctAns = ref(0)
const equation = ref('')
const subHint = ref('')
const options = ref([])
const shaking = ref(false)
const qIdx = ref(0)
const totalQ = ref(12)
const timeLeft = ref(15)
const maxTime = ref(15)
let timer = null

function getMaxTime() { return level.value <= 2 ? 15 : level.value <= 4 ? 10 : 7 }

function speak(text) {
  if (!window.speechSynthesis) return
  window.speechSynthesis.cancel()
  const u = new SpeechSynthesisUtterance(text)
  u.lang = 'ko-KR'; u.rate = 0.9
  window.speechSynthesis.speak(u)
}

function shuffle(a){const r=[...a];for(let i=r.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[r[i],r[j]]=[r[j],r[i]]}return r}
function rand(a, b) { return Math.floor(Math.random()*(b-a+1))+a }

function generateQuestion() {
  subHint.value = ''
  if (level.value <= 2) {
    const ops = ['+','-','×','÷']
    const op = ops[rand(0,3)]
    let a,b,ans
    if (op==='+') { a=rand(10,99); b=rand(10,99); ans=a+b; equation.value=`${a} + ${b} = ?` }
    else if (op==='-') { a=rand(20,99); b=rand(1,a); ans=a-b; equation.value=`${a} - ${b} = ?` }
    else if (op==='×') { a=rand(2,12); b=rand(2,12); ans=a*b; equation.value=`${a} × ${b} = ?` }
    else { a=rand(2,9); b=rand(2,9); ans=a*b; equation.value=`${ans} ÷ ${a} = ?`; const t=ans; ans=b; }
    return ans
  } else if (level.value <= 4) {
    const type = rand(1,3)
    if (type===1) {
      const a=rand(2,9),b=rand(2,9),c=rand(1,10)
      const ans=(a+b)*c; equation.value=`(${a} + ${b}) × ${c} = ?`
      return ans
    } else if (type===2) {
      const a=rand(2,9),b=rand(2,9),c=rand(2,5)
      const ans=a*b+c; equation.value=`${a} × ${b} + ${c} = ?`
      return ans
    } else {
      const a=rand(1,5),b=rand(1,5)
      const num=a*rand(1,3), den=b
      const ans=num+a; equation.value=`${num}/${den} + ${a} = ?`
      subHint.value='분수를 정수로 변환'
      return ans
    }
  } else {
    const type = rand(1,3)
    if (type===1) {
      const a=rand(2,10); const ans=a*a; equation.value=`${a}² = ?`
      return ans
    } else if (type===2) {
      const ans=rand(1,9); equation.value=`x + ${ans+3} = ${ans*2+3}, x = ?`
      return ans
    } else {
      const a=rand(2,8),b=rand(1,5)
      const ans=a*a-b; equation.value=`${a}² - ${b} = ?`
      return ans
    }
  }
}

function startGame() {
  score.value=0; correct.value=0; combo.value=0; maxCombo.value=0; leveled.value=false
  qIdx.value=0; maxTime.value=getMaxTime()
  phase.value='play'
  nextRound()
}

function nextRound() {
  if (qIdx.value >= totalQ.value) { endGame(); return }
  qIdx.value++
  answered.value=false; wasRight.value=false; picked.value=null
  correctAns.value = generateQuestion()
  const wrongs = new Set()
  while (wrongs.size < 3) {
    const w = correctAns.value + rand(-10,10)
    if (w !== correctAns.value) wrongs.add(w)
  }
  options.value = shuffle([correctAns.value, ...wrongs])
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
  answered.value=true; wasRight.value=false; combo.value=0
  shaking.value=true; setTimeout(()=>shaking.value=false, 500)
  speak('시간 초과! 정답은 ' + correctAns.value)
  setTimeout(nextRound, 1800)
}

function selectAnswer(opt) {
  if (answered.value) return
  clearInterval(timer)
  answered.value=true; picked.value=opt
  wasRight.value = opt === correctAns.value
  if (wasRight.value) {
    correct.value++; combo.value++
    if (combo.value > maxCombo.value) maxCombo.value=combo.value
    score.value += 10 + timeLeft.value*2 + (combo.value>1?combo.value*5:0)
    speak(combo.value > 2 ? combo.value + '콤보! 대단해요!' : '정답!')
  } else {
    combo.value=0; shaking.value=true; setTimeout(()=>shaking.value=false, 500)
    speak('오답. 정답은 ' + correctAns.value)
  }
  setTimeout(nextRound, 1500)
}

function endGame() {
  clearInterval(timer); phase.value='result'
  if (correct.value >= (level.value<=2?9:level.value<=4?10:11)) {
    level.value++; localStorage.setItem('mathchallenge_level', level.value)
    leveled.value=true; speak('레벨업!')
  }
}

function goBack() { clearInterval(timer); router.push('/games') }
onUnmounted(() => clearInterval(timer))
</script>

<style scoped>
.mathchallenge-game { min-height:100vh; background:linear-gradient(135deg,#0c4a6e,#0369a1,#0284c7); padding:16px; font-family:'Noto Sans KR',sans-serif; }
.game-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
.back-btn { background:rgba(255,255,255,0.15); color:#fff; border:none; padding:8px 14px; border-radius:20px; cursor:pointer; font-size:14px; }
.level-badge,.score { background:rgba(255,255,255,0.15); color:#fff; padding:6px 14px; border-radius:20px; font-weight:700; }
.center-box { text-align:center; padding:30px 20px; }
.title { font-size:36px; color:#fff; font-weight:900; margin:10px 0; }
.subtitle { color:rgba(255,255,255,0.85); font-size:16px; }
.level-info { background:rgba(0,0,0,0.2); border-radius:12px; padding:12px 20px; color:#bae6fd; font-size:14px; line-height:1.8; margin:12px auto; max-width:220px; text-align:left; }
.start-btn { background:#fff; color:#0c4a6e; border:none; padding:14px 40px; border-radius:30px; font-size:20px; font-weight:800; cursor:pointer; margin-top:16px; }
.play-area { max-width:480px; margin:0 auto; }
.top-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
.combo-badge { background:#f59e0b; color:#fff; padding:6px 14px; border-radius:20px; font-weight:700; font-size:14px; }
.timer-circle { position:relative; width:60px; height:60px; }
.timer-text { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); color:#fff; font-weight:700; font-size:16px; }
.qcount { color:rgba(255,255,255,0.7); font-size:15px; font-weight:600; }
.equation-card { background:rgba(255,255,255,0.12); border-radius:20px; padding:30px 20px; text-align:center; margin-bottom:20px; }
.equation-text { font-size:44px; font-weight:900; color:#fff; letter-spacing:2px; }
.equation-sub { color:#7dd3fc; font-size:14px; margin-top:6px; }
@keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-8px)} 75%{transform:translateX(8px)} }
.shake { animation:shake 0.4s ease; }
.choices-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px; }
.choice-btn { background:rgba(255,255,255,0.9); color:#0c4a6e; border:none; padding:18px; border-radius:14px; font-size:26px; font-weight:800; cursor:pointer; transition:all 0.2s; }
.choice-btn:hover:not(.disabled) { transform:scale(1.04); background:#fff; }
.choice-btn.correct { background:#10b981; color:#fff; }
.choice-btn.wrong { background:#ef4444; color:#fff; }
.choice-btn.disabled { cursor:not-allowed; }
.feedback { text-align:center; padding:12px; border-radius:12px; font-size:17px; font-weight:700; }
.feedback.right { background:rgba(16,185,129,0.2); color:#a7f3d0; }
.feedback.wrong { background:rgba(239,68,68,0.2); color:#fca5a5; }
.result-box { text-align:center; padding:40px 20px; }
.res-score { font-size:56px; font-weight:900; color:#fff; }
.res-detail { color:rgba(255,255,255,0.8); font-size:16px; margin:8px 0; }
.levelup { background:#fff; color:#0c4a6e; padding:10px 20px; border-radius:20px; font-weight:800; font-size:18px; margin:14px auto; display:inline-block; }
.res-btns { display:flex; gap:12px; justify-content:center; margin-top:20px; }
.rbtn { background:rgba(255,255,255,0.9); color:#0c4a6e; border:none; padding:12px 28px; border-radius:20px; font-size:16px; font-weight:700; cursor:pointer; }
.rbtn.home { background:#0369a1; color:#fff; }
</style>
