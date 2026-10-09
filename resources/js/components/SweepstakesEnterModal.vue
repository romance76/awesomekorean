<template>
  <Teleport to="body">
    <Transition name="sem">
      <div v-if="show" class="fixed inset-0 z-[70] flex items-end sm:items-center justify-center" @keydown.esc="tryClose">
        <div class="absolute inset-0 bg-black/50" @click="tryClose"></div>
        <div class="relative w-full sm:max-w-md bg-white rounded-t-3xl sm:rounded-3xl shadow-2xl p-5 pb-7 sm:pb-5 max-h-[92vh] overflow-y-auto" role="dialog" aria-modal="true">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-base font-black text-ink">
              {{ step === 1 ? '참가 개수 선택' : step === 2 ? '참가 확인' : '참가 완료' }}
            </h3>
            <button v-if="!sending" @click="tryClose" class="w-8 h-8 grid place-items-center rounded-full text-ink-faint hover:text-ink hover:bg-gray-100" aria-label="닫기">
              <AppIcon name="x" :size="16" />
            </button>
          </div>

          <div class="rounded-xl bg-amber-50 border border-amber-100 px-3 py-2 mb-4 text-xs text-ink-muted">
            🎁 <span class="font-bold text-ink">{{ sweepstakes?.prize_name || sweepstakes?.title }}</span>
          </div>

          <!-- Step 1 -->
          <div v-if="step === 1">
            <template v-if="balanceNow < 1">
              <div class="text-center py-6">
                <div class="text-4xl mb-2">🎟</div>
                <p class="text-sm font-bold text-ink">사용할 수 있는 Entry가 없어요</p>
                <p class="text-xs text-ink-muted mt-1">출석체크 등으로 Entry를 모은 뒤 다시 참가해 주세요.</p>
              </div>
              <button @click="$emit('close')" class="btn-primary w-full">닫기</button>
            </template>
            <template v-else>
              <div class="text-xs text-ink-muted mb-3">
                내 Entry 잔액 <span class="font-bold text-amber-600">🎟 {{ balanceNow }}</span>
                · 이미 응모 <span class="font-bold text-ink">{{ mine }}</span>개
              </div>

              <div class="flex items-center justify-center gap-3 mb-3">
                <button @click="setAmount(amount - 1)" :disabled="amount <= 1" class="w-11 h-11 rounded-full border-2 border-amber-300 text-amber-700 text-xl font-black disabled:opacity-30" aria-label="하나 줄이기">−</button>
                <input :value="amount" @input="onInput" inputmode="numeric" type="text" maxlength="6"
                  class="input-soft w-24 text-center text-2xl font-black tabular-nums" aria-label="참가 개수" />
                <button @click="setAmount(amount + 1)" :disabled="amount >= balanceNow" class="w-11 h-11 rounded-full border-2 border-amber-300 text-amber-700 text-xl font-black disabled:opacity-30" aria-label="하나 늘리기">+</button>
              </div>

              <div class="flex flex-wrap justify-center gap-2 mb-4">
                <button v-for="c in chips" :key="c.label" @click="setAmount(c.value)"
                  class="px-3.5 py-1.5 rounded-full text-xs font-bold border transition-colors"
                  :class="amount === c.value ? 'bg-amber-400 text-white border-amber-400' : 'bg-white text-amber-700 border-amber-300 hover:bg-amber-50'">{{ c.label }}</button>
              </div>

              <div class="rounded-xl bg-gray-50 px-3 py-2.5 text-xs text-ink-muted mb-4 space-y-1">
                <div class="flex justify-between"><span>참가 후 내 응모 수</span><span class="font-bold text-ink">{{ mine + amount }}개</span></div>
                <div class="flex justify-between"><span>참가 후 Entry 잔액</span><span class="font-bold text-ink">{{ balanceNow - amount }}개</span></div>
                <div v-if="probability !== null" class="flex justify-between"><span>예상 당첨 확률</span><span class="font-black text-amber-600">{{ probability }}%</span></div>
              </div>
              <p v-if="probability !== null" class="text-[11px] text-ink-faint -mt-2 mb-4">다른 참가자가 더 응모하면 확률은 바뀔 수 있어요.</p>

              <p v-if="error" class="text-xs text-red-500 mb-2">{{ error }}</p>
              <button @click="goConfirm" :disabled="amount < 1 || amount > balanceNow" class="btn-primary w-full disabled:opacity-40">다음</button>
            </template>
          </div>

          <!-- Step 2 -->
          <div v-else-if="step === 2">
            <div class="text-center py-3">
              <div class="text-4xl mb-2">🎟</div>
              <p class="text-sm text-ink leading-relaxed">
                Entry <b class="text-amber-600 text-lg">{{ amount }}</b>개를 사용해<br>
                '<b>{{ sweepstakes?.prize_name || sweepstakes?.title }}</b>'에 참가합니다.
              </p>
              <p class="text-xs text-red-500 mt-2">사용한 Entry는 돌려받을 수 없어요.</p>
              <p class="text-sm font-bold text-ink mt-3">맞습니까?</p>
            </div>
            <p v-if="error" class="text-xs text-red-500 mb-2 text-center">{{ error }}</p>
            <div class="flex gap-2 mt-3">
              <button @click="step = 1; error = ''" :disabled="sending" class="flex-1 rounded-xl border-2 border-gray-200 text-ink-muted font-bold py-2.5 text-sm disabled:opacity-40">뒤로</button>
              <button @click="submit" :disabled="sending" class="flex-1 btn-primary disabled:opacity-60">{{ sending ? '처리 중...' : '확인 (OK)' }}</button>
            </div>
          </div>

          <!-- Step 3 -->
          <div v-else>
            <div class="text-center py-2">
              <div class="text-4xl mb-1">🎉</div>
              <p class="text-sm font-bold text-ink">{{ result?.used }}개 참가가 완료됐어요!</p>
            </div>
            <div class="rounded-xl bg-gray-50 px-3 py-2.5 text-xs text-ink-muted my-3 space-y-1">
              <div class="flex justify-between"><span>남은 Entry</span><span class="font-bold text-ink">🎟 {{ result?.remaining }}</span></div>
              <div class="flex justify-between"><span>내 총 응모 수</span><span class="font-bold text-ink">{{ result?.my_entries }}개</span></div>
              <div class="flex justify-between"><span>현재 당첨 확률</span><span class="font-black text-amber-600">{{ result?.probability }}%</span></div>
            </div>
            <label class="flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50/60 px-3 py-3 cursor-pointer select-none">
              <input type="checkbox" class="mt-0.5 w-4 h-4 accent-amber-500" :checked="reminderOn" :disabled="reminderBusy" @change="toggleReminder($event.target.checked)" />
              <span class="text-sm text-ink font-bold">⏰ 추첨 시작 5분 전에 알려 주세요
                <span class="block text-[11px] font-normal text-ink-muted mt-0.5">화면 아래 출석체크 알림처럼 떠요.</span>
              </span>
            </label>
            <button @click="$emit('close')" class="btn-primary w-full mt-4">닫기</button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import axios from 'axios'
