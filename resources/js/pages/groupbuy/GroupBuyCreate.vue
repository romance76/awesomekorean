<template>
<div class="min-h-screen">
  <div class="page-main px-4 py-5">
    <PageHeader title="공동구매 등록" icon="heart-handshake" chip="bg-lime-50 text-lime-600" fallback="/groupbuy" />

    <div class="card p-5 space-y-5">

      <!-- 1. 제목 -->
      <div>
        <label class="input-label">제목 <span class="text-red-400">*</span></label>
        <input v-model="form.title" type="text" placeholder="예: 코스트코 한우 공동구매"
          class="input-soft px-3" />
      </div>

      <!-- 2. 카테고리 -->
      <div>
        <label class="input-label">카테고리</label>
        <select v-model="form.category" class="input-soft px-3">
          <option v-for="c in categories" :key="c.value" :value="c.value">{{ c.label }}</option>
        </select>
      </div>

      <!-- 3. 상세 설명 -->
      <div>
        <label class="input-label">상세 설명 <span class="text-red-400">*</span></label>
        <textarea v-model="form.content" rows="6" placeholder="공동구매 상품 설명, 배송 방법, 픽업 장소 등을 자세히 작성해주세요"
          class="input-soft px-3"></textarea>
      </div>

      <!-- 4. 가격 -->
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="input-label">원래 가격 ($)</label>
          <input v-model.number="form.original_price" type="number" min="0" placeholder="0"
            class="input-soft px-3" />
        </div>
        <div>
          <label class="input-label">공동구매 최저가 ($)</label>
          <input v-model.number="form.group_price" type="number" min="0" placeholder="0"
            class="input-soft px-3" />
        </div>
      </div>

      <!-- 5. 상품 링크 -->
      <div>
        <label class="input-label">상품 링크</label>
        <input v-model="form.product_url" type="url" placeholder="https://..."
          class="input-soft px-3" />
      </div>

      <!-- 6. 상품 이미지 -->
      <div>
        <label class="input-label flex items-center gap-2 mb-0">
          상품 이미지
          <span class="text-xs text-ink-muted font-normal">최대 5장</span>
        </label>
        <div class="flex flex-wrap gap-2 mt-2">
          <div v-for="(photo, idx) in photoList" :key="idx"
            class="relative w-24 h-24 rounded-xl overflow-hidden border-2 border-gray-200 group">
            <img :src="photo.preview" class="w-full h-full object-cover" />
            <button @click="removePhoto(idx)"
              class="absolute bottom-1 right-1 bg-black/60 text-white w-5 h-5 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition">
              <AppIcon name="x" :size="12" />
            </button>
          </div>
          <label v-if="photoList.length < 5"
            class="w-24 h-24 rounded-xl bg-[#F4F6F8] text-ink-muted flex flex-col items-center justify-center cursor-pointer hover:bg-amber-50 transition-colors">
            <AppIcon name="camera" :size="22" :stroke-width="1.5" />
            <span class="text-xs mt-1">{{ photoList.length }}/5</span>
            <input type="file" multiple accept="image/*" @change="onSelectPhotos" class="hidden" />
          </label>
        </div>
      </div>

      <!-- 7. 참여 인원 -->
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="input-label">최소 인원</label>
          <input v-model.number="form.min_participants" type="number" min="2" placeholder="2"
            class="input-soft px-3" />
        </div>
        <div>
          <label class="input-label">최대 인원</label>
          <input v-model.number="form.max_participants" type="number" min="2" :max="maxPeopleCap" placeholder="50"
            class="input-soft px-3" />
        </div>
      </div>

      <!-- 6-1. 수령 방식 -->
      <div>
        <label class="input-label mb-2">수령 방식 <span class="text-red-400">*</span></label>
        <div class="grid grid-cols-2 gap-2">
          <label v-for="o in FULFILLMENTS" :key="o.value" class="flex items-start gap-2 p-3 rounded-xl border transition"
            :class="[form.fulfillment === o.value ? 'border-amber-400 bg-amber-50' : 'border-gray-200', (o.value === 'delivery' && form.scope === 'national') ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:bg-gray-50']">
            <input v-model="form.fulfillment" type="radio" :value="o.value" :disabled="o.value === 'delivery' && form.scope === 'national'" class="mt-0.5 accent-amber-500" />
            <div>
              <div class="text-sm font-semibold text-ink">{{ o.label }}</div>
              <div class="text-xs text-ink-muted">{{ o.desc }}</div>
            </div>
          </label>
        </div>
        <div class="text-xs text-ink-muted mt-1.5">택배용은 배송·교환·환불을 개설자가 직접 책임져요. 전국 공동구매는 우선 쿠폰용만 열 수 있어요.</div>
      </div>

      <!-- 6-2. 범위 (기본 = 내 주 + 인접 주 / 전국) -->
      <div>
        <label class="input-label mb-2">공동구매 범위 <span class="text-red-400">*</span></label>
        <div class="grid grid-cols-2 gap-2">
          <label class="flex items-start gap-2 p-3 rounded-xl border cursor-pointer transition"
            :class="form.scope === 'local' ? 'border-amber-400 bg-amber-50' : 'border-gray-200 hover:bg-gray-50'">
            <input v-model="form.scope" type="radio" value="local" class="mt-0.5 accent-amber-500" />
            <div>
              <div class="text-sm font-semibold text-ink">기본 (내 주 + 인접 주)</div>
              <div class="text-xs text-ink-muted">
                <template v-if="regionList.length">{{ regionList.join(' · ') }}</template>
                <template v-else>아래 State 를 입력하면 포함되는 주가 나와요</template>
              </div>
            </div>
          </label>
          <label class="flex items-start gap-2 p-3 rounded-xl border cursor-pointer transition"
            :class="form.scope === 'national' ? 'border-amber-400 bg-amber-50' : 'border-gray-200 hover:bg-gray-50'">
            <input v-model="form.scope" type="radio" value="national" class="mt-0.5 accent-amber-500" />
            <div>
              <div class="text-sm font-semibold text-ink">전국</div>
              <div class="text-xs text-ink-muted">모든 주에 보여요 · 쿠폰용만 · 사업자 등록증 필수 · 디파짓 ×{{ NATIONAL_DEPOSIT_MULTIPLIER }}</div>
            </div>
          </label>
        </div>
      </div>

      <!-- 7-0. 총 수량 -->
      <div>
        <label class="input-label">총 수량(개) <span class="text-red-400">*</span>
          <span class="text-xs text-ink-muted font-normal">참가자가 살 수 있는 전체 개수</span></label>
        <input v-model.number="form.total_quantity" type="number" min="1" placeholder="예: 30"
          class="input-soft px-3" />
      </div>

      <!-- 7-1. 등급 안내 (가격·인원에 따라 자동) -->
      <div class="rounded-xl p-3 text-xs leading-relaxed"
        :class="isSmall ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800'">
        <div class="font-semibold mb-0.5">
          {{ isSmall ? '소액 공동구매 · 바로 등록됩니다' : '사업자 공동구매 · 관리자 승인이 필요합니다' }}
          <span v-if="estTotal > 0" class="font-normal">(예상 최대 총액 ${{ estTotal.toLocaleString() }})</span>
        </div>
        <div v-if="isSmall">
          총액 ${{ SMALL_TOTAL_LIMIT }} 미만 · 최대 {{ SMALL_MAX_PEOPLE }}명 · {{ SMALL_MAX_QUANTITY }}개 · 마감 {{ SMALL_MAX_DAYS }}일 이내. 사업자 등록증 없이 등록할 수 있어요.
        </div>
        <div v-else-if="form.scope === 'national'">
          전국 공동구매는 총액과 상관없이 사업자 등록증과 관리자 승인이 필요하고, 마감은 {{ BUSINESS_MAX_DAYS }}일(1개월) 이내예요.
        </div>
        <div v-else>
          예상 총액이 ${{ SMALL_TOTAL_LIMIT }} 이상이거나 인원이 {{ SMALL_MAX_PEOPLE }}명, 수량이 {{ SMALL_MAX_QUANTITY }}개를 넘으면 사업자 등록증과 관리자 승인이 필요하고, 마감은 {{ BUSINESS_MAX_DAYS }}일(1개월) 이내예요.
          <button v-if="smallFit && form.scope !== 'national'" type="button" @click="fitSmall" class="underline font-semibold ml-1">소액으로 맞추기 (최대 {{ smallFit }}명)</button>
        </div>
        <div class="mt-1.5 pt-1.5 border-t border-black/10">
          열 때 디파짓 <b>${{ depositPreview }}</b> (환불되지 않아요{{ form.scope === 'national' ? `, 전국 ×${NATIONAL_DEPOSIT_MULTIPLIER} 적용` : '' }}). 결제 기능이 열리기 전에는 받지 않고 안내만 해요.
        </div>
      </div>

      <!-- 8. 마감일 -->
      <div>
        <label class="input-label">마감일 <span class="text-xs text-ink-muted font-normal">(최대 {{ isSmall ? SMALL_MAX_DAYS + '일' : '1개월' }})</span></label>
        <input v-model="form.deadline" type="date" :min="deadlineMin" :max="deadlineMax"
          class="input-soft px-3" />
      </div>

      <!-- 9. 종료 방식 -->
      <div>
        <label class="input-label mb-2">종료 방식</label>
        <div class="space-y-2">
          <label class="flex items-start gap-2 p-3 rounded-xl border cursor-pointer transition"
            :class="form.end_type === 'target_met' ? 'border-amber-400 bg-amber-50' : 'border-gray-200 hover:bg-gray-50'">
            <input v-model="form.end_type" type="radio" value="target_met" class="mt-0.5 accent-amber-500" />
            <div>
              <div class="text-sm font-semibold text-ink">목표 인원 달성 시 자동 종료</div>
              <div class="text-xs text-ink-muted">최대 인원에 도달하면 자동으로 모집이 마감됩니다</div>
            </div>
          </label>
          <label class="flex items-start gap-2 p-3 rounded-xl border cursor-pointer transition"
            :class="form.end_type === 'time_limit' ? 'border-amber-400 bg-amber-50' : 'border-gray-200 hover:bg-gray-50'">
            <input v-model="form.end_type" type="radio" value="time_limit" class="mt-0.5 accent-amber-500" />
            <div>
              <div class="text-sm font-semibold text-ink">기한 종료 시 현재 인원 기준 진행</div>
              <div class="text-xs text-ink-muted">마감일이 지나면 참여 인원 수에 관계없이 진행됩니다</div>
            </div>
          </label>
          <label class="flex items-start gap-2 p-3 rounded-xl border cursor-pointer transition"
            :class="form.end_type === 'flexible' ? 'border-amber-400 bg-amber-50' : 'border-gray-200 hover:bg-gray-50'">
            <input v-model="form.end_type" type="radio" value="flexible" class="mt-0.5 accent-amber-500" />
            <div>
              <div class="text-sm font-semibold text-ink">최소 인원 미달 시 자동 취소</div>
              <div class="text-xs text-ink-muted">마감일까지 최소 인원에 도달하지 못하면 자동 취소됩니다</div>
            </div>
          </label>
        </div>
      </div>

      <!-- 9-1. 참가비 결제 방식 -->
      <div>
        <label class="input-label mb-2">참가비 결제 방식</label>
        <select v-model="form.payment_method" class="input-soft px-3">
          <option value="none">참가비 없음 (무료 참여)</option>
          <option value="point">포인트로 결제</option>
        </select>
      </div>

      <!-- 10. 할인 티어 -->
      <div>
        <label class="input-label mb-2">할인 티어 (선택)</label>
        <div class="space-y-2">
          <div v-for="(tier, idx) in form.discount_tiers" :key="idx"
            class="flex items-center gap-2 bg-[#F4F6F8] rounded-xl p-3">
            <div class="flex-1 flex items-center gap-2">
              <label class="text-xs text-ink-muted flex-shrink-0">최소</label>
              <input v-model.number="tier.min_people" type="number" min="2" placeholder="10"
                class="input-soft bg-white px-2 py-1.5 w-20" />
              <label class="text-xs text-ink-muted flex-shrink-0">명 이상</label>
            </div>
            <div class="flex-1 flex items-center gap-2">
              <label class="text-xs text-ink-muted flex-shrink-0">할인</label>
              <input v-model.number="tier.discount_pct" type="number" min="1" max="100" placeholder="10"
                class="input-soft bg-white px-2 py-1.5 w-20" />
              <label class="text-xs text-ink-muted flex-shrink-0">%</label>
            </div>
            <button @click="removeTier(idx)"
              class="text-red-400 hover:text-red-600 flex-shrink-0 w-7 h-7 flex items-center justify-center rounded-full hover:bg-red-50 transition">
              <AppIcon name="x" :size="14" />
            </button>
          </div>
        </div>
        <button @click="addTier"
          class="btn-soft mt-2 px-3 py-1.5 text-xs">
          <AppIcon name="plus" :size="12" /> 할인 티어 추가
        </button>
      </div>

      <!-- 11. 지역 -->
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="input-label">City</label>
          <input v-model="form.city" type="text" placeholder="Los Angeles"
            class="input-soft px-3" />
        </div>
        <div>
          <label class="input-label">State</label>
          <input v-model="form.state" type="text" placeholder="CA"
            class="input-soft px-3" />
        </div>
      </div>

      <!-- 12. 사업자 등록증 -->
      <div v-if="!isSmall">
        <label class="input-label">사업자 등록증 <span class="text-red-400">*</span></label>
        <div class="mt-1">
          <label class="flex items-center gap-2 rounded-xl bg-[#F4F6F8] text-ink-muted px-4 py-3 cursor-pointer hover:bg-amber-50 transition-colors">
            <AppIcon name="paperclip" :size="18" />
            <span class="text-sm text-ink-muted">{{ businessDocName || '파일을 선택하세요 (PDF, JPG, PNG)' }}</span>
            <input type="file" accept=".pdf,.jpg,.jpeg,.png" @change="onBusinessDoc" class="hidden" />
          </label>
        </div>
        <div class="text-xs text-ink-muted mt-1">관리자 승인 시 사업자 등록증을 확인합니다</div>
      </div>

      <!-- 안내문 -->
      <div v-if="!isSmall" class="bg-amber-50 rounded-xl p-3">
        <div class="flex items-start gap-2">
          <span class="text-amber-500 flex-shrink-0"><AppIcon name="alert-circle" :size="16" /></span>
          <div class="text-xs text-amber-800 leading-relaxed">
            <b>등록 후 관리자 승인이 필요합니다.</b> 사업자 등록증 확인 후 승인됩니다.
          </div>
        </div>
      </div>

      <!-- 에러 -->
      <div v-if="error" class="text-red-500 text-sm">{{ error }}</div>

      <!-- 버튼 -->
      <div class="flex gap-3 pt-2">
        <button @click="submit" :disabled="submitting"
          class="btn-primary px-6">
          {{ submitting ? '등록 중...' : '등록하기' }}
        </button>
        <button @click="$router.back()" class="btn-secondary px-6">취소</button>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
