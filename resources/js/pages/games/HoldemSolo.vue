<template>
  <div class="select-none" style="position:fixed;inset:0;overflow-y:auto;background:radial-gradient(ellipse at center,#1a5c2e 0%,#0e3d1a 55%,#071a0b 100%);font-family:'Malgun Gothic',sans-serif;">

    <!-- ══ TOP BAR ══ -->
    <div class="flex items-center px-3 py-2 gap-2 flex-shrink-0 sticky top-0 z-20"
      style="background:rgba(0,0,0,.75);border-bottom:1px solid rgba(255,255,255,.08);">
      <button @click="$router.back()" class="text-white/50 hover:text-white text-base w-8 flex-shrink-0">◀</button>
      <span class="text-yellow-300 font-black tracking-wider text-sm">Texas Hold'em</span>
      <span class="text-white/30 text-xs">솔로</span>

      <!-- Pot -->
      <div class="mx-auto flex items-center gap-1 px-3 py-0.5 rounded-full text-xs font-bold"
        style="background:rgba(0,0,0,.5);border:1px solid rgba(255,215,0,.3);">
        <span class="text-yellow-400">팟</span>
        <span class="text-white">{{ pot.toLocaleString() }}</span>
      </div>

      <!-- Phase badge -->
      <span class="text-xs px-2 py-0.5 rounded-full font-bold" :style="phaseBadgeStyle">{{ phaseLabel }}</span>
    </div>

    <!-- ══ GAME AREA ══ -->
    <div class="flex flex-col items-center px-3 py-3 gap-3" style="min-height:calc(100dvh - 48px);">

      <!-- ── AI AREA ── -->
      <div class="w-full max-w-md rounded-2xl p-3" style="background:rgba(0,0,0,.45);border:1px solid rgba(239,68,68,.25);">
        <div class="flex items-center justify-between mb-2">
          <div class="flex items-center gap-2">
            <span class="text-base">🤖</span>
            <span class="text-red-300 text-sm font-bold">AI 봇</span>
            <span v-if="aiThinking" class="text-xs text-yellow-300 animate-pulse">생각 중...</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="text-white/50 text-xs">칩</span>
            <span class="text-yellow-300 font-black text-sm">{{ aiChips.toLocaleString() }}</span>
          </div>
        </div>
        <!-- AI cards -->
        <div class="flex gap-2 justify-center mb-2">
          <div v-for="(card, i) in aiHole" :key="'ai'+i">
            <div v-if="phase !== 'showdown' && phase !== 'idle'" class="card card-back">🂠</div>
            <CardTile v-else :card="card" />
          </div>
          <div v-if="aiHole.length === 0" class="text-white/20 text-xs self-center">대기 중</div>
        </div>
        <!-- AI bet -->
        <div v-if="aiRoundBet > 0" class="text-center text-xs text-red-300">
          베팅: <span class="font-bold text-white">{{ aiRoundBet.toLocaleString() }}</span>
        </div>
        <!-- AI hand name at showdown -->
        <div v-if="phase === 'showdown' && aiHandName" class="text-center text-xs mt-1 text-yellow-300 font-bold">{{ aiHandName }}</div>
      </div>

      <!-- ── COMMUNITY CARDS ── -->
      <div class="w-full max-w-md rounded-2xl p-3" style="background:rgba(0,0,0,.35);border:1px solid rgba(255,255,255,.1);">
        <div class="text-center text-white/40 text-xs mb-2 tracking-widest uppercase">커뮤니티 카드</div>
        <div class="flex gap-2 justify-center flex-wrap">
          <CardTile v-for="(card, i) in community" :key="'com'+i" :card="card" />
          <!-- Placeholders -->
          <div v-for="i in (5 - community.length)" :key="'ph'+i"
            class="rounded-lg flex items-center justify-center text-white/10 text-xs"
            style="width:44px;height:64px;border:1px dashed rgba(255,255,255,.12);background:rgba(0,0,0,.2);">?</div>
        </div>
      </div>

      <!-- ── HAND STRENGTH METER ── -->
      <div v-if="phase !== 'idle' && phase !== 'showdown' && myHole.length === 2" class="w-full max-w-md">
        <div class="flex items-center gap-2 px-1">
          <span class="text-white/40 text-xs w-16">핸드 강도</span>
          <div class="flex-1 rounded-full h-2" style="background:rgba(0,0,0,.4);">
            <div class="h-2 rounded-full transition-all duration-500"
              :style="{ width: (playerStrength * 100) + '%', background: strengthColor }"></div>
          </div>
          <span class="text-xs font-bold w-8 text-right" :style="{ color: strengthColor }">{{ Math.round(playerStrength * 100) }}%</span>
        </div>
      </div>

      <!-- ── PLAYER AREA ── -->
      <div class="w-full max-w-md rounded-2xl p-3" style="background:rgba(0,0,0,.45);border:1px solid rgba(34,197,94,.25);">
        <div class="flex items-center justify-between mb-2">
          <div class="flex items-center gap-2">
            <span class="text-base">🙋</span>
            <span class="text-green-300 text-sm font-bold">나 (딜러)</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="text-white/50 text-xs">칩</span>
            <span class="text-yellow-300 font-black text-sm">{{ myChips.toLocaleString() }}</span>
          </div>
        </div>
        <!-- My cards -->
        <div class="flex gap-2 justify-center mb-2">
          <CardTile v-for="(card, i) in myHole" :key="'me'+i" :card="card" />
          <div v-if="myHole.length === 0" class="text-white/20 text-xs self-center">대기 중</div>
        </div>
        <!-- My bet -->
        <div v-if="myRoundBet > 0" class="text-center text-xs text-green-300">
          베팅: <span class="font-bold text-white">{{ myRoundBet.toLocaleString() }}</span>
        </div>
        <!-- My hand name -->
        <div v-if="phase === 'showdown' && myHandName" class="text-center text-xs mt-1 text-yellow-300 font-bold">{{ myHandName }}</div>
      </div>

      <!-- ── ACTION BUTTONS ── -->
      <div v-if="phase !== 'idle' && phase !== 'showdown'" class="w-full max-w-md">
        <div v-if="myTurn" class="flex gap-2">
          <!-- Fold -->
          <button @click="doFold"
            class="flex-1 py-3 rounded-xl font-bold text-sm transition-all active:scale-95"
            style="background:rgba(239,68,68,.2);border:1px solid rgba(239,68,68,.5);color:#fca5a5;">
            폴드
          </button>
          <!-- Check / Call -->
          <button @click="doCall"
            class="flex-1 py-3 rounded-xl font-bold text-sm transition-all active:scale-95"
            :style="toCall === 0
              ? 'background:rgba(59,130,246,.2);border:1px solid rgba(59,130,246,.5);color:#93c5fd;'
              : 'background:rgba(234,179,8,.2);border:1px solid rgba(234,179,8,.5);color:#fde68a;'">
            {{ toCall === 0 ? '체크' : `콜 (${toCall.toLocaleString()})` }}
          </button>
          <!-- Raise -->
          <button @click="showRaisePanel = !showRaisePanel" :disabled="myChips <= toCall"
            class="flex-1 py-3 rounded-xl font-bold text-sm transition-all active:scale-95"
            :style="myChips <= toCall
              ? 'background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:rgba(255,255,255,.2);cursor:not-allowed;'
              : 'background:rgba(34,197,94,.2);border:1px solid rgba(34,197,94,.5);color:#86efac;'">
            레이즈
          </button>
        </div>
        <!-- Raise panel -->
        <div v-if="showRaisePanel && myTurn" class="mt-2 rounded-xl p-3" style="background:rgba(0,0,0,.6);border:1px solid rgba(255,255,255,.1);">
          <div class="text-white/60 text-xs mb-2">레이즈 금액: <span class="text-yellow-300 font-bold">{{ raiseAmount.toLocaleString() }}</span></div>
          <input type="range" v-model="raiseAmount"
            :min="Math.max(bigBlind * 2, toCall * 2)"
            :max="myChips"
            :step="bigBlind"
            class="w-full accent-green-400 mb-2" />
          <div class="flex gap-2">
            <button @click="raiseAmount = Math.max(bigBlind*2, toCall*2)"
              class="flex-1 py-1.5 rounded text-xs font-bold" style="background:rgba(255,255,255,.1);color:#fff;">Min</button>
            <button @click="raiseAmount = Math.round((myChips + toCall) / 2)"
              class="flex-1 py-1.5 rounded text-xs font-bold" style="background:rgba(255,255,255,.1);color:#fff;">1/2</button>
            <button @click="raiseAmount = myChips"
              class="flex-1 py-1.5 rounded text-xs font-bold" style="background:rgba(255,255,255,.1);color:#fff;">올인</button>
            <button @click="doRaise"
              class="flex-1 py-1.5 rounded text-xs font-bold" style="background:rgba(34,197,94,.4);color:#86efac;border:1px solid rgba(34,197,94,.6);">확인</button>
          </div>
        </div>
        <!-- Waiting for AI -->
        <div v-if="!myTurn && !aiThinking" class="text-center text-white/40 text-xs py-3">AI 차례...</div>
      </div>

      <!-- ── IDLE (START) ── -->
      <div v-if="phase === 'idle'" class="w-full max-w-md flex flex-col items-center gap-4 py-6">
        <div class="text-white/60 text-center text-sm">스타팅 칩: 각 1,500</div>
        <div class="text-white/40 text-xs text-center">스몰 블라인드: 20 / 빅 블라인드: 40<br/>당신은 항상 딜러/스몰 블라인드입니다</div>
        <button @click="startHand"
          class="px-8 py-3 rounded-2xl font-black text-lg transition-all active:scale-95"
          style="background:linear-gradient(135deg,#16a34a,#15803d);color:#fff;box-shadow:0 4px 20px rgba(22,163,74,.4);">
          게임 시작
        </button>
      </div>

      <!-- ── SHOWDOWN OVERLAY ── -->
      <div v-if="phase === 'showdown'" class="w-full max-w-md">
        <div class="rounded-2xl p-5 text-center" :style="resultBannerStyle">
          <div class="text-4xl mb-2">{{ resultEmoji }}</div>
          <div class="text-2xl font-black text-white mb-1">{{ resultText }}</div>
          <div v-if="chipDelta !== 0" class="text-lg font-bold" :class="chipDelta > 0 ? 'text-green-300' : 'text-red-300'">
            {{ chipDelta > 0 ? '+' : '' }}{{ chipDelta.toLocaleString() }}
          </div>
        </div>
        <div class="flex gap-2 mt-3">
          <button @click="startHand" :disabled="myChips === 0 || aiChips === 0"
            class="flex-1 py-3 rounded-xl font-bold text-sm transition-all active:scale-95"
            style="background:linear-gradient(135deg,#16a34a,#15803d);color:#fff;">
            다음 판
          </button>
          <button @click="resetGame"
            class="flex-1 py-3 rounded-xl font-bold text-sm transition-all active:scale-95"
            style="background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);color:#fff;">
            리셋
          </button>
        </div>
      </div>

      <!-- ── GAME OVER ── -->
      <div v-if="phase === 'showdown' && (myChips === 0 || aiChips === 0)" class="w-full max-w-md rounded-2xl p-5 text-center mt-2"
        style="background:rgba(0,0,0,.7);border:1px solid rgba(255,255,255,.15);">
        <div class="text-5xl mb-2">{{ myChips === 0 ? '💸' : '🏆' }}</div>
        <div class="text-xl font-black text-white mb-1">{{ myChips === 0 ? '게임 오버' : '승리!' }}</div>
        <div class="text-white/60 text-sm mb-3">{{ myChips === 0 ? 'AI 봇에게 패배했습니다' : '모든 칩을 획득했습니다!' }}</div>
        <button @click="resetGame"
          class="px-6 py-2 rounded-xl font-bold text-sm"
          style="background:linear-gradient(135deg,#d97706,#b45309);color:#fff;">
          처음부터
        </button>
      </div>

    </div><!-- /game area -->
  </div>
