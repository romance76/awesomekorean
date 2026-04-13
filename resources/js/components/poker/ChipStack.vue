<template>
  <div v-if="amount && amount > 0" class="flex items-center gap-1.5">
    <!-- 칩 스택 (SVG 리얼 칩) -->
    <div class="relative" :style="{ width: '32px', height: stackHeight + 'px' }">
      <!-- 쌓인 칩 옆면 -->
      <svg v-for="i in chipCount" :key="'s'+i"
        class="absolute left-0" :style="{ bottom: (i-1)*4 + 'px' }"
        width="32" height="8" viewBox="0 0 32 8">
        <ellipse cx="16" cy="4" rx="15" ry="3.5" :fill="getChipColor(i).dark" stroke="#000" stroke-width="0.5" opacity="0.9"/>
        <ellipse cx="16" cy="3" rx="13" ry="2.5" :fill="getChipColor(i).edge" opacity="0.3"/>
      </svg>
      <!-- 맨 위 칩 윗면 -->
      <svg class="absolute left-0" :style="{ bottom: (chipCount-1)*4 + 'px' }"
        width="32" height="32" viewBox="0 0 32 32">
        <!-- 외곽 원 -->
        <circle cx="16" cy="16" r="15" :fill="topColor.main" stroke="#000" stroke-width="0.5"/>
        <!-- 무늬 (8방향 직선) -->
        <line v-for="a in 8" :key="'l'+a" x1="16" y1="1" x2="16" y2="6"
          :transform="`rotate(${a*45} 16 16)`" :stroke="topColor.stripe" stroke-width="2.5" opacity="0.6"/>
        <!-- 내부 원 -->
        <circle cx="16" cy="16" r="9" :fill="topColor.inner" stroke="white" stroke-width="0.5" opacity="0.9"/>
        <!-- 금액 텍스트 -->
        <text x="16" y="17.5" text-anchor="middle" dominant-baseline="middle"
          fill="white" font-size="7" font-weight="900" font-family="monospace" style="text-shadow:0 1px 2px rgba(0,0,0,0.5)">
          {{ chipLabel }}
        </text>
        <!-- 하이라이트 -->
        <ellipse cx="12" cy="10" rx="6" ry="3" fill="white" opacity="0.15" transform="rotate(-20 12 10)"/>
      </svg>
    </div>
    <!-- 금액 표시 -->
    <span class="text-white text-[11px] font-black drop-shadow-lg font-mono tracking-tight whitespace-nowrap">
      {{ label }}
    </span>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  amount: { type: Number, default: 0 },
  bb: { type: Number, default: 20 }
})

const label = computed(() => {
  if (props.amount >= 10000) return (props.amount / 1000).toFixed(0) + 'K'
  if (props.amount >= 1000) return (props.amount / 1000).toFixed(1) + 'K'
  return props.amount.toLocaleString()
})

const chipCount = computed(() => Math.min(Math.ceil(props.amount / Math.max(props.bb, 10)), 5))
const stackHeight = computed(() => chipCount.value * 4 + 32)

// 칩 색상 테이블 (포커 표준)
const CHIP_COLORS = [
  { main: '#e8e8e8', dark: '#c0c0c0', inner: '#d0d0d0', edge: '#fff', stripe: '#4a90d9', label: '1' },   // 흰색
  { main: '#e53e3e', dark: '#c53030', inner: '#c53030', edge: '#feb2b2', stripe: '#fff', label: '5' },    // 빨강
  { main: '#3182ce', dark: '#2b6cb0', inner: '#2b6cb0', edge: '#90cdf4', stripe: '#fff', label: '10' },   // 파랑
  { main: '#38a169', dark: '#2f855a', inner: '#276749', edge: '#9ae6b4', stripe: '#fff', label: '25' },   // 초록
  { main: '#1a202c', dark: '#171923', inner: '#2d3748', edge: '#718096', stripe: '#e53e3e', label: '100' }, // 검정
  { main: '#805ad5', dark: '#6b46c1', inner: '#553c9a', edge: '#d6bcfa', stripe: '#fff', label: '500' },  // 보라
  { main: '#ecc94b', dark: '#d69e2e', inner: '#b7791f', edge: '#fefcbf', stripe: '#1a202c', label: '1K' }, // 노랑
  { main: '#ed64a6', dark: '#d53f8c', inner: '#b83280', edge: '#fed7e2', stripe: '#fff', label: '5K' },   // 핑크
  { main: '#ed8936', dark: '#dd6b20', inner: '#c05621', edge: '#feebc8', stripe: '#1a202c', label: '10K' }, // 주황
  { main: '#d4af37', dark: '#b8860b', inner: '#8b6914', edge: '#fffacd', stripe: '#fff', label: '50K' },  // 금색
]

function getChipTier(amount) {
  if (amount >= 50000) return 9
  if (amount >= 10000) return 8
  if (amount >= 5000) return 7
  if (amount >= 1000) return 6
  if (amount >= 500) return 5
  if (amount >= 100) return 4
  if (amount >= 25) return 3
  if (amount >= 10) return 2
  if (amount >= 5) return 1
  return 0
}

function getChipColor(stackIdx) {
  const base = getChipTier(props.amount)
  const tier = Math.max(0, base - (chipCount.value - stackIdx))
  const c = CHIP_COLORS[Math.min(tier, CHIP_COLORS.length - 1)]
  return c
}

const topColor = computed(() => CHIP_COLORS[Math.min(getChipTier(props.amount), CHIP_COLORS.length - 1)])
const chipLabel = computed(() => CHIP_COLORS[Math.min(getChipTier(props.amount), CHIP_COLORS.length - 1)].label)
</script>
