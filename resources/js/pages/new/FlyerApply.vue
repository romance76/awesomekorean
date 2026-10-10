<template>
<div :class="embedded ? '' : 'min-h-screen'">
  <div :class="embedded ? '' : 'page-main px-4 py-5'">
    <PageHeader title="NEW 전면광고 신청" icon="megaphone" chip="bg-amber-50 text-amber-600" :back="!embedded" fallback="/new">
      <template #actions><RouterLink to="/new" class="text-xs font-semibold text-ink-muted hover:text-amber-600 transition-colors">NEW 게시판 보기 →</RouterLink></template>
    </PageHeader>
    <p class="text-sm text-ink-muted mb-5">라디오 광고처럼 <b>하루 중 원하는 시간대</b>를 골라 사세요. 고른 시간에 NEW 게시판 맨 위에 전단이 통째로 나가고, 남은 시간은 다른 광고주가 쓸 수 있어요.</p>

    <VerifyGate message="이메일 인증 후 전면광고를 신청할 수 있어요.">
    <form @submit.prevent="submit" class="space-y-4">
      <!-- 1. 전단 내용 -->
      <div class="card p-5 space-y-4">
        <h2 class="font-bold text-ink text-sm flex items-center gap-1.5"><span class="w-5 h-5 rounded-full bg-amber-500 text-white text-xs flex items-center justify-center">1</span>전단 내용</h2>

        <div>
          <label class="input-label">상호 / 제목 *</label>
          <input v-model="form.title" maxlength="80" required class="input-soft" placeholder="예: 서울식당 수와니점 GRAND OPEN" />
        </div>
        <div>
          <label class="input-label">종류 *</label>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            <button v-for="k in KINDS" :key="k.value" type="button" @click="form.kind = k.value"
              class="border-2 rounded-lg px-2 py-2 text-xs font-bold transition-colors"
              :class="form.kind === k.value ? 'bg-amber-50 border-amber-400 text-amber-700' : 'bg-white border-gray-200 text-ink-light hover:border-amber-200'">{{ k.label }}</button>
          </div>
        </div>
        <div>
          <label class="input-label">전단 이미지 * <span class="text-ink-faint font-normal">(JPG·PNG·WEBP, 6MB 이하 — 세로로 긴 전단 한 장이 가장 잘 보여요)</span></label>
          <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" @change="onFile" class="block w-full text-sm text-ink-light file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-amber-50 file:text-amber-700 file:font-bold hover:file:bg-amber-100" />
          <img v-if="preview" :src="preview" alt="미리보기" class="mt-3 max-h-80 mx-auto rounded-lg border border-gray-100" />
        </div>
        <div>
          <label class="input-label">설명 (선택)</label>
          <textarea v-model="form.description" maxlength="400" rows="3" class="input-soft" placeholder="예: 오픈 기념 전 메뉴 20% 할인 · 주차 가능"></textarea>
          <div class="text-xs text-ink-faint mt-0.5">{{ form.description.length }}/400</div>
        </div>
        <div class="grid sm:grid-cols-2 gap-3">
          <div>
            <label class="input-label">전화번호 (선택)</label>
            <input v-model="form.phone" maxlength="30" class="input-soft" placeholder="770-555-1234" />
          </div>
          <div>
            <label class="input-label">링크 (선택)</label>
            <input v-model="form.link_url" type="url" maxlength="300" class="input-soft" placeholder="https://..." />
          </div>
        </div>
      </div>

      <!-- 2. 노출 지역 -->
      <div class="card p-5 space-y-3">
        <h2 class="font-bold text-ink text-sm flex items-center gap-1.5"><span class="w-5 h-5 rounded-full bg-amber-500 text-white text-xs flex items-center justify-center">2</span>노출 지역</h2>
        <div class="grid grid-cols-2 gap-2">
          <button type="button" @click="form.scope = 'state'" class="border-2 rounded-xl p-3 text-left transition-colors"
            :class="form.scope === 'state' ? 'bg-amber-50 border-amber-400' : 'bg-white border-gray-200 hover:border-amber-200'">
            <div class="font-bold text-sm text-ink">📍 내 지역 (주 단위)</div>
            <div class="text-[11px] text-ink-muted mt-0.5">그 주 방문자에게만 · 시간당 {{ usd(basePrice('state')) }}~</div>
          </button>
          <button type="button" @click="form.scope = 'national'" class="border-2 rounded-xl p-3 text-left transition-colors"
            :class="form.scope === 'national' ? 'bg-amber-50 border-amber-400' : 'bg-white border-gray-200 hover:border-amber-200'">
            <div class="font-bold text-sm text-ink">🇺🇸 전국</div>
            <div class="text-[11px] text-ink-muted mt-0.5">전국 어디서 봐도 · 시간당 {{ usd(basePrice('national')) }}~</div>
          </button>
        </div>
        <div v-if="form.scope === 'state'">
          <label class="input-label">주 (State)</label>
          <select v-model="form.state" class="input-soft">
            <option v-for="s in US_STATES" :key="s.code" :value="s.code">{{ s.name }} ({{ s.code }})</option>
          </select>
        </div>
      </div>

      <!-- 3. 기간 + 시간대 -->
      <div class="card p-5 space-y-4">
        <h2 class="font-bold text-ink text-sm flex items-center gap-1.5"><span class="w-5 h-5 rounded-full bg-amber-500 text-white text-xs flex items-center justify-center">3</span>언제, 며칠 동안?</h2>

        <div class="grid sm:grid-cols-2 gap-3">
          <div>
            <label class="input-label">시작일</label>
            <input v-model="form.start_date" type="date" :min="av?.min_date" :max="av?.max_date" required class="input-soft" />
            <div class="text-[11px] text-ink-faint mt-1">시작일은 내일부터 선택할 수 있어요</div>
          </div>
          <div>
            <label class="input-label">며칠 동안</label>
            <div class="flex flex-wrap gap-1.5">
              <button v-for="d in dayChoices" :key="d" type="button" @click="form.days = d"
                class="px-3 py-2 rounded-lg border-2 text-xs font-bold transition-colors"
                :class="form.days === d ? 'bg-amber-50 border-amber-400 text-amber-700' : 'bg-white border-gray-200 text-ink-light hover:border-amber-200'">{{ d }}일</button>
            </div>
          </div>
        </div>

        <div>
          <div class="flex items-center justify-between flex-wrap gap-2 mb-2">
            <label class="input-label !mb-0">방송할 시간대 <span class="text-ink-faint font-normal">(매일 같은 시간 · {{ av ? tzLabel(av.tz) : '' }} 현지 시각)</span></label>
            <div class="flex gap-1 text-[11px]">
              <button type="button" @click="pickPeak" class="chip">피크 {{ peakLabel }}</button>
              <button type="button" @click="pickAll" class="chip">24시간 전체</button>
              <button type="button" @click="form.hours = []" class="chip">초기화</button>
            </div>
          </div>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            <button v-for="(b, i) in blocks" :key="i" type="button" :disabled="b.booked" @click="toggleBlock(b)"
              class="rounded-xl border-2 px-2 py-2.5 text-center transition-colors disabled:cursor-not-allowed"
              :class="blockClass(b)">
              <div class="text-[13px] font-bold leading-tight">{{ blockLabel(b) }}</div>
              <div class="text-[10px] leading-tight mt-0.5 opacity-80">{{ b.end - b.start }}시간 · {{ blockTag(b) }}</div>
              <div class="text-xs font-bold mt-1">{{ b.booked ? '예약됨' : usd(b.price) + '/일' }}</div>
            </button>
          </div>
          <p class="text-[11px] text-ink-faint mt-2">시간은 몇 시간씩 묶인 칸으로 팔아요. 피크({{ peakLabel }})는 이용자가 많아 비싸고 새벽(자정~오전 6시)은 저렴해요. “예약됨”은 같은 지역에서 다른 광고주가 이미 쓰는 시간이 들어 있는 칸이에요.</p>
        </div>
      </div>

      <!-- 요약 / 결제 -->
      <div class="card p-5 border-2 border-amber-100">
        <div class="space-y-1.5 text-sm">
          <div class="flex justify-between"><span class="text-ink-muted">방송 시간</span><span class="font-semibold">{{ form.hours.length }}시간/일 × {{ form.days }}일 = {{ slotCount }}시간</span></div>
          <div v-if="form.hours.length" class="flex justify-between gap-4"><span class="text-ink-muted flex-shrink-0">시간대</span><span class="text-right text-xs">{{ hourRanges(form.hours).join(', ') }}</span></div>
          <div class="flex justify-between items-baseline pt-2 border-t border-gray-100"><span class="font-bold text-ink">총 비용</span><span class="text-xl font-black text-amber-600">{{ usd(total) }}</span></div>
        </div>
        <p v-if="error" class="text-sm text-red-500 mt-3">{{ error }}</p>
        <ul v-if="missing.length" class="mt-3 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 space-y-0.5">
          <li v-for="m in missing" :key="m">• {{ m }}</li>
        </ul>
        <button type="submit" :disabled="submitting || !canSubmit" class="btn-primary w-full py-3 rounded-xl text-sm mt-4 disabled:opacity-50">
          {{ submitting ? '준비 중...' : `${usd(total)} 카드로 신청하기` }}
        </button>
        <p class="text-[11px] text-ink-faint mt-2">다음 단계에서 카드를 확인하지만 <b>지금은 청구되지 않아요.</b> 관리자가 승인하면 그때 카드에 청구되고, 반려되거나 승인 전에 취소하면 한 푼도 청구되지 않습니다.</p>
      </div>
    </form>
    </VerifyGate>

    <!-- 내 신청 내역 -->
    <h2 class="font-bold text-ink text-sm mt-8 mb-2">내 전단 광고</h2>
    <div v-if="!mine.length" class="text-sm text-ink-faint text-center py-6 card">아직 신청 내역이 없어요</div>
    <div v-else class="space-y-2">
      <div v-for="m in mine" :key="m.id" class="card p-3 flex items-center gap-3">
        <img :src="m.image_url" class="w-12 h-16 object-cover rounded border border-gray-100 flex-shrink-0" alt="" />
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-1.5">
            <span class="text-[11px] font-bold px-1.5 py-0.5 rounded border" :class="STATUS_LABEL[m.status]?.cls">{{ STATUS_LABEL[m.status]?.text }}</span>
            <span class="text-sm font-semibold text-ink truncate">{{ m.title }}</span>
          </div>
          <div class="text-[11px] text-ink-muted mt-0.5">{{ m.scope === 'national' ? '전국' : stateName(m.region_key) }} · {{ fmtDay(m.start_date) }}~{{ fmtDay(m.end_date) }} · {{ m.hours_count }}시간 · {{ adMoney(m, m.total_price) }}</div>
          <div v-if="m.status === 'approved'" class="text-[11px] text-ink-faint">노출 {{ fmt(m.view_count) }} · 클릭 {{ fmt(m.click_count) }}</div>
          <div v-if="m.reject_reason && m.status !== 'approved'" class="text-[11px] text-red-500">{{ m.reject_reason }}</div>
        </div>
        <div class="flex flex-col gap-1 flex-shrink-0">
          <RouterLink :to="`/new/${m.id}`" class="text-xs text-ink-muted hover:text-amber-600 text-center">보기</RouterLink>
          <button v-if="m.status === 'pending'" @click="cancel(m)" class="text-xs text-red-400 hover:text-red-600">{{ m.payment_method === 'card' ? '신청 취소' : '취소·환불' }}</button>
        </div>
      </div>
    </div>
  </div>

  <!-- 카드 확인 (승인 후 청구) -->
  <Teleport to="body">
    <div v-if="checkout" class="fixed inset-0 z-[80] bg-black/50 flex items-center justify-center p-4" @click.self="closeCheckout">
      <div class="bg-white rounded-2xl w-full max-w-md p-5">
        <div class="flex items-center justify-between mb-3">
          <h3 class="font-bold text-ink">카드 확인</h3>
          <button type="button" @click="closeCheckout" class="text-ink-muted hover:text-ink" aria-label="닫기"><AppIcon name="x" :size="18" /></button>
        </div>
        <div class="rounded-xl bg-amber-50 p-3 mb-4 text-sm">
          <div class="flex justify-between"><span class="text-ink-muted">NEW 전면광고 {{ checkout.hours }}시간</span><b class="text-amber-600">{{ usd(checkout.total) }}</b></div>
          <p class="text-[11px] text-ink-muted mt-1.5">지금은 카드에 청구되지 않아요. 관리자가 승인하면 그때 {{ usd(checkout.total) }}가 청구되고, 반려되면 청구 없이 취소돼요.</p>
        </div>
        <StripeCardForm :client-secret="checkout.clientSecret" button-label="카드 확인하고 신청 완료" @authorized="onAuthorized" />
        <p class="text-[11px] text-ink-faint mt-2 text-center">선택한 시간은 30분 동안만 잡아둬요. 창을 닫으면 풀립니다.</p>
      </div>
    </div>
  </Teleport>
