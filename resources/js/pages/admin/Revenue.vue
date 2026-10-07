<template>
<div>
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-1">
    <span class="icon-chip w-9 h-9 bg-emerald-50 text-emerald-600"><AppIcon name="wallet" :size="20" /></span>
    매출/결제 현황
  </h1>
  <p class="text-sm text-ink-muted mb-4">카드로 <b>포인트를 산 금액</b>과 <b>달러로 직접 결제한 금액</b>(경품 이벤트 의뢰 · NEW 전면광고)을 구분해서 보여주고, 합친 전체 결제액도 확인합니다. 날짜는 미국 동부시간(ET) 기준이에요.</p>

  <!-- 기간 -->
  <div class="flex items-center gap-1.5 flex-wrap mb-4">
    <button v-for="r in ranges" :key="r.key" @click="setRange(r.key)"
      class="px-3 py-1.5 rounded-full text-xs font-bold border transition-colors"
      :class="range === r.key ? 'bg-ink text-white border-ink' : 'bg-white text-ink-light border-gray-200 hover:border-gray-300'">{{ r.label }}</button>
    <template v-if="range === 'custom'">
      <input type="date" v-model="from" @change="loadAll" class="input-soft !w-auto !px-2 !py-1 text-xs" />
      <span class="text-xs text-ink-muted">~</span>
      <input type="date" v-model="to" @change="loadAll" class="input-soft !w-auto !px-2 !py-1 text-xs" />
    </template>
  </div>

  <div v-if="!sum" class="text-center py-10 text-ink-muted">로딩중...</div>
  <template v-else>
    <!-- 요약 카드 -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
      <div class="card p-4 border-2 border-emerald-200 bg-emerald-50/40">
        <div class="text-xs text-ink-muted">전체 결제액</div>
        <div class="text-2xl font-black text-emerald-600 mt-1">{{ usd(sum.total.net) }}</div>
        <div class="text-[11px] text-ink-faint mt-0.5">{{ sum.total.count }}건 · 환불 제외</div>
      </div>
      <div class="card p-4">
        <div class="text-xs text-ink-muted">포인트 구매</div>
        <div class="text-2xl font-black text-blue-600 mt-1">{{ usd(sum.points.net) }}</div>
        <div class="text-[11px] text-ink-faint mt-0.5">{{ sum.points.count }}건 · 카드로 포인트 충전</div>
      </div>
      <div class="card p-4">
        <div class="text-xs text-ink-muted">달러 직접 결제</div>
        <div class="text-2xl font-black text-rose-600 mt-1">{{ usd(sum.direct.net) }}</div>
        <div class="text-[11px] text-ink-faint mt-0.5">{{ sum.direct.count }}건</div>
        <div class="text-[11px] text-ink-muted mt-1.5 space-y-0.5">
          <div class="flex justify-between"><span>경품 이벤트 의뢰</span><b>{{ usd(sum.direct.by_kind.event_request.net) }}</b></div>
          <div class="flex justify-between"><span>NEW 전면광고</span><b>{{ usd(sum.direct.by_kind.flyer.net) }}</b></div>
        </div>
      </div>
      <div class="card p-4">
        <div class="text-xs text-ink-muted">승인 대기 중(카드 보류)</div>
        <div class="text-2xl font-black text-amber-600 mt-1">{{ usd(sum.held.amount) }}</div>
        <div class="text-[11px] text-ink-faint mt-0.5">{{ sum.held.count }}건 · 승인하면 청구, 반려하면 해제</div>
        <div class="text-[11px] text-red-500 mt-1.5">기간 내 환불 {{ usd(sum.refunded) }}</div>
      </div>
    </div>

    <!-- 일별 추이 -->
    <div v-if="sum.series.length" class="card p-4 mb-4">
      <div class="flex items-center justify-between mb-2">
        <div class="text-sm font-bold text-ink">일별 결제액</div>
        <div class="flex items-center gap-3 text-[11px] text-ink-muted">
          <span><i class="inline-block w-2.5 h-2.5 rounded-sm bg-blue-400 mr-1"></i>포인트 구매</span>
          <span><i class="inline-block w-2.5 h-2.5 rounded-sm bg-rose-400 mr-1"></i>달러 직접 결제</span>
        </div>
      </div>
      <div class="flex items-end gap-px h-28" role="img" aria-label="일별 결제액 막대 그래프">
        <div v-for="d in sum.series" :key="d.date" class="flex-1 flex flex-col justify-end min-w-[3px] group relative" :title="`${d.date}  포인트 ${usd(d.points)} · 직접 ${usd(d.direct)}`">
          <div class="bg-rose-400" :style="{ height: barH(d.direct) }"></div>
          <div class="bg-blue-400" :style="{ height: barH(d.points) }"></div>
        </div>
      </div>
      <div class="flex justify-between text-[10px] text-ink-faint mt-1"><span>{{ sum.series[0].date }}</span><span>{{ sum.series[sum.series.length - 1].date }}</span></div>
    </div>
  </template>

  <!-- 내역 필터 -->
  <div class="flex items-center gap-2 flex-wrap mb-3">
    <button v-for="k in kinds" :key="k.key" @click="kind = k.key; page = 1; loadList()"
      class="px-3 py-1.5 rounded-full text-xs font-bold border transition-colors"
      :class="kind === k.key ? 'bg-ink text-white border-ink' : 'bg-white text-ink-light border-gray-200'">{{ k.label }}</button>
    <select v-model="status" @change="page = 1; loadList()" class="input-soft !w-auto !px-2.5 !py-1.5 text-xs">
      <option value="">전체 상태</option>
      <option v-for="(v, k) in STATUS" :key="k" :value="k">{{ v.text }}</option>
    </select>
    <input v-model="search" @keyup.enter="page = 1; loadList()" type="text" placeholder="이름/이메일/내용 검색" class="input-soft !w-auto !px-3 !py-1.5 text-xs" />
    <button @click="page = 1; loadList()" class="btn-primary !px-3 !py-1.5 text-xs">검색</button>
  </div>

  <div v-if="loading" class="text-center py-8 text-ink-muted">로딩중...</div>
  <div v-else-if="!items.length" class="card py-12 text-center text-sm text-ink-faint">해당 조건의 결제 내역이 없습니다</div>
  <div v-else class="card overflow-x-auto">
    <table class="w-full text-sm min-w-[720px]">
      <thead class="bg-gray-50 border-b border-gray-100"><tr>
        <th class="px-3 py-2 text-left text-xs text-ink-muted">날짜(ET)</th>
        <th class="px-3 py-2 text-left text-xs text-ink-muted">구분</th>
        <th class="px-3 py-2 text-left text-xs text-ink-muted">결제자</th>
        <th class="px-3 py-2 text-left text-xs text-ink-muted">내용</th>
        <th class="px-3 py-2 text-right text-xs text-ink-muted">결제액</th>
        <th class="px-3 py-2 text-right text-xs text-ink-muted">매출 반영</th>
        <th class="px-3 py-2 text-left text-xs text-ink-muted">상태</th>
      </tr></thead>
      <tbody>
        <tr v-for="p in items" :key="p.id" class="border-b border-gray-50 last:border-0">
          <td class="px-3 py-2 text-xs text-ink-light whitespace-nowrap">{{ fmt(p.created_at) }}</td>
          <td class="px-3 py-2"><span class="text-[11px] font-bold px-1.5 py-0.5 rounded border" :class="KIND[p.kind]?.cls">{{ KIND[p.kind]?.text || p.kind }}</span></td>
          <td class="px-3 py-2"><div class="text-ink">{{ p.user?.nickname || p.user?.name || '-' }}</div><div class="text-[11px] text-ink-faint">{{ p.user?.email }}</div></td>
          <td class="px-3 py-2 text-xs text-ink-light">{{ p.description || (p.kind === 'points' ? `${(p.points_purchased || 0).toLocaleString()}P 구매` : '-') }}</td>
          <td class="px-3 py-2 text-right font-bold">{{ usd(p.amount) }}<div v-if="Number(p.refunded_amount) > 0" class="text-[10px] text-red-500 font-normal">환불 {{ usd(p.refunded_amount) }}</div></td>
          <td class="px-3 py-2 text-right font-bold" :class="p.net > 0 ? 'text-emerald-600' : 'text-ink-faint'">{{ usd(p.net) }}</td>
          <td class="px-3 py-2"><span class="text-[11px] font-bold px-1.5 py-0.5 rounded border" :class="STATUS[p.status]?.cls">{{ STATUS[p.status]?.text || p.status }}</span></td>
        </tr>
      </tbody>
    </table>
  </div>
  <Pagination :page="page" :lastPage="lastPage" @page="p => { page = p; loadList() }" />
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
import Pagination from '../../components/Pagination.vue'