</template>

<script setup>
import { ref, computed, watch, defineComponent, h } from 'vue'

// ══════════════════════════════════════════
//  CARD TILE COMPONENT (inline)
// ══════════════════════════════════════════
const CardTile = defineComponent({
  props: { card: Object },
  setup(props) {
    return () => {
      const c = props.card
      if (!c) return h('div', { style: 'width:44px;height:64px' })
      const red = c.suit === '♥' || c.suit === '♦'
      return h('div', {
        style: `
          width:44px;height:64px;border-radius:8px;
          background:#fff;border:1.5px solid #d1d5db;
          display:flex;flex-direction:column;
          align-items:center;justify-content:center;
          font-weight:900;font-size:13px;line-height:1.1;
          color:${red ? '#dc2626' : '#111827'};
          box-shadow:0 2px 6px rgba(0,0,0,.4);
          flex-shrink:0;
        `
      }, [
        h('div', { style: 'font-size:12px;line-height:1' }, c.rank),
        h('div', { style: 'font-size:16px;line-height:1' }, c.suit),
      ])
    }
  }
})

// ══════════════════════════════════════════
//  DECK
// ══════════════════════════════════════════
const SUITS = ['♠', '♥', '♦', '♣']
const RANKS = ['2','3','4','5','6','7','8','9','10','J','Q','K','A']
const RANK_VAL = { '2':2,'3':3,'4':4,'5':5,'6':6,'7':7,'8':8,'9':9,'10':10,'J':11,'Q':12,'K':13,'A':14 }

