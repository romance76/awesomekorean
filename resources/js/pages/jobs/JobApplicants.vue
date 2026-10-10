<template>
<div class="page-main px-4 py-5">
  <DetailHeader title="지원자 관리" :fallback="'/jobs/'+jobId" />
  <div class="hidden lg:block"><PageHeader title="지원자 관리" :subtitle="job?.title || ''" icon="users" :to="'/jobs/'+jobId" /></div>

  <div v-if="loading" class="text-center py-12 text-ink-faint">로딩중...</div>
  <div v-else-if="!applicants.length" class="card p-8 text-center text-sm text-ink-faint">아직 지원자가 없습니다</div>
  <div v-else class="space-y-3">
    <div v-for="a in applicants" :key="a.id" class="card p-4">
      <div class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-2 min-w-0">
          <UserAvatar :user="a.user" :size="60" />
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
import { useModal } from '../../composables/useModal'
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
import UserAvatar from '../../components/UserAvatar.vue'
import PageHeader from '../../components/PageHeader.vue'
import DetailHeader from '../../components/DetailHeader.vue'
const { showConfirm } = useModal()

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
  if (!await showConfirm(`${a.user?.nickname || a.user?.name}님을 ${label} 처리하시겠습니까?`)) return
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
