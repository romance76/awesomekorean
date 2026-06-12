<template>
<div class="min-h-screen">
  <div class="max-w-3xl mx-auto px-4 py-5">
    <div class="flex items-center justify-between mb-4">
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
        <span class="icon-chip w-9 h-9 bg-orange-50 text-orange-600"><AppIcon name="utensils" :size="20" /></span>
        {{ isEdit ? '내 레시피 수정' : '내 레시피 등록' }}
      </h1>
      <button @click="$router.back()" class="btn-ghost text-sm"><AppIcon name="chevron-left" :size="14" /> 뒤로</button>
    </div>

    <div class="card p-5 space-y-4">
      <!-- 썸네일 이미지 업로드 -->
      <div>
        <label class="input-label">대표 이미지</label>
        <div class="mt-1 flex items-start gap-3">
          <div class="w-32 h-32 rounded-xl bg-[#F4F6F8] text-ink-muted flex flex-col items-center justify-center overflow-hidden flex-shrink-0">
            <img v-if="thumbnailPreview" :src="thumbnailPreview" class="w-full h-full object-cover" />
            <AppIcon v-else name="camera" :size="28" :stroke-width="1.5" />
          </div>
          <div class="flex-1">
            <label class="btn-primary text-xs cursor-pointer">
              <AppIcon name="camera" :size="14" /> 이미지 선택
              <input type="file" accept="image/*" @change="onThumbnailSelect" class="hidden" />
            </label>
            <p class="text-xs text-ink-muted mt-1">10MB 이하 · JPG/PNG/WEBP</p>
            <p v-if="thumbnailFile" class="text-xs text-green-600 mt-1 flex items-center gap-1"><AppIcon name="check" :size="12" /> {{ thumbnailFile.name }} ({{ (thumbnailFile.size / 1024 / 1024).toFixed(1) }}MB)</p>
          </div>
        </div>
      </div>

      <!-- 제목 (한영) -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="input-label">제목 (한글) <span class="text-red-500">*</span></label>
          <input v-model="form.title" type="text" placeholder="예: 김치찌개"
            class="input-soft px-3" />
        </div>
        <div>
          <label class="input-label">Title (English)</label>
          <input v-model="form.title_en" type="text" placeholder="Kimchi Jjigae"
            class="input-soft px-3" />
        </div>
      </div>

      <!-- 카테고리/조리법/인분 -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
          <label class="input-label">카테고리</label>
          <select v-model="form.category" class="input-soft px-3">
            <option value="">선택</option>
            <option value="반찬">반찬</option>
            <option value="국&찌개">국&찌개</option>
            <option value="밥">밥</option>
            <option value="일품">일품</option>
            <option value="후식">후식</option>
            <option value="기타">기타</option>
          </select>
        </div>
        <div>
          <label class="input-label">조리법</label>
          <select v-model="form.cook_method" class="input-soft px-3">
            <option value="">선택</option>
            <option value="끓이기">끓이기</option>
            <option value="볶기">볶기</option>
            <option value="찌기">찌기</option>
            <option value="굽기">굽기</option>
            <option value="튀기기">튀기기</option>
            <option value="무침">무침</option>
            <option value="부침">부침</option>
            <option value="기타">기타</option>
          </select>
        </div>
        <div>
          <label class="input-label">분량</label>
          <input v-model="form.servings" type="text" placeholder="2~3인분"
            class="input-soft px-3" />
        </div>
      </div>

      <!-- 재료 (구조화 입력) -->
      <div>
        <div class="flex items-center justify-between mb-2">
          <label class="input-label mb-0">재료 목록 <span class="text-red-500">*</span></label>
          <button @click="addIngredient" type="button" class="btn-soft px-3 py-1 text-xs rounded-full"><AppIcon name="plus" :size="12" /> 재료 추가</button>
        </div>
        <div class="space-y-2">
          <div v-for="(ing, idx) in structuredIngredients" :key="idx" class="flex gap-2 items-center">
            <input v-model="ing.name_ko" placeholder="한글 (예: 묵은 김치)"
              class="input-soft flex-1 w-auto px-3" />
            <input v-model="ing.name_en" placeholder="English (e.g. Kimchi)"
              class="input-soft flex-1 w-auto px-3 italic" />
            <input v-model="ing.amount" placeholder="분량 (2컵)"
              class="input-soft w-24 px-3 text-right" />
            <button @click="removeIngredient(idx)" type="button" class="text-red-400 hover:text-red-600 w-6 flex items-center justify-center transition-colors"><AppIcon name="x" :size="14" /></button>
          </div>
          <div v-if="!structuredIngredients.length" class="text-center py-3 text-xs text-ink-muted rounded-xl bg-[#F4F6F8]">
            "+ 재료 추가" 버튼으로 재료를 추가하세요
          </div>
        </div>
      </div>

      <!-- 조리 순서 (한영) -->
      <div>
        <div class="flex items-center justify-between mb-2">
          <label class="input-label mb-0">조리 순서 <span class="text-red-500">*</span></label>
          <button @click="addStep" class="btn-soft px-3 py-1 text-xs rounded-full"><AppIcon name="plus" :size="12" /> 단계 추가</button>
        </div>
        <div class="space-y-3">
          <div v-for="(step, idx) in steps" :key="idx" class="rounded-xl p-3 bg-[#F4F6F8] relative">
            <div class="flex items-start gap-2">
              <div class="flex-shrink-0 w-7 h-7 rounded-full bg-amber-400 text-white font-black text-xs flex items-center justify-center">{{ idx + 1 }}</div>
              <div class="flex-1 space-y-2">
                <textarea v-model="step.text" rows="2" placeholder="한글 설명 (예: 삼겹살을 냄비에 넣고 중불에서 기름이 나올 때까지 3~4분간 볶아주세요)"
                  class="input-soft bg-white px-3"></textarea>
                <textarea v-model="step.text_en" rows="2" placeholder="English (e.g. Put the pork in a pot and stir-fry over medium heat for 3-4 minutes until fat renders)"
                  class="input-soft bg-white px-3 text-xs italic"></textarea>
                <div class="flex items-center gap-2">
                  <label class="text-xs bg-white rounded-lg px-2 py-1 cursor-pointer hover:bg-amber-50 transition-colors inline-flex items-center gap-1 text-ink-light">
                    <AppIcon name="camera" :size="12" /> 단계 이미지
                    <input type="file" accept="image/*" @change="e => onStepImageSelect(e, idx)" class="hidden" />
                  </label>
                  <img v-if="step.image_url" :src="step.image_url" class="h-12 rounded-lg border border-gray-100" />
                  <span v-if="step.image_uploading" class="text-xs text-amber-600">업로드 중...</span>
                </div>
              </div>
              <button @click="removeStep(idx)" class="text-red-400 hover:text-red-600 flex-shrink-0 transition-colors"><AppIcon name="x" :size="14" /></button>
            </div>
          </div>
          <div v-if="!steps.length" class="text-center py-4 text-xs text-ink-muted rounded-xl bg-[#F4F6F8]">
            단계를 추가해주세요. "+ 단계 추가" 버튼 클릭
          </div>
        </div>
      </div>

      <!-- 해시태그 -->
      <div>
        <label class="input-label">해시태그 (쉼표 구분)</label>
        <input v-model="form.hash_tags" type="text" placeholder="김치찌개, 집밥, 간단요리"
          class="input-soft px-3" />
      </div>

      <div v-if="error" class="text-red-500 text-sm bg-red-50 border border-red-200 rounded-xl p-3">{{ error }}</div>

      <div class="flex gap-3 pt-2 border-t border-gray-100">
        <button @click="submit" :disabled="submitting"
          class="btn-primary px-6">
          {{ submitting ? '저장 중...' : (isEdit ? '수정 완료' : '레시피 등록') }}
        </button>
        <button @click="$router.back()" class="btn-secondary px-6">취소</button>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { compressImage } from '../../utils/imageCompress'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const route = useRoute()