function makeDeck() {
  const deck = []
  for (const suit of SUITS)
    for (const rank of RANKS)
      deck.push({ rank, suit, val: RANK_VAL[rank] })
  return deck
}

function shuffle(arr) {
  const a = [...arr]
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [a[i], a[j]] = [a[j], a[i]]
  }
  return a
}

// ══════════════════════════════════════════
//  HAND EVALUATOR
// ══════════════════════════════════════════
function eval5(cards) {
  // returns [handRank, ...kickers] – higher is better
  const vals = cards.map(c => c.val).sort((a,b) => b - a)
  const suits = cards.map(c => c.suit)
  const flush = suits.every(s => s === suits[0])

  // check straight
  const uniqueVals = [...new Set(vals)].sort((a,b) => b - a)
  let straight = false
  let straightHigh = 0
  if (uniqueVals.length >= 5) {
    for (let i = 0; i <= uniqueVals.length - 5; i++) {
      if (uniqueVals[i] - uniqueVals[i+4] === 4) {
        straight = true
        straightHigh = uniqueVals[i]
        break
      }
    }
  }
  // Ace-low straight A2345
  if (!straight && vals.includes(14) && vals.includes(2) && vals.includes(3) && vals.includes(4) && vals.includes(5)) {
    straight = true
    straightHigh = 5
  }

  // count frequencies
  const freq = {}
  for (const v of vals) freq[v] = (freq[v] || 0) + 1
  const counts = Object.entries(freq).map(([v,c]) => [Number(v), c]).sort((a,b) => b[1]-a[1] || b[0]-a[0])

  const c0 = counts[0][1], c1 = counts.length > 1 ? counts[1][1] : 0

  if (flush && straight) {
    if (straightHigh === 14 && vals[4] === 10) return [8, 14]      // Royal Flush
    return [7, straightHigh]                                          // Straight Flush
  }
  if (c0 === 4) return [6, counts[0][0], counts[1][0]]              // Four of a Kind
  if (c0 === 3 && c1 === 2) return [5, counts[0][0], counts[1][0]] // Full House
  if (flush) return [4, ...vals]                                     // Flush
  if (straight) return [3, straightHigh]                             // Straight
  if (c0 === 3) return [2, counts[0][0], counts[1][0], counts[2][0]] // Three of a Kind
  if (c0 === 2 && c1 === 2) return [1, counts[0][0], counts[1][0], counts[2][0]] // Two Pair
  if (c0 === 2) return [0.5, counts[0][0], ...counts.slice(1).map(x=>x[0])]      // One Pair
  return [0, ...vals]                                                 // High Card
}

