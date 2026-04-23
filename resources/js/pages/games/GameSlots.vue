<template>
<GameShell title="슬롯머신" icon="🎰" :points="coinStr" theme="dark"
  bg="linear-gradient(160deg,#1f2937 0%,#581c87 50%,#831843 100%)">

  <div class="slots-body">
    <!-- 슬롯 머신 케이스 -->
    <div class="machine">
      <div class="marquee">🎰 LUCKY SLOTS 🎰</div>

      <!-- 릴 (3개) -->
      <div class="reels">
        <div v-for="(reel, i) in reels" :key="i" class="reel-wrap">
          <div class="reel" :class="{spinning: spinning[i]}">
            <div class="symbol" :style="{ transform: spinning[i] ? `translateY(-${spinOffset[i]}px)` : 'none' }">
              {{ reel }}
            </div>
          </div>
        </div>
      </div>

      <!-- 상태 메시지 -->
      <div class="status-row">
        <div v-if="lastWin > 0" class="win-banner">🎉 +{{ lastWin.toLocaleString() }} 코인!</div>
        <div v-else-if="spinning.some(s => s)" class="spin-banner">돌리는 중...</div>
        <div v-else-if="lastResult === 'loss'" class="loss-banner">아쉬워요 😢</div>
        <div v-else class="idle-banner">스핀 버튼을 누르세요 ↓</div>
      </div>

      <!-- 베팅 + 스핀 -->
      <div class="controls">
        <div class="bet-row">
          <div class="bet-label">베팅</div>
          <div class="bet-chips">
            <button v-for="b in bets" :key="b"
              class="bet-chip" :class="{active: bet === b}"
              :disabled="anySpinning"
              @click="bet = b">{{ b }}</button>
          </div>
        </div>
        <button class="spin-btn" :disabled="anySpinning || coin < bet" @click="spin">
          <span v-if="anySpinning">🎡</span>
          <span v-else>SPIN!</span>
        </button>
      </div>

      <!-- 페이 테이블 -->
      <details class="paytable">
        <summary>💰 당첨표 보기</summary>
        <div class="pt-grid">
          <div class="pt-row"><span>💎💎💎</span><span>×100</span></div>
          <div class="pt-row"><span>7️⃣7️⃣7️⃣</span><span>×50</span></div>
          <div class="pt-row"><span>⭐⭐⭐</span><span>×20</span></div>
          <div class="pt-row"><span>🔔🔔🔔</span><span>×15</span></div>
          <div class="pt-row"><span>🍒🍒🍒</span><span>×10</span></div>
          <div class="pt-row"><span>🍋🍋🍋</span><span>×8</span></div>
          <div class="pt-row"><span>🍉🍉🍉</span><span>×6</span></div>
          <div class="pt-row"><span>두 개 같음</span><span>×2</span></div>
        </div>
      </details>

      <!-- 잔액 부족 안내 -->
      <div v-if="coin < bet" class="low-coin">
        코인이 부족합니다. 일일 룰렛으로 무료 코인을 받으세요!
      </div>
    </div>
  </div>
</GameShell>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import GameShell from '../../components/GameShell.vue'

const SYMBOLS = ['💎','7️⃣','⭐','🔔','🍒','🍋','🍉']
const PAYOUTS = { '💎':100, '7️⃣':50, '⭐':20, '🔔':15, '🍒':10, '🍋':8, '🍉':6 }

const bets = [10, 50, 100, 500]
const bet = ref(10)
const coin = ref(parseInt(localStorage.getItem('slots_coin') || '1000'))
const coinStr = computed(() => coin.value.toLocaleString())

const reels = ref(['🍒','🍋','🍉'])
const spinning = reactive([false, false, false])
const spinOffset = reactive([0, 0, 0])
const lastWin = ref(0)
const lastResult = ref(null)

const anySpinning = computed(() => spinning.some(s => s))

function randSymbol() { return SYMBOLS[Math.floor(Math.random() * SYMBOLS.length)] }

function playSound(type) {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)()
    if (type === 'spin') {
      const o = ctx.createOscillator(); const g = ctx.createGain()
      o.connect(g); g.connect(ctx.destination); o.type = 'square'
      o.frequency.setValueAtTime(400, ctx.currentTime)
      g.gain.setValueAtTime(0.08, ctx.currentTime)
      g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.08)
      o.start(); o.stop(ctx.currentTime + 0.08)
    } else if (type === 'win') {
      [523,659,784,1047].forEach((f, i) => {
        const o = ctx.createOscillator(); const g = ctx.createGain()
        o.connect(g); g.connect(ctx.destination); o.type = 'triangle'; o.frequency.value = f
        g.gain.setValueAtTime(0, ctx.currentTime + i * 0.1)
        g.gain.linearRampToValueAtTime(0.25, ctx.currentTime + i * 0.1 + 0.03)
        g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + i * 0.1 + 0.2)
        o.start(ctx.currentTime + i * 0.1); o.stop(ctx.currentTime + i * 0.1 + 0.2)
      })
    } else if (type === 'loss') {
      const o = ctx.createOscillator(); const g = ctx.createGain()
      o.connect(g); g.connect(ctx.destination); o.type = 'sine'
      o.frequency.setValueAtTime(300, ctx.currentTime)
      o.frequency.exponentialRampToValueAtTime(100, ctx.currentTime + 0.4)
      g.gain.setValueAtTime(0.2, ctx.currentTime)
      g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4)
      o.start(); o.stop(ctx.currentTime + 0.4)
    }
  } catch {}
}

