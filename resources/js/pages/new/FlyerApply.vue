<template>
<div :class="embedded ? '' : 'min-h-screen'">
  <div :class="embedded ? '' : 'max-w-2xl mx-auto px-4 py-5'">
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-1">
      <span class="icon-chip w-9 h-9 bg-rose-50 text-rose-600"><AppIcon name="megaphone" :size="20" /></span>
      NEW 전면광고 신청
      <RouterLink to="/new" class="ml-auto text-xs font-semibold text-ink-muted hover:text-rose-600 transition-colors">NEW 게시판 보기 →</RouterLink>
    </h1>
    <p class="text-sm text-ink-muted mb-5">라디오 광고처럼 <b>하루 중 원하는 시간대</b>를 골라 사세요. 고른 시간에 NEW 게시판 맨 위에 전단이 통째로 나가고, 남은 시간은 다른 광고주가 쓸 수 있어요.</p>

    <VerifyGate message="이메일 인증 후 전면광고를 신청할 수 있어요.">
    <form @submit.prevent="submit" class="space-y-4">
      <!-- 1. 전단 내용 -->
      <div class="card p-5 space-y-4">
        <h2 class="font-bold text-ink text-sm flex items-center gap-1.5"><span class="w-5 h-5 rounded-full bg-rose-500 text-white text-xs flex items-center justify-center">1</span>전단 내용</h2>

        <div>
          <label class="input-label">상호 / 제목 *</label>
          <input v-model="form.title" maxlength="80" required class="input-soft" placeholder="예: 서울식당 수와니점 GRAND OPEN" />
        </div>
        <div>
          <label class="input-label">종류 *</label>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            <button v-for="k in KINDS" :key="k.value" type="button" @click="form.kind = k.value"
              class="border-2 rounded-lg px-2 py-2 text-xs font-bold transition-colors"
              :class="form.kind === k.value ? 'bg-rose-50 border-rose-400 text-rose-700' : 'bg-white border-gray-200 text-ink-light hover:border-rose-200'">{{ k.label }}</button>
          </div>
        </div>
        <div>
          <label class="input-label">전단 이미지 * <span class="text-ink-faint font-normal">(JPG·PNG·WEBP, 6MB 이하 — 세로로 긴 전단 한 장이 가장 잘 보여요)</span></label>
          <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" @change="onFile" class="block w-full text-sm text-ink-light file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-rose-50 file:text-rose-700 file:font-bold hover:file:bg-rose-100" />
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
        <h2 class="font-bold text-ink text-sm flex items-center gap-1.5"><span class="w-5 h-5 rounded-full bg-rose-500 text-white text-xs flex items-center justify-center">2</span>노출 지역</h2>
        <div class="grid grid-cols-2 gap-2">
          <button type="button" @click="form.scope = 'state'" class="border-2 rounded-xl p-3 text-left transition-colors"
            :class="form.scope === 'state' ? 'bg-rose-50 border-rose-400' : 'bg-white border-gray-200 hover:border-rose-200'">
            <div class="font-bold text-sm text-ink">📍 내 지역 (주 단위)</div>
            <div class="text-[11px] text-ink-muted mt-0.5">그 주 방문자에게만 · 가격 {{ fmt(basePrice('state')) }}P/시간~</div>
          </button>
          <button type="button" @click="form.scope = 'national'" class="border-2 rounded-xl p-3 text-left transition-colors"
            :class="form.scope === 'national' ? 'bg-rose-50 border-rose-400' : 'bg-white border-gray-200 hover:border-rose-200'">
            <div class="font-bold text-sm text-ink">🇺🇸 전국</div>
            <div class="text-[11px] text-ink-muted mt-0.5">전국 어디서 봐도 · 가격 {{ fmt(basePrice('national')) }}P/시간~</div>
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
        <h2 class="font-bold text-ink text-sm flex items-center gap-1.5"><span class="w-5 h-5 rounded-full bg-rose-500 text-white text-xs flex items-center justify-center">3</span>언제, 며칠 동안?</h2>

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
                :class="form.days === d ? 'bg-rose-50 border-rose-400 text-rose-700' : 'bg-white border-gray-200 text-ink-light hover:border-rose-200'">{{ d }}일</button>
            </div>
          </div>
        </div>

        <div>
          <div class="flex items-center justify-between flex-wrap gap-2 mb-2">
            <label class="input-label !mb-0">방송할 시간대 <span class="text-ink-faint font-normal">(매일 같은 시간 · {{ av ? tzLabel(av.tz) : '' }} 현지 시각)</span></label>
            <div class="flex gap-1 text-[11px]">
              <button type="button" @click="pick(range(17, 22))" class="chip">피크 오후5~11시</button>
              <button type="button" @click="pick(range(9, 16))" class="chip">낮 9시~5시</button>
              <button type="button" @click="pick(range(0, 23))" class="chip">24시간</button>
              <button type="button" @click="form.hours = []" class="chip">초기화</button>
            </div>
          </div>
          <div class="grid grid-cols-4 sm:grid-cols-6 gap-1.5">
            <button v-for="h in 24" :key="h" type="button" :disabled="!!blocked(h - 1)" @click="toggle(h - 1)"
              class="rounded-lg border-2 px-1 py-1.5 text-center transition-colors disabled:cursor-not-allowed"
              :class="cellClass(h - 1)">
              <div class="text-xs font-bold leading-tight">{{ fmtHour(h - 1) }}</div>
              <div class="text-[10px] leading-tight mt-0.5">{{ blocked(h - 1) || (price(h - 1) + 'P') }}</div>
            </button>
          </div>
          <p class="text-[11px] text-ink-faint mt-2">피크(오후 5~11시)는 비싸고 심야(자정~오전 6시)는 저렴해요. “예약됨”은 같은 지역에서 다른 광고주가 이미 쓰는 시간이에요. 아무도 쓰지 않는 시간은 모두 고를 수 있어요.</p>
        </div>
      </div>

      <!-- 요약 / 결제 -->
      <div class="card p-5 border-2 border-rose-100">
        <div class="space-y-1.5 text-sm">
          <div class="flex justify-between"><span class="text-ink-muted">방송 시간</span><span class="font-semibold">{{ form.hours.length }}시간/일 × {{ form.days }}일 = {{ slotCount }}시간</span></div>
          <div v-if="form.hours.length" class="flex justify-between gap-4"><span class="text-ink-muted flex-shrink-0">시간대</span><span class="text-right text-xs">{{ hourRanges(form.hours).join(', ') }}</span></div>
          <div class="flex justify-between items-baseline pt-2 border-t border-gray-100"><span class="font-bold text-ink">총 비용</span><span class="text-xl font-black text-rose-600">{{ fmt(total) }}P <span class="text-xs font-semibold text-ink-faint">≈ ${{ (total / 100).toFixed(0) }}</span></span></div>
          <div class="flex justify-between text-xs"><span class="text-ink-muted">내 포인트</span>
            <span :class="lacking ? 'text-red-500 font-bold' : 'text-ink-light'">{{ fmt(auth.user?.points || 0) }}P
              <RouterLink v-if="lacking" to="/dashboard?tab=points" class="underline ml-1">포인트 충전</RouterLink></span></div>
        </div>
        <p v-if="error" class="text-sm text-red-500 mt-3">{{ error }}</p>
        <button type="submit" :disabled="submitting || !canSubmit" class="btn-primary w-full py-3 rounded-xl text-sm mt-4 disabled:opacity-50">
          {{ submitting ? '신청 중...' : `${fmt(total)}P로 신청하기` }}
        </button>
        <p class="text-[11px] text-ink-faint mt-2">신청하면 포인트가 먼저 차감되고, 관리자 승인 후 예약한 시간에 방송돼요. 반려되거나 승인 전에 취소하면 전액 환불됩니다.</p>
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
          <div class="text-[11px] text-ink-muted mt-0.5">{{ m.scope === 'national' ? '전국' : stateName(m.region_key) }} · {{ fmtDay(m.start_date) }}~{{ fmtDay(m.end_date) }} · {{ m.hours_count }}시간 · {{ fmt(m.total_price) }}P</div>
          <div v-if="m.status === 'approved'" class="text-[11px] text-ink-faint">노출 {{ fmt(m.view_count) }} · 클릭 {{ fmt(m.click_count) }}</div>
          <div v-if="m.reject_reason && m.status !== 'approved'" class="text-[11px] text-red-500">{{ m.reject_reason }}</div>
        </div>
        <div class="flex flex-col gap-1 flex-shrink-0">
          <RouterLink :to="`/new/${m.id}`" class="text-xs text-ink-muted hover:text-rose-600 text-center">보기</RouterLink>
          <button v-if="m.status === 'pending'" @click="cancel(m)" class="text-xs text-red-400 hover:text-red-600">취소·환불</button>
        </div>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import axios from 'axios'