const router = useRouter()

const isEdit = computed(() => !!route.params.id)

const form = reactive({
  title: '',
  title_en: '',
  category: '',
  cook_method: '',
  servings: '',
  hash_tags: '',
})

const structuredIngredients = ref([])
const steps = ref([])
const thumbnailFile = ref(null)
const thumbnailPreview = ref('')
const error = ref('')
const submitting = ref(false)

function addIngredient() {
  structuredIngredients.value.push({ name_ko: '', name_en: '', amount: '' })
}

function removeIngredient(idx) {
  structuredIngredients.value.splice(idx, 1)
}

function addStep() {
  steps.value.push({ order: steps.value.length + 1, text: '', text_en: '', image_url: null, image_file: null, image_uploading: false })
}

function removeStep(idx) {
  steps.value.splice(idx, 1)
  // order 재정렬
  steps.value.forEach((s, i) => { s.order = i + 1 })
}

async function onThumbnailSelect(e) {
  const file = e.target.files[0]
  if (!file) return
  try {
    const compressed = await compressImage(file, { maxDim: 1600, quality: 0.85 })
    thumbnailFile.value = compressed
    thumbnailPreview.value = URL.createObjectURL(compressed)
  } catch {}
  e.target.value = ''
}

async function onStepImageSelect(e, idx) {
  const file = e.target.files[0]
  if (!file) return
  steps.value[idx].image_uploading = true
  try {
    const compressed = await compressImage(file, { maxDim: 1200, quality: 0.82 })
    // 곧바로 서버에 업로드는 하지 않고, 데이터 URL로 미리 저장 후 submit 때 함께 전송
    const reader = new FileReader()
    reader.onload = () => {
      steps.value[idx].image_data = reader.result
      steps.value[idx].image_url = reader.result // 미리보기용
      steps.value[idx].image_uploading = false
    }
    reader.readAsDataURL(compressed)
  } catch {
    steps.value[idx].image_uploading = false
  }
  e.target.value = ''
}