import { useAuthStore } from '../stores/auth'
import { useSiteStore } from '../stores/site'
import AppIcon from './AppIcon.vue'

const props = defineProps({
  sweepstakes: { type: Object, default: null },
  balance: { type: Number, default: 0 },
  show: { type: Boolean, default: false },
})
const emit = defineEmits(['close', 'entered'])

const auth = useAuthStore()
const site = useSiteStore()

const step = ref(1)
const amount = ref(1)
const sending = ref(false)
const error = ref('')
const result = ref(null)
const reminderOn = ref(false)
const reminderBusy = ref(false)
const spentBalance = ref(null) // 참가 직후 서버가 알려준 잔액

const balanceNow = computed(() => Math.max(0, Number(props.balance) || 0))
const mine = computed(() => Number(props.sweepstakes?.my_entries) || 0)
const total = computed(() => Number(props.sweepstakes?.total_entries) || 0)
const probability = computed(() => {
  if (!(total.value > 0 || mine.value > 0)) return null
  const denom = total.value + amount.value
  if (denom <= 0) return null
  return Math.round(((mine.value + amount.value) / denom) * 10000) / 100
})
const chips = computed(() => {
  const b = balanceNow.value
  const list = [1, 5, 10].filter(v => v <= b).map(v => ({ label: String(v), value: v }))
  if (b > 0) list.push({ label: '전부', value: b })
  // 중복 값 제거 (예: 잔액 5 → 5 와 전부)
  const seen = new Set()
  return list.filter(c => (seen.has(c.value) ? false : (seen.add(c.value), true)))
})

