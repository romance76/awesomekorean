<template>
  <GameShell title="주식 시뮬레이션" icon="📈" theme="dark"
    bg="linear-gradient(135deg,#0f172a,#1e293b)">
    <template #meta>
      <span class="level-badge">레벨 {{ level }}</span>
      <span class="cash-badge">💰 {{ cash.toLocaleString() }}원</span>
    </template>
  <div class="stock-game">
    <div v-if="phase==='start'" class="center-box">
      <div style="font-size:80px">📈</div>
      <h1 class="title">주식 시뮬레이션</h1>
      <p class="subtitle">가상 주식을 사고 팔아서 돈을 불려봐요!</p>
      <div class="level-info">시작 자금: {{ startCash.toLocaleString() }}원 · {{ totalDays }}일 게임</div>
      <button class="start-btn" @click="startGame">투자 시작! 📊</button>
    </div>

    <div v-if="phase==='play'" class="play-area">
      <div class="day-bar">
        <span class="day-label">📅 {{ day }}일째 / {{ totalDays }}일</span>
        <div class="portfolio-value">
          총 자산: <strong :class="totalAsset >= startCash ? 'profit' : 'loss'">{{ totalAsset.toLocaleString() }}원</strong>
        </div>
      </div>
      <div class="stocks-list">
        <div v-for="stock in stocks" :key="stock.name" class="stock-card">
          <div class="stock-top">
            <span class="stock-icon">{{ stock.icon }}</span>
            <div class="stock-info">
              <div class="stock-name">{{ stock.name }}</div>
              <div class="stock-price" :class="stock.change >= 0 ? 'up' : 'down'">
                {{ stock.price.toLocaleString() }}원
                <span class="change-badge">{{ stock.change >= 0 ? '+' : '' }}{{ stock.change }}%</span>
              </div>
            </div>
            <div class="stock-chart">
              <svg width="60" height="30" viewBox="0 0 60 30">
                <polyline :points="chartPoints(stock)" fill="none"
                  :stroke="stock.change>=0?'#10b981':'#ef4444'" stroke-width="2"/>
              </svg>
            </div>
          </div>
          <div class="stock-holding" v-if="holding[stock.name]">
            보유: {{ holding[stock.name] }}주 ({{ (holding[stock.name]*stock.price).toLocaleString() }}원)
          </div>
          <div class="stock-actions">
            <button class="buy-btn" @click="buy(stock)" :disabled="cash < stock.price">매수</button>
            <button class="sell-btn" @click="sell(stock)" :disabled="!holding[stock.name]">매도</button>
          </div>
        </div>
      </div>
      <button class="next-day-btn" @click="nextDay">다음 날 ▶</button>
    </div>

    <div v-if="phase==='result'" class="result-box">
      <div style="font-size:80px">{{ profit >= 0 ? '🤑' : '😭' }}</div>
      <div class="res-title">{{ totalDays }}일 투자 결과</div>
      <div class="res-amount" :class="profit>=0?'profit':'loss'">
        {{ profit >= 0 ? '+' : '' }}{{ profit.toLocaleString() }}원
      </div>
      <div class="res-rate" :class="profit>=0?'profit':'loss'">
        수익률: {{ profitRate }}%
      </div>
      <div v-if="leveled" class="levelup">🎉 레벨업! 레벨 {{ level }}!</div>
      <GameResultExtras :rec="rec" slug="stock_sim" />
      <div class="res-btns">
        <button class="rbtn" @click="startGame">다시 📊</button>
        <button class="rbtn home" @click="goBack">홈 🏠</button>
      </div>
    </div>
  </div>
  </GameShell>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import GameShell from '../../components/GameShell.vue'
import GameResultExtras from '../../components/GameResultExtras.vue'
import { useGameRecord } from '../../composables/useGameRecord'
const router = useRouter()
const rec = useGameRecord('stock_sim')

const level = ref(parseInt(localStorage.getItem('stock_level') || '1'))

const stockTemplates = [
  {name:'삼성전자',icon:'📱',basePrice:70000},
  {name:'카카오',icon:'💬',basePrice:45000},
  {name:'네이버',icon:'🔍',basePrice:180000},
  {name:'현대차',icon:'🚗',basePrice:220000},
  {name:'LG전자',icon:'📺',basePrice:95000},
]

const startCash = computed(() => level.value <= 2 ? 1000000 : level.value <= 4 ? 2000000 : 5000000)
const totalDays = computed(() => level.value <= 2 ? 10 : level.value <= 4 ? 15 : 20)

