<template>
<!-- ───────── 휴대폰 화면 ───────── -->
<div v-if="isMobile" class="alv-m space-y-3 pb-4">
  <p class="text-[14px] text-ink-muted leading-relaxed">광고주가 시간대를 골라 신청한 전단이에요. 승인하면 카드에 청구되고 예약한 시간에 NEW 게시판 상단에 나가요. 반려하면 청구 없이 시간대가 다시 열려요.</p>

  <div class="flex gap-2 overflow-x-auto scrollbar-hide" role="group" aria-label="전단 상태">
    <button v-for="t in tabs" :key="t.key" @click="status = t.key; page = 1; load()" :aria-pressed="status === t.key"
      class="shrink-0 min-h-[44px] px-4 rounded-full border text-[15px] flex items-center gap-1.5"
      :class="status === t.key ? 'bg-ink text-white border-ink font-bold' : 'bg-white text-ink border-gray-200 font-medium'">
      {{ t.label }}
      <span v-if="t.key === 'pending' && pendingCount" class="min-w-[20px] h-5 px-1.5 rounded-full bg-red-500 text-white text-[12px] font-bold grid place-items-center">{{ pendingCount }}</span>
    </button>
  </div>

  <div v-if="loading" class="text-center py-12 text-ink-muted text-[15px]">불러오는 중...</div>
  <div v-else-if="!items.length" class="text-center py-12 text-ink-muted text-[15px]">해당 상태의 신청이 없어요</div>

  <div v-for="f in items" v-show="!loading" :key="f.id" class="bg-white border border-gray-100 rounded-2xl p-3.5">
    <div class="flex gap-3">
      <a :href="f.image_url" target="_blank" rel="noopener noreferrer" class="shrink-0"><img :src="f.image_url" alt="" loading="lazy" class="w-[88px] h-[116px] object-cover rounded-xl border border-gray-100" /></a>
      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-1.5 flex-wrap">
          <span class="text-[12px] font-bold px-2 py-0.5 rounded-md border" :class="STATUS_LABEL[f.status]?.cls">{{ STATUS_LABEL[f.status]?.text }}</span>
          <span class="text-[12px] font-bold text-rose-600">{{ kindLabel(f.kind) }}</span>
        </div>
        <div class="text-[16px] font-bold text-ink leading-snug break-words mt-1">{{ f.title }}</div>
        <div class="text-[13px] text-ink-muted mt-1 break-words">{{ f.user?.nickname || f.user?.name }} · {{ f.user?.email }}</div>
        <div class="text-[13px] text-ink-muted mt-0.5">{{ f.scope === 'national' ? '🇺🇸 전국' : '📍 ' + stateName(f.region_key) }} ({{ tzLabel(f.tz) }}) · {{ f.hours_count }}시간</div>
        <div class="flex items-center gap-2 flex-wrap mt-1">
          <b class="text-[16px] text-ink tabular-nums">{{ adMoney(f, f.total_price) }}</b>
          <span class="px-1.5 py-0.5 rounded text-[12px] font-bold" :class="f.payment_method === 'card' ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'">{{ payLabel(f) }}</span>
        </div>
      </div>
    </div>
    <p v-if="f.description" class="text-[14px] text-ink-light mt-2 leading-relaxed line-clamp-3 break-words">{{ f.description }}</p>
    <div v-if="f.phone || f.link_url" class="text-[13px] text-ink-muted mt-1.5 space-y-0.5">
      <div v-if="f.phone">📞 {{ f.phone }}</div>
      <a v-if="f.link_url" :href="f.link_url" target="_blank" rel="noopener noreferrer nofollow" class="block text-blue-600 underline break-all min-h-[32px]">{{ f.link_url }}</a>
    </div>
    <button v-if="f.schedule?.length" @click="toggleSchedule(f.id)" class="mt-2 min-h-[44px] w-full rounded-xl border border-gray-200 text-[14px] font-bold text-ink-light">
      {{ openSchedule.has(f.id) ? '방송 일정 접기' : `방송 일정 보기 (${f.schedule.length}일)` }}
    </button>
    <div v-if="openSchedule.has(f.id)" class="mt-2 bg-gray-50 rounded-xl p-3 text-[13px] text-ink-light space-y-1">
      <div v-for="sc in f.schedule" :key="sc.date" class="flex gap-2"><span class="shrink-0 w-24 text-ink-muted">{{ fmtDay(sc.date) }}</span><span class="min-w-0">{{ hourRanges(sc.hours).join(', ') }}</span></div>
    </div>
    <div v-if="f.status === 'approved'" class="text-[13px] text-ink-faint mt-1.5">노출 {{ f.view_count }} · 클릭 {{ f.click_count }}</div>
    <div v-if="f.reject_reason" class="text-[13px] text-red-500 mt-1.5 break-words">사유: {{ f.reject_reason }}</div>

    <div v-if="f.status === 'pending'" class="grid grid-cols-2 gap-2 mt-3">
      <button @click="askApprove(f)" class="min-h-[50px] rounded-xl bg-emerald-500 text-white text-[16px] font-bold">승인</button>
      <button @click="askReject(f)" class="min-h-[50px] rounded-xl bg-red-50 text-red-600 text-[16px] font-bold">반려</button>
    </div>
    <div v-else-if="f.status === 'approved'" class="mt-3 space-y-2">
      <button @click="askReject(f)" class="w-full min-h-[50px] rounded-xl bg-red-50 text-red-600 text-[15px] font-bold">게시 중단 (남은 시간 환불)</button>
      <RouterLink :to="`/new/${f.id}`" class="flex items-center justify-center min-h-[44px] rounded-xl border border-gray-200 text-[14px] font-bold text-ink-light">전단 보기</RouterLink>
    </div>
  </div>

  <div v-if="lastPage > 1" class="flex items-center justify-between gap-2 pt-1">
    <button @click="goPage(page - 1)" :disabled="page <= 1" class="min-h-[48px] px-5 rounded-xl bg-white border border-gray-200 text-[15px] font-bold disabled:opacity-40">이전</button>
    <span class="text-[14px] text-ink-muted tabular-nums">{{ page }} / {{ lastPage }}</span>
    <button @click="goPage(page + 1)" :disabled="page >= lastPage" class="min-h-[48px] px-5 rounded-xl bg-white border border-gray-200 text-[15px] font-bold disabled:opacity-40">다음</button>
  </div>

  <!-- 승인 확인 / 반려 사유 시트 -->
  <Teleport to="body">
    <div v-if="sheet" class="alv-m fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="closeSheet">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2 max-h-[92vh] overflow-y-auto" role="dialog" aria-modal="true"
        :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>
        <div v-if="sheet.mode === 'approve'" class="space-y-3">
          <div class="text-[17px] font-bold text-ink">전단을 승인할까요?</div>
          <p class="text-[15px] text-ink-light leading-relaxed break-words">‘{{ sheet.f.title }}’<br />
            <template v-if="sheet.f.payment_method === 'card'">승인하면 광고주의 카드에 <b class="text-ink">{{ adMoney(sheet.f, sheet.f.total_price) }}</b>가 청구돼요. (이미 지난 시간이 있으면 그만큼 뺀 금액만 청구)</template>
            <template v-else>포인트로 먼저 결제된 신청이에요. 승인하면 예약한 시간에 방송돼요.</template></p>
          <button @click="doApprove" :disabled="busy" class="w-full min-h-[52px] rounded-xl bg-emerald-500 text-white text-[16px] font-bold disabled:opacity-50">{{ busy ? '처리 중...' : '승인하기' }}</button>
          <button @click="closeSheet" :disabled="busy" class="w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">취소</button>
        </div>
        <div v-else class="space-y-3">
          <div class="text-[17px] font-bold text-ink">{{ sheet.f.status === 'pending' ? '전단 반려' : '게시 중단' }}</div>
          <p class="text-[14px] text-ink-muted break-words">‘{{ sheet.f.title }}’ — {{ sheet.f.status === 'pending' ? (sheet.f.payment_method === 'card' ? '청구 없이 취소되고 시간대가 다시 열려요.' : '전액 환불되고 시간대가 다시 열려요.') : '남은 시간만큼 환불돼요.' }} 사유는 광고주에게 전달돼요.</p>
          <textarea v-model="reason" rows="3" maxlength="200" placeholder="사유" aria-label="사유" class="w-full rounded-xl border border-gray-200 px-3 py-3"></textarea>
          <button @click="doReject" :disabled="busy" class="w-full min-h-[52px] rounded-xl bg-red-500 text-white text-[16px] font-bold disabled:opacity-50">{{ busy ? '처리 중...' : (sheet.f.status === 'pending' ? '반려하기' : '게시 중단하기') }}</button>
          <button @click="closeSheet" :disabled="busy" class="w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">취소</button>
        </div>
      </div>
    </div>
    <div v-if="msg" class="alv-m fixed left-1/2 -translate-x-1/2 z-[80] max-w-[90vw] px-4 py-3 rounded-xl text-[15px] font-bold text-white shadow-lg"
      :class="msgOk ? 'bg-ink' : 'bg-red-600'" :style="{ top: 'calc(70px + env(safe-area-inset-top, 0px))' }" role="status">{{ msg }}</div>
  </Teleport>
