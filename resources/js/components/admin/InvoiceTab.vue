<template>
<!-- ───────── 휴대폰 화면 ───────── -->
<div v-if="mobile" class="alv-m space-y-3 pb-4">
  <p class="text-[13px] text-ink-muted leading-relaxed px-0.5">결제가 끝나면 자동으로 만들어지는 영수증이에요. 날짜는 미국 동부시간(ET) 기준이에요.</p>

  <!-- 요약 -->
  <div class="grid grid-cols-2 gap-2">
    <div class="bg-white border border-gray-100 rounded-2xl px-3.5 py-3"><div class="text-[13px] text-ink-muted">발행됨</div><div class="text-[22px] font-black tabular-nums text-green-600">{{ summary.issued }}건</div><div class="text-[12px] text-ink-faint">{{ usdCents(summary.issued_cents) }}</div></div>
    <div class="bg-white border border-gray-100 rounded-2xl px-3.5 py-3"><div class="text-[13px] text-ink-muted">무효</div><div class="text-[22px] font-black tabular-nums text-red-600">{{ summary.void }}건</div></div>
  </div>
  <button v-if="summary.mail_failed > 0" @click="setMail('failed')" class="w-full text-left min-h-[52px] px-3.5 py-2.5 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-[15px]">
    메일이 안 나간 인보이스가 <b>{{ summary.mail_failed }}건</b> 있어요. <span class="underline font-bold">보기</span>
  </button>

  <!-- 자동 발송 -->
  <div class="bg-white border border-gray-100 rounded-2xl px-3.5 py-3 flex items-center justify-between gap-3">
    <div class="min-w-0"><div class="text-[15px] font-bold text-ink">결제 후 메일 자동 발송</div><div class="text-[12px] text-ink-muted">{{ autoMail ? '켜져 있어요' : '꺼져 있어요 (인보이스만 만들어져요)' }}</div></div>
    <button v-if="canManage" @click="toggleAuto" :disabled="togglingAuto" role="switch" :aria-checked="autoMail" aria-label="결제 후 메일 자동 발송"
      class="shrink-0 min-h-[44px] min-w-[64px] px-4 rounded-full border text-[15px] font-bold disabled:opacity-50" :class="autoMail ? 'bg-green-500 text-white border-green-500' : 'bg-white text-ink border-gray-300'">{{ autoMail ? '켬' : '끔' }}</button>
  </div>

  <!-- 기간 -->
  <div class="flex gap-2 overflow-x-auto scrollbar-hide" role="group" aria-label="기간">
    <button v-for="r in ranges" :key="r.key" @click="setRange(r.key)" :aria-pressed="range === r.key"
      class="shrink-0 min-h-[44px] px-4 rounded-full border text-[15px]"
      :class="range === r.key ? 'bg-ink text-white border-ink font-bold' : 'bg-white text-ink border-gray-200 font-medium'">{{ r.label }}</button>
  </div>
  <div v-if="range === 'custom'" class="grid grid-cols-2 gap-2">
    <label class="block"><span class="block text-[12px] text-ink-muted mb-1">시작일</span><input type="date" v-model="from" @change="reload" class="w-full min-h-[48px] rounded-xl border border-gray-200 bg-white px-3" /></label>
    <label class="block"><span class="block text-[12px] text-ink-muted mb-1">종료일</span><input type="date" v-model="to" @change="reload" class="w-full min-h-[48px] rounded-xl border border-gray-200 bg-white px-3" /></label>
  </div>
  <div class="grid grid-cols-2 gap-2">
    <select v-model="status" @change="reload" aria-label="인보이스 상태" class="min-h-[48px] bg-white border border-gray-200 rounded-xl px-3 text-ink">
      <option value="">전체 상태</option><option value="issued">발행됨</option><option value="void">무효</option>
    </select>
    <select v-model="mail" @change="reload" aria-label="메일 상태" class="min-h-[48px] bg-white border border-gray-200 rounded-xl px-3 text-ink">
      <option value="">메일 전체</option><option value="sent">발송됨</option><option value="failed">발송 실패</option><option value="queued">발송 대기</option><option value="none">안 보냄</option>
    </select>
  </div>
  <form @submit.prevent="reload" class="flex gap-2">
    <label class="flex-1 min-w-0 flex items-center gap-2 bg-white border border-gray-200 rounded-xl px-3 min-h-[48px] text-ink-muted">
      <AppIcon name="search" :size="18" />
      <input v-model="search" type="search" placeholder="번호·이름·이메일 검색" autocomplete="off" class="w-full min-w-0 bg-transparent outline-none text-ink" />
    </label>
    <button type="submit" class="shrink-0 min-h-[48px] px-4 rounded-xl bg-amber-500 text-white text-[15px] font-bold">검색</button>
  </form>

  <div class="text-[13px] text-ink-muted px-0.5">{{ loading ? '불러오는 중...' : `${total.toLocaleString()}건` }}</div>
  <p v-if="loadError" class="bg-red-50 text-red-600 text-[14px] rounded-xl p-3">{{ loadError }} <button class="font-bold underline" @click="load()">다시 시도</button></p>

  <button v-for="i in items" :key="i.id" @click="openId = i.id"
    class="w-full text-left bg-white border border-gray-100 rounded-2xl p-3.5 min-h-[76px] active:bg-amber-50">
    <span class="flex items-center gap-1.5 flex-wrap">
      <span class="text-[12px] font-bold px-2 py-0.5 rounded-md border" :class="INVOICE_STATUS[i.status]?.cls">{{ INVOICE_STATUS[i.status]?.text || i.status }}</span>
      <span class="text-[12px] font-bold px-2 py-0.5 rounded-md border" :class="mailChip(i.delivery).cls">{{ mailChip(i.delivery).text }}</span>
      <span class="ml-auto text-[12px] text-ink-faint">{{ fmtShort(i.issued_at) }}</span>
    </span>
    <span class="flex items-end justify-between gap-3 mt-1.5">
      <span class="min-w-0"><span class="block text-[15px] font-bold text-ink truncate">{{ i.billing_name || '-' }}</span><span class="block text-[13px] text-ink-muted truncate">{{ i.billing_email }}</span></span>
      <span class="shrink-0 text-[18px] font-black tabular-nums text-ink">{{ usdCents(i.total_cents) }}</span>
    </span>
    <span class="block text-[12px] font-mono text-ink-faint mt-1 break-all">{{ i.invoice_no }}</span>
  </button>
  <div v-if="!items.length && !loading && !loadError" class="text-center text-ink-muted py-10 text-[15px]">해당 조건의 인보이스가 없어요</div>

  <div v-if="lastPage > 1" class="flex items-center justify-between gap-2 pt-1">
    <button @click="goPage(page - 1)" :disabled="page <= 1" class="min-h-[48px] px-5 rounded-xl bg-white border border-gray-200 text-[15px] font-bold disabled:opacity-40">이전</button>
    <span class="text-[14px] text-ink-muted tabular-nums">{{ page }} / {{ lastPage }}</span>
    <button @click="goPage(page + 1)" :disabled="page >= lastPage" class="min-h-[48px] px-5 rounded-xl bg-white border border-gray-200 text-[15px] font-bold disabled:opacity-40">다음</button>
  </div>

  <InvoiceDetail v-if="openId" :invoice-id="openId" :mobile="true" @close="openId = 0" @changed="load()" />
  <div v-if="toast" class="fixed left-1/2 -translate-x-1/2 z-[80] max-w-[90vw] px-4 py-3 rounded-xl text-[15px] font-bold text-white shadow-lg"
    :class="toast.error ? 'bg-red-600' : 'bg-ink'" :style="{ top: 'calc(70px + env(safe-area-inset-top, 0px))' }" role="status">{{ toast.text }}</div>
