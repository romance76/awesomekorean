<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-3xl mx-auto px-4 py-5">
    <h1 class="text-xl font-black text-gray-800 mb-4">🛒 {{ isEdit ? '물품 수정' : '물품 등록' }}</h1>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">

      <!-- ═══ 1. 사진 (맨 위) ═══ -->
      <div>
        <label class="text-sm font-semibold text-gray-700 flex items-center gap-2">
          📷 사진
          <span class="text-xs text-gray-400 font-normal">기본 {{ freePhotos }}장 무료 · 추가 1장당 {{ extraPhotoPoints }}P</span>
        </label>

        <!-- 사진 미리보기 그리드 -->
        <div class="flex flex-wrap gap-2 mt-2">
          <div v-for="(photo, idx) in photoList" :key="idx"
            class="relative w-24 h-24 rounded-lg overflow-hidden border-2 cursor-pointer group"
            :class="mainPhotoIdx === idx ? 'border-amber-400 ring-2 ring-amber-200' : 'border-gray-200'"
            @click="mainPhotoIdx = idx">
            <img :src="photo.preview" class="w-full h-full object-cover" />
            <!-- 메인 표시 -->
            <div v-if="mainPhotoIdx === idx" class="absolute top-1 left-1 bg-amber-400 text-amber-900 text-[9px] font-bold px-1.5 py-0.5 rounded">메인</div>
            <!-- 유료 표시 -->
            <div v-if="idx >= freePhotos" class="absolute top-1 right-1 bg-red-500 text-white text-[9px] font-bold px-1 py-0.5 rounded">{{ extraPhotoPoints }}P</div>
            <!-- 삭제 -->
            <button @click.stop="removePhoto(idx)"
              class="absolute bottom-1 right-1 bg-black/60 text-white w-5 h-5 rounded-full text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition">✕</button>
          </div>

          <!-- 사진 추가 버튼 -->
          <label v-if="photoList.length < 10"
            class="w-24 h-24 rounded-lg border-2 border-dashed border-gray-300 flex flex-col items-center justify-center cursor-pointer hover:border-amber-400 hover:bg-amber-50/50 transition">
            <span class="text-2xl text-gray-400">+</span>
            <span class="text-[10px] text-gray-400">사진 추가</span>
            <input type="file" multiple accept="image/*" @change="onSelectPhotos" class="hidden" />
          </label>
        </div>

        <!-- 포인트 안내 -->
        <div v-if="extraPhotoCost > 0" class="mt-2 text-xs text-red-500 font-semibold">
          ⚠️ 추가 사진 {{ photoList.length - freePhotos }}장 × {{ extraPhotoPoints }}P = <b>{{ extraPhotoCost }}P</b> 차감됩니다
        </div>
        <div class="text-[10px] text-gray-400 mt-1">첫 번째 사진이 메인 썸네일로 사용됩니다. 클릭하여 메인 사진을 변경할 수 있습니다. (최대 10장)</div>
      </div>

      <!-- ═══ 2. 제목 ═══ -->
      <div><label class="text-sm font-semibold text-gray-700">제목</label><input v-model="form.title" type="text" placeholder="예: 아이폰 15 Pro 판매합니다" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>

      <!-- ═══ 3. 가격/카테고리/상태 ═══ -->
      <div class="grid grid-cols-3 gap-3">
        <div><label class="text-sm font-semibold text-gray-700">가격 ($)</label><input v-model.number="form.price" type="number" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
        <div>
          <label class="text-sm font-semibold text-gray-700">카테고리</label>
          <select v-model="form.category" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm">
            <option v-for="c in categories" :key="c.value" :value="c.value">{{ c.label }}</option>
          </select>
        </div>
        <div>
          <label class="text-sm font-semibold text-gray-700">상태</label>
          <select v-model="form.condition" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm">
            <option value="new">새상품</option><option value="like_new">거의 새것</option><option value="good">양호</option><option value="fair">보통</option>
          </select>
        </div>
      </div>

      <div class="flex items-center gap-2"><input v-model="form.is_negotiable" type="checkbox" class="rounded" /><span class="text-sm text-gray-600">가격 협의 가능</span></div>

      <!-- ═══ 4. 홀드 설정 ═══ -->
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex items-center gap-2 mb-2">
          <input v-model="form.hold_enabled" type="checkbox" class="rounded" />
          <span class="text-sm font-semibold text-blue-800">🔒 홀드 허용 (구매자가 포인트로 물건 예약)</span>
        </div>
        <div v-if="form.hold_enabled" class="space-y-2 ml-6">
          <div class="flex items-center gap-2">
            <label class="text-xs text-gray-600 w-28">6시간당 가격</label>
            <input v-model.number="form.hold_price_per_6h" type="number" min="10" class="border rounded px-2 py-1 text-sm w-24" />
            <span class="text-xs text-gray-400">P</span>
          </div>
          <div class="flex items-center gap-2">
            <label class="text-xs text-gray-600 w-28">최대 홀드 기간</label>
            <select v-model.number="form.hold_max_hours" class="border rounded px-2 py-1 text-sm">
              <option :value="6">6시간</option><option :value="12">12시간</option>
              <option :value="24">1일</option><option :value="48">2일</option>
              <option :value="72">3일</option><option :value="168">7일</option>
            </select>
          </div>
          <div class="text-[10px] text-gray-400">예: 100P/6시간 → 하루 홀드 = 400P (90% 판매자, 10% 수수료)</div>
        </div>
      </div>

      <!-- ═══ 5. 상세 설명 ═══ -->
      <div><label class="text-sm font-semibold text-gray-700">상세 설명</label><textarea v-model="form.content" rows="6" placeholder="상품 상태, 거래 방법 등을 자세히 작성해주세요" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none resize-none"></textarea></div>

      <div v-if="error" class="text-red-500 text-sm">{{ error }}</div>
      <div class="flex gap-3 pt-2">
        <button @click="submit" :disabled="submitting"
          class="bg-amber-400 text-amber-900 font-bold px-6 py-2.5 rounded-lg hover:bg-amber-500 disabled:opacity-50">
          {{ submitting ? '등록 중...' : (extraPhotoCost > 0 ? `등록하기 (${extraPhotoCost}P 차감)` : '등록하기') }}
        </button>
        <button @click="$router.back()" class="text-gray-500 px-6 py-2.5">취소</button>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const route = useRoute()
const form = reactive({
  title: '', price: 0, category: 'electronics', condition: 'good', content: '',
  is_negotiable: false, hold_enabled: false, hold_price_per_6h: 100, hold_max_hours: 24,
})

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
      // 메인 사진을 첫 번째로, 나머지 순서대로
      if (photoList.value.length) {
        const ordered = [...photoList.value]
        // 메인을 첫 번째로 이동
        if (mainPhotoIdx.value > 0) {
          const [main] = ordered.splice(mainPhotoIdx.value, 1)
          ordered.unshift(main)
        }
        ordered.forEach(p => fd.append('images[]', p.file))
      }
      // 추가 사진 포인트 비용
      fd.append('extra_photo_cost', extraPhotoCost.value)

      const { data } = await axios.post('/api/market', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      router.push(`/market/${data.data.id}`)
    }
  } catch (e) { error.value = e.response?.data?.message || '등록 실패' }
  submitting.value = false
}

onMounted(async () => {
  if (route.query.edit) {
    editId.value = route.query.edit; isEdit.value = true
    try {
      const { data } = await axios.get(`/api/market/${editId.value}`)
      const m = data.data; Object.keys(form).forEach(k => { if (m[k] !== undefined) form[k] = m[k] })
    } catch {}
  }
})
</script>
