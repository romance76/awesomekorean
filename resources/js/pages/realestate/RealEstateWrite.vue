<template>
<div class="min-h-screen">
  <div class="max-w-3xl mx-auto px-4 py-5 space-y-4">
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
      <span class="icon-chip w-9 h-9 bg-violet-50 text-violet-600"><AppIcon name="home" :size="20" /></span>
      매물 등록
    </h1>

    <div class="card p-5 space-y-4">
      <!-- 사진 업로드 (최대 20장, 기본 5장 무료) -->
      <div>
        <label class="input-label flex items-center gap-2 mb-0">
          <AppIcon name="camera" :size="16" /> 사진
          <span class="text-xs text-ink-muted font-normal">기본 {{ freePhotos }}장 무료 · 추가 1장당 {{ extraPhotoPoints }}P · 최대 20장</span>
        </label>
        <div class="flex flex-wrap gap-2 mt-2">
          <div v-for="(photo, idx) in photoList" :key="idx"
            class="relative w-20 h-20 rounded-xl overflow-hidden border-2 cursor-pointer group"
            :class="mainPhotoIdx === idx ? 'border-amber-400 ring-2 ring-amber-200' : 'border-gray-200'"
            @click="mainPhotoIdx = idx">
            <img :src="photo.preview" class="w-full h-full object-cover" />
            <div v-if="mainPhotoIdx === idx" class="absolute top-0.5 left-0.5 bg-amber-400 text-white text-[11px] font-bold px-1 py-px rounded">메인</div>
            <div v-if="idx >= freePhotos" class="absolute top-0.5 right-0.5 bg-red-500 text-white text-[11px] font-bold px-1 py-px rounded">{{ extraPhotoPoints }}P</div>
            <button @click.stop="removePhoto(idx)" class="absolute bottom-0.5 right-0.5 bg-black/60 text-white w-4 h-4 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100"><AppIcon name="x" :size="10" /></button>
          </div>
          <label v-if="photoList.length < 20" class="w-20 h-20 rounded-xl bg-[#F4F6F8] text-ink-muted flex flex-col items-center justify-center cursor-pointer hover:bg-amber-50 transition-colors">
            <AppIcon name="camera" :size="20" :stroke-width="1.5" />
            <span class="text-[11px] mt-0.5">{{ photoList.length }}/20</span>
            <input type="file" multiple accept="image/*" @change="onSelectPhotos" class="hidden" />
          </label>
        </div>
        <div v-if="extraPhotoCost > 0" class="mt-1.5 text-xs text-red-500 font-semibold flex items-center gap-1">
          <AppIcon name="alert-circle" :size="14" /> 추가 사진 {{ photoList.length - freePhotos }}장 × {{ extraPhotoPoints }}P = <b>{{ extraPhotoCost }}P</b> 차감
        </div>
        <div class="text-xs text-ink-muted mt-1">클릭하여 메인 사진 선택. 메인 사진이 리스트 썸네일로 표시됩니다.</div>
      </div>

      <div><label class="input-label">제목</label><input v-model="form.title" type="text" placeholder="예: LA 1BR 아파트 렌트" class="input-soft px-3" /></div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="input-label">유형</label>
          <select v-model="form.type" class="input-soft px-3">
            <option value="rent">🔑 렌트</option>
            <option value="sale">🏠 매매</option>
            <option value="roommate">👥 룸메이트</option>
          </select></div>
        <div><label class="input-label">종류</label>
          <select v-model="form.property_type" class="input-soft px-3">
            <!-- 렌트 -->
            <template v-if="form.type==='rent'">
              <optgroup label="주거용">
                <option value="studio">스튜디오</option>
                <option value="1br">1BR</option>
                <option value="2br">2BR</option>
                <option value="3br_plus">3BR 이상</option>
                <option value="minbak">민박</option>
                <option value="etc_home">기타</option>
              </optgroup>
              <optgroup label="상업용">
                <option value="office_rent">오피스</option>
                <option value="retail_rent">소매</option>
                <option value="store_rent">상가</option>
                <option value="building_rent">건물</option>
                <option value="etc_commercial">기타</option>
              </optgroup>
            </template>
            <!-- 룸메이트 -->
            <template v-else-if="form.type==='roommate'">
              <optgroup label="룸메이트">
                <option value="shared_room">쉐어 룸</option>
                <option value="private_room">개인 룸</option>
                <option value="master_room">마스터 룸</option>
                <option value="etc_room">기타</option>
              </optgroup>
            </template>
            <!-- 매매 -->
            <template v-else>
              <optgroup label="주거용 매매">
                <option value="house">하우스</option>
                <option value="condo">콘도</option>
                <option value="duplex">듀플렉스</option>
                <option value="villa">빌라</option>
                <option value="townhouse">타운하우스</option>
                <option value="etc_home">기타</option>
              </optgroup>
              <optgroup label="상업용 매매">
                <option value="office_sale">오피스</option>
                <option value="retail_sale">소매</option>
                <option value="store_sale">상가</option>
                <option value="building">건물</option>
                <option value="etc_commercial">기타</option>
              </optgroup>
            </template>
          </select></div>
      </div>
      <div class="grid grid-cols-4 gap-3">
        <div><label class="input-label">가격 ($)</label><input v-model.number="form.price" type="number" class="input-soft px-3" /></div>
        <div><label class="input-label">방</label><input v-model.number="form.bedrooms" type="number" min="0" class="input-soft px-3" /></div>
        <div><label class="input-label">화장실</label><input v-model.number="form.bathrooms" type="number" min="0" class="input-soft px-3" /></div>
        <div><label class="input-label">면적(sqft)</label><input v-model.number="form.sqft" type="number" class="input-soft px-3" /></div>
      </div>

      <!-- 매물 주소 -->
      <div class="bg-amber-50/50 rounded-xl p-3 space-y-2">
        <label class="input-label flex items-center gap-1.5 mb-0"><AppIcon name="map-pin" :size="16" /> 매물 주소</label>
        <input v-model="form.address" type="text" placeholder="예: 123 Main St" class="input-soft px-3" />
        <div class="grid grid-cols-3 gap-2">
          <input v-model="form.city" type="text" placeholder="City" class="input-soft px-3" />
          <input v-model="form.state" type="text" maxlength="2" placeholder="State (GA)" class="input-soft px-3 uppercase" />
          <input v-model="form.zipcode" type="text" maxlength="5" placeholder="Zip" @input="onReZipChange" class="input-soft px-3 font-mono" />
        </div>
      </div>

      <!-- 상위노출 (주소 다음, 설명 이전) -->
      <PromotionSection resource="realestate" :is-edit="isEdit"
        :category="form.type" :state="form.state || userState"
        v-model="promotion" ref="promoRef"
        category-label="유형 (렌트/매매/룸메이트)" />

      <div><label class="input-label">상세 설명</label><textarea v-model="form.content" rows="6" placeholder="위치, 시설, 조건 등을 자세히 작성해주세요" class="input-soft px-3"></textarea></div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="input-label">연락 전화</label><input v-model="form.contact_phone" type="text" class="input-soft px-3" /></div>
        <div><label class="input-label">연락 이메일</label><input v-model="form.contact_email" type="email" class="input-soft px-3" /></div>
      </div>
      <div v-if="error" class="text-red-500 text-sm">{{ error }}</div>
      <div class="flex gap-3 pt-2">
        <button @click="submit" :disabled="submitting" class="btn-primary px-6">{{ submitting ? '등록 중...' : '매물 등록' }}</button>
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
const form = reactive({ title:'',type:'rent',property_type:'studio',price:0,bedrooms:1,bathrooms:1,sqft:0,content:'',contact_phone:'',contact_email:'', address:'', city:'', state:'', zipcode:'' })