function calcWin(a, b, c) {
  if (a === b && b === c) return (PAYOUTS[a] || 1) * bet.value
  if (a === b || b === c || a === c) return 2 * bet.value
  return 0
}

function spin() {
  if (anySpinning.value || coin.value < bet.value) return

  coin.value -= bet.value
  lastWin.value = 0
  lastResult.value = null

  // 결과 미리 뽑기
  const targets = [randSymbol(), randSymbol(), randSymbol()]

  // 각 릴 애니메이션
  spinning[0] = spinning[1] = spinning[2] = true
  const durations = [800, 1100, 1400]
  for (let i = 0; i < 3; i++) {
    const start = Date.now()
    const iv = setInterval(() => {
      reels.value[i] = randSymbol()
      playSound('spin')
      if (Date.now() - start >= durations[i]) {
        clearInterval(iv)
        reels.value[i] = targets[i]
        spinning[i] = false
        if (i === 2) settle(targets)
      }
    }, 80)
  }
}

function settle(targets) {
  const win = calcWin(...targets)
  if (win > 0) {
    coin.value += win
    lastWin.value = win
    lastResult.value = 'win'
    playSound('win')
  } else {
    lastResult.value = 'loss'
    playSound('loss')
  }
  localStorage.setItem('slots_coin', coin.value)
}
</script>

<style scoped>
.slots-body { padding: 20px; display: flex; justify-content: center; }
.machine { background: linear-gradient(160deg, #fbbf24, #d97706); border: 4px solid #92400e; border-radius: 24px; padding: 24px; max-width: 420px; width: 100%; box-shadow: 0 12px 48px rgba(0,0,0,0.5), inset 0 2px 4px rgba(255,255,255,0.3); }

.marquee { text-align: center; font-size: 18px; font-weight: 900; color: #fbbf24; background: #1f2937; padding: 10px; border-radius: 14px; margin-bottom: 16px; letter-spacing: 2px; box-shadow: inset 0 2px 6px rgba(0,0,0,0.5); border: 2px solid #b45309; }

.reels { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; background: #1f2937; padding: 14px; border-radius: 16px; margin-bottom: 14px; box-shadow: inset 0 4px 12px rgba(0,0,0,0.6); }
.reel-wrap { background: #fff; border-radius: 10px; height: 90px; display: flex; align-items: center; justify-content: center; overflow: hidden; border: 2px solid #92400e; }
.reel { width: 100%; text-align: center; }
.symbol { font-size: 60px; line-height: 1; transition: transform 0.05s linear; }
.reel.spinning .symbol { animation: vibrate 0.1s infinite; }
@keyframes vibrate { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-4px); } }

.status-row { text-align: center; min-height: 32px; margin-bottom: 14px; }
.win-banner { background: #10b981; color: #fff; padding: 8px 20px; border-radius: 20px; font-size: 16px; font-weight: 900; display: inline-block; box-shadow: 0 4px 16px rgba(16,185,129,0.4); animation: pulse 0.6s; }
@keyframes pulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.05); } }
.spin-banner, .idle-banner { color: #fef3c7; font-size: 13px; font-weight: 700; padding: 6px; }
.loss-banner { background: #6b7280; color: #fff; padding: 6px 16px; border-radius: 16px; font-size: 13px; font-weight: 700; display: inline-block; }

.controls { display: flex; flex-direction: column; gap: 12px; }
.bet-row { background: rgba(31, 41, 55, 0.5); border-radius: 12px; padding: 8px 12px; }
.bet-label { font-size: 11px; color: #fef3c7; font-weight: 700; margin-bottom: 4px; }
.bet-chips { display: grid; grid-template-columns: repeat(4, 1fr); gap: 4px; }
.bet-chip { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: #fef3c7; padding: 6px 4px; border-radius: 8px; font-size: 11px; font-weight: 800; cursor: pointer; transition: all 0.15s; }
.bet-chip:hover:not(:disabled) { background: rgba(255,255,255,0.2); }
.bet-chip.active { background: #fbbf24; color: #78350f; border-color: #fbbf24; }
.bet-chip:disabled { opacity: 0.5; cursor: not-allowed; }

.spin-btn { background: linear-gradient(135deg, #dc2626, #991b1b); color: #fff; border: 3px solid #fbbf24; padding: 16px; border-radius: 14px; font-size: 24px; font-weight: 900; letter-spacing: 2px; cursor: pointer; box-shadow: 0 6px 20px rgba(220,38,38,0.5); transition: all 0.15s; }
.spin-btn:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(220,38,38,0.6); }
.spin-btn:active { transform: translateY(0); }
.spin-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.paytable { margin-top: 14px; background: rgba(31,41,55,0.5); border-radius: 10px; padding: 10px 14px; }
.paytable summary { cursor: pointer; color: #fef3c7; font-size: 12px; font-weight: 700; }
.pt-grid { display: flex; flex-direction: column; gap: 3px; margin-top: 8px; }
.pt-row { display: flex; justify-content: space-between; font-size: 12px; color: #fde68a; padding: 3px 0; border-bottom: 1px dashed rgba(254,243,199,0.1); }
.pt-row span:last-child { font-weight: 800; color: #fbbf24; }

.low-coin { text-align: center; font-size: 12px; color: #fca5a5; margin-top: 10px; }
</style>
