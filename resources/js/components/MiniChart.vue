<template>
  <!-- 최근 1개월 종가 흐름 (미국식 색: 올랐으면 초록, 내렸으면 빨강) -->
  <svg v-if="path" viewBox="0 0 64 24" class="w-14 sm:w-20 h-6 mx-auto block" preserveAspectRatio="none" role="img" aria-label="최근 한 달 가격 흐름">
    <path :d="area" :fill="up ? '#16A34A' : '#DC2626'" fill-opacity="0.10" stroke="none" />
    <path :d="path" fill="none" :stroke="up ? '#16A34A' : '#DC2626'" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
  </svg>
  <span v-else class="block text-center text-[10px] text-ink-faint">-</span>
</template>
<script setup>
import { computed } from 'vue'
const props = defineProps({ points: { type: Array, default: () => [] }, up: { type: Boolean, default: true } })
const coords = computed(() => {
  const p = (props.points || []).map(Number).filter(v => Number.isFinite(v))
  if (p.length < 2) return []
  const min = Math.min(...p), max = Math.max(...p), range = (max - min) || 1, stepX = 64 / (p.length - 1)
  return p.map((v, i) => [i * stepX, 21 - ((v - min) / range) * 18])
})
const path = computed(() => coords.value.length ? coords.value.map(([x, y], i) => `${i ? 'L' : 'M'} ${x.toFixed(2)} ${y.toFixed(2)}`).join(' ') : '')
const area = computed(() => path.value ? `${path.value} L 64 24 L 0 24 Z` : '')
</script>
