<template>
  <div class="min-h-screen bg-emerald-900 pb-24 select-none">
    <!-- Header -->
    <div class="bg-black/40 px-4 py-3 flex items-center gap-3">
      <button @click="$router.back()" class="text-white/70 hover:text-white">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
      </button>
      <h1 class="text-white font-bold">블랙잭 (vs 딜러)</h1>
      <div class="ml-auto text-sm text-yellow-300 font-bold">💰 {{ chips.toLocaleString() }}P</div>
    </div>

    <!-- 게임 결과 배너 -->
    <div v-if="result" class="mx-4 mt-4 rounded-2xl p-5 text-center shadow-xl"
      :class="{
        'bg-yellow-400': result==='blackjack',
        'bg-green-500': result==='win',
        'bg-gray-500': result==='push',
        'bg-red-500': result==='lose',
        'bg-red-700': result==='bust'
      }">
      <div class="text-4xl mb-1">{{ resultEmoji }}</div>
      <div class="text-2xl font-black text-white">{{ resultText }}</div>
      <div class="text-white/80 text-sm mt-1">{{ resultDetail }}</div>
    </div>

    <!-- 딜러 -->
    <div class="px-4 pt-4">
      <div class="text-xs text-white/60 mb-2">딜러
        <span v-if="phase!=='betting'" class="ml-1 bg-black/30 text-white/80 px-2 py-0.5 rounded-full">
          {{ dealerScore }}
        </span>
      </div>
      <div class="flex gap-2 flex-wrap min-h-20">
        <div v-for="(card, i) in dealerHand" :key="'d'+i"
          class="w-14 h-20 rounded-xl shadow-lg flex flex-col items-center justify-center font-black text-xl border-2"
          :class="card.hidden
            ? 'bg-gradient-to-br from-blue-800 to-blue-900 border-blue-600 text-blue-700'
            : card.suit==='♥'||card.suit==='♦'
              ? 'bg-white border-gray-200 text-red-500'
              : 'bg-white border-gray-200 text-gray-900'">
          <template v-if="card.hidden">
            <span class="text-3xl text-blue-600">🂠</span>
          </template>
          <template v-else>
            <div class="text-sm leading-none">{{ card.rank }}</div>
            <div class="text-lg leading-none">{{ card.suit }}</div>
          </template>
        </div>
      </div>
    </div>

    <!-- 내 패 -->
    <div class="px-4 pt-3">
      <div class="text-xs text-white/60 mb-2">나
        <span v-if="phase!=='betting'" class="ml-1 bg-black/30 text-white/80 px-2 py-0.5 rounded-full"
          :class="playerScore>21?'bg-red-500/70':''">
          {{ playerScore }}{{ playerScore>21?' BUST':'' }}
        </span>
      </div>
      <div class="flex gap-2 flex-wrap min-h-20">
        <div v-for="(card, i) in playerHand" :key="'p'+i"
          class="w-14 h-20 rounded-xl shadow-lg flex flex-col items-center justify-center font-black border-2"
          :class="card.suit==='♥'||card.suit==='♦'
            ? 'bg-white border-gray-200 text-red-500'
            : 'bg-white border-gray-200 text-gray-900'">
          <div class="text-sm leading-none">{{ card.rank }}</div>
          <div class="text-xl leading-none">{{ card.suit }}</div>
        </div>
      </div>
    </div>

    <!-- 베팅 화면 -->
    <div v-if="phase==='betting'" class="mx-4 mt-6 bg-black/30 rounded-2xl p-5">
      <div class="text-white font-bold mb-3 text-center">베팅 금액 선택</div>
      <div class="grid grid-cols-4 gap-2 mb-4">
        <button v-for="b in betOptions" :key="b"
          @click="bet=b"
          class="py-2 rounded-xl font-bold text-sm transition"
          :class="bet===b ? 'bg-yellow-400 text-yellow-900' : 'bg-white/20 text-white hover:bg-white/30'">
          {{ b.toLocaleString() }}P
        </button>
      </div>
      <div class="text-center text-white/60 text-sm mb-3">선택: <span class="text-yellow-300 font-bold">{{ bet.toLocaleString() }}P</span></div>
      <button @click="startRound" :disabled="bet > chips"
        class="w-full bg-yellow-500 text-yellow-900 font-black py-3 rounded-xl text-lg hover:bg-yellow-400 disabled:opacity-40 transition">
        딜!
      </button>
    </div>

    <!-- 게임 중 버튼 -->
    <div v-else-if="phase==='player'" class="mx-4 mt-4 grid grid-cols-2 gap-3">
      <button @click="hit"
        class="bg-blue-500 text-white font-black py-4 rounded-2xl text-xl hover:bg-blue-600 active:scale-95 transition shadow-lg">
        히트 (한 장 더)
      </button>
      <button @click="stand"
        class="bg-red-500 text-white font-black py-4 rounded-2xl text-xl hover:bg-red-600 active:scale-95 transition shadow-lg">
        스탠드 (그만)
      </button>
      <button v-if="playerHand.length===2 && chips>=bet*2" @click="doubleDown"
        class="col-span-2 bg-purple-500 text-white font-bold py-3 rounded-2xl hover:bg-purple-600 active:scale-95 transition">
        더블 다운 (배팅 2배 + 1장)
      </button>
    </div>

    <!-- 다음 판 -->
    <div v-else-if="phase==='done'" class="mx-4 mt-4 flex gap-3">
      <button @click="phase='betting'; result=null"
        class="flex-1 bg-yellow-500 text-yellow-900 font-black py-4 rounded-2xl text-lg hover:bg-yellow-400 active:scale-95 transition">
        다음 판
      </button>
      <button @click="resetGame"
        class="bg-white/20 text-white font-bold py-4 px-5 rounded-2xl hover:bg-white/30">
        리셋
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

