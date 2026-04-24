<template>
  <div class="breathing-game">
    <div class="game-header">
      <button class="back-btn" @click="goBack">← 뒤로</button>
      <div class="level-badge">레벨 {{ level }} 🧘</div>
      <div class="score">{{ completedCycles }}회 완료</div>
    </div>

    <div v-if="phase==='start'" class="center-box">
      <div style="font-size:80px">🧘</div>
      <h1 class="title">명상 호흡</h1>
      <p class="subtitle">화면을 따라 천천히 숨 쉬어요<br>마음이 편안해져요</p>
      <div class="level-info">
        <div>레벨 1: 4-4-4 호흡 (박스 호흡)</div>
        <div>레벨 2: 4-7-8 호흡 (이완 호흡)</div>
        <div>레벨 3: 복식 호흡 훈련</div>
      </div>
      <button class="start-btn" @click="startGame">시작 🍃</button>
    </div>

    <div v-if="phase==='breathing'" class="breathing-area">
      <div class="cycle-count">{{ completedCycles }}회 / {{ targetCycles }}회</div>
      <div class="breath-circle" :class="breathPhase" :style="circleStyle">
        <div class="breath-text">{{ breathInstruction }}</div>
        <div class="breath-count">{{ breathCount }}</div>
      </div>
      <div class="phase-label">{{ phaseLabel }}</div>
      <div class="progress-dots">
        <span v-for="i in targetCycles" :key="i" class="dot" :class="{done: i<=completedCycles}"></span>
      </div>
      <button class="stop-btn" @click="stopGame">일시정지</button>
    </div>

    <div v-if="phase==='complete'" class="result-box">
      <div style="font-size:80px">🌟</div>
      <h2 class="res-title">수고했어요!</h2>
      <div class="res-detail">{{ completedCycles }}회 호흡 완료</div>
      <div class="res-msg">몸과 마음이 편안해졌나요?</div>
      <div v-if="leveled" class="levelup">🎉 레벨업! 레벨 {{ level }}!</div>
      <GameResultExtras :rec="rec" slug="breathing" />
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
import GameResultExtras from '../../components/GameResultExtras.vue'
import { useGameRecord } from '../../composables/useGameRecord'
const router = useRouter()
const rec = useGameRecord('breathing')

const level = ref(parseInt(localStorage.getItem('breathing_level') || '1'))
const phase = ref('start')
const breathPhase = ref('inhale')
const breathCount = ref(4)
const completedCycles = ref(0)
const leveled = ref(false)
let breathTimer = null

const patterns = {
  1: [{phase:'inhale',dur:4,text:'들이쉬기',label:'코로 천천히 들이쉬어요'},{phase:'hold',dur:4,text:'참기',label:'숨을 참아요'},{phase:'exhale',dur:4,text:'내쉬기',label:'입으로 천천히 내쉬어요'},{phase:'hold2',dur:4,text:'참기',label:'잠시 쉬어요'}],
  2: [{phase:'inhale',dur:4,text:'들이쉬기',label:'코로 4초 들이쉬어요'},{phase:'hold',dur:7,text:'참기',label:'7초 동안 참아요'},{phase:'exhale',dur:8,text:'내쉬기',label:'입으로 8초 내쉬어요'}],
  3: [{phase:'inhale',dur:5,text:'배로 숨쉬기',label:'배가 불룩해지도록 들이쉬어요'},{phase:'hold',dur:2,text:'참기',label:'잠깐 멈춰요'},{phase:'exhale',dur:6,text:'배 집어넣기',label:'배를 집어넣으며 내쉬어요'}],
}

const targetCycles = computed(() => level.value <= 1 ? 5 : level.value <= 2 ? 6 : 8)

const breathInstruction = ref('준비')
const phaseLabel = ref('시작해요')
const circleStyle = ref({})

function speak(text, rate = 0.7) {
  if (!window.speechSynthesis) return
  window.speechSynthesis.cancel()
  const u = new SpeechSynthesisUtterance(text)
  u.lang = 'ko-KR'; u.rate = rate; u.pitch = 0.9; u.volume = 0.8
  window.speechSynthesis.speak(u)
}

function startGame() {
  completedCycles.value = 0; leveled.value = false
  rec.start(level.value)
  phase.value = 'breathing'
  speak('호흡 명상을 시작합니다. 편안하게 앉으세요.')
  setTimeout(runBreathing, 2000)
}