</div>

<!-- ───────── PC 화면 ───────── -->
<div v-else>
  <p class="text-sm text-ink-muted mb-4">결제가 끝나면 자동으로 만들어지는 영수증이에요. 같은 결제로는 한 장만 만들어지고, 환불되면 자동으로 <b>무효</b>가 돼요. 날짜는 미국 동부시간(ET) 기준이에요.</p>

  <!-- 요약 -->
  <div class="grid grid-cols-4 gap-3 mb-4">
    <div class="card p-3 text-center"><div class="text-xs text-ink-muted">발행됨</div><div class="text-lg font-black text-green-600">{{ summary.issued }}건</div><div class="text-[11px] text-ink-faint">{{ usdCents(summary.issued_cents) }}</div></div>
    <div class="card p-3 text-center"><div class="text-xs text-ink-muted">무효</div><div class="text-lg font-black text-red-600">{{ summary.void }}건</div></div>
    <button class="card p-3 text-center" :class="summary.mail_failed > 0 ? 'border-red-200 bg-red-50/40' : ''" @click="summary.mail_failed > 0 && setMail('failed')">
      <div class="text-xs text-ink-muted">메일 안 나간 것</div><div class="text-lg font-black" :class="summary.mail_failed > 0 ? 'text-red-600' : 'text-ink-faint'">{{ summary.mail_failed }}건</div>
      <div v-if="summary.mail_failed > 0" class="text-[11px] text-red-500 underline">눌러서 보기</div>
    </button>
    <div class="card p-3 flex flex-col items-center justify-center gap-1.5">
      <div class="text-xs text-ink-muted">결제 후 메일 자동 발송</div>
      <button v-if="canManage" @click="toggleAuto" :disabled="togglingAuto" role="switch" :aria-checked="autoMail" aria-label="결제 후 메일 자동 발송"
        class="px-4 py-1.5 rounded-full border text-xs font-bold disabled:opacity-50" :class="autoMail ? 'bg-green-500 text-white border-green-500' : 'bg-white text-ink border-gray-300'">{{ autoMail ? '켜짐' : '꺼짐' }}</button>
      <span v-else class="text-xs font-bold" :class="autoMail ? 'text-green-600' : 'text-ink-muted'">{{ autoMail ? '켜짐' : '꺼짐' }}</span>
    </div>
  </div>

  <!-- 필터 -->
  <div class="flex items-center gap-1.5 flex-wrap mb-3">
    <button v-for="r in ranges" :key="r.key" @click="setRange(r.key)"
      class="px-3 py-1.5 rounded-full text-xs font-bold border transition-colors"
      :class="range === r.key ? 'bg-ink text-white border-ink' : 'bg-white text-ink-light border-gray-200 hover:border-gray-300'">{{ r.label }}</button>
    <template v-if="range === 'custom'">
      <input type="date" v-model="from" @change="reload" class="input-soft !w-auto !px-2 !py-1 text-xs" />
      <span class="text-xs text-ink-muted">~</span>
      <input type="date" v-model="to" @change="reload" class="input-soft !w-auto !px-2 !py-1 text-xs" />
    </template>
    <select v-model="status" @change="reload" class="input-soft !w-auto !px-2.5 !py-1.5 text-xs">
      <option value="">전체 상태</option><option value="issued">발행됨</option><option value="void">무효</option>
    </select>
    <select v-model="mail" @change="reload" class="input-soft !w-auto !px-2.5 !py-1.5 text-xs">
      <option value="">메일 전체</option><option value="sent">발송됨</option><option value="failed">발송 실패</option><option value="queued">발송 대기</option><option value="none">안 보냄</option>
    </select>
    <input v-model="search" @keyup.enter="reload" type="text" placeholder="번호/이름/이메일 검색" class="input-soft !w-auto !px-3 !py-1.5 text-xs" />
    <button @click="reload" class="btn-primary !px-3 !py-1.5 text-xs">검색</button>
  </div>

  <div v-if="loadError" class="card py-8 text-center text-sm text-red-600">{{ loadError }} <button class="font-bold underline ml-1" @click="load()">다시 시도</button></div>
  <div v-else-if="loading" class="text-center py-8 text-ink-muted">로딩중...</div>
  <div v-else-if="!items.length" class="card py-12 text-center text-sm text-ink-faint">해당 조건의 인보이스가 없습니다</div>
  <div v-else class="card overflow-x-auto">
    <table class="w-full text-sm min-w-[760px]">
      <thead class="bg-gray-50 border-b border-gray-100"><tr>
        <SortTh class="px-3 py-2 text-left text-xs text-ink-muted" k="invoice_no" :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">인보이스 번호</SortTh>
        <SortTh class="px-3 py-2 text-left text-xs text-ink-muted" k="billing_name" :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">받는 분</SortTh>
        <SortTh class="px-3 py-2 text-right text-xs text-ink-muted" k="total" :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">금액</SortTh>
        <SortTh class="px-3 py-2 text-left text-xs text-ink-muted" k="status" :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">상태</SortTh>
        <th class="px-3 py-2 text-left text-xs text-ink-muted">메일</th>
        <SortTh class="px-3 py-2 text-left text-xs text-ink-muted" k="issued_at" :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">발행일(ET)</SortTh>
        <th class="px-3 py-2 text-left text-xs text-ink-muted">관리</th>
      </tr></thead>
      <tbody>
        <tr v-for="i in items" :key="i.id" class="border-b border-gray-50 last:border-0 hover:bg-amber-50/30 transition-colors cursor-pointer" @click="openId = i.id">
          <td class="px-3 py-2.5 font-mono text-xs text-ink-light whitespace-nowrap">{{ i.invoice_no }}<div class="text-[10px] text-ink-faint font-sans">주문 #{{ i.payment_id }}</div></td>
          <td class="px-3 py-2.5"><div class="text-sm text-ink">{{ i.billing_name || '-' }}</div><div class="text-[11px] text-ink-faint">{{ i.billing_email }}</div></td>
          <td class="px-3 py-2.5 text-right font-bold text-ink tabular-nums">{{ usdCents(i.total_cents) }}</td>
          <td class="px-3 py-2.5"><span class="text-[11px] font-bold px-1.5 py-0.5 rounded border" :class="INVOICE_STATUS[i.status]?.cls">{{ INVOICE_STATUS[i.status]?.text || i.status }}</span></td>
          <td class="px-3 py-2.5"><span class="text-[11px] font-bold px-1.5 py-0.5 rounded border" :class="mailChip(i.delivery).cls" :title="i.delivery?.error || ''">{{ mailChip(i.delivery).text }}</span></td>
          <td class="px-3 py-2.5 text-xs text-ink-light whitespace-nowrap">{{ fmtShort(i.issued_at) }}</td>
          <td class="px-3 py-2.5"><button class="text-xs text-blue-600 hover:underline" @click.stop="openId = i.id">상세</button></td>
        </tr>
      </tbody>
    </table>
  </div>
  <Pagination :page="page" :lastPage="lastPage" @page="goPage" />

  <InvoiceDetail v-if="openId" :invoice-id="openId" :mobile="false" @close="openId = 0" @changed="load()" />
  <div v-if="toast" class="fixed left-1/2 -translate-x-1/2 bottom-8 z-[80] px-4 py-2.5 rounded-xl text-sm font-bold text-white shadow-lg"
    :class="toast.error ? 'bg-red-600' : 'bg-ink'" role="status">{{ toast.text }}</div>
