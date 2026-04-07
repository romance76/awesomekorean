<template>
  <div class="h-full w-full relative">
    <!-- 배경: 카드 무늬 패턴 -->
    <div class="absolute inset-0 opacity-[0.07]"
      style="background-image: url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2260%22 height=%2260%22 viewBox=%220 0 60 60%22><text x=%225%22 y=%2220%22 font-size=%2214%22 fill=%22white%22>♠</text><text x=%2230%22 y=%2220%22 font-size=%2214%22 fill=%22white%22>♥</text><text x=%225%22 y=%2245%22 font-size=%2214%22 fill=%22white%22>♦</text><text x=%2230%22 y=%2245%22 font-size=%2214%22 fill=%22white%22>♣</text></svg>'); background-size: 60px 60px;" />

    <!-- 검은 쿠션 — 딜러 허리에 테이블 상단 맞춤 -->
    <div class="absolute left-[15%] right-[15%] top-[24%] bottom-[14%] p-[7px]"
      style="border-radius: 999px; background: linear-gradient(180deg, #3a3a3a, #1a1a1a, #2a2a2a); box-shadow: 0 8px 32px rgba(0,0,0,0.8), 0 2px 8px rgba(0,0,0,0.5);">

      <!-- 나무 림 -->
      <div class="w-full h-full p-[6px]"
        style="border-radius: 999px; background: linear-gradient(145deg, #c8a96e, #a08050, #d4b878, #9a7848);">

        <!-- 녹색 펠트 -->
        <div class="w-full h-full relative overflow-visible"
          style="border-radius: 999px; background: radial-gradient(ellipse at 50% 40%, #3a9a5a 0%, #2d8a4a 20%, #1f7038 45%, #165a2d 70%, #0f4a24 100%); box-shadow: inset 0 4px 40px rgba(0,0,0,0.3), inset 0 0 80px rgba(0,0,0,0.15);">

          <!-- 펠트 위 얇은 흰색 라인 (베팅 라인) -->
          <div class="absolute left-[8%] right-[8%] top-[12%] bottom-[12%] border border-white/[0.08]"
            style="border-radius: 999px;" />

          <!-- Community Cards (center) -->
          <div class="absolute top-[38%] left-1/2 -translate-x-1/2 -translate-y-1/2 text-center z-[4]">
            <div class="flex gap-2.5 justify-center mb-2">
              <PokerCard v-for="(c, i) in community" :key="i" :card="c" :winner="hasWinner" />
              <template v-if="community.length < 5 && !showdown">
                <div v-for="i in (5 - community.length)" :key="'e' + i"
                  class="w-[72px] h-[100px] rounded-lg border-2 border-dashed border-white/[0.06] bg-black/[0.06]" />
              </template>
            </div>
            <div class="text-emerald-200 text-sm font-medium tracking-widest uppercase">{{ stageLabel }} {{ stageDesc }}</div>
          </div>

          <!-- Pot -->
          <div class="absolute top-[58%] left-1/2 -translate-x-1/2 -translate-y-1/2 z-[4] flex flex-col items-center gap-1">
            <div class="flex gap-1 justify-center">
              <div v-for="ci in potChipColumns" :key="ci" class="flex flex-col items-center">
                <div v-for="si in Math.min(Math.ceil((ci + 1) * 0.8), 4)" :key="si"
                  class="w-5 h-[6px] rounded-full border border-white/30 shadow-sm -mt-0.5"
                  :style="{ background: chipColor(ci) }" />
              </div>
            </div>
            <div class="bg-black/60 rounded-full px-5 py-1.5 flex items-center gap-2 backdrop-blur border border-white/10">
              <span class="text-white/60 text-sm font-bold">POT</span>
              <span class="text-amber-400 text-2xl font-black font-mono" style="text-shadow: 0 0 10px rgba(255,215,0,0.4)">{{ pot.toLocaleString() }}</span>
            </div>
          </div>

          <!-- Dealer chip -->
          <div v-if="dealerSeat && !dealerSeat.isOut"
            class="absolute z-[8] flex items-center justify-center w-7 h-7 rounded-full text-xs font-black text-gray-800 border-2 border-amber-400 shadow-lg"
            style="background: linear-gradient(135deg, #fff, #e8e0d0); transform: translate(-50%, -50%)"
            :style="{ left: dealerChipPos.x + '%', top: dealerChipPos.y + '%' }">D</div>
        </div>
      </div>
    </div>

    <!-- 딜러 이미지 (내 맞은편 상단 중앙) -->
    <div class="absolute left-1/2 -translate-x-1/2 z-[5]" style="top: -8%">
      <div class="flex flex-col items-center">
        <img src="/images/dealer.png" alt="Dealer" class="h-[185px] w-auto object-contain drop-shadow-[0_8px_24px_rgba(0,0,0,0.9)]" />
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

// 좌석 — 딜러가 상단 중앙이므로 9명은 딜러 양옆으로
const seatPositions = [
  { x: 50, y: 84 },   // 0: 나 (하단 중앙)
  { x: 22, y: 76 },   // 1: 좌하
  { x: 8,  y: 50 },   // 2: 좌
  { x: 18, y: 24 },   // 3: 좌상
  { x: 34, y: 10 },   // 4: 상좌 (딜러 왼쪽)
  { x: 66, y: 10 },   // 5: 상우 (딜러 오른쪽) ← 중앙은 딜러
  { x: 82, y: 24 },   // 6: 우상
  { x: 92, y: 50 },   // 7: 우
  { x: 78, y: 76 }    // 8: 우하
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
  return { x: dp.x + (50 - dp.x) * 0.18, y: dp.y + (50 - dp.y) * 0.18 }
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
  const idx = getSeatGlobalIdx(seat)
  const liveIdxs = props.seats.map((x, j) => x.isOut ? -1 : j).filter(j => j >= 0)
  const dlrOff = liveIdxs.indexOf(props.dealerIdx)
  const myOff = liveIdxs.indexOf(idx)
  return POSITION_NAMES[(myOff - dlrOff + liveIdxs.length) % liveIdxs.length] || ''
}
</script>