const ranges = [
  { key: 'today', label: '오늘' }, { key: '7d', label: '7일' }, { key: '30d', label: '30일' },
  { key: 'month', label: '이번 달' }, { key: 'year', label: '올해' }, { key: 'all', label: '전체' }, { key: 'custom', label: '직접 선택' },
]
const kinds = [
  { key: '', label: '전체' }, { key: 'points', label: '포인트 구매' }, { key: 'direct', label: '달러 직접 결제' },
  { key: 'event_request', label: '경품 이벤트 의뢰' }, { key: 'flyer', label: 'NEW 전면광고' },
]
const KIND = {
  points: { text: '포인트 구매', cls: 'bg-blue-50 text-blue-600 border-blue-200' },
  event_request: { text: '경품 이벤트 의뢰', cls: 'bg-rose-50 text-rose-600 border-rose-200' },
  flyer: { text: 'NEW 전면광고', cls: 'bg-orange-50 text-orange-600 border-orange-200' },
}
const STATUS = {
  completed: { text: '완료', cls: 'bg-green-50 text-green-700 border-green-200' },
  captured: { text: '청구 완료', cls: 'bg-green-50 text-green-700 border-green-200' },
  authorized: { text: '승인 대기(보류)', cls: 'bg-amber-50 text-amber-700 border-amber-200' },
  pending: { text: '결제 진행 중', cls: 'bg-gray-100 text-gray-500 border-gray-200' },
  released: { text: '보류 해제', cls: 'bg-gray-100 text-gray-500 border-gray-200' },
  refunded: { text: '환불', cls: 'bg-red-50 text-red-600 border-red-200' },
  failed: { text: '실패', cls: 'bg-red-50 text-red-600 border-red-200' },
}

