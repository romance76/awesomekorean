<template>
  <GameShell title="한국어 타이핑" icon="⌨" theme="dark"
    bg="linear-gradient(135deg,#0a0a2e,#1a1a5e,#0f3460)">
    <template #meta>
      <span class="level-badge">레벨 {{ level }}</span>
      <span class="timer-badge" :class="{warning: timeLeft<=5}" v-if="phase==='play'">⏱ {{ timeLeft }}초</span>
      <span class="score-badge">{{ score }}점</span>
    </template>
  <div class="typing-game">
    <div v-if="phase==='start'" class="center-box">
      <div style="font-size:90px">⌨️</div>
      <h1 class="title">한국어 타이핑</h1>
      <p class="subtitle">단어를 빠르게 타이핑해요!</p>
      <div class="level-info">
        <p v-if="level<=2">짧은 단어 (2~3글자)</p>
        <p v-else-if="level<=4">보통 단어 (4~5글자)</p>
        <p v-else>긴 문장 도전!</p>
      </div>
      <button class="start-btn" @click="startGame">시작하기 ▶</button>
    </div>

    <div v-if="phase==='play'" class="play-box">
      <div class="stats-row">
        <div class="stat-box">
          <div class="stat-val">{{ typed }}</div>
          <div class="stat-label">완료</div>
        </div>
        <div class="timer-circle" :class="{warning: timeLeft<=5}">
          <svg viewBox="0 0 60 60">
            <circle cx="30" cy="30" r="26" fill="none" stroke="rgba(255,255,255,.2)" stroke-width="5"/>
            <circle cx="30" cy="30" r="26" fill="none" stroke="#fff" stroke-width="5"
              stroke-dasharray="163.4" :stroke-dashoffset="163.4*(1-timeLeft/totalTime)"
              stroke-linecap="round" transform="rotate(-90 30 30)"/>
          </svg>
          <div class="timer-text">{{ timeLeft }}</div>
        </div>
        <div class="stat-box">
          <div class="stat-val">{{ accuracy }}%</div>
          <div class="stat-label">정확도</div>
        </div>
      </div>

      <div class="word-display">
        <div class="word-to-type">{{ currentWord }}</div>
        <div class="word-hint" v-if="wordHint">{{ wordHint }}</div>
      </div>

      <div class="input-area">
        <input ref="inputRef" v-model="userInput"
          class="type-input" :class="inputStatus"
          @input="checkInput" @keydown.enter="skipWord"
          placeholder="여기에 타이핑하세요..."
          autocomplete="off" autocorrect="off" spellcheck="false"/>
        <button class="skip-btn" @click="skipWord">건너뛰기</button>
      </div>

      <div class="progress-words">
        <span v-for="(w,i) in wordQueue.slice(0,5)" :key="i"
          class="queued-word" :class="{active: i===0}">{{ w }}</span>
      </div>
    </div>

    <div v-if="phase==='end'" class="end-box">
      <div style="font-size:80px">{{ score>=80?'🏆':score>=50?'🥈':'📝' }}</div>
      <h2 class="end-title">{{ score>=80?'타이핑 마스터!':score>=50?'잘 했어요!':'계속 연습해요!' }}</h2>
      <div class="result-stats">
        <div class="r-stat"><span>완성 단어</span><strong>{{ typed }}개</strong></div>
        <div class="r-stat"><span>정확도</span><strong>{{ accuracy }}%</strong></div>
        <div class="r-stat"><span>점수</span><strong>{{ score }}점</strong></div>
      </div>
      <div v-if="leveled" class="levelup-badge">🌟 레벨업! 레벨 {{ level }}!</div>
      <GameResultExtras :rec="rec" slug="typing" />
      <button class="start-btn" @click="startGame">다시 하기 🔄</button>
      <button class="home-btn" @click="$router.push('/games')">홈으로 🏠</button>
    </div>
  </div>
  </GameShell>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import GameShell from '../../components/GameShell.vue'
import GameResultExtras from '../../components/GameResultExtras.vue'
import { useGameRecord } from '../../composables/useGameRecord'
const router = useRouter()
const rec = useGameRecord('typing')
const level = ref(parseInt(localStorage.getItem('typing_level')||'1'))
const score = ref(0); const typed = ref(0); const mistakes = ref(0); const skipped = ref(0)
const leveled = ref(false); const phase = ref('start')
const userInput = ref(''); const currentWord = ref(''); const wordHint = ref('')
const inputStatus = ref(''); const inputRef = ref(null)
const timeLeft = ref(60); const totalTime = 60
const wordQueue = ref([])
let timer = null
let hadMistake = false // 예전엔 오타를 아무리 내도 정확도에 전혀 반영이 안 됐음(건너뛰기도 입력창이 비어있을 때만 카운트) — 단어별로 오타 여부를 추적