// 사진 관리
const photoList = ref([])
const mainPhotoIdx = ref(0)
const freePhotos = 5
const extraPhotoPoints = 50
const extraPhotoCost = computed(() => Math.max(0, photoList.value.length - freePhotos) * extraPhotoPoints)

function onSelectPhotos(e) {
  const files = Array.from(e.target.files)
  if (photoList.value.length + files.length > 20) { alert('사진은 최대 20장까지'); return }
  for (const f of files) {
    const reader = new FileReader()
    reader.onload = ev => { photoList.value.push({ file: f, preview: ev.target.result }) }
    reader.readAsDataURL(f)
  }
  e.target.value = ''
}
function removePhoto(idx) {
  photoList.value.splice(idx, 1)
  if (mainPhotoIdx.value >= photoList.value.length) mainPhotoIdx.value = Math.max(0, photoList.value.length - 1)
}

let reZipTimer = null
function onReZipChange() {
  clearTimeout(reZipTimer)
  reZipTimer = setTimeout(async () => {
    const z = (form.zipcode || '').trim()
    if (!/^\d{5}$/.test(z)) return
    try {
      const r = await fetch(`https://api.zippopotam.us/us/${z}`)
      if (!r.ok) return
      const d = await r.json()
      const p = d.places?.[0]
      if (p) {
        if (!form.city) form.city = p['place name'] || ''
        if (!form.state) form.state = p['state abbreviation'] || ''
      }
    } catch {}
  }, 500)
}
const promotion = reactive({ tier: 'none', days: 7 })
const promoRef = ref(null)
const userState = computed(() => auth.user?.state || '')
const error = ref('')
const submitting = ref(false)
const isEdit = ref(false)
const editId = ref(null)

