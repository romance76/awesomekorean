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
import axios from 'axios'
import GameShell from '../../components/GameShell.vue'
import GameResultExtras from '../../components/GameResultExtras.vue'
import { useGameRecord } from '../../composables/useGameRecord'
const router = useRouter()
const rec = useGameRecord('typing')
const level = ref(parseInt(localStorage.getItem('typing_level')||'1'))
const score = ref(0); const typed = ref(0); const mistakes = ref(0)
const leveled = ref(false); const phase = ref('start')
const userInput = ref(''); const currentWord = ref(''); const wordHint = ref('')
const inputStatus = ref(''); const inputRef = ref(null)
const timeLeft = ref(60); const totalTime = 60
const wordQueue = ref([])
let timer = null

const accuracy = computed(() => {
  const total = typed.value + mistakes.value
  return total === 0 ? 100 : Math.round(typed.value / total * 100)
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
  score.value=0; typed.value=0; mistakes.value=0; leveled.value=false
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
    userInput.value = ''
    loadNextWord()
  } else if (target.startsWith(val)) {
    inputStatus.value = 'typing'
  } else {
    inputStatus.value = 'wrong'
  }
}

function skipWord() {
  if (!userInput.value) {
    mistakes.value++
    loadNextWord()
  }
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
  const token = localStorage.getItem('token')
  if (token) {
    try {
      await axios.post('/api/games/11/score',
        { score: score.value, level: level.value, result: passed?'win':'lose', duration: totalTime },
        { headers: { Authorization: `Bearer ${token}` } })
    } catch(e) {}
  }
}
</script>

<style scoped>
.typing-game { flex:1; padding:16px; font-family:'Noto Sans KR',sans-serif; }
.level-badge { background:rgba(255,255,255,.15); color:#fff; padding:4px 10px; border-radius:14px; font-weight:800; font-size:11px; white-space:nowrap; }
.timer-badge,.score-badge { background:rgba(255,255,255,.15); color:#fff; padding:4px 10px; border-radius:14px; font-weight:800; font-size:11px; white-space:nowrap; }
.timer-badge.warning { background:rgba(239,68,68,.5); animation:pulse 1s infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.6} }
.center-box,.end-box { text-align:center; padding:40px 20px; }
.title { font-size:36px; color:#fff; font-weight:900; margin:10px 0; }
.subtitle { color:rgba(255,255,255,.8); font-size:16px; }
.level-info { background:rgba(255,255,255,.1); color:#93c5fd; padding:10px 20px; border-radius:16px; display:inline-block; margin:12px 0; font-size:14px; }
.start-btn { background:#2563eb; color:#fff; border:none; padding:14px 40px; border-radius:30px; font-size:20px; font-weight:800; cursor:pointer; margin:10px 6px; }
.home-btn { background:rgba(255,255,255,.2); color:#fff; border:none; padding:12px 28px; border-radius:30px; font-size:16px; font-weight:600; cursor:pointer; margin:10px 6px; }
.play-box { max-width:480px; margin:0 auto; }
.stats-row { display:flex; justify-content:space-around; align-items:center; margin-bottom:20px; }
.stat-box { text-align:center; }
.stat-val { font-size:28px; font-weight:800; color:#fff; }
.stat-label { font-size:12px; color:rgba(255,255,255,.6); }
.timer-circle { position:relative; width:70px; height:70px; }
.timer-circle svg { width:100%; height:100%; }
.timer-text { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; color:#fff; font-size:20px; font-weight:800; }
.timer-circle.warning .timer-text { color:#fca5a5; }
.word-display { background:rgba(255,255,255,.1); border-radius:20px; padding:28px 20px; margin-bottom:16px; text-align:center; }
.word-to-type { font-size:44px; font-weight:900; color:#fff; letter-spacing:4px; }
.word-hint { color:rgba(255,255,255,.6); font-size:14px; margin-top:8px; }
.input-area { display:flex; gap:10px; margin-bottom:16px; }
.type-input { flex:1; background:rgba(255,255,255,.9); border:3px solid transparent; border-radius:16px; padding:16px 20px; font-size:22px; font-weight:700; color:#0a0a2e; outline:none; font-family:inherit; transition:border-color .2s; }
.type-input.typing { border-color:#60a5fa; }
.type-input.correct { border-color:#34d399; background:#f0fdf4; }
.type-input.wrong { border-color:#f87171; background:#fff5f5; }
.skip-btn { background:rgba(255,255,255,.15); color:#fff; border:none; padding:0 16px; border-radius:14px; font-size:13px; cursor:pointer; white-space:nowrap; }
.progress-words { display:flex; gap:8px; flex-wrap:wrap; }
.queued-word { background:rgba(255,255,255,.08); color:rgba(255,255,255,.5); padding:5px 12px; border-radius:20px; font-size:13px; }
.queued-word.active { background:rgba(37,99,235,.5); color:#fff; font-weight:700; }
.result-stats { display:flex; gap:16px; justify-content:center; margin:16px 0; flex-wrap:wrap; }
.r-stat { background:rgba(255,255,255,.1); padding:14px 20px; border-radius:14px; text-align:center; min-width:90px; }
.r-stat span { display:block; color:rgba(255,255,255,.6); font-size:12px; }
.r-stat strong { display:block; color:#fff; font-size:22px; font-weight:800; }
.end-title { font-size:32px; color:#fff; font-weight:900; }
.levelup-badge { background:#2563eb; color:#fff; padding:10px 24px; border-radius:20px; font-weight:800; font-size:16px; display:inline-block; margin:14px 0; }
</style>
