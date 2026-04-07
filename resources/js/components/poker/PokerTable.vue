<template>
  <div class="h-full relative mx-auto w-full max-w-[720px]">
    <!-- Table shadow/glow -->
    <div
      class="absolute left-[8%] right-[8%] top-[10%] bottom-[18%] rounded-full bg-black/40 blur-[20px]"
    />

    <!-- Table border (wood rim) -->
    <div
      class="absolute left-[7%] right-[7%] top-[9%] bottom-[17%] rounded-full p-1.5"
      style="background: linear-gradient(145deg, #5d3a1a, #3e2510, #5d3a1a)"
    >
      <!-- Felt -->
      <div
        class="w-full h-full rounded-full relative overflow-visible"
        style="
          background: radial-gradient(ellipse at 40% 40%, #2d7a4a 0%, #1a5c35 30%, #13442a 60%, #0d3320 100%);
          box-shadow: inset 0 4px 30px rgba(0,0,0,0.4), inset 0 0 60px rgba(0,0,0,0.2);
        "
      >
        <!-- Felt texture overlay -->
        <div class="absolute inset-0 rounded-full opacity-50" style="background: url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%224%22 height=%224%22><rect width=%224%22 height=%224%22 fill=%22rgba(0,0,0,0.03)%22/><rect width=%221%22 height=%221%22 fill=%22rgba(255,255,255,0.02)%22/></svg>')" />

        <!-- Community Cards -->
        <div class="absolute top-[42%] left-1/2 -translate-x-1/2 -translate-y-1/2 text-center z-[4]">
          <div class="flex gap-[5px] justify-center mb-2">
            <PokerCard
              v-for="(c, i) in community"
              :key="i"
              :card="c"
              :winner="hasWinner"
            />
            <!-- Empty card placeholders -->
            <template v-if="community.length < 5 && !showdown">
              <div
                v-for="i in (5 - community.length)"
                :key="'e' + i"
                class="w-[58px] h-20 rounded-lg border-2 border-dashed border-white/10 bg-black/10"
              />
            </template>
          </div>
          <!-- Stage label on felt -->
          <div class="text-emerald-300/70 text-[9px] tracking-[2px] mb-1 uppercase">
            {{ stageLabel }} {{ stageDesc }}
          </div>
        </div>

        <!-- Pot display -->
        <div class="absolute top-[62%] left-1/2 -translate-x-1/2 -translate-y-1/2 z-[4] flex flex-col items-center gap-[3px]">
          <!-- Pot chips visual -->
          <div class="flex gap-[3px] justify-center">
            <div
              v-for="ci in potChipColumns"
              :key="ci"
              class="flex flex-col items-center"
            >
              <div
                v-for="si in Math.min(Math.ceil((ci + 1) * 0.8), 4)"
                :key="si"
                class="w-[18px] h-[5px] rounded-full border border-white/[0.35] shadow-sm -mt-0.5"
                :style="{ background: chipColor(ci) }"
              />
            </div>
          </div>
          <div class="bg-black/55 rounded-full px-4 py-1 flex items-center gap-1.5 backdrop-blur-sm border border-white/10">
            <span class="text-gray-400 text-[9px] font-semibold">POT</span>
            <span class="text-amber-400 text-lg font-extrabold font-mono" style="text-shadow: 0 0 8px rgba(255,215,0,0.3)">
              {{ pot.toLocaleString() }}
            </span>
          </div>
        </div>

        <!-- Dealer chip -->
        <div
          v-if="dealerSeat && !dealerSeat.isOut"
          class="absolute z-[8] flex items-center justify-center w-[22px] h-[22px] rounded-full text-[9px] font-black text-gray-700 border-2 border-amber-400 shadow-md"
          style="background: linear-gradient(135deg, #fff, #e0e0e0); transform: translate(-50%, -50%)"
          :style="{ left: dealerChipPos.x + '%', top: dealerChipPos.y + '%' }"
        >
          D
        </div>
      </div>
    </div>

    <!-- Player Seats -->
    <PokerSeat
      v-for="(seat, i) in displayOrder"
      :key="seat.id + '-' + i"
      :seat="seat"
      :position="seatPositions[i]"
      :is-active="actIdx === getSeatGlobalIdx(seat) && !gameOver"
      :is-winner="isWinnerSeat(seat)"
      :pos-label="getPosLabel(seat)"
      :chat-bubble="chatBubbles[getSeatGlobalIdx(seat)]"
      :showdown="showdown"
      :community="community"
      :bb="bl?.bb || 20"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import PokerCard from './PokerCard.vue'
import PokerSeat from './PokerSeat.vue'

const POSITION_NAMES = {
  0: 'BTN', 1: 'SB', 2: 'BB', 3: 'UTG', 4: 'UTG+1',
  5: 'MP', 6: 'MP+1', 7: 'HJ', 8: 'CO'
}

const STAGE_NAMES = {
  preflop: '프리플랍',
  flop: '플랍',
  turn: '턴',
  river: '리버'
}

const STAGE_DESCS = {
  preflop: '· 카드 배분 후 첫 베팅',
  flop: '· 커뮤니티 3장 오픈',
  turn: '· 4번째 카드 오픈',
  river: '· 마지막 카드 오픈'
}

const props = defineProps({
  seats: { type: Array, required: true },
  community: { type: Array, default: () => [] },
  pot: { type: Number, default: 0 },
  stage: { type: String, default: 'preflop' },
  dealerIdx: { type: Number, default: 0 },
  showdown: { type: Boolean, default: false },
  handResults: { type: Object, default: null },
  gameOver: { type: Boolean, default: false },
  bl: { type: Object, default: () => ({ sb: 10, bb: 20, ante: 0 }) },
  actIdx: { type: Number, default: -1 },
  chatBubbles: { type: Object, default: () => ({}) }
})

const seatPositions = [
  { x: 50, y: 80 },   // 0: player (bottom)
  { x: 12, y: 72 },   // 1: left-bottom
  { x: 4, y: 50 },    // 2: left
  { x: 12, y: 28 },   // 3: left-top
  { x: 32, y: 14 },   // 4: top-left
  { x: 50, y: 10 },   // 5: top center
  { x: 68, y: 14 },   // 6: top-right
  { x: 88, y: 28 },   // 7: right-top
  { x: 96, y: 50 }    // 8: right
]

const stageLabel = computed(() => STAGE_NAMES[props.stage] || props.stage)
const stageDesc = computed(() => STAGE_DESCS[props.stage] || '')

const hasWinner = computed(() => {
  return !!(props.handResults && props.handResults.winners?.[0]?.name)
})

// Player seat is always displayed at bottom center
const playerSeat = computed(() => props.seats.find(s => s.isPlayer))
const playerIdx = computed(() => props.seats.indexOf(playerSeat.value))

const displayOrder = computed(() => {
  const order = []
  for (let i = 0; i < props.seats.length; i++) {
    order.push(props.seats[(playerIdx.value + i) % props.seats.length])
  }
  return order
})

const dealerSeat = computed(() => props.seats[props.dealerIdx])

const dealerChipPos = computed(() => {
  if (!dealerSeat.value) return { x: 50, y: 50 }
  const dDispIdx = displayOrder.value.indexOf(dealerSeat.value)
  if (dDispIdx < 0) return { x: 50, y: 50 }
  const dp = seatPositions[dDispIdx]
  const dx = (50 - dp.x) * 0.25
  const dy = (50 - dp.y) * 0.25
  return { x: dp.x + dx * 0.5, y: dp.y + dy * 0.5 }
})

// Pot chip columns
const potChipColumns = computed(() => {
  if (props.pot <= 0) return []
  const bb = props.bl?.bb || 20
  const count = Math.min(Math.ceil(props.pot / (bb * 4)), 6)
  return Array.from({ length: count }, (_, i) => i)
})

function chipColor(ci) {
  const colors = [
    'linear-gradient(180deg, #e74c3c, #c0392b)',
    'linear-gradient(180deg, #2ecc71, #27ae60)',
    'linear-gradient(180deg, #3498db, #2980b9)',
    'linear-gradient(180deg, #1a1a2e, #333)'
  ]
  return colors[ci % 4]
}

function getSeatGlobalIdx(seat) {
  return props.seats.indexOf(seat)
}

function isWinnerSeat(seat) {
  return !!(
    props.handResults &&
    props.handResults.winners?.[0]?.name === seat.name &&
    props.gameOver
  )
}

function getPosLabel(seat) {
  const seatGlobalIdx = getSeatGlobalIdx(seat)
  const liveIdxs = props.seats
    .map((x, j) => (x.isOut ? -1 : j))
    .filter(j => j >= 0)
  const dlrOff = liveIdxs.indexOf(props.dealerIdx)
  const myOff = liveIdxs.indexOf(seatGlobalIdx)
  const relPos = (myOff - dlrOff + liveIdxs.length) % liveIdxs.length
  return POSITION_NAMES[relPos] || ''
}
</script>