// 정확도 = (완료+건너뛴 단어 중 오타/건너뛰기 없이 끝낸 비율)
const accuracy = computed(() => {
  const totalAttempts = typed.value + skipped.value
  return totalAttempts === 0 ? 100 : Math.round((totalAttempts - mistakes.value) / totalAttempts * 100)
})

const wordPools = {
  easy: ['나무','사과','달','별','집','물','밥','고양이','강아지','해','구름','꽃','새','책','공'],
  medium: ['학교','도서관','자동차','하늘색','선생님','친구들','가족','여행','음식','운동','공부','게임','음악','그림','바다'],
  hard: ['아이스크림','자전거타기','도서관에서','공부해요','운동장에서','뛰어놀아요','한국어를','열심히배워요']
}

function speak(t) {
  if (!window.speechSynthesis) return
  window.speechSynthesis.cancel()
  const u = new SpeechSynthesisUtterance(t); u.lang='ko-KR'; u.rate=0.9
  window.speechSynthesis.speak(u)
}
function shuffle(a){ return [...a].sort(()=>Math.random()-.5) }

function getPool() {
  if (level.value <= 2) return wordPools.easy
  if (level.value <= 4) return wordPools.medium
  return [...wordPools.medium, ...wordPools.hard]
}

function startGame() {
  score.value=0; typed.value=0; mistakes.value=0; skipped.value=0; leveled.value=false
  phase.value='play'; timeLeft.value=totalTime; userInput.value=''; inputStatus.value=''
  rec.start(level.value)
  wordQueue.value = shuffle([...getPool(), ...getPool()]).slice(0, 20)
  loadNextWord()
  startTimer()
  nextTick(() => inputRef.value?.focus())
  speak('타이핑 게임 시작!')
}

function loadNextWord() {
  if (wordQueue.value.length === 0) { endGame(); return }
  currentWord.value = wordQueue.value.shift()
  wordHint.value = ''
  inputStatus.value = ''
  hadMistake = false
}

function startTimer() {
  clearInterval(timer)
  timer = setInterval(() => {
    timeLeft.value--
    if (timeLeft.value <= 0) { clearInterval(timer); endGame() }
  }, 1000)
}

function checkInput() {
  const val = userInput.value
  const target = currentWord.value
  if (val === target) {
    inputStatus.value = 'correct'
    score.value += Math.ceil(10 * (level.value * 0.5 + 0.5))
    typed.value++
    if (hadMistake) mistakes.value++
    userInput.value = ''
    loadNextWord()
  } else if (target.startsWith(val)) {
    inputStatus.value = 'typing'
  } else {
    inputStatus.value = 'wrong'
    hadMistake = true
  }
}

function skipWord() {
  // 예전엔 입력창이 비어있을 때만 건너뛰기가 동작해서, 오타를 낸 채로 건너뛰려 하면
  // 아무 반응이 없었음(정확도에도 반영 안 됨) — 항상 건너뛸 수 있고 건너뛰기는 항상
  // 정확도에 반영되도록 수정.
  mistakes.value++
  skipped.value++
  userInput.value = ''
  loadNextWord()
}

async function endGame() {
  clearInterval(timer)
  phase.value = 'end'
  const passed = typed.value >= 5 && accuracy.value >= 70
  if (passed) {
    level.value++; localStorage.setItem('typing_level', level.value); leveled.value = true
    speak('훌륭해요! 레벨업!')
  } else speak('잘 했어요! 더 빠르게 연습해봐요!')
  await rec.end({ won: passed, leveledUp: leveled.value, score: score.value })
}
</script>

