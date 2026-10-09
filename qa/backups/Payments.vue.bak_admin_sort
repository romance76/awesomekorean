<template>
<!-- ───────── 휴대폰 화면 ───────── -->
<div v-if="isMobile" class="alv-m space-y-3 pb-4">
  <!-- 요약 -->
  <div class="grid grid-cols-2 gap-2">
    <div class="bg-white border border-gray-100 rounded-2xl px-3.5 py-3"><div class="text-[13px] text-ink-muted">이번 달 매출</div><div class="text-[22px] font-black tabular-nums text-amber-600">${{ stats.monthRevenue }}</div></div>
    <div class="bg-white border border-gray-100 rounded-2xl px-3.5 py-3"><div class="text-[13px] text-ink-muted">총 매출</div><div class="text-[22px] font-black tabular-nums text-green-600">${{ stats.totalRevenue }}</div></div>
    <div class="bg-white border border-gray-100 rounded-2xl px-3.5 py-3"><div class="text-[13px] text-ink-muted">총 주문</div><div class="text-[22px] font-black tabular-nums text-blue-600">{{ stats.totalOrders }}건</div></div>
    <div class="bg-white border border-gray-100 rounded-2xl px-3.5 py-3"><div class="text-[13px] text-ink-muted">환불</div><div class="text-[22px] font-black tabular-nums text-red-600">{{ stats.totalRefunds }}건</div></div>
  </div>

  <!-- 상태 필터 -->
  <div class="flex gap-2 overflow-x-auto scrollbar-hide" role="group" aria-label="주문 상태">
    <button v-for="f in statusChips" :key="f.v" @click="setStatus(f.v)" :aria-pressed="filterStatus === f.v"
      class="shrink-0 min-h-[44px] px-4 rounded-full border text-[15px]"
      :class="filterStatus === f.v ? 'bg-ink text-white border-ink font-bold' : 'bg-white text-ink border-gray-200 font-medium'">{{ f.l }}</button>
  </div>

  <!-- 검색 -->
  <form @submit.prevent="search" class="flex gap-2">
    <label class="flex-1 min-w-0 flex items-center gap-2 bg-white border border-gray-200 rounded-xl px-3 min-h-[48px] text-ink-muted">
      <AppIcon name="search" :size="18" />
      <input v-model="searchQ" type="search" placeholder="이름·이메일 검색" autocomplete="off" class="w-full min-w-0 bg-transparent outline-none text-ink" />
    </label>
    <button type="submit" class="shrink-0 min-h-[48px] px-4 rounded-xl bg-amber-500 text-white text-[15px] font-bold">검색</button>
  </form>

  <div class="text-[13px] text-ink-muted px-0.5">{{ loading ? '불러오는 중...' : `${total.toLocaleString()}건` }}</div>
  <p v-if="loadError" class="bg-red-50 text-red-600 text-[14px] rounded-xl p-3">불러오지 못했어요. <button class="font-bold underline" @click="load()">다시 시도</button></p>

  <!-- 주문 카드 -->
  <button v-for="item in items" :key="item.id" @click="openSheet(item)"
    class="w-full text-left bg-white border border-gray-100 rounded-2xl p-3.5 min-h-[76px] flex items-start gap-3 active:bg-amber-50">
    <span class="min-w-0 flex-1">
      <span class="flex items-baseline gap-2">
        <span class="text-[20px] font-black tabular-nums text-ink">${{ Number(item.amount).toFixed(2) }}</span>
        <span class="text-[14px] font-bold tabular-nums text-blue-600">{{ (item.points_purchased || 0).toLocaleString() }}P</span>
      </span>
      <span class="block text-[15px] text-ink truncate mt-0.5">{{ item.user?.name || '-' }}</span>
      <span class="block text-[13px] text-ink-muted truncate">{{ item.user?.email }}</span>
      <span class="block text-[13px] text-ink-faint mt-0.5">#{{ item.id }} · {{ formatDate(item.created_at) }}</span>
    </span>
    <span class="shrink-0 text-[12px] px-2.5 py-1 rounded-full font-bold" :class="statusClass(item.status)">{{ statusLabel(item.status) }}</span>
  </button>
  <div v-if="!items.length && !loading" class="text-center text-ink-muted py-12 text-[15px]">결제 내역이 없어요</div>

  <div v-if="lastPage > 1" class="flex items-center justify-between gap-2 pt-1">
    <button @click="goPage(page - 1)" :disabled="page <= 1" class="min-h-[48px] px-5 rounded-xl bg-white border border-gray-200 text-[15px] font-bold disabled:opacity-40">이전</button>
    <span class="text-[14px] text-ink-muted tabular-nums">{{ page }} / {{ lastPage }}</span>
    <button @click="goPage(page + 1)" :disabled="page >= lastPage" class="min-h-[48px] px-5 rounded-xl bg-white border border-gray-200 text-[15px] font-bold disabled:opacity-40">다음</button>
  </div>

  <!-- 주문 상세 / 환불 확인 시트 -->
  <Teleport to="body">
    <div v-if="detailItem" class="alv-m fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="closeSheet">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2 max-h-[90vh] overflow-y-auto" role="dialog" aria-modal="true"
        :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>

        <!-- 환불 확인 -->
        <div v-if="refunding" class="space-y-3">
          <div class="text-[17px] font-bold text-ink">주문 #{{ detailItem.id }} 환불 처리</div>
          <ul class="text-[15px] text-ink-light space-y-1.5 list-disc pl-5">
            <li v-if="detailItem.stripe_payment_id">카드로 결제한 <b class="text-ink">${{ Number(detailItem.amount).toFixed(2) }}</b>를 Stripe로 <b class="text-ink">자동 환불</b>해요</li>
            <li v-else>결제 번호가 없는 옛 주문이라 <b class="text-ink">카드 환불은 하지 않아요</b></li>
            <li>구매자에게서 <b class="text-ink">{{ (detailItem.points_purchased || 0).toLocaleString() }}P</b>를 회수해요</li>
            <li>주문 상태가 <b class="text-ink">'환불됨'</b>으로 바뀌어요</li>
          </ul>
          <p class="bg-red-50 border border-red-200 text-red-700 text-[14px] rounded-xl p-3 leading-relaxed">
            카드 환불은 되돌릴 수 없어요. 이미 Stripe 대시보드에서 환불했다면 그 부분은 건너뛰고 포인트·상태만 정리해요. 카드사에 따라 구매자에게 돌아가기까지 영업일 5~10일 걸려요.
          </p>
          <button @click="doRefund" :disabled="busy" class="w-full min-h-[52px] rounded-xl bg-red-500 text-white text-[16px] font-bold disabled:opacity-50">{{ busy ? '처리 중...' : '환불 처리하기' }}</button>
          <button @click="refunding = false" :disabled="busy" class="w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">돌아가기</button>
        </div>

        <!-- 주문 상세 -->
        <div v-else class="space-y-3">
          <div class="flex items-center justify-between gap-2">
            <div class="text-[17px] font-bold text-ink">주문 #{{ detailItem.id }}</div>
            <span class="text-[12px] px-2.5 py-1 rounded-full font-bold" :class="statusClass(detailItem.status)">{{ statusLabel(detailItem.status) }}</span>
          </div>
          <div class="bg-gray-50 rounded-2xl p-3.5 space-y-2.5 text-[15px]">
            <div class="flex justify-between gap-3"><span class="text-ink-muted shrink-0">구매자</span><span class="text-right min-w-0"><b class="block text-ink truncate">{{ detailItem.user?.name || '-' }}</b><span class="block text-[13px] text-ink-muted truncate">{{ detailItem.user?.email }}</span></span></div>
            <div class="flex justify-between gap-3"><span class="text-ink-muted shrink-0">주문일</span><span class="text-ink text-right">{{ formatDate(detailItem.created_at) }}</span></div>
            <div class="flex justify-between gap-3"><span class="text-ink-muted shrink-0">상품</span><span class="text-ink text-right">{{ detailItem.description || '포인트 구매' }}</span></div>
            <div class="flex justify-between gap-3"><span class="text-ink-muted shrink-0">포인트</span><b class="text-blue-600 tabular-nums">{{ (detailItem.points_purchased || 0).toLocaleString() }}P</b></div>
            <div class="flex justify-between gap-3"><span class="text-ink-muted shrink-0">금액</span><b class="text-amber-600 tabular-nums text-[17px]">${{ Number(detailItem.amount).toFixed(2) }}</b></div>
          </div>
          <div v-if="detailItem.stripe_payment_id" class="text-[13px] text-ink-faint break-all">Stripe ID: {{ detailItem.stripe_payment_id }}</div>
          <button v-if="detailItem.status === 'completed' && canRefund" @click="refunding = true" class="w-full min-h-[52px] rounded-xl bg-red-50 text-red-600 text-[16px] font-bold">환불하기</button>
          <button @click="closeSheet" class="w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">닫기</button>
        </div>
      </div>
    </div>
    <div v-if="toast" class="alv-m fixed left-1/2 -translate-x-1/2 z-[80] max-w-[90vw] px-4 py-3 rounded-xl text-[15px] font-bold text-white shadow-lg"
      :class="toast.error ? 'bg-red-600' : 'bg-ink'" :style="{ top: 'calc(70px + env(safe-area-inset-top, 0px))' }" role="status">{{ toast.text }}</div>
  </Teleport>
