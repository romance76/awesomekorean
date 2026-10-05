<!-- 관리자 대시보드/시스템 페이지에서 공용으로 쓰는 "전체 콘텐츠 자동 수집" 패널.
     버튼 클릭 시 /api/admin/system/sync-all-content 를 호출해 백그라운드로
     시작시키고, /status 를 2초 간격으로 폴링해 단계별(뉴스/레시피/중고장터/
     부동산/정보/쇼츠 등) 진행 상태를 실시간 체크리스트+진행률 바로 보여줌. -->
<template>
<div>
  <div class="flex items-center gap-2 flex-wrap">
    <button @click="start" :disabled="syncing" class="btn-primary !px-4 !py-2 text-sm disabled:opacity-50">
      <AppIcon name="refresh" :size="14" :class="{ 'animate-spin': syncing }" />{{ syncing ? '진행 중...' : buttonLabel }}
    </button>
    <span v-if="syncing && steps.length" class="text-xs text-ink-muted">{{ doneCount }}/{{ steps.length }} 완료</span>
  </div>

  <div v-if="steps.length" class="mt-3">
    <div class="h-1.5 bg-surface rounded-full overflow-hidden">
      <div class="h-full bg-amber-500 transition-all duration-500" :style="{ width: progressPct + '%' }"></div>
    </div>

    <div class="mt-3 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
      <div v-for="s in steps" :key="s.key"
        class="flex items-center gap-2 text-xs rounded-lg border px-2.5 py-2"
        :class="stepBoxClass(s.status)">
        <span class="shrink-0 w-4 h-4 flex items-center justify-center">
          <AppIcon v-if="s.status === 'done'" name="check" :size="13" class="text-green-600" />
          <AppIcon v-else-if="s.status === 'failed'" name="x" :size="13" class="text-red-600" />
          <AppIcon v-else-if="s.status === 'running'" name="refresh" :size="13" class="text-amber-500 animate-spin" />
          <AppIcon v-else name="clock" :size="13" class="text-ink-faint" />
        </span>
        <span class="truncate">
          {{ s.label }}
          <template v-if="s.key === 'info' && s.detail?.completed != null">
            ({{ s.detail.completed }}/{{ s.detail.target ?? 10 }})
          </template>
        </span>
      </div>
    </div>

    <div v-if="failedSteps.length" class="mt-2 text-xs text-red-600">
      실패: {{ failedSteps.map(s => s.label).join(', ') }} — 사유는 아래 로그 참고
    </div>
  </div>

  <div v-if="syncMsg" class="text-sm mt-2" :class="syncDone ? 'text-green-600' : 'text-ink-muted'">{{ syncMsg }}</div>

  <details v-if="syncLog" class="mt-2">
    <summary class="text-xs text-ink-muted cursor-pointer select-none">자세한 로그 보기</summary>
    <pre class="mt-2 text-xs bg-surface rounded-lg p-3 whitespace-pre-wrap max-h-64 overflow-y-auto">{{ syncLog }}</pre>
  </details>
</div>
</template>

<script setup>
import { ref, computed, onUnmounted } from 'vue'
import axios from 'axios'
import AppIcon from './AppIcon.vue'

defineProps({
  buttonLabel: { type: String, default: '전체 콘텐츠 자동 수집' },
})

const syncing = ref(false)
const syncMsg = ref('')
const syncLog = ref('')
const syncDone = ref(false)
const steps = ref([])
let poll = null
let timeout = null

const doneCount = computed(() => steps.value.filter(s => s.status === 'done' || s.status === 'failed').length)
const progressPct = computed(() => steps.value.length ? Math.round(doneCount.value / steps.value.length * 100) : 0)
const failedSteps = computed(() => steps.value.filter(s => s.status === 'failed'))

function stepBoxClass(status) {
  if (status === 'done') return 'border-green-200 bg-green-50 text-green-700'
  if (status === 'failed') return 'border-red-200 bg-red-50 text-red-700'
  if (status === 'running') return 'border-amber-300 bg-amber-50 text-amber-700'
  return 'border-gray-100 text-ink-muted'
}

async function start() {
  syncing.value = true
  syncDone.value = false
  syncLog.value = ''
  steps.value = []
  try {
    const { data } = await axios.post('/api/admin/system/sync-all-content')
    syncMsg.value = data.message || '시작됐습니다.'
  } catch (e) {
    alert(e.response?.data?.message || '시작 실패')
    syncing.value = false
    return
  }
  poll = setInterval(async () => {
    try {
      const { data } = await axios.get('/api/admin/system/sync-all-content/status')
      syncLog.value = data.log || ''
      steps.value = data.steps || []
      if (data.done) {
        clearInterval(poll)
        syncing.value = false
        syncDone.value = true
        syncMsg.value = '완료됐습니다.'
      }
    } catch {}
  }, 2000)
  timeout = setTimeout(() => { clearInterval(poll); syncing.value = false }, 10 * 60 * 1000)
}

onUnmounted(() => {
  clearInterval(poll)
  clearTimeout(timeout)
})
</script>