import PageHeader from '../../components/PageHeader.vue'
import { stateRegion, isUsState, normalizeState } from '../../utils/usStates'

const router = useRouter()
const auth = useAuthStore()

const categories = [
  { value: 'food', label: '🍱 식품' },
  { value: 'beauty', label: '💄 뷰티' },
  { value: 'electronics', label: '📱 전자제품' },
  { value: 'living', label: '🏠 생활용품' },
  { value: 'fashion', label: '👗 패션' },
  { value: 'health', label: '💊 건강' },
  { value: 'education', label: '📚 교육' },
  { value: 'etc', label: '📋 기타' },
]

const form = reactive({
  title: '',
  content: '',
  category: 'food',
  original_price: null,
  group_price: null,
  product_url: '',
  min_participants: 2,
  max_participants: 50,
  total_quantity: null,
  scope: 'local',
  fulfillment: '',
  deadline: '',
  end_type: 'target_met',
  payment_method: 'none',
  discount_tiers: [],
  city: '',
  state: '',
})

// 등급 — 서버 App\Support\GroupBuyTier 와 같은 숫자(바꿀 때 함께 바꾼다)
const SMALL_TOTAL_LIMIT = 500
const SMALL_MAX_PEOPLE = 50
const SMALL_MAX_QUANTITY = 50
const SMALL_MAX_DAYS = 14
const BUSINESS_MAX_DAYS = 31
const maxPeopleCap = 10000
const NATIONAL_DEPOSIT_MULTIPLIER = 3   // 전국 디파짓 배수 (서버 GroupBuyTier::NATIONAL_DEPOSIT_MULTIPLIER)

