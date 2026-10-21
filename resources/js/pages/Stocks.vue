<template>
<div class="min-h-screen">
<div class="page-main px-4 py-6">
  <PageHeader title="증권" icon="trending-up" :back="false" />
  <p class="text-sm text-ink-muted -mt-2 mb-5">미국 시장 기준이에요. 모든 시간은 미국 동부시간(ET)이고, 시세는 15분마다 갱신돼요. <span class="text-ink-faint">· 지금 {{ nowET }}</span></p>

  <!-- ── 주요 지수 (야후 파이낸스 스타일 띠) ─────────────── -->
  <div class="card !rounded-xl mb-5 relative">
    <button v-show="canScroll" @click="scrollIdx(-1)" aria-label="이전 지수" class="hidden md:flex absolute left-0 top-0 bottom-0 z-10 w-7 items-center justify-center bg-gradient-to-r from-white via-white to-transparent text-ink-muted hover:text-ink">‹</button>
    <div ref="idxEl" @scroll="updateScroll" class="flex overflow-x-auto snap-x [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
      <div v-for="x in indices" :key="x.symbol" class="snap-start shrink-0 w-[168px] px-3.5 py-2.5 border-r border-line last:border-0 flex items-center gap-2">
        <div class="min-w-0 flex-1">
          <div class="text-[12px] font-bold text-[#0B5FFF] truncate leading-tight">{{ x.name }}</div>
          <div class="text-[15px] font-extrabold text-ink tabular-nums leading-snug">{{ fmt(x.price) }}</div>
          <div class="text-[11.5px] font-bold tabular-nums leading-tight whitespace-nowrap" :class="clr(x.change_pct)">
            <template v-if="x.change != null">{{ fmtSigned(x.change) }} </template>{{ fmtSigned(x.change_pct) }}%
          </div>
        </div>
        <svg v-if="x.sparkline?.length > 1" viewBox="0 0 100 24" class="w-11 h-7 shrink-0" preserveAspectRatio="none">
          <path :d="spark(x.sparkline)" fill="none" :stroke="Number(x.change_pct) >= 0 ? '#16A34A' : '#DC2626'" stroke-width="1.5" vector-effect="non-scaling-stroke" />
        </svg>
      </div>
      <div v-if="!indices.length && !loading" class="w-full text-sm text-ink-muted py-5 text-center">시세를 불러오는 중이에요. 잠시 후 다시 확인해 주세요.</div>
    </div>
    <button v-show="canScroll" @click="scrollIdx(1)" aria-label="다음 지수" class="hidden md:flex absolute right-0 top-0 bottom-0 z-10 w-7 items-center justify-center bg-gradient-to-l from-white via-white to-transparent text-ink-muted hover:text-ink">›</button>
  </div>
  <div class="text-[10.5px] text-ink-faint -mt-3.5 mb-5 px-1">주요 지수·지표는 참고용이에요.</div>

  <!-- ── 왼쪽: 증권가 뉴스 / 오른쪽: 내 종목·대표 종목 탭 ───── -->
  <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 mb-6 items-start">

    <!-- 뉴스 (야후 파이낸스 형식: 큰 기사 + 오른쪽 목록 + 작은 카드) -->
    <section class="min-w-0">
      <div class="flex items-center gap-2 mb-3">
        <h2 class="text-[17px] font-extrabold text-ink">증권가 뉴스</h2>
        <span class="ml-auto text-[11px] text-ink-faint">누르면 언론사 원문으로 이동해요</span>
      </div>
      <div v-if="newsLoading" class="card py-10 text-center text-sm text-ink-muted">불러오는 중…</div>
      <div v-else-if="!news.length" class="card py-10 text-center text-sm text-ink-muted">뉴스를 준비하고 있어요. 잠시 후 다시 확인해 주세요.</div>
      <template v-else>
        <!-- 대표 기사 + 오른쪽 목록 -->
        <div class="grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_230px] gap-4 pb-4 border-b border-line">
          <a :href="hero.url" target="_blank" rel="noopener noreferrer" class="group block min-w-0">
            <img v-if="hero.image_url" :src="hero.image_url" alt="" referrerpolicy="no-referrer" class="w-full aspect-[16/9] object-cover rounded-xl bg-gray-100 mb-3" @error="$event.target.style.display = 'none'" />
            <h3 class="text-[22px] leading-snug font-extrabold text-ink group-hover:underline">{{ hero.title }}</h3>
            <p v-if="hero.summary" class="text-[14px] text-ink-light leading-relaxed mt-1.5 line-clamp-3">{{ hero.summary }}</p>
            <div class="text-[11.5px] text-ink-faint mt-1.5">{{ srcLine(hero) }}</div>
          </a>
          <ul class="md:border-l md:border-line md:pl-4 space-y-3.5">
            <li v-for="n in side" :key="n.id">
              <a :href="n.url" target="_blank" rel="noopener noreferrer" class="block group">
                <div class="text-[14px] font-bold text-ink leading-snug group-hover:underline line-clamp-3">{{ n.title }}</div>
                <div class="text-[11px] text-ink-faint mt-0.5">{{ srcLine(n) }}</div>
              </a>
            </li>
          </ul>
        </div>

        <!-- 작은 카드 3개 -->
        <div v-if="cards.length" class="grid grid-cols-1 sm:grid-cols-3 gap-4 py-4 border-b border-line">
          <a v-for="n in cards" :key="n.id" :href="n.url" target="_blank" rel="noopener noreferrer" class="group flex sm:block gap-3 min-w-0">
            <img v-if="n.image_url" :src="n.image_url" alt="" loading="lazy" referrerpolicy="no-referrer" class="w-24 h-[68px] sm:w-full sm:h-auto sm:aspect-[16/10] object-cover rounded-lg bg-gray-100 shrink-0 sm:mb-2" @error="$event.target.style.display = 'none'" />
            <div class="min-w-0">
              <div class="text-[14px] font-bold text-ink leading-snug group-hover:underline line-clamp-3">{{ n.title }}</div>
              <div class="text-[11px] text-ink-faint mt-0.5">{{ srcLine(n) }}</div>
            </div>
          </a>
        </div>
      </template>
      <p class="text-[10.5px] text-ink-faint mt-3">제목과 요약만 보여 주고, 기사 전문은 각 언론사 사이트에서 볼 수 있어요. 영문 기사는 제목을 자동 번역해서 부정확할 수 있어요.</p>
    </section>

    <!-- 오른쪽 탭: 내 관심종목 / 대표 종목 -->
    <aside class="card overflow-hidden lg:sticky lg:top-20">
      <div class="p-2 flex gap-1.5 border-b border-line">
        <button v-for="t in sideTabs" :key="t.v" @click="sideTab = t.v"
          class="flex-1 py-2 rounded-xl text-[13px] font-bold border transition-colors"
          :class="sideTab === t.v ? 'border-[#0B5FFF] text-[#0B5FFF] bg-blue-50/40' : 'border-transparent text-ink-muted hover:bg-gray-50'">{{ t.label }}</button>
      </div>

      <!-- 내 관심종목 -->
      <div v-show="sideTab === 'mine'">
        <form v-if="auth.isLoggedIn" @submit.prevent="submitSearch" class="p-3 relative flex items-center gap-1.5">
          <input v-model="ticker" type="text" maxlength="40" autocomplete="off" placeholder="회사 이름 또는 티커 (예: 애플, AAPL)"
            @input="onTickerInput" @focus="showSuggest = suggestions2.length > 0" @blur="hideSuggestSoon"
            class="input-soft flex-1 min-w-0 !py-1.5 !px-3 !text-xs" />
          <button type="submit" :disabled="adding || !ticker.trim()" class="btn-primary !px-3 !py-1.5 !text-xs disabled:opacity-50 shrink-0">{{ adding ? '확인 중…' : '추가' }}</button>
          <div v-if="showSuggest && (suggestions2.length || searching)" class="absolute left-3 right-3 top-full -mt-1 z-20 bg-white rounded-xl border border-line shadow-lg overflow-hidden">
            <div v-if="searching && !suggestions2.length" class="px-3 py-2.5 text-xs text-ink-muted">찾는 중…</div>
            <button v-for="r in suggestions2" :key="r.symbol" type="button" @mousedown.prevent="pickResult(r)"
              class="w-full text-left px-3 py-2 flex items-center gap-2 hover:bg-amber-50 border-b border-line last:border-0">
              <span class="font-black text-xs text-ink w-14 shrink-0">{{ r.symbol }}</span>
              <span class="min-w-0 flex-1 text-xs text-ink-light truncate">{{ r.name }}<span v-if="r.ko" class="text-ink-faint"> · {{ r.ko }}</span></span>
              <span v-if="watchSet.has(r.symbol)" class="text-[10px] text-amber-600 font-bold shrink-0">담음</span>
            </button>
          </div>
        </form>
        <div v-if="addError" class="px-4 py-2 text-xs text-red-600 bg-red-50">{{ addError }}</div>

        <div v-if="!auth.isLoggedIn" class="px-4 py-8 text-center text-sm text-ink-muted">
          로그인하면 보고 싶은 종목을 티커로 담아 두고, 언제든 지울 수 있어요.
          <div class="mt-3"><RouterLink to="/login" class="btn-primary !px-4 !py-2 !text-xs">로그인</RouterLink></div>
        </div>
        <template v-else>
          <div v-if="!myList.length && !loading" class="px-4 py-6 text-center">
            <p class="text-sm text-ink-muted">아직 담은 종목이 없어요. 회사 이름이나 티커를 검색하거나 아래에서 골라 보세요.</p>
            <div class="flex flex-wrap gap-1.5 justify-center mt-3">
              <button v-for="t in suggestions" :key="t" @click="ticker = t; addTicker()" class="text-xs px-3 py-1.5 rounded-full border border-gray-200 text-ink-light hover:bg-amber-50 hover:border-amber-300 hover:text-amber-700 transition-colors">+ {{ t }}</button>
            </div>
          </div>
          <ul v-else>
            <li v-for="w in myList" :key="w.symbol" class="px-3.5 py-2.5 border-t border-line flex items-center gap-2">
              <div class="min-w-0 flex-1"><div class="font-black text-[13px] text-ink">{{ w.symbol }}</div><div class="text-[11px] text-ink-muted truncate">{{ w.name }}</div></div>
              <svg v-if="w.sparkline?.length > 1" viewBox="0 0 100 24" class="w-11 h-6 shrink-0" preserveAspectRatio="none"><path :d="spark(w.sparkline)" fill="none" :stroke="Number(w.change_pct) >= 0 ? '#16A34A' : '#DC2626'" stroke-width="1.5" vector-effect="non-scaling-stroke" /></svg>
              <div class="text-right tabular-nums w-[78px] shrink-0">
                <div class="text-[13px] font-bold text-ink">{{ w.price != null ? fmt(w.price) : '-' }}</div>
                <div v-if="w.change_pct != null" class="text-[11.5px] font-bold" :class="clr(w.change_pct)">{{ fmtSigned(w.change_pct) }}%</div>
              </div>
              <button @click="removeTicker(w.symbol)" class="text-ink-faint hover:text-red-500 p-1 shrink-0" title="목록에서 지우기"><AppIcon name="x" :size="14" /></button>
            </li>
          </ul>
        </template>
      </div>

      <!-- 대표 종목 -->
      <ul v-show="sideTab === 'top'">
        <li v-for="w in watchlist" :key="w.symbol" class="px-3.5 py-2.5 border-t border-line first:border-0 flex items-center gap-2">
          <div class="min-w-0 flex-1"><div class="font-black text-[13px] text-ink">{{ w.symbol }}</div><div class="text-[11px] text-ink-muted truncate">{{ w.name }}</div></div>
          <svg v-if="w.sparkline?.length > 1" viewBox="0 0 100 24" class="w-11 h-6 shrink-0" preserveAspectRatio="none"><path :d="spark(w.sparkline)" fill="none" :stroke="Number(w.change_pct) >= 0 ? '#16A34A' : '#DC2626'" stroke-width="1.5" vector-effect="non-scaling-stroke" /></svg>
          <div class="text-right tabular-nums w-[78px] shrink-0">
            <div class="text-[13px] font-bold text-ink">{{ fmt(w.price) }}</div>
            <div class="text-[11.5px] font-bold" :class="clr(w.change_pct)">{{ fmtSigned(w.change_pct) }}%</div>
          </div>
        </li>
        <li v-if="!watchlist.length && !loading" class="px-4 py-8 text-center text-sm text-ink-muted">시세를 불러오는 중이에요.</li>
      </ul>
    </aside>
  </div>

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

  <p class="text-[10.5px] text-ink-faint mt-3">시세는 지연될 수 있고 투자 판단의 책임은 본인에게 있어요.</p>
