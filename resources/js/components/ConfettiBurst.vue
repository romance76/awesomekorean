<template>
<div class="confetti-layer" aria-hidden="true">
  <span v-for="p in pieces" :key="p.id" class="confetti-piece" :style="pieceStyle(p)"></span>
</div>
</template>

<script setup>
// 정답/승리 순간에 터뜨리는 공용 컨페티 — 부모에서 ref로 잡고 burst() 호출
import { ref } from 'vue'

const COLORS = ['#FF6B6B', '#FFE66D', '#4ECDC4', '#A855F7', '#F97316', '#3B82F6', '#EC4899', '#10B981']

const pieces = ref([])
let idCounter = 0

function burst(count = 28) {
  const batch = Array.from({ length: count }, () => ({
    id: idCounter++,
    left: Math.random() * 100,
    delay: Math.random() * 0.25,
    duration: 1.5 + Math.random() * 0.9,
    color: COLORS[Math.floor(Math.random() * COLORS.length)],
    size: 6 + Math.random() * 8,
    round: Math.random() > 0.5,
  }))
  pieces.value.push(...batch)
  setTimeout(() => {
    const ids = new Set(batch.map(p => p.id))
    pieces.value = pieces.value.filter(p => !ids.has(p.id))
  }, 2600)
}

function pieceStyle(p) {
  return {
    left: p.left + '%',
    width: p.size + 'px',
    height: p.size + 'px',
    background: p.color,
    borderRadius: p.round ? '50%' : '2px',
    animationDelay: p.delay + 's',
    animationDuration: p.duration + 's',
  }
}

defineExpose({ burst })
</script>

<style scoped>
.confetti-layer {
  position: fixed;
  inset: 0;
  pointer-events: none;
  z-index: 998;
  overflow: hidden;
}
.confetti-piece {
  position: absolute;
  top: -20px;
  animation-name: confettiFall;
  animation-timing-function: ease-in;
  animation-fill-mode: forwards;
}
@keyframes confettiFall {
  0% { transform: translateY(-20px) rotate(0deg); opacity: 1; }
  100% { transform: translateY(100vh) rotate(720deg); opacity: 0; }
}
</style>
