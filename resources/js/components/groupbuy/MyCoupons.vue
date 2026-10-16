<template>
<div class="space-y-4">
  <div class="card p-5">
    <div class="flex items-center justify-between mb-4">
      <h2 class="flex items-center gap-2 font-bold text-ink"><span class="icon-chip w-7 h-7 bg-lime-50 text-lime-600"><AppIcon name="ticket" :size="15" /></span>내 쿠폰</h2>
      <RouterLink to="/groupbuy" class="btn-secondary px-3 py-1.5 text-xs">공동구매 보기</RouterLink>
    </div>

    <div v-if="loading" class="text-sm text-ink-faint py-6 text-center">불러오는 중...</div>
    <div v-else-if="!items.length" class="text-sm text-ink-faint py-6 text-center">
      아직 쿠폰이 없어요.<br />
      <span class="text-xs">참여한 공동구매가 완료되면 여기에 쿠폰이 나와요.</span>
    </div>

    <div v-else class="space-y-2">
      <button v-for="c in items" :key="c.id" @click="openDetail(c.id)"
        class="w-full text-left border border-gray-100 rounded-xl p-3 hover:bg-amber-50/30 transition-colors flex items-center gap-3">
        <div class="w-12 h-12 rounded-lg bg-gray-100 overflow-hidden flex-shrink-0 flex items-center justify-center text-gray-300">
          <img v-if="c.image" :src="imgUrl(c.image)" class="w-full h-full object-cover" @error="e => e.target.style.display='none'" />
          <AppIcon v-else name="ticket" :size="22" :stroke-width="1.5" />
        </div>
        <div class="min-w-0 flex-1">
          <div class="text-sm font-semibold text-ink truncate">{{ c.title }}</div>
          <div class="text-xs text-ink-muted mt-0.5 truncate">
            {{ c.display_code }}<template v-if="c.expires_at"> · {{ fmtDate(c.expires_at) }}까지</template>
          </div>
        </div>
        <span class="text-xs px-2 py-0.5 rounded-full font-bold flex-shrink-0" :class="statusInfo(c.status).cls">{{ statusInfo(c.status).label }}</span>
      </button>
    </div>
    <div v-if="error" class="text-red-500 text-sm mt-3">{{ error }}</div>
  </div>

  <!-- 쿠폰 한 장 -->
  <Teleport to="body">
    <div v-if="detail" class="fixed inset-0 z-[9998] bg-black/50 flex items-end sm:items-center justify-center" @click.self="closeDetail">
      <div class="bg-white w-full sm:max-w-md max-h-[92vh] overflow-y-auto rounded-t-2xl sm:rounded-2xl p-5 space-y-4" role="dialog" aria-modal="true"
        :style="{ paddingBottom: 'calc(20px + env(safe-area-inset-bottom, 0px))' }">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <div class="text-[17px] font-bold text-ink break-words">{{ detail.group_buy.title }}</div>
            <div class="text-xs text-ink-muted mt-0.5">{{ detail.group_buy.opener }} · 쿠폰 {{ detail.seq }}번</div>
          </div>
          <button @click="closeDetail" class="text-ink-faint hover:text-ink flex-shrink-0"><AppIcon name="x" :size="20" /></button>
        </div>

        <!-- 가게가 사용 확인을 요청했을 때: 고객이 맞다고 해야 사용 처리된다 -->
        <div v-if="detail.pending" class="rounded-2xl border-2 border-amber-400 bg-amber-50 p-4 space-y-3">
          <div class="text-sm font-bold text-ink">
            {{ detail.pending.mode === 'online' ? '온라인에서 이 쿠폰을 사용한다는 요청이 왔어요.' : `${detail.pending.place || '가게'}에서 이 쿠폰을 사용합니다.` }} 맞나요?
          </div>
          <div class="text-xs text-amber-800">{{ countdown }}</div>
          <div v-if="!denyOpen" class="grid grid-cols-2 gap-2">
            <button @click="respond(true)" :disabled="busy" class="py-3 rounded-xl bg-emerald-500 text-white text-sm font-bold disabled:opacity-50">맞아요</button>
            <button @click="denyOpen = true" :disabled="busy" class="py-3 rounded-xl bg-white border border-red-200 text-red-600 text-sm font-bold disabled:opacity-50">아니에요</button>
          </div>
          <div v-else class="space-y-2">
            <input v-model="denyReason" type="text" maxlength="200" placeholder="이유를 적어 주세요 (선택)" class="input-soft px-3" />
            <div class="grid grid-cols-2 gap-2">
              <button @click="respond(false)" :disabled="busy" class="py-2.5 rounded-xl bg-red-500 text-white text-sm font-bold disabled:opacity-50">사용 거절</button>
              <button @click="denyOpen = false" :disabled="busy" class="py-2.5 rounded-xl bg-gray-100 text-ink text-sm font-bold">돌아가기</button>
            </div>
          </div>
        </div>

        <!-- 사용 완료 -->
        <div v-if="detail.status === 'used'" class="rounded-xl bg-gray-50 p-3 text-sm text-ink-light space-y-1">
          <div class="font-semibold text-ink">사용 완료</div>
          <div class="text-xs">{{ fmtDateTime(detail.used_at) }}<template v-if="detail.used_place"> · {{ detail.used_place }}</template></div>
          <div v-if="detail.undo && detail.undo.requested_by === 'opener'" class="mt-2 rounded-lg bg-amber-50 p-2 text-xs text-amber-800">
            개설자가 사용 취소를 요청했어요. 사용하지 않은 것이 맞으면 동의해 주세요.
            <div class="grid grid-cols-2 gap-2 mt-2">
              <button @click="undo('accept')" :disabled="busy" class="py-2 rounded-lg bg-emerald-500 text-white font-bold disabled:opacity-50">취소에 동의</button>
              <button @click="undo('decline')" :disabled="busy" class="py-2 rounded-lg bg-white border border-gray-200 text-ink font-bold disabled:opacity-50">그대로 둠</button>
            </div>
          </div>
          <button v-else-if="detail.undo && detail.undo.can_request" @click="undo('request')" :disabled="busy" class="text-xs text-amber-700 underline">실수로 사용 처리됐나요? 되돌리기 요청 ({{ detail.undo.minutes_left }}분 안)</button>
          <div v-else-if="detail.undo && detail.undo.requested_by === 'holder'" class="text-xs text-amber-700">되돌리기를 요청했어요. 개설자가 동의하면 쿠폰이 다시 살아나요.</div>
        </div>
        <div v-else-if="detail.status === 'expired'" class="rounded-xl bg-gray-50 p-3 text-sm text-ink-muted">기간이 지나 쓸 수 없는 쿠폰이에요.</div>
        <div v-else-if="detail.status === 'cancelled'" class="rounded-xl bg-gray-50 p-3 text-sm text-ink-muted">취소된 쿠폰이에요.</div>

        <!-- QR + 번호 (쓸 수 있을 때) -->
        <template v-if="['unused', 'pending'].includes(detail.status)">
          <div v-if="detail.group_buy.use_mode !== 'online'" class="text-center space-y-2">
            <img v-if="qrDataUrl" :src="qrDataUrl" alt="쿠폰 QR" class="w-52 h-52 mx-auto rounded-xl border border-gray-100" />
            <div class="text-xs text-ink-muted">가게에서 이 QR을 보여 주세요. 일반 카메라로 찍으면 돼요.</div>
          </div>
          <div class="grid grid-cols-2 gap-2 text-center">
            <div class="bg-[#F4F6F8] rounded-xl py-2.5">
              <div class="text-[11px] text-ink-muted">쿠폰 번호</div>
              <div class="font-mono font-bold text-ink tracking-wider text-[15px]">{{ detail.display_code }}</div>
            </div>
            <div v-if="detail.group_buy.use_mode !== 'online'" class="bg-[#F4F6F8] rounded-xl py-2.5">
              <div class="text-[11px] text-ink-muted">확인 코드</div>
              <div class="font-mono font-black text-ink tracking-[0.3em] text-lg">{{ detail.confirm_code }}</div>
            </div>
            <div v-else class="bg-[#F4F6F8] rounded-xl py-2.5 flex items-center justify-center">
              <button @click="copy(detail.code)" class="text-xs text-amber-700 font-bold">번호 복사</button>
            </div>
          </div>
          <div v-if="detail.group_buy.use_mode !== 'online'" class="text-[11px] text-ink-muted -mt-2">휴대폰이 안 될 때만 가게에 확인 코드 4자리를 불러 주세요. 아무에게나 알려 주지 마세요.</div>

          <!-- 사용 안내 -->
          <div class="rounded-xl border border-gray-100 p-3 space-y-2 text-sm">
            <div class="font-semibold text-ink">사용 방법</div>
            <div v-if="detail.group_buy.use_mode !== 'online' && detail.group_buy.use_place" class="text-ink-light">
              <span class="text-xs text-ink-muted">매장</span> {{ detail.group_buy.use_place }}
            </div>
            <div v-if="detail.group_buy.use_mode !== 'store'" class="space-y-1.5">
              <button v-if="!onlineOpen" @click="onlineOpen = true" class="w-full py-2 rounded-lg bg-lime-50 text-lime-700 text-sm font-bold">온라인에서 사용하기</button>
              <div v-else class="space-y-1.5 text-ink-light">
                <div><span class="text-xs text-ink-muted">사이트</span>
                  <a :href="detail.group_buy.use_url" target="_blank" rel="noopener noreferrer" class="text-blue-600 underline break-all">{{ detail.group_buy.use_url }}</a></div>
                <div class="whitespace-pre-line">{{ detail.group_buy.use_instructions }}</div>
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="text-xs text-ink-muted">쿠폰 번호</span>
                  <span class="font-mono font-bold">{{ detail.code }}</span>
                  <button @click="copy(detail.code)" class="text-xs text-amber-700 font-bold">복사</button>
                </div>
                <div v-if="detail.group_buy.promo_code" class="flex items-center gap-2 flex-wrap">
                  <span class="text-xs text-ink-muted">할인 코드</span>
                  <span class="font-mono font-bold">{{ detail.group_buy.promo_code }}</span>
                  <button @click="copy(detail.group_buy.promo_code)" class="text-xs text-amber-700 font-bold">복사</button>
                </div>
                <div class="text-xs text-ink-muted">개설자가 주문을 확인하면 여기에 "맞나요?" 확인이 떠요. 24시간 안에 응답해 주세요.</div>
              </div>
            </div>
            <div v-if="detail.group_buy.use_mode !== 'online' && detail.group_buy.promo_code" class="flex items-center gap-2 flex-wrap text-ink-light">
              <span class="text-xs text-ink-muted">할인 코드</span><span class="font-mono font-bold">{{ detail.group_buy.promo_code }}</span>
            </div>
          </div>
        </template>

        <div class="text-xs text-ink-muted flex items-center justify-between flex-wrap gap-1">
          <span v-if="detail.expires_at">유효기간: {{ fmtDate(detail.expires_at) }}까지<template v-if="detail.extended"> (연장됨)</template></span>
          <button @click="reportOpen = !reportOpen" class="text-red-500 underline">문제 신고</button>
        </div>

        <!-- 신고 -->
        <div v-if="reportOpen" class="rounded-xl bg-red-50 p-3 space-y-2">
          <select v-model="reportType" class="input-soft px-3">
            <option value="fake_use">개설자가 쓰지 않았는데 사용 처리했어요</option>
            <option value="not_fulfilled">개설자가 약속을 지키지 않아요</option>
            <option value="code_theft">내 쿠폰을 다른 사람이 쓴 것 같아요</option>
            <option value="other">기타</option>
          </select>
          <textarea v-model="reportDetail" rows="2" maxlength="500" placeholder="자세한 내용 (선택)" class="input-soft px-3"></textarea>
          <button @click="sendReport" :disabled="busy" class="w-full py-2 rounded-lg bg-red-500 text-white text-sm font-bold disabled:opacity-50">관리자에게 신고</button>
        </div>

        <p v-if="message" class="text-xs" :class="messageError ? 'text-red-500' : 'text-emerald-600'">{{ message }}</p>
      </div>
    </div>
  </Teleport>