</div>

<!-- ───────── PC 화면 ───────── -->
<div v-else>
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-4">
    <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="wallet" :size="20" /></span>
    오더/결제 관리
  </h1>

  <!-- 필터 -->
  <div class="flex gap-2 mb-4 flex-wrap">
    <select v-model="filterStatus" @change="load" class="input-soft !w-auto !px-3 !py-1.5 text-sm">
      <option value="">전체 상태</option>
      <option value="completed">완료</option>
      <option value="pending">대기</option>
      <option value="refunded">환불됨</option>
      <option value="cancelled">취소됨</option>
    </select>
    <input v-model="searchQ" @keyup.enter="load" type="text" placeholder="이름/이메일 검색..." class="input-soft !w-auto !px-3 !py-1.5 text-sm" />
    <button @click="load" class="btn-primary !px-4 !py-1.5 text-sm"><AppIcon name="search" :size="14" /> 검색</button>
  </div>

  <!-- 통계 -->
  <div class="grid grid-cols-4 gap-3 mb-4">
    <div class="card p-3 text-center">
      <div class="text-xs text-ink-muted">총 매출</div>
      <div class="text-lg font-black text-green-600">${{ stats.totalRevenue }}</div>
    </div>
    <div class="card p-3 text-center">
      <div class="text-xs text-ink-muted">총 주문</div>
      <div class="text-lg font-black text-blue-600">{{ stats.totalOrders }}건</div>
    </div>
    <div class="card p-3 text-center">
      <div class="text-xs text-ink-muted">환불</div>
      <div class="text-lg font-black text-red-600">{{ stats.totalRefunds }}건</div>
    </div>
    <div class="card p-3 text-center">
      <div class="text-xs text-ink-muted">이번 달</div>
      <div class="text-lg font-black text-amber-600">${{ stats.monthRevenue }}</div>
    </div>
  </div>

  <div v-if="loading" class="text-center py-8 text-ink-muted">로딩중...</div>
  <div v-else-if="!items.length" class="py-16 text-center">
    <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="wallet" :size="28" :stroke-width="1.5" /></div>
    <p class="text-sm text-ink-muted">결제 내역 없음</p>
  </div>
  <div v-else class="card overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 border-b border-gray-100"><tr>
        <th class="px-3 py-2 text-left text-xs text-ink-muted">주문번호</th>
        <th class="px-3 py-2 text-left text-xs text-ink-muted">유저</th>
        <th class="px-3 py-2 text-left text-xs text-ink-muted">금액</th>
        <th class="px-3 py-2 text-left text-xs text-ink-muted">포인트</th>
        <th class="px-3 py-2 text-left text-xs text-ink-muted">상태</th>
        <th class="px-3 py-2 text-left text-xs text-ink-muted">날짜</th>
        <th class="px-3 py-2 text-left text-xs text-ink-muted">관리</th>
      </tr></thead>
      <tbody>
        <tr v-for="item in items" :key="item.id" class="border-b border-gray-50 last:border-0 hover:bg-amber-50/30 transition-colors">
          <td class="px-3 py-2.5 font-mono text-xs text-ink-light">#{{ item.id }}</td>
          <td class="px-3 py-2.5">
            <div class="text-sm text-ink">{{ item.user?.name || '-' }}</div>
            <div class="text-[11px] text-ink-faint">{{ item.user?.email }}</div>
          </td>
          <td class="px-3 py-2.5 text-amber-600 font-bold">${{ Number(item.amount).toFixed(2) }}</td>
          <td class="px-3 py-2.5 font-bold text-blue-600">{{ item.points_purchased?.toLocaleString() }}P</td>
          <td class="px-3 py-2.5">
            <span class="text-xs px-2 py-0.5 rounded-full font-bold" :class="{
              'bg-green-100 text-green-700': item.status==='completed',
              'bg-amber-100 text-amber-700': item.status==='pending',
              'bg-red-100 text-red-700': item.status==='refunded',
              'bg-gray-200 text-gray-500': item.status==='cancelled'
            }">{{ statusLabel(item.status) }}</span>
          </td>
          <td class="px-3 py-2.5 text-xs text-ink-faint">{{ formatDate(item.created_at) }}</td>
          <td class="px-3 py-2.5">
            <div class="flex gap-1">
              <button @click="showDetail(item)" class="text-xs text-blue-600 hover:underline transition-colors">상세</button>
              <button v-if="item.status==='completed'" @click="refundOrder(item)" class="text-xs text-red-600 hover:underline transition-colors">환불</button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- 상세/인보이스 모달 -->
  <div v-if="detailItem" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="detailItem=null">
    <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden">
      <!-- 인보이스 헤더 -->
      <div class="bg-gradient-to-r from-amber-400 to-amber-500 px-6 py-4 text-white">
        <div class="flex justify-between items-start">
          <div>
            <div class="text-xs opacity-80">INVOICE</div>
            <div class="text-2xl font-black">#{{ detailItem.id }}</div>
          </div>
          <div class="text-right">
            <div class="font-bold">AwesomeKorean</div>
            <div class="text-xs opacity-80">awesomekorean.com</div>
          </div>
        </div>
      </div>

      <div class="px-6 py-4 space-y-4">
        <!-- 구매자 정보 -->
        <div class="flex justify-between">
          <div>
            <div class="text-xs text-ink-muted">구매자</div>
            <div class="font-bold text-sm text-ink">{{ detailItem.user?.name }}</div>
            <div class="text-xs text-ink-faint">{{ detailItem.user?.email }}</div>
          </div>
          <div class="text-right">
            <div class="text-xs text-ink-muted">주문일</div>
            <div class="text-sm text-ink-light">{{ formatDate(detailItem.created_at) }}</div>
          </div>
        </div>

        <!-- 주문 내용 -->
        <table class="w-full text-sm border-t border-gray-100">
          <thead><tr class="border-b border-gray-100 bg-gray-50">
            <th class="py-2 px-3 text-left text-xs text-ink-muted">상품</th>
            <th class="py-2 px-3 text-right text-xs text-ink-muted">포인트</th>
            <th class="py-2 px-3 text-right text-xs text-ink-muted">금액</th>
          </tr></thead>
          <tbody>
            <tr class="border-b border-gray-50">
              <td class="py-2 px-3 text-ink-light">포인트 구매</td>
              <td class="py-2 px-3 text-right font-bold text-blue-600">{{ detailItem.points_purchased?.toLocaleString() }}P</td>
              <td class="py-2 px-3 text-right font-bold text-ink">${{ Number(detailItem.amount).toFixed(2) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="font-bold">
              <td class="py-2 px-3 text-ink">합계</td>
              <td class="py-2 px-3 text-right text-blue-600">{{ detailItem.points_purchased?.toLocaleString() }}P</td>
              <td class="py-2 px-3 text-right text-amber-600">${{ Number(detailItem.amount).toFixed(2) }}</td>
            </tr>
          </tfoot>
        </table>

        <!-- 상태 -->
        <div class="flex items-center justify-between bg-gray-50 rounded-lg px-4 py-2">
          <span class="text-xs text-ink-muted">결제 상태</span>
          <span class="text-xs px-2 py-0.5 rounded-full font-bold" :class="{
            'bg-green-100 text-green-700': detailItem.status==='completed',
            'bg-red-100 text-red-700': detailItem.status==='refunded',
            'bg-gray-200 text-gray-500': detailItem.status==='cancelled'
          }">{{ statusLabel(detailItem.status) }}</span>
        </div>

        <div v-if="detailItem.stripe_payment_id" class="text-xs text-ink-faint">
          Stripe ID: {{ detailItem.stripe_payment_id }}
        </div>
      </div>

      <!-- 액션 -->
      <div class="px-6 py-3 border-t border-gray-100 flex gap-2 justify-end">
        <button @click="printInvoice" class="btn-secondary !px-4 !py-2 text-xs"><AppIcon name="download" :size="13" /> 인쇄</button>
        <button v-if="detailItem.status==='completed'" @click="refundOrder(detailItem)" class="inline-flex items-center gap-1.5 text-xs bg-red-500 text-white font-bold px-4 py-2 rounded-xl transition-colors hover:bg-red-600"><AppIcon name="coins" :size="13" /> 환불</button>
        <button @click="detailItem=null" class="btn-primary !px-4 !py-2 text-xs">닫기</button>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount, watch, inject } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