// ── 사운드 ───────────────────────────────────────────────────────────────────
let _ac = null
function getAc() { if(!_ac) _ac=new(window.AudioContext||window.webkitAudioContext)(); return _ac }

function playSound(type) {
  try {
    const ctx = getAc()
    if (type === 'deal') {
      const buf = ctx.createBuffer(1, ctx.sampleRate*0.05, ctx.sampleRate)
      const d = buf.getChannelData(0)
      for (let i=0;i<d.length;i++) d[i]=(Math.random()*2-1)*Math.pow(1-i/d.length,4)
      const src = ctx.createBufferSource(); src.buffer=buf
      const f=ctx.createBiquadFilter(); f.type='bandpass'; f.frequency.value=2500
      const g=ctx.createGain(); g.gain.value=0.5
      src.connect(f); f.connect(g); g.connect(ctx.destination); src.start()
    } else if (type==='win') {
      [523,659,784,1047].forEach((freq,i)=>{
        const o=ctx.createOscillator(),g=ctx.createGain()
        o.connect(g);g.connect(ctx.destination);o.type='triangle';o.frequency.value=freq
        g.gain.setValueAtTime(0,ctx.currentTime+i*.13)
        g.gain.linearRampToValueAtTime(.25,ctx.currentTime+i*.13+.04)
        g.gain.exponentialRampToValueAtTime(.001,ctx.currentTime+i*.13+.25)
        o.start(ctx.currentTime+i*.13);o.stop(ctx.currentTime+i*.13+.25)
      })
    } else if (type==='blackjack') {
      [523,659,784,1047,1319].forEach((freq,i)=>{
        const o=ctx.createOscillator(),g=ctx.createGain()
        o.connect(g);g.connect(ctx.destination);o.type='triangle';o.frequency.value=freq
        g.gain.setValueAtTime(0,ctx.currentTime+i*.1)
        g.gain.linearRampToValueAtTime(.3,ctx.currentTime+i*.1+.04)
        g.gain.exponentialRampToValueAtTime(.001,ctx.currentTime+i*.1+.3)
        o.start(ctx.currentTime+i*.1);o.stop(ctx.currentTime+i*.1+.3)
      })
    } else if (type==='lose') {
      [392,330,262].forEach((freq,i)=>{
        const o=ctx.createOscillator(),g=ctx.createGain()
        o.connect(g);g.connect(ctx.destination);o.type='sine';o.frequency.value=freq
        g.gain.setValueAtTime(.2,ctx.currentTime+i*.2)
        g.gain.exponentialRampToValueAtTime(.001,ctx.currentTime+i*.2+.25)
        o.start(ctx.currentTime+i*.2);o.stop(ctx.currentTime+i*.2+.25)
      })
    } else if (type==='bust') {
      const o=ctx.createOscillator(),g=ctx.createGain()
      o.connect(g);g.connect(ctx.destination);o.type='sawtooth'
      o.frequency.setValueAtTime(300,ctx.currentTime)
      o.frequency.exponentialRampToValueAtTime(100,ctx.currentTime+.3)
      g.gain.setValueAtTime(.3,ctx.currentTime)
      g.gain.exponentialRampToValueAtTime(.001,ctx.currentTime+.3)
      o.start();o.stop(ctx.currentTime+.3)
    }
  } catch(e){}
}

const SUITS = ['♠','♥','♦','♣']
const RANKS = ['A','2','3','4','5','6','7','8','9','10','J','Q','K']

