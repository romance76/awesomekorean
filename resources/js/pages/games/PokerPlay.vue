<template>
<div class="select-none h-screen bg-gradient-to-b from-gray-950 via-[#0e1525] to-[#0b1018] flex flex-col overflow-hidden"
  style="font-family:'Noto Sans KR','Malgun Gothic','Apple SD Gothic Neo',sans-serif;">

  <!-- ===== RESULT SCREEN ===== -->
  <div v-if="tourneyOver" class="min-h-screen flex items-center justify-center p-4">
    <div class="text-center max-w-md bg-gray-900 rounded-2xl border border-amber-400/30 p-8 space-y-4">
      <div class="text-5xl">{{ inMoney ? '&#127942;' : '&#128148;' }}</div>
      <h2 :class="inMoney ? 'text-amber-400' : 'text-red-500'" class="text-2xl font-black">
        {{ inMoney ? '&#51077;&#49345; &#52629;&#54616;&#54633;&#45768;&#45796;!' : '&#53448;&#46973;' }}
      </h2>
      <div class="text-5xl font-black font-mono" :class="inMoney ? 'text-green-400' : 'text-red-400'">
        {{ finalPlace }}&#50948;
      </div>
      <div class="text-sm text-gray-400">/ {{ config.totalPlayers }}&#47749; &#51473;</div>

      <div v-if="inMoney" class="bg-green-500/10 border border-green-500/20 rounded-lg p-3">
        <div class="text-green-400 font-bold">&#128176; &#49345;&#44552; &#54925;&#46301;!</div>
        <div class="text-green-300 text-lg font-bold font-mono">{{ calcPrize().toLocaleString() }} &#52841;</div>
      </div>

      <div v-if="myBounties.length > 0" class="bg-yellow-500/10 border border-yellow-500/20 rounded-lg p-3">
        <div class="text-yellow-400 font-bold">&#128176; &#48148;&#50868;&#54000; {{ myBounties.length }}&#47749; &#51228;&#44144;</div>
        <div class="flex gap-1 justify-center flex-wrap mt-1">
          <span v-for="(b, i) in myBounties" :key="i"
            class="bg-amber-400/10 border border-amber-400/20 rounded-md px-2 py-0.5 text-[11px] text-amber-400">
            {{ b.emoji }} {{ b.name }}
          </span>
        </div>
        <div class="text-yellow-300 text-sm font-mono mt-1">+{{ (myBounties.length * Math.floor(config.buyIn * 0.1)).toLocaleString() }} &#52841;</div>
      </div>

      <div class="text-gray-400 text-sm">&#54540;&#47112;&#51060; &#54592;&#46300;: {{ handNum }} | &#52572;&#44256; &#48660;&#46972;&#51064;&#46300;: {{ bl.sb }}/{{ bl.bb }}</div>

      <div class="flex gap-3 justify-center pt-2">
        <button @click="handleRestart"
          class="px-8 py-3 rounded-xl bg-amber-500 hover:bg-amber-400 text-gray-950 font-bold text-base transition">
          &#45796;&#49884; &#46020;&#51204;
        </button>
        <router-link to="/games/poker"
          class="px-8 py-3 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 font-bold text-base border border-gray-700 transition">
          &#47196;&#48708;&#47196;
        </router-link>
      </div>
    </div>
  </div>

  <!-- ===== GAME SCREEN ===== -->
  <template v-else-if="screen === 'game'">
    <!-- Top bar (compact) -->
    <div class="bg-gradient-to-b from-[#0a1628] to-[#0d1f38] border-b-2 border-blue-900/60 shrink-0 px-2.5 py-1">
      <div class="flex justify-between items-center">
        <div class="flex items-center gap-1.5">
          <div class="bg-red-700 rounded px-2 py-0.5 text-[11px] font-extrabold text-white tracking-wider">LEVEL {{ blindLevel + 1 }}</div>
          <div class="text-blue-400 text-sm font-bold font-mono">{{ bl.sb.toLocaleString() }}/{{ bl.bb.toLocaleString() }}</div>
          <div :class="levelTimer <= 60 ? 'bg-red-500/30 border-red-500' : 'bg-white/5 border-white/10'" class="rounded px-2 py-0.5 border">
            <span :class="levelTimer <= 60 ? 'text-red-500' : 'text-emerald-400'" class="text-sm font-bold font-mono">{{ fmtTime(levelTimer) }}</span>
          </div>
        </div>
        <div class="flex items-center gap-1.5">
          <div :class="totalRemaining <= paidSlots ? 'text-emerald-400' : 'text-gray-400'" class="text-[11px]">
            <span class="font-bold text-sm">{{ totalRemaining }}</span>/{{ config.totalPlayers }}명
          </div>
          <div v-if="myBounties.length > 0" class="text-amber-300 text-[11px] font-bold">💰x{{ myBounties.length }}</div>
        </div>
      </div>
    </div>

    <!-- 3-column: 코칭(left) + 테이블(center) + 모니터(right) -->
    <div class="flex-1 min-h-0 flex">

      <!-- ◀ 좌측: 코칭 + 폴드 -->
      <div class="w-[200px] shrink-0 bg-[#080c14]/90 border-r border-gray-800/30 flex flex-col overflow-hidden hidden xl:flex">
        <!-- 코칭 -->
        <div v-if="coachTips && !gameOver" class="p-2.5 border-b border-gray-800/30">
          <div class="flex items-center justify-between mb-1.5">
            <div class="flex items-center gap-1">
              <span class="bg-blue-500/20 border border-blue-500/30 rounded px-1.5 py-0.5 text-blue-400 text-xs font-bold">{{ coachTips.posName }}</span>
              <span class="text-gray-400 text-xs">{{ coachTips.posFullName }}</span>
            </div>
            <span :class="coachTips.m <= 10 ? 'text-red-400' : 'text-gray-300'" class="text-xs font-mono">{{ coachTips.m }}BB</span>
          </div>
          <div class="mb-1.5">
            <div class="flex justify-between items-center mb-0.5">
              <span class="text-gray-400 text-xs">승률</span>
              <span :class="coachTips.equity >= 60 ? 'text-emerald-400' : coachTips.equity >= 40 ? 'text-amber-400' : 'text-red-400'" class="text-base font-black">{{ coachTips.equity }}%</span>
            </div>
            <div class="w-full h-1.5 bg-white/10 rounded-full overflow-hidden">
              <div :class="coachTips.equity >= 60 ? 'bg-emerald-500' : coachTips.equity >= 40 ? 'bg-amber-500' : 'bg-red-500'" class="h-full rounded-full transition-all" :style="{ width: coachTips.equity + '%' }" />
            </div>
          </div>
          <div v-if="coachTips.toCall > 0" class="bg-black/30 rounded px-2 py-0.5 mb-1.5 text-[11px] text-gray-300">
            팟오즈 {{ coachTips.potOddsPct }}% ({{ coachTips.toCall }}/{{ coachTips.pot + coachTips.toCall }})
          </div>
          <div class="rounded px-2 py-1" :style="{ background: coachTips.rec.color + '15', border: '1px solid ' + coachTips.rec.color + '30' }">
            <span :style="{ color: coachTips.rec.color }" class="text-xs font-black">→ {{ coachTips.rec.action }}</span>
            <div class="text-gray-400 text-[11px] mt-0.5 leading-tight">{{ coachTips.rec.reason }}</div>
          </div>
          <div v-if="coachTips.handDesc || coachTips.madeHand" class="text-gray-300 text-[11px] mt-1">
            {{ coachTips.madeHand ? '메이드: ' + coachTips.madeHand : coachTips.handDesc }}
          </div>
        </div>

        <!-- 폴드 카드 -->
        <div class="flex-1 overflow-y-auto p-2.5">
          <div class="text-amber-600/80 text-xs font-bold mb-1">🃏 폴드 카드</div>
          <div v-if="foldReveals.length === 0" class="text-gray-400 text-[11px]">아직 없음</div>
          <div v-for="(fr, i) in foldReveals.slice(-6)" :key="i" class="flex items-start gap-1.5 mb-1.5">
            <span class="text-xs shrink-0">{{ fr.emoji }}</span>
            <div class="min-w-0">
              <div class="text-gray-300 text-xs font-bold">{{ fr.name }} <span class="text-gray-400">({{ fr.posLabel }})</span></div>
              <div class="text-amber-800 text-[11px] leading-tight">{{ fr.reason }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- ■ 중앙: 테이블 -->
      <div class="flex-1 min-h-0 min-w-0">
        <PokerTable :seats="seats" :community="community" :pot="pot" :stage="stage"
          :dealer-idx="dealerIdx" :showdown="showdown" :hand-results="handResults"
          :game-over="gameOver" :bl="bl" :act-idx="actIdx" :chat-bubbles="chatBubbles"
          :current-bet-level="currentBetLevel" :blind-level="blindLevel" :total-remaining="totalRemaining"
          :paid-slots="paidSlots" :fold-reveals="foldReveals" :is-player-turn="isPlayerTurn" />
      </div>

      <!-- ▶ 우측: 모니터(항상) + 채팅 -->
      <div class="w-[210px] shrink-0 bg-[#080c14]/90 border-l border-gray-800/30 flex flex-col overflow-hidden hidden xl:flex">
        <!-- 토너먼트 모니터 (항상 표시) -->
        <div class="p-2.5 border-b border-gray-800/30">
          <div class="text-blue-400 text-xs font-bold tracking-wider mb-1.5">🏆 TOURNAMENT</div>
          <div class="text-center mb-1.5">
            <div class="text-gray-300 text-[8px] tracking-widest">LEVEL {{ blindLevel + 1 }} · {{ bl.sb }}/{{ bl.bb }}{{ bl.ante > 0 ? ' (A' + bl.ante + ')' : '' }}</div>
            <div :class="levelTimer <= 60 ? 'text-red-500' : 'text-emerald-400'" class="text-2xl font-extrabold font-mono leading-none">{{ fmtTime(levelTimer) }}</div>
            <div class="w-full h-[3px] bg-white/[0.06] rounded mt-1 overflow-hidden">
              <div :class="levelTimer <= 60 ? 'bg-red-500' : 'bg-emerald-500'" class="h-full rounded transition-all duration-1000" :style="{ width: (levelTimer / (bl.dur * 60) * 100) + '%' }" />
            </div>
            <div class="text-gray-400 text-[8px] mt-0.5">NEXT: {{ nextBl.sb }}/{{ nextBl.bb }} · 경과 {{ fmtElapsed(elapsedTime) }}</div>
          </div>
          <div class="space-y-0.5">
            <div class="flex justify-between text-xs"><span class="text-gray-300">REMAINING</span><span :class="totalRemaining <= paidSlots ? 'text-emerald-400' : 'text-blue-400'" class="font-bold font-mono">{{ totalRemaining }}/{{ config.totalPlayers }}</span></div>
            <div class="flex justify-between text-xs"><span class="text-gray-300">AVG STACK</span><span class="text-amber-400 font-bold font-mono">{{ Math.floor(config.startChips * config.totalPlayers / Math.max(1, totalRemaining)).toLocaleString() }}</span></div>
            <div class="flex justify-between text-xs"><span class="text-gray-300">MY STACK</span><span :class="(playerSeat?.chips || 0) < bl.bb * 10 ? 'text-red-400' : 'text-white'" class="font-bold font-mono">{{ (playerSeat?.chips || 0).toLocaleString() }}</span></div>
            <div class="flex justify-between text-xs"><span class="text-gray-300">RANK</span><span :class="myRank <= paidSlots ? 'text-emerald-400' : 'text-gray-300'" class="font-bold font-mono">~{{ myRank }}위</span></div>
            <div class="flex justify-between text-xs"><span class="text-gray-300">BOUNTIES</span><span :class="myBounties.length > 0 ? 'text-amber-400' : 'text-gray-400'" class="font-bold font-mono">{{ myBounties.length }}</span></div>
          </div>
          <div class="flex justify-between text-xs mt-1 pt-1 border-t border-gray-800/30">
            <span class="text-gray-300">PRIZE</span><span class="text-amber-400 font-bold">${{ prizePool.toLocaleString() }}</span>
          </div>
          <div v-if="totalRemaining <= paidSlots * 1.2 && totalRemaining > paidSlots" class="text-center mt-1.5 bg-amber-500/10 rounded py-0.5">
            <span class="text-amber-400 text-[11px] font-bold">🫧 BUBBLE — {{ totalRemaining - paidSlots }}명</span>
          </div>
          <div v-else-if="totalRemaining <= paidSlots" class="text-center mt-1.5 bg-emerald-500/10 rounded py-0.5">
            <span class="text-emerald-400 text-[11px] font-bold">💰 IN THE MONEY</span>
          </div>
        </div>

        <!-- 채팅 (준비 중) -->
        <div class="flex-1 flex flex-col overflow-hidden">
          <div class="p-2 border-b border-gray-800/30">
            <span class="text-gray-300 text-xs font-bold">💬 채팅</span>
          </div>
          <div class="flex-1 overflow-y-auto p-2">
            <div v-if="lastAction" class="text-gray-400 text-xs mb-1">{{ lastAction }}</div>
            <div v-if="bustMsg" class="text-red-400 text-xs mb-1">{{ bustMsg }}</div>
            <div v-for="(log, i) in tourneyLog.slice(-8)" :key="i" class="text-gray-300 text-[11px] mb-0.5">{{ log }}</div>
            <div v-if="!lastAction && !bustMsg && tourneyLog.length === 0" class="text-gray-700 text-[11px]">게임이 시작되면 여기에 로그가 표시됩니다</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Bottom: 결과 + 액션 버튼 -->
    <div class="shrink-0">
      <div v-if="handResults && gameOver" class="text-center py-1">
        <span :class="handResults.winners[0]?.name === '\uB098' ? 'text-emerald-400' : 'text-red-400'" class="text-sm font-bold">{{ handResults.msg }}</span>
      </div>
      <PokerActions :is-player-turn="isPlayerTurn" :game-over="gameOver" :tourney-over="tourneyOver"
        :can-check="canCheck" :call-amt="callAmt" :current-bet-level="currentBetLevel"
        :player-chips="playerSeat?.chips || 0" :blind-b-b="bl.bb" :raise-amt="raiseAmt"
        @action="doPlayerAction" @update-raise="raiseAmt = $event" @next-hand="nextHand" />
    </div>
  </template>

  <!-- Loading -->
  <div v-else class="min-h-screen flex items-center justify-center">
    <div class="text-gray-300 text-sm">&#47196;&#46377; &#51473;...</div>
  </div>
</div>
</template>

<script setup>
import { computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { usePokerGame } from '@/composables/usePokerGame'
import { usePokerWallet } from '@/composables/usePokerWallet'
import PokerTable from '@/components/poker/PokerTable.vue'
// PokerMonitor now inlined in right panel
import PokerActions from '@/components/poker/PokerActions.vue'
// PokerCoaching now inlined in bottom panel for compact layout

const router = useRouter()
const { saveGame } = usePokerWallet()

const {
  screen, config, blindLevel, totalRemaining, myRank, handNum, tourneyLog, myBounties, chatBubbles,
  seats, dealerIdx, community, pot, stage, actIdx, lastAction, isPlayerTurn, gameOver, showdown,
  raiseAmt, coachTips, showCoach, showMonitor, bustMsg, handResults, tourneyOver, finalPlace,
  currentBetLevel, levelTimer, elapsedTime, foldReveals, termTip,
  bl, nextBl, paidSlots, prizePool, prizes,
  startTournament, doPlayerAction, nextHand, resetTournament, cleanup,
  BLIND_SCHEDULE, fmtTime, fmtElapsed,
} = usePokerGame()

const playerSeat = computed(() => seats.value.find(s => s.isPlayer))
const canCheck = computed(() => currentBetLevel.value <= (playerSeat.value?.bet || 0))
const callAmt = computed(() => Math.max(0, currentBetLevel.value - (playerSeat.value?.bet || 0)))
const inMoney = computed(() => finalPlace.value <= paidSlots.value)

function calcPrize() {
  if (finalPlace.value > paidSlots.value) return 0
  const pool = prizePool.value
  if (finalPlace.value === 1) return Math.floor(pool * 0.25)
  if (finalPlace.value === 2) return Math.floor(pool * 0.16)
  if (finalPlace.value === 3) return Math.floor(pool * 0.11)
  if (finalPlace.value <= 6) return Math.floor(pool * 0.07)
  if (finalPlace.value <= 10) return Math.floor(pool * 0.04)
  return Math.floor(pool * 0.02)
}

function handleRestart() {
  resetTournament()
  const saved = sessionStorage.getItem('pokerConfig')
  if (saved) {
    try { Object.assign(config.value, JSON.parse(saved)) } catch (e) { /* use defaults */ }
  }
  startTournament()
}

function saveResult() {
  if (!tourneyOver.value) return
  const prize = calcPrize()
  const bountyTotal = myBounties.value.length * Math.floor(config.value.buyIn * 0.1)
  saveGame({
    buy_in: config.value.buyIn,
    total_players: config.value.totalPlayers,
    final_place: finalPlace.value,
    prize_won: prize,
    bounties: myBounties.value.length,
    bounty_earnings: bountyTotal,
    hands_played: handNum.value,
    elapsed_seconds: elapsedTime.value,
    blind_level: blindLevel.value,
  }).catch(() => {})
}

let tourneyWatcher = null

onMounted(() => {
  // Read config from sessionStorage (set by PokerLobby)
  const saved = sessionStorage.getItem('pokerConfig')
  if (saved) {
    try { Object.assign(config.value, JSON.parse(saved)) } catch (e) { /* use defaults */ }
  }
  startTournament()

  // Watch for tournament end to save results
  tourneyWatcher = setInterval(() => {
    if (tourneyOver.value) {
      saveResult()
      if (tourneyWatcher) { clearInterval(tourneyWatcher); tourneyWatcher = null }
    }
  }, 500)
})

onUnmounted(() => {
  cleanup()
  if (tourneyWatcher) { clearInterval(tourneyWatcher); tourneyWatcher = null }
})
</script>