function combos5(cards) {
  const result = []
  for (let a=0;a<cards.length-4;a++)
    for (let b=a+1;b<cards.length-3;b++)
      for (let c=b+1;c<cards.length-2;c++)
        for (let d=c+1;d<cards.length-1;d++)
          for (let e=d+1;e<cards.length;e++)
            result.push([cards[a],cards[b],cards[c],cards[d],cards[e]])
  return result
}

function compareScore(a, b) {
  for (let i = 0; i < Math.max(a.length, b.length); i++) {
    const av = a[i] ?? 0, bv = b[i] ?? 0
    if (av > bv) return 1
    if (av < bv) return -1
  }
  return 0
}

function bestHand7(cards) {
  let best = null
  for (const combo of combos5(cards)) {
    const score = eval5(combo)
    if (!best || compareScore(score, best) > 0) best = score
  }
  return best
}

function compareHands(a, b) { return compareScore(a, b) }

function handName(score) {
  const rank = score[0]
  if (rank === 8) return '로열 플러시'
  if (rank === 7) return '스트레이트 플러시'
  if (rank === 6) return '포 오브 어 카인드'
  if (rank === 5) return '풀 하우스'
  if (rank === 4) return '플러시'
  if (rank === 3) return '스트레이트'
  if (rank === 2) return '쓰리 오브 어 카인드'
  if (rank === 1) return '투 페어'
  if (rank === 0.5) return '원 페어'
  return '하이 카드'
}

