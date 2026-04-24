<template>
  <div class="hanja-game">
    <div class="game-header">
      <button class="back-btn" @click="goBack">← 뒤로</button>
      <div class="level-badge">레벨 {{ level }} 漢</div>
      <div class="score">⭐ {{ score }}</div>
    </div>

    <div v-if="phase==='start'" class="center-box">
      <div style="font-size:80px">漢</div>
      <h1 class="title">한자 퀴즈</h1>
      <p class="subtitle">한자의 음과 뜻을 맞춰보세요!</p>
      <div class="level-info">현재 레벨: {{ level }}</div>
      <button class="start-btn" @click="startGame">시작! 📖</button>
    </div>

    <div v-if="phase==='play'" class="play-area">
      <div class="progress-row">
        <span>{{ qIdx }}/{{ totalQ }}</span>
        <div class="prog-bar"><div class="prog-fill" :style="{width:(qIdx/totalQ*100)+'%'}"></div></div>
        <span :style="{color:timeLeft<6?'#ef4444':'#fbbf24'}">{{ timeLeft }}초</span>
      </div>
      <div class="hanja-card">
        <div class="hanja-char">{{ curQ.hanja }}</div>
        <div class="ask-type">{{ askType === 'reading' ? '이 한자의 음(소리)는?' : '이 한자의 뜻은?' }}</div>
      </div>
      <div class="choices-grid">
        <button v-for="opt in options" :key="opt" class="choice-btn"
          :class="{correct: answered && opt===correctAnswer, wrong: answered && opt===picked && opt!==correctAnswer, disabled: answered}"
          :disabled="answered" @click="selectAnswer(opt)">
          {{ opt }}
        </button>
      </div>
      <div v-if="answered" class="feedback" :class="wasRight?'right':'wrong'">
        {{ wasRight ? '정답! 🎉' : '정답: ' + curQ.reading + ' (' + curQ.meaning + ')' }}
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
import { ref, computed, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
const router = useRouter()

const hanjaDB = [
  {hanja:'山',reading:'산',meaning:'산',level:1},
  {hanja:'水',reading:'수',meaning:'물',level:1},
  {hanja:'火',reading:'화',meaning:'불',level:1},
  {hanja:'木',reading:'목',meaning:'나무',level:1},
  {hanja:'金',reading:'금',meaning:'금/쇠',level:1},
  {hanja:'土',reading:'토',meaning:'흙',level:1},
  {hanja:'日',reading:'일',meaning:'날/해',level:1},
  {hanja:'月',reading:'월',meaning:'달/월',level:1},
  {hanja:'人',reading:'인',meaning:'사람',level:1},
  {hanja:'大',reading:'대',meaning:'크다',level:1},
  {hanja:'小',reading:'소',meaning:'작다',level:2},
  {hanja:'上',reading:'상',meaning:'위',level:2},
  {hanja:'下',reading:'하',meaning:'아래',level:2},
  {hanja:'東',reading:'동',meaning:'동쪽',level:2},
  {hanja:'西',reading:'서',meaning:'서쪽',level:2},
  {hanja:'南',reading:'남',meaning:'남쪽',level:2},
  {hanja:'北',reading:'북',meaning:'북쪽',level:2},
  {hanja:'中',reading:'중',meaning:'가운데',level:2},
  {hanja:'心',reading:'심',meaning:'마음',level:3},
  {hanja:'力',reading:'력',meaning:'힘',level:3},
  {hanja:'王',reading:'왕',meaning:'임금',level:3},
  {hanja:'國',reading:'국',meaning:'나라',level:3},
  {hanja:'學',reading:'학',meaning:'배우다',level:3},
  {hanja:'語',reading:'어',meaning:'말/언어',level:3},
]

const level = ref(parseInt(localStorage.getItem('hanja_level') || '1'))
const score = ref(0)
const correct = ref(0)
const leveled = ref(false)
const phase = ref('start')
const answered = ref(false)
const wasRight = ref(false)
const picked = ref('')
const correctAnswer = ref('')
const curQ = ref({hanja:'',reading:'',meaning:'',level:1})
const options = ref([])
const askType = ref('reading')
const qIdx = ref(0)
const totalQ = ref(10)
const timeLeft = ref(15)
const maxTime = ref(15)
let timer = null
let queue = []

function getPool() {
  const maxLv=level.value<=2?1:level.value<=4?2:3
  return hanjaDB.filter(h=>h.level<=maxLv)
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
  const pool=getPool()
  queue=shuffle(pool).slice(0,totalQ.value)
  phase.value='play'; nextQuestion()
}

function nextQuestion() {
  if(qIdx.value>=totalQ.value){endGame();return}
  const q=queue[qIdx.value]; qIdx.value++
  curQ.value=q; answered.value=false; wasRight.value=false; picked.value=''
  askType.value=Math.random()>0.5?'reading':'meaning'
  correctAnswer.value=askType.value==='reading'?q.reading:q.meaning
  const pool=getPool()
  const wrongField=askType.value==='reading'?'reading':'meaning'
  const wrongs=shuffle(pool.filter(h=>h.hanja!==q.hanja)).slice(0,3).map(h=>h[wrongField])
  options.value=shuffle([correctAnswer.value,...wrongs])
  speak(q.hanja)
  startTimer()
}

function startTimer() {
  clearInterval(timer); timeLeft.value=maxTime.value
  timer=setInterval(()=>{ timeLeft.value--; if(timeLeft.value<=0){clearInterval(timer);timeOut()} },1000)
}

function timeOut() {
  answered.value=true; wasRight.value=false
  speak('정답은 ' + correctAnswer.value)
  setTimeout(nextQuestion,2000)
}

function selectAnswer(opt) {
  if(answered.value) return
  clearInterval(timer); answered.value=true; picked.value=opt
  wasRight.value=opt===correctAnswer.value
  if(wasRight.value){ correct.value++; score.value+=10+timeLeft.value; speak('정답!') }
  else speak('오답. 정답은 ' + correctAnswer.value)
  setTimeout(nextQuestion,2000)
}

function endGame() {
  clearInterval(timer); phase.value='result'
  if(correct.value>=8){ level.value++; localStorage.setItem('hanja_level',level.value); leveled.value=true; speak('레벨업!') }
}

function goBack() { clearInterval(timer); router.push('/games') }
onUnmounted(()=>clearInterval(timer))
</script>

<style scoped>
.hanja-game { min-height:100vh; background:linear-gradient(135deg,#1e0533,#3b0764,#581c87); padding:16px; font-family:'Noto Sans KR',serif; }
.game-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
.back-btn { background:rgba(255,255,255,0.15); color:#e9d5ff; border:none; padding:8px 14px; border-radius:20px; cursor:pointer; font-size:14px; }
.level-badge,.score { background:rgba(255,255,255,0.15); color:#e9d5ff; padding:6px 14px; border-radius:20px; font-weight:700; }
.center-box { text-align:center; padding:40px 20px; }
.title { font-size:36px; color:#e9d5ff; font-weight:900; margin:10px 0; }
.subtitle { color:rgba(255,255,255,0.8); font-size:16px; }
.level-info { color:#ddd6fe; margin:10px 0; font-size:15px; }
.start-btn { background:#a855f7; color:#fff; border:none; padding:14px 40px; border-radius:30px; font-size:20px; font-weight:800; cursor:pointer; margin-top:20px; }
.play-area { max-width:400px; margin:0 auto; }
.progress-row { display:flex; align-items:center; gap:10px; margin-bottom:16px; color:rgba(255,255,255,0.6); font-size:14px; }
.prog-bar { flex:1; height:8px; background:rgba(255,255,255,0.15); border-radius:4px; overflow:hidden; }
.prog-fill { height:100%; background:#a855f7; border-radius:4px; transition:width 0.3s; }
.hanja-card { background:rgba(255,255,255,0.08); border-radius:20px; padding:30px 20px; text-align:center; margin-bottom:20px; border:1px solid rgba(168,85,247,0.4); }
.hanja-char { font-size:96px; color:#fff; line-height:1; margin-bottom:12px; }
.ask-type { color:#d8b4fe; font-size:16px; font-weight:600; }
.choices-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px; }
.choice-btn { background:rgba(255,255,255,0.12); color:#e9d5ff; border:1px solid rgba(168,85,247,0.3); padding:16px; border-radius:12px; font-size:20px; font-weight:700; cursor:pointer; transition:all 0.2s; }
.choice-btn:hover:not(.disabled) { background:rgba(168,85,247,0.25); border-color:#a855f7; }
.choice-btn.correct { background:rgba(16,185,129,0.3); border-color:#10b981; color:#a7f3d0; }
.choice-btn.wrong { background:rgba(239,68,68,0.25); border-color:#ef4444; color:#fca5a5; }
.choice-btn.disabled { cursor:not-allowed; }
.feedback { text-align:center; padding:12px; border-radius:12px; font-size:16px; font-weight:700; }
.feedback.right { background:rgba(16,185,129,0.2); color:#a7f3d0; }
.feedback.wrong { background:rgba(239,68,68,0.2); color:#fca5a5; }
.result-box { text-align:center; padding:40px 20px; }
.res-score { font-size:52px; font-weight:900; color:#e9d5ff; }
.res-detail { color:rgba(255,255,255,0.7); font-size:16px; margin:8px 0; }
.levelup { background:#a855f7; color:#fff; padding:10px 20px; border-radius:20px; font-weight:800; font-size:18px; margin:14px auto; display:inline-block; }
.res-btns { display:flex; gap:12px; justify-content:center; margin-top:20px; }
.rbtn { background:rgba(255,255,255,0.9); color:#3b0764; border:none; padding:12px 28px; border-radius:20px; font-size:16px; font-weight:700; cursor:pointer; }
.rbtn.home { background:#7c3aed; color:#fff; }
</style>
