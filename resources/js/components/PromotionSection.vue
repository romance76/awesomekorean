<template>
<section v-if="!isEdit" class="card overflow-hidden">
  <div class="px-5 py-3 border-b border-violet-100 bg-violet-50 flex items-center gap-2">
    <h2 class="text-sm font-bold text-violet-700 flex items-center gap-2">
      <span class="icon-chip w-7 h-7 bg-white text-violet-600"><AppIcon name="sparkles" :size="14" /></span>상위노출 (선택)
    </h2>
    <span class="text-[11px] text-violet-600">위치 입력 후 자동으로 슬롯 확인됩니다</span>
  </div>
  <div class="p-5 space-y-4">
    <p class="text-xs text-ink-muted">
      카테고리당 <b>주(State)</b> / <b>전국(National)</b> 최대 {{ maxSlots.state_plus }}개 한정.
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <!-- 사용 안함 -->
      <button type="button" @click="selectTier('none')"
        class="p-3 rounded-xl border-2 text-left transition"
        :class="tier === 'none' ? 'border-gray-400 bg-gray-50' : 'border-gray-200 bg-white hover:border-gray-300'">
        <div class="font-bold text-sm text-ink">사용 안 함</div>
        <div class="text-xs text-ink-muted">일반 등록</div>
      </button>

      <!-- 스폰서 (슬롯 제한 없음) -->
      <button type="button" @click="selectTier('sponsored')"
        class="p-3 rounded-xl border-2 text-left transition"
        :class="tier === 'sponsored' ? 'border-violet-400 bg-violet-50' : 'border-gray-200 bg-white hover:border-gray-300'">
        <div class="font-bold text-sm text-ink">스폰서 (Sponsored)</div>
        <div class="text-xs text-violet-600 font-semibold">하루 {{ prices.sponsored }}P</div>
        <div class="text-[11px] text-ink-muted mt-1">색상 강조만 (위치 부스트 없음)</div>
      </button>

      <!-- 주(State) 상위노출 -->
      <button type="button" @click="selectTier('state_plus')"
        :disabled="statePlusUnselectable"
        class="p-3 rounded-xl border-2 text-left transition relative"
        :class="[
          statePlusFull ? 'border-red-300 bg-red-50 cursor-not-allowed opacity-75' :
          tier === 'state_plus' ? 'border-violet-400 bg-violet-50' :
          'border-gray-200 bg-white hover:border-gray-300',
        ]">
        <div class="font-bold text-sm" :class="statePlusFull ? 'text-red-800' : 'text-ink'">
          주(State) 상위노출
        </div>
        <div class="text-xs font-semibold" :class="statePlusFull ? 'text-red-600' : 'text-violet-600'">
          하루 {{ prices.state_plus }}P
        </div>

        <!-- 슬롯 상태 메시지 (카드 안) -->
        <div v-if="!state" class="text-[11px] text-amber-700 mt-1.5 bg-amber-50 rounded-lg p-1.5 flex items-center gap-1">
          <AppIcon name="alert-circle" :size="12" /> State 입력 후 슬롯 확인
        </div>
        <div v-else-if="statePlusFull" class="text-[11px] text-red-700 mt-1.5 bg-red-100 rounded-lg p-1.5 leading-snug">
          <div class="font-bold flex items-center gap-1"><AppIcon name="alert-circle" :size="12" /> 슬롯 만석 — 현재 선택 불가</div>
          <div class="mt-0.5">카테고리당 최대 {{ slotInfoByTier.state_plus?.max_slots ?? maxSlots.state_plus }}개까지.</div>
          <div v-if="nextStatePlusFmt" class="font-bold mt-0.5 flex items-center gap-1"><AppIcon name="calendar" :size="12" /> {{ nextStatePlusFmt }} 이후 가능</div>
        </div>
        <div v-else-if="slotInfoByTier.state_plus" class="text-[11px] text-emerald-700 mt-1.5 bg-emerald-50 rounded-lg p-1.5 flex items-center gap-1">
          <AppIcon name="check" :size="12" /> 사용 가능 ({{ slotInfoByTier.state_plus.used }}/{{ slotInfoByTier.state_plus.max_slots }})
        </div>
        <div v-else class="text-[11px] text-ink-muted mt-1.5">내 주 + 인접 주 자동 포함</div>
      </button>

      <!-- 전국구 (National) -->
      <button type="button" @click="selectTier('national')"
        :disabled="nationalUnselectable"
        class="p-3 rounded-xl border-2 text-left transition relative"
        :class="[
          nationalFull ? 'border-red-300 bg-red-50 cursor-not-allowed opacity-75' :
          tier === 'national' ? 'border-violet-400 bg-violet-50' :
          'border-gray-200 bg-white hover:border-gray-300',
        ]">
        <div class="font-bold text-sm" :class="nationalFull ? 'text-red-800' : 'text-ink'">
          전국구 (National)
        </div>
        <div class="text-xs font-semibold" :class="nationalFull ? 'text-red-600' : 'text-violet-600'">
          하루 {{ prices.national }}P
        </div>

        <div v-if="!category" class="text-[11px] text-amber-700 mt-1.5 bg-amber-50 rounded-lg p-1.5 flex items-center gap-1">
          <AppIcon name="alert-circle" :size="12" /> {{ categoryLabel }} 선택 후 슬롯 확인
        </div>
        <div v-else-if="nationalFull" class="text-[11px] text-red-700 mt-1.5 bg-red-100 rounded-lg p-1.5 leading-snug">
          <div class="font-bold flex items-center gap-1"><AppIcon name="alert-circle" :size="12" /> 슬롯 만석 — 현재 선택 불가</div>
          <div class="mt-0.5">카테고리당 최대 {{ slotInfoByTier.national?.max_slots ?? maxSlots.national }}개까지.</div>
          <div v-if="nextNationalFmt" class="font-bold mt-0.5 flex items-center gap-1"><AppIcon name="calendar" :size="12" /> {{ nextNationalFmt }} 이후 가능</div>
        </div>
        <div v-else-if="slotInfoByTier.national" class="text-[11px] text-emerald-700 mt-1.5 bg-emerald-50 rounded-lg p-1.5 flex items-center gap-1">
          <AppIcon name="check" :size="12" /> 사용 가능 ({{ slotInfoByTier.national.used }}/{{ slotInfoByTier.national.max_slots }})
        </div>
        <div v-else class="text-[11px] text-ink-muted mt-1.5">전 지역 노출</div>
      </button>
    </div>

    <!-- 선택된 티어 추가 설정 -->
    <div v-if="tier !== 'none'" class="space-y-3 pt-3 border-t border-gray-100">
      <div v-if="tier === 'state_plus' && state && neighbors.length" class="bg-blue-50 rounded-xl p-3">
        <div class="text-xs font-bold text-blue-700 mb-1.5 flex items-center gap-1"><AppIcon name="map-pin" :size="12" /> 자동 노출 주</div>
        <div class="flex flex-wrap gap-1">
          <span v-for="s in neighbors" :key="s"
            class="text-[11px] font-bold px-2 py-0.5 rounded-full"
            :class="s === state.toUpperCase() ? 'bg-blue-500 text-white' : 'bg-white text-blue-700'">
            {{ s }}{{ s === state.toUpperCase() ? ' (내 주)' : '' }}
          </span>
        </div>
      </div>

      <div>
        <label class="input-label">노출 기간 (일)</label>
        <input v-model.number="daysLocal" type="number" min="1" max="30"
          class="input-soft w-32" />
      </div>

      <div class="bg-violet-50 rounded-xl p-3 flex items-center justify-between">
        <span class="text-sm font-semibold text-violet-700">총 비용</span>
        <span class="text-lg font-black text-violet-700">{{ totalCost.toLocaleString() }} P</span>
      </div>
    </div>
  </div>
