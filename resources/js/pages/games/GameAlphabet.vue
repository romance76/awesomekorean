<template>
  <div class="alphabet-game">
    <!-- Header -->
    <div class="game-header">
      <button class="back-btn" @click="goBack">← Back</button>
      <div class="game-title">
        <span class="title-icon">🔤</span>
        <span>ABC 알파벳!</span>
      </div>
      <div class="star-display">⭐ {{ totalStars }}</div>
    </div>

    <!-- Mode Selection Screen -->
    <div v-if="gameState === 'mode'" class="mode-screen">
      <div class="mode-title">
        <div class="big-emoji">🌈</div>
        <h1>ABC 게임</h1>
        <p>어떤 게임을 할까요?</p>
      </div>
      <div class="mode-cards">
        <div class="mode-card" @click="startGame('case')">
          <div class="mode-icon">Aa</div>
          <div class="mode-name">대문자 → 소문자</div>
          <div class="mode-desc">대문자를 보고 소문자를 찾아요!</div>
        </div>
        <div class="mode-card" @click="startGame('picture')">
          <div class="mode-icon">🍎</div>
          <div class="mode-name">그림 → 알파벳</div>
          <div class="mode-desc">그림을 보고 첫 글자를 찾아요!</div>
        </div>
      </div>
      <div v-if="rec.maxCompletedLevel.value > 0" class="progress-info">
        🎯 최고 클리어: Lv.{{ rec.maxCompletedLevel.value }}
      </div>
    </div>

    <!-- Game Playing Screen -->
    <div v-else-if="gameState === 'playing'" class="playing-screen">
      <!-- Progress -->
      <div class="progress-section">
        <div class="progress-text">{{ currentQ + 1 }} / {{ totalQuestions }}</div>
        <div class="progress-bar">
          <div class="progress-fill" :style="{ width: progressPercent + '%' }"></div>
        </div>
        <div class="score-badge">✅ {{ score }}</div>
      </div>

      <!-- Question Display -->
      <div class="question-area">
        <div class="question-card" :class="{ 'bounce-in': questionAnimation }">
          <template v-if="currentMode === 'case'">
            <div class="question-letter">{{ currentQuestion.upper }}</div>
            <div class="question-hint">소문자를 찾아요!</div>
          </template>
          <template v-else>
            <div class="question-emoji">{{ currentQuestion.emoji }}</div>
            <div class="question-word">{{ currentQuestion.word }}</div>
            <div class="question-hint">첫 글자는 무엇일까요?</div>
          </template>
        </div>
      </div>

      <!-- Feedback Message -->
      <transition name="feedback-anim">
        <div v-if="feedback" class="feedback-message" :class="feedback.type">
          {{ feedback.message }}
        </div>
      </transition>

      <!-- Answer Choices -->
      <div class="choices-grid">
        <button
          v-for="(choice, idx) in currentChoices"
          :key="idx"
          class="choice-btn"
          :class="getChoiceClass(choice)"
          :disabled="answered"
          @click="selectAnswer(choice)"
        >
          <span class="choice-letter">{{ choice }}</span>
        </button>
      </div>
    </div>

    <!-- Result Screen -->
    <div v-else-if="gameState === 'result'" class="result-screen">
      <div class="result-content">
        <div class="result-stars-display">
          <span
            v-for="n in 10"
            :key="n"
            class="result-star"
            :class="{ earned: n <= score, animate: n <= score }"
            :style="{ animationDelay: (n * 0.1) + 's' }"
          >⭐</span>
        </div>
        <div class="result-score-text">
          <span class="score-num">{{ score }}</span> / 10
        </div>
        <div class="result-grade">
          <span v-if="score === 10">🏆 완벽해요! 천재!</span>
          <span v-else-if="score >= 8">🥇 정말 잘했어요!</span>
          <span v-else-if="score >= 6">🥈 잘했어요!</span>
          <span v-else-if="score >= 4">🥉 조금 더 연습해요!</span>
          <span v-else>💪 다시 도전해봐요!</span>
        </div>
        <div class="star-earn" v-if="earnedStars > 0">
          <div class="earn-anim">⭐ {{ earnedStars }} STAR 획득!</div>
        </div>
        <GameResultExtras :rec="rec" slug="alphabet" />
        <div class="result-btns">
          <button class="retry-btn" @click="startGame(currentMode)">🔄 다시 하기</button>
          <button class="menu-btn" @click="gameState = 'mode'">📋 메뉴로</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import GameResultExtras from '../../components/GameResultExtras.vue'
import { useGameRecord } from '../../composables/useGameRecord'

