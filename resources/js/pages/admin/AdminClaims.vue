<template>
<div>
  <div class="mb-4">
    <div class="text-xs text-ink-muted">관리자 › 서비스 › 업소 클레임</div>
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mt-1">
      <span class="icon-chip w-9 h-9 bg-pink-50 text-pink-600"><AppIcon name="store" :size="20" /></span>
      업소 클레임 관리
    </h1>
    <p class="text-xs text-ink-muted mt-0.5">업주의 업소록 소유권 클레임을 승인/거절합니다</p>
  </div>

  <!-- 통계 -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <div class="card p-3">
      <div class="text-xs text-ink-muted">전체</div>
      <div class="text-xl font-bold text-ink">{{ stats.total }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">대기</div>
      <div class="text-xl font-bold text-amber-600">{{ stats.pending }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">승인</div>
      <div class="text-xl font-bold text-green-600">{{ stats.approved }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">거절</div>
      <div class="text-xl font-bold text-red-600">{{ stats.rejected }}</div>
    </div>
  </div>

  <!-- 상태 필터 -->
  <div class="card p-3 mb-3 flex flex-wrap gap-2 items-center">
    <span class="text-xs text-ink-muted">상태:</span>
    <button v-for="f in ['all','pending','approved','rejected']" :key="f" @click="filter=f"
      class="text-xs px-3 py-1 rounded-full transition-colors"
      :class="filter===f ? 'bg-amber-400 text-white font-bold shadow-btn' : 'bg-gray-100 text-ink-muted hover:bg-gray-200'">
      {{ {all:'전체',pending:'대기',approved:'승인',rejected:'거절'}[f] }}
      <span class="ml-1 text-[11px]">({{ filter==='all' ? stats.total : stats[f] || 0 }})</span>
    </button>
    <button @click="loadClaims" class="ml-auto btn-secondary !px-3 !py-1 !text-xs"><AppIcon name="refresh" :size="12" /> 새로고침</button>
  </div>

  <!-- 리스트 -->
  <div v-if="loading" class="text-center py-12 text-ink-muted">로딩중...</div>
  <div v-else-if="!filteredClaims.length" class="card py-16 text-center">
    <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="store" :size="28" :stroke-width="1.5" /></div>
    <p class="text-sm text-ink-muted">{{ filter === 'pending' ? '대기 중인 클레임이 없습니다' : '해당 상태의 클레임이 없습니다' }}</p>
  </div>
  <div v-else class="space-y-3">
    <div v-for="c in filteredClaims" :key="c.id" class="card p-4">
      <div class="flex items-start justify-between gap-3">
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 mb-1 flex-wrap">
            <span class="text-xs px-2 py-0.5 rounded-full font-bold"
              :class="c.status==='pending'?'bg-amber-100 text-amber-700':c.status==='approved'?'bg-green-100 text-green-700':'bg-red-100 text-red-700'">
              {{ {pending:'대기',approved:'승인',rejected:'거절'}[c.status] }}
            </span>
            <span class="text-xs text-ink-faint">{{ formatDate(c.created_at) }}</span>
            <span class="text-xs text-ink-faint">#{{ c.id }}</span>
          </div>
          <div class="flex items-center gap-1 text-sm font-bold text-ink"><AppIcon name="store" :size="14" class="text-pink-500" /> {{ c.business?.name }}</div>
          <div class="text-xs text-ink-muted">{{ c.business?.category }} · {{ c.business?.city }}</div>
          <div class="flex items-center gap-1 text-xs text-ink-light mt-1"><AppIcon name="user" :size="12" /> <strong>{{ c.user?.name }}</strong> ({{ c.user?.email }}) {{ c.user?.phone ? '· ' + c.user.phone : '' }}</div>
          <div v-if="c.notes" class="flex items-center gap-1 text-xs text-ink-light mt-1"><AppIcon name="message-circle" :size="12" /> {{ c.notes }}</div>
          <div v-if="c.document_url" class="mt-2">
            <a :href="c.document_url" target="_blank" class="inline-flex items-center gap-1 text-xs text-amber-600 hover:underline"><AppIcon name="paperclip" :size="12" /> 증빙서류 보기</a>
          </div>
        </div>
        <div class="flex gap-2 flex-shrink-0">
          <template v-if="c.status==='pending'">
            <button @click="approve(c)" class="bg-green-500 text-white font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-green-600 transition-colors">승인</button>
            <button @click="reject(c)" class="bg-red-400 text-white font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-red-500 transition-colors">거절</button>
          </template>
          <button v-else-if="c.status==='approved'" @click="revoke(c)" class="bg-gray-400 text-white font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-gray-500 transition-colors">승인취소</button>
          <button v-else-if="c.status==='rejected'" @click="approve(c)" class="bg-green-500 text-white font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-green-600 transition-colors">재승인</button>
        </div>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const claims = ref([])
const loading = ref(true)
const filter = ref('pending')

const stats = computed(() => ({
  total: claims.value.length,
  pending: claims.value.filter(c => c.status === 'pending').length,
  approved: claims.value.filter(c => c.status === 'approved').length,
  rejected: claims.value.filter(c => c.status === 'rejected').length,
}))

const filteredClaims = computed(() =>
  filter.value === 'all' ? claims.value : claims.value.filter(c => c.status === filter.value)
)

function formatDate(dt) { return dt ? new Date(dt).toLocaleDateString('ko-KR') : '' }

async function loadClaims() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/admin/claims')
    claims.value = data.data?.data || data.data || []
  } catch {}
  loading.value = false
}

async function approve(c) {
  if (!confirm(`${c.business?.name} 클레임을 승인하시겠습니까?`)) return
  try { await axios.post(`/api/admin/claims/${c.id}/approve`); c.status = 'approved' }
  catch (e) { alert(e.response?.data?.message || '실패') }
}

async function revoke(c) {
  if (!confirm(`${c.business?.name} 승인을 취소하시겠습니까?`)) return
  try { await axios.post(`/api/admin/claims/${c.id}/reject`, { notes: '관리자 승인 취소' }); c.status = 'rejected' }
  catch (e) { alert(e.response?.data?.message || '실패') }
}

async function reject(c) {
  const reason = prompt('거절 사유를 입력하세요:')
  if (reason === null) return
  try { await axios.post(`/api/admin/claims/${c.id}/reject`, { notes: reason }); c.status = 'rejected' }
  catch (e) { alert(e.response?.data?.message || '실패') }
}

onMounted(loadClaims)
</script>
