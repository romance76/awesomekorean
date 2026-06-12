<template>
<div class="min-h-screen">
  <div class="max-w-3xl mx-auto px-4 py-5 space-y-4">
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
      <span class="icon-chip w-9 h-9 bg-emerald-50 text-emerald-600"><AppIcon name="shopping-cart" :size="20" /></span>
      {{ isEdit ? '물품 수정' : '물품 등록' }}
    </h1>

    <div class="card p-5 space-y-4">

      <!-- ═══ 1. 사진 (맨 위) ═══ -->
      <div>
        <label class="input-label flex items-center gap-2 mb-0">
          <AppIcon name="camera" :size="16" /> 사진
          <span class="text-xs text-ink-muted font-normal">기본 {{ freePhotos }}장 무료 · 추가 1장당 {{ extraPhotoPoints }}P</span>
        </label>

        <!-- 사진 미리보기 그리드 -->
        <div class="flex flex-wrap gap-2 mt-2">
          <div v-for="(photo, idx) in photoList" :key="idx"
            class="relative w-24 h-24 rounded-xl overflow-hidden border-2 cursor-pointer group"
            :class="mainPhotoIdx === idx ? 'border-amber-400 ring-2 ring-amber-200' : 'border-gray-200'"
            @click="mainPhotoIdx = idx">
            <img :src="photo.preview" class="w-full h-full object-cover" />
            <!-- 메인 표시 -->
            <div v-if="mainPhotoIdx === idx" class="absolute top-1 left-1 bg-amber-400 text-white text-[11px] font-bold px-1.5 py-0.5 rounded">메인</div>
            <!-- 유료 표시 -->
            <div v-if="idx >= freePhotos" class="absolute top-1 right-1 bg-red-500 text-white text-[11px] font-bold px-1 py-0.5 rounded">{{ extraPhotoPoints }}P</div>
            <!-- 삭제 -->
            <button @click.stop="removePhoto(idx)"
              class="absolute bottom-1 right-1 bg-black/60 text-white w-5 h-5 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition"><AppIcon name="x" :size="12" /></button>
          </div>

          <!-- 사진 추가 버튼 -->
          <label v-if="photoList.length < 10"
            class="w-24 h-24 rounded-xl bg-[#F4F6F8] text-ink-muted flex flex-col items-center justify-center cursor-pointer hover:bg-amber-50 transition-colors">
            <AppIcon name="camera" :size="22" :stroke-width="1.5" />
            <span class="text-xs mt-1">{{ photoList.length }}/10</span>
            <input type="file" multiple accept="image/*" @change="onSelectPhotos" class="hidden" />
          </label>
        </div>

        <!-- 포인트 안내 -->
        <div v-if="extraPhotoCost > 0" class="mt-2 text-xs text-red-500 font-semibold flex items-center gap-1">
          <AppIcon name="alert-circle" :size="14" /> 추가 사진 {{ photoList.length - freePhotos }}장 × {{ extraPhotoPoints }}P = <b>{{ extraPhotoCost }}P</b> 차감됩니다
        </div>
        <div class="text-xs text-ink-muted mt-1">첫 번째 사진이 메인 썸네일로 사용됩니다. 클릭하여 메인 사진을 변경할 수 있습니다. (최대 10장)</div>
      </div>

      <!-- ═══ 2. 제목 ═══ -->
      <div><label class="input-label">제목</label><input v-model="form.title" type="text" placeholder="예: 아이폰 15 Pro 판매합니다" class="input-soft" /></div>

      <!-- ═══ 3. 가격/카테고리/상태 ═══ -->
      <div class="grid grid-cols-3 gap-3">
        <div><label class="input-label">가격 ($)</label><input v-model.number="form.price" type="number" class="input-soft px-3" /></div>
        <div>
          <label class="input-label">카테고리</label>
          <select v-model="form.category" class="input-soft px-3">
            <option v-for="c in categories" :key="c.value" :value="c.value">{{ c.label }}</option>
          </select>
        </div>
        <div>
          <label class="input-label">상태</label>
          <select v-model="form.condition" class="input-soft px-3">
            <option value="new">새상품</option><option value="like_new">거의 새것</option><option value="good">양호</option><option value="fair">보통</option>
          </select>
        </div>
      </div>

      <div class="flex items-center gap-2"><input v-model="form.is_negotiable" type="checkbox" class="rounded" /><span class="text-sm text-ink-light">가격 협의 가능</span></div>

      <!-- ═══ 4. 홀드 설정 ═══ -->
      <div class="bg-blue-50 rounded-xl p-4">
        <div class="flex items-center gap-2 mb-2">
          <input v-model="form.hold_enabled" type="checkbox" class="rounded" />
          <span class="text-sm font-semibold text-blue-800 flex items-center gap-1"><AppIcon name="lock" :size="14" /> 홀드 허용 (구매자가 포인트로 물건 예약)</span>
        </div>
        <div v-if="form.hold_enabled" class="space-y-2 ml-6">
          <div class="flex items-center gap-2">
            <label class="text-xs text-ink-light w-28">6시간당 가격</label>
            <input v-model.number="form.hold_price_per_6h" type="number" min="10" class="input-soft px-2 py-1 w-24" />
            <span class="text-xs text-ink-muted">P</span>
          </div>
          <div class="flex items-center gap-2">
            <label class="text-xs text-ink-light w-28">최대 홀드 기간</label>
            <select v-model.number="form.hold_max_hours" class="input-soft px-2 py-1 w-auto">
              <option :value="6">6시간</option><option :value="12">12시간</option>
              <option :value="24">1일</option><option :value="48">2일</option>
              <option :value="72">3일</option><option :value="168">7일</option>
            </select>
          </div>
          <div class="text-xs text-ink-muted">예: 100P/6시간 → 하루 홀드 = 400P (90% 판매자, 10% 수수료)</div>
        </div>
      </div>

      <!-- ═══ 5. 물품 위치 (zipcode 기준, 기본 유저 집코드) ═══ -->
      <div class="bg-amber-50/50 rounded-xl p-3 space-y-2">
        <label class="input-label flex items-center gap-2 mb-0">
          <AppIcon name="map-pin" :size="16" /> 물품 위치 (Zip Code)
          <span class="text-xs text-ink-muted font-normal">기본값은 본인 집코드. 변경 가능.</span>
        </label>
        <div class="flex items-center gap-2">
          <input v-model="form.zipcode" type="text" maxlength="5" placeholder="90001"
            @input="onZipChange"
            class="input-soft w-28 px-3 font-mono" />
          <span v-if="form.city || form.state" class="text-xs text-ink-light flex items-center gap-1">
            <AppIcon name="map-pin" :size="12" /> {{ form.city }}{{ form.state ? ', ' + form.state : '' }}
          </span>
          <span v-else-if="form.zipcode?.length === 5" class="text-xs text-ink-muted">위치 확인 중...</span>
        </div>
      </div>

      <!-- ═══ 6. 상위노출 (위치 입력 뒤에 배치) ═══ -->
      <PromotionSection resource="market" :is-edit="isEdit"
        :category="form.category" :state="form.state || userState"
        v-model="promotion" ref="promoRef"
        category-label="카테고리" />

      <!-- ═══ 7. 상세 설명 ═══ -->
      <div><label class="input-label">상세 설명</label><textarea v-model="form.content" rows="6" placeholder="상품 상태, 거래 방법 등을 자세히 작성해주세요" class="input-soft px-3"></textarea></div>

      <div v-if="error" class="text-red-500 text-sm">{{ error }}</div>
      <div class="flex gap-3 pt-2">
        <button @click="submit" :disabled="submitting"
          class="btn-primary px-6">
          {{ submitting ? '등록 중...' : (extraPhotoCost > 0 ? `등록하기 (${extraPhotoCost}P 차감)` : '등록하기') }}
        </button>
        <button @click="$router.back()" class="btn-secondary px-6">취소</button>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from 'axios'
