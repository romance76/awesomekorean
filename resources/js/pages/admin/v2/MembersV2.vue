<template>
  <!-- /admin/v2/users (Phase 2-C Post: DataTable 활용 리팩토링) -->
  <div class="space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
      <h2 class="text-xl font-bold">👥 회원 관리</h2>
      <div class="flex gap-2">
        <router-link to="/admin/v2/users/point-ops" class="px-3 py-1.5 bg-amber-100 text-amber-700 hover:bg-amber-200 rounded text-xs font-semibold">
          💰 포인트 운영
        </router-link>
        <router-link to="/admin/v2/security/login-logs" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded text-xs">
          🔒 로그인 감시
        </router-link>
      </div>
    </div>

    <!-- 필터 -->
    <div class="bg-white rounded-xl shadow-sm p-3 flex gap-2 flex-wrap">
      <select v-model="roleFilter" @change="load" class="px-3 py-1.5 border rounded text-sm">
        <option value="">모든 역할</option>
        <option value="super_admin">super_admin</option>
        <option value="admin">admin (legacy)</option>
        <option value="manager">manager</option>
        <option value="moderator">moderator</option>
        <option value="user">user</option>
        <option value="business">business</option>
      </select>
      <select v-model="statusFilter" @change="load" class="px-3 py-1.5 border rounded text-sm">
        <option value="">전체 상태</option>
        <option value="active">활성</option>
        <option value="banned">차단</option>
      </select>
    </div>

    <DataTable
      :rows="users"
      :columns="columns"
      :loading="loading"
      :page-size="30"
      exportable
      :search-keys="['email','nickname','name','phone']"
      search-placeholder="이메일·닉네임·이름·전화 검색"
      empty-text="회원 없음"
      :bulk-actions="bulkActions"
      @bulk-action="handleBulk"
    >
      <template #cell-avatar="{ row }">
        <img :src="row.avatar || '/images/default-avatar.png'" @error="$event.target.src='/images/default-avatar.png'" class="w-8 h-8 rounded-full object-cover" />
      </template>
      <template #cell-role="{ value }">
        <span :class="roleBadge(value)">{{ value }}</span>
      </template>
      <template #cell-is_banned="{ value }">
        <span v-if="value" class="px-2 py-0.5 bg-red-100 text-red-700 rounded text-xs">차단</span>
        <span v-else class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs">활성</span>
      </template>
      <template #cell-points="{ value }">
        <span class="font-mono text-xs">{{ Number(value || 0).toLocaleString() }}</span>
      </template>
      <template #cell-created_at="{ value }">
        <span class="text-xs">{{ fmtDate(value) }}</span>
      </template>

      <template #actions="{ row }">
        <button @click="viewDetail(row)" class="text-xs px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded mr-1">상세</button>
        <router-link :to="`/admin/v2/users/${row.id}/point-history`" class="text-xs px-2 py-1 bg-amber-100 text-amber-700 hover:bg-amber-200 rounded mr-1">💰</router-link>
        <button @click="impersonate(row)" class="text-xs px-2 py-1 bg-purple-100 text-purple-700 hover:bg-purple-200 rounded mr-1" title="유저 계정으로 임시 로그인 (30분)">🎭</button>
        <button v-if="!row.is_banned" @click="ban(row)" class="text-xs px-2 py-1 bg-red-100 text-red-700 hover:bg-red-200 rounded">차단</button>
        <button v-else @click="unban(row)" class="text-xs px-2 py-1 bg-green-100 text-green-700 hover:bg-green-200 rounded">해제</button>
      </template>
    </DataTable>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import DataTable from '../../../components/admin/DataTable.vue'
import { useSiteStore } from '../../../stores/site'

const site = useSiteStore()
const users = ref([])
const loading = ref(true)
const roleFilter = ref('')
const statusFilter = ref('')

const fmtDate = (s) => s ? new Date(s).toLocaleDateString('ko-KR') : ''

