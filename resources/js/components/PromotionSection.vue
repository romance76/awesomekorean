<template>
<section v-if="!isEdit" class="bg-white rounded-xl shadow-sm border-2 border-purple-300 overflow-hidden">
  <div class="px-5 py-3 border-b border-purple-100 bg-purple-50 flex items-center gap-2">
    <h2 class="text-sm font-black text-purple-800">🚀 상위노출 (선택)</h2>
    <span class="text-[10px] text-purple-600">등록 전 슬롯 여부 먼저 확인</span>
  </div>
  <div class="p-5 space-y-4">
    <p class="text-xs text-gray-500">
      카테고리당 <b>주(State)</b> / <b>전국(National)</b> 최대 5개 한정.
      슬롯이 차면 등록 차단됩니다.
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <button type="button" @click="selectTier('none')"
        class="p-3 rounded-lg border-2 text-left transition"
        :class="tier === 'none' ? 'border-gray-400 bg-gray-50' : 'border-gray-200 bg-white hover:border-gray-300'">
        <div class="font-bold text-sm text-gray-800">사용 안 함</div>
        <div class="text-xs text-gray-500">일반 등록</div>
      </button>
      <button type="button" @click="selectTier('sponsored')"
        class="p-3 rounded-lg border-2 text-left transition"
        :class="tier === 'sponsored' ? 'border-purple-400 bg-purple-50' : 'border-gray-200 bg-white hover:border-gray-300'">
        <div class="font-bold text-sm text-gray-800">스폰서 (Sponsored)</div>
        <div class="text-xs text-purple-600 font-semibold">하루 {{ prices.sponsored }}P</div>
        <div class="text-[10px] text-gray-500 mt-1">색상 강조만 (위치 부스트 없음)</div>
      </button>
      <button type="button" @click="selectTier('state_plus')"
        class="p-3 rounded-lg border-2 text-left transition"
        :class="tier === 'state_plus' ? 'border-purple-400 bg-purple-50' : 'border-gray-200 bg-white hover:border-gray-300'">
        <div class="font-bold text-sm text-gray-800">주(State) 상위노출</div>
        <div class="text-xs text-purple-600 font-semibold">하루 {{ prices.state_plus }}P</div>
        <div class="text-[10px] text-gray-500 mt-1">내 주 + 인접 주 자동 · 카테고리당 최대 {{ maxSlots.state_plus }}개</div>
      </button>
      <button type="button" @click="selectTier('national')"
        class="p-3 rounded-lg border-2 text-left transition"
        :class="tier === 'national' ? 'border-purple-400 bg-purple-50' : 'border-gray-200 bg-white hover:border-gray-300'">
        <div class="font-bold text-sm text-gray-800">전국(National) 상위노출</div>
        <div class="text-xs text-purple-600 font-semibold">하루 {{ prices.national }}P</div>
        <div class="text-[10px] text-gray-500 mt-1">전 지역 · 카테고리당 최대 {{ maxSlots.national }}개</div>
      </button>
    </div>

    <div v-if="tier !== 'none'" class="space-y-3 pt-3 border-t border-gray-100">
      <!-- state_plus: 자동 주 미리보기 -->
      <div v-if="tier === 'state_plus'" class="bg-blue-50 border border-blue-200 rounded-lg p-3">
        <div class="text-xs font-bold text-blue-800 mb-1.5">📍 자동 노출 주 (State)</div>
        <div v-if="!state" class="text-xs text-red-600">
          ⚠️ <b>State</b> 를 아래 폼에 먼저 입력해주세요.
        </div>
        <div v-else-if="neighbors.length">
          <div class="text-xs text-blue-700 mb-2">
            주 (<b>{{ state.toUpperCase() }}</b>) + 인접 주에 자동 상위노출:
          </div>
          <div class="flex flex-wrap gap-1">
            <span v-for="s in neighbors" :key="s"
              class="text-[11px] font-bold px-2 py-0.5 rounded"
              :class="s === state.toUpperCase() ? 'bg-blue-500 text-white' : 'bg-white border border-blue-300 text-blue-700'">
              {{ s }}{{ s === state.toUpperCase() ? ' (내 주)' : '' }}
            </span>
          </div>
        </div>
      </div>

      <div>
        <label class="text-sm font-semibold text-gray-700 block mb-1">노출 기간 (일)</label>
        <input v-model.number="daysLocal" type="number" min="1" max="30"
          class="w-32 border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-purple-400" />
      </div>

      <div class="bg-purple-50 rounded-lg p-3 flex items-center justify-between">
        <span class="text-sm text-purple-800">총 비용</span>
        <span class="text-lg font-black text-purple-800">{{ totalCost.toLocaleString() }} P</span>
      </div>

      <!-- 슬롯 만석 경고 -->
      <div v-if="isSlotFull" class="bg-red-50 border-2 border-red-300 rounded-lg p-3 text-xs text-red-700">
        <div class="font-bold mb-1 text-red-800">⚠️ 슬롯 만석 — 현재 선택 불가</div>
        <div class="mb-1">
          {{ tier === 'national' ? '전국' : '주(State)' }} 상위노출은
          카테고리당 최대 {{ slotInfo?.max_slots ?? 5 }}개까지.
        </div>
        <div v-if="nextSlotTimeFmt" class="font-bold text-red-700">
          📅 {{ nextSlotTimeFmt }} 이후 가능합니다.
        </div>
      </div>
      <div v-else-if="slotInfo && !isSlotFull" class="text-xs text-green-700 bg-green-50 rounded-lg p-2">
        ✅ 슬롯 사용 가능 ({{ slotInfo.used }}/{{ slotInfo.max_slots }} 사용 중, {{ slotInfo.available }}개 남음)
      </div>
      <div v-else-if="!category || (tier === 'state_plus' && !state)"
        class="text-xs text-amber-700 bg-amber-50 rounded-lg p-2">
        ⚠️ 슬롯 확인을 위해
        <b v-if="!category">{{ categoryLabel }}</b><span v-if="!category && tier === 'state_plus' && !state">와 </span><b v-if="tier === 'state_plus' && !state">State</b>를 먼저 입력해주세요.
      </div>
    </div>
  </div>
