<template>
  <div class="proverb-game">
    <div class="game-header">
      <button class="back-btn" @click="goBack">← 뒤로</button>
      <div class="level-badge">레벨 {{ level }} 📜</div>
      <div class="score">⭐ {{ score }}</div>
    </div>

    <div v-if="phase==='start'" class="center-box">
      <div style="font-size:80px">📜</div>
      <h1 class="title">속담 퀴즈</h1>
      <p class="subtitle">우리나라 속담을 맞춰보세요!</p>
      <div class="level-info">현재 레벨: {{ level }}</div>
      <button class="start-btn" @click="startGame">시작! 🎯</button>
    </div>

    <div v-if="phase==='play'" class="play-area">
      <div class="progress-row">
        <span>{{ qIdx }}/{{ totalQ }}</span>
        <div class="prog-bar"><div class="prog-fill" :style="{width:(qIdx/totalQ*100)+'%'}"></div></div>
        <span :style="{color:timeLeft<6?'#ef4444':'#fbbf24'}">{{ timeLeft }}초</span>
      </div>
      <div class="proverb-card">
        <div class="proverb-text">{{ curQ.proverb }}</div>
      </div>
      <div class="q-ask">이 속담의 뜻은?</div>
      <div class="choices-col">
        <button v-for="opt in curQ.options" :key="opt" class="choice-btn"
          :class="{correct: answered && opt===curQ.meaning, wrong: answered && opt===picked && opt!==curQ.meaning, disabled: answered}"
          :disabled="answered" @click="selectAnswer(opt)">
          {{ opt }}
        </button>
      </div>
      <div v-if="answered" class="feedback" :class="wasRight?'right':'wrong'">
        <div>{{ wasRight ? '정답! 🎉' : '오답!' }}</div>
        <div class="explain">{{ curQ.meaning }}</div>
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

const proverbs = [
  {proverb:'가는 말이 고와야 오는 말이 곱다',meaning:'내가 먼저 잘 대해야 상대도 잘 대한다',level:1},
  {proverb:'공든 탑이 무너지랴',meaning:'정성껏 쌓은 것은 쉽게 무너지지 않는다',level:1},
  {proverb:'구슬이 서 말이라도 꿰어야 보배',meaning:'아무리 좋은 것도 활용해야 가치가 있다',level:1},
  {proverb:'낮말은 새가 듣고 밤말은 쥐가 듣는다',meaning:'항상 말을 조심해야 한다',level:1},
  {proverb:'세 살 버릇 여든까지 간다',meaning:'어릴 때 형성된 습관은 오래 지속된다',level:1},
  {proverb:'티끌 모아 태산',meaning:'작은 것도 모이면 크게 된다',level:1},
  {proverb:'하늘이 무너져도 솟아날 구멍이 있다',meaning:'아무리 어려운 상황에도 해결책이 있다',level:1},
  {proverb:'원숭이도 나무에서 떨어진다',meaning:'아무리 능숙한 사람도 실수할 때가 있다',level:2},
  {proverb:'우물 안 개구리',meaning:'세상을 좁게만 알고 식견이 없는 사람',level:2},
  {proverb:'백지장도 맞들면 낫다',meaning:'아무리 쉬운 일도 협력하면 더 잘 된다',level:2},
  {proverb:'빈 수레가 요란하다',meaning:'실속이 없는 사람이 더 떠들어댄다',level:2},
  {proverb:'소 잃고 외양간 고친다',meaning:'일이 다 잘못된 뒤에야 대책을 세운다',level:2},
  {proverb:'식은 죽 먹기',meaning:'매우 쉬운 일',level:2},
  {proverb:'아니 땐 굴뚝에 연기 날까',meaning:'원인 없는 결과는 없다',level:3},
  {proverb:'윗물이 맑아야 아랫물이 맑다',meaning:'위에 있는 사람이 잘해야 아랫사람도 잘 된다',level:3},
  {proverb:'호랑이 없는 곳에서 여우가 왕 노릇',meaning:'실력 있는 사람이 없을 때 그보다 못한 사람이 으스댄다',level:3},
]

const level = ref(parseInt(localStorage.getItem('proverb_level') || '1'))
const score = ref(0)
const correct = ref(0)
const leveled = ref(false)
const phase = ref('start')
const answered = ref(false)
const wasRight = ref(false)
const picked = ref('')
const curQ = ref({proverb:'',meaning:'',options:[]})
const qIdx = ref(0)
const totalQ = ref(10)
const timeLeft = ref(20)
const maxTime = ref(20)
let timer = null
let queue = []

function getPool() {
  const maxLv=level.value<=2?1:level.value<=4?2:3
  return proverbs.filter(p=>p.level<=maxLv)
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
  const pool=getPool()
  // 보기 중복 방지
  const set=new Set([q.meaning])
  for(const p of shuffle(pool)){ if(set.size>=4) break; if(p.meaning && p.meaning!==q.meaning) set.add(p.meaning) }
  curQ.value={...q, options:shuffle([...set])}
  answered.value=false; wasRight.value=false; picked.value=''
  speak(q.proverb)
  startTimer()
}