function preflopStrength(hole) {
  const [a, b] = hole.map(c => c.val).sort((x,y) => y - x)
  const suited = hole[0].suit === hole[1].suit
  if (a === b) {
    if (a >= 14) return 0.92
    if (a >= 13) return 0.85
    if (a >= 11) return 0.80
    if (a >= 9)  return 0.70
    if (a >= 7)  return 0.58
    return 0.45
  }
  const gap = a - b
  let base = 0
  if (a === 14 && b >= 13) base = 0.65
  else if (a === 14 && b >= 11) base = 0.55
  else if (a === 14) base = 0.35
  else if (a >= 13 && b >= 12) base = 0.52
  else if (a >= 12 && b >= 11) base = 0.45
  else if (a >= 11) base = 0.38
  else if (a >= 9) base = 0.28
  else base = 0.15
  if (suited) base += 0.04
  if (gap <= 1) base += 0.03
  return Math.min(base, 0.95)
}

function handStrength(hole, community) {
  if (community.length === 0) return preflopStrength(hole)
  const all = [...hole, ...community]
  const score = bestHand7(all)
  const rank = score[0]
  if (rank === 8) return 0.99
  if (rank === 7) return 0.97
  if (rank === 6) return 0.95
  if (rank === 5) return 0.88
  if (rank === 4) return 0.80
  if (rank === 3) return 0.70
  if (rank === 2) return 0.58
  if (rank === 1) return 0.45
  if (rank === 0.5) return 0.30
  // High card – scale by top card
  return 0.10 + (score[1] - 2) / 12 * 0.15
}

// ══════════════════════════════════════════
//  AI DECISION
// ══════════════════════════════════════════
function aiDecide(strength, toCall, potSize, chips, bigBlind) {
  if (strength > 0.75) {
    const raise = Math.min(Math.max(toCall * 2, bigBlind * 2), chips)
    return { action: 'raise', amount: raise }
  }
  if (strength > 0.5) {
    if (toCall <= potSize * 0.5 && toCall <= chips) return { action: 'call' }
    if (toCall === 0) return { action: 'check' }
    return { action: 'fold' }
  }
  if (strength > 0.3) {
    if (toCall <= bigBlind && toCall <= chips) return { action: 'call' }
    if (toCall === 0) return { action: 'check' }
    return { action: 'fold' }
  }
  if (toCall === 0) return { action: 'check' }
  return { action: 'fold' }
}

// ══════════════════════════════════════════
//  AUDIO
// ══════════════════════════════════════════
let audioCtx = null
function getAudioCtx() {
  if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)()
  return audioCtx
}

function playDeal() {
  try {
    const ctx = getAudioCtx()
    const bufferSize = ctx.sampleRate * 0.08
    const buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate)
    const data = buffer.getChannelData(0)
    for (let i = 0; i < bufferSize; i++) data[i] = (Math.random() * 2 - 1) * (1 - i / bufferSize) * 0.4
    const src = ctx.createBufferSource()
    src.buffer = buffer
    const g = ctx.createGain(); g.gain.value = 0.5
    src.connect(g); g.connect(ctx.destination)
    src.start()
  } catch(e) {}
}

