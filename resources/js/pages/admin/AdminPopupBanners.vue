<template>
<div>
  <h1 class="text-xl font-black text-gray-800 mb-4">💬 팝업 배너 관리</h1>
  <div class="bg-white rounded-xl shadow-sm border overflow-hidden mb-4">
    <div class="px-4 py-3 border-b flex items-center justify-between">
      <div>
        <div class="font-bold text-sm text-gray-800">메인 팝업 배너</div>
        <div class="text-[11px] text-gray-500 mt-0.5">사이트 접속 시 띄우는 팝업 (이미지 또는 텍스트)</div>
      </div>
      <button @click="openForm()" class="bg-amber-400 text-amber-900 font-bold px-3 py-1 rounded text-xs">+ 새 팝업</button>
    </div>

    <!-- 추가/수정 폼 -->
    <div v-if="showForm" class="px-4 py-4 border-b bg-amber-50 space-y-3">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
        <div>
          <label class="text-[11px] font-bold text-gray-600 block mb-0.5">관리용 제목 <span class="text-red-500">*</span></label>
          <input v-model="form.title" placeholder="예: 신년 이벤트 공지" class="w-full border rounded px-2 py-1 text-sm" />
        </div>
        <div>
          <label class="text-[11px] font-bold text-gray-600 block mb-0.5">타입</label>
          <select v-model="form.type" class="w-full border rounded px-2 py-1 text-sm">
            <option value="text">📝 텍스트</option>
            <option value="image">🖼️ 이미지</option>
          </select>
        </div>
      </div>

      <!-- 이미지 타입 -->
      <div v-if="form.type === 'image'" class="space-y-2">
        <label class="text-[11px] font-bold text-gray-600 block">이미지 업로드</label>
        <input type="file" accept="image/*" @change="onFile" class="text-xs" />
        <div v-if="form.image_url && !pickedFile" class="mt-1">
          <img :src="form.image_url" class="max-h-32 rounded border" />
        </div>
        <div v-if="pickedFile" class="text-[11px] text-green-600">선택됨: {{ pickedFile.name }}</div>
      </div>

      <!-- 텍스트 타입 -->
      <div v-else class="space-y-2">
        <div>
          <label class="text-[11px] font-bold text-gray-600 block mb-0.5">본문 (HTML 허용)</label>
          <textarea v-model="form.content" rows="5" placeholder="예: &lt;h2&gt;공지사항&lt;/h2&gt;&lt;p&gt;내용...&lt;/p&gt;" class="w-full border rounded px-2 py-1 text-sm font-mono"></textarea>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <div>
            <label class="text-[11px] font-bold text-gray-600 block mb-0.5">가로 (px)</label>
            <input v-model.number="form.width" type="number" min="200" max="1200" class="w-full border rounded px-2 py-1 text-sm" />
          </div>
          <div>
            <label class="text-[11px] font-bold text-gray-600 block mb-0.5">세로 (px)</label>
            <input v-model.number="form.height" type="number" min="150" max="900" class="w-full border rounded px-2 py-1 text-sm" />
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
        <div>
          <label class="text-[11px] font-bold text-gray-600 block mb-0.5">클릭 시 이동 URL (선택)</label>
          <input v-model="form.link_url" placeholder="https://... 또는 /events" class="w-full border rounded px-2 py-1 text-sm" />
        </div>
        <div>
          <label class="text-[11px] font-bold text-gray-600 block mb-0.5">표시 빈도</label>
          <select v-model="form.display_mode" class="w-full border rounded px-2 py-1 text-sm">
            <option value="once_per_day">하루에 한 번</option>
            <option value="every_visit">매번 방문 시</option>
          </select>
        </div>
        <div>
          <label class="text-[11px] font-bold text-gray-600 block mb-0.5">시작 일시 (선택)</label>
          <input v-model="form.start_at" type="datetime-local" class="w-full border rounded px-2 py-1 text-sm" />
        </div>
        <div>
          <label class="text-[11px] font-bold text-gray-600 block mb-0.5">종료 일시 (선택)</label>
          <input v-model="form.end_at" type="datetime-local" class="w-full border rounded px-2 py-1 text-sm" />
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

    <!-- 목록 -->
    <div v-for="b in banners" :key="b.id" class="px-4 py-3 border-b flex items-center gap-3">
      <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-amber-100 flex-shrink-0 overflow-hidden">
        <img v-if="b.type === 'image' && b.image_url" :src="b.image_url" class="w-full h-full object-cover" />
        <span v-else class="text-lg">{{ b.type === 'image' ? '🖼️' : '📝' }}</span>
      </div>
      <div class="flex-1 min-w-0">
        <div class="text-sm font-bold text-gray-800 truncate">{{ b.title }}</div>
        <div class="text-[10px] text-gray-400">
          {{ b.type === 'image' ? '이미지' : `텍스트 ${b.width}×${b.height}` }}
          · {{ b.display_mode === 'once_per_day' ? '하루 1회' : '매번' }}
          <span v-if="b.start_at || b.end_at"> · 기간 {{ fmt(b.start_at) }}~{{ fmt(b.end_at) }}</span>
        </div>
      </div>
      <label class="flex items-center gap-1 text-xs">
        <input type="checkbox" :checked="b.is_active" @change="toggleActive(b)" class="accent-amber-500" /> 활성
      </label>
      <button @click="editBanner(b)" class="text-xs text-amber-600">수정</button>
      <button @click="deleteBanner(b)" class="text-xs text-red-400">삭제</button>
    </div>
    <div v-if="!banners.length" class="px-4 py-6 text-center text-sm text-gray-400">등록된 팝업이 없습니다</div>
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
  title: '', type: 'text',
  image_url: '', content: '',
  width: 500, height: 300,
  link_url: '', display_mode: 'once_per_day',
  is_active: true, start_at: '', end_at: '', sort_order: 0,
}

