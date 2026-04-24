<template>
  <div class="counting-game" :class="{ shake: shaking, flash: flashing }">
    <!-- Header -->
    <div class="game-header">
      <button class="back-btn" @click="goBack">← 뒤로</button>
      <div class="level-badge">레벨 {{ level }} 🔢</div>
      <div class="score-display">⭐ {{ totalStars }}</div>
    </div>

    <!-- Progress bar -->
    <div class="progress-bar" v-if="gameState === 'playing'">
      <div class="progress-fill" :style="{ width: (questionIndex / 10 * 100) + '%' }"></div>
      <span class="progress-text">{{ questionIndex }}/10</span>
    </div>

    <!-- Start Screen -->
    <div v-if="gameState === 'start'" class="start-screen">
      <div class="game-title">숫자 세기 🔢</div>
      <div class="game-subtitle">몇 개일까요?</div>
      <div class="level-info">
        <div class="level-range">레벨 {{ level }}: 1~{{ maxNumber }}까지</div>
      </div>
      <button class="start-btn" @click="startGame">게임 시작! 🎮</button>
    </div>

    <!-- Playing Screen -->
    <div v-if="gameState === 'playing'" class="playing-screen">
      <div class="object-label">{{ currentObject.name }} 몇 개일까요?</div>

      <div class="emoji-display" :class="emojiSizeClass">
        <span
          v-for="i in currentCount"
          :key="i"
          class="emoji-item"
          :style="{ animationDelay: (i * 0.05) + 's' }"
        >{{ currentObject.emoji }}</span>
      </div>

      <div class="choices">
        <button
          v-for="choice in choices"
          :key="choice"
          class="choice-btn"
          :class="{
            correct: answered && choice === currentCount,
            wrong: answered && choice === selectedAnswer && choice !== currentCount,
            disabled: answered
          }"
          @click="selectAnswer(choice)"
          :disabled="answered"
        >
          {{ choice }}
        </button>
      </div>

      <div v-if="answered" class="feedback-msg" :class="lastCorrect ? 'correct-msg' : 'wrong-msg'">
        {{ lastCorrect ? koreanNumbers[currentCount] + '개! 정답이에요! 🎉' : '다시 세어봐요 🔄' }}
      </div>
    </div>

    <!-- Result Screen -->
    <div v-if="gameState === 'result'" class="result-screen">
      <div class="result-title">결과 🎊</div>
      <div class="result-stats">
        <div class="stat-item">
          <span class="stat-num">{{ correctCount }}</span>
          <span class="stat-label">정답</span>
        </div>
        <div class="stat-divider">/</div>
        <div class="stat-item">
          <span class="stat-num">10</span>
          <span class="stat-label">문제</span>
        </div>
      </div>
      <div class="stars-earned">
        <span class="stars-count">+{{ starsEarned }} ⭐</span>
        <span class="stars-label">별을 획득했어요!</span>
      </div>
      <div v-if="leveledUp" class="levelup-banner">
        🎉 레벨업! 레벨 {{ level }} 🎉
      </div>
      <GameResultExtras :rec="rec" slug="counting" />
      <div class="result-actions">
        <button class="action-btn retry-btn" @click="startGame">다시 하기 🔄</button>
        <button class="action-btn home-btn" @click="goBack">홈으로 🏠</button>
      </div>
    </div>

    <!-- Star sparkles overlay -->
    <div v-if="showSparkles" class="sparkles-overlay">
      <span v-for="i in 8" :key="i" class="sparkle" :style="sparkleStyle(i)">⭐</span>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import GameResultExtras from '../../components/GameResultExtras.vue'
import { useGameRecord } from '../../composables/useGameRecord'
const rec = useGameRecord('counting')

const router = useRouter()

const koreanNumbers = ['','한','두','세','네','다섯','여섯','일곱','여덟','아홉','열','열한','열두','열세','열네','열다섯']

const objects = [
  { emoji: '🍎', name: '사과' },
  { emoji: '⭐', name: '별' },
  { emoji: '🌸', name: '꽃' },
  { emoji: '🐣', name: '병아리' },
  { emoji: '🍭', name: '사탕' },
  { emoji: '🦋', name: '나비' },
  { emoji: '🍩', name: '도넛' },
  { emoji: '🌈', name: '무지개' },
  { emoji: '🐥', name: '오리' },
  { emoji: '🍓', name: '딸기' },
]

