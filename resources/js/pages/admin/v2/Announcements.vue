<template>
  <!-- /admin/v2/communication/notices (Phase 2-C Post: 공지 배너 관리) -->
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-bold">📰 공지사항 배너</h2>
      <button @click="openNew" class="px-3 py-1.5 bg-amber-400 hover:bg-amber-500 text-white rounded text-sm font-semibold">+ 새 공지</button>
    </div>

    <DataTable
      :rows="announcements"
      :columns="columns"
      :loading="loading"
      :page-size="20"
      empty-text="등록된 공지 없음"
    >
      <template #cell-level="{ value }">
        <span :class="levelBadge(value)">{{ levelLabel(value) }}</span>
      </template>
      <template #cell-is_active="{ value }">
        <span :class="value ? 'text-green-600' : 'text-gray-400'">{{ value ? '✅ 활성' : '❌ 비활성' }}</span>
      </template>
      <template #cell-audience="{ value }">
        <span class="text-xs text-gray-500">{{ value }}</span>
      </template>
      <template #cell-starts_at="{ value }">
        <span class="text-xs">{{ fmtDate(value) || '-' }}</span>
      </template>
      <template #cell-ends_at="{ value }">
        <span class="text-xs">{{ fmtDate(value) || '-' }}</span>
      </template>
      <template #actions="{ row }">
        <button @click="edit(row)" class="text-xs px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded mr-1">수정</button>
        <button @click="toggle(row)" class="text-xs px-2 py-1 rounded mr-1" :class="row.is_active ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'">
          {{ row.is_active ? '중지' : '활성' }}
        </button>
        <button @click="del(row)" class="text-xs px-2 py-1 bg-red-100 text-red-700 rounded">삭제</button>
      </template>
    </DataTable>

    <!-- 편집 모달 -->
    <div v-if="showModal" @click.self="showModal = false" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl max-w-2xl w-full p-5">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-lg">{{ form.id ? '✏️ 수정' : '+ 새 공지' }}</h3>
          <button @click="showModal = false" class="text-xl text-gray-400 hover:text-gray-600">×</button>
        </div>
        <div class="space-y-3">
          <label class="block">
            <span class="text-xs text-gray-500">제목</span>
            <input v-model="form.title" class="w-full border rounded px-3 py-2 mt-1 text-sm" />
          </label>
          <label class="block">
            <span class="text-xs text-gray-500">메시지</span>
            <textarea v-model="form.message" rows="3" class="w-full border rounded px-3 py-2 mt-1 text-sm"></textarea>
          </label>
          <div class="grid grid-cols-2 gap-3">
            <label class="block">
              <span class="text-xs text-gray-500">레벨</span>
              <select v-model="form.level" class="w-full border rounded px-3 py-2 mt-1 text-sm">
                <option value="info">ℹ️ 정보 (파랑)</option>
                <option value="success">✅ 성공 (초록)</option>
                <option value="warning">⚠️ 경고 (주황)</option>
                <option value="danger">🚨 위험 (빨강)</option>
              </select>
            </label>
            <label class="block">
              <span class="text-xs text-gray-500">대상</span>
              <select v-model="form.audience" class="w-full border rounded px-3 py-2 mt-1 text-sm">
                <option value="all">전체</option>
                <option value="logged_in">로그인 유저</option>
                <option value="guest">비로그인</option>
                <option value="role:super_admin">super_admin 만</option>
                <option value="role:manager">manager 만</option>
              </select>
            </label>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <label class="block">
              <span class="text-xs text-gray-500">링크 URL (선택)</span>
              <input v-model="form.link_url" class="w-full border rounded px-3 py-2 mt-1 text-sm" />
            </label>
            <label class="block">
              <span class="text-xs text-gray-500">링크 라벨</span>
              <input v-model="form.link_label" placeholder="자세히 →" class="w-full border rounded px-3 py-2 mt-1 text-sm" />
            </label>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <label class="block">
              <span class="text-xs text-gray-500">시작 일시</span>
              <input v-model="form.starts_at" type="datetime-local" class="w-full border rounded px-3 py-2 mt-1 text-sm" />
            </label>
            <label class="block">
              <span class="text-xs text-gray-500">종료 일시</span>
              <input v-model="form.ends_at" type="datetime-local" class="w-full border rounded px-3 py-2 mt-1 text-sm" />
            </label>
          </div>
          <div class="flex gap-4">
            <label class="flex items-center gap-2 text-sm">
              <input type="checkbox" v-model="form.is_active" /> 활성화
            </label>
            <label class="flex items-center gap-2 text-sm">
              <input type="checkbox" v-model="form.dismissible" /> 유저가 닫기 가능
            </label>
          </div>
          <div class="flex justify-end gap-2 pt-3 border-t">
            <button @click="showModal = false" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded text-sm">취소</button>
            <button @click="save" :disabled="saving" class="px-4 py-2 bg-amber-400 hover:bg-amber-500 text-white rounded text-sm font-semibold disabled:opacity-50">
              {{ saving ? '저장 중...' : '💾 저장' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'
import DataTable from '../../../components/admin/DataTable.vue'
import { useSiteStore } from '../../../stores/site'

const site = useSiteStore()
const announcements = ref([])
const loading = ref(true)
const showModal = ref(false)
const saving = ref(false)

const form = reactive({
  id: null, title: '', message: '', level: 'info',
  link_url: '', link_label: '', dismissible: true, is_active: true,
  audience: 'all', starts_at: null, ends_at: null,
})

const columns = [
  { key: 'id', label: 'ID', sortable: true, class: 'w-16 font-mono text-xs' },
  { key: 'title', label: '제목', sortable: true },
  { key: 'level', label: '레벨', sortable: true, class: 'w-24 text-center' },
  { key: 'audience', label: '대상', class: 'w-32' },
  { key: 'is_active', label: '상태', sortable: true, class: 'w-24 text-center' },
  { key: 'starts_at', label: '시작', sortable: true, class: 'w-32' },
  { key: 'ends_at', label: '종료', sortable: true, class: 'w-32' },
]

const fmtDate = (s) => s ? new Date(s).toLocaleString('ko-KR') : ''
const levelBadge = (l) => 'px-2 py-0.5 rounded text-xs font-semibold ' + ({
  info: 'bg-blue-100 text-blue-700',
  success: 'bg-green-100 text-green-700',
  warning: 'bg-yellow-100 text-yellow-700',
  danger: 'bg-red-100 text-red-700',
}[l] || 'bg-gray-100')
const levelLabel = (l) => ({ info: '정보', success: '성공', warning: '경고', danger: '위험' }[l] || l)

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/admin/announcements')
    announcements.value = data.data || []
  } finally { loading.value = false }
}