const router = useRouter()
const rec = useGameRecord('alphabet')
// 모드별로 레벨 구분: case=1, word=2, sound=3
const modeToLevel = { case: 1, word: 2, sound: 3 }

const letters = [
  { upper: 'A', lower: 'a', emoji: '🍎', word: 'Apple' },
  { upper: 'B', lower: 'b', emoji: '🐝', word: 'Bee' },
  { upper: 'C', lower: 'c', emoji: '🐱', word: 'Cat' },
  { upper: 'D', lower: 'd', emoji: '🐶', word: 'Dog' },
  { upper: 'E', lower: 'e', emoji: '🥚', word: 'Egg' },
  { upper: 'F', lower: 'f', emoji: '🐟', word: 'Fish' },
  { upper: 'G', lower: 'g', emoji: '🍇', word: 'Grape' },
  { upper: 'H', lower: 'h', emoji: '🏠', word: 'House' },
  { upper: 'I', lower: 'i', emoji: '🍦', word: 'Ice cream' },
  { upper: 'J', lower: 'j', emoji: '🃏', word: 'Joker' },
  { upper: 'K', lower: 'k', emoji: '🪁', word: 'Kite' },
  { upper: 'L', lower: 'l', emoji: '🦁', word: 'Lion' },
  { upper: 'M', lower: 'm', emoji: '🌙', word: 'Moon' },
  { upper: 'N', lower: 'n', emoji: '📰', word: 'News' },
  { upper: 'O', lower: 'o', emoji: '🍊', word: 'Orange' },
  { upper: 'P', lower: 'p', emoji: '🍕', word: 'Pizza' },
  { upper: 'Q', lower: 'q', emoji: '👸', word: 'Queen' },
  { upper: 'R', lower: 'r', emoji: '🌈', word: 'Rainbow' },
  { upper: 'S', lower: 's', emoji: '⭐', word: 'Star' },
  { upper: 'T', lower: 't', emoji: '🌳', word: 'Tree' },
  { upper: 'U', lower: 'u', emoji: '☂️', word: 'Umbrella' },
  { upper: 'V', lower: 'v', emoji: '🎻', word: 'Violin' },
  { upper: 'W', lower: 'w', emoji: '🐋', word: 'Whale' },
  { upper: 'X', lower: 'x', emoji: '🎅', word: 'Xmas' },
  { upper: 'Y', lower: 'y', emoji: '🧶', word: 'Yarn' },
  { upper: 'Z', lower: 'z', emoji: '🦓', word: 'Zebra' },
]

const gameState = ref('mode')
const currentMode = ref('case')
const totalQuestions = 10
const currentQ = ref(0)
const score = ref(0)
const earnedStars = ref(0)
const totalStars = ref(0)
const answered = ref(false)
const selectedAnswer = ref(null)
const feedback = ref(null)
const questionAnimation = ref(false)
const questionPool = ref([])
const currentQuestion = ref(null)
const currentChoices = ref([])

const progressPercent = computed(() => ((currentQ.value) / totalQuestions) * 100)

onMounted(async () => {
  try {
    const res = await axios.get('/api/user/stars')
    totalStars.value = res.data.stars || 0
  } catch (e) {}
  await rec.loadProgress()
})

function goBack() {
  router.push('/games')
}

function shuffle(arr) {
  const a = [...arr]
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [a[i], a[j]] = [a[j], a[i]]
  }
  return a
}

function startGame(mode) {
  currentMode.value = mode
  score.value = 0
  earnedStars.value = 0
  currentQ.value = 0
  answered.value = false
  selectedAnswer.value = null
  feedback.value = null
  questionPool.value = shuffle(letters).slice(0, totalQuestions)
  rec.start(modeToLevel[mode] || 1)
  gameState.value = 'playing'
  loadQuestion()
}

function loadQuestion() {
  answered.value = false
  selectedAnswer.value = null
  feedback.value = null
  currentQuestion.value = questionPool.value[currentQ.value]
  currentChoices.value = generateChoices(currentQuestion.value)
  questionAnimation.value = false
  setTimeout(() => { questionAnimation.value = true }, 50)
}

function generateChoices(correct) {
  const correctAnswer = currentMode.value === 'case' ? correct.lower : correct.upper
  const others = letters.filter(l => l.upper !== correct.upper)
  const picked = shuffle(others).slice(0, 3).map(l =>
    currentMode.value === 'case' ? l.lower : l.upper
  )
  return shuffle([correctAnswer, ...picked])
}

function getCorrectAnswer() {
  return currentMode.value === 'case'
    ? currentQuestion.value.lower
    : currentQuestion.value.upper
}