async function runBreathing() {
  const pattern = patterns[Math.min(level.value, 3)]
  while (completedCycles.value < targetCycles.value && phase.value === 'breathing') {
    for (const step of pattern) {
      if (phase.value !== 'breathing') return
      breathPhase.value = step.phase
      breathInstruction.value = step.text
      phaseLabel.value = step.label
      circleStyle.value = {
        transform: step.phase === 'inhale' ? 'scale(1.3)' : step.phase === 'exhale' ? 'scale(0.85)' : 'scale(1.1)',
        transition: `transform ${step.dur}s ease`
      }
      speak(step.text, 0.6)
      for (let i = step.dur; i >= 1; i--) {
        if (phase.value !== 'breathing') return
        breathCount.value = i
        await delay(1000)
      }
    }
    completedCycles.value++
    if (completedCycles.value < targetCycles.value) speak('잘했어요. 한 번 더.')
  }
  if (phase.value === 'breathing') {
    phase.value = 'complete'
    speak('모두 완료했어요. 수고했습니다.')
    if (targetCycles.value >= 5) {
      level.value++; localStorage.setItem('breathing_level', level.value)
      leveled.value = true
    }
    await rec.end({ won: true, leveledUp: leveled.value, score: completedCycles.value * 10 })
  }
}

function delay(ms) { return new Promise(r => setTimeout(r, ms)) }
function stopGame() { phase.value = 'start'; window.speechSynthesis.cancel() }
function goBack() { phase.value = 'start'; window.speechSynthesis.cancel(); router.push('/games') }
onUnmounted(() => { clearInterval(breathTimer); window.speechSynthesis?.cancel() })
</script>

<style scoped>
.breathing-game { min-height:100vh; background:linear-gradient(180deg,#0f2027,#203a43,#2c5364); padding:16px; font-family:'Noto Sans KR',sans-serif; }
.game-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; }
.back-btn { background:rgba(255,255,255,0.1); color:#a7f3d0; border:none; padding:8px 14px; border-radius:20px; cursor:pointer; font-size:14px; }
.level-badge,.score { background:rgba(255,255,255,0.1); color:#a7f3d0; padding:6px 14px; border-radius:20px; font-weight:700; }
.center-box { text-align:center; padding:30px 20px; }
.title { font-size:36px; color:#a7f3d0; font-weight:900; margin:10px 0; }
.subtitle { color:rgba(255,255,255,0.7); font-size:16px; line-height:1.8; }
.level-info { background:rgba(255,255,255,0.07); border-radius:12px; padding:14px 20px; color:#6ee7b7; font-size:14px; line-height:1.9; margin:16px auto; max-width:280px; text-align:left; }
.start-btn { background:#10b981; color:#fff; border:none; padding:16px 44px; border-radius:30px; font-size:20px; font-weight:700; cursor:pointer; margin-top:20px; }
.breathing-area { display:flex; flex-direction:column; align-items:center; padding:20px; }
.cycle-count { color:#6ee7b7; font-size:16px; font-weight:600; margin-bottom:20px; }
.breath-circle { width:200px; height:200px; border-radius:50%; background:radial-gradient(circle,rgba(16,185,129,0.5),rgba(6,182,212,0.2)); border:3px solid rgba(16,185,129,0.5); display:flex; flex-direction:column; align-items:center; justify-content:center; margin-bottom:24px; box-shadow:0 0 40px rgba(16,185,129,0.2); }
.breath-text { color:#fff; font-size:22px; font-weight:700; }
.breath-count { color:#6ee7b7; font-size:36px; font-weight:900; margin-top:4px; }
.phase-label { color:rgba(255,255,255,0.7); font-size:16px; text-align:center; margin-bottom:24px; }
.progress-dots { display:flex; gap:10px; margin-bottom:24px; }
.dot { width:16px; height:16px; border-radius:50%; background:rgba(255,255,255,0.2); transition:background 0.5s; }
.dot.done { background:#10b981; }
.stop-btn { background:rgba(255,255,255,0.12); color:#a7f3d0; border:none; padding:10px 24px; border-radius:20px; cursor:pointer; font-size:15px; }
.result-box { text-align:center; padding:40px 20px; }
.res-title { font-size:34px; color:#a7f3d0; font-weight:900; margin:10px 0; }
.res-detail { color:rgba(255,255,255,0.8); font-size:18px; }
.res-msg { color:#6ee7b7; font-size:16px; margin:8px 0; }
.levelup { background:#10b981; color:#fff; padding:10px 20px; border-radius:20px; font-weight:800; font-size:18px; margin:14px auto; display:inline-block; }
.res-btns { display:flex; gap:12px; justify-content:center; margin-top:20px; }
.rbtn { background:rgba(255,255,255,0.9); color:#0f2027; border:none; padding:12px 28px; border-radius:20px; font-size:16px; font-weight:700; cursor:pointer; }
.rbtn.home { background:#10b981; color:#fff; }
</style>