</div>
</div>
</template>

<script setup>
import { ref, computed, reactive, nextTick, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import { useAuthStore } from '../stores/auth'
import AppIcon from '../components/AppIcon.vue'
import PageHeader from '../components/PageHeader.vue'
import MiniChart from '../components/MiniChart.vue'

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
// 회사 이름 검색 (입력이 멈추면 0.3초 뒤 서버에 물어봄)
const suggestions2 = ref([])
const showSuggest = ref(false)
const searching = ref(false)
let searchTimer = null
let searchSeq = 0
function onTickerInput() {
  addError.value = ''
  clearTimeout(searchTimer)
  const text = ticker.value.trim()
  if (!text) { suggestions2.value = []; showSuggest.value = false; return }
  searching.value = true; showSuggest.value = true
  const seq = ++searchSeq
  searchTimer = setTimeout(async () => {
    try {
      const { data } = await axios.get('/api/stocks/search', { params: { q: text } })
      if (seq === searchSeq) suggestions2.value = data.data || []
    } catch { if (seq === searchSeq) suggestions2.value = [] }
    if (seq === searchSeq) searching.value = false
  }, 300)
}
function hideSuggestSoon() { setTimeout(() => { showSuggest.value = false }, 150) }
function pickResult(r) { ticker.value = r.symbol; showSuggest.value = false; addTicker() }
function submitSearch() {
  // 정확히 같은 티커가 결과에 있으면 그것을, 아직 검색 결과가 없고 티커 모양이면 그대로, 아니면 맨 위 결과를 추가
  const text = ticker.value.trim().toUpperCase()
  const exact = suggestions2.value.find(r => r.symbol === text)
  const looksTicker = /^[A-Z0-9.^=-]{1,12}$/.test(text)
  const top = exact || (looksTicker && !suggestions2.value.length ? { symbol: text } : suggestions2.value[0])
  if (top) pickResult(top)
  else addError.value = '찾는 종목이 없어요. 영문 회사 이름이나 티커로 다시 검색해 보세요.'
}
async function addTicker() {
  const t = ticker.value.trim().toUpperCase()
  if (!t || adding.value) return
  adding.value = true; addError.value = ''
  try {
    const { data } = await axios.post('/api/stocks/watchlist', { symbol: t })
    myList.value = data.data || []
    ticker.value = ''; suggestions2.value = []
  } catch (e) { addError.value = e.response?.data?.message || '추가하지 못했어요. 잠시 후 다시 시도해 주세요.' }
  adding.value = false
}
async function removeTicker(symbol) {
  try { const { data } = await axios.delete(`/api/stocks/watchlist/${encodeURIComponent(symbol)}`); myList.value = data.data || [] } catch {}
}

// 오른쪽 탭
const sideTabs = [{ v: 'mine', label: '내 관심종목' }, { v: 'top', label: '대표 종목' }]
const sideTab = ref('mine')

// 지수 띠 좌우 이동
const idxEl = ref(null)
const canScroll = ref(false)
function updateScroll() { const el = idxEl.value; canScroll.value = !!el && el.scrollWidth > el.clientWidth + 4 }
function scrollIdx(dir) { idxEl.value?.scrollBy({ left: dir * 340, behavior: 'smooth' }) }

// 증권가 뉴스: 대표 기사 1 + 오른쪽 목록 4 + 작은 카드 3 (그림 있는 기사를 앞쪽 카드에 우선 배치)
const news = ref([])
const newsLoading = ref(true)
const hero = computed(() => news.value.find(n => n.image_url) || news.value[0])
const cards = computed(() => news.value.filter(n => n.image_url && n !== hero.value).slice(0, 3))
const side = computed(() => news.value.filter(n => n !== hero.value && !cards.value.includes(n)).slice(0, 4))
function srcLine(n) {
  return `${n.source}${n.lang === 'en' ? ' · 영문 기사(제목 번역)' : n.lang === 'en_raw' ? ' · 영문 기사' : ''} · ${timeAgo(n.published_at)}`
}
function timeAgo(t) {
  const m = Math.max(0, Math.round((Date.now() - new Date(t).getTime()) / 60000))
  if (m < 60) return `${m || 1}분 전`
  if (m < 1440) return `${Math.floor(m / 60)}시간 전`
  return `${Math.floor(m / 1440)}일 전`
}
async function loadNews() {
  try { const { data } = await axios.get('/api/stock-news', { params: { limit: 20 } }); news.value = data.data || [] } catch {}
  newsLoading.value = false
}

onMounted(async () => {
  tickClock(); clockTimer = setInterval(tickClock, 30000)
  loadEarnings(0)
  loadNews()
  loadMine()
  try {
    const { data } = await axios.get('/api/market-quotes')
    indices.value = data.data?.indices || []
    watchlist.value = data.data?.watchlist || []
  } catch {}
  loading.value = false
  await nextTick(); updateScroll()
})
onUnmounted(() => clearInterval(clockTimer))
</script>