</div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import AppIcon from '../AppIcon.vue'
import SortTh from '../SortTh.vue'
import Pagination from '../Pagination.vue'
import InvoiceDetail from './InvoiceDetail.vue'
import { useAdminSort } from '../../composables/useAdminSort'
import { usdCents, INVOICE_STATUS, mailChip } from '../../composables/useInvoiceUi'

defineProps({ mobile: { type: Boolean, default: false } })

// 표 머리글을 눌러 정렬 (기본: 번호 내림차순 = 가장 마지막 번호가 위)
const { sortKey, sortDir, toggleSort, sortParams } = useAdminSort(() => { page.value = 1; load() })

const ranges = [
  { key: '', label: '전체' }, { key: 'today', label: '오늘' }, { key: '7d', label: '7일' }, { key: '30d', label: '30일' },
  { key: 'month', label: '이번 달' }, { key: 'year', label: '올해' }, { key: 'custom', label: '직접 선택' },
]
const range = ref('')
const from = ref('')
const to = ref('')
const status = ref('')
const mail = ref('')
const search = ref('')

const items = ref([])
const summary = ref({ issued: 0, issued_cents: 0, void: 0, mail_failed: 0 })
const autoMail = ref(true)
const canManage = ref(false)
const loading = ref(true)
const loadError = ref('')
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const openId = ref(0)
const togglingAuto = ref(false)
const toast = ref(null)
let toastTimer = null
function say(text, error = false) {
  toast.value = { text, error }
  clearTimeout(toastTimer)
  toastTimer = setTimeout(() => { toast.value = null }, 3500)
}

