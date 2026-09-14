<template>
  <GameShell title="속담 퀴즈" icon="📜" theme="dark" :level="level" :score="score"
    bg="linear-gradient(135deg,#14532d,#166534,#15803d)">
  <div class="proverb-game">
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
      <div class="res-detail">{{ correct }}/{{ totalQ }} 정답 · ⏱️ {{ formatTime(elapsedMs) }}</div>
      <div v-if="leveled" class="levelup">🎉 레벨업! 레벨 {{ level }}!</div>
      <div v-if="pointsEarned > 0" style="background:linear-gradient(135deg,#f59e0b,#d97706); color:#fff; padding:8px 20px; border-radius:18px; font-size:16px; font-weight:800; margin:4px auto; display:inline-block;">+{{ pointsEarned }}P 획득!</div>
      <div v-if="newRecord" style="background:linear-gradient(135deg,#ec4899,#be185d); color:#fff; padding:8px 20px; border-radius:18px; font-size:14px; font-weight:800; margin:4px auto 12px; display:inline-block;">⭐ 신기록! (이전: {{ prevTimeMs ? formatTime(prevTimeMs) : '처음 기록' }})</div>
      <GameLeaderboard ref="lbRef" slug="proverb" :level="recordLevel" />
      <div class="res-btns">
        <button class="rbtn" @click="startGame">다시 🔄</button>
        <button class="rbtn home" @click="goBack">홈 🏠</button>
      </div>
    </div>
  </div>
  <ConfettiBurst ref="confettiRef" />
  </GameShell>
</template>

<script setup>
import { ref, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import GameShell from '../../components/GameShell.vue'
import GameLeaderboard from '../../components/GameLeaderboard.vue'
import ConfettiBurst from '../../components/ConfettiBurst.vue'
import { useAuthStore } from '../../stores/auth'
import { useSiteStore } from '../../stores/site'
import { useGameSound } from '../../composables/useGameSound'
const sound = useGameSound()
const confettiRef = ref(null)
const router = useRouter()
const auth = useAuthStore()
const siteStore = useSiteStore()

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
  {proverb:'발 없는 말이 천리 간다',meaning:'말은 순식간에 멀리 퍼진다',level:1},
  {proverb:'백문이 불여일견',meaning:'백 번 듣는 것보다 한 번 보는 것이 낫다',level:1},
  {proverb:'산 넘어 산',meaning:'갈수록 더 어려운 일이 닥친다',level:1},
  {proverb:'열 번 찍어 안 넘어가는 나무 없다',meaning:'꾸준히 노력하면 결국 이루어진다',level:1},
  {proverb:'우물을 파도 한 우물을 파라',meaning:'한 가지 일에 집중해야 성공한다',level:1},
  {proverb:'하나를 보면 열을 안다',meaning:'작은 행동만 보아도 전체를 짐작할 수 있다',level:1},
  {proverb:'고생 끝에 낙이 온다',meaning:'고생한 뒤에는 좋은 일이 생긴다',level:1},
  {proverb:'말 한마디에 천 냥 빚도 갚는다',meaning:'말을 잘하면 큰 효과를 볼 수 있다',level:1},
  {proverb:'뜻이 있는 곳에 길이 있다',meaning:'의지가 있으면 방법이 생기기 마련이다',level:1},
  {proverb:'시작이 반이다',meaning:'무슨 일이든 시작하면 절반은 이룬 것이다',level:1},
  {proverb:'급할수록 돌아가라',meaning:'급할수록 침착하게 절차를 지켜야 한다',level:2},
  {proverb:'등잔 밑이 어둡다',meaning:'가까이 있는 것을 오히려 잘 알지 못한다',level:2},
  {proverb:'마른하늘에 날벼락',meaning:'뜻밖에 당하는 재난',level:2},
  {proverb:'미운 아이 떡 하나 더 준다',meaning:'미운 사람일수록 잘 대해줘야 뒤탈이 없다',level:2},
  {proverb:'벼는 익을수록 고개를 숙인다',meaning:'사람은 성숙할수록 겸손해진다',level:2},
  {proverb:'서당개 삼 년이면 풍월을 읊는다',meaning:'오래 보고 들으면 자연히 배우게 된다',level:2},
  {proverb:'열 길 물속은 알아도 한 길 사람 속은 모른다',meaning:'사람의 속마음은 알기 어렵다',level:2},
  {proverb:'작은 고추가 더 맵다',meaning:'몸집이 작은 사람이 오히려 더 야무지고 강하다',level:2},
  {proverb:'콩 심은 데 콩 나고 팥 심은 데 팥 난다',meaning:'원인에 따라 그에 맞는 결과가 나타난다',level:2},
  {proverb:'가재는 게 편',meaning:'비슷한 처지에 있는 사람끼리 서로 편을 든다',level:2},
  {proverb:'갈수록 태산',meaning:'어려운 상황이 갈수록 더 심해진다',level:3},
  {proverb:'개구리 올챙이 적 생각 못 한다',meaning:'형편이 나아진 사람이 예전의 어려움을 잊는다',level:3},
  {proverb:'고래 싸움에 새우 등 터진다',meaning:'강한 자들의 싸움에 약한 자가 애꿎게 피해를 본다',level:3},
  {proverb:'꿩 먹고 알 먹기',meaning:'한 가지 일로 두 가지 이익을 함께 얻는다',level:3},
  {proverb:'도토리 키 재기',meaning:'비슷한 처지의 사람끼리 서로 낫다고 다툰다',level:3},
  {proverb:'되로 주고 말로 받는다',meaning:'조금 주고 그 대가로 훨씬 많이 받는다',level:3},
  {proverb:'모로 가도 서울만 가면 된다',meaning:'수단이야 어떻든 목적만 이루면 된다',level:3},
  {proverb:'병 주고 약 준다',meaning:'해를 입힌 후에 도와주는 척한다',level:3},
  {proverb:'수박 겉 핥기',meaning:'내용을 제대로 알지 못하고 겉만 대충 훑는다',level:3},
  {proverb:'짚신도 짝이 있다',meaning:'아무리 부족한 사람도 어울리는 짝이 있기 마련이다',level:3},
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
const startAt = ref(0)
const elapsedMs = ref(0)
const recordLevel = ref(1)
const pointsEarned = ref(0)
const newRecord = ref(false)
const prevTimeMs = ref(null)
const lbRef = ref(null)
function formatTime(ms) { return (Math.round(ms/10)/100).toFixed(2) + '초' }

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
  totalQ.value = queue.length // 낮은 레벨은 문제은행이 10개보다 적을 수 있어 실제 큐 길이로 맞춤 — 안 맞추면 배열 끝을 넘어가 크래시남
  recordLevel.value = level.value
  pointsEarned.value = 0; newRecord.value = false; prevTimeMs.value = null
  elapsedMs.value = 0
  startAt.value = Date.now()
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
  if(wasRight.value){ correct.value++; score.value+=10+timeLeft.value; speak('정답!'); sound.correct() }
  else { speak('오답!'); sound.wrong() }
  setTimeout(nextQuestion,2800)
}

