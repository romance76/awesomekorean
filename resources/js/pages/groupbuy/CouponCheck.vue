<template>
<div class="min-h-screen">
  <div class="max-w-md mx-auto px-4 py-6 space-y-4">
    <div class="text-center">
      <div class="icon-chip w-12 h-12 bg-lime-50 text-lime-600 mx-auto mb-2"><AppIcon name="ticket" :size="24" /></div>
      <h1 class="text-lg font-bold text-ink">공동구매 쿠폰 확인</h1>
      <p class="text-xs text-ink-muted mt-1">가게 직원이 손님의 쿠폰을 확인하는 화면이에요.</p>
    </div>

    <div v-if="loading" class="text-center text-sm text-ink-muted py-10">확인하는 중...</div>

    <!-- 알 수 없는 쿠폰 -->
    <div v-else-if="notFound" class="card p-5 text-center text-sm text-red-600">알 수 없는 쿠폰이에요. QR을 다시 찍어 주세요.</div>

    <!-- 잠금 -->
    <div v-else-if="locked" class="card p-5 text-center space-y-2">
      <div class="text-sm font-semibold text-red-600">PIN이 잠겼어요</div>
      <div class="text-xs text-ink-muted">{{ message }}</div>
    </div>

    <!-- PIN 입력 (개설자가 로그인하지 않았을 때) -->
    <div v-else-if="!info" class="card p-5 space-y-3">
      <div class="text-sm font-semibold text-ink">가게 확인 PIN</div>
      <div class="text-xs text-ink-muted">공동구매를 연 가게에서 정한 숫자 4자리 PIN을 입력해 주세요.</div>
      <input v-model="pin" type="password" inputmode="numeric" maxlength="4" placeholder="••••" autocomplete="off"
        class="input-soft px-3 text-center text-2xl tracking-[0.5em]" @keyup.enter="checkPin" />
      <button @click="checkPin" :disabled="busy || pin.length !== 4" class="btn-primary w-full py-3 text-sm disabled:opacity-50">확인</button>
      <p v-if="message" class="text-xs text-red-500">{{ message }}</p>
    </div>

    <!-- 쿠폰 정보 -->
    <template v-else>
      <div class="card p-5 space-y-3">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <div class="font-bold text-ink break-words">{{ info.title }}</div>
            <div class="text-xs text-ink-muted mt-0.5">손님 {{ info.holder }} · 쿠폰 {{ info.display_code }}</div>
          </div>
          <span class="text-xs px-2 py-0.5 rounded-full font-bold flex-shrink-0" :class="statusInfo(info.status).cls">{{ statusInfo(info.status).label }}</span>
        </div>
        <div class="grid grid-cols-2 gap-2 text-center">
          <div class="bg-[#F4F6F8] rounded-xl py-2"><div class="text-[11px] text-ink-muted">공동구매 가격</div><div class="font-bold text-ink">${{ Number(info.price || 0).toLocaleString() }}</div></div>
          <div class="bg-[#F4F6F8] rounded-xl py-2"><div class="text-[11px] text-ink-muted">유효기간</div><div class="font-bold text-ink text-sm">{{ fmtDate(info.expires_at) }}까지</div></div>
        </div>
        <div v-if="info.use_place" class="text-xs text-ink-muted">사용 장소: {{ info.use_place }}</div>

        <!-- 쓸 수 있는 쿠폰 -->
        <template v-if="info.status === 'unused'">
          <div v-if="info.use_mode === 'online'" class="rounded-xl bg-gray-50 p-3 text-sm text-ink-muted">이 쿠폰은 온라인에서만 쓸 수 있어요. 개설자가 "내 공동구매"에서 쿠폰 번호로 사용을 요청해 주세요.</div>
          <template v-else>
            <div class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">이 쿠폰은 <b>한 번도 사용하지 않은 쿠폰</b>이에요. 사용을 수락할까요?</div>
            <button @click="accept" :disabled="busy" class="w-full py-3.5 rounded-xl bg-emerald-500 text-white text-base font-bold disabled:opacity-50">사용 수락</button>
            <div class="text-[11px] text-ink-muted">수락하면 손님 휴대폰에 "○○에서 사용합니다. 맞나요?"가 떠요. 손님이 "맞아요"를 눌러야 사용 완료예요. (3분 안에)</div>
          </template>
        </template>

        <!-- 손님 응답 기다리는 중 -->
        <template v-else-if="info.status === 'pending'">
          <div class="rounded-xl border-2 border-amber-300 bg-amber-50 p-4 text-center space-y-1">
            <div class="text-sm font-bold text-ink">손님의 확인을 기다리는 중이에요</div>
            <div class="text-2xl font-black text-amber-600 tabular-nums">{{ countdown }}</div>
            <div class="text-xs text-amber-800">손님이 "맞아요"를 누르면 자동으로 사용 완료돼요.</div>
          </div>
          <div v-if="info.use_mode !== 'online'" class="rounded-xl border border-gray-200 p-3 space-y-2">
            <div class="text-xs text-ink-light">손님 휴대폰이 안 되면, 손님이 쿠폰 화면의 <b>확인 코드 4자리</b>를 불러 줄 때 여기에 입력하세요.</div>
            <div class="flex gap-2">
              <input v-model="confirmCode" type="text" inputmode="numeric" maxlength="4" placeholder="0000" class="input-soft px-3 flex-1 text-center text-xl tracking-[0.4em]" @keyup.enter="submitCode" />
              <button @click="submitCode" :disabled="busy || confirmCode.length !== 4" class="px-4 rounded-xl bg-amber-500 text-white text-sm font-bold disabled:opacity-40">확인</button>
            </div>
          </div>
        </template>

        <div v-else-if="info.status === 'used'" class="rounded-xl bg-emerald-50 p-4 text-center">
          <div class="text-2xl">✓</div>
          <div class="text-sm font-bold text-emerald-700">사용 완료</div>
          <div class="text-xs text-emerald-700 mt-0.5">{{ fmtDateTime(info.used_at) }}</div>
        </div>
        <div v-else-if="info.status === 'expired'" class="rounded-xl bg-gray-50 p-3 text-sm text-ink-muted text-center">유효기간이 지난 쿠폰이라 쓸 수 없어요.</div>
        <div v-else class="rounded-xl bg-gray-50 p-3 text-sm text-ink-muted text-center">쓸 수 없는 쿠폰이에요.</div>

        <p v-if="message" class="text-xs" :class="messageError ? 'text-red-500' : 'text-emerald-600'">{{ message }}</p>
      </div>
      <div v-if="declinedNote" class="card p-4 text-xs text-amber-800 bg-amber-50">{{ declinedNote }}</div>
    </template>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const route = useRoute()
