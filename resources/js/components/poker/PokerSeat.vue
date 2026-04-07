<template>
  <div
    class="absolute z-[3] transition-all duration-300"
    :class="{
      'z-10': isMe,
      'z-[8]': isActive && !isMe,
      'opacity-20': seat.isOut
    }"
    :style="{
      left: position.x + '%',
      top: position.y + '%',
      transform: 'translate(-50%, -50%)'
    }"
  >
    <!-- Cards above avatar -->
    <div
      class="flex gap-[3px] justify-center"
      :class="[
        isMe ? 'mb-1.5 min-h-[80px]' : 'mb-[3px] min-h-[50px]',
        seat.folded ? 'opacity-[0.35]' : ''
      ]"
    >
      <PokerCard
        v-for="(c, j) in seat.cards"
        :key="j"
        :card="c"
        :face-down="!seat.showCards && !isMe"
        :highlight="isMe"
        :tiny="!isMe"
        :winner="isWinner"
      />
    </div>

    <!-- Hand label at showdown -->
    <div v-if="handEval && handEval.name && handEval.name !== '-'" class="text-center mb-[3px]">
      <HandLabel :name="handEval.name" :is-winner="isWinner" />
    </div>

    <!-- Avatar + Info plate -->
    <div class="flex flex-col items-center">
      <!-- Circular avatar -->
      <div
        class="rounded-full flex items-center justify-center relative transition-all duration-300"
        :class="isMe ? 'w-[52px] h-[52px]' : 'w-[42px] h-[42px]'"
        :style="{
          background: `linear-gradient(135deg, ${seatColor}, ${seatColor}88)`,
          border: isActive
            ? '3px solid #ffd700'
            : isWinner
              ? '3px solid #ffa000'
              : `2px solid ${seatColor}88`,
          boxShadow: isActive
            ? `0 0 16px ${seatColor}88, 0 0 24px rgba(255,215,0,0.4)`
            : isWinner
              ? '0 0 20px rgba(255,160,0,0.6)'
              : '0 2px 8px rgba(0,0,0,0.4)'
        }"
      >
        <span :class="isMe ? 'text-[26px]' : 'text-xl'">{{ seat.emoji }}</span>

        <!-- Position badge -->
        <div
          v-if="!seat.isOut && posLabel"
          class="absolute -top-1 -right-1 z-[2]"
        >
          <span
            class="inline-block rounded px-1.5 py-0.5 text-[9px] font-extrabold text-white border border-white/30 leading-tight"
            :class="{
              'bg-amber-400': posLabel === 'BTN',
              'bg-blue-400': posLabel === 'SB',
              'bg-red-500': posLabel === 'BB',
              'bg-gray-600': posLabel !== 'BTN' && posLabel !== 'SB' && posLabel !== 'BB'
            }"
          >
            {{ posLabel }}
          </span>
        </div>

        <!-- Winner crown -->
        <div
          v-if="isWinner"
          class="absolute -top-3.5 left-1/2 -translate-x-1/2 text-base"
        >
          👑
        </div>
      </div>

      <!-- Name + Chips -->
      <div
        class="rounded-md px-2.5 py-[3px] -mt-1 text-center backdrop-blur-sm"
        :class="[
          isMe ? 'min-w-[80px]' : 'min-w-[60px]',
          isWinner
            ? 'bg-black/70 border border-amber-600/40'
            : 'bg-black/70 border border-white/[0.08]'
        ]"
      >
        <div
          class="font-bold whitespace-nowrap"
          :class="[
            isMe ? 'text-blue-300 text-sm' : 'text-white text-xs'
          ]"
        >
          {{ seat.name }}
        </div>
        <div
          class="font-extrabold font-mono"
          :class="[
            isMe ? 'text-sm' : 'text-xs',
            seat.isOut ? 'text-gray-500' : 'text-amber-400'
          ]"
        >
          {{ seat.isOut ? '탈락' : seat.chips.toLocaleString() }}
        </div>
        <div
          v-if="seat.allIn && !seat.isOut"
          class="text-red-400 text-[10px] font-extrabold tracking-wider"
        >
          ALL-IN
        </div>
      </div>
    </div>

    <!-- Bet chips (positioned toward center) -->
    <div
      v-if="seat.bet > 0 && !showdown"
      class="absolute z-[6] pointer-events-none"
      :style="{
        left: (chipX - position.x) * 3 + 'px',
        top: (chipY - position.y) * 2 + 'px'
      }"
    >
      <ChipStack :amount="seat.bet" :bb="bb" />
    </div>

    <!-- Chat bubble -->
    <div
      v-if="chatBubble && isChatVisible"
      class="absolute z-20 bg-white/95 rounded-lg px-2 py-[3px] max-w-[120px] shadow-lg pointer-events-none"
      :class="isMe ? '-top-2.5 left-[110%]' : '-top-1.5 left-[105%]'"
    >
      <div class="text-gray-800 text-[9px] font-semibold leading-tight">
        {{ chatBubble.text }}
      </div>
      <div class="absolute left-[-4px] top-1/2 -translate-y-1/2 rotate-45 w-2 h-2 bg-white/95" />
    </div>

    <!-- From table label (new arrivals) -->
    <div
      v-if="seat.fromTable && !seat.isOut"
      class="text-center mt-[1px]"
    >
      <span class="text-blue-400 text-[7px] bg-blue-400/15 rounded px-1 py-[1px]">
        T#{{ seat.fromTable }}에서 이동
      </span>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watchEffect } from 'vue'
import PokerCard from './PokerCard.vue'
import HandLabel from './HandLabel.vue'
import ChipStack from './ChipStack.vue'
import { evalHand } from '@/composables/useHandEvaluation'

const props = defineProps({
  seat: { type: Object, required: true },
  position: { type: Object, required: true },
  isActive: { type: Boolean, default: false },
  isWinner: { type: Boolean, default: false },
  posLabel: { type: String, default: '' },
  chatBubble: { type: Object, default: null },
  showdown: { type: Boolean, default: false },
  community: { type: Array, default: () => [] },
  bb: { type: Number, default: 20 }
})

const isMe = computed(() => props.seat.isPlayer)
const seatColor = computed(() => props.seat.color || '#888')

// Bet chip position (between seat and center)
const chipX = computed(() => props.position.x + (50 - props.position.x) * 0.4)
const chipY = computed(() => props.position.y + (50 - props.position.y) * 0.4)

// Chat bubble visibility (4 second window)
const isChatVisible = ref(false)
let chatTimer = null

watchEffect(() => {
  if (props.chatBubble) {
    isChatVisible.value = true
    clearTimeout(chatTimer)
    chatTimer = setTimeout(() => {
      isChatVisible.value = false
    }, 4000)
  } else {
    isChatVisible.value = false
  }
})

// Hand evaluation for showdown
const handEval = computed(() => {
  if (
    props.showdown &&
    props.seat.showCards &&
    props.seat.cards?.length === 2 &&
    props.community?.length >= 3
  ) {
    return evalHand([...props.seat.cards, ...props.community])
  }
  return null
})
</script>