import { useAuthStore } from '../../stores/auth'

// 관리자 휴대폰 화면이면 카드 + 아래에서 올라오는 시트로 보여 줌 (AdminLayout 이 알려 줌)
const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)
const auth = useAuthStore()
// 환불은 서버에서 admin/super_admin 만 허용 — 일반 운영진에게는 버튼을 숨김
const canRefund = computed(() => ['admin', 'super_admin'].includes(auth.user?.role))

const items = ref([])
const loading = ref(true)
const filterStatus = ref('')
const searchQ = ref('')
const detailItem = ref(null)
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const loadError = ref(false)
const refunding = ref(false)
const busy = ref(false)
const toast = ref(null)
let toastTimer = null
function say(text, error = false) {
  toast.value = { text, error }
  clearTimeout(toastTimer)
  toastTimer = setTimeout(() => { toast.value = null }, 3000)
}
const statusChips = [{ v: '', l: '전체' }, { v: 'completed', l: '완료' }, { v: 'pending', l: '대기' }, { v: 'refunded', l: '환불됨' }, { v: 'cancelled', l: '취소됨' }, { v: 'authorized', l: '승인됨' }, { v: 'captured', l: '청구됨' }]
function statusClass(s) {
  return { completed: 'bg-green-100 text-green-700', captured: 'bg-green-100 text-green-700', authorized: 'bg-blue-100 text-blue-700', pending: 'bg-amber-100 text-amber-700', refunded: 'bg-red-100 text-red-700', cancelled: 'bg-gray-200 text-gray-500' }[s] || 'bg-gray-100 text-gray-700'
}
function setStatus(v) { filterStatus.value = v; load(1) }
function search() { load(1) }
function goPage(n) { if (n >= 1 && n <= lastPage.value) load(n) }
function openSheet(item) { detailItem.value = item; refunding.value = false }
function closeSheet() { if (!busy.value) { detailItem.value = null; refunding.value = false } }
async function doRefund() {
  const item = detailItem.value
  if (!item || busy.value) return
  busy.value = true
  try {
    const { data } = await axios.post(`/api/admin/payments/${item.id}/refund`)
    say(data.message || '환불 처리했어요')
    detailItem.value = null; refunding.value = false
    load(page.value)
  } catch (e) { say(e.response?.data?.message || '환불 처리에 실패했어요', true) }
  finally { busy.value = false }
}
// 시트가 열려 있는 동안 뒤쪽 화면이 같이 스크롤되지 않게
watch(() => isMobile.value && !!detailItem.value, locked => { document.body.style.overflow = locked ? 'hidden' : '' })
onBeforeUnmount(() => { document.body.style.overflow = ''; clearTimeout(toastTimer) })
const stats = reactive({ totalRevenue: 0, totalOrders: 0, totalRefunds: 0, monthRevenue: 0 })