const FULFILLMENTS = [
  { value: 'delivery', label: '택배용', desc: '상품을 택배·배송으로 받아요' },
  { value: 'coupon', label: '쿠폰용', desc: '쿠폰·이용권을 받아 매장 등에서 써요' },
]

// 예상 최대 총액 = (공동구매 최저가 || 원래 가격) × (총 수량 || 최대 인원)
const estTotal = computed(() => {
  const price = Number(form.group_price || form.original_price || 0)
  return price * Number(form.total_quantity || form.max_participants || 0)
})
// 최대 인원이 없거나 50명을 넘거나, 수량이 50개를 넘으면 사업자 등급
const isSmall = computed(() => {
  if (form.scope === 'national') return false   // 전국은 총액과 상관없이 사업자 등급
  const max = Number(form.max_participants || 0)
  const qty = Number(form.total_quantity || 0)
  if (max < 1 || max > SMALL_MAX_PEOPLE || qty > SMALL_MAX_QUANTITY) return false
  return estTotal.value < SMALL_TOTAL_LIMIT
})
// 소액으로 유지할 수 있는 최대 인원·수량 (2개도 못 채우면 0)
const smallFit = computed(() => {
  const price = Number(form.group_price || form.original_price || 0)
  if (price <= 0) return 0
  const fit = Math.min(SMALL_MAX_PEOPLE, SMALL_MAX_QUANTITY, Math.floor((SMALL_TOTAL_LIMIT - 0.01) / price))
  return fit >= 2 ? fit : 0
})
function fitSmall() {
  if (!smallFit.value) return
  form.max_participants = Math.min(Number(form.max_participants) || smallFit.value, smallFit.value)
  form.total_quantity = Math.min(Number(form.total_quantity) || smallFit.value, smallFit.value)
  if (form.min_participants > form.max_participants) form.min_participants = Math.min(2, form.max_participants)
}

