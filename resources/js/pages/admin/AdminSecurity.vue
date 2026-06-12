<template>
<div>
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-4">
    <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="shield" :size="20" /></span>
    보안
  </h1>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <!-- IP 차단 -->
    <div class="card overflow-hidden">
      <div class="px-4 py-3 border-b border-gray-50 flex items-center gap-2 font-bold text-sm text-ink">
        <span class="icon-chip w-7 h-7 bg-red-50 text-red-500"><AppIcon name="shield" :size="14" /></span>IP 차단 목록
      </div>
      <div v-for="ban in ipBans" :key="ban.id" class="px-4 py-2.5 border-b border-gray-50 last:border-0 flex justify-between text-sm">
        <div><span class="font-mono text-ink">{{ ban.ip_address }}</span> <span class="text-xs text-ink-muted ml-2">{{ ban.reason }}</span></div>
        <button @click="removeBan(ban)" class="text-red-400 hover:text-red-600 text-xs transition-colors">삭제</button>
      </div>
      <div v-if="!ipBans.length" class="px-4 py-4 text-sm text-ink-muted text-center">차단된 IP 없음</div>
      <div class="px-4 py-3 border-t border-gray-50 flex gap-2">
        <input v-model="newIp" placeholder="IP 주소" class="input-soft flex-1 !w-auto !px-2 !py-1 !text-sm" />
        <button @click="addBan" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded-lg text-xs font-bold transition-colors">차단</button>
      </div>
    </div>

    <!-- 차단 사용자 -->
    <div class="card overflow-hidden">
      <div class="px-4 py-3 border-b border-gray-50 flex items-center gap-2 font-bold text-sm text-ink">
        <span class="icon-chip w-7 h-7 bg-red-50 text-red-500"><AppIcon name="user" :size="14" /></span>차단된 사용자
      </div>
      <div v-for="u in bannedUsers" :key="u.id" class="px-4 py-2.5 border-b border-gray-50 last:border-0 flex justify-between items-center text-sm">
        <div>
          <span class="font-bold text-ink">{{ u.nickname || u.name }}</span>
          <span class="text-xs text-ink-muted ml-2">{{ u.ban_reason || '사유 없음' }}</span>
        </div>
        <button @click="unbanUser(u)" class="text-blue-500 hover:text-blue-700 text-xs font-bold transition-colors">해제</button>
      </div>
      <div v-if="!bannedUsers.length" class="px-4 py-4 text-sm text-ink-muted text-center">차단된 사용자 없음</div>
    </div>
  </div>

  <!-- 신고 관리 -->
  <div class="mt-6 card overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-50 flex items-center justify-between">
      <div class="flex items-center gap-2 font-bold text-sm text-ink">
        <span class="icon-chip w-7 h-7 bg-amber-50 text-amber-600"><AppIcon name="alert-circle" :size="14" /></span>신고 관리
      </div>
      <div class="flex gap-2">
        <select v-model="reportFilter.type" @change="loadReports" class="input-soft !w-auto !px-2 !py-1 !text-xs">
          <option value="">전체 유형</option>
          <option value="User">사용자</option>
          <option value="Post">게시글</option>
          <option value="MarketItem">중고장터</option>
          <option value="RealEstateListing">부동산</option>
          <option value="Comment">댓글</option>
          <option value="ChatMessage">채팅</option>
          <option value="GroupBuy">공동구매</option>
        </select>
        <select v-model="reportFilter.status" @change="loadReports" class="input-soft !w-auto !px-2 !py-1 !text-xs">
          <option value="">전체 상태</option>
          <option value="pending">대기중</option>
          <option value="resolved">해결됨</option>
          <option value="dismissed">기각됨</option>
        </select>
      </div>
    </div>

    <div v-for="r in reports" :key="r.id" class="px-4 py-3 border-b border-gray-50 last:border-0">
      <div class="flex items-start justify-between">
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="text-xs px-2 py-0.5 rounded-full font-bold"
              :class="{'bg-yellow-100 text-yellow-700': r.status==='pending', 'bg-green-100 text-green-700': r.status==='resolved', 'bg-gray-100 text-ink-light': r.status==='dismissed'}">
              {{ {pending:'대기중',resolved:'해결됨',dismissed:'기각'}[r.status] || r.status }}
            </span>
            <span class="badge-blue">{{ formatType(r.reportable_type) }}</span>
            <span class="text-[11px] text-ink-faint">#{{ r.reportable_id }}</span>
          </div>
          <div class="text-sm text-ink font-semibold mt-1">{{ r.reason }}</div>
          <div v-if="r.content" class="text-xs text-ink-muted mt-0.5 truncate">{{ r.content }}</div>
          <div class="flex items-center gap-3 mt-1.5 text-[11px] text-ink-faint">
            <span v-if="r.reporter">신고자: <b class="text-ink-light">{{ r.reporter.nickname || r.reporter.name }}</b></span>
            <span>{{ formatDate(r.created_at) }}</span>
          </div>
          <!-- 관리자 메모 -->
          <div v-if="r.admin_note" class="mt-1 inline-flex items-center gap-1 text-xs text-amber-700 bg-amber-50 px-2 py-1 rounded-lg"><AppIcon name="edit" :size="11" /> {{ r.admin_note }}</div>
        </div>
        <div v-if="r.status === 'pending'" class="flex gap-1.5 ml-3 flex-shrink-0">
          <button @click="resolveReport(r, 'resolved')" class="bg-green-500 text-white text-xs font-bold px-2 py-1 rounded-lg hover:bg-green-600 transition-colors">해결</button>
          <button @click="resolveReport(r, 'dismissed')" class="bg-gray-300 text-ink-light text-xs font-bold px-2 py-1 rounded-lg hover:bg-gray-400 transition-colors">기각</button>
        </div>
      </div>
      <!-- 메모 입력 (대기중일 때) -->
      <div v-if="r.status === 'pending' && editNoteId === r.id" class="mt-2 flex gap-2">
        <input v-model="editNote" placeholder="관리자 메모..." class="input-soft flex-1 !w-auto !px-2 !py-1 !text-xs" />
        <button @click="saveNote(r)" class="btn-primary !px-2 !py-1 !text-xs">저장</button>
        <button @click="editNoteId=null" class="btn-ghost !px-2 !py-1 !text-xs">취소</button>
      </div>
      <button v-else-if="r.status === 'pending'" @click="editNoteId=r.id; editNote=r.admin_note||''" class="inline-flex items-center gap-1 text-xs text-ink-faint hover:text-amber-600 mt-1 transition-colors"><AppIcon name="edit" :size="11" /> 메모 추가</button>
    </div>

    <div v-if="!reports.length" class="px-4 py-8 text-sm text-ink-muted text-center">신고 없음</div>

    <!-- 페이지네이션 -->
    <div v-if="reportPagination.lastPage > 1" class="px-4 py-3 border-t border-gray-50 flex items-center justify-center gap-2">
      <button v-for="p in reportPagination.lastPage" :key="p" @click="reportFilter.page=p; loadReports()"
        class="w-8 h-8 rounded-lg text-xs font-bold transition-colors"
        :class="p === reportPagination.currentPage ? 'bg-amber-400 text-white' : 'text-ink-muted hover:bg-gray-100'">
        {{ p }}
      </button>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const ipBans = ref([])
