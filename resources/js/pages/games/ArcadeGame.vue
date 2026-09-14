<template>
  <GameShell :title="title" theme="dark" bg="#000" fullscreen>
    <template #meta>
      <span v-if="lastScore > 0" class="score-badge">최고: {{ lastScore }}</span>
      <span v-if="pointsEarned > 0" class="points-badge">+{{ pointsEarned }} P</span>
    </template>

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
  </GameShell>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from 'axios'
import GameShell from '../../components/GameShell.vue'

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
.score-badge {
  background: rgba(255,255,255,0.1);
  backdrop-filter: blur(8px);
  border: 1px solid rgba(251,191,36,0.3);
  color: #fbbf24;
  font-size: 12.5px;
  font-weight: 700;
  padding: 4px 12px;
  border-radius: 999px;
  white-space: nowrap;
}
.points-badge {
  background-image: linear-gradient(135deg,#4ade80,#16a34a);
  color: #fff;
  font-size: 12.5px;
  font-weight: 800;
  padding: 4px 12px;
  border-radius: 999px;
  box-shadow: 0 4px 12px -3px rgba(34,197,94,0.55);
  white-space: nowrap;
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
  background: rgba(20,20,35,0.75);
  backdrop-filter: blur(16px) saturate(160%);
  border: 1px solid rgba(251,191,36,0.3);
  color: #fbbf24;
  font-size: 16px;
  font-weight: bold;
  padding: 11px 26px;
  border-radius: 999px;
  box-shadow: 0 12px 30px rgba(0,0,0,0.4);
  z-index: 1000;
  pointer-events: none;
  white-space: nowrap;
}
.fade-enter-active, .fade-leave-active { transition: opacity 0.4s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
