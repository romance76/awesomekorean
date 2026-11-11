<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-[300] flex items-end sm:items-center justify-center bg-black/50 p-0 sm:p-4" @click.self="$emit('close')" role="dialog" aria-modal="true" :aria-label="`${symbol} 차트`">
      <div class="bg-white w-full sm:max-w-[720px] rounded-t-2xl sm:rounded-2xl shadow-2xl max-h-[92vh] overflow-y-auto">
        <div class="flex items-start gap-3 px-4 pt-4 pb-2">
          <div class="min-w-0 flex-1">
            <div class="flex items-baseline gap-2 flex-wrap">
              <span class="text-lg font-black text-ink">{{ symbol }}</span>
              <span class="text-xs text-ink-muted truncate">{{ info?.name || name }}</span>
            </div>
            <div v-if="shown" class="flex items-baseline gap-2 mt-0.5 tabular-nums">
              <span class="text-2xl font-extrabold text-ink">{{ money(shown.c) }}</span>
              <span class="text-sm font-bold" :class="up ? 'text-[#16A34A]' : 'text-[#DC2626]'">{{ signed(diff) }} ({{ signed(pct) }}%)</span>
              <span class="text-[11px] text-ink-faint">{{ hover ? fmtTime(shown.t, true) : rangeLabel }}</span>
            </div>
          </div>
          <button type="button" class="p-1.5 rounded-full hover:bg-gray-100 text-ink-muted" aria-label="닫기" @click="$emit('close')">
            <AppIcon name="x" :size="18" />
          </button>
        </div>

        <div class="px-4 flex gap-1 flex-wrap">
          <button v-for="r in ranges" :key="r.v" type="button" @click="setRange(r.v)"
            class="px-3 py-1 rounded-full text-xs font-bold border transition-colors"
            :class="range === r.v ? 'bg-ink text-white border-ink' : 'bg-white text-ink-light border-gray-200 hover:bg-gray-50'">{{ r.label }}</button>
        </div>

        <div class="px-2 sm:px-4 pt-2 pb-1">
          <div v-if="loading" class="h-[260px] flex items-center justify-center text-sm text-ink-muted">차트를 불러오는 중…</div>
          <div v-else-if="error" class="h-[260px] flex flex-col items-center justify-center gap-2 text-sm text-ink-muted">
            {{ error }}
            <button type="button" class="btn-secondary !px-3 !py-1.5 !text-xs" @click="load">다시 시도</button>
          </div>
          <svg v-else ref="svgEl" :viewBox="`0 0 ${W} ${H}`" class="w-full h-[260px] touch-none select-none" preserveAspectRatio="none"
            @pointermove="onMove" @pointerdown="onMove" @pointerleave="hover = null">
            <g v-for="g in grid" :key="g.y">
              <line :x1="PAD_L" :x2="W - PAD_R" :y1="g.y" :y2="g.y" stroke="#E5E7EB" stroke-width="1" vector-effect="non-scaling-stroke" />
            </g>
            <path :d="area" :fill="color" fill-opacity="0.10" />
            <path :d="line" fill="none" :stroke="color" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
            <g v-if="hover">
              <line :x1="hover.x" :x2="hover.x" :y1="PAD_T" :y2="H - PAD_B" stroke="#9CA3AF" stroke-dasharray="3 3" stroke-width="1" vector-effect="non-scaling-stroke" />
              <circle :cx="hover.x" :cy="hover.y" r="4" :fill="color" stroke="#fff" stroke-width="2" vector-effect="non-scaling-stroke" />
            </g>
          </svg>
          <!-- 가격 눈금은 SVG 가 늘어나도 글자가 안 찌그러지게 HTML 로 -->
          <div v-if="!loading && !error" class="relative -mt-[260px] h-[260px] pointer-events-none">
            <span v-for="g in grid" :key="'l' + g.y" class="absolute right-1 text-[10px] text-ink-faint tabular-nums bg-white/70 px-0.5 rounded"
              :style="{ top: `calc(${(g.y / H) * 100}% - 7px)` }">{{ money(g.v) }}</span>
          </div>
        </div>

        <div v-if="info && !loading && !error" class="px-4 pb-4 pt-1 grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-2 text-xs">
          <div v-for="s in stats" :key="s.k"><div class="text-ink-faint">{{ s.k }}</div><div class="font-bold text-ink tabular-nums">{{ s.v }}</div></div>
        </div>
        <p class="px-4 pb-4 text-[10.5px] text-ink-faint">시세는 약간 늦을 수 있고 참고용이에요. 시각은 미국 동부 기준이에요.</p>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import AppIcon from './AppIcon.vue'

const props = defineProps({ symbol: { type: String, required: true }, name: { type: String, default: '' } })
const emit = defineEmits(['close'])

const ranges = [
  { v: '1d', label: '1일' }, { v: '5d', label: '5일' }, { v: '1mo', label: '1개월' },
  { v: '6mo', label: '6개월' }, { v: '1y', label: '1년' }, { v: '5y', label: '5년' },
]
const range = ref('1mo')
const info = ref(null)
const loading = ref(true)
const error = ref('')
const hover = ref(null)
const svgEl = ref(null)