function statusLabel(s) { return { completed:'완료', pending:'대기', refunded:'환불됨', cancelled:'취소됨', authorized:'승인됨', captured:'청구됨' }[s] || s }
function formatDate(dt) { return dt ? new Date(dt).toLocaleDateString('ko-KR') + ' ' + new Date(dt).toLocaleTimeString('ko-KR', {hour:'2-digit',minute:'2-digit'}) : '' }

async function load(p = 1) {
  loading.value = true
  loadError.value = false
  try {
    const params = {}
    if (isMobile.value) { params.page = p; params.per_page = 20 }
    if (filterStatus.value) params.status = filterStatus.value
    if (searchQ.value) params.search = searchQ.value
    const { data } = await axios.get('/api/admin/payments', { params })
    items.value = data.data?.data || data.data || []
    page.value = data.data?.current_page || 1
    lastPage.value = data.data?.last_page || 1
    total.value = data.data?.total ?? items.value.length

    // 통계: 서버가 전체 주문 기준으로 계산해 주면 그대로 사용 (예전에는 불러온 목록만 합산해서 50건이 넘으면 틀렸음)
    if (data.stats) {
      stats.totalRevenue = Number(data.stats.totalRevenue).toFixed(2)
      stats.totalOrders = data.stats.totalOrders
      stats.totalRefunds = data.stats.totalRefunds
      stats.monthRevenue = Number(data.stats.monthRevenue).toFixed(2)
      loading.value = false
      return
    }
    // 서버 통계가 없으면 예전 방식으로 계산
    const all = items.value
    stats.totalRevenue = all.filter(i => i.status === 'completed').reduce((s, i) => s + Number(i.amount), 0).toFixed(2)
    stats.totalOrders = all.length
    stats.totalRefunds = all.filter(i => i.status === 'refunded').length
    const thisMonth = new Date().getMonth()
    stats.monthRevenue = all.filter(i => i.status === 'completed' && new Date(i.created_at).getMonth() === thisMonth).reduce((s, i) => s + Number(i.amount), 0).toFixed(2)
  } catch { loadError.value = true }
  loading.value = false
}

function showDetail(item) { detailItem.value = item }

async function refundOrder(item) {
  const cardLine = item.stripe_payment_id ? `카드 결제 금액 $${item.amount}가 Stripe로 자동 환불됩니다(되돌릴 수 없음).` : '결제 번호가 없는 옛 주문이라 카드 환불은 하지 않습니다.'
  if (!confirm(`주문 #${item.id} 환불 처리하시겠습니까?\n${cardLine}\n${item.points_purchased}P가 회수되고 주문이 '환불됨'으로 바뀝니다.`)) return
  try {
    const { data } = await axios.post(`/api/admin/payments/${item.id}/refund`)
    alert(data.message || '환불 완료')
    item.status = 'refunded'
    detailItem.value = null
    load()
  } catch (e) { alert(e.response?.data?.message || '환불 실패') }
}

function printInvoice() { window.print() }

onMounted(load)
</script>
<style>
/* 휴대폰 관리자: 입력창 글자가 16px 보다 작으면 iOS 가 화면을 확대해 버림 */
.alv-m input, .alv-m textarea, .alv-m select { font-size: 16px; }
</style>