const speak = (text) => {
  if (!window.speechSynthesis) return
  window.speechSynthesis.cancel()
  const u = new SpeechSynthesisUtterance(text)
  u.lang = 'ko-KR'; u.rate = 0.85; u.pitch = 1.3
  window.speechSynthesis.speak(u)
}

// State
const level = ref(parseInt(localStorage.getItem('counting_level') || '1'))
const totalStars = ref(parseInt(localStorage.getItem('counting_stars') || '0'))
const gameState = ref('start')
const questionIndex = ref(0)
const correctCount = ref(0)
const starsEarned = ref(0)
const leveledUp = ref(false)

const currentObject = ref(objects[0])
const currentCount = ref(1)
const choices = ref([])
const answered = ref(false)
const selectedAnswer = ref(null)
const lastCorrect = ref(false)
const shaking = ref(false)
const flashing = ref(false)
const showSparkles = ref(false)

const maxNumber = computed(() => {
  if (level.value <= 2) return 5
  if (level.value <= 4) return 10
  return 15
})

const emojiSizeClass = computed(() => {
  if (currentCount.value <= 5) return 'size-large'
  if (currentCount.value <= 10) return 'size-medium'
  return 'size-small'
})

function goBack() {
  window.speechSynthesis && window.speechSynthesis.cancel()
  router.push('/games')
}

function startGame() {
  questionIndex.value = 0
  correctCount.value = 0
  starsEarned.value = 0
  leveledUp.value = false
  rec.start(level.value)
  gameState.value = 'playing'
  nextQuestion()
}

function nextQuestion() {
  answered.value = false
  selectedAnswer.value = null
  shaking.value = false
  flashing.value = false
  showSparkles.value = false

  currentObject.value = objects[Math.floor(Math.random() * objects.length)]
  currentCount.value = Math.floor(Math.random() * maxNumber.value) + 1

  // Generate 4 unique choices including the correct answer
  const choiceSet = new Set([currentCount.value])
  while (choiceSet.size < 4) {
    const r = Math.floor(Math.random() * maxNumber.value) + 1
    choiceSet.add(r)
  }
  choices.value = Array.from(choiceSet).sort((a, b) => a - b)

  // Speak the question
  setTimeout(() => {
    speak(currentObject.value.name + ' 몇 개일까요?')
  }, 300)
}

function selectAnswer(choice) {
  if (answered.value) return
  answered.value = true
  selectedAnswer.value = choice

  if (choice === currentCount.value) {
    lastCorrect.value = true
    correctCount.value++
    flashing.value = true
    showSparkles.value = true
    speak(koreanNumbers[currentCount.value] + '개! 정답이에요!')
    setTimeout(() => { flashing.value = false; showSparkles.value = false }, 1500)
  } else {
    lastCorrect.value = false
    shaking.value = true
    speak('다시 세어봐요')
    setTimeout(() => { shaking.value = false }, 600)
  }

  questionIndex.value++

  if (questionIndex.value >= 10) {
    setTimeout(() => showResults(), 1600)
  } else {
    setTimeout(() => nextQuestion(), 1600)
  }
}

function showResults() {
  starsEarned.value = correctCount.value
  totalStars.value += starsEarned.value
  localStorage.setItem('counting_stars', totalStars.value)

  // Level up logic: 8+ correct = level up
  const won = correctCount.value >= 8
  if (won) {
    level.value++
    leveledUp.value = true
    localStorage.setItem('counting_level', level.value)
    speak('레벨업! 축하해요!')
  } else {
    speak(koreanNumbers[Math.min(starsEarned.value, 15)] + ' 개 맞았어요. 잘했어요!')
  }

  gameState.value = 'result'
  rec.end({ won, leveledUp: leveledUp.value, score: correctCount.value * 10 })
}

function sparkleStyle(i) {
  const angle = (i - 1) * 45
  const rad = angle * Math.PI / 180
  const dist = 80 + Math.random() * 60
  return {
    left: '50%',
    top: '50%',
    transform: `translate(calc(-50% + ${Math.cos(rad) * dist}px), calc(-50% + ${Math.sin(rad) * dist}px))`,
    animationDelay: (i * 0.08) + 's'
  }
}

