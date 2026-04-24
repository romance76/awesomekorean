<template>
  <div class="senior-memory">
    <div class="game-header">
      <button class="back-btn" @click="goBack">← 뒤로</button>
      <div class="level-badge">레벨 {{ level }} 🌸</div>
      <div class="score">⭐ {{ score }}</div>
    </div>

    <div v-if="phase==='start'" class="center-box">
      <div style="font-size:80px">🌸</div>
      <h1 class="title">기억력 카드</h1>
      <p class="subtitle">같은 그림 두 장을 찾아요!</p>
      <div class="level-info">
        <div style="font-size:16px">레벨 1: 4쌍 (2×4)</div>
        <div style="font-size:16px">레벨 2: 6쌍 (3×4)</div>
        <div style="font-size:16px">레벨 3+: 8쌍 (4×4)</div>
      </div>
      <button class="start-btn" @click="startGame">시작해요! 🃏</button>
    </div>

    <div v-if="phase==='play'" class="play-area">
      <div class="game-top">
        <div class="moves-info">👆 {{ moves }}번</div>
        <div class="matched-info">✅ {{ matched }}/{{ totalPairs }}쌍</div>
        <div class="timer-info">⏱ {{ elapsed }}초</div>
      </div>
      <div class="card-grid" :style="gridStyle">
        <div v-for="(card, i) in cards" :key="i"
          class="big-card" :class="{flipped: card.flipped||card.matched, matched: card.matched}"
          @click="flipCard(i)">
          <div class="big-face front">?</div>
          <div class="big-face back">{{ card.emoji }}</div>
        </div>
      </div>
    </div>

    <div v-if="phase==='complete'" class="result-box">
      <div style="font-size:80px">🎊</div>
      <div class="res-title">잘했어요!</div>
      <div class="res-stats">{{ moves }}번 만에 완성!</div>
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

const seniorEmojis = ['🌸','🌻','🌺','🍎','🍊','🍇','🌙','⭐','🌈','🦋','🐶','🐱','🌳','🏠','❤️','🎵']

const level = ref(parseInt(localStorage.getItem('senior_memory_level') || '1'))
const phase = ref('start')
const cards = ref([])
const moves = ref(0)
const matched = ref(0)
const elapsed = ref(0)
const score = ref(0)
const leveled = ref(false)
let f1 = ref(-1), f2 = ref(-1), checking = false
let timerInterval = null, startTime = null

const totalPairs = computed(() => level.value <= 1 ? 4 : level.value <= 2 ? 6 : 8)
const gridStyle = computed(() => {
  const cols = level.value <= 1 ? 4 : 4
  return { gridTemplateColumns: `repeat(${cols}, 1fr)` }
})

function speak(text) {
  if (!window.speechSynthesis) return
  window.speechSynthesis.cancel()
  const u = new SpeechSynthesisUtterance(text)
  u.lang = 'ko-KR'; u.rate = 0.8; u.pitch = 1.0
  window.speechSynthesis.speak(u)
}

function shuffle(a){const r=[...a];for(let i=r.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[r[i],r[j]]=[r[j],r[i]]}return r}

function startGame() {
  const pairs = totalPairs.value
  const emojis = shuffle(seniorEmojis).slice(0, pairs)
  cards.value = shuffle([...emojis, ...emojis].map(e => ({emoji:e, flipped:false, matched:false})))
  moves.value = 0; matched.value = 0; score.value = 0; leveled.value = false
  f1.value = -1; f2.value = -1; checking = false
  elapsed.value = 0; startTime = Date.now()
  clearInterval(timerInterval)
  timerInterval = setInterval(() => { elapsed.value = Math.round((Date.now()-startTime)/1000) }, 1000)
  phase.value = 'play'
  speak('같은 그림을 찾아보세요!')
}