const phase = ref('start')
const cash = ref(1000000)
const stocks = ref([])
const holding = ref({})
const day = ref(1)
const leveled = ref(false)

const totalAsset = computed(() => {
  let total = cash.value
  for (const stock of stocks.value) {
    total += (holding.value[stock.name] || 0) * stock.price
  }
  return total
})

const profit = computed(() => totalAsset.value - startCash.value)
const profitRate = computed(() => Math.round(profit.value / startCash.value * 100))

function speak(text) {
  if (!window.speechSynthesis) return
  window.speechSynthesis.cancel()
  const u = new SpeechSynthesisUtterance(text)
  u.lang = 'ko-KR'; u.rate = 0.9
  window.speechSynthesis.speak(u)
}

function initStocks() {
  const count = level.value <= 2 ? 3 : 5
  return stockTemplates.slice(0, count).map(t => ({
    ...t,
    price: t.basePrice,
    change: 0,
    history: [t.basePrice],
  }))
}

function startGame() {
  cash.value = startCash.value
  stocks.value = initStocks()
  holding.value = {}
  day.value = 1
  leveled.value = false
  rec.start(level.value)
  phase.value = 'play'
  speak('주식 투자를 시작합니다!')
}

function nextDay() {
  if (day.value >= totalDays.value) { endGame(); return }
  day.value++
  stocks.value = stocks.value.map(s => {
    const changePercent = (Math.random() * 14 - 5)
    const newPrice = Math.max(1000, Math.round(s.price * (1 + changePercent / 100)))
    const history = [...s.history, newPrice].slice(-10)
    return { ...s, price: newPrice, change: Math.round(changePercent * 10) / 10, history }
  })
  const news = stocks.value[Math.floor(Math.random()*stocks.value.length)]
  if (news.change > 3) speak(news.name + ' 주가 상승!')
  else if (news.change < -3) speak(news.name + ' 주가 하락!')
}

function buy(stock) {
  if (cash.value < stock.price) return
  cash.value -= stock.price
  holding.value[stock.name] = (holding.value[stock.name] || 0) + 1
  speak(stock.name + ' 매수')
}

function sell(stock) {
  if (!holding.value[stock.name]) return
  cash.value += stock.price
  holding.value[stock.name]--
  if (!holding.value[stock.name]) delete holding.value[stock.name]
  speak(stock.name + ' 매도')
}

function chartPoints(stock) {
  const h = stock.history
  if (h.length < 2) return '0,15 60,15'
  const min = Math.min(...h), max = Math.max(...h)
  const range = max - min || 1
  return h.map((v, i) => {
    const x = i / (h.length - 1) * 60
    const y = 28 - (v - min) / range * 26
    return x + ',' + y
  }).join(' ')
}

async function endGame() {
  for (const stock of stocks.value) {
    if (holding.value[stock.name]) {
      cash.value += holding.value[stock.name] * stock.price
      delete holding.value[stock.name]
    }
  }
  phase.value = 'result'
  const won = profitRate.value >= (level.value <= 2 ? 5 : level.value <= 4 ? 10 : 15)
  if (won) {
    level.value++
    localStorage.setItem('stock_level', level.value)
    leveled.value = true
    speak('레벨업! 투자 실력이 늘었어요!')
  } else {
    speak('게임 종료! 수익률 ' + profitRate.value + '퍼센트')
  }
  await rec.end({ won, leveledUp: leveled.value, score: Math.max(0, Math.round(profitRate.value * 10)) })
}

function goBack() { router.push('/games') }
</script>

