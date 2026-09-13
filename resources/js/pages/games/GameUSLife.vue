<template>
  <GameShell title="미국 생활 상식" icon="🇺🇸" theme="dark" :level="level" :score="score"
    bg="linear-gradient(135deg,#1e3a5f,#1d4ed8,#2563eb)">
  <div class="uslife-game">
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
      <GameResultExtras :rec="rec" slug="us_life" />
      <div class="res-btns">
        <button class="rbtn" @click="startGame">다시 🔄</button>
        <button class="rbtn home" @click="goBack">홈 🏠</button>
      </div>
    </div>
  </div>
  </GameShell>
</template>

<script setup>
import { ref, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import GameShell from '../../components/GameShell.vue'
import GameResultExtras from '../../components/GameResultExtras.vue'
import { useGameRecord } from '../../composables/useGameRecord'
const router = useRouter()
const rec = useGameRecord('us_life')

// 예전엔 총 10문제뿐이라(레벨1-2 전용 문제는 5개뿐) 감사에서 "컨셉은 제일 좋은데
// 콘텐츠가 제일 적다"고 지적됨 — 운전·세금·생활·의료·금융·이민·법률 각 분야를
// 보강해 33문제로 확충.
const usQuiz = [
  {level:1,category:'운전',question:'미국에서 오른쪽에 빨간불이 있어도 할 수 있는 것은?',options:['우회전','유턴','좌회전','직진'],answer:'우회전',explain:'미국은 "No Turn On Red" 표지가 없으면 빨간불에도 우회전 가능해요.'},
  {level:1,category:'운전',question:'미국 운전면허 필기시험에서 음주운전 혈중 알코올 기준은?',options:['0.08%','0.1%','0.05%','0.03%'],answer:'0.08%',explain:'미국 대부분의 주에서 BAC 0.08% 이상이면 DUI로 처벌받아요.'},
  {level:1,category:'세금',question:'미국 연방 소득세 신고 마감일은?',options:['4월 15일','1월 31일','12월 31일','6월 15일'],answer:'4월 15일',explain:'Tax Day는 매년 4월 15일이에요. 주말이면 다음 월요일로 연장돼요.'},
  {level:1,category:'생활',question:'미국에서 팁(Tip)의 일반적인 비율은?',options:['15-20%','5-10%','25-30%','1-5%'],answer:'15-20%',explain:'레스토랑에서는 보통 15~20%의 팁을 남기는 것이 에티켓이에요.'},
  {level:1,category:'의료',question:'미국에서 119 응급전화 번호는?',options:['911','119','999','000'],answer:'911',explain:'미국 응급전화는 911이에요. 경찰·소방·구급 모두 911로 연결돼요.'},
  {level:1,category:'세금',question:'미국에서 급여마다 자동으로 떼는 사회보장세·메디케어세를 합쳐 부르는 이름은?',options:['FICA','VAT','GST','Excise Tax'],answer:'FICA',explain:'FICA(Federal Insurance Contributions Act)는 사회보장세와 메디케어세를 합친 급여공제 항목이에요.'},
  {level:1,category:'생활',question:'미국 마트에서 계산할 때 진열 가격보다 최종 금액이 높은 주된 이유는?',options:['판매세(Sales Tax)가 별도로 붙어서','카드 수수료 때문에','항상 할인이 적용돼서','환율 때문에'],answer:'판매세(Sales Tax)가 별도로 붙어서',explain:'미국은 진열 가격에 세금이 포함 안 돼 있어 계산할 때 주(州)별 판매세가 추가돼요.'},
  {level:1,category:'생활',question:'미국에서 집 주소를 증빙할 때 흔히 인정되는 서류는?',options:['공과금 고지서(Utility Bill)','학생증','명함','진단서'],answer:'공과금 고지서(Utility Bill)',explain:'전기·가스 등 공과금 고지서는 은행·DMV 등에서 주소 증빙 서류로 흔히 쓰여요.'},
  {level:1,category:'운전',question:'미국에서 보호자 동승이 필요한 미성년자용 임시 운전면허를 뭐라 부르나요?',options:["Learner's Permit",'Full License','CDL','REAL ID'],answer:"Learner's Permit",explain:'정식 면허 전 단계로, 보호자 동승 하에 연습 운전을 할 수 있는 허가증이에요.'},
  {level:1,category:'의료',question:'처방전 없이 약국에서 바로 살 수 있는 약을 줄여서 뭐라 부르나요?',options:['OTC','Rx','ER','HMO'],answer:'OTC',explain:'Over-The-Counter(OTC)는 처방전이 필요 없는 일반의약품을 뜻해요.'},
  {level:1,category:'생활',question:'미국 급여명세서(Pay Stub)에서 세금 등을 공제하고 실제 받는 금액을 뭐라 하나요?',options:['Net Pay','Gross Pay','Overtime','Bonus'],answer:'Net Pay',explain:'Gross Pay(세전 총액)에서 세금·보험료 등을 뗀 실수령액이 Net Pay예요.'},
  {level:1,category:'법률',question:'미국에서 타인의 우편함에 온 편지를 허락 없이 열어보면 어떻게 되나요?',options:['연방법 위반이다','문제없다','가족이면 합법이다','이웃이면 합법이다'],answer:'연방법 위반이다',explain:'타인의 우편물을 무단으로 열어보는 것은 연방 범죄(우편물 절도·훼손)에 해당해요.'},
  {level:1,category:'운전',question:'자동차 등록증(Registration)과 보험증(Insurance Card)은 보통 어디에 보관하나요?',options:['차량 안(글로브박스 등)','집 서랍','은행 금고','안 갖고 다녀도 됨'],answer:'차량 안(글로브박스 등)',explain:'경찰 검문 시 바로 제시해야 하므로 항상 차량 안에 보관하는 게 좋아요.'},
  {level:2,category:'의료',question:'미국 의료보험 중 65세 이상 노인을 위한 것은?',options:['Medicare','Medicaid','CHIP','ACA'],answer:'Medicare',explain:'Medicare는 65세 이상 시민권자/영주권자를 위한 연방 의료보험이에요.'},
  {level:2,category:'금융',question:'미국 신용점수(FICO Score)에서 "Good"은 몇 점부터인가요?',options:['670점 이상','500점 이상','800점 이상','600점 이상'],answer:'670점 이상',explain:'FICO 점수: 670-739=Good, 740-799=Very Good, 800+=Exceptional이에요.'},
  {level:2,category:'금융',question:'미국 은행 계좌의 예금자 보호 한도는?',options:['$250,000','$100,000','$500,000','$1,000,000'],answer:'$250,000',explain:'FDIC가 인당 $250,000까지 예금을 보호해요.'},
  {level:2,category:'금융',question:'미국 신용카드 대금을 최소금액만 내고 나머지를 이월하면 붙는 것은?',options:['이자(APR)','판매세','등록세','아무것도 안 붙음'],answer:'이자(APR)',explain:'이월 잔액에는 카드사가 정한 연이율(APR)만큼 이자가 붙어요.'},
  {level:2,category:'의료',question:'미국에서 저소득층을 위한 정부 의료보험은?',options:['Medicaid','Medicare','ACA','FSA'],answer:'Medicaid',explain:'Medicaid는 소득 기준을 충족하는 저소득층을 위한 연방·주 공동 의료보험이에요.'},
  {level:2,category:'금융',question:'다음 중 미국 3대 신용평가사에 속하지 않는 곳은?',options:['Visa','Experian','Equifax','TransUnion'],answer:'Visa',explain:'Visa는 카드 결제망 회사예요. 신용평가사는 Experian·Equifax·TransUnion 세 곳이에요.'},
  {level:2,category:'세금',question:'미국에서 개인 소득세 신고에 쓰이는 대표 양식은?',options:['Form 1040','W-2','W-4','I-9'],answer:'Form 1040',explain:'W-2는 고용주가 주는 소득명세서, W-4는 원천징수 신청서예요. 실제 신고서는 Form 1040이에요.'},
  {level:2,category:'금융',question:'미국에서 집을 살 때 받는 장기 주택담보대출을 뭐라 하나요?',options:['모기지(Mortgage)','리스(Lease)','오버드래프트','크레딧라인'],answer:'모기지(Mortgage)',explain:'모기지는 집을 담보로 은행에서 빌리는 장기 대출이에요.'},
  {level:2,category:'의료',question:'내가 가입한 보험사와 계약이 되어 있어 진료비가 더 저렴한 병원 상태를 뭐라 하나요?',options:['In-Network','Out-of-Network','Copay','Deductible'],answer:'In-Network',explain:'In-Network(네트워크 내) 병원을 이용하면 보험 적용을 더 많이 받아 저렴해요.'},
  {level:2,category:'금융',question:'매달 정해진 날짜에 자동으로 계좌에서 요금이 빠져나가게 설정하는 것을 뭐라 하나요?',options:['자동이체(Autopay)','수표(Check)','머니오더','우편환'],answer:'자동이체(Autopay)',explain:'Autopay를 설정하면 공과금·구독료 등이 매달 자동으로 결제돼요.'},
  {level:3,category:'이민',question:'미국 영주권(Green Card) 취득 후 시민권 신청 가능 기간은?',options:['5년 후','3년 후 (배우자)','7년 후','10년 후'],answer:'3년 후 (배우자)',explain:'미국 시민권자 배우자는 3년, 일반 영주권자는 5년 후 시민권 신청 가능해요.'},
  {level:3,category:'법률',question:'미국에서 Miranda Rights(미란다 원칙)는 언제 고지해야 하나요?',options:['체포 후 심문 시','경찰 접촉 시','법원 출두 시','구금 24시간 후'],answer:'체포 후 심문 시',explain:'체포 후 심문 전 "당신은 묵비권을 행사할 수 있습니다..." 고지가 필요해요.'},
  {level:3,category:'법률',question:'형사재판에서 배심원 재판을 받을 권리를 보장하는 수정헌법은?',options:['수정헌법 6조','수정헌법 1조','수정헌법 5조','수정헌법 14조'],answer:'수정헌법 6조',explain:'수정헌법 6조는 신속한 공개재판과 배심원 재판을 받을 권리를 보장해요.'},
  {level:3,category:'이민',question:'미국 영주권자가 6개월 이상 해외에 계속 체류하면 어떤 문제가 생길 수 있나요?',options:['입국·영주권 유지에 문제가 생길 수 있다','자동으로 시민권을 얻는다','세금이 면제된다','아무 문제 없다'],answer:'입국·영주권 유지에 문제가 생길 수 있다',explain:'장기간 해외 체류는 "영주권 포기 의사"로 해석될 수 있어 입국 심사에서 문제가 될 수 있어요.'},
  {level:3,category:'이민',question:'미국 시민권 신청 시 실제로 치르는 시험은?',options:['영어 및 미국 역사·공민(Civics) 시험','수학 시험','운전 시험','체력 시험'],answer:'영어 및 미국 역사·공민(Civics) 시험',explain:'영어 읽기·쓰기·말하기와 미국 역사·정부에 관한 공민 시험을 함께 봐요.'},
  {level:3,category:'법률',question:'형사 피고인이 변호사를 구할 형편이 안 되면 어떻게 되나요?',options:['국선변호인이 무료로 지정된다','재판을 받을 수 없다','자비로 반드시 구해야 한다','유죄로 간주된다'],answer:'국선변호인이 무료로 지정된다',explain:'수정헌법 6조에 따라 변호사를 구할 수 없으면 국선변호인(Public Defender)이 지정돼요.'},
  {level:3,category:'세금',question:'미국에서 세금 신고 기한 연장을 신청할 때 쓰는 양식은?',options:['Form 4868','Form 1040','W-2','I-9'],answer:'Form 4868',explain:'Form 4868을 제출하면 자동으로 6개월 신고 기한 연장을 받을 수 있어요(납부 기한은 그대로).'},
  {level:3,category:'이민',question:'미국 영주권자가 특정 중범죄로 유죄 판결을 받으면 어떤 일이 생길 수 있나요?',options:['추방(Deportation) 대상이 될 수 있다','벌금만 내면 끝난다','영주권이 자동 갱신된다','아무 영향 없다'],answer:'추방(Deportation) 대상이 될 수 있다',explain:'특정 중범죄는 영주권자라도 추방 사유가 될 수 있어요.'},
  {level:3,category:'법률',question:'경찰이 영장 없이 함부로 집을 수색하지 못하도록 보장하는 수정헌법은?',options:['수정헌법 4조','수정헌법 2조','수정헌법 8조','수정헌법 10조'],answer:'수정헌법 4조',explain:'수정헌법 4조는 불합리한 압수·수색으로부터 보호받을 권리를 보장해요.'},
  {level:3,category:'세금',question:'자영업자가 일반 근로자의 FICA(사회보장·메디케어세)를 대신 내는 세금은?',options:['Self-Employment Tax','Payroll Tax','Excise Tax','Property Tax'],answer:'Self-Employment Tax',explain:'자영업자는 고용주 몫까지 본인이 부담하는 Self-Employment Tax를 내요.'},
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
  totalQ.value = queue.length // 낮은 레벨은 문제은행이 10개보다 적을 수 있어 실제 큐 길이로 맞춤 — 안 맞추면 배열 끝을 넘어가 크래시남
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
  if(wasRight.value){ correct.value++; score.value+=10+timeLeft.value; speak('정답!') }
  else speak('오답!')
  setTimeout(nextQuestion,3000)
}

async function endGame() {
  clearInterval(timer); phase.value='result'
  const won = correct.value >= 7
  if(won){ level.value++; localStorage.setItem('uslife_level',level.value); leveled.value=true; speak('레벨업!') }
  await rec.end({ won, leveledUp: leveled.value, score: score.value })
}

function goBack() { clearInterval(timer); router.push('/games') }
onUnmounted(()=>clearInterval(timer))
</script>

<style scoped>
.uslife-game { flex:1; padding:16px; font-family:'Noto Sans KR',sans-serif; }
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