import PromotionSection from '../../components/PromotionSection.vue'
import AppIcon from '../../components/AppIcon.vue'
import { useAuthStore } from '../../stores/auth'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const form = reactive({
  title: '', price: 0, category: 'electronics', condition: 'good', content: '',
  is_negotiable: false, hold_enabled: false, hold_price_per_6h: 100, hold_max_hours: 24,
  zipcode: '', city: '', state: '',
})
const promotion = reactive({ tier: 'none', days: 7 })
const promoRef = ref(null)
const userState = computed(() => auth.user?.state || '')

// zipcode 입력시 zippopotam 으로 city/state 자동 (디바운스 500ms)
let zipTimer = null
function onZipChange() {
  clearTimeout(zipTimer)
  zipTimer = setTimeout(async () => {
    const z = (form.zipcode || '').trim()
    if (!/^\d{5}$/.test(z)) { form.city = ''; form.state = ''; return }
    try {
      const r = await fetch(`https://api.zippopotam.us/us/${z}`)
      if (!r.ok) return
      const d = await r.json()
      const p = d.places?.[0]
      if (p) { form.city = p['place name'] || ''; form.state = p['state abbreviation'] || '' }
    } catch {}
  }, 500)
}

const categories = [
  { value: 'electronics', label: '📱 전자기기' },
  { value: 'furniture', label: '🪑 가구' },
  { value: 'clothing', label: '👕 의류' },
  { value: 'auto', label: '🚗 자동차' },
  { value: 'baby', label: '👶 유아' },
  { value: 'sports', label: '⚽ 스포츠' },
  { value: 'books', label: '📚 도서' },
  { value: 'etc', label: '📋 기타' },
]

