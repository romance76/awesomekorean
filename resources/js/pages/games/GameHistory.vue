<template>
  <div class="history-game">
    <div class="game-header">
      <button class="back-btn" @click="goBack">← 뒤로</button>
      <div class="level-badge">레벨 {{ level }} 🏛️</div>
      <div class="score">⭐ {{ score }}</div>
    </div>

    <div v-if="phase==='start'" class="center-box">
      <div style="font-size:80px">🏛️</div>
      <h1 class="title">한국 역사 퀴즈</h1>
      <p class="subtitle">우리 역사를 알아보아요!</p>
      <div class="level-info">
        <div>레벨 1-2: 삼국시대 · 고려</div>
        <div>레벨 3-4: 조선시대</div>
        <div>레벨 5+: 근현대사</div>
      </div>
      <button class="start-btn" @click="startGame">시작! 🎖️</button>
    </div>

    <div v-if="phase==='play'" class="play-area">
      <div class="progress-row">
        <span>{{ qIdx }}/{{ totalQ }}</span>
        <div class="prog-bar"><div class="prog-fill" :style="{width:(qIdx/totalQ*100)+'%'}"></div></div>
        <span :style="{color:timeLeft<6?'#ef4444':'#fde68a'}">{{ timeLeft }}초</span>
      </div>
      <div class="era-badge">{{ curQ.era }}</div>
      <div class="question-card">
        <div class="q-text">{{ curQ.question }}</div>
        <div class="q-year" v-if="curQ.year">{{ curQ.year }}</div>
      </div>
      <div class="choices-grid">
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

const historyDB = [
  {level:1,era:'고조선',question:'우리나라 최초의 국가는?',options:['고조선','고구려','백제','신라'],answer:'고조선',year:'기원전 2333년',explain:'단군왕검이 세운 우리나라 최초의 국가예요.'},
  {level:1,era:'삼국시대',question:'신라가 삼국을 통일한 해는?',options:['676년','668년','660년','612년'],answer:'676년',year:'676년',explain:'신라는 당나라를 몰아내고 676년에 삼국통일을 완성했어요.'},
  {level:1,era:'삼국시대',question:'백제를 세운 사람은?',options:['온조','주몽','박혁거세','김수로'],answer:'온조',year:'기원전 18년',explain:'온조왕이 백제를 건국했어요.'},
  {level:1,era:'고려',question:'고려를 세운 사람은?',options:['왕건','궁예','견훤','최충헌'],answer:'왕건',year:'918년',explain:'왕건(태조)이 918년 고려를 건국했어요.'},
  {level:1,era:'고려',question:'팔만대장경을 만든 시대는?',options:['고려','조선','신라','고구려'],answer:'고려',year:'1236-1251년',explain:'몽골 침략을 물리치기 위해 고려에서 팔만대장경을 만들었어요.'},
  {level:2,era:'조선',question:'조선을 건국한 사람은?',options:['이성계','이순신','세종대왕','정도전'],answer:'이성계',year:'1392년',explain:'이성계(태조)가 1392년 조선을 건국했어요.'},
  {level:2,era:'조선',question:'한글을 창제한 왕은?',options:['세종대왕','태종','성종','연산군'],answer:'세종대왕',year:'1443년',explain:'세종대왕이 1443년 훈민정음(한글)을 창제했어요.'},
  {level:2,era:'조선',question:'임진왜란 때 활약한 장군은?',options:['이순신','강감찬','을지문덕','김유신'],answer:'이순신',year:'1592년',explain:'이순신 장군이 거북선으로 왜군을 물리쳤어요.'},
  {level:3,era:'근현대',question:'3·1운동이 일어난 해는?',options:['1919년','1945년','1910년','1920년'],answer:'1919년',year:'1919년 3월 1일',explain:'일제강점기에 독립을 외친 만세운동이에요.'},
  {level:3,era:'근현대',question:'광복절은 몇 월 며칠인가요?',options:['8월 15일','3월 1일','10월 3일','10월 9일'],answer:'8월 15일',year:'1945년',explain:'1945년 8월 15일 일본으로부터 광복을 되찾았어요.'},
  {level:3,era:'근현대',question:'한국전쟁이 시작된 해는?',options:['1950년','1945년','1953년','1948년'],answer:'1950년',year:'1950년 6월 25일',explain:'6·25전쟁이라고도 하며 1950년에 시작되었어요.'},
]

const level = ref(parseInt(localStorage.getItem('history_level') || '1'))
const score = ref(0)
const correct = ref(0)
const leveled = ref(false)
const phase = ref('start')
const answered = ref(false)
const wasRight = ref(false)
const picked = ref('')
const curQ = ref({options:[],answer:'',explain:'',era:'',question:''})
const qIdx = ref(0)
const totalQ = ref(10)
const timeLeft = ref(20)
const maxTime = ref(20)
let timer = null
let queue = []

