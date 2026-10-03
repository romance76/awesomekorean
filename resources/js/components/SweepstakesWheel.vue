<template>
<div class="flex flex-col items-center">
  <svg :viewBox="`0 0 ${size} ${size}`" :width="size" :height="size" class="transform -rotate-90">
    <circle v-if="total === 0" :cx="size/2" :cy="size/2" :r="radius"
      fill="none" stroke="#E5E7EB" stroke-width="18" stroke-dasharray="2 6" stroke-linecap="round" />
    <template v-else>
      <circle v-for="(seg, i) in segments" :key="i"
        :cx="size/2" :cy="size/2" :r="radius" fill="none"
        :stroke="seg.color" stroke-width="18"
        :stroke-dasharray="`${seg.length} ${circumference - seg.length}`"
        :stroke-dashoffset="-seg.offset"
        :class="seg.mine ? '' : 'transition-all duration-500'" />
    </template>
  </svg>
  <div class="-mt-[7.5rem] flex flex-col items-center justify-center" :style="{ height: (size - 36) + 'px' }">
    <template v-if="total === 0">
      <div class="text-xs text-ink-faint">아직 응모자가 없습니다</div>
    </template>
    <template v-else>
      <div class="text-2xl font-black text-ink">{{ total.toLocaleString() }}</div>
      <div class="text-[11px] text-ink-muted">전체 Entry</div>
      <div v-if="myEntries > 0" class="mt-1 text-xs font-bold text-amber-600">내 {{ myEntries }}개 · {{ myPct }}%</div>
    </template>
  </div>
  <div v-if="total > 0" class="flex items-center gap-3 mt-1 text-[11px] text-ink-muted">
    <span v-if="myEntries > 0" class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-amber-400 inline-block"></span>내 응모</span>
    <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-gray-300 inline-block"></span>다른 참가자 {{ otherCount }}명</span>
  </div>
</div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  myEntries: { type: Number, default: 0 },
  otherBreakdown: { type: Array, default: () => [] }, // 익명 entries_count 목록(나 제외)
  size: { type: Number, default: 160 },
})

const radius = computed(() => props.size / 2 - 9)
const circumference = computed(() => 2 * Math.PI * radius.value)
const otherCount = computed(() => props.otherBreakdown.length)
const total = computed(() => props.myEntries + props.otherBreakdown.reduce((a, b) => a + b, 0))
const myPct = computed(() => total.value > 0 ? Math.round((props.myEntries / total.value) * 1000) / 10 : 0)

// 회색 계열을 순환시켜 슬라이스 경계를 눈으로 구분할 수 있게 함(신원과는 무관)
const grayShades = ['#D1D5DB', '#E5E7EB', '#D1D5DB', '#E5E7EB']

const segments = computed(() => {
  const c = circumference.value
  const t = total.value
  if (t === 0) return []
  const list = []
  let offset = 0
  if (props.myEntries > 0) {
    const length = (props.myEntries / t) * c
    list.push({ length, offset, color: '#FBBF24', mine: true })
    offset += length
  }
  props.otherBreakdown.forEach((n, i) => {
    const length = (n / t) * c
    list.push({ length, offset, color: grayShades[i % grayShades.length], mine: false })
    offset += length
  })
  return list
})
</script>