async function submit() {
  if (!form.title || !form.content || !form.price) { error.value = '필수 항목을 입력해주세요'; return }
  if (['state_plus','national'].includes(promotion.tier) && promoRef.value?.isSlotFull) {
    const t = promoRef.value?.nextSlotTimeFmt
    error.value = t ? `상위노출 슬롯 만석. ${t} 이후 가능합니다.` : '상위노출 슬롯 만석.'
    return
  }
  submitting.value = true; error.value = ''
  try {
    const fd = new FormData()
    Object.keys(form).forEach(k => { if (form[k] !== null && form[k] !== undefined) fd.append(k, form[k]) })

    // 새 사진만 업로드, 기존 사진 경로는 existing_images 로 전달
    const newPhotos = photoList.value.filter(p => p.file)
    const existingPaths = photoList.value.filter(p => p.existing).map(p => {
      // preview URL → 상대 경로
      return String(p.preview).replace(/^\/storage\//, '')
    })
    newPhotos.forEach(p => fd.append('images[]', p.file))
    if (existingPaths.length) fd.append('existing_images', JSON.stringify(existingPaths))
    fd.append('thumbnail_index', String(mainPhotoIdx.value || 0))

    if (!isEdit.value) {
      fd.append('extra_photo_cost', String(extraPhotoCost.value))
      const { data } = await axios.post('/api/realestate', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      const createdId = data?.data?.id
      if (createdId && promotion.tier !== 'none') {
        try { await axios.post(`/api/realestate/${createdId}/promote`, { tier: promotion.tier, days: promotion.days }) } catch {}
      }
      router.push(`/realestate/${createdId}`)
    } else {
      fd.append('_method', 'PUT')
      await axios.post(`/api/realestate/${editId.value}`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      router.push(`/realestate/${editId.value}`)
    }
  } catch (e) { error.value = e.response?.data?.message || '등록 실패' }
  submitting.value = false
}

onMounted(async () => {
  if (route.query.edit) {
    editId.value = route.query.edit; isEdit.value = true
    try {
      const { data } = await axios.get(`/api/realestate/${editId.value}`)
      const r = data.data
      Object.keys(form).forEach(k => { if (r[k] !== undefined && r[k] !== null) form[k] = r[k] })

      // 기존 사진 로드 (preview URL 생성)
      if (Array.isArray(r.images) && r.images.length) {
        r.images.forEach(path => {
          const url = String(path).startsWith('http') || String(path).startsWith('/storage/')
            ? path : '/storage/' + path
          photoList.value.push({ file: null, preview: url, existing: true })
        })
      }

      // 기존 프로모션 상태 표시
      if (r.promotion_tier && r.promotion_tier !== 'none') {
        promotion.tier = r.promotion_tier
        // 남은 일수 계산
        if (r.promotion_expires_at) {
          const remaining = Math.max(1, Math.ceil((new Date(r.promotion_expires_at) - Date.now()) / 86400000))
          promotion.days = remaining
        }
      }
    } catch {}
  }
})
</script>
