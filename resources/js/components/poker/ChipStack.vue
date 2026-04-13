<template>
  <div v-if="amount && amount > 0" class="flex items-center gap-1">
    <!-- 칩 스택 (SVG 기반 리얼 칩) -->
    <div class="relative" :style="{ width: '28px', height: stackHeight + 'px' }">
      <div v-for="i in chipCount" :key="i"
        class="absolute left-0 w-7 h-2 rounded-[50%] shadow-md"
        :style="{
          bottom: (i - 1) * 3 + 'px',
          background: chipColors[Math.min(i-1, chipColors.length-1)].bg,
          border: '1.5px solid ' + chipColors[Math.min(i-1, chipColors.length-1)].border,
          boxShadow: '0 1px 3px rgba(0,0,0,0.4), inset 0 1px 1px rgba(255,255,255,0.3)',
        }">
        <!-- 칩 무늬 (가운데 점) -->
        <div class="absolute inset-0 flex items-center justify-center">
          <div class="w-1.5 h-[3px] rounded-full" :style="{ background: chipColors[Math.min(i-1, chipColors.length-1)].dot }"></div>
        </div>
      </div>
      <!-- 맨 위 칩 윗면 (입체감) -->
      <div class="absolute left-0 w-7 h-7 rounded-full"
        :style="{
          bottom: (chipCount - 1) * 3 + 'px',
          background: topChipBg,
          border: '2px solid ' + topChipBorder,
          boxShadow: '0 2px 6px rgba(0,0,0,0.5), inset 0 2px 4px rgba(255,255,255,0.2)',
        }">
        <div class="w-full h-full rounded-full flex items-center justify-center">
          <div class="w-4 h-4 rounded-full border border-white/30 flex items-center justify-center"
            :style="{ background: topChipInner }">
            <span class="text-white text-[6px] font-black drop-shadow">{{ chipSymbol }}</span>
          </div>
        </div>
      </div>
    </div>
    <!-- 금액 표시 -->
    <span class="text-white text-[11px] font-black drop-shadow-lg font-mono tracking-tight">
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
  if (props.amount >= 10000) return (props.amount / 1000).toFixed(props.amount % 1000 ? 1 : 0) + 'K'
  if (props.amount >= 1000) return (props.amount / 1000).toFixed(props.amount % 1000 ? 1 : 0) + 'K'
  return props.amount.toLocaleString()
})

const chipCount = computed(() => Math.min(Math.ceil(props.amount / Math.max(props.bb, 10)), 5))
const stackHeight = computed(() => chipCount.value * 3 + 28)

// 금액대별 칩 색상
const chipColorSet = computed(() => {
  if (props.amount >= props.bb * 50) return { bg: '#1a1a2e', border: '#e94560', dot: '#e94560', inner: '#0f3460', symbol: '♦' }
  if (props.amount >= props.bb * 20) return { bg: '#c0392b', border: '#e74c3c', dot: '#fff', inner: '#922b21', symbol: '♠' }
  if (props.amount >= props.bb * 5) return { bg: '#27ae60', border: '#2ecc71', dot: '#fff', inner: '#1e8449', symbol: '♣' }
  if (props.amount >= props.bb * 2) return { bg: '#2980b9', border: '#3498db', dot: '#fff', inner: '#1f6dad', symbol: '♥' }
  return { bg: '#7f8c8d', border: '#95a5a6', dot: '#fff', inner: '#636e72', symbol: '●' }
})

const chipColors = computed(() => {
  const c = chipColorSet.value
  return Array.from({ length: 5 }, () => ({ bg: c.bg, border: c.border, dot: c.dot }))
})

const topChipBg = computed(() => chipColorSet.value.bg)
const topChipBorder = computed(() => chipColorSet.value.border)
const topChipInner = computed(() => chipColorSet.value.inner)
const chipSymbol = computed(() => chipColorSet.value.symbol)
</script>
