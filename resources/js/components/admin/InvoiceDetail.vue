<template>
<Teleport to="body">
  <!-- 인보이스 상세: 휴대폰은 아래에서 올라오는 시트, PC는 가운데 창 -->
  <div class="fixed inset-0 z-[85] bg-black/45 flex" :class="mobile ? 'items-end' : 'items-center justify-center p-4'" @click.self="close">
    <div class="alv-m w-full bg-white overflow-y-auto max-h-[90vh]" role="dialog" aria-modal="true" aria-label="인보이스 상세"
      :class="mobile ? 'rounded-t-3xl px-4 pt-2' : 'rounded-2xl max-w-lg px-6 py-5'"
      :style="mobile ? { paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' } : {}">
      <div v-if="mobile" class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>

      <div v-if="loading" class="py-10 text-center text-ink-muted text-[15px]">불러오는 중...</div>
      <div v-else-if="loadError" class="py-8 text-center space-y-3">
        <p class="text-[15px] text-red-600">{{ loadError }}</p>
        <button @click="load" :class="btnGray">다시 시도</button>
        <button @click="close" :class="btnGray">닫기</button>
      </div>

      <div v-else-if="inv" class="space-y-3">
        <!-- 머리 -->
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <div class="text-[13px] text-ink-muted">인보이스 번호</div>
            <div class="text-[19px] font-black font-mono text-ink break-all">{{ inv.invoice_no }}</div>
          </div>
          <span class="shrink-0 text-[12px] font-bold px-2.5 py-1 rounded-md border" :class="INVOICE_STATUS[inv.status]?.cls">{{ INVOICE_STATUS[inv.status]?.text || inv.status }}</span>
        </div>

        <p v-if="inv.status === 'void'" class="bg-red-50 border border-red-200 text-red-700 text-[14px] rounded-xl p-3 leading-relaxed">
          무효 처리된 인보이스예요{{ inv.voided_at ? ` (${fmtET(inv.voided_at)})` : '' }}.<br v-if="inv.void_reason">
          <span v-if="inv.void_reason">사유: {{ inv.void_reason }}</span>
        </p>

        <!-- 기본 정보 -->
        <div class="bg-gray-50 rounded-2xl p-3.5 space-y-2.5 text-[15px]">
          <div class="flex justify-between gap-3"><span class="text-ink-muted shrink-0">받는 분</span><span class="text-right min-w-0"><b class="block text-ink truncate">{{ inv.billing_name || '-' }}</b><span class="block text-[13px] text-ink-muted break-all">{{ inv.billing_email || '(이메일 없음)' }}</span></span></div>
          <div class="flex justify-between gap-3"><span class="text-ink-muted shrink-0">발행일</span><span class="text-ink text-right">{{ fmtET(inv.issued_at) }}</span></div>
          <div class="flex justify-between gap-3"><span class="text-ink-muted shrink-0">구분</span><span class="text-ink text-right">{{ KIND_TEXT[inv.kind] || inv.kind || '-' }}</span></div>
          <div class="flex justify-between gap-3"><span class="text-ink-muted shrink-0">결제 번호</span><span class="text-ink text-right">#{{ inv.payment_id }}<span v-if="inv.payment" class="text-[13px] text-ink-muted"> · 결제 {{ PAY_STATUS[inv.payment.status] || inv.payment.status }}</span></span></div>
        </div>
        <p v-if="emailChanged" class="bg-amber-50 border border-amber-200 text-amber-800 text-[13px] rounded-xl px-3 py-2.5 leading-relaxed">
          회원이 지금 쓰는 이메일은 <b class="break-all">{{ inv.member_email_now }}</b> 로 달라요. 인보이스는 <b>발행 당시 주소</b>로만 다시 보내져요(받는 주소는 바꿀 수 없어요).
        </p>

        <!-- 상품 -->
        <div class="border border-gray-100 rounded-2xl overflow-hidden">
          <div v-for="(it, i) in inv.line_items || []" :key="i" class="flex items-start justify-between gap-3 px-3.5 py-3 border-b border-gray-100 last:border-0 text-[15px]">
            <div class="min-w-0"><div class="text-ink break-words">{{ it.name }}</div><div v-if="it.name_en" class="text-[12px] text-ink-faint">{{ it.name_en }}</div></div>
            <div class="shrink-0 text-right tabular-nums"><div class="text-ink">{{ usdCents(it.amount_cents) }}</div><div class="text-[12px] text-ink-faint">수량 {{ it.qty || 1 }}</div></div>
          </div>
          <div v-if="inv.discount_cents > 0" class="flex justify-between px-3.5 py-2 text-[14px] bg-gray-50"><span class="text-ink-muted">할인</span><b class="tabular-nums text-red-600">-{{ usdCents(inv.discount_cents) }}</b></div>
          <div class="flex justify-between items-center px-3.5 py-3 bg-amber-50/60"><span class="text-[15px] font-bold text-ink">합계 ({{ inv.currency }})</span><b class="text-[20px] tabular-nums text-amber-600">{{ usdCents(inv.total_cents) }}</b></div>
        </div>

        <!-- 메일 발송 이력 -->
        <div>
          <div class="flex items-center justify-between mb-1.5">
            <div class="text-[15px] font-bold text-ink">메일 발송 이력</div>
            <div class="text-[12px] text-ink-faint">자동 발송: {{ meta.auto_mail ? '켜짐' : '꺼짐' }}</div>
          </div>
          <div v-if="!inv.deliveries?.length" class="text-[14px] text-ink-muted bg-gray-50 rounded-xl px-3 py-3">아직 보낸 기록이 없어요.</div>
          <ul v-else class="space-y-1.5">
            <li v-for="d in inv.deliveries" :key="d.id" class="bg-gray-50 rounded-xl px-3 py-2.5 text-[14px]">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-[12px] font-bold px-2 py-0.5 rounded-md border" :class="MAIL_STATUS[d.status]?.cls">{{ MAIL_STATUS[d.status]?.text || d.status }}</span>
                <span class="text-ink-light">{{ d.triggered_label }}</span>
                <span class="ml-auto text-[12px] text-ink-faint">{{ fmtET(d.sent_at || d.created_at) }}</span>
              </div>
              <div class="text-[13px] text-ink-muted break-all mt-0.5">{{ d.to_email || '(주소 없음)' }}</div>
              <div v-if="d.error" class="text-[13px] text-red-600 mt-0.5 break-words">{{ d.error }}</div>
            </li>
          </ul>
        </div>

        <!-- 무효 처리 입력 -->
        <div v-if="voiding" class="space-y-2 border border-red-200 bg-red-50/50 rounded-2xl p-3.5">
          <div class="text-[15px] font-bold text-red-700">인보이스 무효 처리</div>
          <p class="text-[13px] text-red-700 leading-relaxed">무효로 바꾸면 되돌릴 수 없고, 같은 결제로 새 인보이스를 다시 만들 수도 없어요. 환불은 따로 처리해야 해요. 비밀번호를 한 번 더 물어볼 수 있어요.</p>
          <label class="block">
            <span class="block text-[13px] text-ink-muted mb-1">무효 사유 (5자 이상)</span>
            <textarea v-model="voidReason" rows="3" maxlength="200" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-ink" placeholder="예: 금액을 잘못 입력해 발행됨"></textarea>
          </label>
          <button @click="doVoid" :disabled="busy || voidReason.trim().length < 5" :class="[btnBase, 'bg-red-500 text-white disabled:opacity-50']">{{ busy ? '처리 중...' : '무효 처리하기' }}</button>
          <button @click="voiding = false" :disabled="busy" :class="btnGray">취소</button>
        </div>

        <!-- 버튼 -->
        <div v-else class="space-y-2 pt-1">
          <button @click="viewHtml" :disabled="busy" :class="[btnBase, 'bg-gray-900 text-white']">인보이스 보기 · 인쇄</button>
          <template v-if="meta.can_manage">
            <button @click="doResend" :disabled="busy" :class="[btnBase, 'bg-amber-500 text-white disabled:opacity-50']">{{ busy ? '처리 중...' : '메일 다시 보내기' }}</button>
            <button v-if="inv.status === 'issued'" @click="voiding = true" :disabled="busy" :class="[btnBase, 'bg-red-50 text-red-600']">무효 처리</button>
          </template>
          <p v-else class="text-[13px] text-ink-faint leading-relaxed">다시 보내기와 무효 처리는 최고관리자만 할 수 있어요.</p>
          <button @click="close" :disabled="busy" :class="btnGray">닫기</button>
        </div>
      </div>
    </div>
  </div>
  <div v-if="toast" class="alv-m fixed left-1/2 -translate-x-1/2 z-[90] max-w-[90vw] px-4 py-3 rounded-xl text-[15px] font-bold text-white shadow-lg"
    :class="toast.error ? 'bg-red-600' : 'bg-ink'" :style="{ top: 'calc(70px + env(safe-area-inset-top, 0px))' }" role="status">{{ toast.text }}</div>
</Teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import { useModal } from '../../composables/useModal'
import { usdCents, fmtET, INVOICE_STATUS, MAIL_STATUS, KIND_TEXT } from '../../composables/useInvoiceUi'

const props = defineProps({
  invoiceId: { type: Number, required: true },
  mobile: { type: Boolean, default: false },
})
const emit = defineEmits(['close', 'changed'])
const { showConfirm, showAlert } = useModal()

const inv = ref(null)
const meta = ref({ auto_mail: true, can_manage: false })
const loading = ref(true)
const loadError = ref('')
const busy = ref(false)
const voiding = ref(false)
const voidReason = ref('')
const toast = ref(null)
let toastTimer = null

// 버튼 크기: 휴대폰은 터치 44px 이상, PC는 작은 버튼
const btnBase = computed(() => 'w-full rounded-xl font-bold ' + (props.mobile ? 'min-h-[50px] text-[16px]' : 'py-2.5 text-sm'))
const btnGray = computed(() => 'w-full rounded-xl font-bold bg-gray-100 text-ink ' + (props.mobile ? 'min-h-[50px] text-[16px]' : 'py-2.5 text-sm'))

const PAY_STATUS = { completed: '완료', captured: '청구됨', refunded: '환불됨', authorized: '승인됨', pending: '대기', released: '보류 해제', failed: '실패' }
const emailChanged = computed(() => !!inv.value?.member_email_now && inv.value.member_email_now !== inv.value.billing_email)