<style scoped>
.typing-game { flex:1; padding:16px; font-family:'Noto Sans KR',sans-serif; }
.level-badge { background:rgba(255,255,255,.1); backdrop-filter:blur(8px); border:1px solid rgba(255,255,255,.14); color:#fff; padding:5px 12px; border-radius:999px; font-weight:800; font-size:11px; white-space:nowrap; }
.timer-badge,.score-badge { background:rgba(255,255,255,.1); backdrop-filter:blur(8px); border:1px solid rgba(255,255,255,.14); color:#fff; padding:5px 12px; border-radius:999px; font-weight:800; font-size:11px; white-space:nowrap; }
.timer-badge.warning { background:rgba(239,68,68,.4); border-color:rgba(239,68,68,.5); animation:pulse 1s infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.6} }
.center-box,.end-box { text-align:center; padding:40px 20px; }
.title { font-size:36px; color:#fff; font-weight:900; margin:10px 0; text-shadow:0 2px 12px rgba(0,0,0,.25); }
.subtitle { color:rgba(255,255,255,.8); font-size:16px; }
.level-info { background:rgba(255,255,255,.1); backdrop-filter:blur(12px); border:1px solid rgba(255,255,255,.18); color:#93c5fd; padding:11px 22px; border-radius:999px; display:inline-block; margin:14px 0; font-size:14px; font-weight:700; box-shadow:0 8px 20px rgba(0,0,0,0.15); }
.start-btn { position:relative; overflow:hidden; background-image:linear-gradient(135deg,#60a5fa,#2563eb); color:#fff; border:none; padding:16px 44px; border-radius:999px; font-size:20px; font-weight:800; cursor:pointer; margin:10px 6px; box-shadow:0 12px 28px -8px rgba(37,99,235,0.6), inset 0 1px 0 rgba(255,255,255,.35); transition:transform .15s ease, box-shadow .15s ease; }
.start-btn:hover { transform:translateY(-2px); box-shadow:0 16px 32px -8px rgba(37,99,235,0.65), inset 0 1px 0 rgba(255,255,255,.4); }
.start-btn:active { transform:translateY(0) scale(.97); }
.home-btn { background:rgba(255,255,255,.12); backdrop-filter:blur(10px); border:1px solid rgba(255,255,255,.2); color:#fff; padding:13px 30px; border-radius:999px; font-size:16px; font-weight:700; cursor:pointer; margin:10px 6px; transition:background .15s ease; }
.home-btn:hover { background:rgba(255,255,255,.2); }
.play-box { max-width:480px; margin:0 auto; }
.stats-row { display:flex; justify-content:space-around; align-items:center; margin-bottom:22px; }
.stat-box { text-align:center; }
.stat-val { font-size:28px; font-weight:800; color:#fff; }
.stat-label { font-size:12px; color:rgba(255,255,255,.6); }
.timer-circle { position:relative; width:70px; height:70px; }
.timer-circle svg { width:100%; height:100%; }
.timer-text { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; color:#fff; font-size:20px; font-weight:800; }
.timer-circle.warning .timer-text { color:#fca5a5; }
.word-display { background:rgba(255,255,255,.08); backdrop-filter:blur(16px) saturate(160%); border:1px solid rgba(255,255,255,.15); border-radius:22px; padding:30px 20px; margin-bottom:18px; text-align:center; box-shadow:0 12px 30px rgba(0,0,0,0.2), inset 0 1px 0 rgba(255,255,255,0.08); }
.word-to-type { font-size:44px; font-weight:900; color:#fff; letter-spacing:4px; }
.word-hint { color:rgba(255,255,255,.6); font-size:14px; margin-top:8px; }
.input-area { display:flex; gap:10px; margin-bottom:18px; }
.type-input { flex:1; background:rgba(255,255,255,.92); backdrop-filter:blur(10px); border:3px solid transparent; border-radius:18px; padding:16px 20px; font-size:22px; font-weight:700; color:#0a0a2e; outline:none; font-family:inherit; transition:border-color .2s, box-shadow .2s; box-shadow:0 6px 16px rgba(0,0,0,0.15); }
.type-input.typing { border-color:#60a5fa; }
.type-input.correct { border-color:#34d399; background:rgba(240,253,244,.95); }
.type-input.wrong { border-color:#f87171; background:rgba(255,245,245,.95); }
.skip-btn { background:rgba(255,255,255,.12); backdrop-filter:blur(8px); border:1px solid rgba(255,255,255,.18); color:#fff; padding:0 18px; border-radius:999px; font-size:13px; font-weight:700; cursor:pointer; white-space:nowrap; transition:background .15s ease; }
.skip-btn:hover { background:rgba(255,255,255,.2); }
.progress-words { display:flex; gap:8px; flex-wrap:wrap; }
.queued-word { background:rgba(255,255,255,.06); backdrop-filter:blur(6px); color:rgba(255,255,255,.5); padding:6px 14px; border-radius:999px; font-size:13px; font-weight:600; }
.queued-word.active { background-image:linear-gradient(135deg,#60a5fa,#2563eb); color:#fff; font-weight:800; box-shadow:0 4px 12px -3px rgba(37,99,235,0.5); }
.result-stats { display:flex; gap:16px; justify-content:center; margin:18px 0; flex-wrap:wrap; }
.r-stat { background:rgba(255,255,255,.08); backdrop-filter:blur(12px); border:1px solid rgba(255,255,255,.12); padding:15px 22px; border-radius:16px; text-align:center; min-width:90px; box-shadow:0 8px 20px rgba(0,0,0,0.15); }
.r-stat span { display:block; color:rgba(255,255,255,.6); font-size:12px; }
.r-stat strong { display:block; color:#fff; font-size:22px; font-weight:800; }
.end-title { font-size:32px; color:#fff; font-weight:900; text-shadow:0 2px 12px rgba(0,0,0,.25); }
.levelup-badge { background-image:linear-gradient(135deg,#60a5fa,#2563eb); color:#fff; padding:10px 24px; border-radius:999px; font-weight:800; font-size:17px; display:inline-block; margin:14px 0; box-shadow:0 10px 24px -6px rgba(37,99,235,0.55), inset 0 1px 0 rgba(255,255,255,.35); }
</style>
