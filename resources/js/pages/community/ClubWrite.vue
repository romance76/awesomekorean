<template>
<div class="min-h-screen">
  <div class="max-w-3xl mx-auto px-4 py-6 space-y-5">
    <!-- Header -->
    <div class="flex items-center gap-3">
      <button @click="$router.back()" class="w-9 h-9 flex items-center justify-center rounded-full bg-white shadow-card text-ink-muted hover:bg-gray-50 transition-colors">
        <AppIcon name="chevron-left" :size="20" />
      </button>
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
        <span class="icon-chip w-9 h-9 bg-teal-50 text-teal-600"><AppIcon name="users" :size="20" /></span>
        {{ isEdit ? '동호회 수정' : '동호회 만들기' }}
      </h1>
    </div>

    <!-- Section 1: 기본 정보 -->
    <section class="card overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-100 bg-amber-50">
        <h2 class="text-sm font-bold text-amber-800">기본 정보</h2>
      </div>
      <div class="p-5 space-y-4">
        <!-- 이름 -->
        <div>
          <label class="input-label">
            동호회 이름 <span class="text-red-400">*</span>
          </label>
          <input v-model="form.name" type="text" placeholder="예: LA 한인 등산 모임"
            class="input-soft" />
        </div>

        <!-- 카테고리 -->
        <div>
          <label class="input-label">
            카테고리 <span class="text-red-400">*</span>
          </label>
          <select v-model="form.category"
            class="input-soft">
            <option value="" disabled>카테고리를 선택하세요</option>
            <option v-for="c in categories" :key="c.value" :value="c.value">{{ c.label }}</option>
          </select>
        </div>

        <!-- 활동 유형 -->
        <div>
          <label class="input-label mb-2">활동 유형</label>
          <div class="flex gap-3">
            <button type="button" @click="form.type = 'online'"
              class="flex-1 py-3 rounded-xl font-bold text-sm transition-all border-2 flex items-center justify-center gap-2"
              :class="form.type === 'online'
                ? 'bg-blue-500 border-blue-500 text-white shadow-md shadow-blue-200'
                : 'bg-white border-gray-200 text-ink-muted hover:border-blue-300'">
              <AppIcon name="globe" :size="18" />
              온라인
            </button>
            <button type="button" @click="form.type = 'local'"
              class="flex-1 py-3 rounded-xl font-bold text-sm transition-all border-2 flex items-center justify-center gap-2"
              :class="form.type === 'local'
                ? 'bg-green-500 border-green-500 text-white shadow-md shadow-green-200'
                : 'bg-white border-gray-200 text-ink-muted hover:border-green-300'">
              <AppIcon name="map-pin" :size="18" />
              지역 모임
            </button>
          </div>
        </div>

        <!-- 공개 여부 -->
        <div class="flex items-center gap-3 pt-1">
          <label class="relative inline-flex items-center cursor-pointer">
            <input v-model="form.is_public" type="checkbox" class="sr-only peer" />
            <div class="w-10 h-5 bg-gray-200 peer-focus:ring-2 peer-focus:ring-amber-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-amber-400"></div>
          </label>
          <span class="text-sm font-semibold text-ink-light">공개 동호회</span>
          <span class="text-xs text-ink-muted">{{ form.is_public ? '누구나 자유롭게 가입할 수 있습니다' : '가입 승인이 필요합니다' }}</span>
        </div>

        <!-- 최대 인원 -->
        <div>
          <label class="input-label">최대 인원</label>
          <div class="flex items-center gap-3">
            <input v-model.number="form.max_members" type="number" min="0" placeholder="0"
              class="input-soft w-32" />
            <span class="text-xs text-ink-muted">0 = 무제한</span>
          </div>
        </div>
      </div>
    </section>

    <!-- Section 2: 위치 (local only) -->
    <section v-if="form.type === 'local'" class="card overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-100 bg-green-50">
        <h2 class="text-sm font-bold text-green-800">활동 위치</h2>
      </div>
      <div class="p-5">
        <div class="grid grid-cols-3 gap-3">
          <div>
            <label class="text-xs text-ink-muted block mb-1">City</label>
            <input v-model="form.city" type="text" placeholder="예: Los Angeles"
              class="input-soft px-3" />
          </div>
          <div>
            <label class="text-xs text-ink-muted block mb-1">State</label>
            <input v-model="form.state" type="text" placeholder="예: CA"
              class="input-soft px-3" />
          </div>
          <div>
            <label class="text-xs text-ink-muted block mb-1">Zip Code</label>
            <input v-model="form.zipcode" type="text" placeholder="예: 90001"
              class="input-soft px-3" />
          </div>
        </div>
      </div>
    </section>

    <!-- Section 3: 소개 & 규칙 -->
    <section class="card overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-100 bg-amber-50">
        <h2 class="text-sm font-bold text-amber-800">소개 & 규칙</h2>
      </div>
      <div class="p-5 space-y-4">
        <div>
          <label class="input-label">동호회 소개</label>
          <textarea v-model="form.description" rows="4"
            placeholder="동호회의 활동 내용, 모임 주기, 목표 등을 자유롭게 작성해주세요"
            class="input-soft py-3"></textarea>
          <p class="text-xs text-ink-muted mt-1 text-right">{{ (form.description || '').length }}자</p>
        </div>
        <div>
          <label class="input-label">동호회 규칙 (선택)</label>
          <textarea v-model="form.rules" rows="4"
            placeholder="예:&#10;1. 서로 존중하기&#10;2. 개인정보 보호&#10;3. 정기 모임 참석 필수"
            class="input-soft py-3"></textarea>
        </div>
      </div>
    </section>

    <!-- Section 4: 이미지 -->
    <section class="card overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-100 bg-amber-50">
        <h2 class="text-sm font-bold text-amber-800">이미지</h2>
      </div>
      <div class="p-5 space-y-4">
        <!-- 대표 이미지 -->
        <div>
          <label class="input-label mb-2">대표 이미지 (프로필)</label>
          <div class="flex items-center gap-4">
            <div class="w-24 h-24 rounded-xl bg-[#F4F6F8] text-ink-muted overflow-hidden flex flex-col items-center justify-center"
              :class="{ 'ring-2 ring-amber-400': imagePreview }">
              <img v-if="imagePreview" :src="imagePreview" class="w-full h-full object-cover" />
              <AppIcon v-else name="camera" :size="26" :stroke-width="1.5" />
            </div>
            <div class="flex-1">
              <label class="btn-soft cursor-pointer">
                <AppIcon name="image" :size="16" />
                사진 선택
                <input type="file" accept="image/*" @change="onImageSelect" class="hidden" />
              </label>
              <p class="text-xs text-ink-muted mt-1">권장: 정사각형 비율 (200x200px 이상)</p>
            </div>
          </div>
        </div>

        <!-- 커버 이미지 -->
        <div>
          <label class="input-label mb-2">커버 이미지 (배너)</label>
          <div class="relative w-full h-32 rounded-xl bg-[#F4F6F8] text-ink-muted overflow-hidden flex flex-col items-center justify-center cursor-pointer hover:bg-amber-50 transition-colors"
            :class="{ 'ring-2 ring-amber-400': coverPreview }"
            @click="$refs.coverInput.click()">
            <img v-if="coverPreview" :src="coverPreview" class="w-full h-full object-cover" />
            <div v-else class="text-center flex flex-col items-center">
              <AppIcon name="camera" :size="26" :stroke-width="1.5" />
              <span class="text-xs mt-1">클릭하여 커버 이미지 업로드</span>
            </div>
          </div>
          <input ref="coverInput" type="file" accept="image/*" @change="onCoverSelect" class="hidden" />
          <p class="text-xs text-ink-muted mt-1">권장: 가로 비율 (1200x300px 이상)</p>
        </div>
      </div>
    </section>

    <!-- Error -->
    <div v-if="error" class="bg-red-50 border border-red-200 text-red-600 rounded-xl px-5 py-3 text-sm font-medium">
      {{ error }}
    </div>

    <!-- Actions -->
    <div class="flex gap-3 pb-10">
      <button @click="submit" :disabled="submitting"
        class="btn-primary flex-1 sm:flex-none px-8 py-3">
        {{ submitting ? '저장 중...' : (isEdit ? '수정하기' : '만들기') }}
      </button>
      <button @click="$router.back()"
        class="btn-secondary px-6 py-3">
        취소
      </button>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const form = reactive({
  name: '',
  description: '',
  rules: '',
  category: '',
  type: 'local',
  city: '',
  state: '',
  zipcode: '',
  max_members: 0,
  is_public: true,
})