</section>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import axios from 'axios'
import { neighborsOf } from '../utils/stateNeighbors'

// resource: 'jobs'|'market'|'realestate'|'business'
// category: 현재 폼의 카테고리/유형 (예: market=electronics, realestate=rent, business=restaurant)
// state: 현재 폼의 근무/매물/업소 State (GA 등)
// isEdit: 수정 모드면 상위노출 UI 숨김
// modelValue: { tier, days } — v-model 로 부모와 공유
const props = defineProps({
  resource: { type: String, required: true },
  category: { type: String, default: '' },
  state: { type: String, default: '' },
  isEdit: { type: Boolean, default: false },
  modelValue: { type: Object, default: () => ({ tier: 'none', days: 7 }) },
  categoryLabel: { type: String, default: '카테고리' },
})
const emit = defineEmits(['update:modelValue', 'slot-info'])

const tier = computed({
  get: () => props.modelValue?.tier ?? 'none',
  set: (v) => emit('update:modelValue', { ...props.modelValue, tier: v }),
})
const daysLocal = computed({
  get: () => props.modelValue?.days ?? 7,
  set: (v) => emit('update:modelValue', { ...props.modelValue, days: v }),
})

const prices = reactive({ national: 100, state_plus: 50, sponsored: 20 })
const maxSlots = reactive({ national: 5, state_plus: 5 })
const slotInfo = ref(null)
const loadingSlot = ref(false)

const neighbors = computed(() => (props.state ? neighborsOf(props.state) : []))

const totalCost = computed(() => {
  const unit = prices[tier.value] || 0
  const d = Math.max(1, Math.min(30, Number(daysLocal.value) || 0))
  return unit * d
})

const isSlotFull = computed(() => slotInfo.value?.is_full === true)
const nextSlotTimeFmt = computed(() => {
  if (!slotInfo.value?.next_slot_time) return null
  const d = new Date(slotInfo.value.next_slot_time)
  return `${d.getFullYear()}. ${d.getMonth()+1}. ${d.getDate()} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`
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

async function refreshSlotInfo() {
  slotInfo.value = null
  if (['none', 'sponsored'].includes(tier.value)) return
  if (!props.category) return
  if (tier.value === 'state_plus' && !props.state) return
  loadingSlot.value = true
  try {
    const params = { tier: tier.value, category: props.category }
    if (tier.value === 'state_plus') params.state = (props.state || '').toUpperCase()
    const endpoint = {
      jobs: '/api/jobs/promotion-slots',
      market: '/api/market/promotion-slots',
      realestate: '/api/realestate/promotion-slots',
      business: '/api/businesses/promotion-slots',
    }[props.resource] || '/api/jobs/promotion-slots'
    const { data } = await axios.get(endpoint, { params })
    slotInfo.value = data?.data ?? null
    emit('slot-info', slotInfo.value)
  } catch {}
  loadingSlot.value = false
}

function selectTier(t) {
  tier.value = t
  refreshSlotInfo()
}

watch(() => [props.category, props.state, tier.value], refreshSlotInfo)

onMounted(() => {
  loadSettings()
  if (!['none', 'sponsored'].includes(tier.value)) refreshSlotInfo()
})

// 외부에서 '만석 여부' 체크용 노출
defineExpose({ isSlotFull, slotInfo, nextSlotTimeFmt })
</script>
