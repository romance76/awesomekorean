<template>
<div class="space-y-4">
  <div class="card p-5">
    <div class="flex items-center justify-between mb-4">
      <h2 class="flex items-center gap-2 font-bold text-ink"><span class="icon-chip w-7 h-7 bg-lime-50 text-lime-600"><AppIcon name="heart-handshake" :size="15" /></span>내 공동구매</h2>
      <RouterLink to="/groupbuy/create" class="btn-primary px-4 py-1.5 text-xs"><AppIcon name="plus" :size="13" /> 열기</RouterLink>
    </div>

    <div v-if="loading" class="text-sm text-ink-faint py-6 text-center">불러오는 중...</div>
    <div v-else-if="!items.length" class="text-sm text-ink-faint py-6 text-center">아직 연 공동구매가 없습니다</div>

    <div v-else class="space-y-3">
      <div v-for="g in items" :key="g.id" class="border border-gray-100 rounded-xl p-3 space-y-3">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <RouterLink :to="`/groupbuy/${g.id}`" class="text-sm font-semibold text-ink hover:text-amber-600 transition-colors break-words">{{ g.title }}</RouterLink>
            <div class="text-xs text-ink-faint mt-0.5">
              {{ g.tier === 'small' ? '소액 공동구매' : '사업자 공동구매' }}
              · {{ g.scope === 'national' ? '전국' : (g.target_states && g.target_states.length ? g.target_states.join('·') : '지역') }}
              <template v-if="g.fulfillment"> · {{ g.fulfillment === 'coupon' ? '쿠폰용' : '택배용' }}</template>
              <template v-if="g.deadline"> · 마감 {{ fmtDate(g.deadline) }}</template>
            </div>
          </div>
          <span class="text-xs px-2 py-0.5 rounded-full font-bold flex-shrink-0" :class="stageInfo(g).cls">{{ stageInfo(g).label }}</span>
        </div>

        <div v-if="g.deposit_cents" class="text-xs text-ink-muted">
          디파짓 ${{ (g.deposit_cents / 100).toFixed(2) }} ·
          {{ { held: '카드 보류 중 (승인되면 청구)', charged: '결제 완료', released: '청구 없이 해제됨' }[g.deposit_state] || '' }}
        </div>

        <div v-if="g.stage === 'rejected' && g.rejection_reason" class="bg-red-50 text-red-700 rounded-xl p-3 text-xs">반려 사유: {{ g.rejection_reason }}</div>
        <div v-else-if="g.stage === 'pending_approval'" class="bg-amber-50 text-amber-800 rounded-xl p-3 text-xs">관리자가 사업자 등록증을 확인하는 중이에요. 승인되면 목록에 공개되고 알림이 와요.</div>
        <div v-else-if="g.stage === 'cancelled' && g.closed_reason === 'declined'" class="bg-gray-50 text-ink-muted rounded-xl p-3 text-xs">공구를 취소했어요. 모든 참여가 취소됐고, 참가자에게 취소 안내를 보냈어요. 참가자 포인트는 돌려줬고 낸 디파짓은 환불되지 않아요.</div>
        <div v-else-if="g.stage === 'completed' && g.closed_reason === 'early'" class="bg-lime-50 text-lime-800 rounded-xl p-3 text-xs">마감일 전에 조기 마감하고 완료했어요. 마감 때까지 참여한 분들께 공동구매 가격이 적용됐어요.</div>

        <div class="grid grid-cols-3 gap-2 text-center">
          <div class="bg-[#F4F6F8] rounded-xl py-2">
            <div class="text-[11px] text-ink-muted">참가자</div>
            <div class="font-bold text-ink">{{ g.current_participants }}<span class="text-xs text-ink-muted font-normal"> / {{ g.max_participants || '∞' }}명</span></div>
          </div>
          <div class="bg-[#F4F6F8] rounded-xl py-2">
            <div class="text-[11px] text-ink-muted">신청 수량</div>
            <div class="font-bold text-ink">{{ g.sold_quantity }}<span class="text-xs text-ink-muted font-normal"> / {{ g.total_quantity || '∞' }}개</span></div>
          </div>
          <div class="bg-[#F4F6F8] rounded-xl py-2">
            <div class="text-[11px] text-ink-muted">현재 가격</div>
            <div class="font-bold text-ink">${{ Number(g.price || 0).toLocaleString() }}</div>
          </div>
        </div>

        <!-- 완료·수락 -->
        <div v-if="g.accept" class="rounded-xl border border-lime-200 bg-lime-50/50 p-3 space-y-2">
          <div class="text-sm font-semibold text-ink">공동구매 마무리</div>
          <div v-if="g.accept.can_accept" class="text-xs text-ink-light leading-relaxed">
            <b>조기 마감하고 완료</b>하면 지금까지 참여한 {{ g.participants.length }}명에게 <b>현재 공동구매 가격(${{ Number(g.price || 0).toLocaleString() }})</b>이 그대로 적용돼요.
            예상 총액 <b>${{ money(g.accept.total_cents) }}</b>, 수수료 <b>${{ money(g.accept.fee_cents) }}</b>
            <template v-if="g.accept.deposit_cents"> − 낸 디파짓 ${{ money(g.accept.deposit_cents) }}</template>
            → <b>{{ g.accept.due_cents > 0 ? `카드로 $${money(g.accept.due_cents)} 결제` : '추가 결제 없음' }}</b>.
            완료하면 참가자 정보(이름·전화·이메일)를 볼 수 있고{{ g.fulfillment === 'coupon' ? ', 참가자에게 쿠폰이 발급돼요' : '' }}.
          </div>
          <div v-else class="text-xs text-ink-muted">{{ g.accept.blocked_reason }}</div>
          <div class="grid grid-cols-2 gap-2">
            <button @click="startAccept(g)" :disabled="!g.accept.can_accept || busy" class="py-2.5 rounded-xl bg-emerald-500 text-white text-sm font-bold disabled:opacity-40 leading-tight">조기 마감하고<br />완료</button>
            <button @click="declineGb(g)" :disabled="busy" class="py-2.5 rounded-xl bg-white border border-red-200 text-red-600 text-sm font-bold disabled:opacity-50 leading-tight">공구 취소<br />(모두 취소)</button>
          </div>
          <div class="text-[11px] text-ink-muted space-y-0.5">
            <div><b>조기 마감하고 완료</b> — 마감일 전이거나 사람이 덜 모였어도 지금 모집을 닫고, 참가자가 한 명이라도 있으면 그분들까지 공동구매 가격으로 진행해요.</div>
            <div><b>공구 취소</b> — 공동구매를 접어요. 모든 참여가 취소되고 참가자에게 취소 안내가 가요. 낸 디파짓은 환불되지 않고, 참가자 포인트는 돌려줘요.</div>
          </div>
        </div>

        <!-- 쿠폰 관리 (완료된 쿠폰형) -->
        <div v-if="g.coupon && g.stage === 'completed'" class="rounded-xl border border-amber-200 bg-amber-50/40 p-3 space-y-2">
          <div class="flex items-center justify-between">
            <div class="text-sm font-semibold text-ink">쿠폰 관리</div>
            <button @click="toggleCoupons(g)" class="text-xs text-amber-700 font-bold">{{ couponsOpen[g.id] ? '접기' : '쿠폰 목록 보기' }}</button>
          </div>
          <div class="grid grid-cols-4 gap-1.5 text-center text-[11px]">
            <div class="bg-white rounded-lg py-1.5"><div class="text-ink-muted">미사용</div><div class="font-bold text-ink">{{ g.coupon.counts.unused }}</div></div>
            <div class="bg-white rounded-lg py-1.5"><div class="text-ink-muted">확인 중</div><div class="font-bold text-ink">{{ g.coupon.counts.pending }}</div></div>
            <div class="bg-white rounded-lg py-1.5"><div class="text-ink-muted">사용</div><div class="font-bold text-ink">{{ g.coupon.counts.used }}</div></div>
            <div class="bg-white rounded-lg py-1.5"><div class="text-ink-muted">만료</div><div class="font-bold text-ink">{{ g.coupon.counts.expired }}</div></div>
          </div>

          <div v-if="g.coupon.use_mode !== 'online'" class="text-xs text-ink-light">
            <b>매장:</b> 손님이 보여 주는 QR을 일반 카메라로 찍으면 확인 페이지가 열려요. PIN을 입력하면 사용을 수락할 수 있어요.
          </div>
          <div v-if="g.coupon.use_mode !== 'store'" class="space-y-1.5">
            <div class="text-xs text-ink-light"><b>온라인:</b> 손님이 주문 메모에 적은 쿠폰 번호를 입력하면 손님에게 "맞나요?" 확인이 가요. (24시간)</div>
            <div class="flex gap-2">
              <input v-model="onlineCode[g.id]" type="text" maxlength="14" placeholder="쿠폰 번호 (예: ABCDE-FGHJK)" class="input-soft px-3 flex-1 min-w-0 font-mono uppercase" />
              <button @click="requestOnline(g)" :disabled="busy || !(onlineCode[g.id] || '').trim()" class="px-3 rounded-xl bg-amber-500 text-white text-sm font-bold disabled:opacity-40 flex-shrink-0">사용 요청</button>
            </div>
          </div>

          <div v-if="!g.coupon.extended && (g.coupon.counts.unused + g.coupon.counts.pending) > 0" class="flex items-center gap-2 text-xs text-ink-light">
            <span>유효기간 연장 (한 번만)</span>
            <select v-model.number="extendDays[g.id]" class="input-soft px-2 py-1 w-auto">
              <option :value="7">+7일</option><option :value="14">+14일</option><option :value="30">+30일</option><option :value="60">+60일</option><option :value="90">+90일</option>
            </select>
            <button @click="extendCoupons(g)" :disabled="busy" class="px-2.5 py-1 rounded-lg bg-white border border-amber-300 text-amber-700 font-bold disabled:opacity-50">연장</button>
          </div>
          <div v-else-if="g.coupon.extended" class="text-[11px] text-ink-muted">유효기간을 이미 한 번 연장했어요.</div>

          <div v-if="couponsOpen[g.id]" class="space-y-1.5 pt-1">
            <div v-if="!couponLists[g.id]" class="text-xs text-ink-muted">불러오는 중...</div>
            <div v-else-if="!couponLists[g.id].length" class="text-xs text-ink-muted">쿠폰이 없어요.</div>
            <div v-for="c in couponLists[g.id] || []" :key="c.id" class="bg-white rounded-lg px-3 py-2 text-xs space-y-1">
              <div class="flex items-center justify-between gap-2">
                <div class="min-w-0">
                  <span class="font-mono font-bold text-ink">{{ c.display_code }}</span>
                  <span class="text-ink-muted"> · {{ c.holder }}</span>
                </div>
                <span class="px-1.5 py-0.5 rounded-full font-bold flex-shrink-0" :class="couponStatus(c.status).cls">{{ couponStatus(c.status).label }}</span>
              </div>
              <div class="text-ink-muted">
                <template v-if="c.used_at">{{ fmtDateTime(c.used_at) }} 사용</template>
                <template v-else-if="c.expires_at">{{ fmtDate(c.expires_at) }}까지</template>
              </div>
              <div v-if="c.undo && c.undo.requested_by === 'holder'" class="rounded bg-amber-50 p-1.5 text-amber-800">
                손님이 사용 취소(되돌리기)를 요청했어요.
                <div class="grid grid-cols-2 gap-1.5 mt-1">
                  <button @click="undoCoupon(g, c, 'accept')" :disabled="busy" class="py-1 rounded bg-emerald-500 text-white font-bold disabled:opacity-50">동의</button>
                  <button @click="undoCoupon(g, c, 'decline')" :disabled="busy" class="py-1 rounded bg-white border border-gray-200 text-ink font-bold disabled:opacity-50">거절</button>
                </div>
              </div>
              <button v-else-if="c.undo && c.undo.can_request" @click="undoCoupon(g, c, 'request')" :disabled="busy" class="text-amber-700 underline">사용 취소 요청 ({{ c.undo.minutes_left }}분 안)</button>
              <div v-else-if="c.undo && c.undo.requested_by === 'opener'" class="text-amber-700">사용 취소를 요청했어요. 손님이 동의하면 되돌려져요.</div>
            </div>
          </div>
        </div>

        <div>
          <button @click="toggle(g.id)" class="text-xs font-semibold text-ink-light">
            참가자 {{ g.participants.length }}명 {{ openId === g.id ? '접기' : '보기' }}
          </button>
          <div v-if="openId === g.id" class="mt-2 space-y-1">
            <div v-if="!g.participants.length" class="text-xs text-ink-muted">아직 참가자가 없어요.</div>
            <div v-for="p in g.participants" :key="p.id" class="bg-[#F4F6F8] rounded-lg px-3 py-1.5 text-sm">
              <div class="flex items-center justify-between">
                <span class="text-ink">{{ p.name }}</span>
                <span class="text-xs text-ink-muted">{{ p.quantity }}개 · {{ fmtDate(p.joined_at) }}</span>
              </div>
              <div v-if="p.contact" class="text-xs text-ink-light mt-1 space-y-0.5">
                <div>{{ p.contact.name }}</div>
                <div v-if="p.contact.phone"><a :href="`tel:${p.contact.phone}`" class="text-blue-600">{{ p.contact.phone }}</a></div>
                <div v-if="p.contact.email"><a :href="`mailto:${p.contact.email}`" class="text-blue-600 break-all">{{ p.contact.email }}</a></div>
              </div>
            </div>
            <div v-if="g.contacts_open" class="text-xs text-ink-muted bg-emerald-50 rounded-lg p-2 mt-2">
              참가자가 제공에 동의한 정보예요. 이 공동구매의 이행·안내·문의에만 쓰고, 광고·마케팅에 쓰거나 다른 사람에게 주지 마세요.
            </div>
            <div v-else class="text-xs text-ink-muted bg-amber-50 rounded-lg p-2 mt-2">
              🔒 참가자 이름·전화번호·이메일은 공동구매를 수락(완료)하고 수수료 결제까지 마치면 볼 수 있어요.
            </div>
          </div>
        </div>

        <div class="flex items-center gap-1.5 pt-2 border-t border-gray-100">
          <RouterLink :to="`/groupbuy/${g.id}`" class="flex-1 flex items-center justify-center gap-1 text-xs font-bold py-1.5 rounded-lg bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors"><AppIcon name="eye" :size="12" /> 미리보기</RouterLink>
        </div>
      </div>
    </div>

    <div v-if="error" class="text-red-500 text-sm mt-3">{{ error }}</div>
    <div v-if="notice" class="text-emerald-600 text-sm mt-3">{{ notice }}</div>
  </div>

  <!-- 수수료 카드 결제 창 (수락할 때 결제액이 있을 때) -->
  <Teleport to="body">
    <div v-if="pay.open" class="fixed inset-0 z-[10001] bg-black/55 flex items-end sm:items-center justify-center" @click.self="cancelCard">
      <div class="bg-white w-full sm:max-w-md rounded-t-2xl sm:rounded-2xl p-5" role="dialog" aria-modal="true"
        :style="{ paddingBottom: 'calc(20px + env(safe-area-inset-bottom, 0px))' }">
        <div class="text-[17px] font-bold text-ink mb-1">공동구매 완료 수수료</div>
        <div class="bg-lime-50 rounded-xl px-4 py-3 mb-3 space-y-1">
          <div class="flex items-center justify-between text-xs text-lime-800"><span>예상 총액</span><span>${{ money(pay.total_cents) }}</span></div>
          <div class="flex items-center justify-between text-xs text-lime-800"><span>수수료</span><span>${{ money(pay.fee_cents) }}</span></div>
          <div v-if="pay.deposit_cents" class="flex items-center justify-between text-xs text-lime-800"><span>낸 디파짓</span><span>− ${{ money(pay.deposit_cents) }}</span></div>
          <div class="flex items-center justify-between pt-1 border-t border-lime-200"><span class="text-sm font-bold text-lime-700">지금 결제</span><span class="text-xl font-black text-lime-700">${{ money(pay.due_cents) }}</span></div>
        </div>
        <p class="text-[13px] text-ink-muted mb-3 leading-relaxed">카드를 확인하면 <b>바로 청구</b>되고 공동구매가 완료돼요. 인보이스는 마이페이지 › 결제에서 볼 수 있어요.</p>
        <StripeCardForm :client-secret="pay.secret" button-label="카드 확인하고 결제·완료하기" @authorized="onCardAuthorized" />
        <p v-if="pay.busy" class="text-xs text-ink-muted mt-2">마무리하는 중...</p>
        <p v-if="pay.error" class="text-xs text-red-500 mt-2">{{ pay.error }}</p>
        <button @click="cancelCard" :disabled="pay.busy" class="w-full mt-2 py-2.5 rounded-xl bg-gray-100 text-ink text-sm font-bold disabled:opacity-50">취소 (청구 없음)</button>
      </div>
    </div>
  </Teleport>