const fmtShort = d => d ? new Date(d).toLocaleString('en-US', { timeZone: 'America/New_York', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : ''

async function load(p = page.value) {
  loading.value = true
  loadError.value = ''
  try {
    const params = { ...sortParams.value, page: p }
    if (status.value) params.status = status.value
    if (mail.value) params.mail = mail.value
    if (search.value.trim()) params.search = search.value.trim()
    if (range.value) params.range = range.value
    if (range.value === 'custom') { params.from = from.value; params.to = to.value }
    const { data } = await axios.get('/api/admin/invoices', { params })
    items.value = data.data?.data || []
    page.value = data.data?.current_page || 1
    lastPage.value = data.data?.last_page || 1
    total.value = data.data?.total ?? items.value.length
    if (data.summary) summary.value = data.summary
    if (data.meta) { autoMail.value = !!data.meta.auto_mail; canManage.value = !!data.meta.can_manage }
  } catch (e) {
    loadError.value = e.response?.data?.message || '인보이스 목록을 불러오지 못했어요.'
  }
  loading.value = false
}
function reload() { page.value = 1; load(1) }
function goPage(n) { if (n >= 1 && n <= lastPage.value) load(n) }
function setRange(k) {
  range.value = k
  if (k === 'custom' && !from.value) { const d = new Date().toISOString().slice(0, 10); from.value = d; to.value = d }
  reload()
}
function setMail(v) { mail.value = v; reload() }

async function toggleAuto() {
  if (togglingAuto.value) return
  togglingAuto.value = true
  try {
    const { data } = await axios.post('/api/admin/invoices/auto-mail', { enabled: !autoMail.value })
    autoMail.value = !!data.data?.auto_mail
    say(data.message || '바꿨어요')
  } catch (e) {
    say(e.response?.data?.message || '바꾸지 못했어요', true)
  } finally { togglingAuto.value = false }
}

onMounted(() => load(1))
onBeforeUnmount(() => clearTimeout(toastTimer))
</script>
<style>
/* 휴대폰 관리자: 입력창 글자가 16px 보다 작으면 iOS 가 화면을 확대해 버림 */
.alv-m input, .alv-m textarea, .alv-m select { font-size: 16px; }
</style>