function getPool() {
  const maxLv=level.value<=2?1:level.value<=4?2:3
  return historyDB.filter(q=>q.level<=maxLv)
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
  maxTime.value=level.value<=2?20:level.value<=4?15:12
  queue=shuffle(getPool()).slice(0,totalQ.value)
  phase.value='play'; nextQuestion()
}

function nextQuestion() {
  if(qIdx.value>=totalQ.value){endGame();return}
  const q=queue[qIdx.value]; qIdx.value++
  curQ.value={...q,options:shuffle([...q.options])}
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
  setTimeout(nextQuestion,2800)
}

function selectAnswer(opt) {
  if(answered.value) return
  clearInterval(timer); answered.value=true; picked.value=opt
  wasRight.value=opt===curQ.value.answer
  if(wasRight.value){ correct.value++; score.value+=10+timeLeft.value; speak('정답!') }
  else speak('오답! 정답은 ' + curQ.value.answer)
  setTimeout(nextQuestion,2800)
}

function endGame() {
  clearInterval(timer); phase.value='result'
  if(correct.value>=8){ level.value++; localStorage.setItem('history_level',level.value); leveled.value=true; speak('레벨업!') }
}

function goBack() { clearInterval(timer); router.push('/games') }
onUnmounted(()=>clearInterval(timer))
</script>

<style scoped>
.history-game { min-height:100vh; background:linear-gradient(135deg,#451a03,#78350f,#92400e); padding:16px; font-family:'Noto Sans KR',sans-serif; }
.game-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
.back-btn { background:rgba(255,255,255,0.15); color:#fde68a; border:none; padding:8px 14px; border-radius:20px; cursor:pointer; font-size:14px; }
.level-badge,.score { background:rgba(255,255,255,0.15); color:#fde68a; padding:6px 14px; border-radius:20px; font-weight:700; }
.center-box { text-align:center; padding:30px 20px; }
.title { font-size:36px; color:#fde68a; font-weight:900; margin:10px 0; }
.subtitle { color:rgba(255,255,255,0.8); font-size:16px; }
.level-info { background:rgba(0,0,0,0.25); border-radius:12px; padding:12px 20px; color:#fde68a; font-size:14px; line-height:1.8; margin:12px auto; max-width:240px; text-align:left; }
.start-btn { background:#fde68a; color:#451a03; border:none; padding:14px 40px; border-radius:30px; font-size:20px; font-weight:800; cursor:pointer; margin-top:16px; }
.play-area { max-width:480px; margin:0 auto; }
.progress-row { display:flex; align-items:center; gap:10px; margin-bottom:12px; color:rgba(255,255,255,0.7); font-size:14px; }
.prog-bar { flex:1; height:8px; background:rgba(255,255,255,0.2); border-radius:4px; overflow:hidden; }
.prog-fill { height:100%; background:#fde68a; border-radius:4px; transition:width 0.3s; }
.era-badge { display:inline-block; background:rgba(253,230,138,0.2); color:#fde68a; font-size:12px; font-weight:700; padding:4px 12px; border-radius:12px; margin-bottom:10px; }
.question-card { background:rgba(255,255,255,0.1); border-radius:18px; padding:22px 20px; margin-bottom:16px; }
.q-text { color:#fff; font-size:20px; font-weight:700; line-height:1.5; }
.q-year { color:#fde68a; font-size:14px; margin-top:6px; font-weight:600; }
.choices-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:12px; }
.choice-btn { background:rgba(255,255,255,0.12); color:#fff; border:1px solid rgba(253,230,138,0.3); padding:14px 8px; border-radius:12px; font-size:15px; font-weight:600; cursor:pointer; transition:all 0.2s; }
.choice-btn:hover:not(.disabled) { background:rgba(253,230,138,0.2); border-color:#fde68a; }
.choice-btn.correct { background:rgba(16,185,129,0.3); border-color:#10b981; color:#a7f3d0; }
.choice-btn.wrong { background:rgba(239,68,68,0.25); border-color:#ef4444; color:#fca5a5; }
.choice-btn.disabled { cursor:not-allowed; }
.feedback { padding:12px 16px; border-radius:12px; }
.feedback.right { background:rgba(16,185,129,0.15); color:#a7f3d0; }
.feedback.wrong { background:rgba(239,68,68,0.15); color:#fca5a5; }
.explain { font-size:13px; font-weight:400; margin-top:4px; }
.result-box { text-align:center; padding:40px 20px; }
.res-score { font-size:52px; font-weight:900; color:#fde68a; }
.res-detail { color:rgba(255,255,255,0.8); font-size:16px; margin:8px 0; }
.levelup { background:#fde68a; color:#451a03; padding:10px 20px; border-radius:20px; font-weight:800; font-size:18px; margin:14px auto; display:inline-block; }
.res-btns { display:flex; gap:12px; justify-content:center; margin-top:20px; }
.rbtn { background:rgba(255,255,255,0.9); color:#451a03; border:none; padding:12px 28px; border-radius:20px; font-size:16px; font-weight:700; cursor:pointer; }
.rbtn.home { background:#92400e; color:#fff; }
</style>