const range = ref('30d')
const from = ref('')
const to = ref('')
const kind = ref('')
const status = ref('')
const search = ref('')
const sum = ref(null)
const items = ref([])
const loading = ref(true)
const page = ref(1)
const lastPage = ref(1)

const maxDay = computed(() => Math.max(1, ...(sum.value?.series || []).map(d => d.points + d.direct)))
function barH(v) { return v > 0 ? Math.max(2, Math.round((v / maxDay.value) * 112)) + 'px' : '0' }
const usd = v => '$' + Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const fmt = d => new Date(d).toLocaleString('en-US', { timeZone: 'America/New_York', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })

function params(extra = {}) {
  const p = { range: range.value, ...extra }
  if (range.value === 'custom') { p.from = from.value; p.to = to.value }
  return p
}
async function loadSummary() {
  try { sum.value = (await axios.get('/api/admin/revenue/summary', { params: params() })).data.data } catch {}
}
async function loadList() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/admin/revenue/list', { params: params({ kind: kind.value || undefined, status: status.value || undefined, search: search.value || undefined, page: page.value }) })
    items.value = data.data.data
    lastPage.value = data.data.last_page
  } catch {}
  loading.value = false
}
function loadAll() { page.value = 1; loadSummary(); loadList() }
function setRange(k) {
  range.value = k
  if (k === 'custom' && !from.value) { const d = new Date().toISOString().slice(0, 10); from.value = d; to.value = d }
  loadAll()
}
onMounted(loadAll)
</script>
