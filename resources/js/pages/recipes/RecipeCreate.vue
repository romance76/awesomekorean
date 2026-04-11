<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-3xl mx-auto px-4 py-5">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-xl font-black text-gray-800">🍳 {{ isEdit ? '내 레시피 수정' : '내 레시피 등록' }}</h1>
      <button @click="$router.back()" class="text-sm text-gray-500 hover:text-amber-600">← 뒤로</button>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">
      <!-- 썸네일 이미지 업로드 -->
      <div>
        <label class="text-sm font-semibold text-gray-700">대표 이미지</label>
        <div class="mt-1 flex items-start gap-3">
          <div class="w-32 h-32 rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 flex items-center justify-center overflow-hidden flex-shrink-0">
            <img v-if="thumbnailPreview" :src="thumbnailPreview" class="w-full h-full object-cover" />
            <span v-else class="text-3xl text-gray-300">🍲</span>
          </div>
          <div class="flex-1">
            <label class="inline-block bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-lg text-xs hover:bg-amber-500 cursor-pointer">
              📷 이미지 선택
              <input type="file" accept="image/*" @change="onThumbnailSelect" class="hidden" />
            </label>
            <p class="text-[10px] text-gray-400 mt-1">10MB 이하 · JPG/PNG/WEBP</p>
            <p v-if="thumbnailFile" class="text-[10px] text-green-600 mt-1">✓ {{ thumbnailFile.name }} ({{ (thumbnailFile.size / 1024 / 1024).toFixed(1) }}MB)</p>
          </div>
        </div>
      </div>

      <!-- 제목 (한영) -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="text-sm font-semibold text-gray-700">제목 (한글) <span class="text-red-500">*</span></label>
          <input v-model="form.title" type="text" placeholder="예: 김치찌개"
            class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" />
        </div>
        <div>
          <label class="text-sm font-semibold text-gray-700">Title (English)</label>
          <input v-model="form.title_en" type="text" placeholder="Kimchi Jjigae"
            class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" />
        </div>
      </div>

      <!-- 카테고리/조리법/인분 -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
          <label class="text-sm font-semibold text-gray-700">카테고리</label>
          <select v-model="form.category" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none">
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
          <label class="text-sm font-semibold text-gray-700">조리법</label>
          <select v-model="form.cook_method" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none">
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
          <label class="text-sm font-semibold text-gray-700">분량</label>
          <input v-model="form.servings" type="text" placeholder="2~3인분"
            class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" />
        </div>
      </div>

      <!-- 재료 (구조화 입력) -->
      <div>
        <div class="flex items-center justify-between mb-2">
          <label class="text-sm font-semibold text-gray-700">재료 목록 <span class="text-red-500">*</span></label>
          <button @click="addIngredient" type="button" class="text-xs bg-amber-100 text-amber-700 px-3 py-1 rounded-full font-bold hover:bg-amber-200">+ 재료 추가</button>
        </div>
        <div class="space-y-2">
          <div v-for="(ing, idx) in structuredIngredients" :key="idx" class="flex gap-2 items-center">
            <input v-model="ing.name_ko" placeholder="한글 (예: 묵은 김치)"
              class="flex-1 border rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-amber-400" />
            <input v-model="ing.name_en" placeholder="English (e.g. Kimchi)"
              class="flex-1 border rounded-lg px-3 py-2 text-sm italic outline-none focus:ring-2 focus:ring-amber-400" />
            <input v-model="ing.amount" placeholder="분량 (2컵)"
              class="w-24 border rounded-lg px-3 py-2 text-sm text-right outline-none focus:ring-2 focus:ring-amber-400" />
            <button @click="removeIngredient(idx)" type="button" class="text-red-400 hover:text-red-600 text-sm w-6">✕</button>
          </div>
          <div v-if="!structuredIngredients.length" class="text-center py-3 text-xs text-gray-400 border-2 border-dashed rounded-lg">
            "+ 재료 추가" 버튼으로 재료를 추가하세요
          </div>
        </div>
      </div>

      <!-- 조리 순서 (한영) -->
      <div>
        <div class="flex items-center justify-between mb-2">
          <label class="text-sm font-semibold text-gray-700">조리 순서 <span class="text-red-500">*</span></label>
          <button @click="addStep" class="text-xs bg-amber-100 text-amber-700 px-3 py-1 rounded-full font-bold hover:bg-amber-200">+ 단계 추가</button>
        </div>
        <div class="space-y-3">
          <div v-for="(step, idx) in steps" :key="idx" class="border rounded-lg p-3 bg-gray-50/50 relative">
            <div class="flex items-start gap-2">
              <div class="flex-shrink-0 w-7 h-7 rounded-full bg-amber-400 text-amber-900 font-black text-xs flex items-center justify-center">{{ idx + 1 }}</div>
              <div class="flex-1 space-y-2">
                <textarea v-model="step.text" rows="2" placeholder="한글 설명 (예: 삼겹살을 냄비에 넣고 중불에서 기름이 나올 때까지 3~4분간 볶아주세요)"
                  class="w-full border rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-amber-400 resize-none"></textarea>
                <textarea v-model="step.text_en" rows="2" placeholder="English (e.g. Put the pork in a pot and stir-fry over medium heat for 3-4 minutes until fat renders)"
                  class="w-full border rounded-lg px-3 py-2 text-xs italic outline-none focus:ring-2 focus:ring-amber-400 resize-none"></textarea>
                <div class="flex items-center gap-2">
                  <label class="text-[10px] bg-white border rounded px-2 py-1 cursor-pointer hover:bg-amber-50">
                    📷 단계 이미지
                    <input type="file" accept="image/*" @change="e => onStepImageSelect(e, idx)" class="hidden" />
                  </label>
                  <img v-if="step.image_url" :src="step.image_url" class="h-12 rounded border" />
                  <span v-if="step.image_uploading" class="text-[10px] text-amber-600">업로드 중...</span>
                </div>
              </div>
              <button @click="removeStep(idx)" class="text-red-400 hover:text-red-600 text-xs flex-shrink-0">✕</button>
            </div>
          </div>
          <div v-if="!steps.length" class="text-center py-4 text-xs text-gray-400 border-2 border-dashed rounded-lg">
            단계를 추가해주세요. "+ 단계 추가" 버튼 클릭
          </div>
        </div>
      </div>

      <!-- 해시태그 -->
      <div>
        <label class="text-sm font-semibold text-gray-700">해시태그 (쉼표 구분)</label>
        <input v-model="form.hash_tags" type="text" placeholder="김치찌개, 집밥, 간단요리"
          class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" />
      </div>

      <div v-if="error" class="text-red-500 text-sm bg-red-50 border border-red-200 rounded-lg p-3">{{ error }}</div>

      <div class="flex gap-3 pt-2 border-t">
        <button @click="submit" :disabled="submitting"
          class="bg-amber-400 text-amber-900 font-bold px-6 py-2.5 rounded-lg hover:bg-amber-500 disabled:opacity-50">
          {{ submitting ? '저장 중...' : (isEdit ? '수정 완료' : '레시피 등록') }}
        </button>
        <button @click="$router.back()" class="text-gray-500 px-6 py-2.5">취소</button>
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