const form = ref({ ...DEFAULT_FORM })

function fmt(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  return `${d.getFullYear()}.${d.getMonth()+1}.${d.getDate()}`
}

async function load() {
  try {
    const { data } = await axios.get('/api/admin/popup-banners')
    banners.value = data.data || []
  } catch {}
}

function openForm() {
  resetForm()
  showForm.value = true
}

function onFile(e) {
  pickedFile.value = e.target.files?.[0] || null
}

async function saveForm() {
  if (!form.value.title) { alert('제목을 입력하세요'); return }
  if (form.value.type === 'text' && !form.value.content) { alert('본문을 입력하세요'); return }
  if (form.value.type === 'image' && !pickedFile.value && !form.value.image_url) { alert('이미지를 선택하세요'); return }
  saving.value = true
  try {
    const fd = new FormData()
    Object.entries(form.value).forEach(([k, v]) => {
      if (v === null || v === undefined) return
      if (typeof v === 'boolean') fd.append(k, v ? '1' : '0')
      else fd.append(k, v)
    })
    if (pickedFile.value) fd.append('image', pickedFile.value)
    const url = editId.value ? `/api/admin/popup-banners/${editId.value}` : '/api/admin/popup-banners'
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
  form.value = {
    ...DEFAULT_FORM,
    ...b,
    start_at: b.start_at ? b.start_at.substring(0, 16) : '',
    end_at: b.end_at ? b.end_at.substring(0, 16) : '',
  }
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
    fd.append('type', b.type)
    fd.append('display_mode', b.display_mode)
    fd.append('is_active', b.is_active ? '0' : '1')
    await axios.post(`/api/admin/popup-banners/${b.id}`, fd)
    b.is_active = !b.is_active
  } catch {}
}

async function deleteBanner(b) {
  if (!confirm(`'${b.title}' 삭제하시겠습니까?`)) return
  try {
    await axios.delete(`/api/admin/popup-banners/${b.id}`)
    banners.value = banners.value.filter(x => x.id !== b.id)
  } catch {}
}

onMounted(load)
</script>