import { useAuthStore } from '../../stores/auth'
import { useSiteStore } from '../../stores/site'
import AppIcon from '../../components/AppIcon.vue'
import VerifyGate from '../../components/VerifyGate.vue'
import { KINDS, US_STATES, fmtHour, hourRanges, fmtDay, tzLabel, stateName, STATUS_LABEL } from '../../utils/flyer'

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
const basePrices = ref({ national: 60, state: 30 })
const basePrice = (s) => basePrices.value[s]

const dayChoices = computed(() => {
  const max = av.value?.max_days || 30
  return [1, 3, 7, 14, 30].filter(d => d <= max)
})

const price = (h) => Number(av.value?.prices?.[h] ?? 0)
const slotCount = computed(() => form.hours.length * form.days)
const total = computed(() => form.hours.reduce((s, h) => s + price(h), 0) * form.days)
const lacking = computed(() => total.value > (auth.user?.points || 0))
const canSubmit = computed(() => form.title && file.value && form.hours.length && form.start_date && !lacking.value)

// 이 시간을 고를 수 없는 이유 (다른 광고주가 이미 예약) — 없으면 빈 문자열
function blocked(h) {
  if (!av.value) return ''
  return av.value.booked?.[h]?.length ? '예약됨' : ''
}
function cellClass(h) {
  if (blocked(h)) return 'bg-gray-100 border-gray-100 text-gray-400'
  if (form.hours.includes(h)) return 'bg-rose-500 border-rose-500 text-white'
  const peak = h >= 17 && h <= 22
  return peak ? 'bg-amber-50 border-amber-200 text-amber-800 hover:border-amber-400' : 'bg-white border-gray-200 text-ink-light hover:border-rose-300'
}
function toggle(h) {
  const i = form.hours.indexOf(h)
  if (i >= 0) form.hours.splice(i, 1)
  else { form.hours.push(h); form.hours.sort((a, b) => a - b) }
}
function pick(hs) {
  form.hours = hs.filter(h => !blocked(h))
}

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
    form.hours = form.hours.filter(h => !blocked(h))   // 막힌 시간은 선택에서 제외
  } catch {}
}
watch(() => [form.scope, form.state, form.start_date, form.days], loadAvailability)

async function loadMine() {
  try { mine.value = (await axios.get('/api/flyers/my')).data.data || [] } catch {}
}

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
    site.toast(data.message || '신청이 접수됐어요', 'success', 5000)
    Object.assign(form, { title: '', description: '', phone: '', link_url: '', hours: [] })
    file.value = null; preview.value = ''
    if (fileInput.value) fileInput.value.value = ''
    await Promise.all([auth.fetchUser(), loadAvailability(), loadMine()])
  } catch (e) {
    const r = e.response
    error.value = r?.data?.message || Object.values(r?.data?.errors || {})[0]?.[0] || '신청에 실패했어요. 잠시 후 다시 시도해주세요.'
    if (r?.status === 409) loadAvailability()   // 그 사이 다른 광고주가 가져간 시간 갱신
  }
  submitting.value = false
}

async function cancel(m) {
  if (!confirm(`'${m.title}' 신청을 취소하고 ${fmt(m.total_price)}P를 환불받을까요?`)) return
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