</div>

<!-- ───────── PC 화면 ───────── -->
<div v-else>
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-2">
    <span class="icon-chip w-9 h-9 bg-rose-50 text-rose-600"><AppIcon name="megaphone" :size="20" /></span>
    NEW 전단 광고 관리
  </h1>
  <p class="text-sm text-ink-muted mb-4">광고주가 시간대를 골라 신청한 전단입니다. 승인하면 광고주의 카드에 청구되고 예약한 시간에 NEW 게시판 상단에 방송됩니다. 반려하면 청구 없이 카드 보류가 풀리고 시간 슬롯이 다시 열려요. (가격은 센트 단위 — 가격/할인 센터의 flyer_* 항목 · 결제 내역은 매출/결제 현황)</p>

  <div class="flex gap-1.5 mb-4 flex-wrap">
    <button v-for="t in tabs" :key="t.key" @click="status = t.key; page = 1; load()"
      class="px-3 py-1.5 rounded-full text-xs font-bold border transition-colors"
      :class="status === t.key ? 'bg-ink text-white border-ink' : 'bg-white text-ink-light border-gray-200'">
      {{ t.label }}<span v-if="t.key === 'pending' && pendingCount" class="ml-1 bg-rose-500 text-white rounded-full px-1.5">{{ pendingCount }}</span>
    </button>
  </div>

  <div v-if="loading" class="text-center py-12 text-ink-muted">로딩중...</div>
  <div v-else-if="!items.length" class="text-center py-12 text-sm text-ink-faint card">해당 상태의 신청이 없습니다</div>
  <div v-else class="space-y-3">
    <div v-for="f in items" :key="f.id" class="card p-4 flex gap-4">
      <a :href="f.image_url" target="_blank" class="flex-shrink-0"><img :src="f.image_url" alt="" class="w-24 h-32 object-cover rounded-lg border border-gray-100" /></a>
      <div class="flex-1 min-w-0 text-sm">
        <div class="flex items-center gap-2 flex-wrap">
          <span class="text-[11px] font-bold px-1.5 py-0.5 rounded border" :class="STATUS_LABEL[f.status]?.cls">{{ STATUS_LABEL[f.status]?.text }}</span>
          <span class="text-xs font-bold text-rose-600">{{ kindLabel(f.kind) }}</span>
          <span class="font-bold text-ink">{{ f.title }}</span>
        </div>
        <div class="text-xs text-ink-muted mt-1">
          {{ f.user?.nickname || f.user?.name }} ({{ f.user?.email }}) · {{ f.scope === 'national' ? '🇺🇸 전국' : '📍 ' + stateName(f.region_key) }} ({{ tzLabel(f.tz) }}) · {{ f.hours_count }}시간 · <b>{{ adMoney(f, f.total_price) }}</b> <span class="px-1 py-0.5 rounded text-[10px] font-bold" :class="f.payment_method === 'card' ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'">{{ f.payment_method === 'card' ? (f.payment_status === 'captured' ? '카드 청구됨' : f.payment_status === 'authorized' ? '카드 보류 중' : '카드') : '포인트 결제' }}</span>
        </div>
        <p v-if="f.description" class="text-xs text-ink-light mt-1 line-clamp-2">{{ f.description }}</p>
        <div class="text-xs text-ink-muted mt-1">
          <span v-if="f.phone">📞 {{ f.phone }} </span>
          <a v-if="f.link_url" :href="f.link_url" target="_blank" rel="noopener noreferrer nofollow" class="text-blue-500 underline break-all">{{ f.link_url }}</a>
        </div>
        <div class="mt-2 text-[11px] text-ink-muted space-y-0.5 max-h-24 overflow-y-auto">
          <div v-for="s in f.schedule" :key="s.date"><span class="inline-block w-24">{{ fmtDay(s.date) }}</span>{{ hourRanges(s.hours).join(', ') }}</div>
        </div>
        <div v-if="f.status === 'approved'" class="text-[11px] text-ink-faint mt-1">노출 {{ f.view_count }} · 클릭 {{ f.click_count }}</div>
        <div v-if="f.reject_reason" class="text-[11px] text-red-500 mt-1">사유: {{ f.reject_reason }}</div>

        <div class="flex gap-2 mt-3">
          <button v-if="f.status === 'pending'" @click="approve(f)" class="btn-primary px-4 py-1.5 rounded-lg text-xs">승인</button>
          <button v-if="f.status === 'pending' || f.status === 'approved'" @click="reject(f)" class="px-4 py-1.5 rounded-lg text-xs font-bold border border-red-200 text-red-500 hover:bg-red-50">{{ f.status === 'pending' ? (f.payment_method === 'card' ? '반려(청구 없이 취소)' : '반려(전액 환불)') : '게시 중단(남은 시간 환불)' }}</button>
          <RouterLink v-if="f.status === 'approved'" :to="`/new/${f.id}`" class="px-3 py-1.5 text-xs text-ink-muted hover:text-rose-600">전단 보기</RouterLink>
        </div>
      </div>
    </div>
  </div>
  <Pagination :page="page" :lastPage="lastPage" @page="p => { page = p; load() }" />
  <p v-if="msg" class="text-sm mt-3" :class="msgOk ? 'text-green-600' : 'text-red-500'">{{ msg }}</p>