function say(text, error = false) {
  toast.value = { text, error }
  clearTimeout(toastTimer)
  toastTimer = setTimeout(() => { toast.value = null }, 3500)
}
function close() { if (!busy.value) emit('close') }

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await axios.get(`/api/admin/invoices/${props.invoiceId}`)
    inv.value = data.data
    meta.value = data.meta || meta.value
  } catch (e) {
    loadError.value = e.response?.data?.message || '인보이스를 불러오지 못했어요'
  }
  loading.value = false
}

// 회원이 받는 것과 같은 HTML을 새 창에 (인쇄 가능). 로그인 정보가 필요해서 axios 로 받아 쓴다.
async function viewHtml() {
  const w = window.open('', '_blank')
  if (!w) { showAlert('팝업이 막혀 있어요. 팝업을 허용한 뒤 다시 눌러 주세요.', '알림'); return }
  try {
    const { data } = await axios.get(`/api/admin/invoices/${props.invoiceId}/html`, { responseType: 'text', transformResponse: [(d) => d] })
    w.document.open(); w.document.write(data); w.document.close()
  } catch (e) {
    w.close()
    say('인보이스를 열지 못했어요', true)
  }
}

async function doResend() {
  if (busy.value || !inv.value) return
  const to = inv.value.billing_email || '(주소 없음)'
  const ok = await showConfirm(`${inv.value.invoice_no} 인보이스를\n${to}\n로 다시 보낼까요?\n\n받는 주소는 바꿀 수 없고, 같은 인보이스는 1분에 한 번만 보낼 수 있어요.`, '메일 다시 보내기')
  if (!ok) return
  busy.value = true
  try {
    const { data } = await axios.post(`/api/admin/invoices/${props.invoiceId}/resend`)
    say(data.message || '다시 보내도록 요청했어요')
    emit('changed')
    await load()
  } catch (e) {
    say(e.response?.data?.message || '다시 보내지 못했어요', true)
    emit('changed')
    await load()
  } finally { busy.value = false }
}

async function doVoid() {
  if (busy.value || voidReason.value.trim().length < 5) return
  busy.value = true
  try {
    const { data } = await axios.post(`/api/admin/invoices/${props.invoiceId}/void`, { reason: voidReason.value.trim() })
    say(data.message || '무효 처리했어요')
    voiding.value = false; voidReason.value = ''
    emit('changed')
    await load()
  } catch (e) {
    // 비밀번호 재확인을 취소하면 428 이 그대로 돌아온다 — 조용히 입력 화면에 머문다
    if (e.response?.status !== 428) say(e.response?.data?.errors?.reason?.[0] || e.response?.data?.message || '무효 처리에 실패했어요', true)
  } finally { busy.value = false }
}

watch(() => props.invoiceId, () => { voiding.value = false; voidReason.value = ''; load() })
// 시트가 열려 있는 동안 뒤쪽 화면이 같이 스크롤되지 않게 (닫을 때 원래 값으로)
let prevOverflow = ''
onMounted(() => { load(); if (props.mobile) { prevOverflow = document.body.style.overflow; document.body.style.overflow = 'hidden' } })
onBeforeUnmount(() => { clearTimeout(toastTimer); if (props.mobile) document.body.style.overflow = prevOverflow })
</script>
<style>
/* 휴대폰 관리자: 입력창 글자가 16px 보다 작으면 iOS 가 화면을 확대해 버림 */
.alv-m input, .alv-m textarea, .alv-m select { font-size: 16px; }
</style>