</section>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import axios from 'axios'
import { neighborsOf } from '../utils/stateNeighbors'
import AppIcon from './AppIcon.vue'

const props = defineProps({
  resource: { type: String, required: true },
  category: { type: String, default: '' },
  state: { type: String, default: '' },
  isEdit: { type: Boolean, default: false },
  modelValue: { type: Object, default: () => ({ tier: 'none', days: 7 }) },
  categoryLabel: { type: String, default: '카테고리' },
})
const emit = defineEmits(['update:modelValue', 'slot-info'])

// 부모에서 reactive({}) 로 내려주는 경우 v-model 재할당이 const 에러.
// 따라서 props.modelValue 객체를 직접 mutate (Vue 프록시 반응성으로 부모도 업데이트됨).
// emit 도 병행 (ref 로 내려주는 경우 대응).
const tier = computed({
  get: () => props.modelValue?.tier ?? 'none',
  set: (v) => {
    if (props.modelValue && typeof props.modelValue === 'object') {
      props.modelValue.tier = v
    }
    // ref 로 넘겨준 경우를 위한 emit (reactive 의 경우는 위 mutate 로 이미 반영됨)
    // Vue 가 v-model 업데이트 핸들러를 const 에 assign 시도해서 throw 해도 이미 mutate 는 성공함
    try { emit('update:modelValue', { ...(props.modelValue || {}), tier: v }) } catch {}
  },
})
const daysLocal = computed({
  get: () => props.modelValue?.days ?? 7,
  set: (v) => {
    if (props.modelValue && typeof props.modelValue === 'object') {
      props.modelValue.days = v
    }
    try { emit('update:modelValue', { ...(props.modelValue || {}), days: v }) } catch {}
  },
})

