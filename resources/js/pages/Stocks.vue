<template>
<div class="min-h-screen">
<div class="page-main px-4 py-6">
  <PageHeader title="증권" icon="trending-up" :back="false" />
  <p class="text-sm text-ink-muted -mt-2 mb-5">미국 시장 기준이에요. 모든 시간은 미국 동부시간(ET)이고, 시세는 15분마다 갱신돼요. <span class="text-ink-faint">· 지금 {{ nowET }}</span></p>

  <!-- ── 실적 발표(어닝) 캘린더 ─────────────────────────── -->
  <div class="card overflow-hidden mb-6">
    <div class="px-4 py-3 border-b border-line flex items-center gap-2 flex-wrap">
      <div class="font-bold text-sm text-ink flex items-center gap-1.5"><AppIcon name="calendar" :size="15" class="text-amber-600" />실적 발표 캘린더</div>
      <div class="ml-auto flex items-center gap-1 flex-wrap">
        <button v-for="w in weekTabs" :key="w.v" @click="setWeek(w.v)"
          class="px-3 py-1.5 rounded-full text-xs font-bold border transition-colors"
          :class="week === w.v ? 'bg-ink text-white border-ink' : 'bg-white text-ink-light border-gray-200 hover:bg-gray-50'">{{ w.label }}</button>
      </div>
    </div>
    <div class="px-4 py-2 border-b border-line bg-gray-50/60 flex items-center gap-3 flex-wrap text-[11px] text-ink-muted">
      <span class="font-semibold text-ink-light">{{ rangeText }}</span>
      <span class="inline-flex items-center gap-1"><b class="text-amber-600">장전</b>장 시작 전 발표</span>
      <span class="inline-flex items-center gap-1"><b class="text-indigo-600">장후</b>장 마감 후 발표</span>
      <span class="inline-flex items-center gap-1"><span class="text-amber-500">★</span>내 관심종목</span>
      <input v-model="q" type="text" placeholder="티커/회사 검색" class="input-soft !w-40 !py-1 !px-3 !text-xs ml-auto" />
    </div>

    <div v-if="earnLoading" class="py-10 text-center text-sm text-ink-muted">불러오는 중…</div>
    <div v-else-if="!earn || earn.days.every(d => !d.total)" class="py-10 text-center text-sm text-ink-muted">이 주에는 아직 등록된 실적 발표 일정이 없어요.</div>
    <div v-else class="grid grid-cols-1 md:grid-cols-5 md:divide-x divide-line">
      <div v-for="d in earn.days" :key="d.date" class="min-w-0" :class="d.is_today ? 'bg-amber-50/40' : ''">
        <div class="px-3 py-2 border-b border-line flex items-center justify-between" :class="d.is_today ? 'bg-amber-100/60' : 'bg-gray-50/60'">
          <div class="text-xs font-black text-ink">{{ d.weekday }} <span class="font-semibold text-ink-muted">{{ shortDate(d.date) }}</span><span v-if="d.is_today" class="ml-1 text-[10px] bg-amber-500 text-white px-1.5 py-0.5 rounded-full">오늘</span></div>
          <div class="text-[11px] text-ink-faint">{{ d.total }}곳</div>
        </div>
        <div class="p-2 space-y-1">
          <div v-for="e in visibleItems(d)" :key="e.symbol" class="flex items-center gap-1.5 rounded-lg px-2 py-1.5 hover:bg-gray-50"
            :title="tip(e)">
            <span class="w-7 flex-shrink-0 text-[10px] font-bold" :class="e.slot === 'bmo' ? 'text-amber-600' : e.slot === 'amc' ? 'text-indigo-600' : 'text-gray-300'">{{ e.slot === 'bmo' ? '장전' : e.slot === 'amc' ? '장후' : '미정' }}</span>
            <span class="text-xs font-black text-ink">{{ e.symbol }}</span>
            <span v-if="watchSet.has(e.symbol)" class="text-amber-500 text-xs">★</span>
            <span class="text-[11px] text-ink-muted truncate flex-1">{{ e.name }}</span>
          </div>
          <div v-if="!visibleItems(d).length" class="text-[11px] text-ink-faint text-center py-3">{{ q ? '검색 결과 없음' : '일정 없음' }}</div>
          <button v-if="canExpand(d)" @click="toggleDay(d.date)" class="w-full text-[11px] font-bold text-amber-700 hover:bg-amber-50 rounded-lg py-1.5">
            {{ expanded[d.date] ? '접기' : `+${filtered(d).length - TOP}곳 더 보기` }}
          </button>
        </div>
      </div>
    </div>
    <p class="px-4 py-2 text-[10.5px] text-ink-faint border-t border-line">시가총액이 큰 회사부터 보여요. 종목에 마우스를 올리면 예상 EPS와 분기 정보가 나와요. 일정은 회사 사정으로 바뀔 수 있어요.</p>
  </div>

  <!-- ── 주요 지수 ───────────────────────────────────── -->
  <div class="font-bold text-sm text-ink mb-2">주요 지수 · 지표</div>
  <div class="grid grid-cols-2 md:grid-cols-4 gap-2.5 mb-6">
    <div v-for="x in indices" :key="x.symbol" class="card p-3">
      <div class="text-xs font-bold text-ink-light truncate">{{ x.name }}</div>
      <div class="text-lg font-black text-ink tabular-nums mt-0.5">{{ fmt(x.price) }}</div>
      <div class="text-xs font-bold tabular-nums" :class="clr(x.change_pct)">{{ arrow(x.change_pct) }} {{ fmtSigned(x.change) }} ({{ fmtSigned(x.change_pct) }}%)</div>
      <svg v-if="x.sparkline?.length > 1" viewBox="0 0 100 24" class="w-full h-6 mt-1.5" preserveAspectRatio="none">
        <path :d="spark(x.sparkline)" fill="none" :stroke="Number(x.change_pct) >= 0 ? '#16A34A' : '#DC2626'" stroke-width="1.6" vector-effect="non-scaling-stroke" />
      </svg>
    </div>
    <div v-if="!indices.length && !loading" class="col-span-full text-sm text-ink-muted py-4 text-center">시세를 불러오는 중이에요. 잠시 후 다시 확인해 주세요.</div>
  </div>

  <!-- ── 내 관심종목 ─────────────────────────────────── -->
  <div class="card overflow-hidden mb-6">
    <div class="px-4 py-3 border-b border-line flex items-center gap-2 flex-wrap">
      <div class="font-bold text-sm text-ink flex items-center gap-1.5"><AppIcon name="star" :size="15" class="text-amber-500" />내 관심종목</div>
      <form v-if="auth.isLoggedIn" @submit.prevent="addTicker" class="ml-auto flex items-center gap-1.5">
        <input v-model="ticker" type="text" maxlength="12" placeholder="티커 입력 (예: AAPL)" class="input-soft !w-44 !py-1.5 !px-3 !text-xs uppercase" />
        <button type="submit" :disabled="adding || !ticker.trim()" class="btn-primary !px-3 !py-1.5 !text-xs disabled:opacity-50">{{ adding ? '확인 중…' : '추가' }}</button>
      </form>
    </div>
    <div v-if="addError" class="px-4 py-2 text-xs text-red-600 bg-red-50">{{ addError }}</div>

    <div v-if="!auth.isLoggedIn" class="px-4 py-8 text-center text-sm text-ink-muted">
      로그인하면 보고 싶은 종목을 티커로 담아 두고, 언제든 지울 수 있어요.
      <div class="mt-3"><RouterLink to="/login" class="btn-primary !px-4 !py-2 !text-xs">로그인</RouterLink></div>
    </div>
    <template v-else>
      <div v-if="!myList.length && !loading" class="px-4 py-8 text-center">
        <p class="text-sm text-ink-muted">아직 담은 종목이 없어요. 티커를 입력하거나 아래에서 골라 보세요.</p>
        <div class="flex flex-wrap gap-1.5 justify-center mt-3">
          <button v-for="t in suggestions" :key="t" @click="ticker = t; addTicker()" class="text-xs px-3 py-1.5 rounded-full border border-gray-200 text-ink-light hover:bg-amber-50 hover:border-amber-300 hover:text-amber-700 transition-colors">+ {{ t }}</button>
        </div>
      </div>
      <table v-else class="w-full text-sm">
        <thead><tr class="text-xs text-ink-muted border-b border-line">
          <th class="text-left font-medium px-4 py-2">종목</th><th class="text-right font-medium px-2 py-2">현재가</th>
          <th class="text-right font-medium px-2 py-2">전일대비</th><th class="hidden sm:table-cell w-28 px-2"></th><th class="w-10"></th>
        </tr></thead>
        <tbody>
          <tr v-for="w in myList" :key="w.symbol" class="border-b border-line last:border-0">
            <td class="px-4 py-2.5"><div class="font-black text-ink">{{ w.symbol }}</div><div class="text-[11px] text-ink-muted truncate max-w-[160px]">{{ w.name }}</div></td>
            <td class="px-2 py-2.5 text-right tabular-nums font-semibold">{{ w.price != null ? fmt(w.price) : '-' }}</td>
            <td class="px-2 py-2.5 text-right tabular-nums font-bold" :class="clr(w.change_pct)">
              <template v-if="w.change_pct != null">{{ arrow(w.change_pct) }} {{ fmtSigned(w.change) }}<div class="text-[11px]">{{ fmtSigned(w.change_pct) }}%</div></template>
            </td>
            <td class="hidden sm:table-cell px-2">
              <svg v-if="w.sparkline?.length > 1" viewBox="0 0 100 24" class="w-24 h-6" preserveAspectRatio="none">
                <path :d="spark(w.sparkline)" fill="none" :stroke="Number(w.change_pct) >= 0 ? '#16A34A' : '#DC2626'" stroke-width="1.6" vector-effect="non-scaling-stroke" />
              </svg>
            </td>
            <td class="px-2 text-center"><button @click="removeTicker(w.symbol)" class="text-ink-faint hover:text-red-500 p-1" title="목록에서 지우기"><AppIcon name="x" :size="14" /></button></td>
          </tr>
        </tbody>
      </table>
    </template>
  </div>

  <!-- ── 대표 종목 ───────────────────────────────────── -->
  <div class="card overflow-hidden">
    <div class="px-4 py-3 border-b border-line font-bold text-sm text-ink">대표 종목</div>
    <table class="w-full text-sm">
      <tbody>
        <tr v-for="w in watchlist" :key="w.symbol" class="border-b border-line last:border-0">
          <td class="px-4 py-2.5"><div class="font-black text-ink">{{ w.symbol }}</div><div class="text-[11px] text-ink-muted">{{ w.name }}</div></td>
          <td class="px-2 py-2.5 text-right tabular-nums font-semibold">{{ fmt(w.price) }}</td>
          <td class="px-4 py-2.5 text-right tabular-nums font-bold" :class="clr(w.change_pct)">{{ arrow(w.change_pct) }} {{ fmtSigned(w.change_pct) }}%</td>
        </tr>
      </tbody>
    </table>
  </div>
  <p class="text-[10.5px] text-ink-faint mt-3">시세는 지연될 수 있고 투자 판단의 책임은 본인에게 있어요.</p>