const code = route.params.code
const base = `/api/coupon-check/${encodeURIComponent(code)}`

const loading = ref(true)
const notFound = ref(false)
const locked = ref(false)
const info = ref(null)
const pin = ref('')
const confirmCode = ref('')
const busy = ref(false)
const message = ref('')
const messageError = ref(false)
const declinedNote = ref('')
const now = ref(Date.now())
let usedPin = ''          // 화면을 닫기 전까지만 기억 — 저장하지 않는다
let pollTimer = null
let tickTimer = null
let endsAt = 0
let prevStatus = ''

const STATUS = {
  unused: { label: '미사용', cls: 'bg-emerald-100 text-emerald-700' },
  pending: { label: '손님 확인 중', cls: 'bg-amber-100 text-amber-700' },
  used: { label: '사용 완료', cls: 'bg-gray-200 text-gray-600' },
  expired: { label: '만료', cls: 'bg-gray-200 text-gray-500' },
  cancelled: { label: '취소', cls: 'bg-gray-200 text-gray-500' },
}
const statusInfo = s => STATUS[s] || { label: s, cls: 'bg-gray-100 text-gray-500' }
function fmtDate(v) { const d = new Date(v); return isNaN(d) ? '' : `${d.getFullYear()}.${d.getMonth() + 1}.${d.getDate()}` }
function fmtDateTime(v) { const d = new Date(v); return isNaN(d) ? '' : `${d.getMonth() + 1}/${d.getDate()} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}` }

const countdown = computed(() => {
  if (!endsAt) return ''
  const sec = Math.max(0, Math.floor((endsAt - now.value) / 1000))
  return `${Math.floor(sec / 60)}:${String(sec % 60).padStart(2, '0')}`
})

function apply(data) {
  const wasPending = prevStatus === 'pending'
  info.value = data
  prevStatus = data.status
  endsAt = data.seconds_left != null ? Date.now() + data.seconds_left * 1000 : 0
  // 손님이 거절했거나 시간이 지나 다시 미사용으로 돌아온 경우 알려 준다
  declinedNote.value = (wasPending && data.status === 'unused') ? '손님이 사용을 거절했거나 시간이 지나 요청이 취소됐어요. 다시 수락하거나 손님과 확인해 주세요.' : ''
  if (data.status === 'pending') startPolling(); else stopPolling()
}

async function call(path, body = {}) {
  return axios.post(`${base}/${path}`, { ...(usedPin ? { pin: usedPin } : {}), ...body })
}

async function lookup() {
  message.value = ''
  try {
    const { data } = await call('lookup')
    apply(data.data)
  } catch (e) {
    const st = e.response?.status
    if (st === 404) notFound.value = true
    else if (st === 429) { locked.value = true; message.value = e.response?.data?.message || '' }
    else if (st === 401 || st === 403) { info.value = null; if (usedPin) message.value = e.response?.data?.message || 'PIN이 맞지 않아요.' }
    else message.value = e.response?.data?.message || '확인하지 못했어요'
  }
  loading.value = false
}

async function checkPin() {
  if (pin.value.length !== 4 || busy.value) return
  busy.value = true
  usedPin = pin.value
  await lookup()
  if (!info.value) usedPin = ''
  pin.value = ''
  busy.value = false
}

async function accept() {
  if (busy.value) return
  busy.value = true; message.value = ''; messageError.value = false
  try {
    const { data } = await call('accept')
    message.value = data?.message || ''
    apply(data.data)
  } catch (e) {
    message.value = e.response?.data?.message || '사용 수락을 보내지 못했어요'
    messageError.value = true
    await refresh()
  }
  busy.value = false
}

async function submitCode() {
  if (busy.value || confirmCode.value.length !== 4) return
  busy.value = true; message.value = ''; messageError.value = false
  try {
    const { data } = await call('confirm-code', { confirm_code: confirmCode.value })
    message.value = data?.message || ''
    confirmCode.value = ''
    apply(data.data)
  } catch (e) {
    message.value = e.response?.data?.message || '확인하지 못했어요'
    messageError.value = true
    confirmCode.value = ''
  }
  busy.value = false
}

async function refresh() {
  try {
    const { data } = await call('status')
    apply(data.data)
  } catch { /* 다음 확인 때 다시 */ }
}

function startPolling() {
  if (pollTimer) return
  pollTimer = setInterval(refresh, 2000)
  tickTimer = setInterval(() => { now.value = Date.now() }, 1000)
}
function stopPolling() { clearInterval(pollTimer); clearInterval(tickTimer); pollTimer = tickTimer = null }

onMounted(lookup)
onBeforeUnmount(stopPolling)
</script>