function openNew() {
  Object.assign(form, {
    id: null, title: '', message: '', level: 'info',
    link_url: '', link_label: '', dismissible: true, is_active: true,
    audience: 'all', starts_at: null, ends_at: null,
  })
  showModal.value = true
}

function edit(row) {
  Object.assign(form, row)
  form.is_active = !!row.is_active
  form.dismissible = !!row.dismissible
  showModal.value = true
}

async function save() {
  saving.value = true
  try {
    if (form.id) await axios.put(`/api/admin/announcements/${form.id}`, form)
    else await axios.post('/api/admin/announcements', form)
    showModal.value = false
    await load()
    site.toast('저장되었습니다', 'success')
  } catch (e) {
    site.toast(e.response?.data?.message || '저장 실패', 'error')
  } finally { saving.value = false }
}

async function toggle(row) {
  try {
    await axios.put(`/api/admin/announcements/${row.id}`, { is_active: !row.is_active })
    row.is_active = !row.is_active
  } catch { site.toast('실패', 'error') }
}

async function del(row) {
  if (!confirm(`"${row.title}" 삭제?`)) return
  try {
    await axios.delete(`/api/admin/announcements/${row.id}`)
    await load()
    site.toast('삭제됨', 'success')
  } catch { site.toast('실패', 'error') }
}

onMounted(load)
</script>
