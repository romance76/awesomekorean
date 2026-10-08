<template>
<!-- ───────── 휴대폰 화면 ───────── -->
<div v-if="isMobile" class="alv-m space-y-3 pb-4">
  <p class="text-[13px] text-ink-muted leading-relaxed px-0.5">카드로 <b>포인트를 산 금액</b>과 <b>달러로 직접 결제한 금액</b>을 나눠서 보여 줘요. 날짜는 미국 동부시간(ET) 기준이에요.</p>

  <!-- 기간 -->
  <div class="flex gap-2 overflow-x-auto scrollbar-hide" role="group" aria-label="기간">
    <button v-for="r in ranges" :key="r.key" @click="setRange(r.key)" :aria-pressed="range === r.key"
      class="shrink-0 min-h-[44px] px-4 rounded-full border text-[15px]"
      :class="range === r.key ? 'bg-ink text-white border-ink font-bold' : 'bg-white text-ink border-gray-200 font-medium'">{{ r.label }}</button>
  </div>
  <div v-if="range === 'custom'" class="grid grid-cols-2 gap-2">
    <label class="block"><span class="block text-[12px] text-ink-muted mb-1">시작일</span><input type="date" v-model="from" @change="loadAll" class="w-full min-h-[48px] rounded-xl border border-gray-200 bg-white px-3" /></label>
    <label class="block"><span class="block text-[12px] text-ink-muted mb-1">종료일</span><input type="date" v-model="to" @change="loadAll" class="w-full min-h-[48px] rounded-xl border border-gray-200 bg-white px-3" /></label>
  </div>

  <div v-if="!sum" class="text-center py-10 text-ink-muted text-[15px]">불러오는 중...</div>
  <template v-else>
    <!-- 전체 결제액 -->
    <div class="rounded-2xl border-2 border-emerald-200 bg-emerald-50/50 px-4 py-3.5">
      <div class="text-[13px] text-ink-muted">전체 결제액</div>
      <div class="text-[32px] leading-tight font-black tabular-nums text-emerald-600">{{ usd(sum.total.net) }}</div>
      <div class="text-[13px] text-ink-faint">{{ sum.total.count }}건 · 환불 제외</div>
    </div>
    <div class="grid grid-cols-2 gap-2">
      <div class="bg-white border border-gray-100 rounded-2xl px-3.5 py-3">
        <div class="text-[13px] text-ink-muted">포인트 구매</div>
        <div class="text-[22px] font-black tabular-nums text-blue-600">{{ usd(sum.points.net) }}</div>
        <div class="text-[12px] text-ink-faint">{{ sum.points.count }}건 · 카드로 충전</div>
      </div>
      <div class="bg-white border border-gray-100 rounded-2xl px-3.5 py-3">
        <div class="text-[13px] text-ink-muted">달러 직접 결제</div>
        <div class="text-[22px] font-black tabular-nums text-rose-600">{{ usd(sum.direct.net) }}</div>
        <div class="text-[12px] text-ink-faint">{{ sum.direct.count }}건</div>
      </div>
      <div class="bg-white border border-gray-100 rounded-2xl px-3.5 py-3">
        <div class="text-[13px] text-ink-muted">승인 대기 (카드 보류)</div>
        <div class="text-[22px] font-black tabular-nums text-amber-600">{{ usd(sum.held.amount) }}</div>
        <div class="text-[12px] text-ink-faint">{{ sum.held.count }}건 · 승인하면 청구</div>
      </div>
      <div class="bg-white border border-gray-100 rounded-2xl px-3.5 py-3">
        <div class="text-[13px] text-ink-muted">기간 내 환불</div>
        <div class="text-[22px] font-black tabular-nums text-red-600">{{ usd(sum.refunded) }}</div>
      </div>
    </div>
    <div class="bg-white border border-gray-100 rounded-2xl px-3.5 py-2.5 text-[14px] space-y-1">
      <div class="flex justify-between gap-3"><span class="text-ink-muted">경품 이벤트 의뢰</span><b class="tabular-nums">{{ usd(sum.direct.by_kind.event_request.net) }}</b></div>
      <div class="flex justify-between gap-3"><span class="text-ink-muted">NEW 전면광고</span><b class="tabular-nums">{{ usd(sum.direct.by_kind.flyer.net) }}</b></div>
    </div>

    <!-- 일별 결제액 (막대를 누르면 그날 금액이 아래에 나와요) -->
    <div v-if="sum.series.length" class="bg-white border border-gray-100 rounded-2xl p-3.5">
      <div class="flex items-center justify-between gap-2 flex-wrap mb-2">
        <div class="text-[15px] font-bold text-ink">일별 결제액</div>
        <div class="flex items-center gap-3 text-[12px] text-ink-muted">
          <span><i class="inline-block w-2.5 h-2.5 rounded-sm bg-blue-400 mr-1"></i>포인트 구매</span>
          <span><i class="inline-block w-2.5 h-2.5 rounded-sm bg-rose-400 mr-1"></i>달러 직접 결제</span>
        </div>
      </div>
      <div class="overflow-x-auto scrollbar-hide">
        <div class="flex items-end gap-[2px] h-32 min-w-full" role="group" aria-label="일별 결제액 막대 그래프">
          <button v-for="d in sum.series" :key="d.date" type="button" @click="selDay = d.date" :aria-label="`${d.date} 포인트 ${usd(d.points)} 직접 ${usd(d.direct)}`"
            class="flex-1 min-w-[8px] h-full flex flex-col justify-end rounded-sm" :class="selDay === d.date ? 'bg-amber-100' : ''">
            <span class="block bg-rose-400 rounded-t-[2px]" :style="{ height: barHm(d.direct) }"></span>
            <span class="block bg-blue-400" :style="{ height: barHm(d.points) }"></span>
          </button>
        </div>
      </div>
      <div class="flex justify-between text-[11px] text-ink-faint mt-1"><span>{{ sum.series[0].date }}</span><span>{{ sum.series[sum.series.length - 1].date }}</span></div>
      <div class="mt-2 rounded-xl bg-gray-50 px-3 py-2 text-[14px] min-h-[44px] flex items-center">
        <template v-if="selDayData"><span class="text-ink-muted mr-2 shrink-0">{{ selDayData.date }}</span><span class="text-blue-600 font-bold mr-2">포인트 {{ usd(selDayData.points) }}</span><span class="text-rose-600 font-bold mr-2">직접 {{ usd(selDayData.direct) }}</span><b class="ml-auto tabular-nums">{{ usd(selDayData.points + selDayData.direct) }}</b></template>
        <span v-else class="text-ink-faint">막대를 누르면 그날 금액이 나와요</span>
      </div>
    </div>
  </template>

  <!-- 결제 내역 -->
  <div class="text-[15px] font-bold text-ink px-0.5 pt-1">결제 내역</div>
  <div class="flex gap-2 overflow-x-auto scrollbar-hide" role="group" aria-label="결제 종류">
    <button v-for="k in kinds" :key="k.key" @click="kind = k.key; page = 1; loadList()" :aria-pressed="kind === k.key"
      class="shrink-0 min-h-[44px] px-4 rounded-full border text-[15px]"
      :class="kind === k.key ? 'bg-ink text-white border-ink font-bold' : 'bg-white text-ink border-gray-200 font-medium'">{{ k.label }}</button>
  </div>
  <select v-model="status" @change="page = 1; loadList()" aria-label="결제 상태" class="w-full min-h-[48px] bg-white border border-gray-200 rounded-xl px-3 text-ink">
    <option value="">전체 상태</option>
    <option v-for="(v, k) in STATUS" :key="k" :value="k">{{ v.text }}</option>
  </select>
  <form @submit.prevent="page = 1; loadList()" class="flex gap-2">
    <label class="flex-1 min-w-0 flex items-center gap-2 bg-white border border-gray-200 rounded-xl px-3 min-h-[48px] text-ink-muted">
      <AppIcon name="search" :size="18" />
      <input v-model="search" type="search" placeholder="이름·이메일·내용 검색" autocomplete="off" class="w-full min-w-0 bg-transparent outline-none text-ink" />
    </label>
    <button type="submit" class="shrink-0 min-h-[48px] px-4 rounded-xl bg-amber-500 text-white text-[15px] font-bold">검색</button>
  </form>

  <div v-if="loading" class="text-center py-8 text-ink-muted text-[15px]">불러오는 중...</div>
  <div v-else-if="!items.length" class="text-center py-10 text-ink-muted text-[15px]">해당 조건의 결제 내역이 없어요</div>
  <div v-else class="space-y-2">
    <div v-for="p in items" :key="p.id" class="bg-white border border-gray-100 rounded-2xl p-3.5">
      <div class="flex items-center gap-1.5 flex-wrap">
        <span class="text-[12px] font-bold px-2 py-0.5 rounded-md border" :class="KIND[p.kind]?.cls">{{ KIND[p.kind]?.text || p.kind }}</span>
        <span class="text-[12px] font-bold px-2 py-0.5 rounded-md border" :class="STATUS[p.status]?.cls">{{ STATUS[p.status]?.text || p.status }}</span>
        <span class="ml-auto text-[12px] text-ink-faint">{{ fmt(p.created_at) }}</span>
      </div>
      <div class="flex items-end justify-between gap-3 mt-1.5">
        <div class="min-w-0"><div class="text-[15px] font-bold text-ink truncate">{{ p.user?.nickname || p.user?.name || '-' }}</div><div class="text-[13px] text-ink-muted truncate">{{ p.user?.email }}</div></div>
        <div class="shrink-0 text-right"><div class="text-[18px] font-black tabular-nums text-ink">{{ usd(p.amount) }}</div>
          <div class="text-[12px] tabular-nums" :class="p.net > 0 ? 'text-emerald-600 font-bold' : 'text-ink-faint'">매출 {{ usd(p.net) }}</div></div>
      </div>
      <div class="text-[13px] text-ink-light mt-1 break-words">{{ p.description || (p.kind === 'points' ? `${(p.points_purchased || 0).toLocaleString()}P 구매` : '-') }}</div>
      <div v-if="Number(p.refunded_amount) > 0" class="text-[12px] text-red-500 mt-0.5">환불 {{ usd(p.refunded_amount) }}</div>
    </div>
  </div>
  <div v-if="lastPage > 1" class="flex items-center justify-between gap-2 pt-1">
    <button @click="goPage(page - 1)" :disabled="page <= 1" class="min-h-[48px] px-5 rounded-xl bg-white border border-gray-200 text-[15px] font-bold disabled:opacity-40">이전</button>
    <span class="text-[14px] text-ink-muted tabular-nums">{{ page }} / {{ lastPage }}</span>
    <button @click="goPage(page + 1)" :disabled="page >= lastPage" class="min-h-[48px] px-5 rounded-xl bg-white border border-gray-200 text-[15px] font-bold disabled:opacity-40">다음</button>
  </div>
</div>

<!-- ───────── PC 화면 ───────── -->
<div v-else>
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
import { ref, computed, onMounted, inject } from 'vue'
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

// 관리자 휴대폰 화면이면 카드 + 눌러 보는 막대그래프로 보여 줌 (AdminLayout 이 알려 줌)
const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)
const selDay = ref('')
const selDayData = computed(() => (sum.value?.series || []).find(d => d.date === selDay.value) || null)
// 휴대폰 막대 높이는 퍼센트로 (칸 높이 8rem 안에서 가장 높은 날 기준)
function barHm(v) { return v > 0 ? Math.max(2, Math.round((v / maxDay.value) * 100)) + '%' : '0' }
function goPage(n) { if (n >= 1 && n <= lastPage.value) { page.value = n; loadList() } }

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
<style>
/* 휴대폰 관리자: 입력창 글자가 16px 보다 작으면 iOS 가 화면을 확대해 버림 */
.alv-m input, .alv-m textarea, .alv-m select { font-size: 16px; }
</style>
