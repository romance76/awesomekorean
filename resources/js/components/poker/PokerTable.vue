<template>
  <div class="h-full w-full relative">
    <!-- Table shadow -->
    <div class="absolute left-[2%] right-[2%] top-[8%] bottom-[8%] bg-black/50 blur-[24px]"
      style="border-radius: 45% / 42%" />

    <!-- Wood rim -->
    <div class="absolute left-[1.5%] right-[1.5%] top-[7%] bottom-[7%] p-[6px]"
      style="border-radius: 45% / 42%; background: linear-gradient(145deg, #7a5a3a, #5a3a1a, #7a5a3a)">
      <!-- Green felt -->
      <div class="w-full h-full relative overflow-visible"
        style="border-radius: 45% / 42%; background: radial-gradient(ellipse at 45% 40%, #2d7a4a 0%, #1a5c35 35%, #13442a 65%, #0d3320 100%); box-shadow: inset 0 4px 40px rgba(0,0,0,0.5), inset 0 0 80px rgba(0,0,0,0.2)">

        <!-- Community Cards (center) -->
        <div class="absolute top-[40%] left-1/2 -translate-x-1/2 -translate-y-1/2 text-center z-[4]">
          <div class="flex gap-2 justify-center mb-2">
            <PokerCard v-for="(c, i) in community" :key="i" :card="c" :winner="hasWinner" />
            <template v-if="community.length < 5 && !showdown">
              <div v-for="i in (5 - community.length)" :key="'e' + i"
                class="w-16 h-[88px] rounded-lg border-2 border-dashed border-white/10 bg-black/10" />
            </template>
          </div>
          <div class="text-emerald-300 text-sm tracking-widest uppercase">{{ stageLabel }} {{ stageDesc }}</div>
        </div>

        <!-- Pot -->
        <div class="absolute top-[60%] left-1/2 -translate-x-1/2 -translate-y-1/2 z-[4] flex flex-col items-center gap-1">
          <div class="flex gap-1 justify-center">
            <div v-for="ci in potChipColumns" :key="ci" class="flex flex-col items-center">
              <div v-for="si in Math.min(Math.ceil((ci + 1) * 0.8), 4)" :key="si"
                class="w-5 h-[6px] rounded-full border border-white/30 shadow-sm -mt-0.5"
                :style="{ background: chipColor(ci) }" />
            </div>
          </div>
          <div class="bg-black/60 rounded-full px-5 py-1.5 flex items-center gap-2 backdrop-blur border border-white/10">
            <span class="text-white/70 text-sm font-bold">POT</span>
            <span class="text-amber-400 text-2xl font-black font-mono" style="text-shadow: 0 0 10px rgba(255,215,0,0.4)">{{ pot.toLocaleString() }}</span>
          </div>
        </div>

        <!-- Dealer chip -->
        <div v-if="dealerSeat && !dealerSeat.isOut"
          class="absolute z-[8] flex items-center justify-center w-6 h-6 rounded-full text-[10px] font-black text-gray-700 border-2 border-amber-400 shadow-lg"
          style="background: linear-gradient(135deg, #fff, #e0e0e0); transform: translate(-50%, -50%)"
          :style="{ left: dealerChipPos.x + '%', top: dealerChipPos.y + '%' }">D</div>
      </div>
    </div>

    <!-- Player Seats -->
    <PokerSeat v-for="(seat, i) in displayOrder" :key="seat.id + '-' + i"
      :seat="seat" :position="seatPositions[i]"
      :is-active="actIdx === getSeatGlobalIdx(seat) && !gameOver"
      :is-winner="isWinnerSeat(seat)" :pos-label="getPosLabel(seat)"
      :chat-bubble="chatBubbles[getSeatGlobalIdx(seat)]"
      :showdown="showdown" :community="community" :bb="bl?.bb || 20" />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import PokerCard from './PokerCard.vue'
import PokerSeat from './PokerSeat.vue'

const POSITION_NAMES = { 0:'BTN', 1:'SB', 2:'BB', 3:'UTG', 4:'UTG+1', 5:'MP', 6:'MP+1', 7:'HJ', 8:'CO' }
const STAGE_NAMES = { preflop:'프리플랍', flop:'플랍', turn:'턴', river:'리버' }
const STAGE_DESCS = { preflop:'· 카드 배분 후 첫 베팅', flop:'· 커뮤니티 3장 오픈', turn:'· 4번째 카드 오픈', river:'· 마지막 카드 오픈' }

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

// 넓은 타원형 좌석 배치 — 좌우로 넓게, 테이블 가장자리에
const seatPositions = [
  { x: 50, y: 88 },   // 0: 나 (하단 중앙)
  { x: 16, y: 78 },   // 1: 좌하
  { x: 2,  y: 48 },   // 2: 좌
  { x: 16, y: 18 },   // 3: 좌상
  { x: 36, y: 4 },    // 4: 상좌
  { x: 50, y: 0 },    // 5: 상단 중앙
  { x: 64, y: 4 },    // 6: 상우
  { x: 84, y: 18 },   // 7: 우상
  { x: 98, y: 48 }    // 8: 우
]

const stageLabel = computed(() => STAGE_NAMES[props.stage] || props.stage)
const stageDesc = computed(() => STAGE_DESCS[props.stage] || '')
const hasWinner = computed(() => !!(props.handResults?.winners?.[0]?.name))

const playerSeat = computed(() => props.seats.find(s => s.isPlayer))
const playerIdx = computed(() => props.seats.indexOf(playerSeat.value))
const displayOrder = computed(() => {
  const order = []
  for (let i = 0; i < props.seats.length; i++) order.push(props.seats[(playerIdx.value + i) % props.seats.length])
  return order
})

const dealerSeat = computed(() => props.seats[props.dealerIdx])
const dealerChipPos = computed(() => {
  if (!dealerSeat.value) return { x: 50, y: 50 }
  const dDispIdx = displayOrder.value.indexOf(dealerSeat.value)
  if (dDispIdx < 0) return { x: 50, y: 50 }
  const dp = seatPositions[dDispIdx]
  return { x: dp.x + (50 - dp.x) * 0.15, y: dp.y + (50 - dp.y) * 0.15 }
})

const potChipColumns = computed(() => {
  if (props.pot <= 0) return []
  return Array.from({ length: Math.min(Math.ceil(props.pot / ((props.bl?.bb || 20) * 4)), 6) }, (_, i) => i)
})

function chipColor(ci) {
  return ['linear-gradient(180deg,#e74c3c,#c0392b)', 'linear-gradient(180deg,#2ecc71,#27ae60)', 'linear-gradient(180deg,#3498db,#2980b9)', 'linear-gradient(180deg,#1a1a2e,#333)'][ci % 4]
}

function getSeatGlobalIdx(seat) { return props.seats.indexOf(seat) }

function isWinnerSeat(seat) {
  return !!(props.handResults?.winners?.[0]?.name === seat.name && props.gameOver)
}

function getPosLabel(seat) {
  const seatGlobalIdx = getSeatGlobalIdx(seat)
  const liveIdxs = props.seats.map((x, j) => x.isOut ? -1 : j).filter(j => j >= 0)
  const dlrOff = liveIdxs.indexOf(props.dealerIdx)
  const myOff = liveIdxs.indexOf(seatGlobalIdx)
  return POSITION_NAMES[(myOff - dlrOff + liveIdxs.length) % liveIdxs.length] || ''
}
</script>