function getChoiceClass(choice) {
  if (!answered.value) return ''
  const correct = getCorrectAnswer()
  if (choice === correct) return 'correct'
  if (choice === selectedAnswer.value && choice !== correct) return 'wrong'
  return 'dim'
}

function selectAnswer(choice) {
  if (answered.value) return
  answered.value = true
  selectedAnswer.value = choice
  const correct = getCorrectAnswer()

  if (choice === correct) {
    score.value++
    feedback.value = { type: 'correct', message: '🎉 잘했어요!' }
    playSound('correct')
  } else {
    feedback.value = { type: 'wrong', message: '❌ 정답은 "' + correct + '" 이에요!' }
    playSound('wrong')
  }

  setTimeout(() => {
    feedback.value = null
    if (currentQ.value < totalQuestions - 1) {
      currentQ.value++
      loadQuestion()
    } else {
      finishGame()
    }
  }, 1500)
}

async function finishGame() {
  earnedStars.value = score.value
  gameState.value = 'result'
  if (earnedStars.value > 0) {
    try {
      await axios.post('/api/games/earn-stars', {
        game: 'alphabet',
        stars: earnedStars.value,
        score: score.value
      })
      totalStars.value += earnedStars.value
    } catch (e) {}
  }
  await rec.end({ won: score.value >= 7, leveledUp: false, score: score.value })
}

function playSound(type) {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)()
    const osc = ctx.createOscillator()
    const gain = ctx.createGain()
    osc.connect(gain)
    gain.connect(ctx.destination)
    if (type === 'correct') {
      osc.frequency.setValueAtTime(523, ctx.currentTime)
      osc.frequency.setValueAtTime(659, ctx.currentTime + 0.1)
      osc.frequency.setValueAtTime(784, ctx.currentTime + 0.2)
    } else {
      osc.frequency.setValueAtTime(300, ctx.currentTime)
      osc.frequency.setValueAtTime(200, ctx.currentTime + 0.15)
    }
    gain.gain.setValueAtTime(0.3, ctx.currentTime)
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5)
    osc.start(ctx.currentTime)
    osc.stop(ctx.currentTime + 0.5)
  } catch (e) {}
}
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;800;900&display=swap');

.alphabet-game {
  font-family: 'Nunito', sans-serif;
  min-height: 100vh;
  background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 20%, #ffeaa7 40%, #a29bfe 60%, #fd79a8 80%, #74b9ff 100%);
  background-size: 400% 400%;
  animation: gradientShift 8s ease infinite;
  padding: 0;
  overflow-x: hidden;
}

@keyframes gradientShift {
  0% { background-position: 0% 50%; }
  50% { background-position: 100% 50%; }
  100% { background-position: 0% 50%; }
}

/* Header */
.game-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 20px;
  background: rgba(255,255,255,0.7);
  backdrop-filter: blur(10px);
  box-shadow: 0 2px 15px rgba(0,0,0,0.1);
  position: sticky;
  top: 0;
  z-index: 100;
}

.back-btn {
  background: white;
  border: 2px solid #ddd;
  border-radius: 20px;
  padding: 6px 14px;
  font-size: 14px;
  cursor: pointer;
  font-family: 'Nunito', sans-serif;
  font-weight: 700;
  transition: all 0.2s;
}
.back-btn:hover { background: #f0f0f0; transform: scale(1.05); }

.game-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 22px;
  font-weight: 900;
  color: #6c5ce7;
}
.title-icon { font-size: 28px; }

.star-display {
  background: linear-gradient(135deg, #f6d365, #fda085);
  border-radius: 20px;
  padding: 6px 14px;
  font-weight: 800;
  color: white;
  font-size: 15px;
  box-shadow: 0 3px 10px rgba(253,160,133,0.4);
}

/* Mode Screen */
.mode-screen {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 40px 20px;
  gap: 30px;
}

.mode-title {
  text-align: center;
}
.big-emoji { font-size: 80px; animation: bounce 2s ease infinite; }
.mode-title h1 {
  font-size: 42px;
  font-weight: 900;
  color: white;
  text-shadow: 3px 3px 0px rgba(0,0,0,0.15);
  margin: 0;
}
.mode-title p {
  font-size: 20px;
  color: rgba(255,255,255,0.9);
  margin: 8px 0 0;
}

@keyframes bounce {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-15px); }
}

.mode-cards {
  display: flex;
  gap: 20px;
  flex-wrap: wrap;
  justify-content: center;
}

