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
        :face-down="(!seat.showCards && !isMe) || c === '??' || c === '?'"
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
        :class="isMe ? 'w-[64px] h-[64px]' : 'w-[40px] h-[40px]'"
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
        <span :class="isMe ? 'text-[30px]' : 'text-lg'">{{ seat.emoji }}</span>

        <!-- Winner crown -->
        <div
          v-if="isWinner"
          class="absolute -top-3.5 left-1/2 -translate-x-1/2 text-base"
        >
          👑
        </div>
      </div>

      <!-- Name + Position + Chips -->
      <div
        class="rounded-md text-center backdrop-blur-sm"
        :class="[
          isMe ? 'min-w-[100px] px-3 py-1.5 -mt-1.5 rounded-lg' : 'min-w-[56px] px-1.5 py-[2px] -mt-1',
          isWinner
            ? 'bg-black/80 border-2 border-amber-500/60'
            : isMe
              ? 'bg-black/80 border-2 border-yellow-500/50 shadow-lg shadow-yellow-500/20'
              : 'bg-black/70 border border-white/[0.08]'
        ]"
      >
        <div class="flex items-center justify-center gap-1 whitespace-nowrap">
          <span v-if="!seat.isOut && posLabel"
            class="inline-block rounded px-1 py-[1px] text-[8px] font-extrabold text-white border border-white/30 leading-tight"
            :class="{
              'bg-amber-500': posLabel === 'BTN',
              'bg-blue-500': posLabel === 'SB',
              'bg-red-600': posLabel === 'BB',
              'bg-gray-600': !['BTN','SB','BB'].includes(posLabel)
            }">{{ posLabel }}</span>
          <span :class="isMe ? 'text-yellow-300 text-sm font-black' : 'text-white text-[11px] font-bold'">{{ seat.name }}</span>
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

    <!-- Bet chips (카드 오른쪽에 통일) -->
    <div
      v-if="seat.bet > 0 && !showdown"
      class="absolute z-[6] pointer-events-none"
      style="top: -10px; left: 100%; margin-left: 4px;"
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

// (betChipStyle removed — chips now always positioned right of cards)

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