const prices = reactive({ national: 100, state_plus: 50, sponsored: 20 })
const maxSlots = reactive({ national: 5, state_plus: 5 })

// 각 티어별 슬롯 정보 (state_plus, national 동시 보유)
const slotInfoByTier = reactive({ state_plus: null, national: null })

const neighbors = computed(() => (props.state ? neighborsOf(props.state) : []))

const totalCost = computed(() => {
  const unit = prices[tier.value] || 0
  const d = Math.max(1, Math.min(30, Number(daysLocal.value) || 0))
  return unit * d
})

// 만석 여부 (category + state 모두 있어야 판정 가능, 만석이면 true)
const statePlusFull = computed(() => slotInfoByTier.state_plus?.is_full === true)
const nationalFull = computed(() => slotInfoByTier.national?.is_full === true)

// 선택 불가 (만석이거나 필수값 미입력)
const statePlusUnselectable = computed(() => statePlusFull.value || !props.state || !props.category)
const nationalUnselectable = computed(() => nationalFull.value || !props.category)

function formatDt(iso) {
  if (!iso) return null
  const d = new Date(iso)
  return `${d.getFullYear()}. ${d.getMonth()+1}. ${d.getDate()} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`
}
const nextStatePlusFmt = computed(() => formatDt(slotInfoByTier.state_plus?.next_slot_time))
const nextNationalFmt = computed(() => formatDt(slotInfoByTier.national?.next_slot_time))

// 부모에서 참조
const isSlotFull = computed(() => {
  if (tier.value === 'state_plus') return statePlusFull.value
  if (tier.value === 'national') return nationalFull.value
  return false
})
const nextSlotTimeFmt = computed(() => {
  if (tier.value === 'state_plus') return nextStatePlusFmt.value
  if (tier.value === 'national') return nextNationalFmt.value
  return null
})

async function loadSettings() {
  try {
    const { data } = await axios.get('/api/promotion/settings', { params: { resource: props.resource } })
    const s = data?.data
    if (s?.price_per_day) {
      prices.national = s.price_per_day.national ?? 100
      prices.state_plus = s.price_per_day.state_plus ?? 50
      prices.sponsored = s.price_per_day.sponsored ?? 20
    }
    if (s?.max_slots) {
      maxSlots.national = s.max_slots.national ?? 5
      maxSlots.state_plus = s.max_slots.state_plus ?? 5
    }
  } catch {}
}

// 2개 티어 슬롯 병렬 조회 — 속도 개선
let slotReqSeq = 0
async function refreshSlotInfo() {
  const endpoint = {
    jobs: '/api/jobs/promotion-slots',
    market: '/api/market/promotion-slots',
    realestate: '/api/realestate/promotion-slots',
    business: '/api/businesses/promotion-slots',
  }[props.resource] || '/api/jobs/promotion-slots'

  // 경쟁 조건 방지: 가장 마지막 요청만 결과 반영
  const mySeq = ++slotReqSeq

  const nationalPromise = props.category
    ? axios.get(endpoint, { params: { tier: 'national', category: props.category } }).then(r => r?.data?.data).catch(() => null)
    : Promise.resolve(null)

  const statePlusPromise = (props.category && props.state)
    ? axios.get(endpoint, { params: { tier: 'state_plus', category: props.category, state: props.state.toUpperCase() } }).then(r => r?.data?.data).catch(() => null)
    : Promise.resolve(null)

  const [national, statePlus] = await Promise.all([nationalPromise, statePlusPromise])

  // 늦게 도착한 예전 응답은 무시
  if (mySeq !== slotReqSeq) return

  slotInfoByTier.national = national
  slotInfoByTier.state_plus = statePlus
  emit('slot-info', { state_plus: statePlus, national })
}

function selectTier(t) {
  // 만석이면 선택 무시
  if (t === 'state_plus' && statePlusUnselectable.value) return
  if (t === 'national' && nationalUnselectable.value) return
  tier.value = t
}

// 만석 티어가 현재 선택이면 자동 none 으로 되돌림 (카테고리/주 바꿨을 때)
watch([statePlusFull, nationalFull], () => {
  if (tier.value === 'state_plus' && statePlusFull.value) tier.value = 'none'
  if (tier.value === 'national' && nationalFull.value) tier.value = 'none'
})

watch(() => [props.category, props.state], refreshSlotInfo, { immediate: false })

onMounted(async () => {
  await loadSettings()
  await refreshSlotInfo()
})

defineExpose({ isSlotFull, slotInfoByTier, nextSlotTimeFmt })
</script>