function playChip() {
  try {
    const ctx = getAudioCtx()
    const osc = ctx.createOscillator()
    const g = ctx.createGain()
    osc.type = 'triangle'
    osc.frequency.setValueAtTime(800, ctx.currentTime)
    osc.frequency.exponentialRampToValueAtTime(400, ctx.currentTime + 0.05)
    g.gain.setValueAtTime(0.3, ctx.currentTime)
    g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.08)
    osc.connect(g); g.connect(ctx.destination)
    osc.start(); osc.stop(ctx.currentTime + 0.1)
  } catch(e) {}
}

function playWin() {
  try {
    const ctx = getAudioCtx()
    const notes = [523, 659, 784, 1047]
    notes.forEach((freq, i) => {
      const osc = ctx.createOscillator()
      const g = ctx.createGain()
      osc.type = 'sine'
      osc.frequency.value = freq
      g.gain.setValueAtTime(0, ctx.currentTime + i * 0.12)
      g.gain.linearRampToValueAtTime(0.3, ctx.currentTime + i * 0.12 + 0.05)
      g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + i * 0.12 + 0.2)
      osc.connect(g); g.connect(ctx.destination)
      osc.start(ctx.currentTime + i * 0.12)
      osc.stop(ctx.currentTime + i * 0.12 + 0.25)
    })
  } catch(e) {}
}

function playFold() {
  try {
    const ctx = getAudioCtx()
    const notes = [400, 300, 200]
    notes.forEach((freq, i) => {
      const osc = ctx.createOscillator()
      const g = ctx.createGain()
      osc.type = 'sawtooth'
      osc.frequency.value = freq
      g.gain.setValueAtTime(0.15, ctx.currentTime + i * 0.1)
      g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + i * 0.1 + 0.15)
      osc.connect(g); g.connect(ctx.destination)
      osc.start(ctx.currentTime + i * 0.1)
      osc.stop(ctx.currentTime + i * 0.1 + 0.2)
    })
  } catch(e) {}
}

// ══════════════════════════════════════════
//  GAME STATE
// ══════════════════════════════════════════
const smallBlind = 20
const bigBlind = 40

const phase = ref('idle')          // idle | preflop | flop | turn | river | showdown
const deck = ref([])
const myHole = ref([])
const aiHole = ref([])
const community = ref([])

const myChips = ref(1500)
const aiChips = ref(1500)
const pot = ref(0)

const myRoundBet = ref(0)
const aiRoundBet = ref(0)
const currentBet = ref(0)         // amount the next player must match

const myTurn = ref(false)
const aiThinking = ref(false)
const playerActedThisRound = ref(false)  // 이번 베팅 라운드에 플레이어가 행동했는지

const showRaisePanel = ref(false)
const raiseAmount = ref(bigBlind * 2)

const resultText = ref('')
const resultEmoji = ref('')
const chipDelta = ref(0)
const myHandName = ref('')
const aiHandName = ref('')

// ── Computed ──
const phaseLabel = computed(() => {
  const map = {
    idle: '대기', preflop: '프리플랍', flop: '플랍',
    turn: '턴', river: '리버', showdown: '쇼다운'
  }
  return map[phase.value] || ''
})

const phaseBadgeStyle = computed(() => {
  if (phase.value === 'idle' || phase.value === 'showdown') return 'background:rgba(255,255,255,.1);color:rgba(255,255,255,.5);'
  return myTurn.value
    ? 'background:rgba(34,197,94,.3);color:#86efac;'
    : 'background:rgba(239,68,68,.3);color:#fca5a5;'
})

const toCall = computed(() => Math.max(0, currentBet.value - myRoundBet.value))

const playerStrength = computed(() => {
  if (myHole.value.length < 2) return 0
  return handStrength(myHole.value, community.value)
})

const strengthColor = computed(() => {
  const s = playerStrength.value
  if (s > 0.7) return '#4ade80'
  if (s > 0.4) return '#facc15'
  return '#f87171'
})

