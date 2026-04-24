<template>
  <div class="stroop-game">
    <div class="game-header">
      <button class="back-btn" @click="goBack">← 뒤로</button>
      <div class="level-badge">레벨 {{ level }} 🎨</div>
      <div class="score">⭐ {{ score }}</div>
    </div>

    <div v-if="phase==='start'" class="center-box">
      <div style="font-size:80px">🎨</div>
      <h1 class="title">색깔 스트룹</h1>
      <p class="subtitle">글자의 색깔을 골라요! (글자 내용 말고 색깔!)</p>
      <div class="example-row">
        <span style="color:#ef4444;font-size:28px;font-weight:700">파랑</span>
        <span style="color:#3b82f6;font-size:16px;margin:0 8px">← 이 글자의 색은?</span>
        <span style="color:#fff;font-size:20px">빨강</span>
      </div>
      <div class="level-info">현재 레벨: {{ level }}</div>
      <button class="start-btn" @click="startGame">시작! 🎯</button>
    </div>

    <div v-if="phase==='play'" class="play-area">
      <div class="top-row">
        <div class="timer-box" :style="{color:timeLeft<4?'#ef4444':'#fff'}">⏱ {{ timeLeft }}</div>
        <div class="combo-box" v-if="combo>1">🔥 {{ combo }}콤보</div>
        <div class="count-box">{{ qIdx }}/{{ totalQ }}</div>
      </div>
      <div class="stroop-word" :style="{color: wordColor}">{{ wordText }}</div>
      <div class="color-choices">
        <button v-for="col in colorChoices" :key="col.name" class="color-btn"
          :style="{background: col.hex}"
          :class="{correct: answered && col.name===wordColorName, wrong: answered && col.name===picked && col.name!==wordColorName, disabled: answered}"
          :disabled="answered" @click="selectColor(col.name)">
          {{ col.name }}
        </button>
      </div>
      <div v-if="answered" class="feedback" :class="wasRight?'right':'wrong'">
        {{ wasRight ? '정답! 🎉' : '글자 색은 ' + wordColorName + '이에요!' }}
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

const colors = [
  {name:'빨강',hex:'#ef4444'},
  {name:'파랑',hex:'#3b82f6'},
  {name:'초록',hex:'#10b981'},
  {name:'노랑',hex:'#fbbf24'},
  {name:'보라',hex:'#8b5cf6'},
  {name:'주황',hex:'#f97316'},
]

const level = ref(parseInt(localStorage.getItem('stroop_level') || '1'))
const score = ref(0)
const correct = ref(0)
const combo = ref(0)
const maxCombo = ref(0)
const leveled = ref(false)
const phase = ref('start')
const answered = ref(false)
const wasRight = ref(false)
const picked = ref('')
const wordText = ref('')
const wordColor = ref('#fff')
const wordColorName = ref('')
const colorChoices = ref([])
const qIdx = ref(0)
const totalQ = ref(15)
const timeLeft = ref(8)
const maxTime = ref(8)
let timer = null

function shuffle(a){const r=[...a];for(let i=r.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[r[i],r[j]]=[r[j],r[i]]}return r}

function startGame() {
  score.value=0; correct.value=0; combo.value=0; maxCombo.value=0; leveled.value=false
  qIdx.value=0; maxTime.value=level.value<=2?8:level.value<=4?5:3
  const numColors = level.value<=2?4:level.value<=4?5:6
  colorChoices.value = colors.slice(0,numColors)
  phase.value='play'; nextRound()
}

function nextRound() {
  if(qIdx.value>=totalQ.value){endGame();return}
  qIdx.value++; answered.value=false; wasRight.value=false; picked.value=''
  const pool = colorChoices.value
  const textColor = pool[Math.floor(Math.random()*pool.length)]
  let displayColor = pool[Math.floor(Math.random()*pool.length)]
  // At higher levels, force mismatch more often
  if(level.value >= 3 && Math.random() > 0.3) {
    const others = pool.filter(c=>c.name!==textColor.name)
    if(others.length) displayColor = others[Math.floor(Math.random()*others.length)]
  }
  wordText.value = textColor.name
  wordColor.value = displayColor.hex
  wordColorName.value = displayColor.name
  startTimer()
}