async function submit() {
  error.value = ''
  if (!form.title.trim()) { error.value = '제목을 입력해주세요'; return }
  const validIngredients = structuredIngredients.value.filter(i => (i.name_ko || '').trim())
  if (!validIngredients.length) { error.value = '재료를 최소 1개 이상 입력해주세요'; return }
  if (!steps.value.length || !steps.value.some(s => s.text.trim())) { error.value = '조리 순서를 최소 1단계 이상 입력해주세요'; return }

  // 구조화 재료 → plain text 자동 생성
  const ingredientsText = validIngredients.map(i => {
    const parts = [i.name_ko.trim()]
    if (i.amount) parts.push(i.amount.trim())
    return parts.join(' ')
  }).join(', ')
  const ingredientsEnText = validIngredients.filter(i => i.name_en).map(i => {
    const parts = [i.name_en.trim()]
    if (i.amount) parts.push(i.amount.trim())
    return parts.join(' ')
  }).join(', ')

  submitting.value = true
  try {
    const fd = new FormData()
    fd.append('title', form.title)
    if (form.title_en) fd.append('title_en', form.title_en)
    if (form.category) fd.append('category', form.category)
    if (form.cook_method) fd.append('cook_method', form.cook_method)
    if (form.servings) fd.append('servings', form.servings)
    fd.append('ingredients', ingredientsText)
    if (ingredientsEnText) fd.append('ingredients_en', ingredientsEnText)
    fd.append('ingredients_structured', JSON.stringify(validIngredients))
    if (form.hash_tags) fd.append('hash_tags', form.hash_tags)
    if (thumbnailFile.value) fd.append('thumbnail_file', thumbnailFile.value)

    // steps → JSON (이미지는 데이터 URL 포함)
    const stepsPayload = steps.value
      .filter(s => s.text.trim())
      .map((s, i) => ({
        order: i + 1,
        text: s.text,
        text_en: s.text_en || null,
        image_url: s.image_data || s.image_url || null,
      }))
    fd.append('steps', JSON.stringify(stepsPayload))

    const url = isEdit.value ? `/api/recipes/${route.params.id}` : '/api/recipes'
    const method = isEdit.value ? 'post' : 'post' // 수정도 POST로 (Laravel form method spoofing)
    if (isEdit.value) fd.append('_method', 'PUT')

    const { data } = await axios.post(url, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    const newId = data.data?.id || route.params.id
    router.push(`/recipes/${newId}`)
  } catch (e) {
    error.value = e.response?.data?.message || e.response?.data?.errors?.title?.[0] || '저장 실패'
  }
  submitting.value = false
}

async function loadExisting() {
  if (!isEdit.value) return
  try {
    const { data } = await axios.get(`/api/recipes/${route.params.id}`)
    const r = data.data
    form.title = r.title || ''
    form.title_en = r.title_en || ''
    form.category = r.category || ''
    form.cook_method = r.cook_method || ''
    form.servings = r.servings || ''
    form.hash_tags = r.hash_tags || ''
    if (r.thumbnail) thumbnailPreview.value = r.thumbnail

    // 구조화 재료가 있으면 복원, 없으면 빈 행 하나
    if (r.ingredients_structured && r.ingredients_structured.length) {
      structuredIngredients.value = r.ingredients_structured.map(i => ({
        name_ko: i.name_ko || i.name || '',
        name_en: i.name_en || '',
        amount: i.amount || '',
      }))
    } else {
      structuredIngredients.value = [{ name_ko: '', name_en: '', amount: '' }]
    }

    steps.value = (r.steps || []).map((s, i) => ({
      order: s.order || i + 1,
      text: s.text || '',
      text_en: s.text_en || '',
      image_url: s.image_url || null,
      image_data: null,
    }))
  } catch {}
}

onMounted(() => {
  if (isEdit.value) {
    loadExisting()
  } else {
    addIngredient()
    addStep()
  }
})
</script>