function buildDeck() {
  const d = []
  for (const suit of SUITS)
    for (const rank of RANKS)
      d.push({ suit, rank, hidden: false })
  return d
}

function shuffle(arr) {
  const a = [...arr]
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [a[i], a[j]] = [a[j], a[i]]
  }
  return a
}

function cardValue(rank) {
  if (['J','Q','K'].includes(rank)) return 10
  if (rank === 'A') return 11
  return parseInt(rank)
}

function handScore(hand) {
  let score = 0, aces = 0
  for (const c of hand) {
    if (c.hidden) continue
    score += cardValue(c.rank)
    if (c.rank === 'A') aces++
  }
  while (score > 21 && aces > 0) { score -= 10; aces-- }
  return score
}

const chips = ref(1000)
const bet    = ref(100)
const betOptions = [50, 100, 200, 500]

const deck       = ref([])
const playerHand = ref([])
const dealerHand = ref([])
const phase      = ref('betting') // betting | player | dealer | done
const result     = ref(null)      // win | lose | push | bust | blackjack

const playerScore = computed(() => handScore(playerHand.value))
const dealerScore = computed(() => handScore(dealerHand.value))

const resultEmoji = computed(() => ({
  blackjack:'🃏', win:'🎉', push:'🤝', lose:'😢', bust:'💥'
}[result.value] ?? ''))

const resultText = computed(() => ({
  blackjack:'블랙잭!', win:'승리!', push:'무승부', lose:'패배', bust:'버스트!'
}[result.value] ?? ''))

const resultDetail = computed(() => {
  if (result.value === 'blackjack') return `+${Math.floor(bet.value*1.5).toLocaleString()}P 획득!`
  if (result.value === 'win') return `+${bet.value.toLocaleString()}P 획득!`
  if (result.value === 'push') return '베팅금 반환'
  if (result.value === 'lose') return `-${bet.value.toLocaleString()}P`
  if (result.value === 'bust') return `-${bet.value.toLocaleString()}P`
  return ''
})

function deal(hidden=false) {
  const c = deck.value.pop()
  c.hidden = hidden
  return c
}

async function startRound() {
  if (bet.value > chips.value) return
  deck.value = shuffle(buildDeck())
  playerHand.value = []
  dealerHand.value = []
  result.value = null
  phase.value = 'player'

  // 딜 애니메이션: 카드 4장을 순서대로 나눠줌
  const cards = [deal(), deal(true), deal(), deal(true)]
  // 나→딜러→나→딜러 순으로
  for (let i = 0; i < 4; i++) {
    await new Promise(r => setTimeout(r, 250))
    playSound('deal')
    if (i % 2 === 0) playerHand.value.push(cards[i])
    else             dealerHand.value.push(cards[i])
  }

  // 블랙잭 체크
  if (playerScore.value === 21) {
    revealDealer()
    if (dealerScore.value === 21) { settle('push') }
    else { settle('blackjack') }
  }
}

function hit() {
  playSound('deal')
  playerHand.value.push(deal())
  if (playerScore.value > 21) { settle('bust') }
}

function stand() {
  revealDealer()
  dealerPlay()
}

function doubleDown() {
  bet.value *= 2
  playerHand.value.push(deal())
  if (playerScore.value > 21) { settle('bust'); return }
  stand()
}

function revealDealer() {
  dealerHand.value = dealerHand.value.map(c => ({ ...c, hidden: false }))
}

function dealerPlay() {
  // 딜러: 16 이하면 반드시 히트
  while (dealerScore.value < 17) {
    dealerHand.value.push(deal())
  }
  const ps = playerScore.value
  const ds = dealerScore.value
  if (ds > 21) settle('win')
  else if (ps > ds) settle('win')
  else if (ps === ds) settle('push')
  else settle('lose')
}

function settle(res) {
  result.value = res
  phase.value = 'done'
  playSound(res === 'blackjack' ? 'blackjack' : res === 'win' ? 'win' : res === 'bust' ? 'bust' : 'lose')
  if (res === 'blackjack') chips.value += Math.floor(bet.value * 1.5)
  else if (res === 'win') chips.value += bet.value
  else if (res === 'lose' || res === 'bust') chips.value -= bet.value
  // push: 환불 (이미 차감 안 했으므로 그대로)
  if (chips.value <= 0) chips.value = 200 // 파산 시 리셋
  bet.value = Math.min(bet.value, chips.value)
}

function resetGame() {
  chips.value = 1000
  bet.value = 100
  phase.value = 'betting'
  result.value = null
  playerHand.value = []
  dealerHand.value = []
}
</script>
