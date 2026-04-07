<template>
  <!-- Face Down Card -->
  <div
    v-if="faceDown"
    :class="[
      'rounded-lg border-2 border-blue-700 flex items-center justify-center flex-shrink-0',
      'bg-gradient-to-br from-blue-900 to-blue-950 shadow-lg',
      tiny ? 'w-10 h-14' : 'w-16 h-[88px]'
    ]"
  >
    <div
      :class="[
        'rounded border border-blue-600 flex items-center justify-center',
        'bg-gradient-radial from-blue-700 to-blue-900',
        tiny ? 'w-5 h-8' : 'w-8 h-14'
      ]"
    >
      <span :class="['opacity-40', tiny ? 'text-xs' : 'text-lg']">♠</span>
    </div>
  </div>

  <!-- Face Up Card -->
  <div
    v-else-if="card"
    :class="[
      'rounded-lg border-2 flex flex-col items-center justify-center flex-shrink-0',
      tiny ? 'w-10 h-14 gap-[1px]' : 'w-16 h-[88px] gap-1',
      winner
        ? 'bg-gradient-to-br from-amber-50 to-amber-100 border-amber-600 shadow-[0_0_16px_rgba(255,160,0,0.6),0_3px_8px_rgba(0,0,0,0.3)]'
        : highlight
          ? 'bg-gradient-to-br from-yellow-50 to-yellow-100 border-amber-400 shadow-[0_0_10px_rgba(245,158,11,0.4)]'
          : 'bg-gradient-to-br from-white to-gray-100 border-gray-400 shadow-md'
    ]"
  >
    <div
      :class="[
        'font-extrabold leading-none',
        tiny ? 'text-base -mt-[1px]' : 'text-2xl -mt-0.5'
      ]"
      :style="{ color: suitColor }"
    >
      {{ card.rank }}
    </div>
    <SuitIcon :suit="card.suit" :size="suitSize" :color="suitColor" />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import SuitIcon from './SuitIcon.vue'

const SC = { '♠': '#1a1a2e', '♥': '#cc0000', '♦': '#cc0000', '♣': '#1a1a2e' }

const props = defineProps({
  card: { type: Object, default: null },
  faceDown: { type: Boolean, default: false },
  highlight: { type: Boolean, default: false },
  tiny: { type: Boolean, default: false },
  winner: { type: Boolean, default: false }
})

const suitColor = computed(() => props.card ? SC[props.card.suit] || '#1a1a2e' : '#1a1a2e')
const suitSize = computed(() => props.tiny ? 18 : 28)
</script>
