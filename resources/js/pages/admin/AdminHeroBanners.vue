<template>
<div>
  <h1 class="text-xl font-black text-gray-800 mb-4">🎪 히어로 배너 관리</h1>
  <div class="bg-white rounded-xl shadow-sm border overflow-hidden mb-4">
    <div class="px-4 py-3 border-b flex items-center justify-between">
      <span class="font-bold text-sm text-gray-800">메인 홈 상단 슬라이드 배너</span>
      <button @click="showForm = !showForm" class="bg-amber-400 text-amber-900 font-bold px-3 py-1 rounded text-xs">+ 추가</button>
    </div>

    <!-- 추가/수정 폼 -->
    <div v-if="showForm" class="px-4 py-3 border-b bg-amber-50 space-y-2">
      <div class="grid grid-cols-2 gap-2">
        <input v-model="form.title" placeholder="제목 (예: 🐛 버그를 잡아라!)" class="border rounded px-2 py-1 text-sm" />
        <input v-model="form.subtitle" placeholder="소제목" class="border rounded px-2 py-1 text-sm" />
        <input v-model="form.bg_color" placeholder="배경색 (#E8534A)" class="border rounded px-2 py-1 text-sm" />
        <input v-model="form.text_color" placeholder="글자색 (#FFFFFF)" class="border rounded px-2 py-1 text-sm" />
        <select v-model="form.link_type" class="border rounded px-2 py-1 text-sm">
          <option value="none">클릭 없음</option>
          <option value="event">이벤트 연결</option>
          <option value="page">페이지 이동</option>
          <option value="url">외부 URL</option>
        </select>
        <input v-if="form.link_type === 'event'" v-model.number="form.event_id" placeholder="이벤트 ID" class="border rounded px-2 py-1 text-sm" />
        <input v-if="form.link_type === 'page'" v-model="form.link_page" placeholder="/music, /chat 등" class="border rounded px-2 py-1 text-sm" />
        <input v-if="form.link_type === 'url'" v-model="form.link_url" placeholder="https://..." class="border rounded px-2 py-1 text-sm" />
        <input v-model.number="form.sort_order" placeholder="순서 (1, 2, 3...)" class="border rounded px-2 py-1 text-sm" />
      </div>
      <div class="flex gap-2">
        <button @click="saveForm" class="bg-amber-500 text-white font-bold px-4 py-1 rounded text-xs">{{ editId ? '수정' : '등록' }}</button>
        <button @click="resetForm" class="text-gray-500 text-xs">취소</button>
      </div>
    </div>

    <!-- 배너 목록 -->
    <div v-for="b in banners" :key="b.id" class="px-4 py-3 border-b flex items-center gap-3">
      <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
        :style="{ backgroundColor: b.bg_color || '#F5A623' }">{{ b.sort_order }}</div>
      <div class="flex-1 min-w-0">
        <div class="text-sm font-bold text-gray-800">{{ b.title }}</div>
        <div class="text-[10px] text-gray-400">{{ b.subtitle }} · {{ b.link_type }}{{ b.event_id ? ' → 이벤트#' + b.event_id : '' }}{{ b.link_page || '' }}</div>
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
const form = ref({ title: '', subtitle: '', bg_color: '#F5A623', text_color: '#FFFFFF', link_type: 'none', event_id: null, link_page: '', link_url: '', sort_order: 0 })

async function load() {
  try { const { data } = await axios.get('/api/admin/hero-banners'); banners.value = data.data || [] } catch {}
}
async function saveForm() {
  try {
    if (editId.value) await axios.put('/api/admin/hero-banners/' + editId.value, form.value)
    else await axios.post('/api/admin/hero-banners', form.value)
    resetForm(); load()
  } catch {}
}
function editBanner(b) {
  editId.value = b.id
  form.value = { ...b }
  showForm.value = true
}
function resetForm() {
  editId.value = null
  form.value = { title: '', subtitle: '', bg_color: '#F5A623', text_color: '#FFFFFF', link_type: 'none', event_id: null, link_page: '', link_url: '', sort_order: 0 }
  showForm.value = false
}
async function toggleActive(b) {
  try { await axios.put('/api/admin/hero-banners/' + b.id, { is_active: !b.is_active }); b.is_active = !b.is_active } catch {}
}
async function deleteBanner(b) {
  if (!confirm('삭제?')) return
  try { await axios.delete('/api/admin/hero-banners/' + b.id); banners.value = banners.value.filter(x => x.id !== b.id) } catch {}
}
onMounted(load)
</script>
