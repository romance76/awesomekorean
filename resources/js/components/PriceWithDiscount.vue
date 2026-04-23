<template>
<span v-if="discount > 0" class="inline-flex items-baseline gap-1.5 flex-wrap">
  <span class="text-gray-400 line-through text-[0.85em]">{{ formatted(original) }}</span>
  <span class="text-[0.7em] bg-red-100 text-red-600 font-bold px-1.5 py-0.5 rounded-full leading-none">-{{ discount }}%</span>
  <span :class="finalClass">{{ formatted(final) }}</span>
</span>
<span v-else :class="finalClass">{{ formatted(original) }}</span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  original: { type: Number, required: true },     // 원가 (숫자)
  discount: { type: Number, default: 0 },          // 0 이면 할인 없음
  currency: { type: String, default: 'P' },        // 'P' (포인트) 또는 'USD'
  finalClass: { type: String, default: 'font-bold text-gray-800' },
})

const final = computed(() => {
  if (!props.discount) return props.original
  return props.currency === 'USD'
    ? +(props.original * (100 - props.discount) / 100).toFixed(2)
    : Math.round(props.original * (100 - props.discount) / 100)
})

function formatted(n) {
  if (props.currency === 'USD') return '$' + (Number(n).toFixed(2))
  return Number(n).toLocaleString() + props.currency
}
</script>