.mode-card {
  background: white;
  border-radius: 24px;
  padding: 30px 25px;
  width: 180px;
  text-align: center;
  cursor: pointer;
  box-shadow: 0 8px 25px rgba(0,0,0,0.15);
  transition: all 0.3s;
  border: 3px solid transparent;
}
.mode-card:hover {
  transform: translateY(-8px) scale(1.05);
  border-color: #a29bfe;
  box-shadow: 0 15px 35px rgba(162,155,254,0.4);
}
.progress-info { display:inline-block; margin-top:18px; background:rgba(162,155,254,0.2); color:#5b4ad1; padding:10px 20px; border-radius:14px; font-size:14px; font-weight:700; }

.mode-icon {
  font-size: 52px;
  font-weight: 900;
  color: #6c5ce7;
  margin-bottom: 12px;
  line-height: 1;
}
.mode-name {
  font-size: 16px;
  font-weight: 800;
  color: #2d3436;
  margin-bottom: 6px;
}
.mode-desc {
  font-size: 13px;
  color: #636e72;
}

/* Playing Screen */
.playing-screen {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-width: 600px;
  margin: 0 auto;
}

/* Progress */
.progress-section {
  display: flex;
  align-items: center;
  gap: 12px;
}
.progress-text {
  font-size: 16px;
  font-weight: 800;
  color: white;
  min-width: 55px;
  text-shadow: 1px 1px 3px rgba(0,0,0,0.2);
}
.progress-bar {
  flex: 1;
  height: 16px;
  background: rgba(255,255,255,0.4);
  border-radius: 10px;
  overflow: hidden;
}
.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #55efc4, #00b894);
  border-radius: 10px;
  transition: width 0.5s ease;
  box-shadow: 0 0 10px rgba(0,184,148,0.5);
}
.score-badge {
  background: white;
  border-radius: 16px;
  padding: 4px 12px;
  font-weight: 800;
  color: #00b894;
  font-size: 15px;
  min-width: 50px;
  text-align: center;
}

