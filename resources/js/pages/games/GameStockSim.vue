<template>
  <div class="stock-game">
    <div class="game-header">
      <button class="back-btn" @click="goBack">← 뒤로</button>
      <div class="level-badge">레벨 {{ level }} 📈</div>
      <div class="cash-badge">💰 {{ cash.toLocaleString() }}원</div>
    </div>

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
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
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
.stock-game { min-height:100vh; background:linear-gradient(135deg,#0f172a,#1e293b); padding:16px; font-family:'Noto Sans KR',sans-serif; color:#fff; }
.game-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
.back-btn { background:rgba(255,255,255,0.1); color:#fff; border:none; padding:8px 14px; border-radius:20px; cursor:pointer; font-size:14px; }
.level-badge,.cash-badge { background:rgba(255,255,255,0.1); color:#fff; padding:6px 14px; border-radius:20px; font-weight:700; font-size:13px; }
.center-box { text-align:center; padding:40px 20px; }
.title { font-size:36px; color:#fff; font-weight:900; margin:10px 0; }
.subtitle { color:rgba(255,255,255,0.7); font-size:16px; }
.level-info { color:#60a5fa; margin:12px 0; font-size:15px; }
.start-btn { background:#10b981; color:#fff; border:none; padding:14px 40px; border-radius:30px; font-size:20px; font-weight:800; cursor:pointer; margin-top:20px; }
.play-area { max-width:480px; margin:0 auto; }
.day-bar { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; padding:10px 14px; background:rgba(255,255,255,0.07); border-radius:12px; }
.day-label { font-size:14px; color:rgba(255,255,255,0.7); }
.portfolio-value { font-size:14px; }
.portfolio-value strong { font-size:16px; }
.profit { color:#10b981; }
.loss { color:#ef4444; }
.stocks-list { display:flex; flex-direction:column; gap:10px; margin-bottom:16px; }
.stock-card { background:rgba(255,255,255,0.08); border-radius:14px; padding:14px; }
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
.buy-btn,.sell-btn { flex:1; padding:8px; border:none; border-radius:8px; font-size:14px; font-weight:700; cursor:pointer; }
.buy-btn { background:#10b981; color:#fff; }
.buy-btn:disabled { background:#374151; color:#6b7280; cursor:not-allowed; }
.sell-btn { background:#ef4444; color:#fff; }
.sell-btn:disabled { background:#374151; color:#6b7280; cursor:not-allowed; }
.next-day-btn { width:100%; padding:14px; background:#3b82f6; color:#fff; border:none; border-radius:12px; font-size:17px; font-weight:700; cursor:pointer; }
.result-box { text-align:center; padding:40px 20px; }
.res-title { font-size:22px; font-weight:700; margin:10px 0; }
.res-amount { font-size:48px; font-weight:900; }
.res-rate { font-size:22px; font-weight:700; margin:8px 0; }
.levelup { background:#10b981; color:#fff; padding:10px 20px; border-radius:20px; font-weight:800; font-size:18px; margin:14px auto; display:inline-block; }
.res-btns { display:flex; gap:12px; justify-content:center; margin-top:20px; }
.rbtn { background:rgba(255,255,255,0.9); color:#0f172a; border:none; padding:12px 28px; border-radius:20px; font-size:16px; font-weight:700; cursor:pointer; }
.rbtn.home { background:#3b82f6; color:#fff; }
</style>
