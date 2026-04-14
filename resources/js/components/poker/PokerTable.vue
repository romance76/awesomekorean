<template>
  <div class="h-full w-full relative">

    <!-- 검은 쿠션 -->
    <div class="absolute left-[15%] right-[15%] top-[16%] bottom-[24%] p-[7px]"
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

          <!-- Pot (승리 시 우승자에게 이동) -->
          <div class="absolute top-[58%] left-1/2 -translate-x-1/2 -translate-y-1/2 z-[4] flex flex-col items-center gap-1"
            :style="potAnimStyle">
            <div class="flex gap-1 justify-center">
              <div v-for="ci in potChipColumns" :key="ci" class="flex flex-col items-center">
                <div v-for="si in Math.min(Math.ceil((ci + 1) * 0.8), 4)" :key="si"
                  class="w-5 h-[6px] rounded-full border border-white/30 shadow-sm -mt-0.5"
                  :style="{ background: chipColor(ci) }" />
              </div>
            </div>
            <div v-if="!isChipFlying" class="bg-black/60 rounded-full px-5 py-1.5 flex items-center gap-2 backdrop-blur border border-white/10">
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

    <!-- 딜러 이미지 + 턴 타이머 -->
    <div class="absolute left-1/2 -translate-x-1/2 z-[5]" style="top: 0%">
      <div class="flex flex-col items-center relative">
        <img src="/images/dealer.png" alt="Dealer" class="h-[185px] w-auto object-contain drop-shadow-[0_8px_24px_rgba(0,0,0,0.9)]" />
        <!-- 턴 타이머 (딜러 오른쪽) -->
        <div v-if="turnTimer > 0 && actIdx >= 0 && !gameOver"
          class="absolute right-[-70px] top-[40%] flex flex-col items-center">
          <div class="relative w-14 h-14">
            <svg class="w-14 h-14 -rotate-90" viewBox="0 0 56 56">
              <circle cx="28" cy="28" r="24" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="4"/>
              <circle cx="28" cy="28" r="24" fill="none"
                :stroke="turnTimer <= 5 ? '#ef4444' : turnTimer <= 10 ? '#f59e0b' : '#22c55e'"
                stroke-width="4" stroke-linecap="round"
                :stroke-dasharray="150.8"
                :stroke-dashoffset="150.8 - (150.8 * turnTimer / turnTimerMax)"
                class="transition-all duration-1000"/>
            </svg>
            <div class="absolute inset-0 flex items-center justify-center">
              <span class="text-lg font-black font-mono" :class="turnTimer <= 5 ? 'text-red-400 animate-pulse' : 'text-white'">
                {{ turnTimer }}
              </span>
            </div>
          </div>
          <div class="text-[9px] text-white/50 font-bold mt-0.5">{{ activePlayerName }}</div>
        </div>
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
import { computed, ref, watch } from 'vue'
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
  chatBubbles: { type: Object, default: () => ({}) },
  turnTimer: { type: Number, default: 0 },
  turnTimerMax: { type: Number, default: 15 },
})

const activePlayerName = computed(() => {
  if (props.actIdx < 0 || !props.seats[props.actIdx]) return ''
  return props.seats[props.actIdx].name || ''
})

// 좌석 — 딜러가 상단 중앙이므로 9명은 딜러 양옆으로
// 타원 기반 좌석 배치: 포커 시계방향 (내 왼쪽부터)
// 쿠션(top:16%,bottom:24%) → 중심(50%,46%)
const seatPositions = (() => {
  const cx = 50, cy = 46, a = 40, b = 33
  // 나(아래) → 좌하 → 좌 → 좌상 → 상좌 → 상우 → 우상 → 우 → 우하
  const angles = [270, 235, 195, 155, 125, 55, 25, 345, 305]
  return angles.map(deg => {
    const rad = deg * Math.PI / 180
    return { x: Math.round(cx + a * Math.cos(rad)), y: Math.round(cy - b * Math.sin(rad)) }
  })
})()

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

// ── 승리 시 팟 칩 → 우승자에게 날아가는 애니메이션 ──
const isChipFlying = ref(false)
const chipFlyTarget = ref({ x: 50, y: 58 })

const potAnimStyle = computed(() => {
  if (!isChipFlying.value) return { transition: 'none' }
  const tx = chipFlyTarget.value.x - 50 // 현재 left:50% 기준 이동량
  const ty = chipFlyTarget.value.y - 58 // 현재 top:58% 기준 이동량
  return {
    transition: 'all 1.2s cubic-bezier(0.25, 0.1, 0.25, 1)',
    left: chipFlyTarget.value.x + '%',
    top: chipFlyTarget.value.y + '%',
    transform: 'translate(-50%, -50%) scale(0.4)',
    opacity: '0',
  }
})

watch(() => props.showdown, (val) => {
  if (val && props.handResults?.winners?.length) {
    // 1.5초 후 칩을 우승자에게 날리기
    setTimeout(() => {
      const winnerIdx = props.handResults.winners[0].seatIdx
      const winnerSeat = props.seats[winnerIdx]
      if (winnerSeat) {
        const dIdx = displayOrder.value.indexOf(winnerSeat)
        if (dIdx >= 0 && seatPositions[dIdx]) {
          chipFlyTarget.value = { x: seatPositions[dIdx].x, y: seatPositions[dIdx].y }
        }
      }
      isChipFlying.value = true
      setTimeout(() => { isChipFlying.value = false }, 1500)
    }, 1500)
  }
})
</script>