</div>
</template>

<script setup>
import { useModal } from '../../composables/useModal'
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import axios from 'axios'
import { useAuthStore } from '../../stores/auth'
import { useSiteStore } from '../../stores/site'
import AppIcon from '../../components/AppIcon.vue'
import PageHeader from '../../components/PageHeader.vue'
import VerifyGate from '../../components/VerifyGate.vue'
import StripeCardForm from '../../components/StripeCardForm.vue'
import { KINDS, US_STATES, fmtHour, hourRanges, fmtDay, tzLabel, stateName, STATUS_LABEL, usd, adMoney } from '../../utils/flyer'
const { showConfirm } = useModal()

defineProps({ embedded: { type: Boolean, default: false } })

const auth = useAuthStore()
const site = useSiteStore()

const form = reactive({
  title: '', kind: 'open', description: '', phone: '', link_url: '',
  scope: 'state', state: 'GA', start_date: '', days: 7, hours: [],
})
const file = ref(null)
const preview = ref('')
const fileInput = ref(null)
const av = ref(null)           // availability 응답
const mine = ref([])
const submitting = ref(false)
const error = ref('')

const fmt = (n) => Number(n || 0).toLocaleString()
const range = (a, b) => Array.from({ length: b - a + 1 }, (_, i) => a + i)