const reports = ref([])
const bannedUsers = ref([])
const newIp = ref('')
const reportFilter = ref({ type: '', status: '', page: 1 })
const reportPagination = ref({ currentPage: 1, lastPage: 1 })
const editNoteId = ref(null)
const editNote = ref('')

function formatType(t) {
  if (!t) return '?'
  const name = t.replace(/^App\\Models\\/, '')
  const map = { User: '사용자', Post: '게시글', MarketItem: '중고장터', RealEstateListing: '부동산', Comment: '댓글', ChatMessage: '채팅', GroupBuy: '공동구매' }
  return map[name] || name
}

function formatDate(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  return `${d.getFullYear()}.${d.getMonth()+1}.${d.getDate()} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`
}

onMounted(async () => {
  loadIpBans()
  loadReports()
  loadBannedUsers()
})

async function loadIpBans() {
  try { const { data } = await axios.get('/api/admin/ip-bans'); ipBans.value = data.data || [] } catch {}
}

async function loadReports() {
  try {
    const { data } = await axios.get('/api/admin/reports', {
      params: { type: reportFilter.value.type, status: reportFilter.value.status, page: reportFilter.value.page }
    })
    const d = data.data
    reports.value = d?.data || d || []
    reportPagination.value = { currentPage: d?.current_page || 1, lastPage: d?.last_page || 1 }
  } catch {}
}

async function loadBannedUsers() {
  try {
    const { data } = await axios.get('/api/admin/users', { params: { banned: 1, per_page: 50 } })
    bannedUsers.value = (data.data?.data || data.data || []).filter(u => u.is_banned)
  } catch {}
}

async function addBan() {
  if (!newIp.value) return
  try { await axios.post('/api/admin/ip-bans', { ip_address: newIp.value, reason: '관리자 차단' }); newIp.value = ''; loadIpBans() } catch {}
}

async function removeBan(b) {
  try { await axios.delete('/api/admin/ip-bans/' + b.id); ipBans.value = ipBans.value.filter(x => x.id !== b.id) } catch {}
}

async function resolveReport(r, status) {
  try {
    await axios.put('/api/admin/reports/' + r.id, { status, admin_note: r.admin_note || '' })
    r.status = status
  } catch {}
}

async function saveNote(r) {
  try {
    await axios.put('/api/admin/reports/' + r.id, { admin_note: editNote.value })
    r.admin_note = editNote.value
    editNoteId.value = null
  } catch {}
}

async function unbanUser(u) {
  try {
    await axios.post(`/api/admin/chat/users/${u.id}/permaban`, { unban: true })
    bannedUsers.value = bannedUsers.value.filter(x => x.id !== u.id)
  } catch {}
}
</script>
