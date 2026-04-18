<template>
  <!-- /admin/v2/security/audit (Phase 2-C Post: admin_audit_log 조회) -->
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-bold">📜 관리자 감사 로그</h2>
      <div class="flex items-center gap-2">
        <input v-model="filter.action" @change="load" placeholder="액션 필터 (예: revoke_points)" class="px-3 py-1 border rounded text-sm" />
        <button @click="exportCsv" class="px-3 py-1.5 bg-green-500 hover:bg-green-600 text-white rounded text-xs font-semibold">📥 CSV</button>
      </div>
    </div>

    <div v-if="loading" class="text-sm text-gray-400 p-6 text-center">로딩 중...</div>

    <div v-else-if="!rows.length" class="bg-white rounded-xl shadow-sm p-10 text-center text-gray-500">
      <p class="text-3xl mb-2">📜</p><p>로그 없음</p>
    </div>

    <div v-else class="bg-white rounded-xl shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
          <tr>
            <th class="px-3 py-2 text-left">일시</th>
            <th class="px-3 py-2 text-left">관리자</th>
            <th class="px-3 py-2 text-left">액션</th>
            <th class="px-3 py-2 text-left">대상</th>
            <th class="px-3 py-2 text-left">IP</th>
            <th class="px-3 py-2 text-center">상세</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in rows" :key="r.id" class="border-t hover:bg-amber-50">
            <td class="px-3 py-2 text-xs font-mono whitespace-nowrap">{{ fmtDate(r.created_at) }}</td>
            <td class="px-3 py-2">{{ r.admin_name || r.admin_id }}</td>
            <td class="px-3 py-2 font-mono text-xs">
              <span :class="actionBadge(r.action)">{{ r.action }}</span>
            </td>
            <td class="px-3 py-2 text-xs">{{ r.target_type }}{{ r.target_id ? '#' + r.target_id : '' }}</td>
            <td class="px-3 py-2 font-mono text-xs">{{ r.ip }}</td>
            <td class="px-3 py-2 text-center">
              <button @click="expand = expand === r.id ? null : r.id" class="text-amber-500 hover:text-amber-700 text-xs">
                {{ expand === r.id ? '닫기' : '보기' }}
              </button>
            </td>
          </tr>
          <template v-for="r in rows" :key="'det-' + r.id">
            <tr v-if="expand === r.id" class="bg-gray-50">
              <td colspan="6" class="px-3 py-3">
                <div class="text-xs space-y-2">
                  <div v-if="r.before_value"><strong>Before:</strong> <pre class="whitespace-pre-wrap text-xs mt-1 bg-white p-2 border rounded">{{ pretty(r.before_value) }}</pre></div>
                  <div v-if="r.after_value"><strong>After / Payload:</strong> <pre class="whitespace-pre-wrap text-xs mt-1 bg-white p-2 border rounded">{{ pretty(r.after_value) }}</pre></div>
                  <div v-if="r.note"><strong>Note:</strong> {{ r.note }}</div>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'
const rows = ref([])
const loading = ref(true)
const expand = ref(null)
const filter = reactive({ action: '' })
const fmtDate = (s) => s ? new Date(s).toLocaleString('ko-KR') : ''
const pretty = (v) => {
  if (!v) return ''
  try { return JSON.stringify(typeof v === 'string' ? JSON.parse(v) : v, null, 2) } catch { return String(v) }
}
const actionBadge = (a) => {
  if (!a) return 'text-gray-500'
  if (a.includes('delete') || a.includes('revoke') || a.includes('ban'))     return 'text-red-600 font-semibold'
  if (a.includes('reset')  || a.includes('force'))                           return 'text-orange-600 font-semibold'
  if (a.includes('grant')  || a.includes('create') || a.includes('approve'))return 'text-green-600 font-semibold'
  return 'text-gray-700'
}

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/admin/analytics/audit-log', { params: filter })
    rows.value = data.data || []
  } finally { loading.value = false }
}

function exportCsv() {
  if (!rows.value.length) return
  const cols = ['id','created_at','admin_id','admin_name','action','target_type','target_id','ip']
  const header = cols.join(',')
  const body = rows.value.map(r => cols.map(c => JSON.stringify(r[c] ?? '')).join(',')).join('\n')
  const blob = new Blob(['\uFEFF' + header + '\n' + body], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a'); a.href = url; a.download = `audit_log_${Date.now()}.csv`; a.click()
  URL.revokeObjectURL(url)
}

onMounted(load)
</script>