function startTimer() {
  clearInterval(timer); timeLeft.value=maxTime.value
  timer=setInterval(()=>{ timeLeft.value--; if(timeLeft.value<=0){clearInterval(timer);timeOut()} },1000)
}

function timeOut() {
  answered.value=true; wasRight.value=false
  speak('시간 초과! 정답은 ' + curQ.value.meaning)
  setTimeout(nextQuestion,2800)
}

function selectAnswer(opt) {
  if(answered.value) return
  clearInterval(timer); answered.value=true; picked.value=opt
  wasRight.value=opt===curQ.value.meaning
  if(wasRight.value){ correct.value++; score.value+=10+timeLeft.value; speak('정답!') }
  else speak('오답!')
  setTimeout(nextQuestion,2800)
}

function endGame() {
  clearInterval(timer); phase.value='result'
  if(correct.value>=8){ level.value++; localStorage.setItem('proverb_level',level.value); leveled.value=true; speak('레벨업!') }
}

function goBack() { clearInterval(timer); router.push('/games') }
onUnmounted(()=>clearInterval(timer))
</script>

<style scoped>
.proverb-game { min-height:100vh; background:linear-gradient(135deg,#14532d,#166534,#15803d); padding:16px; font-family:'Noto Sans KR',sans-serif; }
.game-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
.back-btn { background:rgba(255,255,255,0.15); color:#fff; border:none; padding:8px 14px; border-radius:20px; cursor:pointer; font-size:14px; }
.level-badge,.score { background:rgba(255,255,255,0.15); color:#fff; padding:6px 14px; border-radius:20px; font-weight:700; }
.center-box { text-align:center; padding:40px 20px; }
.title { font-size:36px; color:#fff; font-weight:900; margin:10px 0; }
.subtitle { color:rgba(255,255,255,0.85); font-size:16px; }
.level-info { color:#bbf7d0; margin:10px 0; font-size:15px; }
.start-btn { background:#fbbf24; color:#14532d; border:none; padding:14px 40px; border-radius:30px; font-size:20px; font-weight:800; cursor:pointer; margin-top:20px; }
.play-area { max-width:500px; margin:0 auto; }
.progress-row { display:flex; align-items:center; gap:10px; margin-bottom:16px; color:rgba(255,255,255,0.7); font-size:14px; }
.prog-bar { flex:1; height:8px; background:rgba(255,255,255,0.2); border-radius:4px; overflow:hidden; }
.prog-fill { height:100%; background:#fbbf24; border-radius:4px; transition:width 0.3s; }
.proverb-card { background:rgba(0,0,0,0.25); border-radius:18px; padding:28px 20px; text-align:center; margin-bottom:14px; border:1px solid rgba(251,191,36,0.3); }
.proverb-text { color:#fde68a; font-size:20px; font-weight:700; line-height:1.6; }
.q-ask { color:rgba(255,255,255,0.7); font-size:14px; text-align:center; margin-bottom:12px; }
.choices-col { display:flex; flex-direction:column; gap:8px; margin-bottom:12px; }
.choice-btn { background:rgba(255,255,255,0.9); color:#14532d; border:none; padding:14px 16px; border-radius:12px; font-size:15px; font-weight:600; cursor:pointer; text-align:left; transition:all 0.2s; }
.choice-btn:hover:not(.disabled) { background:#fff; transform:translateX(4px); }
.choice-btn.correct { background:#10b981; color:#fff; }
.choice-btn.wrong { background:#ef4444; color:#fff; }
.choice-btn.disabled { cursor:not-allowed; }
.feedback { padding:14px 16px; border-radius:12px; }
.feedback.right { background:rgba(16,185,129,0.2); color:#a7f3d0; }
.feedback.wrong { background:rgba(239,68,68,0.2); color:#fca5a5; }
.explain { font-size:14px; font-weight:400; margin-top:6px; }
.result-box { text-align:center; padding:40px 20px; }
.res-score { font-size:52px; font-weight:900; color:#fde68a; }
.res-detail { color:rgba(255,255,255,0.8); font-size:16px; margin:8px 0; }
.levelup { background:#fbbf24; color:#14532d; padding:10px 20px; border-radius:20px; font-weight:800; font-size:18px; margin:14px auto; display:inline-block; }
.res-btns { display:flex; gap:12px; justify-content:center; margin-top:20px; }
.rbtn { background:rgba(255,255,255,0.9); color:#14532d; border:none; padding:12px 28px; border-radius:20px; font-size:16px; font-weight:700; cursor:pointer; }
.rbtn.home { background:#15803d; color:#fff; }
</style>