const W = 640, H = 260, PAD_L = 4, PAD_R = 4, PAD_T = 12, PAD_B = 12

const points = computed(() => info.value?.points || [])
const bounds = computed(() => {
  const cs = points.value.map(p => p.c)
  if (!cs.length) return { min: 0, max: 1 }
  let min = Math.min(...cs), max = Math.max(...cs)
  const pad = (max - min) * 0.06 || max * 0.01 || 1
  return { min: min - pad, max: max + pad }
})
const xy = computed(() => {
  const n = points.value.length
  const { min, max } = bounds.value
  return points.value.map((p, i) => [
    PAD_L + (n === 1 ? 0 : (i / (n - 1)) * (W - PAD_L - PAD_R)),
    PAD_T + (1 - (p.c - min) / (max - min)) * (H - PAD_T - PAD_B),
  ])
})
const line = computed(() => xy.value.map(([x, y], i) => `${i ? 'L' : 'M'} ${x.toFixed(1)} ${y.toFixed(1)}`).join(' '))
const area = computed(() => xy.value.length ? `${line.value} L ${xy.value.at(-1)[0].toFixed(1)} ${H - PAD_B} L ${xy.value[0][0].toFixed(1)} ${H - PAD_B} Z` : '')
const grid = computed(() => {
  const { min, max } = bounds.value
  return [0, 1, 2, 3].map(i => {
    const f = i / 3
    return { y: PAD_T + f * (H - PAD_T - PAD_B), v: max - f * (max - min) }
  })
})

// 1일 차트는 전일 종가, 나머지는 기간 첫 값을 기준으로 오르내림을 계산
const baseline = computed(() => (range.value === '1d' && info.value?.prev_close) ? info.value.prev_close : points.value[0]?.c)
const shown = computed(() => hover.value ? points.value[hover.value.i] : points.value.at(-1))
const diff = computed(() => shown.value && baseline.value != null ? shown.value.c - baseline.value : 0)
const pct = computed(() => baseline.value ? (diff.value / baseline.value) * 100 : 0)
const up = computed(() => diff.value >= 0)
const color = computed(() => {
  const last = points.value.at(-1)?.c
  return baseline.value != null && last != null && last < baseline.value ? '#DC2626' : '#16A34A'
})
const rangeLabel = computed(() => ({ '1d': '오늘', '5d': '최근 5일', '1mo': '최근 1개월', '6mo': '최근 6개월', '1y': '최근 1년', '5y': '최근 5년' }[range.value]))

const money = v => v == null ? '-' : Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const signed = v => (v > 0 ? '+' : '') + Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
function fmtTime(t, full) {
  const d = new Date(t * 1000)
  const intraday = range.value === '1d' || range.value === '5d'
  const o = intraday || full
    ? { timeZone: 'America/New_York', month: 'numeric', day: 'numeric', ...(intraday ? { hour: 'numeric', minute: '2-digit' } : { year: 'numeric' }) }
    : { timeZone: 'America/New_York', year: 'numeric', month: 'numeric', day: 'numeric' }
  return d.toLocaleString('ko-KR', o)
}
const stats = computed(() => {
  const i = info.value || {}
  const vol = i.volume != null ? Intl.NumberFormat('en-US', { notation: 'compact' }).format(i.volume) : '-'
  return [
    { k: '전일 종가', v: money(i.prev_close) },
    { k: '오늘 고가 / 저가', v: `${money(i.day_high)} / ${money(i.day_low)}` },
    { k: '52주 고가 / 저가', v: `${money(i.week52_high)} / ${money(i.week52_low)}` },
    { k: '거래량', v: vol },
  ]
})

function onMove(e) {
  const el = svgEl.value
  if (!el || !xy.value.length) return
  const r = el.getBoundingClientRect()
  const x = ((e.clientX - r.left) / r.width) * W
  let best = 0, bd = Infinity
  xy.value.forEach(([px], i) => { const d = Math.abs(px - x); if (d < bd) { bd = d; best = i } })
  hover.value = { i: best, x: xy.value[best][0], y: xy.value[best][1] }
}

let seq = 0
async function load() {
  const my = ++seq
  loading.value = true; error.value = ''; hover.value = null
  try {
    const { data } = await axios.get('/api/stocks/chart', { params: { symbol: props.symbol, range: range.value } })
    if (my !== seq) return
    info.value = data.data
  } catch (e) {
    if (my !== seq) return
    error.value = e.response?.data?.message || '차트를 불러오지 못했어요.'
  } finally {
    if (my === seq) loading.value = false
  }
}
function setRange(v) { if (v !== range.value) { range.value = v; load() } }

const onKey = e => { if (e.key === 'Escape') emit('close') }
onMounted(() => { load(); window.addEventListener('keydown', onKey); document.body.style.overflow = 'hidden' })
onBeforeUnmount(() => { window.removeEventListener('keydown', onKey); document.body.style.overflow = '' })
</script>
