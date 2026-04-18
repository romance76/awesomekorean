<template>
  <!-- /admin/v2/security/ip-bans (Phase 2-C Post — Kay #5 UI: 단일 IP + CIDR) -->
  <div class="space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
      <h2 class="text-xl font-bold">🛡️ IP 차단 관리</h2>
      <div class="flex gap-2">
        <button @click="showNew = true" class="px-3 py-1.5 bg-amber-400 hover:bg-amber-500 text-white rounded text-sm font-semibold">+ 단일 IP</button>
        <button @click="showNew = true; newForm.is_cidr = true" class="px-3 py-1.5 bg-red-400 hover:bg-red-500 text-white rounded text-sm font-semibold">+ CIDR 대역</button>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-3 flex gap-2">
      <select v-model="typeFilter" @change="load" class="px-3 py-1.5 border rounded text-sm">
        <option value="">전체</option>
        <option value="single">단일 IP</option>
        <option value="cidr">CIDR 대역</option>
      </select>
    </div>

    <DataTable
      :rows="bans"
      :columns="columns"
      :loading="loading"
      :page-size="40"
      exportable
      :search-keys="['ip_address', 'reason']"
      search-placeholder="IP 또는 사유 검색"
      empty-text="차단 IP 없음"
      :bulk-actions="bulkActions"
      @bulk-action="handleBulk"
    >
      <template #cell-ip_address="{ row }">
        <span :class="['font-mono text-xs px-2 py-0.5 rounded', row.is_cidr ? 'bg-red-100 text-red-700' : 'bg-gray-100']">
          {{ row.is_cidr ? '📡' : '📍' }} {{ row.ip_address }}
        </span>
      </template>
      <template #cell-is_cidr="{ value }">
        <span :class="value ? 'text-red-600' : 'text-gray-600'">{{ value ? 'CIDR' : '단일' }}</span>
      </template>
      <template #cell-expires_at="{ value }">
        <span class="text-xs" :class="isExpired(value) ? 'text-gray-400' : ''">
          {{ value ? fmtDate(value) : '영구' }}
        </span>
      </template>
      <template #cell-created_at="{ value }">
        <span class="text-xs">{{ fmtDate(value) }}</span>
      </template>
      <template #actions="{ row }">
        <button @click="del(row)" class="text-xs px-2 py-1 bg-green-100 text-green-700 hover:bg-green-200 rounded">해제</button>
      </template>
    </DataTable>

    <!-- 신규 차단 모달 -->
    <div v-if="showNew" @click.self="showNew = false" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl max-w-md w-full p-5">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold">+ IP 차단 추가</h3>
          <button @click="showNew = false" class="text-xl text-gray-400 hover:text-gray-600">×</button>
        </div>
        <div class="space-y-3">
          <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" v-model="newForm.is_cidr" />
            <span>CIDR 대역 차단 (예: <code class="bg-gray-100 px-1">192.168.0.0/24</code>)</span>
          </label>
          <label class="block">
            <span class="text-xs text-gray-500">{{ newForm.is_cidr ? 'CIDR (IP/mask)' : 'IP 주소' }}</span>
            <input v-model="newForm.ip_address" :placeholder="newForm.is_cidr ? '1.2.3.0/24' : '1.2.3.4'" class="w-full border rounded px-3 py-2 mt-1 text-sm font-mono" />
          </label>
          <label class="block">
            <span class="text-xs text-gray-500">사유</span>
            <input v-model="newForm.reason" placeholder="스팸·brute force 등" class="w-full border rounded px-3 py-2 mt-1 text-sm" />
          </label>
          <label class="block">
            <span class="text-xs text-gray-500">만료 일시 (비우면 영구)</span>
            <input v-model="newForm.expires_at" type="datetime-local" class="w-full border rounded px-3 py-2 mt-1 text-sm" />
          </label>
          <div class="flex justify-end gap-2 pt-3 border-t">
            <button @click="showNew = false" class="px-3 py-1.5 bg-gray-100 rounded text-sm">취소</button>
            <button @click="create" :disabled="saving" class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded text-sm font-semibold disabled:opacity-50">
              {{ saving ? '차단 중...' : '🚫 차단' }}
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
const bans = ref([])
const loading = ref(true)
const typeFilter = ref('')
const showNew = ref(false)
const saving = ref(false)
const newForm = reactive({ ip_address: '', is_cidr: false, reason: '', expires_at: null })

const columns = [
  { key: 'id', label: 'ID', sortable: true, class: 'w-16 font-mono text-xs' },
  { key: 'ip_address', label: 'IP/대역', sortable: true },
  { key: 'is_cidr', label: '유형', sortable: true, class: 'w-20 text-center' },
  { key: 'reason', label: '사유' },
  { key: 'banned_by_name', label: '차단자', class: 'w-24' },
  { key: 'expires_at', label: '만료', sortable: true, class: 'w-40' },
  { key: 'created_at', label: '차단일', sortable: true, class: 'w-32' },
]

const bulkActions = [
  { key: 'delete', label: '🗑️ 일괄 해제', danger: true, confirm: '선택한 차단을 해제하시겠습니까?' },
]

const fmtDate = (s) => s ? new Date(s).toLocaleString('ko-KR') : ''
const isExpired = (s) => s && new Date(s) < new Date()

async function load() {
  loading.value = true
  try {
    const params = {}
    if (typeFilter.value) params.type = typeFilter.value
    const { data } = await axios.get('/api/admin/ip-bans-v2', { params })
    bans.value = data.data || []
  } finally { loading.value = false }
}

async function create() {
  if (!newForm.ip_address || !newForm.reason) { site.toast('IP·사유 입력', 'error'); return }
  saving.value = true
  try {
    await axios.post('/api/admin/ip-bans-v2', newForm)
    showNew.value = false
    Object.assign(newForm, { ip_address: '', is_cidr: false, reason: '', expires_at: null })
    await load()
    site.toast('차단 완료', 'success')
  } catch (e) {
    site.toast(e.response?.data?.message || '실패', 'error')
  } finally { saving.value = false }
}

async function del(row) {
  if (!confirm(`${row.ip_address} 차단 해제?`)) return
  try {
    await axios.delete(`/api/admin/ip-bans-v2/${row.id}`)
    await load()
    site.toast('해제됨', 'success')
  } catch { site.toast('실패', 'error') }
}

async function handleBulk({ key, rows }) {
  if (key === 'delete') {
    const ids = rows.map(r => r.id)
    try {
      await axios.post('/api/admin/ip-bans-v2/bulk-delete', { ids })
      await load()
      site.toast(`${ids.length}건 해제`, 'success')
    } catch { site.toast('실패', 'error') }
  }
}

onMounted(load)
</script>
