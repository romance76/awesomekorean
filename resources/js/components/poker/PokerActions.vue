<template>
  <div class="px-3 shrink-0 bg-black/20 border-t border-amber-400/[0.08] h-[90px] flex flex-col justify-center">
    <!-- Player's turn -->
    <div v-if="isPlayerTurn && !gameOver">
      <!-- Main action buttons -->
      <div class="flex gap-1.5 justify-center mb-1.5 flex-wrap">
        <button
          class="px-4 py-2.5 rounded-lg border-none bg-red-700 text-white cursor-pointer font-bold text-sm font-sans hover:bg-red-600 active:bg-red-800 transition-colors"
          @click="$emit('action', 'fold', 0)"
        >
          폴드
        </button>

        <button
          v-if="canCheck"
          class="px-4 py-2.5 rounded-lg border-none bg-blue-600 text-white cursor-pointer font-bold text-sm font-sans hover:bg-blue-500 active:bg-blue-700 transition-colors"
          @click="$emit('action', 'check', 0)"
        >
          체크
        </button>
        <button
          v-else
          class="px-4 py-2.5 rounded-lg border-none bg-green-600 text-white cursor-pointer font-bold text-sm font-sans hover:bg-green-500 active:bg-green-700 transition-colors"
          @click="$emit('action', 'call', 0)"
        >
          콜 ({{ callAmt.toLocaleString() }})
        </button>

        <button
          class="px-4 py-2.5 rounded-lg border-none bg-amber-700 text-white cursor-pointer font-bold text-sm font-sans hover:bg-amber-600 active:bg-amber-800 transition-colors"
          @click="$emit('action', 'allin', 0)"
        >
          올인
        </button>
      </div>

      <!-- Raise slider + button -->
      <div class="flex gap-1.5 justify-center items-center">
        <input
          type="range"
          :min="raiseMin"
          :max="Math.max(raiseMin, playerChips)"
          :step="blindBB"
          :value="raiseAmt"
          class="flex-1 max-w-[220px] accent-amber-400"
          @input="$emit('updateRaise', +$event.target.value)"
        />
        <button
          class="px-4 py-2.5 rounded-lg border-none text-gray-900 cursor-pointer font-bold text-sm font-sans hover:brightness-110 active:brightness-90 transition-all"
          style="background: #e8b730"
          :disabled="raiseAmt >= playerChips"
          @click="$emit('action', 'raise', raiseAmt)"
        >
          레이즈 {{ raiseAmt.toLocaleString() }}
        </button>
      </div>
    </div>

    <!-- Game over: next hand button -->
    <div v-else-if="gameOver" class="text-center">
      <button
        class="px-9 py-3 rounded-[10px] border-none text-gray-900 cursor-pointer font-bold text-[15px] font-sans hover:brightness-110 active:brightness-90 transition-all"
        style="background: linear-gradient(135deg, #e8b730, #c49a20)"
        @click="$emit('nextHand')"
      >
        다음 핸드 →
      </button>
    </div>

    <!-- AI turn -->
    <div v-else class="text-center text-gray-600 text-xs py-2">
      ⏳ AI 턴...
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  isPlayerTurn: { type: Boolean, default: false },
  gameOver: { type: Boolean, default: false },
  canCheck: { type: Boolean, default: false },
  callAmt: { type: Number, default: 0 },
  currentBetLevel: { type: Number, default: 0 },
  playerChips: { type: Number, default: 0 },
  blindBB: { type: Number, default: 20 },
  raiseAmt: { type: Number, default: 0 }
})

defineEmits(['action', 'updateRaise', 'nextHand'])

const raiseMin = computed(() => {
  return Math.max(props.currentBetLevel * 2, props.blindBB * 2)
})
</script>
