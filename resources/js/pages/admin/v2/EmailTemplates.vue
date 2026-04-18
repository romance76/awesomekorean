<template>
  <!-- /admin/v2/communication/email-templates (Phase 2-C Post) -->
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-bold">📧 이메일 템플릿</h2>
      <button @click="openNew" class="px-3 py-1.5 bg-amber-400 hover:bg-amber-500 text-white rounded text-sm font-semibold">+ 새 템플릿</button>
    </div>

    <DataTable
      :rows="templates"
      :columns="columns"
      :loading="loading"
      :page-size="20"
      exportable
      empty-text="등록된 템플릿 없음"
    >
      <template #cell-is_active="{ value }">
        <span :class="value ? 'text-green-600' : 'text-gray-400'">{{ value ? '✅ 활성' : '❌ 비활성' }}</span>
      </template>
      <template #cell-category="{ value }">
        <span class="px-2 py-0.5 bg-amber-100 text-amber-700 rounded text-xs">{{ value }}</span>
      </template>
      <template #actions="{ row }">
        <button @click="edit(row)" class="text-xs px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded mr-1">수정</button>
        <button @click="preview(row)" class="text-xs px-2 py-1 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded mr-1">미리보기</button>
        <button @click="del(row)" class="text-xs px-2 py-1 bg-red-100 text-red-700 hover:bg-red-200 rounded">삭제</button>
      </template>
    </DataTable>

    <!-- 편집 모달 -->
    <div v-if="showModal" @click.self="showModal = false" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl max-w-3xl w-full p-5 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-lg">{{ form.id ? '✏️ 수정' : '+ 새 템플릿' }}</h3>
          <button @click="showModal = false" class="text-gray-400 hover:text-gray-600 text-xl">×</button>
        </div>
        <div class="space-y-3">
          <div class="grid grid-cols-2 gap-3">
            <label class="block">
              <span class="text-xs text-gray-500">slug</span>
              <input v-model="form.slug" :disabled="!!form.id" class="w-full border rounded px-3 py-2 mt-1 text-sm font-mono" />
            </label>
            <label class="block">
              <span class="text-xs text-gray-500">카테고리</span>
              <input v-model="form.category" class="w-full border rounded px-3 py-2 mt-1 text-sm" />
            </label>
          </div>
          <label class="block">
            <span class="text-xs text-gray-500">이름</span>
            <input v-model="form.name" class="w-full border rounded px-3 py-2 mt-1 text-sm" />
          </label>
          <label class="block">
            <span class="text-xs text-gray-500">제목 (Subject) — 변수 지원: <code v-pre>{{var}}</code></span>
            <input v-model="form.subject" class="w-full border rounded px-3 py-2 mt-1 text-sm" />
          </label>
          <label class="block">
            <span class="text-xs text-gray-500">HTML 본문 (WYSIWYG)</span>
            <RichEditor v-model="form.body_html" :height="250" placeholder="내용을 입력하세요..." />
          </label>
          <label class="block">
            <span class="text-xs text-gray-500">텍스트 본문 (폴백, 선택)</span>
            <textarea v-model="form.body_text" rows="4" class="w-full border rounded px-3 py-2 mt-1 text-sm"></textarea>
          </label>
          <label class="block">
            <span class="text-xs text-gray-500">변수 목록 (콤마 구분)</span>
            <input v-model="varsCsv" placeholder="user_name, code, amount" class="w-full border rounded px-3 py-2 mt-1 text-sm font-mono" />
          </label>
          <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" v-model="form.is_active" /> <span>활성화</span>
          </label>
          <div class="flex justify-end gap-2 pt-3 border-t">
            <button @click="showModal = false" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded text-sm">취소</button>
            <button @click="save" :disabled="saving" class="px-4 py-2 bg-amber-400 hover:bg-amber-500 text-white rounded text-sm font-semibold disabled:opacity-50">
              {{ saving ? '저장 중...' : '💾 저장' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- 미리보기 모달 -->
    <div v-if="previewData" @click.self="previewData = null" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl max-w-2xl w-full p-5 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-lg">👁️ 미리보기</h3>
          <button @click="previewData = null" class="text-gray-400 hover:text-gray-600 text-xl">×</button>
        </div>
        <p class="text-xs text-gray-500 mb-1">제목</p>
        <p class="font-semibold mb-4">{{ previewData.subject }}</p>
        <p class="text-xs text-gray-500 mb-1">본문</p>
        <div class="border rounded p-4 bg-gray-50" v-html="previewData.body_html"></div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import axios from 'axios'
import DataTable from '../../../components/admin/DataTable.vue'
import RichEditor from '../../../components/admin/RichEditor.vue'
import { useSiteStore } from '../../../stores/site'

const site = useSiteStore()
const templates = ref([])
const loading = ref(true)
const showModal = ref(false)
const saving = ref(false)
const previewData = ref(null)
const varsCsv = ref('')

const form = reactive({
  id: null, slug: '', name: '', subject: '', body_html: '', body_text: '',
  category: 'general', is_active: true, variables: [],
})

const columns = [
  { key: 'slug', label: 'Slug', sortable: true, class: 'font-mono text-xs' },
  { key: 'name', label: '이름', sortable: true },
  { key: 'category', label: '카테고리', sortable: true },
  { key: 'subject', label: '제목' },
  { key: 'is_active', label: '상태', sortable: true, class: 'text-center' },
]

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/admin/email-templates')
    templates.value = data.data || []
  } finally { loading.value = false }
}

function openNew() {
  Object.assign(form, { id: null, slug: '', name: '', subject: '', body_html: '', body_text: '', category: 'general', is_active: true, variables: [] })
  varsCsv.value = ''
  showModal.value = true
}

function edit(row) {
  Object.assign(form, row)
  const v = typeof row.variables === 'string' ? JSON.parse(row.variables || '[]') : (row.variables || [])
  form.variables = v
  varsCsv.value = v.join(', ')
  form.is_active = !!row.is_active
  showModal.value = true
}

async function save() {
  saving.value = true
  try {
    const vars = varsCsv.value.split(',').map(s => s.trim()).filter(Boolean)
    const payload = { ...form, variables: vars }
    if (form.id) {
      await axios.put(`/api/admin/email-templates/${form.id}`, payload)
    } else {
      await axios.post('/api/admin/email-templates', payload)
    }
    showModal.value = false
    await load()
    site.toast('저장되었습니다', 'success')
  } catch (e) {
    site.toast(e.response?.data?.message || '저장 실패', 'error')
  } finally { saving.value = false }
}

async function del(row) {
  if (!confirm(`"${row.name}" 삭제?`)) return
  try {
    await axios.delete(`/api/admin/email-templates/${row.id}`)
    await load()
    site.toast('삭제되었습니다', 'success')
  } catch { site.toast('실패', 'error') }
}

async function preview(row) {
  const vars = typeof row.variables === 'string' ? JSON.parse(row.variables || '[]') : (row.variables || [])
  const sample = {}
  vars.forEach(v => { sample[v] = `{${v}}` })
  try {
    const { data } = await axios.post(`/api/admin/email-templates/${row.id}/render`, { variables: sample })
    previewData.value = data.data
  } catch { site.toast('미리보기 실패', 'error') }
}

onMounted(load)
</script>
