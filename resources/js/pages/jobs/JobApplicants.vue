<template>
<div class="max-w-2xl mx-auto px-4 py-5">
  <RouterLink :to="'/jobs/'+jobId" class="inline-flex items-center gap-1 text-sm text-ink-muted hover:text-ink mb-3">
    <AppIcon name="chevron-left" :size="16" /> 공고로 돌아가기
  </RouterLink>

  <h1 class="text-lg font-bold text-ink mb-4 flex items-center gap-2">
    <span class="icon-chip w-8 h-8 bg-amber-50 text-amber-600"><AppIcon name="users" :size="16" /></span>
    지원자 관리 <span v-if="job" class="text-ink-faint font-normal text-sm">— {{ job.title }}</span>
  </h1>

  <div v-if="loading" class="text-center py-12 text-ink-faint">로딩중...</div>
  <div v-else-if="!applicants.length" class="card p-8 text-center text-sm text-ink-faint">아직 지원자가 없습니다</div>
  <div v-else class="space-y-3">
    <div v-for="a in applicants" :key="a.id" class="card p-4">
      <div class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-2 min-w-0">
          <div class="w-9 h-9 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center font-bold flex-shrink-0">{{ (a.user?.nickname || a.user?.name || '?')[0] }}</div>
          <div class="min-w-0">
            <div class="text-sm font-semibold text-ink truncate">{{ a.user?.nickname || a.user?.name }}</div>
            <div class="text-[11px] text-ink-faint">{{ fmtDate(a.created_at) }} 지원</div>
          </div>
        </div>
        <span class="text-xs font-bold px-2 py-1 rounded-full flex-shrink-0" :class="statusStyle(a.status)">{{ statusLabel(a.status) }}</span>
      </div>

      <p v-if="a.message" class="text-sm text-ink-light mt-3 whitespace-pre-wrap">{{ a.message }}</p>

      <div v-if="a.resume" class="mt-3 bg-gray-50 rounded-xl p-3 text-xs space-y-1">
        <div class="font-bold text-ink flex items-center gap-1"><AppIcon name="briefcase" :size="12" /> {{ a.resume.title }}</div>
        <div v-if="a.resume.summary" class="text-ink-muted">{{ a.resume.summary }}</div>
        <div class="text-ink-faint flex flex-wrap gap-2 mt-1">
          <span v-if="a.resume.phone">{{ a.resume.phone }}</span>
          <span v-if="a.resume.email">{{ a.resume.email }}</span>
        </div>
      </div>

      <div class="flex gap-2 mt-3">
        <button v-if="a.status !== 'accepted'" @click="setStatus(a, 'accepted')" :disabled="busyId === a.id"
          class="flex-1 text-xs font-bold py-2 rounded-lg bg-emerald-500 text-white hover:bg-emerald-600 disabled:opacity-50 transition-colors">
          채용확정
        </button>
        <button v-if="a.status !== 'rejected'" @click="setStatus(a, 'rejected')" :disabled="busyId === a.id"
          class="flex-1 text-xs font-bold py-2 rounded-lg bg-gray-100 text-ink-light hover:bg-gray-200 disabled:opacity-50 transition-colors">
          불합격 처리
        </button>
        <span v-if="a.status === 'accepted' || a.status === 'rejected'" class="flex-1 text-center text-xs text-ink-faint py-2">처리 완료</span>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const route = useRoute()
const jobId = route.params.id
const job = ref(null)
const applicants = ref([])
const loading = ref(true)
const busyId = ref(null)

function fmtDate(dt) { return dt ? new Date(dt).toLocaleDateString('ko-KR') : '' }

function statusLabel(s) {
  return { pending: '대기중', viewed: '확인함', accepted: '채용확정', rejected: '불합격' }[s] || s
}
function statusStyle(s) {
  return {
    pending: 'bg-amber-50 text-amber-700',
    viewed: 'bg-blue-50 text-blue-700',
    accepted: 'bg-emerald-50 text-emerald-700',
    rejected: 'bg-gray-100 text-ink-faint',
  }[s] || 'bg-gray-100 text-ink-faint'
}

async function setStatus(a, status) {
  const label = status === 'accepted' ? '채용확정' : '불합격 처리'
  if (!confirm(`${a.user?.nickname || a.user?.name}님을 ${label} 처리하시겠습니까?`)) return
  busyId.value = a.id
  try {
    const { data } = await axios.post(`/api/jobs/${jobId}/applicants/${a.id}/status`, { status })
    a.status = data.data.status
  } catch (e) {
    alert(e.response?.data?.message || '처리 실패')
  }
  busyId.value = null
}

onMounted(async () => {
  try {
    const [{ data }, { data: jobData }] = await Promise.all([
      axios.get(`/api/jobs/${jobId}/applicants`),
      axios.get(`/api/jobs/${jobId}`),
    ])
    applicants.value = data.data || []
    job.value = jobData.data
  } catch {}
  loading.value = false
})
</script>