</div>
</div>
</template>

<script setup>
import { ref, computed, reactive, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import { useAuthStore } from '../stores/auth'
import AppIcon from '../components/AppIcon.vue'
import PageHeader from '../components/PageHeader.vue'

const auth = useAuthStore()
const TOP = 8
const indices = ref([])
const watchlist = ref([])
const myList = ref([])
const loading = ref(true)
const ticker = ref('')
const adding = ref(false)
const addError = ref('')
const suggestions = ['AAPL', 'MSFT', 'NVDA', 'TSLA', 'AMZN', 'GOOGL', 'META', 'SPY']

// 실적 캘린더
const weekTabs = [{ v: -1, label: '지난주' }, { v: 0, label: '이번주' }, { v: 1, label: '다음주' }, { v: 2, label: '2주 뒤' }]
const week = ref(0)
const earn = ref(null)
const earnLoading = ref(true)
const q = ref('')
const expanded = reactive({})
const cache = {}

const nowET = ref('')
function tickClock() {
  nowET.value = new Date().toLocaleString('en-US', { timeZone: 'America/New_York', weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) + ' ET'
}
let clockTimer = null

const watchSet = computed(() => new Set(myList.value.map(w => w.symbol)))
const rangeText = computed(() => earn.value ? `${longDate(earn.value.from)} – ${longDate(earn.value.to)}` : '')
function longDate(d) { return new Date(d + 'T12:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) }
function shortDate(d) { return new Date(d + 'T12:00:00').toLocaleDateString('en-US', { month: 'numeric', day: 'numeric' }) }

function filtered(d) {
  const s = q.value.trim().toLowerCase()
  if (!s) return d.items
  return d.items.filter(e => e.symbol.toLowerCase().includes(s) || (e.name || '').toLowerCase().includes(s))
}
function visibleItems(d) {
  const list = filtered(d)
  return expanded[d.date] || q.value.trim() ? list : list.slice(0, TOP)
}
function canExpand(d) { return !q.value.trim() && filtered(d).length > TOP }
function toggleDay(date) { expanded[date] = !expanded[date] }
function tip(e) {
  const cap = e.market_cap ? '시가총액 $' + (e.market_cap >= 1e9 ? (e.market_cap / 1e9).toFixed(1) + 'B' : (e.market_cap / 1e6).toFixed(0) + 'M') : ''
  return [e.name, e.fiscal_quarter ? `분기: ${e.fiscal_quarter}` : '', e.eps_forecast ? `예상 EPS ${e.eps_forecast}` : '', e.last_year_eps ? `전년 EPS ${e.last_year_eps}` : '', cap, e.est_count ? `애널리스트 ${e.est_count}명` : ''].filter(Boolean).join(' · ')
}

async function loadEarnings(w) {
  if (cache[w]) { earn.value = cache[w]; earnLoading.value = false; return }
  earnLoading.value = true
  try {
    const { data } = await axios.get('/api/stocks/earnings', { params: { week: w } })
    cache[w] = data.data
    if (week.value === w) earn.value = data.data
  } catch { if (week.value === w) earn.value = null }
  if (week.value === w) earnLoading.value = false
}
function setWeek(w) { week.value = w; loadEarnings(w) }

// 숫자/색 (미국식: 상승 초록, 하락 빨강)
const fmt = v => Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const fmtSigned = v => (v == null ? '' : (Number(v) > 0 ? '+' : '') + Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }))
const clr = v => (Number(v) >= 0 ? 'text-[#16A34A]' : 'text-[#DC2626]')
const arrow = v => (Number(v) >= 0 ? '▲' : '▼')
function spark(points) {
  if (!points || points.length < 2) return ''
  const min = Math.min(...points), max = Math.max(...points), range = (max - min) || 1, stepX = 100 / (points.length - 1)
  return points.map((v, i) => `${i === 0 ? 'M' : 'L'} ${(i * stepX).toFixed(2)} ${(22 - ((v - min) / range) * 20).toFixed(2)}`).join(' ')
}

async function loadMine() {
  if (!auth.isLoggedIn) return
  try { const { data } = await axios.get('/api/stocks/watchlist'); myList.value = data.data || [] } catch {}
}
async function addTicker() {
  const t = ticker.value.trim().toUpperCase()
  if (!t || adding.value) return
  adding.value = true; addError.value = ''
  try {
    const { data } = await axios.post('/api/stocks/watchlist', { symbol: t })
    myList.value = data.data || []
    ticker.value = ''
  } catch (e) { addError.value = e.response?.data?.message || '추가하지 못했어요. 잠시 후 다시 시도해 주세요.' }
  adding.value = false
}
async function removeTicker(symbol) {
  try { const { data } = await axios.delete(`/api/stocks/watchlist/${encodeURIComponent(symbol)}`); myList.value = data.data || [] } catch {}
}

onMounted(async () => {
  tickClock(); clockTimer = setInterval(tickClock, 30000)
  loadEarnings(0)
  loadMine()
  try {
    const { data } = await axios.get('/api/market-quotes')
    indices.value = data.data?.indices || []
    watchlist.value = data.data?.watchlist || []
  } catch {}
  loading.value = false
})
onUnmounted(() => clearInterval(clockTimer))
</script>
