<template>
  <!-- /admin/v2/users/invitations (Phase 2-C Post) -->
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-bold">👑 관리자 초대</h2>
      <button @click="showNew = true" class="px-3 py-1.5 bg-amber-400 hover:bg-amber-500 text-white rounded text-sm font-semibold">+ 초대 발송</button>
    </div>

    <DataTable
      :rows="invitations"
      :columns="columns"
      :loading="loading"
      :page-size="20"
      empty-text="초대 기록 없음"
    >
      <template #cell-status="{ value }">
        <span :class="statusBadge(value)">{{ statusLabel(value) }}</span>
      </template>
      <template #cell-role="{ value }">
        <span class="px-2 py-0.5 bg-amber-100 text-amber-700 rounded text-xs">{{ value }}</span>
      </template>
      <template #cell-invited_by_name="{ row }">
        <span class="text-xs">{{ row.invited_by_name || row.invited_by_email || '-' }}</span>
      </template>
      <template #cell-expires_at="{ value }">
        <span class="text-xs">{{ fmtDate(value) }}</span>
      </template>
      <template #actions="{ row }">
        <button v-if="row.status === 'pending'" @click="copyLink(row)" class="text-xs px-2 py-1 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded mr-1" title="초대 링크 복사">🔗</button>
        <button v-if="row.status === 'pending'" @click="revoke(row)" class="text-xs px-2 py-1 bg-red-100 text-red-700 hover:bg-red-200 rounded">취소</button>
      </template>
    </DataTable>

    <!-- 신규 초대 모달 -->
    <div v-if="showNew" @click.self="showNew = false" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl max-w-md w-full p-5">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold">+ 관리자 초대</h3>
          <button @click="showNew = false" class="text-xl text-gray-400 hover:text-gray-600">×</button>
        </div>
        <div class="space-y-3">
          <label class="block">
            <span class="text-xs text-gray-500">이메일</span>
            <input v-model="form.email" type="email" class="w-full border rounded px-3 py-2 mt-1 text-sm" />
          </label>
          <label class="block">
            <span class="text-xs text-gray-500">역할</span>
            <select v-model="form.role" class="w-full border rounded px-3 py-2 mt-1 text-sm">
              <option value="moderator">moderator (콘텐츠 검토)</option>
              <option value="manager">manager (운영 전반)</option>
              <option value="super_admin">super_admin (최고 관리자)</option>
            </select>
          </label>
          <label class="block">
            <span class="text-xs text-gray-500">메모 (선택)</span>
            <textarea v-model="form.note" rows="2" class="w-full border rounded px-3 py-2 mt-1 text-sm"></textarea>
          </label>
          <p class="text-xs text-amber-600 bg-amber-50 p-2 rounded">
            💡 초대 링크 유효 기간: 7일. 이메일 발송 실패 시 "🔗" 버튼으로 수동 공유 가능.
          </p>
          <div class="flex justify-end gap-2 pt-3 border-t">
            <button @click="showNew = false" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded text-sm">취소</button>
            <button @click="send" :disabled="sending" class="px-4 py-2 bg-amber-400 hover:bg-amber-500 text-white rounded text-sm font-semibold disabled:opacity-50">
              {{ sending ? '전송 중...' : '📧 초대 발송' }}
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
const invitations = ref([])
const loading = ref(true)
const showNew = ref(false)
const sending = ref(false)
const form = reactive({ email: '', role: 'moderator', note: '' })

const columns = [
  { key: 'id', label: 'ID', sortable: true, class: 'w-16 font-mono text-xs' },
  { key: 'email', label: '이메일', sortable: true, class: 'font-mono text-xs' },
  { key: 'role', label: '역할', sortable: true, class: 'w-32 text-center' },
  { key: 'status', label: '상태', sortable: true, class: 'w-24 text-center' },
  { key: 'invited_by_name', label: '초대자', class: 'w-32' },
  { key: 'expires_at', label: '만료', sortable: true, class: 'w-32' },
]

const fmtDate = (s) => s ? new Date(s).toLocaleString('ko-KR') : ''
const statusBadge = (s) => 'px-2 py-0.5 rounded text-xs font-semibold ' + ({
  pending: 'bg-yellow-100 text-yellow-700',
  accepted: 'bg-green-100 text-green-700',
  expired: 'bg-gray-100 text-gray-500',
  revoked: 'bg-red-100 text-red-700',
}[s] || 'bg-gray-100')
const statusLabel = (s) => ({ pending: '대기중', accepted: '수락됨', expired: '만료', revoked: '취소' }[s] || s)

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/admin/admin-invitations')
    invitations.value = data.data || []
  } finally { loading.value = false }
}

async function send() {
  if (!form.email) { site.toast('이메일 입력', 'error'); return }
  sending.value = true
  try {
    const { data } = await axios.post('/api/admin/admin-invitations', form)
    showNew.value = false
    form.email = ''; form.note = ''
    site.toast(`초대 발송 완료. 링크: ${data.data.accept_url}`, 'success', 8000)
    await load()
  } catch (e) {
    site.toast(e.response?.data?.message || '발송 실패', 'error')
  } finally { sending.value = false }
}

function copyLink(row) {
  const url = `${window.location.origin}/admin/accept-invitation?token=${row.token}`
  navigator.clipboard.writeText(url)
  site.toast('초대 링크 복사됨', 'success')
}

async function revoke(row) {
  if (!confirm(`${row.email} 초대를 취소하시겠습니까?`)) return
  try {
    await axios.post(`/api/admin/admin-invitations/${row.id}/revoke`)
    row.status = 'revoked'
    site.toast('취소됨', 'success')
  } catch { site.toast('실패', 'error') }
}

onMounted(load)
</script>