// 전국 / 주 기본 가격 안내용 (피크/심야 배율 적용 전 낮 시간대 가격)
const basePrices = ref({ national: 60, state: 30 })   // 센트
const basePrice = (s) => basePrices.value[s]

const dayChoices = computed(() => {
  const max = av.value?.max_days || 30
  return [1, 3, 7, 14, 30].filter(d => d <= max)
})

const price = (h) => Number(av.value?.prices?.[h] ?? 0)
const slotCount = computed(() => form.hours.length * form.days)
const total = computed(() => form.hours.reduce((s, h) => s + price(h), 0) * form.days)
const belowMin = computed(() => !!av.value && total.value > 0 && total.value < (av.value.min_order_cents || 0))
// 신청 버튼이 눌리지 않는 이유 — 비어 있는 필수 항목을 버튼 위에 알려준다
const missing = computed(() => {
  const m = []
  if (!form.title.trim()) m.push('① 상호/제목을 입력해주세요')
  if (!file.value) m.push('① 전단 이미지를 올려주세요')
  if (!form.hours.length) m.push('③ 방송할 시간 칸을 하나 이상 골라주세요')
  if (!form.start_date) m.push('③ 시작일을 골라주세요')
  if (belowMin.value) m.push(`최소 결제 금액은 ${usd(av.value.min_order_cents)} 예요`)
  return m
})
const canSubmit = computed(() => missing.value.length === 0)