const categories = [
  { value: '등산', label: '🥾 등산' },
  { value: '스포츠', label: '⚽ 스포츠' },
  { value: '요리', label: '🍳 요리' },
  { value: '게임', label: '🎮 게임' },
  { value: '여행', label: '✈️ 여행' },
  { value: '온라인', label: '🌐 온라인' },
  { value: '기타', label: '📋 기타' },
]

const imageFile = ref(null)
const imagePreview = ref(null)
const coverFile = ref(null)
const coverPreview = ref(null)

const error = ref('')
const submitting = ref(false)
const isEdit = ref(false)
const editId = ref(null)

function onImageSelect(e) {
  const file = e.target.files[0]
  if (!file) return
  imageFile.value = file
  const reader = new FileReader()
  reader.onload = ev => { imagePreview.value = ev.target.result }
  reader.readAsDataURL(file)
}

function onCoverSelect(e) {
  const file = e.target.files[0]
  if (!file) return
  coverFile.value = file
  const reader = new FileReader()
  reader.onload = ev => { coverPreview.value = ev.target.result }
  reader.readAsDataURL(file)
}

async function submit() {
  if (!form.name.trim()) { error.value = '동호회 이름을 입력해주세요'; return }
  if (!form.category) { error.value = '카테고리를 선택해주세요'; return }

  submitting.value = true
  error.value = ''

  try {
    const fd = new FormData()
    fd.append('name', form.name)
    fd.append('description', form.description || '')
    fd.append('rules', form.rules || '')
    fd.append('category', form.category)
    fd.append('type', form.type)
    fd.append('city', form.city || '')
    fd.append('state', form.state || '')
    fd.append('zipcode', form.zipcode || '')
    fd.append('max_members', form.max_members || 0)
    fd.append('is_public', form.is_public ? '1' : '0')

    if (imageFile.value) fd.append('image', imageFile.value)
    if (coverFile.value) fd.append('cover_image', coverFile.value)

    if (isEdit.value) {
      fd.append('_method', 'PUT')
      const { data } = await axios.post(`/api/clubs/${editId.value}`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
      router.push(`/clubs/${editId.value}`)
    } else {
      const { data } = await axios.post('/api/clubs', fd, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
      const newId = data.data?.id || data.id
      router.push(`/clubs/${newId}`)
    }
  } catch (e) {
    const msg = e.response?.data?.message || ''
    const errs = e.response?.data?.errors ? Object.values(e.response.data.errors).flat().join(', ') : ''
    error.value = msg || errs || '저장에 실패했습니다. 다시 시도해주세요.'
  }
  submitting.value = false
}

onMounted(async () => {
  // Edit mode: /clubs/:id/edit
  if (route.params.id) {
    editId.value = route.params.id
    isEdit.value = true
    try {
      const { data } = await axios.get(`/api/clubs/${editId.value}`)
      const c = data.data
      Object.keys(form).forEach(k => {
        if (c[k] !== undefined && c[k] !== null) form[k] = c[k]
      })
      // Boolean cast
      form.is_public = !!c.is_public
      // Existing images
      if (c.image) imagePreview.value = c.image.startsWith('http') ? c.image : `/storage/${c.image}`
      if (c.cover_image) coverPreview.value = c.cover_image.startsWith('http') ? c.cover_image : `/storage/${c.cover_image}`
    } catch {}
  } else {
    // Auto-fill location from user profile
    if (auth.user) {
      if (auth.user.city) form.city = auth.user.city
      if (auth.user.state) form.state = auth.user.state
      if (auth.user.zipcode) form.zipcode = auth.user.zipcode
    }
  }
})
</script>