<style scoped>
.stock-game { flex:1; padding:16px; font-family:'Noto Sans KR',sans-serif; color:#fff; }
.level-badge,.cash-badge { background:rgba(255,255,255,0.1); backdrop-filter:blur(8px); border:1px solid rgba(255,255,255,0.14); color:#fff; padding:5px 12px; border-radius:999px; font-weight:800; font-size:11px; white-space:nowrap; }
.center-box { text-align:center; padding:40px 20px; }
.title { font-size:36px; color:#fff; font-weight:900; margin:10px 0; text-shadow:0 2px 12px rgba(0,0,0,.25); }
.subtitle { color:rgba(255,255,255,0.7); font-size:16px; }
.level-info { color:#60a5fa; margin:12px 0; font-size:15px; }
.start-btn { position:relative; overflow:hidden; background-image:linear-gradient(135deg,#34d399,#059669); color:#fff; border:none; padding:16px 44px; border-radius:999px; font-size:20px; font-weight:800; cursor:pointer; margin-top:20px; box-shadow:0 12px 28px -8px rgba(16,185,129,0.55), inset 0 1px 0 rgba(255,255,255,.35); transition:transform .15s ease; }
.start-btn:hover { transform:translateY(-2px); }
.play-area { max-width:480px; margin:0 auto; }
.day-bar { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; padding:11px 16px; background:rgba(255,255,255,0.06); backdrop-filter:blur(12px); border:1px solid rgba(255,255,255,0.1); border-radius:16px; box-shadow:0 8px 20px rgba(0,0,0,0.15); }
.day-label { font-size:14px; color:rgba(255,255,255,0.7); }
.portfolio-value { font-size:14px; }
.portfolio-value strong { font-size:16px; }
.profit { color:#10b981; }
.loss { color:#ef4444; }
.stocks-list { display:flex; flex-direction:column; gap:12px; margin-bottom:16px; }
.stock-card { background:rgba(255,255,255,0.06); backdrop-filter:blur(14px) saturate(160%); border:1px solid rgba(255,255,255,0.1); border-radius:18px; padding:14px; box-shadow:0 10px 26px rgba(0,0,0,0.18), inset 0 1px 0 rgba(255,255,255,0.06); }
.stock-top { display:flex; align-items:center; gap:10px; margin-bottom:10px; }
.stock-icon { font-size:28px; }
.stock-info { flex:1; }
.stock-name { font-size:15px; font-weight:700; }
.stock-price { font-size:16px; font-weight:700; display:flex; align-items:center; gap:6px; }
.stock-price.up { color:#10b981; }
.stock-price.down { color:#ef4444; }
.change-badge { font-size:12px; font-weight:600; }
.stock-holding { font-size:12px; color:rgba(255,255,255,0.6); margin-bottom:8px; }
.stock-actions { display:flex; gap:8px; }
.buy-btn,.sell-btn { flex:1; padding:9px; border:none; border-radius:10px; font-size:14px; font-weight:700; cursor:pointer; transition:transform .15s ease; }
.buy-btn:hover:not(:disabled),.sell-btn:hover:not(:disabled) { transform:translateY(-1px); }
.buy-btn { background-image:linear-gradient(135deg,#34d399,#059669); color:#fff; box-shadow:0 6px 14px -4px rgba(16,185,129,0.5); }
.buy-btn:disabled { background:rgba(255,255,255,0.08); color:#6b7280; cursor:not-allowed; box-shadow:none; }
.sell-btn { background-image:linear-gradient(135deg,#f87171,#dc2626); color:#fff; box-shadow:0 6px 14px -4px rgba(239,68,68,0.5); }
.sell-btn:disabled { background:rgba(255,255,255,0.08); color:#6b7280; cursor:not-allowed; box-shadow:none; }
.next-day-btn { width:100%; padding:14px; background-image:linear-gradient(135deg,#60a5fa,#2563eb); color:#fff; border:none; border-radius:14px; font-size:17px; font-weight:700; cursor:pointer; box-shadow:0 10px 24px -8px rgba(37,99,235,0.5); transition:transform .15s ease; }
.next-day-btn:hover { transform:translateY(-2px); }
.result-box { text-align:center; padding:40px 20px; }
.res-title { font-size:22px; font-weight:700; margin:10px 0; }
.res-amount { font-size:48px; font-weight:900; }
.res-rate { font-size:22px; font-weight:700; margin:8px 0; }
.levelup { background-image:linear-gradient(135deg,#34d399,#059669); color:#fff; padding:10px 24px; border-radius:999px; font-weight:800; font-size:17px; margin:14px auto; display:inline-block; box-shadow:0 10px 24px -6px rgba(16,185,129,0.5); }
.res-btns { display:flex; gap:12px; justify-content:center; margin-top:24px; }
.rbtn { background:rgba(255,255,255,0.92); backdrop-filter:blur(10px); color:#0f172a; border:none; padding:13px 30px; border-radius:999px; font-size:16px; font-weight:800; cursor:pointer; box-shadow:0 6px 16px rgba(0,0,0,0.15); transition:transform .15s ease; }
.rbtn:hover { transform:translateY(-2px); }
.rbtn.home { background-image:linear-gradient(135deg,#60a5fa,#2563eb); color:#fff; }
</style>