const roleBadge = (r) => 'px-2 py-0.5 rounded text-xs font-semibold ' + ({
  super_admin: 'bg-purple-100 text-purple-700',
  admin: 'bg-red-100 text-red-700',
  manager: 'bg-amber-100 text-amber-700',
  moderator: 'bg-blue-100 text-blue-700',
  business: 'bg-green-100 text-green-700',
  user: 'bg-gray-100 text-gray-600',
}[r] || 'bg-gray-100 text-gray-500')

const columns = [
  { key: 'avatar', label: '', class: 'w-12' },
  { key: 'id', label: 'ID', sortable: true, class: 'font-mono text-xs w-16' },
  { key: 'email', label: '이메일', sortable: true, class: 'font-mono text-xs' },
  { key: 'nickname', label: '닉네임', sortable: true },
  { key: 'role', label: '역할', sortable: true, class: 'text-center' },
  { key: 'points', label: '포인트', sortable: true, class: 'text-right' },
  { key: 'is_banned', label: '상태', sortable: true, class: 'text-center' },
  { key: 'last_login_at', label: '마지막 로그인', sortable: true, format: (v) => v ? new Date(v).toLocaleDateString('ko-KR') : '-' },
  { key: 'created_at', label: '가입', sortable: true },
]

const bulkActions = [
  { key: 'ban',   label: '🚫 일괄 차단', danger: true, confirm: '선택한 회원을 차단하시겠습니까?' },
  { key: 'email', label: '✉️ 이메일 발송' },
]

async function load() {
  loading.value = true
  try {
    const params = { per_page: 200 }
    if (roleFilter.value) params.role = roleFilter.value
    if (statusFilter.value) params.status = statusFilter.value
    const { data } = await axios.get('/api/admin/users', { params })
    users.value = data?.data?.data || data?.data || []
  } finally { loading.value = false }
}

async function ban(u) {
  if (!confirm(`${u.email} 를 차단하시겠습니까?`)) return
  try {
    await axios.post(`/api/admin/users/${u.id}/ban`, { reason: '관리자 수동 차단' })
    u.is_banned = true
    site.toast('차단되었습니다', 'success')
  } catch { site.toast('차단 실패', 'error') }
}

async function unban(u) {
  try {
    await axios.post(`/api/admin/users/${u.id}/unban`)
    u.is_banned = false
    site.toast('차단 해제', 'success')
  } catch { site.toast('실패', 'error') }
}

function viewDetail(u) {
  window.open(`/profile/${u.id}`, '_blank')
}

async function impersonate(u) {
  if (!confirm(`${u.email} 계정으로 30분간 임시 로그인하시겠습니까?\n모든 행동이 감사 로그에 기록됩니다.`)) return
  try {
    const { data } = await axios.post(`/api/admin/users/${u.id}/impersonate`)
    if (data?.data?.token) {
      localStorage.setItem('sk_token', data.data.token)
      localStorage.setItem('sk_user', JSON.stringify(data.data.user))
      site.toast('Impersonation 시작. 헤더 빨간 배너에서 복귀 가능.', 'info')
      setTimeout(() => window.location.href = '/', 500)
    }
  } catch (e) {
    site.toast(e.response?.data?.message || 'Impersonate 실패', 'error')
  }
}

async function handleBulk({ key, rows }) {
  const ids = rows.map(r => r.id)
  if (key === 'ban') {
    for (const id of ids) {
      try { await axios.post(`/api/admin/users/${id}/ban`, { reason: '관리자 일괄 차단' }) } catch {}
    }
    site.toast(`${ids.length}명 차단 완료`, 'success')
    load()
  } else if (key === 'email') {
    // Broadcast 페이지로 이동
    sessionStorage.setItem('broadcast_preset_user_ids', ids.join(','))
    site.toast('이메일 페이지로 이동...', 'info')
    setTimeout(() => window.location.href = '/admin/v2/communication/broadcast', 500)
  }
}

onMounted(load)
</script>