function dateStr(addDays) {
  const d = new Date()
  d.setDate(d.getDate() + addDays)
  const p = n => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`
}
// 기본(지역) 범위에 포함되는 주 = 작성자 주 + 인접 주
const regionList = computed(() => stateRegion(form.state))

// 열 때 디파짓 안내 (서버 GroupBuyTier::deposit 과 같은 규칙): 소액 $5, 사업자 기간별 $5/$7/$10/$15, 전국 ×3
const depositPreview = computed(() => {
  let base = 5
  if (!isSmall.value) {
    let days = 1
    if (form.deadline) {
      const end = new Date(form.deadline + 'T23:59:59')
      days = Math.max(1, Math.ceil((end - new Date()) / 86400000))
    }
    base = days <= 3 ? 5 : days <= 7 ? 7 : days <= 14 ? 10 : 15
  }
  return base * (form.scope === 'national' ? NATIONAL_DEPOSIT_MULTIPLIER : 1)
})

const deadlineMin = computed(() => dateStr(1))
const deadlineMax = computed(() => dateStr(isSmall.value ? SMALL_MAX_DAYS : BUSINESS_MAX_DAYS))

// 전국은 우선 쿠폰용만 — 전국을 고르면 쿠폰용으로 맞춘다
watch(() => form.scope, v => { if (v === 'national') form.fulfillment = 'coupon' })

// 사진 관리
const photoList = ref([])

function onSelectPhotos(e) {
  const newFiles = Array.from(e.target.files)
  const total = photoList.value.length + newFiles.length
  if (total > 5) {
    alert('사진은 최대 5장까지 등록 가능합니다')
    return
  }
  for (const f of newFiles) {
    const reader = new FileReader()
    reader.onload = ev => {
      photoList.value.push({ file: f, preview: ev.target.result })
    }
    reader.readAsDataURL(f)
  }
  e.target.value = ''
}

function removePhoto(idx) {
  photoList.value.splice(idx, 1)
}

// 사업자 등록증
const businessDoc = ref(null)
const businessDocName = ref('')

function onBusinessDoc(e) {
  const file = e.target.files[0]
  if (file) {
    businessDoc.value = file
    businessDocName.value = file.name
  }
}

// 할인 티어
function addTier() {
  form.discount_tiers.push({ min_people: null, discount_pct: null })
}

function removeTier(idx) {
  form.discount_tiers.splice(idx, 1)
}

// 제출
const error = ref('')
const submitting = ref(false)

async function submit() {
  if (!form.title.trim()) { error.value = '제목을 입력해주세요'; return }
  if (!form.content.trim()) { error.value = '상세 설명을 입력해주세요'; return }
  if (!form.max_participants || form.max_participants < 2) { error.value = '최대 인원을 입력해주세요'; return }
  if (!form.fulfillment) { error.value = '수령 방식(택배용/쿠폰용)을 골라주세요'; return }
  if (form.scope === 'national' && form.fulfillment !== 'coupon') { error.value = '전국 공동구매는 우선 쿠폰용만 열 수 있어요'; return }
  if (form.scope === 'local' && !isUsState(form.state)) { error.value = '지역 공동구매는 State(주)를 정확히 입력해주세요. 예: GA'; return }
  if (!form.total_quantity || form.total_quantity < 1) { error.value = '총 수량(개)을 입력해주세요'; return }
  if (!form.deadline) { error.value = '마감일을 정해주세요'; return }
  if (form.deadline > deadlineMax.value) {
    error.value = isSmall.value
      ? `소액 공동구매는 마감일을 ${SMALL_MAX_DAYS}일 안으로 정해주세요`
      : '마감일은 1개월 안으로 정해주세요'
    return
  }
  if (!isSmall.value && !businessDoc.value) { error.value = '예상 총액이 $' + SMALL_TOTAL_LIMIT + ' 이상이면 사업자 등록증을 첨부해주세요'; return }

  submitting.value = true
  error.value = ''

  try {
    const fd = new FormData()
    fd.append('title', form.title)
    fd.append('content', form.content)
    fd.append('category', form.category)
    if (form.original_price) fd.append('original_price', form.original_price)
    if (form.group_price) fd.append('group_price', form.group_price)
    if (form.product_url) fd.append('product_url', form.product_url)
    fd.append('min_participants', form.min_participants)
    fd.append('max_participants', form.max_participants)
    fd.append('total_quantity', form.total_quantity)
    fd.append('scope', form.scope)
    fd.append('fulfillment', form.fulfillment)
    if (form.deadline) fd.append('deadline', form.deadline)
    fd.append('end_type', form.end_type)
    fd.append('payment_method', form.payment_method)
    if (form.city) fd.append('city', form.city)
    if (form.state) fd.append('state', normalizeState(form.state))

    // 할인 티어
    const validTiers = form.discount_tiers.filter(t => t.min_people && t.discount_pct)
    if (validTiers.length) {
      fd.append('discount_tiers', JSON.stringify(validTiers))
    }

    // 이미지
    photoList.value.forEach(p => fd.append('images[]', p.file))

    // 사업자 등록증
    if (!isSmall.value && businessDoc.value) fd.append('business_doc', businessDoc.value)

    await axios.post('/api/groupbuys', fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    router.push('/groupbuy')
  } catch (e) {
    const msg = e.response?.data?.message || ''
    const errs = e.response?.data?.errors ? Object.values(e.response.data.errors).flat().join(', ') : ''
    error.value = msg || errs || '등록 실패: ' + e.message
  }

  submitting.value = false
}

// 유저 프로필에서 도시/주 자동 채우기
onMounted(() => {
  if (auth.user) {
    if (auth.user.city) form.city = auth.user.city
    if (auth.user.state) form.state = auth.user.state
  }
})
</script>