const resultBannerStyle = computed(() => {
  if (chipDelta.value > 0) return 'background:linear-gradient(135deg,rgba(21,128,61,.8),rgba(22,163,74,.6));border:1px solid rgba(34,197,94,.4);'
  if (chipDelta.value < 0) return 'background:linear-gradient(135deg,rgba(153,27,27,.8),rgba(220,38,38,.6));border:1px solid rgba(239,68,68,.4);'
  return 'background:rgba(0,0,0,.5);border:1px solid rgba(255,255,255,.15);'
})

// ══════════════════════════════════════════
//  GAME FLOW
// ══════════════════════════════════════════
function resetGame() {
  myChips.value = 1500
  aiChips.value = 1500
  pot.value = 0
  myHole.value = []
  aiHole.value = []
  community.value = []
  myRoundBet.value = 0
  aiRoundBet.value = 0
  currentBet.value = 0
  myTurn.value = false
  aiThinking.value = false
  showRaisePanel.value = false
  resultText.value = ''
  chipDelta.value = 0
  myHandName.value = ''
  aiHandName.value = ''
  phase.value = 'idle'
}

function startHand() {
  if (myChips.value === 0 || aiChips.value === 0) { resetGame(); return }

  // Reset round state
  myHole.value = []
  aiHole.value = []
  community.value = []
  myRoundBet.value = 0
  aiRoundBet.value = 0
  currentBet.value = 0
  pot.value = 0
  showRaisePanel.value = false
  resultText.value = ''
  myHandName.value = ''
  aiHandName.value = ''
  chipDelta.value = 0
  aiThinking.value = false

  // Shuffle
  deck.value = shuffle(makeDeck())

  // Deal hole cards
  myHole.value = [deck.value.pop(), deck.value.pop()]
  aiHole.value = [deck.value.pop(), deck.value.pop()]
  playDeal()

  // Post blinds
  // Player = small blind (20), AI = big blind (40)
  const sb = Math.min(smallBlind, myChips.value)
  const bb = Math.min(bigBlind, aiChips.value)
  myChips.value -= sb
  aiChips.value -= bb
  pot.value = sb + bb
  myRoundBet.value = sb
  aiRoundBet.value = bb
  currentBet.value = bb  // player must call to bb
  playChip()

  phase.value = 'preflop'
  playerActedThisRound.value = false
  myTurn.value = true  // Player acts first preflop
}

function placeBet(who, amount) {
  // who: 'my' | 'ai'
  if (who === 'my') {
    const actual = Math.min(amount, myChips.value)
    myChips.value -= actual
    myRoundBet.value += actual
    pot.value += actual
    if (myRoundBet.value > currentBet.value) currentBet.value = myRoundBet.value
  } else {
    const actual = Math.min(amount, aiChips.value)
    aiChips.value -= actual
    aiRoundBet.value += actual
    pot.value += actual
    if (aiRoundBet.value > currentBet.value) currentBet.value = aiRoundBet.value
  }
  playChip()
}

function doFold() {
  showRaisePanel.value = false
  playFold()
  // AI wins pot
  aiChips.value += pot.value
  chipDelta.value = -(myRoundBet.value)
  resultText.value = 'AI 승리'
  resultEmoji.value = '🤖'
  myHandName.value = '폴드'
  aiHandName.value = ''
  phase.value = 'showdown'
}

function doCall() {
  showRaisePanel.value = false
  playerActedThisRound.value = true
  if (toCall.value === 0) {
    // 체크
    myTurn.value = false
    if (phase.value === 'preflop') {
      triggerAiTurn()  // preflop: AI responds to player check
    } else {
      // post-flop: player checked after AI checked → phase advance
      advancePhase()
    }
  } else {
    placeBet('my', toCall.value)
    myTurn.value = false
    if (phase.value === 'preflop') {
      triggerAiTurn()  // preflop: AI big blind gets option
    } else {
      // post-flop: player called AI's bet → phase advance
      advancePhase()
    }
  }
}

function doRaise() {
  showRaisePanel.value = false
  playerActedThisRound.value = true
  const extra = raiseAmount.value - myRoundBet.value
  placeBet('my', Math.max(0, extra))
  myTurn.value = false
  triggerAiTurn()
}