function startTimer() {
  clearInterval(timer); timeLeft.value=maxTime.value
  timer=setInterval(()=>{ timeLeft.value--; if(timeLeft.value<=0){clearInterval(timer);timeOut()} },1000)
}

function timeOut() {
  answered.value=true; wasRight.value=false; combo.value=0
  setTimeout(nextRound, 1000)
}

function selectColor(name) {
  if(answered.value) return
  clearInterval(timer); answered.value=true; picked.value=name
  wasRight.value = name===wordColorName.value
  if(wasRight.value){ correct.value++; combo.value++; if(combo.value>maxCombo.value)maxCombo.value=combo.value; score.value+=10+timeLeft.value*2+(combo.value>1?combo.value*3:0) }
  else combo.value=0
  setTimeout(nextRound, 1000)
}

function endGame() {
  clearInterval(timer); phase.value='result'
  if(correct.value>=12){ level.value++; localStorage.setItem('stroop_level',level.value); leveled.value=true }
}

function goBack() { clearInterval(timer); router.push('/games') }
onUnmounted(()=>clearInterval(timer))
</script>

<style scoped>
.stroop-game { min-height:100vh; background:#111827; padding:16px; font-family:'Noto Sans KR',sans-serif; }
.game-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
.back-btn { background:rgba(255,255,255,0.12); color:#fff; border:none; padding:8px 14px; border-radius:20px; cursor:pointer; font-size:14px; }
.level-badge,.score { background:rgba(255,255,255,0.12); color:#fff; padding:6px 14px; border-radius:20px; font-weight:700; }
.center-box { text-align:center; padding:30px 20px; }
.title { font-size:36px; color:#fff; font-weight:900; margin:10px 0; }
.subtitle { color:rgba(255,255,255,0.7); font-size:15px; }
.example-row { margin:16px 0; display:flex; align-items:center; justify-content:center; flex-wrap:wrap; gap:4px; }
.level-info { color:#9ca3af; font-size:15px; margin:10px 0; }
.start-btn { background:#8b5cf6; color:#fff; border:none; padding:14px 40px; border-radius:30px; font-size:20px; font-weight:800; cursor:pointer; margin-top:16px; }
.play-area { max-width:400px; margin:0 auto; display:flex; flex-direction:column; align-items:center; }
.top-row { display:flex; gap:12px; margin-bottom:20px; width:100%; justify-content:space-between; align-items:center; }
.timer-box,.combo-box,.count-box { font-size:18px; font-weight:700; padding:6px 14px; background:rgba(255,255,255,0.1); border-radius:12px; }
.timer-box { color:#fff; }
.combo-box { color:#fbbf24; }
.count-box { color:rgba(255,255,255,0.6); }
.stroop-word { font-size:72px; font-weight:900; text-align:center; margin-bottom:32px; transition:color 0.1s; }
.color-choices { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; width:100%; margin-bottom:16px; }
.color-btn { padding:18px 8px; border:none; border-radius:14px; font-size:18px; font-weight:800; color:#fff; cursor:pointer; transition:all 0.15s; text-shadow:0 1px 3px rgba(0,0,0,0.5); }
.color-btn:hover:not(.disabled) { transform:scale(1.05); }
.color-btn.correct { outline:4px solid #fff; transform:scale(1.05); }
.color-btn.wrong { opacity:0.5; }
.color-btn.disabled { cursor:not-allowed; }
.feedback { text-align:center; font-size:18px; font-weight:700; color:#fff; padding:10px; }
.feedback.right { color:#10b981; }
.feedback.wrong { color:#ef4444; }
.result-box { text-align:center; padding:40px 20px; }
.res-score { font-size:56px; font-weight:900; color:#8b5cf6; }
.res-detail { color:rgba(255,255,255,0.7); font-size:16px; margin:8px 0; }
.levelup { background:#8b5cf6; color:#fff; padding:10px 20px; border-radius:20px; font-weight:800; font-size:18px; margin:14px auto; display:inline-block; }
.res-btns { display:flex; gap:12px; justify-content:center; margin-top:20px; }
.rbtn { background:rgba(255,255,255,0.9); color:#111827; border:none; padding:12px 28px; border-radius:20px; font-size:16px; font-weight:700; cursor:pointer; }
.rbtn.home { background:#8b5cf6; color:#fff; }
</style>
