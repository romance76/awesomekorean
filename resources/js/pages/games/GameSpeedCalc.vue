<template>
  <div class="speedcalc-game">
    <div class="game-header">
      <button class="back-btn" @click="goBack">← 뒤로</button>
      <div class="level-badge">레벨 {{ level }} ⚡</div>
      <div class="score">⭐ {{ score }}</div>
    </div>

    <div v-if="phase==='start'" class="center-box">
      <div style="font-size:80px">⚡</div>
      <h1 class="title">스피드 계산</h1>
      <p class="subtitle">60초 안에 최대한 많이 맞춰요!</p>
      <div class="level-info">
        <div>레벨 1: 1-9 덧셈뺄셈</div>
        <div>레벨 3: 두 자리 연산</div>
        <div>레벨 5+: 곱셈·나눗셈 포함</div>
      </div>
      <div class="best-display">🏆 최고기록: {{ bestScore }}개</div>
      <button class="start-btn" @click="startGame">GO! ⚡</button>
    </div>

    <div v-if="phase==='play'" class="play-area">
      <div class="top-bar">
        <div class="timer-big" :class="{urgent: timeLeft<=10}">{{ timeLeft }}</div>
        <div class="count-display">
          <div class="count-correct">✅ {{ correct }}</div>
          <div class="count-wrong">❌ {{ wrong }}</div>
        </div>
      </div>
      <div class="equation-big">{{ equation }}</div>
      <div class="input-row">
        <input ref="answerInput" v-model="userAnswer" @keydown.enter="submitAnswer"
          type="number" class="answer-input" placeholder="답?"
          :class="{flash: flashing}" autocomplete="off" />
        <button class="submit-btn" @click="submitAnswer">확인</button>
      </div>
      <div class="feedback-flash" v-if="flashMsg" :class="flashType">{{ flashMsg }}</div>
      <div class="num-pad">
        <button v-for="n in [7,8,9,4,5,6,1,2,3,0]" :key="n" class="num-key" @click="appendNum(n)">{{ n }}</button>
        <button class="num-key del" @click="deleteLast">⌫</button>
        <button class="num-key neg" @click="toggleNeg">±</button>
      </div>
    </div>

    <div v-if="phase==='result'" class="result-box">
      <div style="font-size:80px">{{ correct >= 20 ? '🏆' : correct >= 10 ? '😊' : '💪' }}</div>
      <div class="res-score">{{ correct }}개 정답!</div>
      <div class="res-detail">정답 {{ correct }} · 오답 {{ wrong }}</div>
      <div class="res-best" v-if="isNewBest">🏆 신기록!</div>
      <div v-if="leveled" class="levelup">🎉 레벨업! 레벨 {{ level }}!</div>
      <GameResultExtras :rec="rec" slug="speed_calc" />
      <div class="res-btns">
        <button class="rbtn" @click="startGame">다시 ⚡</button>
        <button class="rbtn home" @click="goBack">홈 🏠</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, nextTick, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import GameResultExtras from '../../components/GameResultExtras.vue'
import { useGameRecord } from '../../composables/useGameRecord'
const router = useRouter()
const rec = useGameRecord('speed_calc')

const level = ref(parseInt(localStorage.getItem('speedcalc_level') || '1'))
const bestScore = ref(parseInt(localStorage.getItem('speedcalc_best') || '0'))
const phase = ref('start')
const score = ref(0)
const correct = ref(0)
const wrong = ref(0)
const equation = ref('')
const userAnswer = ref('')
const timeLeft = ref(60)
const flashing = ref(false)
const flashMsg = ref('')
const flashType = ref('ok')
const isNewBest = ref(false)
const leveled = ref(false)
const answerInput = ref(null)
let timer = null
let correctAns = 0

function speak(text) {
  if(!window.speechSynthesis) return
  window.speechSynthesis.cancel()
  const u = new SpeechSynthesisUtterance(text)
  u.lang='ko-KR'; u.rate=1.1
  window.speechSynthesis.speak(u)
}

function rand(a, b) { return Math.floor(Math.random()*(b-a+1))+a }

