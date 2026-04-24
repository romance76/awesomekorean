<template>
  <div class="coding-game">
    <div class="game-header">
      <button class="back-btn" @click="goBack">← 뒤로</button>
      <div class="level-badge">레벨 {{ level }} 💻</div>
      <div class="score">⭐ {{ score }}</div>
    </div>

    <div v-if="phase==='start'" class="center-box">
      <div style="font-size:80px">💻</div>
      <h1 class="title">코딩 퀴즈</h1>
      <p class="subtitle">프로그래밍 개념을 테스트해요!</p>
      <div class="level-info">
        <div>레벨 1-2: HTML/CSS 기초</div>
        <div>레벨 3-4: JavaScript · Python</div>
        <div>레벨 5+: 알고리즘 · 자료구조</div>
      </div>
      <button class="start-btn" @click="startGame">시작! 🚀</button>
    </div>

    <div v-if="phase==='play'" class="play-area">
      <div class="progress-row">
        <span>{{ qIdx }}/{{ totalQ }}</span>
        <div class="prog-bar"><div class="prog-fill" :style="{width:(qIdx/totalQ*100)+'%'}"></div></div>
        <span :style="{color:timeLeft<6?'#ef4444':'#a5f3fc'}">{{ timeLeft }}초</span>
      </div>
      <div class="question-card">
        <div class="q-category">{{ curQ.category }}</div>
        <div class="q-text">{{ curQ.question }}</div>
        <div class="code-block" v-if="curQ.code"><pre>{{ curQ.code }}</pre></div>
      </div>
      <div class="choices-col">
        <button v-for="opt in curQ.options" :key="opt" class="choice-btn"
          :class="{correct: answered && opt===curQ.answer, wrong: answered && opt===picked && opt!==curQ.answer, disabled: answered}"
          :disabled="answered" @click="selectAnswer(opt)">
          <span class="choice-mono" v-if="curQ.codeOptions">{{ opt }}</span>
          <span v-else>{{ opt }}</span>
        </button>
      </div>
      <div v-if="answered" class="feedback" :class="wasRight?'right':'wrong'">
        <div>{{ wasRight ? '정답! 🎉' : '오답! 정답: ' + curQ.answer }}</div>
        <div class="explain">{{ curQ.explain }}</div>
      </div>
    </div>

    <div v-if="phase==='result'" class="result-box">
      <div style="font-size:80px">{{ correct>=8?'🏆':'💪' }}</div>
      <div class="res-score">{{ score }}점</div>
      <div class="res-detail">{{ correct }}/{{ totalQ }} 정답</div>
      <div v-if="leveled" class="levelup">🎉 레벨업! 레벨 {{ level }}!</div>
      <GameResultExtras :rec="rec" slug="coding_quiz" />
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
const rec = useGameRecord('coding_quiz')

