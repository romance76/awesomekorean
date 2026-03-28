<template>
  <div class="min-h-screen bg-gray-50 pb-16">
    <div class="max-w-[1200px] mx-auto px-4 pt-4">
      <!-- Header -->
      <div class="bg-gradient-to-r from-blue-600 to-blue-500 text-white px-6 py-5 rounded-2xl mb-4">
        <h1 class="text-xl font-black">🏠 부동산 매물 등록</h1>
        <p class="text-blue-100 text-sm mt-0.5">매물 정보를 입력해 주세요</p>
      </div>

      <div class="bg-white rounded-2xl shadow-sm p-5 space-y-5">
        <!-- 기본 정보 -->
        <h2 class="font-bold text-gray-800 text-sm border-b pb-2">기본 정보</h2>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">제목 <span class="text-red-400">*</span></label>
          <input v-model="form.title" type="text" placeholder="예: LA 한인타운 2BR 아파트 렌트" maxlength="200"
            class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400" />
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">매물 유형 <span class="text-red-400">*</span></label>
            <select v-model="form.type" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400">
              <option value="">선택</option>
              <option>렌트</option>
              <option>매매</option>
              <option>룸메이트</option>
              <option>상가</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">가격 ($) <span class="text-red-400">*</span></label>
            <input v-model="form.price" type="number" min="0" placeholder="월세 또는 매매가"
              class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400" />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">주소 <span class="text-red-400">*</span></label>
          <AddressInput v-model="form.addressObj" />
        </div>

        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">방 수</label>
            <input v-model="form.bedrooms" type="number" min="0" max="20" placeholder="0"
              class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">욕실 수</label>
            <input v-model="form.bathrooms" type="number" min="0" max="10" placeholder="0"
              class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">면적 (sqft)</label>
            <input v-model="form.sqft" type="number" min="0" placeholder="0"
              class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400" />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">상세 설명</label>
          <textarea v-model="form.description" rows="6" placeholder="매물에 대한 상세 정보를 입력해 주세요"
            class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400 resize-none" />
        </div>

        <!-- 사진 업로드 -->
        <h2 class="font-bold text-gray-800 text-sm border-b pb-2 pt-2">📷 사진 업로드</h2>
        <p class="text-xs text-gray-500">최대 10장 (처음 3장 무료, 추가 1장당 10P)</p>
        <div class="grid grid-cols-4 sm:grid-cols-5 lg:grid-cols-6 gap-2">
          <div v-for="i in 10" :key="i" class="relative">
            <div v-if="photosPreviews[i-1]" class="h-24 rounded-xl overflow-hidden relative group">
              <img :src="photosPreviews[i-1]" class="w-full h-full object-cover" />
              <button @click="removePhoto(i-1)" class="absolute top-1 right-1 w-6 h-6 bg-red-500 text-white rounded-full text-xs opacity-0 group-hover:opacity-100 transition">✕</button>
            </div>
            <label v-else-if="i <= 3" class="h-24 rounded-xl border-2 border-dashed border-gray-300 flex flex-col items-center justify-center cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition">
              <span class="text-2xl text-gray-400">+</span>
              <span class="text-[10px] text-gray-400 mt-0.5">무료</span>
              <input type="file" accept="image/*" class="hidden" @change="onPhotoSelect($event, i-1)" />
            </label>
            <label v-else class="h-24 rounded-xl border-2 border-dashed border-gray-200 flex flex-col items-center justify-center cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition bg-gray-50">
              <span class="text-xl text-gray-300">+</span>
              <span class="text-[10px] text-gray-400 mt-0.5">10P</span>
              <input type="file" accept="image/*" class="hidden" @change="onPhotoSelect($event, i-1)" />
            </label>
          </div>
        </div>

        <!-- 추가 정보 -->
        <h2 class="font-bold text-gray-800 text-sm border-b pb-2 pt-2">추가 정보</h2>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">디파짓 ($)</label>
            <input v-model="form.deposit" type="number" min="0" placeholder="보증금"
              class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">입주 가능일</label>
            <input v-model="form.move_in_date" type="date"
              class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400" />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">반려동물</label>
          <div class="flex gap-3">
            <button v-for="p in petOptions" :key="p.value" @click="form.pet_policy = p.value"
              class="flex-1 py-3 rounded-xl font-semibold text-sm transition"
              :class="form.pet_policy === p.value ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
              {{ p.label }}
            </button>
          </div>
        </div>

        <!-- 연락처 -->
        <h2 class="font-bold text-gray-800 text-sm border-b pb-2 pt-2">📞 연락처</h2>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">전화번호</label>
            <input v-model="form.phone" type="tel" placeholder="000-000-0000"
              class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">이메일</label>
            <input v-model="form.email" type="email" placeholder="email@example.com"
              class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400" />
          </div>
        </div>

        <!-- 에러 / 버튼 -->
        <div v-if="error" class="text-red-600 text-sm bg-red-50 p-3 rounded-xl">{{ error }}</div>
        <div class="flex justify-end gap-3 pt-2">
          <button @click="$router.back()" class="px-6 py-3 border border-gray-300 text-gray-600 rounded-xl text-sm hover:bg-gray-50">취소</button>
          <button @click="submit" :disabled="submitting"
            class="px-6 py-3 bg-blue-600 text-white rounded-xl text-sm font-bold hover:bg-blue-700 disabled:opacity-50 transition">
            {{ submitting ? '등록 중...' : '매물 등록' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import AddressInput from '../../components/AddressInput.vue'

const router = useRouter()
const submitting = ref(false)
const error = ref('')
const photosPreviews = ref([])
const photosFiles = ref([])

const petOptions = [
  { value: '가능', label: '🐶 가능' },
  { value: '불가', label: '🚫 불가' },
  { value: '협의', label: '💬 협의' },
]

const form = ref({
  title: '',
  type: '',
  price: '',
  address: '',
  addressObj: { address1: '', address2: '', city: '', state: '', zip: '', full: '' },
  bedrooms: '',
  bathrooms: '',
  sqft: '',
  description: '',
  deposit: '',
  move_in_date: '',
  pet_policy: '협의',
  phone: '',
  email: '',
})

function onPhotoSelect(e, idx) {
  const file = e.target.files[0]
  if (!file) return
  if (file.size > 5 * 1024 * 1024) { alert('5MB 이하 이미지만 가능합니다'); return }
  const reader = new FileReader()
  reader.onload = ev => { photosPreviews.value[idx] = ev.target.result }
  reader.readAsDataURL(file)
  photosFiles.value[idx] = file
}

function removePhoto(idx) {
  photosPreviews.value.splice(idx, 1)
  photosFiles.value.splice(idx, 1)
}

async function submit() {
  if (!form.value.title.trim()) { error.value = '제목을 입력하세요.'; return }
  if (!form.value.type) { error.value = '매물 유형을 선택하세요.'; return }
  if (!form.value.price && form.value.price !== 0) { error.value = '가격을 입력하세요.'; return }
  if (!form.value.addressObj.address1?.trim()) { error.value = '주소를 입력하세요.'; return }

  submitting.value = true
  error.value = ''
  try {
    const fd = new FormData()
    form.value.address = form.value.addressObj.full || '';
    Object.entries(form.value).forEach(([k, v]) => { if (k === 'addressObj') return; if (v !== '' && v !== null) fd.append(k, v) })
    photosFiles.value.forEach((file, i) => { if (file) fd.append(`photos[${i}]`, file) })

    const { data } = await axios.post('/api/realestate', fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    router.push(`/realestate/${data.listing?.id || data.id || ''}`)
  } catch (e) {
    error.value = e.response?.data?.message || '등록 실패. 다시 시도해 주세요.'
  } finally {
    submitting.value = false
  }
}
</script>