function genEquation() {
  const lv = level.value
  if(lv <= 2) {
    const a=rand(1,9), b=rand(1,9), op=Math.random()>0.5?'+':'-'
    if(op==='+'){correctAns=a+b;equation.value=`${a} + ${b} = ?`}
    else{const big=Math.max(a,b),small=Math.min(a,b);correctAns=big-small;equation.value=`${big} - ${small} = ?`}
  } else if(lv <= 4) {
    const ops=['+','-','×']
    const op=ops[rand(0,2)]
    if(op==='+'){const a=rand(10,99),b=rand(10,99);correctAns=a+b;equation.value=`${a} + ${b} = ?`}
    else if(op==='-'){const a=rand(20,99),b=rand(1,a);correctAns=a-b;equation.value=`${a} - ${b} = ?`}
    else{const a=rand(2,9),b=rand(2,9);correctAns=a*b;equation.value=`${a} × ${b} = ?`}
  } else {
    const ops=['+','-','×','÷','²']
    const op=ops[rand(0,4)]
    if(op==='+'){const a=rand(50,999),b=rand(50,999);correctAns=a+b;equation.value=`${a} + ${b} = ?`}
    else if(op==='-'){const a=rand(100,999),b=rand(1,a);correctAns=a-b;equation.value=`${a} - ${b} = ?`}
    else if(op==='×'){const a=rand(2,12),b=rand(2,12);correctAns=a*b;equation.value=`${a} × ${b} = ?`}
    else if(op==='÷'){const b=rand(2,9),a=b*rand(2,9);correctAns=a/b;equation.value=`${a} ÷ ${b} = ?`}
    else{const a=rand(2,15);correctAns=a*a;equation.value=`${a}² = ?`}
  }
}

function startGame() {
  score.value=0; correct.value=0; wrong.value=0; leveled.value=false; isNewBest.value=false
  phase.value='play'; timeLeft.value=60; flashMsg.value=''
  rec.start(level.value)
  genEquation(); userAnswer.value=''
  clearInterval(timer)
  timer=setInterval(()=>{
    timeLeft.value--
    if(timeLeft.value<=0){clearInterval(timer);endGame()}
  },1000)
  nextTick(()=>answerInput.value?.focus())
  speak('시작!')
}

function submitAnswer() {
  const val = parseInt(userAnswer.value)
  if(isNaN(val)) return
  userAnswer.value=''
  if(val===correctAns){
    correct.value++; score.value+=level.value*5
    flashMsg.value='✓'; flashType.value='ok'
  } else {
    wrong.value++
    flashMsg.value='✗ ' + correctAns; flashType.value='err'
  }
  flashing.value=true; setTimeout(()=>{flashing.value=false;flashMsg.value=''},500)
  genEquation()
  nextTick(()=>answerInput.value?.focus())
}

function appendNum(n) { userAnswer.value+=n; answerInput.value?.focus() }
function deleteLast() { userAnswer.value=userAnswer.value.slice(0,-1) }
function toggleNeg() {
  if(userAnswer.value.startsWith('-')) userAnswer.value=userAnswer.value.slice(1)
  else userAnswer.value='-'+userAnswer.value
}

async function endGame() {
  phase.value='result'
  if(correct.value>bestScore.value){
    bestScore.value=correct.value
    localStorage.setItem('speedcalc_best',correct.value)
    isNewBest.value=true
  }
  const threshold=level.value<=2?10:level.value<=4?18:25
  const won = correct.value >= threshold
  if(won){
    level.value++; localStorage.setItem('speedcalc_level',level.value)
    leveled.value=true; speak('레벨업!')
  } else speak('게임종료! ' + correct.value + '개 정답!')
  await rec.end({ won, leveledUp: leveled.value, score: correct.value })
}

function goBack() { clearInterval(timer); router.push('/games') }
onUnmounted(()=>clearInterval(timer))
</script>