</div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, inject } from 'vue'
import { RouterLink } from 'vue-router'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
import { kindLabel, fmtDay, hourRanges, tzLabel, stateName, STATUS_LABEL, adMoney } from '../../utils/flyer'

const tabs = [
  { key: 'pending', label: '승인 대기' }, { key: 'approved', label: '게시 중' },
  { key: 'rejected', label: '반려' }, { key: 'cancelled', label: '취소' }, { key: 'all', label: '전체' },
]
const status = ref('pending')
const items = ref([])
const page = ref(1)
const lastPage = ref(1)
const pendingCount = ref(0)
const loading = ref(true)
const msg = ref('')
const msgOk = ref(true)

// 관리자 휴대폰 화면이면 카드 + 아래에서 올라오는 시트로 보여 줌 (AdminLayout 이 알려 줌)
const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)
const sheet = ref(null)          // { mode: 'approve' | 'reject', f }
const reason = ref('')
const busy = ref(false)
const openSchedule = ref(new Set())
let msgTimer = null
const payLabel = (f) => f.payment_method === 'card' ? (f.payment_status === 'captured' ? '카드 청구됨' : f.payment_status === 'authorized' ? '카드 보류 중' : '카드') : '포인트 결제'
function toggleSchedule(id) { const n = new Set(openSchedule.value); n.has(id) ? n.delete(id) : n.add(id); openSchedule.value = n }
function goPage(n) { if (n >= 1 && n <= lastPage.value) { page.value = n; load() } }
function closeSheet() { if (!busy.value) sheet.value = null }
function askApprove(f) { sheet.value = { mode: 'approve', f } }
function askReject(f) { reason.value = f.status === 'pending' ? '운영 정책에 맞지 않는 내용입니다.' : ''; sheet.value = { mode: 'reject', f } }
async function runSheet(path, body = {}) {
  if (busy.value || !sheet.value) return
  busy.value = true
  const f = sheet.value.f
  await act(path, f, body)
  busy.value = false
  sheet.value = null
  clearTimeout(msgTimer)
  msgTimer = setTimeout(() => { msg.value = '' }, 4000)
}
const doApprove = () => runSheet('approve')
const doReject = () => runSheet('reject', { reason: reason.value })
// 시트가 열려 있는 동안 뒤쪽 화면이 같이 스크롤되지 않게
watch(() => isMobile.value && !!sheet.value, locked => { document.body.style.overflow = locked ? 'hidden' : '' })
onBeforeUnmount(() => { document.body.style.overflow = ''; clearTimeout(msgTimer) })

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/admin/flyers', { params: { status: status.value, page: page.value } })
    items.value = data.data.data
    lastPage.value = data.data.last_page
    pendingCount.value = data.pending_count
  } catch {}
  loading.value = false
}

async function act(path, f, body = {}) {
  msg.value = ''
  try {
    const { data } = await axios.post(`/api/admin/flyers/${f.id}/${path}`, body)
    msg.value = data.message; msgOk.value = true
  } catch (e) {
    msg.value = e.response?.data?.message || '처리 실패'; msgOk.value = false
  }
  await load()
}

function approve(f) {
  if (f.payment_method === 'card' && !window.confirm(`승인하면 광고주의 카드에 ${adMoney(f, f.total_price)}가 청구됩니다. (이미 지난 시간이 있으면 그만큼 뺀 금액만 청구) 승인할까요?`)) return
  act('approve', f)
}
function reject(f) {
  const reason = window.prompt(`'${f.title}' 사유를 입력하세요 (광고주에게 전달됩니다)`, '운영 정책에 맞지 않는 내용입니다.')
  if (reason === null) return
  act('reject', f, { reason })
}

onMounted(load)
</script>
<style>
/* 휴대폰 관리자: 입력창 글자가 16px 보다 작으면 iOS 가 화면을 확대해 버림 */
.alv-m input, .alv-m textarea, .alv-m select { font-size: 16px; }
</style>