// 시간 칸(블록): 서버가 내려주는 몇 시간씩 묶은 칸. 선택은 시간(hours) 단위로 저장해 서버에 그대로 보낸다.
const blocks = computed(() => av.value?.blocks || [])
const peak = computed(() => av.value?.peak || [11, 18])
const peakLabel = computed(() => `${fmtHour(peak.value[0])}~${fmtHour(peak.value[1])}`)
const blockLabel = (b) => `${fmtHour(b.start)}~${fmtHour(b.end)}`
const isPeak = (b) => b.start >= peak.value[0] && b.end <= peak.value[1]
const isNight = (b) => b.end <= (av.value?.night_end ?? 6)
const blockTag = (b) => isPeak(b) ? '피크' : isNight(b) ? '새벽' : '일반'
const blockOn = (b) => b.hours.every(h => form.hours.includes(h))
function blockClass(b) {
  if (b.booked) return 'bg-gray-100 border-gray-100 text-gray-400'
  if (blockOn(b)) return 'bg-amber-500 border-amber-500 text-white'
  return isPeak(b) ? 'bg-amber-50 border-amber-200 text-amber-800 hover:border-amber-400' : 'bg-white border-gray-200 text-ink-light hover:border-amber-300'
}
function toggleBlock(b) {
  if (b.booked) return
  if (blockOn(b)) form.hours = form.hours.filter(h => !b.hours.includes(h))
  else form.hours = [...new Set([...form.hours, ...b.hours])].sort((x, y) => x - y)
}
function setBlocks(list) {
  form.hours = [...new Set(list.filter(b => !b.booked).flatMap(b => b.hours))].sort((x, y) => x - y)
}
const pickPeak = () => setBlocks(blocks.value.filter(isPeak))
const pickAll = () => setBlocks(blocks.value)

function onFile(e) {
  const f = e.target.files?.[0]
  file.value = f || null
  preview.value = f ? URL.createObjectURL(f) : ''
}