<style scoped>
.speedcalc-game { min-height:100vh; background:linear-gradient(135deg,#1c1917,#292524,#44403c); padding:16px; font-family:'Noto Sans KR',sans-serif; color:#fff; }
.game-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
.back-btn { background:rgba(255,255,255,0.12); color:#fff; border:none; padding:8px 14px; border-radius:20px; cursor:pointer; font-size:14px; }
.level-badge,.score { background:rgba(255,255,255,0.12); color:#fff; padding:6px 14px; border-radius:20px; font-weight:700; }
.center-box { text-align:center; padding:30px 20px; }
.title { font-size:36px; color:#fff; font-weight:900; margin:10px 0; }
.subtitle { color:rgba(255,255,255,0.7); font-size:16px; }
.level-info { background:rgba(255,255,255,0.07); border-radius:12px; padding:12px 20px; color:#d6d3d1; font-size:14px; line-height:1.8; margin:12px auto; max-width:220px; text-align:left; }
.best-display { color:#fbbf24; font-size:16px; font-weight:700; margin:10px 0; }
.start-btn { background:#f59e0b; color:#1c1917; border:none; padding:14px 40px; border-radius:30px; font-size:22px; font-weight:800; cursor:pointer; margin-top:16px; }
.play-area { max-width:400px; margin:0 auto; }
.top-bar { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
.timer-big { font-size:52px; font-weight:900; color:#f59e0b; transition:color 0.3s; min-width:70px; text-align:center; }
.timer-big.urgent { color:#ef4444; animation:pulse 0.5s infinite; }
@keyframes pulse { 0%,100%{transform:scale(1)} 50%{transform:scale(1.1)} }
.count-display { display:flex; flex-direction:column; gap:4px; align-items:flex-end; }
.count-correct { color:#10b981; font-size:20px; font-weight:700; }
.count-wrong { color:#ef4444; font-size:20px; font-weight:700; }
.equation-big { font-size:52px; font-weight:900; text-align:center; margin-bottom:16px; background:rgba(255,255,255,0.07); border-radius:16px; padding:20px; letter-spacing:4px; }
.input-row { display:flex; gap:8px; margin-bottom:8px; }
.answer-input { flex:1; padding:14px 16px; font-size:28px; font-weight:700; text-align:center; background:rgba(255,255,255,0.1); border:2px solid rgba(255,255,255,0.3); color:#fff; border-radius:12px; outline:none; }
.answer-input:focus { border-color:#f59e0b; }
@keyframes flashAnim { 0%,100%{background:rgba(255,255,255,0.1)} 50%{background:rgba(245,158,11,0.3)} }
.answer-input.flash { animation:flashAnim 0.3s; }
.submit-btn { background:#f59e0b; color:#1c1917; border:none; padding:14px 20px; border-radius:12px; font-size:18px; font-weight:800; cursor:pointer; }
.feedback-flash { text-align:center; font-size:24px; font-weight:700; padding:8px; border-radius:10px; margin-bottom:8px; }
.feedback-flash.ok { color:#10b981; }
.feedback-flash.err { color:#ef4444; }
.num-pad { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; margin-top:8px; }
.num-key { background:rgba(255,255,255,0.12); color:#fff; border:none; padding:16px; border-radius:10px; font-size:20px; font-weight:700; cursor:pointer; transition:all 0.1s; }
.num-key:active { transform:scale(0.93); background:rgba(255,255,255,0.25); }
.num-key.del { background:rgba(239,68,68,0.3); color:#fca5a5; }
.num-key.neg { background:rgba(99,102,241,0.3); color:#a5b4fc; }
.result-box { text-align:center; padding:40px 20px; }
.res-score { font-size:52px; font-weight:900; color:#f59e0b; }
.res-detail { color:rgba(255,255,255,0.7); font-size:16px; margin:8px 0; }
.res-best { color:#fbbf24; font-size:20px; font-weight:800; margin:8px 0; }
.levelup { background:#f59e0b; color:#1c1917; padding:10px 20px; border-radius:20px; font-weight:800; font-size:18px; margin:14px auto; display:inline-block; }
.res-btns { display:flex; gap:12px; justify-content:center; margin-top:20px; }
.rbtn { background:rgba(255,255,255,0.9); color:#1c1917; border:none; padding:12px 28px; border-radius:20px; font-size:16px; font-weight:700; cursor:pointer; }
.rbtn.home { background:#f59e0b; color:#1c1917; }
</style>