</div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../AppIcon.vue'
import StripeCardForm from '../StripeCardForm.vue'
import { useModal } from '../../composables/useModal'

const { showAlert, showConfirm, showPrompt } = useModal()
const items = ref([])
const loading = ref(true)
const error = ref('')
const notice = ref('')
const openId = ref(null)
const busy = ref(false)

const couponsOpen = reactive({})
const couponLists = reactive({})
const onlineCode = reactive({})
const extendDays = reactive({})

const STAGES = {
  pending_approval: { label: '승인 대기', cls: 'bg-amber-100 text-amber-700' },
  rejected: { label: '반려됨', cls: 'bg-red-100 text-red-600' },
  recruiting: { label: '모집 중', cls: 'bg-emerald-100 text-emerald-700' },
  confirmed: { label: '확정', cls: 'bg-sky-100 text-sky-700' },
  completed: { label: '완료', cls: 'bg-gray-200 text-gray-600' },
  cancelled: { label: '취소', cls: 'bg-gray-200 text-gray-500' },
  ended: { label: '마감', cls: 'bg-gray-200 text-gray-600' },
}
// 수락하지 않고 종료한 것은 '취소' 대신 '종료'로 보여 준다
const stageInfo = g => (g.stage === 'cancelled' && g.closed_reason === 'declined') ? { label: '공구 취소', cls: 'bg-gray-200 text-gray-500' }
  : (g.stage === 'completed' && g.closed_reason === 'early') ? { label: '조기 마감 완료', cls: 'bg-gray-200 text-gray-600' }
  : (STAGES[g.stage] || { label: g.stage, cls: 'bg-gray-100 text-gray-500' })
