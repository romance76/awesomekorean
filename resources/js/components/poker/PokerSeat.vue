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
      transform: 'translate(-50%, -50%) scale(' + (seat.isPlayer ? 1.4 : 1.25) + ')',
      transformOrigin: 'center center'
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

        <!-- Dealer button (아이콘 옆으로 떨어져서) -->
        <div v-if="isDealer && !seat.isOut"
          class="absolute -right-5 top-1/2 -translate-y-1/2 w-7 h-7 rounded-full flex items-center justify-center text-[11px] font-black text-gray-800 border-2 border-amber-400 shadow-lg z-10"
          style="background: linear-gradient(135deg, #fff, #e8e0d0); box-shadow: 0 2px 6px rgba(0,0,0,0.5), 0 0 8px rgba(255,200,0,0.3)">
          D
        </div>

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

    <!-- Bet chips (라운드 종료 시 팟 중앙으로 이동 애니메이션) -->
    <div
      v-if="displayBet > 0"
      class="absolute z-[6] pointer-events-none"
      :class="chipMovingToPot ? 'chip-to-pot' : ''"
      :style="chipMovingToPot ? chipToPotStyle : 'top: -10px; left: 100%; margin-left: 4px;'"
    >
      <ChipStack :amount="displayBet" :bb="bb" />
    </div>

    <!-- Chat bubble (이름 아래에 표시) -->
    <div
      v-if="chatBubble && isChatVisible"
      class="relative z-20 flex justify-center pointer-events-none mt-1"
    >
      <div class="bg-white/95 rounded-lg px-2 py-[3px] max-w-[120px] shadow-lg">
        <div class="text-gray-800 text-[9px] font-semibold leading-tight text-center">
          {{ chatBubble.text }}
        </div>
        <div class="absolute left-1/2 -translate-x-1/2 -top-1 rotate-45 w-2 h-2 bg-white/95" />
      </div>
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
import { computed, ref, watch, watchEffect } from 'vue'
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
  bb: { type: Number, default: 20 },
  isDealer: { type: Boolean, default: false }
})

const isMe = computed(() => props.seat.isPlayer)

// 베팅 칩: bet→0 시 팟 중앙으로 이동 애니메이션
const lastBet = ref(0)
const chipMovingToPot = ref(false)
let betTimer = null

const displayBet = computed(() => props.seat.bet > 0 ? props.seat.bet : lastBet.value)

// 칩→팟 이동 스타일 (좌석 위치 → 테이블 중앙)
const chipToPotStyle = computed(() => {
  const dx = 50 - props.position.x
  const dy = 50 - props.position.y
  return {
    position: 'absolute',
    top: '-10px',
    left: '100%',
    marginLeft: '4px',
    transform: `translate(${dx * 3}px, ${dy * 3}px) scale(0.3)`,
    opacity: 0,
    transition: 'all 0.8s ease-in',
  }
})

watch(() => props.seat.bet, (newBet, oldBet) => {
  if (oldBet > 0 && newBet === 0) {
    lastBet.value = oldBet
    chipMovingToPot.value = true
    clearTimeout(betTimer)
    betTimer = setTimeout(() => {
      chipMovingToPot.value = false
      lastBet.value = 0
    }, 1000)
  } else if (newBet > 0) {
    lastBet.value = 0
    chipMovingToPot.value = false
    clearTimeout(betTimer)
  }
})
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

// 서버 문자열 카드 ('As','Kh') → 객체 ({rank,suit}) 변환
const SUIT_MAP = { s: '♠', h: '♥', d: '♦', c: '♣' }
const RANK_MAP = { T: '10' }
function parseCard(c) {
  if (!c || c === '??' || typeof c === 'object') return c
  const r = c.slice(0, -1)
  const s = c.slice(-1)
  return { rank: RANK_MAP[r] || r, suit: SUIT_MAP[s] || s }
}

// Hand evaluation for showdown
const handEval = computed(() => {
  if (
    props.showdown &&
    props.seat.showCards &&
    props.seat.cards?.length === 2 &&
    props.community?.length >= 3
  ) {
    const allCards = [...props.seat.cards, ...props.community]
      .map(c => typeof c === 'string' ? parseCard(c) : c)
      .filter(c => c && c.rank && c.suit)
    if (allCards.length < 5) return null
    return evalHand(allCards)
  }
  return null
})
</script>