const quizDB = [
  {level:1,category:'HTML',question:'웹페이지 제목을 나타내는 태그는?',options:['<title>','<head>','<h1>','<body>'],answer:'<title>',explain:'<title>은 브라우저 탭에 표시되는 페이지 제목이에요.',codeOptions:true},
  {level:1,category:'HTML',question:'링크를 만드는 HTML 태그는?',options:['<a>','<link>','<href>','<url>'],answer:'<a>',explain:'<a href="URL">텍스트</a> 형태로 사용해요.',codeOptions:true},
  {level:1,category:'CSS',question:'글자 색상을 바꾸는 CSS 속성은?',options:['color','font-color','text-color','foreground'],answer:'color',explain:'color: red; 처럼 사용해요.',codeOptions:true},
  {level:1,category:'CSS',question:'배경색을 지정하는 CSS 속성은?',options:['background-color','bg-color','back-color','color-bg'],answer:'background-color',explain:'background-color: blue; 처럼 사용해요.',codeOptions:true},
  {level:1,category:'개념',question:'HTML에서 HT는 무엇의 약자인가요?',options:['HyperText','HighType','HeatText','HardType'],answer:'HyperText',explain:'HTML = HyperText Markup Language에요.'},
  {level:2,category:'JavaScript',question:'변수를 선언하는 키워드가 아닌 것은?',options:['int','let','const','var'],answer:'int',explain:'JavaScript에는 int 키워드가 없어요. let, const, var를 사용해요.',codeOptions:true},
  {level:2,category:'JavaScript',question:'배열의 길이를 구하는 방법은?',options:['array.length','array.size()','array.count','len(array)'],answer:'array.length',explain:'JavaScript에서는 .length 속성으로 배열 길이를 구해요.',codeOptions:true},
  {level:2,category:'Python',question:'Python에서 화면에 출력하는 함수는?',options:['print()','echo()','console.log()','puts()'],answer:'print()',explain:'Python은 print("Hello") 로 출력해요.',codeOptions:true},
  {level:2,category:'Python',question:'Python 반복문: for i in ____(5):',options:['range','list','array','loop'],answer:'range',explain:'for i in range(5): 는 0,1,2,3,4를 순환해요.',codeOptions:true},
  {level:3,category:'알고리즘',question:'버블 정렬의 최악 시간 복잡도는?',options:['O(n²)','O(n)','O(log n)','O(n log n)'],answer:'O(n²)',explain:'버블 정렬은 최악의 경우 n²번 비교가 필요해요.'},
  {level:3,category:'자료구조',question:'LIFO(Last In First Out) 구조는?',options:['Stack','Queue','Tree','Graph'],answer:'Stack',explain:'Stack은 나중에 넣은 것이 먼저 나오는 구조예요.'},
  {level:3,category:'자료구조',question:'FIFO(First In First Out) 구조는?',options:['Queue','Stack','Heap','Array'],answer:'Queue',explain:'Queue는 먼저 넣은 것이 먼저 나오는 구조예요.'},
]

const level = ref(parseInt(localStorage.getItem('coding_level') || '1'))
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
  return quizDB.filter(q=>q.level<=maxLv)
}

function speak(text) {
  if(!window.speechSynthesis) return
  window.speechSynthesis.cancel()
  const u = new SpeechSynthesisUtterance(text)
  u.lang='ko-KR'; u.rate=0.9
  window.speechSynthesis.speak(u)
}

function shuffle(a){const r=[...a];for(let i=r.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[r[i],r[j]]=[r[j],r[i]]}return r}

function startGame() {
  score.value=0; correct.value=0; leveled.value=false; qIdx.value=0
  maxTime.value=level.value<=2?25:level.value<=4?20:15
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
  timer=setInterval(()=>{
    timeLeft.value--
    if(timeLeft.value<=0){clearInterval(timer);timeOut()}
  },1000)
}

function timeOut() {
  answered.value=true; wasRight.value=false
  speak('시간 초과! 정답은 ' + curQ.value.answer)
  setTimeout(nextQuestion,2500)
}

function selectAnswer(opt) {
  if(answered.value) return
  clearInterval(timer)
  answered.value=true; picked.value=opt
  wasRight.value=opt===curQ.value.answer
  if(wasRight.value){correct.value++;score.value+=10+timeLeft.value;speak('정답!')}
  else speak('오답')
  setTimeout(nextQuestion,2800)
}

async function endGame() {
  clearInterval(timer); phase.value='result'
  const won = correct.value >= 8
  if(won){level.value++;localStorage.setItem('coding_level',level.value);leveled.value=true;speak('레벨업!')}
  await rec.end({ won, leveledUp: leveled.value, score: score.value })
}

function goBack() { clearInterval(timer); router.push('/games') }
onUnmounted(()=>clearInterval(timer))
</script>