const COUPON_STATUS = {
  unused: { label: '미사용', cls: 'bg-emerald-100 text-emerald-700' },
  pending: { label: '확인 중', cls: 'bg-amber-100 text-amber-700' },
  used: { label: '사용', cls: 'bg-gray-200 text-gray-600' },
  expired: { label: '만료', cls: 'bg-gray-200 text-gray-500' },
  cancelled: { label: '취소', cls: 'bg-gray-200 text-gray-500' },
}
const couponStatus = s => COUPON_STATUS[s] || { label: s, cls: 'bg-gray-100 text-gray-500' }

const money = c => (Number(c || 0) / 100).toFixed(2)
function fmtDate(v) {
  if (!v) return ''
  const d = new Date(v)
  return isNaN(d) ? '' : `${d.getMonth() + 1}/${d.getDate()}`
}
function fmtDateTime(v) {
  const d = new Date(v)
  return isNaN(d) ? '' : `${d.getMonth() + 1}/${d.getDate()} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}
function toggle(id) { openId.value = openId.value === id ? null : id }

async function load() {
  try {
    const { data } = await axios.get('/api/groupbuys/my-opened')
    items.value = data.data || []
    items.value.forEach(g => { if (extendDays[g.id] == null) extendDays[g.id] = 30 })
  } catch (e) {
    error.value = e.response?.data?.message || '불러오지 못했어요'
  }
  loading.value = false
}

// ─── 수락 / 종료 ───
const pay = reactive({ open: false, secret: '', gbId: null, total_cents: 0, fee_cents: 0, deposit_cents: 0, due_cents: 0, busy: false, error: '' })

async function startAccept(g) {
  if (busy.value) return
  const a = g.accept
  const early = g.deadline && new Date(g.deadline).getTime() > Date.now()
  const ok = await showConfirm(
    (early ? `마감일이 아직 남았지만 지금 마감하고 완료할까요?\n` : `지금 완료할까요?\n`) +
    `지금까지 참여한 ${g.participants.length}명에게 현재 공동구매 가격($${Number(g.price || 0).toLocaleString()})이 적용돼요.\n` +
    `예상 총액 $${money(a.total_cents)} · 수수료 $${money(a.fee_cents)}` +
    (a.deposit_cents ? ` − 디파짓 $${money(a.deposit_cents)}` : '') +
    `\n→ ${a.due_cents > 0 ? `카드로 $${money(a.due_cents)} 결제` : '추가 결제 없음'}\n\n마감하면 더 이상 참여할 수 없어요.`, early ? '조기 마감하고 완료' : '공동구매 완료')
  if (!ok) return
  busy.value = true; error.value = ''; notice.value = ''
  try {
    const { data } = await axios.post(`/api/groupbuys/${g.id}/accept`)
    if (data?.data?.needs_card) {
      Object.assign(pay, { open: true, secret: data.data.client_secret, gbId: g.id, total_cents: data.data.total_cents, fee_cents: data.data.fee_cents, deposit_cents: data.data.deposit_cents, due_cents: data.data.due_cents, busy: false, error: '' })
    } else {
      await showAlert(data?.message || '완료되었어요', '공동구매 수락')
      await load()
    }
  } catch (e) {
    error.value = e.response?.data?.message || '수락하지 못했어요'
  }
  busy.value = false
}

async function onCardAuthorized() {
  pay.busy = true; pay.error = ''
  try {
    const { data } = await axios.post(`/api/groupbuys/${pay.gbId}/accept/confirm`)
    pay.open = false
    await showAlert(data?.message || '완료되었어요', '공동구매 수락')
    await load()
  } catch (e) {
    pay.busy = false
    pay.error = e.response?.data?.message || '결제를 마치지 못했어요. 다시 시도해 주세요.'
  }
}
function cancelCard() { if (!pay.busy) pay.open = false }   // 청구는 카드 확인 후에만 — 닫으면 아무 일도 없다 (보류는 다시 수락할 때 정리)

async function declineGb(g) {
  const people = g.participants.length
  const ok = await showConfirm(
    `공구를 취소할까요?\n모든 참여가 취소되고 참가자 ${people}명의 포인트는 돌려줘요. 낸 디파짓은 환불되지 않고, 되돌릴 수 없어요.\n` +
    `(일부 참가자에게라도 공동구매 가격을 적용하려면 취소하지 말고 "조기 마감하고 완료"를 눌러 주세요.)\n\n` +
    `참가자에게 "개설자의 피치 못할 사정으로 취소되었다"는 안내 메시지를 보내겠습니까?`, '공구 취소')
  if (!ok) return
  // 참가자에게 덧붙일 한마디(선택) — 취소를 누르거나 비워 두면 기본 안내만 간다
  const note = await showPrompt('참가자에게 전하고 싶은 말이 있으면 적어 주세요. (선택)', '취소 안내에 덧붙이기', '예: 재료 수급이 어려워졌어요. 죄송합니다.')
  busy.value = true; error.value = ''
  try {
    const { data } = await axios.post(`/api/groupbuys/${g.id}/decline`, { note: (note || '').trim() || undefined })
    notice.value = data?.message || '종료했어요'
    await load()
  } catch (e) {
    error.value = e.response?.data?.message || '종료하지 못했어요'
  }
  busy.value = false
}

// ─── 쿠폰 관리 ───
async function loadCoupons(g) {
  try {
    const { data } = await axios.get(`/api/groupbuys/${g.id}/coupons`)
    couponLists[g.id] = data.data || []
  } catch (e) {
    couponLists[g.id] = []
    error.value = e.response?.data?.message || '쿠폰을 불러오지 못했어요'
  }
}
async function toggleCoupons(g) {
  couponsOpen[g.id] = !couponsOpen[g.id]
  if (couponsOpen[g.id]) await loadCoupons(g)
}

async function requestOnline(g) {
  busy.value = true; error.value = ''; notice.value = ''
  try {
    const { data } = await axios.post(`/api/groupbuys/${g.id}/coupons/use`, { code: onlineCode[g.id], mode: 'online' })
    notice.value = data?.message || '손님에게 확인을 요청했어요'
    onlineCode[g.id] = ''
    await load()
    if (couponsOpen[g.id]) await loadCoupons(g)
  } catch (e) {
    error.value = e.response?.data?.message || '사용 요청을 보내지 못했어요'
  }
  busy.value = false
}

async function extendCoupons(g) {
  const days = extendDays[g.id] || 30
  const ok = await showConfirm(`쿠폰 유효기간을 ${days}일 연장할까요?\n연장은 한 번만 할 수 있어요.`, '유효기간 연장')
  if (!ok) return
  busy.value = true; error.value = ''; notice.value = ''
  try {
    const { data } = await axios.post(`/api/groupbuys/${g.id}/coupons/extend`, { days })
    notice.value = data?.message || '연장했어요'
    await load()
    if (couponsOpen[g.id]) await loadCoupons(g)
  } catch (e) {
    error.value = e.response?.data?.message || '연장하지 못했어요'
  }
  busy.value = false
}

async function undoCoupon(g, c, action) {
  busy.value = true; error.value = ''; notice.value = ''
  try {
    const { data } = await axios.post(`/api/groupbuys/coupons/${c.id}/undo`, { action })
    notice.value = data?.message || ''
    await load()
    await loadCoupons(g)
  } catch (e) {
    error.value = e.response?.data?.message || '처리하지 못했어요'
  }
  busy.value = false
}

onMounted(load)
</script>