function flipCard(i) {
  if (checking || cards.value[i].matched || cards.value[i].flipped) return
  if (f1.value >= 0 && f2.value >= 0) return
  cards.value[i] = {...cards.value[i], flipped: true}
  if (f1.value < 0) { f1.value = i; return }
  f2.value = i; moves.value++; checking = true
  setTimeout(() => {
    const c1 = cards.value[f1.value], c2 = cards.value[f2.value]
    if (c1.emoji === c2.emoji) {
      cards.value[f1.value] = {...c1, matched:true, flipped:false}
      cards.value[f2.value] = {...c2, matched:true, flipped:false}
      matched.value++; speak('맞아요!')
      if (matched.value === totalPairs.value) {
        clearInterval(timerInterval)
        const threshold = level.value <= 1 ? 12 : level.value <= 2 ? 18 : 24
        if (moves.value <= threshold) { level.value++; localStorage.setItem('senior_memory_level', level.value); leveled.value = true }
        phase.value = 'complete'
        speak(leveled.value ? '완성! 레벨업!' : '완성! 잘하셨어요!')
      }
    } else {
      cards.value[f1.value] = {...c1, flipped:false}
      cards.value[f2.value] = {...c2, flipped:false}
    }
    f1.value = -1; f2.value = -1; checking = false
  }, 1200)
}

function goBack() { clearInterval(timerInterval); router.push('/games') }
onUnmounted(() => clearInterval(timerInterval))
</script>

<style scoped>
.senior-memory { min-height:100vh; background:linear-gradient(135deg,#fdf4ff,#fce7f3,#ffe4e6); padding:16px; font-family:'Noto Sans KR',sans-serif; }
.game-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
.back-btn { background:rgba(0,0,0,0.08); color:#831843; border:none; padding:10px 16px; border-radius:20px; cursor:pointer; font-size:15px; font-weight:600; }
.level-badge,.score { background:rgba(0,0,0,0.08); color:#831843; padding:8px 16px; border-radius:20px; font-weight:700; font-size:15px; }
.center-box { text-align:center; padding:30px 20px; }
.title { font-size:40px; color:#831843; font-weight:900; margin:10px 0; }
.subtitle { color:#9d174d; font-size:18px; }
.level-info { background:rgba(255,255,255,0.7); border-radius:12px; padding:16px 20px; color:#831843; line-height:2; margin:16px auto; max-width:240px; }
.start-btn { background:#ec4899; color:#fff; border:none; padding:18px 48px; border-radius:30px; font-size:22px; font-weight:800; cursor:pointer; margin-top:20px; }
.play-area { display:flex; flex-direction:column; align-items:center; }
.game-top { display:flex; gap:12px; margin-bottom:16px; }
.moves-info,.matched-info,.timer-info { background:rgba(255,255,255,0.8); color:#831843; padding:8px 14px; border-radius:12px; font-size:16px; font-weight:700; }
.card-grid { display:grid; gap:10px; }
.big-card { width:76px; height:76px; cursor:pointer; perspective:600px; position:relative; }
.big-face { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; border-radius:14px; font-size:36px; backface-visibility:hidden; transition:transform 0.5s; }
.front { background:rgba(255,255,255,0.8); border:3px solid #f9a8d4; color:#9d174d; font-size:32px; font-weight:900; transform:rotateY(0deg); }
.back { background:rgba(255,255,255,0.95); border:3px solid #ec4899; transform:rotateY(180deg); }
.big-card.flipped .front { transform:rotateY(180deg); }
.big-card.flipped .back { transform:rotateY(0deg); }
.big-card.matched .front { transform:rotateY(180deg); }
.big-card.matched .back { background:#fce7f3; border-color:#ec4899; transform:rotateY(0deg); }
.result-box { text-align:center; padding:40px 20px; }
.res-title { font-size:40px; color:#831843; font-weight:900; margin:10px 0; }
.res-stats { color:#9d174d; font-size:20px; margin:8px 0; }
.levelup { background:#ec4899; color:#fff; padding:12px 24px; border-radius:20px; font-weight:800; font-size:20px; margin:14px auto; display:inline-block; }
.res-btns { display:flex; gap:12px; justify-content:center; margin-top:20px; }
.rbtn { background:rgba(255,255,255,0.9); color:#831843; border:2px solid #f9a8d4; padding:14px 30px; border-radius:20px; font-size:18px; font-weight:700; cursor:pointer; }
.rbtn.home { background:#ec4899; color:#fff; border:none; }
</style>