onMounted(() => {
  speak('숫자 세기 게임!')
})

onUnmounted(() => {
  window.speechSynthesis && window.speechSynthesis.cancel()
})
</script>

<style scoped>
.counting-game {
  min-height: 100vh;
  background: linear-gradient(135deg, #FFE066 0%, #FF9F43 50%, #FF6B6B 100%);
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 0;
  font-family: 'Nanum Gothic', sans-serif;
  position: relative;
  overflow: hidden;
  transition: background 0.2s;
}

.counting-game.flash {
  background: linear-gradient(135deg, #A8FF78 0%, #78FFD6 100%);
}

.counting-game.shake {
  animation: shake 0.5s ease-in-out;
}

@keyframes shake {
  0%, 100% { transform: translateX(0); }
  15% { transform: translateX(-10px); }
  30% { transform: translateX(10px); }
  45% { transform: translateX(-8px); }
  60% { transform: translateX(8px); }
  75% { transform: translateX(-5px); }
  90% { transform: translateX(5px); }
}

/* Header */
.game-header {
  width: 100%;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: rgba(255,255,255,0.25);
  backdrop-filter: blur(4px);
}

.back-btn {
  background: rgba(255,255,255,0.6);
  border: none;
  border-radius: 20px;
  padding: 8px 14px;
  font-size: 14px;
  font-weight: bold;
  cursor: pointer;
  color: #333;
}

.level-badge {
  background: rgba(255,255,255,0.8);
  border-radius: 20px;
  padding: 6px 16px;
  font-size: 16px;
  font-weight: bold;
  color: #FF6B6B;
}

.score-display {
  background: rgba(255,255,255,0.6);
  border-radius: 20px;
  padding: 6px 14px;
  font-size: 16px;
  font-weight: bold;
  color: #FF9F43;
}

/* Progress */
.progress-bar {
  width: 90%;
  height: 14px;
  background: rgba(255,255,255,0.4);
  border-radius: 7px;
  margin: 10px auto;
  position: relative;
  overflow: visible;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #fff 0%, #FFE066 100%);
  border-radius: 7px;
  transition: width 0.4s ease;
}

.progress-text {
  position: absolute;
  right: -35px;
  top: -3px;
  font-size: 13px;
  font-weight: bold;
  color: white;
  text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
}

/* Start Screen */
.start-screen {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 24px;
  padding: 40px 20px;
}

.game-title {
  font-size: 52px;
  font-weight: 900;
  color: white;
  text-shadow: 3px 3px 0 rgba(0,0,0,0.15);
  letter-spacing: -1px;
}

.game-subtitle {
  font-size: 28px;
  color: rgba(255,255,255,0.9);
  font-weight: bold;
}

.level-info {
  background: rgba(255,255,255,0.3);
  border-radius: 16px;
  padding: 14px 28px;
  text-align: center;
}

.level-range {
  font-size: 20px;
  font-weight: bold;
  color: white;
}

.start-btn {
  background: white;
  color: #FF6B6B;
  border: none;
  border-radius: 30px;
  padding: 18px 50px;
  font-size: 26px;
  font-weight: 900;
  cursor: pointer;
  box-shadow: 0 6px 20px rgba(0,0,0,0.2);
  transition: transform 0.15s, box-shadow 0.15s;
}

.start-btn:hover {
  transform: scale(1.06);
  box-shadow: 0 8px 24px rgba(0,0,0,0.25);
}

/* Playing Screen */
.playing-screen {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 100%;
  padding: 10px 16px 20px;
  gap: 16px;
}

.object-label {
  font-size: 24px;
  font-weight: bold;
  color: white;
  text-shadow: 1px 1px 3px rgba(0,0,0,0.2);
  background: rgba(255,255,255,0.25);
  border-radius: 20px;
  padding: 10px 24px;
}

/* Emoji Display */
.emoji-display {
  background: rgba(255,255,255,0.35);
  border-radius: 24px;
  padding: 20px;
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  align-items: center;
  gap: 6px;
  min-height: 180px;
  width: 90%;
  max-width: 480px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.emoji-item {
  display: inline-block;
  animation: popIn 0.3s ease forwards;
  opacity: 0;
}

@keyframes popIn {
  0% { transform: scale(0); opacity: 0; }
  70% { transform: scale(1.2); opacity: 1; }
  100% { transform: scale(1); opacity: 1; }
}

.size-large .emoji-item { font-size: 52px; }
.size-medium .emoji-item { font-size: 38px; }
.size-small .emoji-item { font-size: 28px; }

/* Choices */
.choices {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  width: 90%;
  max-width: 360px;
}

.choice-btn {
  background: white;
  border: 3px solid transparent;
  border-radius: 20px;
  padding: 18px 10px;
  font-size: 36px;
  font-weight: 900;
  color: #333;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  transition: transform 0.1s, box-shadow 0.1s;
}

.choice-btn:hover:not(.disabled) {
  transform: scale(1.05);
  box-shadow: 0 6px 16px rgba(0,0,0,0.2);
}

.choice-btn:active:not(.disabled) {
  transform: scale(0.97);
}

.choice-btn.correct {
  background: #A8FF78;
  border-color: #4CAF50;
  color: #1a7a1a;
  animation: correctBounce 0.4s ease;
}

@keyframes correctBounce {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.15); }
}

.choice-btn.wrong {
  background: #FFB3B3;
  border-color: #F44336;
  color: #c0392b;
}

.choice-btn.disabled {
  cursor: not-allowed;
}

/* Feedback */
.feedback-msg {
  font-size: 22px;
  font-weight: bold;
  border-radius: 16px;
  padding: 10px 24px;
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}

.correct-msg {
  background: rgba(168, 255, 120, 0.5);
  color: #1a7a1a;
}

.wrong-msg {
  background: rgba(255, 179, 179, 0.5);
  color: #c0392b;
}

/* Results */
.result-screen {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 20px;
  padding: 30px 20px;
}

.result-title {
  font-size: 48px;
  font-weight: 900;
  color: white;
  text-shadow: 2px 2px 0 rgba(0,0,0,0.15);
}

.result-stats {
  display: flex;
  align-items: center;
  gap: 16px;
  background: rgba(255,255,255,0.35);
  border-radius: 24px;
  padding: 20px 40px;
}

.stat-item {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.stat-num {
  font-size: 56px;
  font-weight: 900;
  color: white;
  line-height: 1;
}

.stat-label {
  font-size: 16px;
  color: rgba(255,255,255,0.9);
  font-weight: bold;
}

.stat-divider {
  font-size: 40px;
  color: rgba(255,255,255,0.7);
  font-weight: bold;
}

.stars-earned {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}

.stars-count {
  font-size: 42px;
  font-weight: 900;
  color: white;
  text-shadow: 1px 1px 3px rgba(0,0,0,0.2);
}

.stars-label {
  font-size: 18px;
  color: rgba(255,255,255,0.9);
  font-weight: bold;
}

.levelup-banner {
  background: linear-gradient(135deg, #FFD700, #FFA500);
  border-radius: 20px;
  padding: 14px 30px;
  font-size: 24px;
  font-weight: 900;
  color: white;
  text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
  box-shadow: 0 4px 15px rgba(255, 165, 0, 0.5);
  animation: levelupPulse 0.6s ease infinite alternate;
}

@keyframes levelupPulse {
  from { transform: scale(1); }
  to { transform: scale(1.05); }
}

.result-actions {
  display: flex;
  gap: 14px;
}

.action-btn {
  border: none;
  border-radius: 24px;
  padding: 16px 28px;
  font-size: 20px;
  font-weight: bold;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(0,0,0,0.2);
  transition: transform 0.15s;
}

.action-btn:hover {
  transform: scale(1.05);
}

.retry-btn {
  background: white;
  color: #FF6B6B;
}

.home-btn {
  background: rgba(255,255,255,0.5);
  color: white;
}

/* Sparkles */
.sparkles-overlay {
  position: fixed;
  top: 50%;
  left: 50%;
  pointer-events: none;
  z-index: 100;
}

.sparkle {
  position: absolute;
  font-size: 28px;
  animation: sparkleOut 1.2s ease forwards;
}

@keyframes sparkleOut {
  0% { opacity: 1; transform: translate(-50%, -50%) scale(0.5); }
  50% { opacity: 1; }
  100% { opacity: 0; }
}
</style>