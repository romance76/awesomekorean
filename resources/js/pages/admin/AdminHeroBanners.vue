<template>
<div>
  <h1 class="text-xl font-black text-gray-800 mb-4">🎪 히어로 배너 관리</h1>
  <div class="bg-white rounded-xl shadow-sm border overflow-hidden mb-4">
    <div class="px-4 py-3 border-b flex items-center justify-between">
      <span class="font-bold text-sm text-gray-800">메인 홈 상단 슬라이드 배너</span>
      <button @click="openForm()" class="bg-amber-400 text-amber-900 font-bold px-3 py-1 rounded text-xs">+ 추가</button>
    </div>

    <!-- 추가/수정 폼 -->
    <div v-if="showForm" class="px-4 py-3 border-b bg-amber-50 space-y-3">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
        <div>
          <label class="text-[11px] font-bold text-gray-600 block mb-0.5">제목</label>
          <input v-model="form.title" placeholder="예: 🐛 버그를 잡아라!" class="w-full border rounded px-2 py-1 text-sm" />
        </div>
        <div>
          <label class="text-[11px] font-bold text-gray-600 block mb-0.5">소제목</label>
          <input v-model="form.subtitle" placeholder="부제목 (선택)" class="w-full border rounded px-2 py-1 text-sm" />
        </div>
      </div>

      <!-- 이미지 업로드 -->
      <div>
        <label class="text-[11px] font-bold text-gray-600 block mb-0.5">배경 이미지 (선택, 이미지가 있으면 텍스트 대신 이미지 전체가 표시됨)</label>
        <input type="file" accept="image/*" @change="onFile" class="text-xs" />
        <div v-if="pickedFile" class="text-[11px] text-green-600 mt-1">선택됨: {{ pickedFile.name }}</div>
        <div v-else-if="form.image_url" class="mt-1">
          <img :src="form.image_url" class="max-h-20 rounded border" />
          <button type="button" @click="clearImage" class="ml-2 text-[11px] text-red-500">이미지 제거</button>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
        <div>
          <label class="text-[11px] font-bold text-gray-600 block mb-0.5">배경색 (이미지 없을 때)</label>
          <input v-model="form.bg_color" placeholder="#F5A623" class="w-full border rounded px-2 py-1 text-sm" />
        </div>
        <div>
          <label class="text-[11px] font-bold text-gray-600 block mb-0.5">글자색</label>
          <input v-model="form.text_color" placeholder="#FFFFFF" class="w-full border rounded px-2 py-1 text-sm" />
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
        <div>
          <label class="text-[11px] font-bold text-gray-600 block mb-0.5">클릭 링크 타입</label>
          <select v-model="form.link_type" class="w-full border rounded px-2 py-1 text-sm">
            <option value="none">클릭 없음</option>
            <option value="event">이벤트 연결</option>
            <option value="page">페이지 이동</option>
            <option value="url">외부 URL</option>
          </select>
        </div>
        <div v-if="form.link_type === 'event'">
          <label class="text-[11px] font-bold text-gray-600 block mb-0.5">이벤트 ID</label>
          <input v-model.number="form.event_id" type="number" class="w-full border rounded px-2 py-1 text-sm" />
        </div>
        <div v-if="form.link_type === 'page'">
          <label class="text-[11px] font-bold text-gray-600 block mb-0.5">페이지 경로</label>
          <input v-model="form.link_page" placeholder="/music, /chat 등" class="w-full border rounded px-2 py-1 text-sm" />
        </div>
        <div v-if="form.link_type === 'url'">
          <label class="text-[11px] font-bold text-gray-600 block mb-0.5">외부 URL</label>
          <input v-model="form.link_url" placeholder="https://..." class="w-full border rounded px-2 py-1 text-sm" />
        </div>
        <div>
          <label class="text-[11px] font-bold text-gray-600 block mb-0.5">순서</label>
          <input v-model.number="form.sort_order" type="number" class="w-full border rounded px-2 py-1 text-sm" />
        </div>
        <div class="flex items-end">
          <label class="flex items-center gap-2 text-sm">
            <input v-model="form.is_active" type="checkbox" class="accent-amber-500 w-4 h-4" />
            활성화
          </label>
        </div>
      </div>

      <div class="flex gap-2 pt-1">
        <button @click="saveForm" :disabled="saving" class="bg-amber-500 text-white font-bold px-4 py-1.5 rounded text-xs disabled:opacity-50">
          {{ saving ? '저장 중...' : (editId ? '수정' : '등록') }}
        </button>
        <button @click="resetForm" class="text-gray-500 text-xs px-3">취소</button>
      </div>
    </div>

    <!-- 배너 목록 -->
    <div v-for="b in banners" :key="b.id" class="px-4 py-3 border-b flex items-center gap-3">
      <div class="w-12 h-12 rounded-lg flex items-center justify-center flex-shrink-0 overflow-hidden"
        :style="{ backgroundColor: b.bg_color || '#F5A623' }">
        <img v-if="b.image_url" :src="b.image_url" class="w-full h-full object-cover" />
        <span v-else class="text-white text-xs font-bold">{{ b.sort_order }}</span>
      </div>
      <div class="flex-1 min-w-0">
        <div class="text-sm font-bold text-gray-800 truncate">{{ b.title }}</div>
        <div class="text-[10px] text-gray-400 truncate">
          {{ b.image_url ? '🖼️ 이미지' : '📝 텍스트' }}
          <span v-if="b.subtitle"> · {{ b.subtitle }}</span>
          <span v-if="b.link_type && b.link_type !== 'none'"> · {{ b.link_type }}{{ b.event_id ? ' #' + b.event_id : '' }}{{ b.link_page || '' }}</span>
        </div>
      </div>
      <label class="flex items-center gap-1 text-xs">
        <input type="checkbox" :checked="b.is_active" @change="toggleActive(b)" class="accent-amber-500" /> 활성
      </label>
      <button @click="editBanner(b)" class="text-xs text-amber-600">수정</button>
      <button @click="deleteBanner(b)" class="text-xs text-red-400">삭제</button>
    </div>
    <div v-if="!banners.length" class="px-4 py-6 text-center text-sm text-gray-400">배너 없음</div>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const banners = ref([])