function setAmount(v) {
  const n = Math.floor(Number(v))
  if (!Number.isFinite(n)) return
  amount.value = Math.min(Math.max(1, n), Math.max(1, balanceNow.value))
}
function onInput(e) {
  const digits = String(e.target.value).replace(/[^0-9]/g, '')
  if (digits === '') { amount.value = 1; e.target.value = '1'; return }
  setAmount(digits)
  e.target.value = String(amount.value)
}

function goConfirm() {
  error.value = ''
  if (amount.value < 1 || amount.value > balanceNow.value) { error.value = '참가 개수를 확인해 주세요'; return }
  step.value = 2
}

function newKey() {
  try { if (crypto?.randomUUID) return crypto.randomUUID() } catch {}
  return 'k' + Date.now().toString(36) + Math.random().toString(36).slice(2, 12)
}

async function submit() {
  if (sending.value) return
  sending.value = true
  error.value = ''
  const used = amount.value
  try {
    const { data } = await axios.post(`/api/sweepstakes/${props.sweepstakes.id}/enter`, {
      amount: used,
      idempotency_key: newKey(),
    })
    const d = data?.data || {}
    const remaining = d.remaining_entries ?? (balanceNow.value - used)
    if (auth.user) auth.user.entries = remaining
    spentBalance.value = remaining
    result.value = {
      used,
      remaining,
      my_entries: d.my_entries ?? (mine.value + used),
      probability: d.my_win_probability_pct ?? 0,
    }
    reminderOn.value = !!props.sweepstakes?.my_reminder
    step.value = 3
    emit('entered', { ...d, used })
  } catch (e) {
    error.value = e.response?.data?.message
      || (e.response?.data?.errors ? Object.values(e.response.data.errors).flat()[0] : '')
      || '응모에 실패했어요. 잠시 후 다시 시도해 주세요'
  }
  sending.value = false
}

async function toggleReminder(on) {
  if (reminderBusy.value) return
  const prev = reminderOn.value
  reminderOn.value = on // 낙관적 반영
  reminderBusy.value = true
  try {
    if (on) await axios.post(`/api/sweepstakes/${props.sweepstakes.id}/reminder`)
    else await axios.delete(`/api/sweepstakes/${props.sweepstakes.id}/reminder`)
    site.toast(on ? '⏰ 추첨 5분 전에 알려드릴게요' : '알림을 해제했어요', 'success')
  } catch (e) {
    reminderOn.value = prev
    site.toast(e.response?.data?.message || '알림 설정에 실패했어요', 'error')
  }
  reminderBusy.value = false
}

function tryClose() {
  if (sending.value) return
  emit('close')
}

watch(() => props.show, (v) => {
  if (v) {
    step.value = 1
    error.value = ''
    result.value = null
    spentBalance.value = null
    reminderOn.value = !!props.sweepstakes?.my_reminder
    amount.value = Math.min(1, Math.max(1, balanceNow.value))
  }
}, { immediate: true })
</script>

<style scoped>
.sem-enter-active, .sem-leave-active { transition: opacity .2s; }
.sem-enter-from, .sem-leave-to { opacity: 0; }
</style>