<style scoped>
.coding-game { min-height:100vh; background:linear-gradient(135deg,#0f172a,#0c2340,#0a3d62); padding:16px; font-family:'Noto Sans KR','JetBrains Mono',monospace; }
.game-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
.back-btn { background:rgba(255,255,255,0.1); color:#a5f3fc; border:none; padding:8px 14px; border-radius:20px; cursor:pointer; font-size:14px; }
.level-badge,.score { background:rgba(255,255,255,0.1); color:#a5f3fc; padding:6px 14px; border-radius:20px; font-weight:700; }
.center-box { text-align:center; padding:30px 20px; }
.title { font-size:36px; color:#a5f3fc; font-weight:900; margin:10px 0; }
.subtitle { color:rgba(255,255,255,0.7); font-size:16px; }
.level-info { background:rgba(165,243,252,0.08); border-radius:12px; padding:12px 20px; color:#a5f3fc; font-size:14px; line-height:1.8; margin:12px auto; max-width:240px; text-align:left; font-family:monospace; }
.start-btn { background:#06b6d4; color:#0f172a; border:none; padding:14px 40px; border-radius:30px; font-size:20px; font-weight:800; cursor:pointer; margin-top:16px; }
.play-area { max-width:520px; margin:0 auto; }
.progress-row { display:flex; align-items:center; gap:10px; margin-bottom:16px; color:rgba(255,255,255,0.6); font-size:14px; }
.prog-bar { flex:1; height:6px; background:rgba(255,255,255,0.1); border-radius:3px; overflow:hidden; }
.prog-fill { height:100%; background:#06b6d4; border-radius:3px; transition:width 0.3s; }
.question-card { background:rgba(255,255,255,0.06); border-radius:16px; padding:20px; margin-bottom:14px; border:1px solid rgba(165,243,252,0.15); }
.q-category { color:#06b6d4; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:2px; margin-bottom:6px; }
.q-text { color:#fff; font-size:18px; font-weight:600; line-height:1.5; }
.code-block { background:#0d1117; border-radius:8px; padding:12px; margin-top:10px; border:1px solid rgba(165,243,252,0.2); }
.code-block pre { color:#a5f3fc; font-size:14px; font-family:monospace; margin:0; white-space:pre-wrap; }
.choices-col { display:flex; flex-direction:column; gap:8px; margin-bottom:12px; }
.choice-btn { background:rgba(255,255,255,0.08); color:#e2e8f0; border:1px solid rgba(165,243,252,0.2); padding:14px 16px; border-radius:10px; font-size:15px; font-weight:600; cursor:pointer; text-align:left; transition:all 0.2s; }
.choice-btn:hover:not(.disabled) { background:rgba(6,182,212,0.15); border-color:#06b6d4; }
.choice-btn.correct { background:rgba(16,185,129,0.25); border-color:#10b981; color:#a7f3d0; }
.choice-btn.wrong { background:rgba(239,68,68,0.2); border-color:#ef4444; color:#fca5a5; }
.choice-btn.disabled { cursor:not-allowed; }
.choice-mono { font-family:monospace; font-size:16px; }
.feedback { padding:12px 16px; border-radius:12px; margin-top:8px; }
.feedback.right { background:rgba(16,185,129,0.15); color:#a7f3d0; border:1px solid rgba(16,185,129,0.3); }
.feedback.wrong { background:rgba(239,68,68,0.1); color:#fca5a5; border:1px solid rgba(239,68,68,0.3); }
.explain { font-size:13px; font-weight:400; margin-top:4px; opacity:0.85; }
.result-box { text-align:center; padding:40px 20px; }
.res-score { font-size:52px; font-weight:900; color:#a5f3fc; }
.res-detail { color:rgba(255,255,255,0.7); font-size:16px; margin:8px 0; }
.levelup { background:#06b6d4; color:#0f172a; padding:10px 20px; border-radius:20px; font-weight:800; font-size:18px; margin:14px auto; display:inline-block; }
.res-btns { display:flex; gap:12px; justify-content:center; margin-top:20px; }
.rbtn { background:rgba(255,255,255,0.9); color:#0f172a; border:none; padding:12px 28px; border-radius:20px; font-size:16px; font-weight:700; cursor:pointer; }
.rbtn.home { background:#06b6d4; color:#0f172a; }
</style>
