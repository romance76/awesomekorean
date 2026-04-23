<template>
<!--
  모든 게임의 공통 레이아웃 쉘.
  - 왼쪽 상단 뒤로가기 (통일)
  - 가운데 타이틀
  - 오른쪽 포인트/레벨/점수 배지
  - 아래 기본 슬롯 (게임 콘텐츠)
  - 전체창(fullscreen)/카드형(card) 테마 선택 가능
  props:
    title, icon, level, score, bg(그라디언트), theme('light'|'dark'), back(경로), fullscreen(bool)
-->
<div :class="['game-shell', `theme-${theme}`, fullscreen ? 'is-fullscreen' : 'is-card']" :style="bg ? { background: bg } : {}">
  <div class="shell-header">
    <button class="shell-back" @click="onBack" aria-label="뒤로">
      <span class="arrow">←</span>
      <span class="label">뒤로</span>
    </button>
    <div class="shell-title">
      <span v-if="icon" class="shell-icon">{{ icon }}</span>
      <span>{{ title }}</span>
    </div>
    <div class="shell-meta">
      <slot name="meta">
        <span v-if="level !== null && level !== undefined" class="shell-badge badge-lv">Lv.{{ level }}</span>
        <span v-if="score !== null && score !== undefined" class="shell-badge badge-score">⭐ {{ score }}</span>
        <span v-if="points !== null && points !== undefined" class="shell-badge badge-pts">🪙 {{ points }}</span>
      </slot>
    </div>
  </div>

  <div class="shell-body">
    <slot />
  </div>
</div>
</template>

<script setup>
import { useRouter } from 'vue-router'
const props = defineProps({
  title: { type: String, default: '게임' },
  icon: { type: String, default: '' },
  level: { default: null },
  score: { default: null },
  points: { default: null },
  bg: { type: String, default: '' },
  theme: { type: String, default: 'light' }, // 'light' | 'dark'
  back: { type: String, default: '/games' },
  fullscreen: { type: Boolean, default: false },
})
const emit = defineEmits(['back'])
const router = useRouter()
function onBack() {
  if (emit) emit('back')
  router.push(props.back)
}
</script>

<style scoped>
.game-shell { min-height: 100vh; font-family: 'Noto Sans KR', sans-serif; display: flex; flex-direction: column; }
.game-shell.theme-light { background: #f8fafc; color: #111827; }
.game-shell.theme-dark { background: #0b1020; color: #f3f4f6; }

.shell-header {
  position: sticky; top: 0; z-index: 20;
  display: flex; align-items: center; justify-content: space-between;
  padding: 10px 14px; backdrop-filter: blur(10px);
  background: rgba(255, 255, 255, 0.75);
  border-bottom: 1px solid rgba(0,0,0,0.06);
}
.theme-dark .shell-header { background: rgba(11, 16, 32, 0.85); border-color: rgba(255,255,255,0.08); }

.shell-back {
  display: inline-flex; align-items: center; gap: 4px;
  background: rgba(0,0,0,0.05); border: none; color: inherit;
  padding: 6px 12px; border-radius: 18px; cursor: pointer;
  font-size: 13px; font-weight: 700;
  transition: background 0.15s;
}
.shell-back:hover { background: rgba(0,0,0,0.1); }
.theme-dark .shell-back { background: rgba(255,255,255,0.08); }
.theme-dark .shell-back:hover { background: rgba(255,255,255,0.16); }
.shell-back .arrow { font-size: 16px; }

.shell-title {
  font-size: 15px; font-weight: 800;
  display: flex; align-items: center; gap: 6px;
  position: absolute; left: 50%; transform: translateX(-50%);
}
.shell-icon { font-size: 18px; }

.shell-meta { display: flex; align-items: center; gap: 6px; }
.shell-badge {
  font-size: 11px; font-weight: 800; padding: 4px 10px;
  border-radius: 14px; white-space: nowrap;
}
.badge-lv { background: #ddd6fe; color: #5b21b6; }
.badge-score { background: #fef3c7; color: #92400e; }
.badge-pts { background: #fef3c7; color: #92400e; }
.theme-dark .badge-lv { background: rgba(139,92,246,0.2); color: #c4b5fd; }
.theme-dark .badge-score { background: rgba(251,191,36,0.2); color: #fcd34d; }
.theme-dark .badge-pts { background: rgba(251,191,36,0.2); color: #fcd34d; }

.shell-body { flex: 1; display: flex; flex-direction: column; }

/* 카드형: 중앙 컨텐츠 최대폭 제한 */
.is-card .shell-body { max-width: 900px; margin: 0 auto; width: 100%; padding: 16px; }
/* 전체창: 여백 없음 */
.is-fullscreen .shell-body { padding: 0; }

@media (max-width: 640px) {
  .shell-title { font-size: 13px; }
  .shell-title .shell-icon { font-size: 16px; }
  .shell-back .label { display: none; }
  .shell-back { padding: 6px 10px; }
  .shell-badge { font-size: 10px; padding: 3px 8px; }
}
</style>
