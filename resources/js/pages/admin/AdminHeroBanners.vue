<template>
<div>
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-4">
    <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="image" :size="20" /></span>
    히어로 배너 관리
  </h1>
  <div class="card overflow-hidden mb-4">
    <div class="px-4 py-3 border-b border-gray-50 flex items-center justify-between">
      <span class="font-bold text-sm text-ink">메인 홈 상단 슬라이드 배너</span>
      <button @click="openForm()" class="btn-primary !px-3 !py-1 text-xs"><AppIcon name="plus" :size="13" /> 추가</button>
    </div>

    <!-- 추가/수정 폼 -->
    <div v-if="showForm" class="px-4 py-3 border-b border-gray-50 bg-amber-50 space-y-3">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
        <div>
          <label class="input-label">제목</label>
          <input v-model="form.title" placeholder="예: 🐛 버그를 잡아라!" class="input-soft !px-3 !py-1.5 text-sm" />
        </div>
        <div>
          <label class="input-label">소제목</label>
          <input v-model="form.subtitle" placeholder="부제목 (선택)" class="input-soft !px-3 !py-1.5 text-sm" />
        </div>
      </div>

      <!-- 이미지 업로드 -->
      <div>
        <label class="input-label">배경 이미지 (선택, 이미지가 있으면 텍스트 대신 이미지 전체가 표시됨)</label>
        <input type="file" accept="image/*" @change="onFile" class="text-xs" />
        <div v-if="pickedFile" class="text-[11px] text-green-600 mt-1">선택됨: {{ pickedFile.name }}</div>
        <div v-else-if="form.image_url" class="mt-1">
          <img :src="form.image_url" class="max-h-20 rounded-lg border border-gray-200" />
          <button type="button" @click="clearImage" class="ml-2 text-[11px] text-red-500 hover:text-red-600 transition-colors">이미지 제거</button>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
        <div>
          <label class="input-label">배경색 (이미지 없을 때)</label>
          <input v-model="form.bg_color" placeholder="#F5A623" class="input-soft !px-3 !py-1.5 text-sm" />
        </div>
        <div>
          <label class="input-label">글자색</label>
          <input v-model="form.text_color" placeholder="#FFFFFF" class="input-soft !px-3 !py-1.5 text-sm" />
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
        <div>
          <label class="input-label">클릭 링크 타입</label>
          <select v-model="form.link_type" class="input-soft !px-3 !py-1.5 text-sm">
            <option value="none">클릭 없음</option>
            <option value="event">이벤트 연결</option>
            <option value="page">페이지 이동</option>
            <option value="url">외부 URL</option>
          </select>
        </div>
        <div v-if="form.link_type === 'event'">
          <label class="input-label">이벤트 ID</label>
          <input v-model.number="form.event_id" type="number" class="input-soft !px-3 !py-1.5 text-sm" />
        </div>
        <div v-if="form.link_type === 'page'">
          <label class="input-label">페이지 경로</label>
          <input v-model="form.link_page" placeholder="/music, /chat 등" class="input-soft !px-3 !py-1.5 text-sm" />
        </div>
        <div v-if="form.link_type === 'url'">
          <label class="input-label">외부 URL</label>
          <input v-model="form.link_url" placeholder="https://..." class="input-soft !px-3 !py-1.5 text-sm" />
        </div>
        <div>
          <label class="input-label">순서</label>
          <input v-model.number="form.sort_order" type="number" class="input-soft !px-3 !py-1.5 text-sm" />
        </div>
        <div class="flex items-end">
          <label class="flex items-center gap-2 text-sm text-ink-light">
            <input v-model="form.is_active" type="checkbox" class="accent-amber-500 w-4 h-4" />
            활성화
          </label>
        </div>
      </div>

      <div class="flex gap-2 pt-1">
        <button @click="saveForm" :disabled="saving" class="btn-primary !px-4 !py-1.5 text-xs">
          {{ saving ? '저장 중...' : (editId ? '수정' : '등록') }}
        </button>
        <button @click="resetForm" class="btn-ghost !px-3 !py-1.5 text-xs">취소</button>
      </div>
    </div>

    <!-- 배너 목록 -->
    <div v-for="b in banners" :key="b.id" class="px-4 py-3 border-b border-gray-50 flex items-center gap-3 hover:bg-amber-50/40 transition-colors">
      <div class="w-12 h-12 rounded-lg flex items-center justify-center flex-shrink-0 overflow-hidden"
        :style="{ backgroundColor: b.bg_color || '#F5A623' }">
        <img v-if="b.image_url" :src="b.image_url" class="w-full h-full object-cover" />
        <span v-else class="text-white text-xs font-bold">{{ b.sort_order }}</span>
      </div>
      <div class="flex-1 min-w-0">
        <div class="text-sm font-bold text-ink truncate">{{ b.title }}</div>
        <div class="text-[11px] text-ink-muted truncate">
          <span class="inline-flex items-center gap-0.5"><AppIcon :name="b.image_url ? 'image' : 'edit'" :size="10" />{{ b.image_url ? '이미지' : '텍스트' }}</span>
          <span v-if="b.subtitle"> · {{ b.subtitle }}</span>
          <span v-if="b.link_type && b.link_type !== 'none'"> · {{ b.link_type }}{{ b.event_id ? ' #' + b.event_id : '' }}{{ b.link_page || '' }}</span>
        </div>
      </div>
      <label class="flex items-center gap-1 text-xs text-ink-light">
        <input type="checkbox" :checked="b.is_active" @change="toggleActive(b)" class="accent-amber-500" /> 활성
      </label>
      <button @click="editBanner(b)" class="text-xs text-amber-600 hover:text-amber-700 transition-colors">수정</button>
      <button @click="deleteBanner(b)" class="text-xs text-red-400 hover:text-red-600 transition-colors">삭제</button>
    </div>
    <div v-if="!banners.length" class="py-16 text-center">
      <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="image" :size="28" :stroke-width="1.5" /></div>
      <p class="text-sm text-ink-muted">배너 없음</p>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

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