/* Question Card */
.question-area {
  display: flex;
  justify-content: center;
}
.question-card {
  background: white;
  border-radius: 28px;
  padding: 32px 40px;
  text-align: center;
  box-shadow: 0 10px 40px rgba(0,0,0,0.15);
  width: 100%;
  max-width: 400px;
  opacity: 0;
  transform: scale(0.8);
  transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.question-card.bounce-in {
  opacity: 1;
  transform: scale(1);
}

.question-letter {
  font-size: 110px;
  font-weight: 900;
  line-height: 1.1;
  background: linear-gradient(135deg, #6c5ce7, #a29bfe);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  filter: drop-shadow(3px 3px 0px rgba(108,92,231,0.2));
}

.question-emoji {
  font-size: 90px;
  line-height: 1.1;
  animation: wobble 2s ease infinite;
}

@keyframes wobble {
  0%, 100% { transform: rotate(-5deg); }
  50% { transform: rotate(5deg); }
}

.question-word {
  font-size: 32px;
  font-weight: 900;
  color: #2d3436;
  margin: 8px 0 0;
}
.question-hint {
  font-size: 16px;
  color: #b2bec3;
  margin-top: 6px;
  font-weight: 600;
}

/* Feedback */
.feedback-message {
  text-align: center;
  font-size: 24px;
  font-weight: 900;
  padding: 12px 24px;
  border-radius: 20px;
  animation: popIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.feedback-message.correct {
  background: linear-gradient(135deg, #55efc4, #00b894);
  color: white;
  box-shadow: 0 5px 20px rgba(0,184,148,0.4);
}
.feedback-message.wrong {
  background: linear-gradient(135deg, #fd79a8, #e84393);
  color: white;
  box-shadow: 0 5px 20px rgba(232,67,147,0.4);
  animation: popIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), shake 0.5s ease 0.1s;
}

@keyframes popIn {
  from { opacity: 0; transform: scale(0.5); }
  to { opacity: 1; transform: scale(1); }
}

@keyframes shake {
  0%, 100% { transform: translateX(0); }
  20% { transform: translateX(-10px); }
  40% { transform: translateX(10px); }
  60% { transform: translateX(-8px); }
  80% { transform: translateX(8px); }
}

.feedback-anim-enter-active { animation: popIn 0.3s ease; }
.feedback-anim-leave-active { animation: popIn 0.3s ease reverse; }

/* Choice Buttons */
.choices-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
  padding: 0 0 20px;
}

.choice-btn {
  background: white;
  border: 4px solid transparent;
  border-radius: 22px;
  padding: 20px 10px;
  cursor: pointer;
  font-family: 'Nunito', sans-serif;
  transition: all 0.2s;
  box-shadow: 0 5px 15px rgba(0,0,0,0.1);
  position: relative;
  overflow: hidden;
}
.choice-btn:hover:not(:disabled) {
  transform: scale(1.05) translateY(-3px);
  box-shadow: 0 10px 25px rgba(108,92,231,0.3);
  border-color: #a29bfe;
}
.choice-btn:active:not(:disabled) {
  transform: scale(0.97);
}
.choice-btn:disabled { cursor: default; }

.choice-letter {
  font-size: 64px;
  font-weight: 900;
  color: #2d3436;
  display: block;
  line-height: 1.1;
}

.choice-btn.correct {
  background: linear-gradient(135deg, #55efc4, #00b894);
  border-color: #00b894;
  animation: correctBounce 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
  box-shadow: 0 0 0 6px rgba(0,184,148,0.3), 0 8px 25px rgba(0,184,148,0.4);
}
.choice-btn.correct .choice-letter { color: white; }

.choice-btn.wrong {
  background: linear-gradient(135deg, #fd79a8, #e84393);
  border-color: #e84393;
  animation: wrongShake 0.5s ease;
  box-shadow: 0 0 0 6px rgba(232,67,147,0.3);
}
.choice-btn.wrong .choice-letter { color: white; }

.choice-btn.dim {
  opacity: 0.35;
}

@keyframes correctBounce {
  0% { transform: scale(1); }
  30% { transform: scale(1.15); }
  60% { transform: scale(0.95); }
  80% { transform: scale(1.08); }
  100% { transform: scale(1); }
}
@keyframes wrongShake {
  0%, 100% { transform: translateX(0); }
  20% { transform: translateX(-8px); }
  40% { transform: translateX(8px); }
  60% { transform: translateX(-6px); }
  80% { transform: translateX(6px); }
}

/* Result Screen */
.result-screen {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: calc(100vh - 70px);
  padding: 20px;
}

.result-content {
  background: white;
  border-radius: 32px;
  padding: 40px 30px;
  text-align: center;
  box-shadow: 0 20px 60px rgba(0,0,0,0.2);
  max-width: 420px;
  width: 100%;
  animation: resultAppear 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
}
@keyframes resultAppear {
  from { opacity: 0; transform: scale(0.7) translateY(30px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}

.result-stars-display {
  display: flex;
  justify-content: center;
  flex-wrap: wrap;
  gap: 4px;
  margin-bottom: 16px;
}
.result-star {
  font-size: 28px;
  filter: grayscale(100%) brightness(1.5);
  transition: all 0.3s;
  display: inline-block;
}
.result-star.earned {
  filter: none;
  animation: starPop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}
@keyframes starPop {
  from { transform: scale(0) rotate(-30deg); opacity: 0; }
  to { transform: scale(1) rotate(0deg); opacity: 1; }
}

.result-score-text {
  font-size: 52px;
  font-weight: 900;
  color: #2d3436;
  margin-bottom: 12px;
}
.score-num {
  background: linear-gradient(135deg, #6c5ce7, #a29bfe);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.result-grade {
  font-size: 26px;
  font-weight: 800;
  color: #2d3436;
  margin-bottom: 20px;
}

.star-earn {
  background: linear-gradient(135deg, #f6d365, #fda085);
  border-radius: 20px;
  padding: 14px 24px;
  margin-bottom: 24px;
}
.earn-anim {
  font-size: 28px;
  font-weight: 900;
  color: white;
  text-shadow: 2px 2px 0px rgba(0,0,0,0.15);
  animation: earnPulse 1s ease infinite;
}
@keyframes earnPulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.07); }
}

.result-btns {
  display: flex;
  gap: 12px;
  justify-content: center;
}
.retry-btn, .menu-btn {
  border: none;
  border-radius: 18px;
  padding: 14px 28px;
  font-size: 18px;
  font-weight: 800;
  font-family: 'Nunito', sans-serif;
  cursor: pointer;
  transition: all 0.2s;
}
.retry-btn {
  background: linear-gradient(135deg, #6c5ce7, #a29bfe);
  color: white;
  box-shadow: 0 5px 20px rgba(108,92,231,0.4);
}
.menu-btn {
  background: #f0f0f0;
  color: #636e72;
}
.retry-btn:hover { transform: scale(1.05) translateY(-2px); }
.menu-btn:hover { transform: scale(1.05) translateY(-2px); background: #e0e0e0; }

/* Mobile */
@media (max-width: 480px) {
  .question-letter { font-size: 80px; }
  .question-emoji { font-size: 70px; }
  .choice-letter { font-size: 50px; }
  .mode-cards { flex-direction: column; align-items: center; }
  .mode-card { width: 260px; }
}
</style>