const showForm = ref(false)
const editId = ref(null)
const saving = ref(false)
const pickedFile = ref(null)

const DEFAULT_FORM = {
  title: '', subtitle: '',
  image_url: '',
  bg_color: '#F5A623', text_color: '#FFFFFF',
  link_type: 'none', event_id: null, link_page: '', link_url: '',
  sort_order: 0, is_active: true,
}

const form = ref({ ...DEFAULT_FORM })

async function load() {
  try { const { data } = await axios.get('/api/admin/hero-banners'); banners.value = data.data || [] } catch {}
}

function openForm() {
  resetForm()
  showForm.value = true
}

function onFile(e) {
  pickedFile.value = e.target.files?.[0] || null
}

function clearImage() {
  form.value.image_url = ''
  pickedFile.value = null
}

async function saveForm() {
  if (!form.value.title) { alert('제목을 입력하세요'); return }
  saving.value = true
  try {
    const fd = new FormData()
    Object.entries(form.value).forEach(([k, v]) => {
      if (v === null || v === undefined) return
      if (typeof v === 'boolean') fd.append(k, v ? '1' : '0')
      else fd.append(k, v)
    })
    if (pickedFile.value) fd.append('image', pickedFile.value)
    const url = editId.value ? `/api/admin/hero-banners/${editId.value}` : '/api/admin/hero-banners'
    await axios.post(url, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    resetForm()
    await load()
  } catch (err) {
    alert('저장 실패: ' + (err.response?.data?.message || err.message))
  } finally {
    saving.value = false
  }
}

function editBanner(b) {
  editId.value = b.id
  form.value = { ...DEFAULT_FORM, ...b }
  pickedFile.value = null
  showForm.value = true
}

function resetForm() {
  editId.value = null
  form.value = { ...DEFAULT_FORM }
  pickedFile.value = null
  showForm.value = false
}

async function toggleActive(b) {
  try {
    const fd = new FormData()
    fd.append('title', b.title)
    fd.append('is_active', b.is_active ? '0' : '1')
    await axios.post(`/api/admin/hero-banners/${b.id}`, fd)
    b.is_active = !b.is_active
  } catch {}
}

async function deleteBanner(b) {
  if (!confirm(`'${b.title}' 삭제?`)) return
  try {
    await axios.delete(`/api/admin/hero-banners/${b.id}`)
    banners.value = banners.value.filter(x => x.id !== b.id)
  } catch {}
}

onMounted(load)
</script>