// 사진 관리
const photoList = ref([])  // [{file, preview}]
const mainPhotoIdx = ref(0)
const freePhotos = 3       // 무료 장수 (관리자 설정 가능)
const extraPhotoPoints = 50 // 추가 1장당 포인트
const extraPhotoCost = computed(() => {
  const extra = Math.max(0, photoList.value.length - freePhotos)
  return extra * extraPhotoPoints
})

function onSelectPhotos(e) {
  const newFiles = Array.from(e.target.files)
  const total = photoList.value.length + newFiles.length
  if (total > 10) {
    alert('사진은 최대 10장까지 등록 가능합니다')
    return
  }
  for (const f of newFiles) {
    const reader = new FileReader()
    reader.onload = ev => {
      photoList.value.push({ file: f, preview: ev.target.result })
    }
    reader.readAsDataURL(f)
  }
  e.target.value = '' // 같은 파일 재선택 가능
}

function removePhoto(idx) {
  photoList.value.splice(idx, 1)
  if (mainPhotoIdx.value >= photoList.value.length) mainPhotoIdx.value = Math.max(0, photoList.value.length - 1)
  if (mainPhotoIdx.value === idx) mainPhotoIdx.value = 0
}

const error = ref('')
const submitting = ref(false)
const isEdit = ref(false)
const editId = ref(null)

async function submit() {
  if (!form.title || !form.content) { error.value = '제목과 설명을 입력해주세요'; return }
  // 프로모션 슬롯 만석 차단
  if (['state_plus','national'].includes(promotion.tier) && promoRef.value?.isSlotFull) {
    const t = promoRef.value?.nextSlotTimeFmt
    error.value = t ? `상위노출 슬롯 만석. ${t} 이후 가능합니다.` : '상위노출 슬롯 만석.'
    return
  }
  submitting.value = true; error.value = ''
  try {
    if (isEdit.value) {
      await axios.put(`/api/market/${editId.value}`, form)
      router.push(`/market/${editId.value}`)
    } else {
      const fd = new FormData()
      Object.keys(form).forEach(k => {
        const v = form[k]
        fd.append(k, typeof v === 'boolean' ? (v ? '1' : '0') : v)
      })
      if (photoList.value.length) {
        photoList.value.forEach(p => fd.append('images[]', p.file))
        fd.append('thumbnail_index', String(mainPhotoIdx.value || 0))
      }
      fd.append('extra_photo_cost', extraPhotoCost.value)

      const { data } = await axios.post('/api/market', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      const createdId = data?.data?.id
      // 프로모션 적용
      if (createdId && promotion.tier !== 'none') {
        try {
          await axios.post(`/api/market/${createdId}/promote`, {
            tier: promotion.tier, days: promotion.days,
          })
        } catch (pe) { console.warn('promote failed', pe?.response?.data?.message) }
      }
      router.push(`/market/${createdId}`)
    }
  } catch (e) {
    const msg = e.response?.data?.message || ''
    const errs = e.response?.data?.errors ? Object.values(e.response.data.errors).flat().join(', ') : ''
    error.value = msg || errs || '등록 실패: ' + e.message
    console.error('market submit error:', e.response?.data || e.message)
  }
  submitting.value = false
}

onMounted(async () => {
  if (route.query.edit) {
    editId.value = route.query.edit; isEdit.value = true
    try {
      const { data } = await axios.get(`/api/market/${editId.value}`)
      const m = data.data; Object.keys(form).forEach(k => { if (m[k] !== undefined) form[k] = m[k] })
    } catch {}
  } else {
    // 신규 등록시 유저 집코드를 기본값으로
    if (auth.user?.zipcode) {
      form.zipcode = auth.user.zipcode
      form.city = auth.user.city || ''
      form.state = auth.user.state || ''
    }
  }
})
</script>