function triggerAiTurn() {
  aiThinking.value = true
  setTimeout(() => {
    aiThinking.value = false
    const strength = handStrength(aiHole.value, community.value)
    const aiToCall = Math.max(0, currentBet.value - aiRoundBet.value)
    const decision = aiDecide(strength, aiToCall, pot.value, aiChips.value, bigBlind)

    if (decision.action === 'fold') {
      playFold()
      myChips.value += pot.value
      chipDelta.value = pot.value - myRoundBet.value
      resultText.value = '내 승리!'
      resultEmoji.value = '🏆'
      aiHandName.value = '폴드'
      myHandName.value = ''
      phase.value = 'showdown'
      playWin()
      return
    }

    if (decision.action === 'raise') {
      const extra = decision.amount - aiRoundBet.value
      placeBet('ai', Math.max(0, extra))
      // Now player must respond
      myTurn.value = true
      // Update raise amount suggestion
      raiseAmount.value = Math.max(bigBlind * 2, toCall.value * 2)
      return
    }

    if (decision.action === 'call') {
      placeBet('ai', aiToCall)
      advancePhase()
      return
    }

    // AI 체크: 플레이어가 아직 행동 안 했으면 플레이어 차례, 했으면 phase advance
    if (!playerActedThisRound.value) {
      // AI가 먼저 체크 → 플레이어 차례 (체크 or 베팅 가능)
      myTurn.value = true
    } else {
      // 플레이어가 체크한 후 AI도 체크 → 라운드 종료
      advancePhase()
    }
  }, 300 + Math.random() * 400)
}

function advancePhase() {
  // Reset round bets
  myRoundBet.value = 0
  aiRoundBet.value = 0
  currentBet.value = 0
  raiseAmount.value = bigBlind * 2
  playerActedThisRound.value = false

  if (phase.value === 'preflop') {
    // Deal flop
    deck.value.pop() // burn
    community.value = [deck.value.pop(), deck.value.pop(), deck.value.pop()]
    playDeal()
    phase.value = 'flop'
    // Post-flop: AI acts first
    triggerAiTurn()
  } else if (phase.value === 'flop') {
    deck.value.pop() // burn
    community.value.push(deck.value.pop())
    playDeal()
    phase.value = 'turn'
    triggerAiTurn()
  } else if (phase.value === 'turn') {
    deck.value.pop() // burn
    community.value.push(deck.value.pop())
    playDeal()
    phase.value = 'river'
    triggerAiTurn()
  } else if (phase.value === 'river') {
    doShowdown()
  }
}

function doShowdown() {
  phase.value = 'showdown'
  const myCards = [...myHole.value, ...community.value]
  const aiCards = [...aiHole.value, ...community.value]
  const myScore = bestHand7(myCards)
  const aiScore = bestHand7(aiCards)
  myHandName.value = handName(myScore)
  aiHandName.value = handName(aiScore)

  const cmp = compareHands(myScore, aiScore)
  if (cmp > 0) {
    // Player wins
    myChips.value += pot.value
    chipDelta.value = pot.value - myRoundBet.value
    resultText.value = '내 승리!'
    resultEmoji.value = '🏆'
    playWin()
  } else if (cmp < 0) {
    // AI wins
    aiChips.value += pot.value
    chipDelta.value = -myRoundBet.value
    resultText.value = 'AI 승리'
    resultEmoji.value = '🤖'
    playFold()
  } else {
    // Split
    const half = Math.floor(pot.value / 2)
    myChips.value += half
    aiChips.value += pot.value - half
    chipDelta.value = 0
    resultText.value = '무승부'
    resultEmoji.value = '🤝'
  }
  pot.value = 0
}

// When AI raises post-flop and player must respond, myTurn is set.
// We watch myTurn to also set raiseAmount properly.
watch(myTurn, (v) => {
  if (v) {
    const min = Math.max(bigBlind * 2, toCall.value * 2)
    raiseAmount.value = Math.min(Math.max(min, bigBlind * 2), myChips.value)
  }
})
</script>

<style scoped>
.card-back {
  width: 44px;
  height: 64px;
  border-radius: 8px;
  background: linear-gradient(135deg, #1e3a8a, #1e40af);
  border: 1.5px solid #3b82f6;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
  box-shadow: 0 2px 6px rgba(0,0,0,.4);
  flex-shrink: 0;
}
</style>