async function endGame() {
  clearInterval(timer); phase.value='result'
  elapsedMs.value = Date.now() - startAt.value
  const won = correct.value >= 8
  const clearedLevel = recordLevel.value
  if(won){ level.value++; localStorage.setItem('proverb_level',level.value); leveled.value=true; speak('레벨업!'); sound.levelUp(); confettiRef.value?.burst() }
  else { sound.gameOver() }
  if (auth.isLoggedIn && won) {
    try {
      const { data } = await axios.post('/api/games/result', {
        game_slug: 'proverb', level: clearedLevel, time_ms: elapsedMs.value,
        score: score.value, won: true, leveled_up: leveled.value,
      })
      const r = data.data || {}
      pointsEarned.value = r.points_earned || 0
      newRecord.value = !!r.new_record
      prevTimeMs.value = r.prev_time_ms
      if (pointsEarned.value > 0) {
        siteStore.toast(`+${pointsEarned.value}P 획득!`, 'success')
        auth.user && (auth.user.points = r.balance ?? auth.user.points)
      }
      lbRef.value?.reload?.()
    } catch {}
  }
}

function goBack() { clearInterval(timer); router.push('/games') }
onUnmounted(()=>clearInterval(timer))
</script>

<style scoped>
.proverb-game { flex:1; padding:16px; font-family:'Noto Sans KR',sans-serif; }
.center-box { text-align:center; padding:40px 20px; }
.title { font-size:36px; color:#fff; font-weight:900; margin:10px 0; text-shadow:0 2px 12px rgba(0,0,0,.25); }
.subtitle { color:rgba(255,255,255,0.85); font-size:16px; }
.level-info { color:#bbf7d0; margin:10px 0; font-size:15px; font-weight:700; }
.start-btn { position:relative; overflow:hidden; background-image:linear-gradient(135deg,#fbbf24,#d97706); color:#14532d; border:none; padding:16px 44px; border-radius:999px; font-size:20px; font-weight:800; cursor:pointer; margin-top:20px; box-shadow:0 12px 28px -8px rgba(217,119,6,0.55), inset 0 1px 0 rgba(255,255,255,.4); transition:transform .15s ease, box-shadow .15s ease; }
.start-btn:hover { transform:translateY(-2px); box-shadow:0 16px 32px -8px rgba(217,119,6,0.6), inset 0 1px 0 rgba(255,255,255,.45); }
.start-btn:active { transform:translateY(0) scale(.97); }
.play-area { max-width:500px; margin:0 auto; }
.progress-row { display:flex; align-items:center; gap:10px; margin-bottom:16px; color:rgba(255,255,255,0.7); font-size:14px; font-weight:700; }
.prog-bar { flex:1; height:9px; background:rgba(255,255,255,0.15); border-radius:999px; overflow:hidden; }
.prog-fill { height:100%; background-image:linear-gradient(90deg,#fbbf24,#4ade80); border-radius:999px; transition:width 0.3s; }
.proverb-card { background:rgba(255,255,255,0.08); backdrop-filter:blur(16px) saturate(160%); border-radius:22px; padding:30px 20px; text-align:center; margin-bottom:16px; border:1px solid rgba(251,191,36,0.35); box-shadow:0 12px 30px rgba(0,0,0,0.2), inset 0 1px 0 rgba(255,255,255,0.1); }
.proverb-text { color:#fde68a; font-size:20px; font-weight:700; line-height:1.6; }
.q-ask { color:rgba(255,255,255,0.7); font-size:14px; text-align:center; margin-bottom:12px; }
.choices-col { display:flex; flex-direction:column; gap:10px; margin-bottom:12px; }
.choice-btn { background:rgba(255,255,255,0.92); backdrop-filter:blur(10px); color:#14532d; border:1px solid rgba(255,255,255,0.5); padding:15px 18px; border-radius:14px; font-size:15px; font-weight:600; cursor:pointer; text-align:left; box-shadow:0 6px 16px rgba(0,0,0,0.1); transition:transform .15s ease, box-shadow .15s ease, background .15s ease; }
.choice-btn:hover:not(.disabled) { background:#fff; transform:translateY(-2px) translateX(2px); box-shadow:0 10px 22px rgba(0,0,0,0.16); }
.choice-btn.correct { background-image:linear-gradient(135deg,#10b981,#059669); color:#fff; box-shadow:0 10px 24px -6px rgba(16,185,129,0.55); }
.choice-btn.wrong { background-image:linear-gradient(135deg,#f43f5e,#e11d48); color:#fff; box-shadow:0 10px 24px -6px rgba(244,63,94,0.5); }
.choice-btn.disabled { cursor:not-allowed; }
.feedback { padding:16px 18px; border-radius:16px; backdrop-filter:blur(10px); }
.feedback.right { background:rgba(16,185,129,0.18); border:1px solid rgba(16,185,129,0.35); color:#a7f3d0; }
.feedback.wrong { background:rgba(239,68,68,0.18); border:1px solid rgba(239,68,68,0.35); color:#fca5a5; }
.explain { font-size:14px; font-weight:400; margin-top:6px; }
.result-box { text-align:center; padding:40px 20px; }
.res-score { font-size:54px; font-weight:900; color:#fde68a; text-shadow:0 2px 16px rgba(0,0,0,0.3); }
.res-detail { color:rgba(255,255,255,0.8); font-size:16px; margin:8px 0; }
.levelup { background-image:linear-gradient(135deg,#fbbf24,#d97706); color:#14532d; padding:10px 24px; border-radius:999px; font-weight:800; font-size:17px; margin:14px auto; display:inline-block; box-shadow:0 10px 24px -6px rgba(217,119,6,0.5), inset 0 1px 0 rgba(255,255,255,.4); }
.res-btns { display:flex; gap:12px; justify-content:center; margin-top:24px; }
.rbtn { background:rgba(255,255,255,0.92); backdrop-filter:blur(10px); color:#14532d; border:none; padding:13px 30px; border-radius:999px; font-size:16px; font-weight:800; cursor:pointer; box-shadow:0 6px 16px rgba(0,0,0,0.12); transition:transform .15s ease; }
.rbtn:hover { transform:translateY(-2px); }
.rbtn.home { background-image:linear-gradient(135deg,#22c55e,#15803d); color:#fff; }
</style>