</div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import axios from 'axios'
import QRCode from 'qrcode'
import AppIcon from '../AppIcon.vue'

const items = ref([])
const loading = ref(true)
const error = ref('')

const detail = ref(null)
const qrDataUrl = ref('')
const onlineOpen = ref(false)
const busy = ref(false)
const message = ref('')
const messageError = ref(false)
const denyOpen = ref(false)
const denyReason = ref('')
const reportOpen = ref(false)
const reportType = ref('fake_use')
const reportDetail = ref('')
const now = ref(Date.now())
let pollTimer = null
let tickTimer = null
let qrFor = ''   // 지금 만들어 둔 QR 이 어느 주소의 것인지

const STATUS = {
  unused: { label: '사용 가능', cls: 'bg-emerald-100 text-emerald-700' },
  pending: { label: '확인 중', cls: 'bg-amber-100 text-amber-700' },
  used: { label: '사용 완료', cls: 'bg-gray-200 text-gray-600' },
  expired: { label: '만료', cls: 'bg-gray-200 text-gray-500' },
  cancelled: { label: '취소', cls: 'bg-gray-200 text-gray-500' },
}
const statusInfo = s => STATUS[s] || { label: s, cls: 'bg-gray-100 text-gray-500' }
const imgUrl = p => (p && /^(https?:)?\/\//.test(p) ? p : '/storage/' + String(p || '').replace(/^\/?(storage\/)?/, ''))

function fmtDate(v) {
  const d = new Date(v)
  return isNaN(d) ? '' : `${d.getFullYear()}.${d.getMonth() + 1}.${d.getDate()}`
}
function fmtDateTime(v) {
  const d = new Date(v)
  return isNaN(d) ? '' : `${d.getMonth() + 1}/${d.getDate()} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}

// 가게 요청의 남은 응답 시간 (매장 3분 / 온라인 24시간)
const countdown = computed(() => {
  const p = detail.value?.pending
  if (!p?.expires_at) return ''
  const sec = Math.max(0, Math.floor((new Date(p.expires_at).getTime() - now.value) / 1000))
  if (sec >= 3600) return `${Math.floor(sec / 3600)}시간 ${Math.floor((sec % 3600) / 60)}분 안에 응답해 주세요`
  return `${Math.floor(sec / 60)}분 ${String(sec % 60).padStart(2, '0')}초 안에 응답해 주세요`
})

async function loadList() {
  try {
    const { data } = await axios.get('/api/coupons')
    items.value = data.data || []
  } catch (e) {
    error.value = e.response?.data?.message || '불러오지 못했어요'
  }
  loading.value = false
}

async function loadDetail(id, silent = false) {
  try {
    const { data } = await axios.get(`/api/coupons/${id}`)
    detail.value = data.data
    if (!qrDataUrl.value || qrFor !== data.data.qr_url) {
      qrFor = data.data.qr_url
      try { qrDataUrl.value = await QRCode.toDataURL(data.data.qr_url, { width: 320, margin: 1, errorCorrectionLevel: 'M' }) } catch { qrDataUrl.value = '' }
    }
  } catch (e) {
    if (!silent) { message.value = e.response?.data?.message || '쿠폰을 불러오지 못했어요'; messageError.value = true }
  }
}

function startPolling(id) {
  stopPolling()
  // 열려 있는 동안 3초마다 새로 확인 — 가게가 사용 확인을 요청하면 바로 "맞나요?"가 뜬다
  pollTimer = setInterval(() => { if (detail.value && ['unused', 'pending', 'used'].includes(detail.value.status)) loadDetail(id, true) }, 3000)
  tickTimer = setInterval(() => { now.value = Date.now() }, 1000)
}
function stopPolling() { clearInterval(pollTimer); clearInterval(tickTimer); pollTimer = tickTimer = null }

async function openDetail(id) {
  message.value = ''; messageError.value = false; denyOpen.value = false; denyReason.value = ''; reportOpen.value = false; onlineOpen.value = false; qrDataUrl.value = ''; qrFor = ''
  await loadDetail(id)
  if (detail.value) startPolling(id)
}
function closeDetail() { stopPolling(); detail.value = null; loadList() }

async function act(fn, okMsg) {
  if (busy.value || !detail.value) return
  busy.value = true; message.value = ''; messageError.value = false
  try {
    const { data } = await fn()
    message.value = data?.message || okMsg || ''
    await loadDetail(detail.value.id, true)
  } catch (e) {
    message.value = e.response?.data?.message || '처리하지 못했어요'
    messageError.value = true
  }
  busy.value = false
}
const respond = accept => act(() => axios.post(`/api/coupons/${detail.value.id}/respond`, { accept, reason: denyReason.value.trim() || undefined }), accept ? '사용이 확인됐어요' : '사용을 거절했어요')
const undo = action => act(() => axios.post(`/api/coupons/${detail.value.id}/undo`, { action }))
function sendReport() {
  act(() => axios.post(`/api/coupons/${detail.value.id}/report`, { type: reportType.value, detail: reportDetail.value.trim() || undefined }), '신고가 접수됐어요')
  reportOpen.value = false; reportDetail.value = ''
}
async function copy(text) {
  try { await navigator.clipboard.writeText(String(text)); message.value = '복사했어요'; messageError.value = false } catch { message.value = '복사하지 못했어요'; messageError.value = true }
}

watch(() => detail.value?.status, () => { denyOpen.value = false })
onMounted(loadList)
onBeforeUnmount(stopPolling)
</script>