let seq = 0
async function loadAvailability() {
  const my = ++seq
  try {
    const { data } = await axios.get('/api/flyers/availability', {
      params: { scope: form.scope, state: form.scope === 'state' ? form.state : undefined, start_date: form.start_date || undefined, days: form.days },
    })
    if (my !== seq) return
    av.value = data.data
    if (!form.start_date || form.start_date < av.value.min_date) form.start_date = av.value.min_date
    form.hours = form.hours.filter(h => !(av.value.blocks || []).some(b => b.booked && b.hours.includes(h)))   // 예약된 칸은 선택에서 제외
  } catch {}
}
watch(() => [form.scope, form.state, form.start_date, form.days], loadAvailability)

async function loadMine() {
  try { mine.value = (await axios.get('/api/flyers/my')).data.data || [] } catch {}
}

// 카드 확인 단계 — 신청을 먼저 접수(시간 슬롯을 잠깐 잡아둠)한 뒤 카드를 확인한다
const checkout = ref(null)   // { id, clientSecret, total, hours }

async function submit() {
  error.value = ''
  submitting.value = true
  try {
    const fd = new FormData()
    ;['title', 'kind', 'description', 'phone', 'link_url', 'scope', 'start_date'].forEach(k => form[k] !== '' && fd.append(k, form[k]))
    if (form.scope === 'state') fd.append('state', form.state)
    fd.append('days', form.days)
    form.hours.forEach(h => fd.append('hours[]', h))
    fd.append('image', file.value)
    const { data } = await axios.post('/api/flyers', fd)
    checkout.value = { id: data.data.flyer_id, clientSecret: data.data.client_secret, total: data.data.total_cents, hours: data.data.hours_count }
  } catch (e) {
    const r = e.response
    error.value = r?.data?.message || Object.values(r?.data?.errors || {})[0]?.[0] || '신청에 실패했어요. 잠시 후 다시 시도해주세요.'
    if (r?.status === 409) loadAvailability()   // 그 사이 다른 광고주가 가져간 시간 갱신
  }
  submitting.value = false
}

async function onAuthorized() {
  const c = checkout.value
  try {
    const { data } = await axios.post(`/api/flyers/${c.id}/confirm-payment`)
    site.toast(data.message || '신청이 접수됐어요', 'success', 6000)
    checkout.value = null
    Object.assign(form, { title: '', description: '', phone: '', link_url: '', hours: [] })
    file.value = null; preview.value = ''
    if (fileInput.value) fileInput.value.value = ''
    await Promise.all([loadAvailability(), loadMine()])
  } catch (e) {
    error.value = e.response?.data?.message || '결제 확인에 실패했어요. 잠시 후 다시 시도해주세요.'
    checkout.value = null
  }
}

// 카드 확인 창을 닫으면 잡아둔 시간을 풀어준다
async function closeCheckout() {
  const c = checkout.value
  checkout.value = null
  if (!c) return
  try { await axios.post(`/api/flyers/${c.id}/cancel`) } catch {}
  loadAvailability()
}

async function cancel(m) {
  const card = m.payment_method === 'card'
  if (!await showConfirm(card ? `'${m.title}' 신청을 취소할까요? (카드에는 청구되지 않았어요)` : `'${m.title}' 신청을 취소하고 ${adMoney(m, m.total_price)}를 환불받을까요?`)) return
  try {
    const { data } = await axios.post(`/api/flyers/${m.id}/cancel`)
    site.toast(data.message, 'success')
    await Promise.all([auth.fetchUser(), loadAvailability(), loadMine()])
  } catch (e) { site.toast(e.response?.data?.message || '취소 실패', 'error') }
}

onMounted(async () => {
  const myState = (auth.user?.state || '').toUpperCase()
  if (US_STATES.some(s => s.code === myState)) form.state = myState
  await loadAvailability()
  // 안내용 기본 가격 (낮 12시 = 배율 100%)
  try {
    const [n, s] = await Promise.all([
      axios.get('/api/flyers/availability', { params: { scope: 'national', days: 1 } }),
      axios.get('/api/flyers/availability', { params: { scope: 'state', state: form.state, days: 1 } }),
    ])
    basePrices.value = { national: n.data.data.prices[12], state: s.data.data.prices[12] }
  } catch {}
  loadMine()
})
</script>

<style scoped>
.chip { padding: 3px 8px; border-radius: 9999px; border: 1px solid #e5e7eb; background: #fff; color: #6b7280; font-weight: 700; }
.chip:hover { border-color: #fb7185; color: #e11d48; }
</style>
