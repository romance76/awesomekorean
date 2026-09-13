<template>
  <div class="arcade-wrapper">
    <!-- Header -->
    <div class="arcade-header">
      <button class="back-btn" @click="goBack">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="white">
          <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
        </svg>
      </button>
      <span class="game-title">{{ title }}</span>
      <div class="header-right">
        <span v-if="lastScore > 0" class="score-badge">최고: {{ lastScore }}</span>
        <span v-if="pointsEarned > 0" class="points-badge">+{{ pointsEarned }} P</span>
      </div>
    </div>

    <!-- Game iframe -->
    <div class="game-frame-container">
      <iframe
        ref="frameRef"
        :src="gameUrl"
        class="game-frame"
        frameborder="0"
        allow="autoplay"
        allowfullscreen
      ></iframe>
    </div>

    <!-- Score saved toast -->
    <transition name="fade">
      <div v-if="toastMsg" class="score-toast">{{ toastMsg }}</div>
    </transition>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from 'axios'

const props = defineProps({
  gameSlug: { type: String, required: true },
  gameId: { type: String, default: null },
  title: { type: String, default: '게임' },
  pointsPerScore: { type: Number, default: 1 },
})

const router = useRouter()
const route = useRoute()
const frameRef = ref(null)
const lastScore = ref(0)
const pointsEarned = ref(0)
const toastMsg = ref('')
let toastTimer = null

// 캐시 무효화: 빌드/배포 시점 기준으로 매번 새 버전 강제 로드
const gameUrl = computed(() => `/games/${props.gameSlug}/index.html?v=${Date.now()}`)

function showToast(msg, duration = 3000) {
  toastMsg.value = msg
  clearTimeout(toastTimer)
  toastTimer = setTimeout(() => { toastMsg.value = '' }, duration)
}

async function saveScore(score) {
  lastScore.value = Math.max(lastScore.value, score)
  try {
    // 예전엔 존재하지 않는 /api/games/{id}/score 로 보내 매번 조용히 실패했고(포인트가
    // 실제로 지급된 적 없음), 클라이언트가 직접 계산한 포인트를 서버가 그대로 믿는
    // 구조였음 — 다른 모든 게임이 이미 쓰고 있는 공용 채점 API(관리자 게임별 포인트
    // 설정을 서버에서 직접 계산)로 통일.
    const { data } = await axios.post('/api/games/scores', { game_type: props.gameSlug, score })
    const points = data.data?.points_earned ?? 0
    pointsEarned.value += points
    showToast(points > 0 ? `점수 저장! +${points}P` : `점수 저장! ${score}점`)
  } catch (e) {
    console.error('score save error', e)
    showToast(`점수: ${score}`)
  }
}

function goBack() {
  router.push({ name: 'games' })
}

function handleMessage(event) {
  if (!event.data || typeof event.data !== 'object') return
  const { type, score } = event.data
  if (type === 'GAME_SCORE' && typeof score === 'number') {
    saveScore(score)
  } else if (type === 'GAME_EXIT') {
    goBack()
  }
}

onMounted(() => {
  window.addEventListener('message', handleMessage)
})

onUnmounted(() => {
  window.removeEventListener('message', handleMessage)
  clearTimeout(toastTimer)
})
</script>

<style scoped>
.arcade-wrapper {
  position: fixed;
  inset: 0;
  background: #000;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.arcade-header {
  display: flex;
  align-items: center;
  padding: 8px 14px;
  background: rgba(0,0,0,0.85);
  border-bottom: 1px solid rgba(255,255,255,0.1);
  min-height: 48px;
  z-index: 10;
  flex-shrink: 0;
}
.back-btn {
  background: none;
  border: none;
  cursor: pointer;
  padding: 4px;
  display: flex;
  align-items: center;
  flex-shrink: 0;
}
.game-title {
  color: white;
  font-size: 17px;
  font-weight: 700;
  margin-left: 10px;
  flex: 1;
}
.header-right {
  display: flex;
  gap: 8px;
  align-items: center;
}
.score-badge {
  background: rgba(255,255,255,0.1);
  color: #fbbf24;
  font-size: 13px;
  padding: 3px 10px;
  border-radius: 12px;
}
.points-badge {
  background: #22c55e;
  color: #000;
  font-size: 13px;
  font-weight: bold;
  padding: 3px 10px;
  border-radius: 12px;
}
.game-frame-container {
  flex: 1;
  position: relative;
  overflow: hidden;
}
.game-frame {
  width: 100%;
  height: 100%;
  border: none;
  display: block;
}
.score-toast {
  position: fixed;
  bottom: 30px;
  left: 50%;
  transform: translateX(-50%);
  background: rgba(0,0,0,0.85);
  color: #fbbf24;
  font-size: 16px;
  font-weight: bold;
  padding: 10px 24px;
  border-radius: 20px;
  z-index: 1000;
  pointer-events: none;
  white-space: nowrap;
}
.fade-enter-active, .fade-leave-active { transition: opacity 0.4s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
