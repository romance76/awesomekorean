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
          <template v-if="s.status === 'running'">({{ elapsedLabel(s) }})</template>
          <template v-else-if="s.key === 'info' && s.status !== 'pending' && s.detail?.completed != null">
            ({{ s.detail.completed }}/{{ s.detail.target ?? 10 }})
          </template>
        </span>
      </div>
    </div>

    <div v-if="failedSteps.length" class="mt-2 text-xs text-red-600">
      실패: {{ failedSteps.map(s => s.label).join(', ') }} — 사유는 아래 로그 참고
    </div>

    <div v-if="stalled" class="mt-2 text-xs text-amber-600 flex items-center gap-2 flex-wrap">
      <span>진행이 멈춘 것 같습니다 — 배포 등으로 서버가 재시작되면서 기존 작업이 끊겼을 수 있어요.</span>
      <button @click="start" class="text-amber-700 font-bold underline shrink-0">지금 다시 시작하기</button>
    </div>
  </div>

  <div v-if="syncMsg" class="text-sm mt-2" :class="syncDone ? 'text-green-600' : 'text-ink-muted'">{{ syncMsg }}</div>

  <details v-if="syncLog" class="mt-2">
    <summary class="text-xs text-ink-muted cursor-pointer select-none">자세한 로그 보기</summary>
    <pre class="mt-2 text-xs bg-surface rounded-lg p-3 whitespace-pre-wrap max-h-64 overflow-y-auto">{{ syncLog }}</pre>
  </details>

  <!-- 자동(스케줄러) 수집 현황 — 위의 체크리스트는 버튼을 누른 수동 실행의 기록이고, 자동 수집은 여기에 나온다 -->
  <div class="mt-4 pt-3 border-t border-gray-100">
    <div class="flex items-center justify-between gap-2 flex-wrap">
      <div class="text-sm font-bold text-ink">자동 수집 현황</div>
      <button @click="loadAuto" :disabled="autoLoading" class="text-xs text-amber-700 font-bold disabled:opacity-50">{{ autoLoading ? '불러오는 중...' : '새로고침' }}</button>
    </div>
    <div class="text-[11px] text-ink-muted mt-0.5 leading-relaxed">서버가 알아서 돌리는 수집이에요. 위 체크리스트는 버튼을 누른 때의 기록만 보여서 날짜가 오래돼 보일 수 있어요.</div>
    <div v-if="autoPaused" class="mt-2 text-xs rounded-lg bg-amber-50 text-amber-800 px-3 py-2">비상 스위치로 자동 수집(음악·뉴스·쇼츠)이 멈춰 있어요.</div>

    <div v-if="autoError" class="mt-2 text-xs text-red-600">{{ autoError }}</div>
    <div v-else-if="autoRows.length" class="mt-2 space-y-1.5">
      <div v-for="r in autoRows" :key="r.key" class="flex items-start gap-2 text-xs rounded-lg border px-2.5 py-2" :class="autoBox(r.state)">
        <span class="mt-1 w-2 h-2 rounded-full shrink-0" :class="autoDot(r.state)"></span>
        <div class="min-w-0 flex-1">
          <div class="flex items-center justify-between gap-2 flex-wrap">
            <span class="font-semibold">{{ r.label }}</span>
            <span class="text-[11px]" :class="r.state === 'fail' ? 'text-red-600 font-bold' : r.state === 'late' ? 'text-amber-700 font-bold' : 'text-ink-muted'">{{ autoStateLabel(r) }}</span>
          </div>
          <div class="text-[11px] text-ink-muted mt-0.5">
            {{ r.schedule }}
            <template v-if="r.last_ok_at"> · 마지막 성공 {{ ago(r.last_ok_at) }}</template>
            <template v-else-if="r.state === 'never'"> · 아직 자동 실행 기록이 없어요 (다음 실행부터 기록돼요)</template>
            <template v-if="r.last_fail_at && r.state === 'fail'"> · 마지막 실패 {{ ago(r.last_fail_at) }}</template>
          </div>
          <div v-if="r.has_content_metric" class="text-[11px] mt-0.5" :class="r.state === 'late' ? 'text-amber-700' : 'text-ink-muted'">
            새 콘텐츠가 마지막으로 들어온 때: {{ r.latest_content_at ? ago(r.latest_content_at) : '없음' }}
          </div>
        </div>
      </div>
    </div>
    <div v-else-if="autoLoading" class="mt-2 text-xs text-ink-muted">불러오는 중...</div>

    <details v-if="autoLog" class="mt-2">
      <summary class="text-xs text-ink-muted cursor-pointer select-none">자동 수집 로그 끝부분 (실패 이유 확인용)</summary>
      <pre class="mt-2 text-[11px] bg-surface rounded-lg p-3 whitespace-pre-wrap break-words max-h-72 overflow-y-auto">{{ autoLog }}</pre>
    </details>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
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
const now = ref(Date.now())
const lastUpdatedAt = ref(null)
let poll = null
let timeout = null
let tick = null

const doneCount = computed(() => steps.value.filter(s => s.status === 'done' || s.status === 'failed').length)
const progressPct = computed(() => steps.value.length ? Math.round(doneCount.value / steps.value.length * 100) : 0)
const failedSteps = computed(() => steps.value.filter(s => s.status === 'failed'))
// 서버가 진행 파일에 마지막으로 쓴 시각(updated_at) 기준으로 멈춤 여부를
// 판단 — 처음엔 클라이언트에서 "steps가 바뀐 지 얼마나 됐나"로 추적했는데,
// 그러면 페이지를 새로고침할 때마다 추적 시작점이 "지금"으로 리셋돼버려서
// 실제로 몇 분째 멈춰있어도 새로고침만 하면 다시 안 멈춘 것처럼 보이는 문제가
// 있었음. 뉴스 수집처럼 정상적으로 몇 분씩 걸리는 단계도 있어 임계값을
// 넉넉하게 잡음.
const stalled = computed(() => {
  if (!syncing.value || !steps.value.length || !lastUpdatedAt.value) return false
  return (now.value - lastUpdatedAt.value) > 8 * 60 * 1000
})

function elapsedLabel(step) {
  if (!step.started_at) return '0초'
  const sec = Math.max(0, Math.floor((now.value - new Date(step.started_at).getTime()) / 1000))
  return sec < 60 ? `${sec}초` : `${Math.floor(sec / 60)}분 ${sec % 60}초`
}

function stepBoxClass(status) {
  if (status === 'done') return 'border-green-200 bg-green-50 text-green-700'
  if (status === 'failed') return 'border-red-200 bg-red-50 text-red-700'
  if (status === 'running') return 'border-amber-300 bg-amber-50 text-amber-700'
  return 'border-gray-100 text-ink-muted'
}

async function pollOnce() {
  const { data } = await axios.get('/api/admin/system/sync-all-content/status')
  syncLog.value = data.log || ''
  steps.value = data.steps || []
  if (data.updated_at) lastUpdatedAt.value = new Date(data.updated_at).getTime()
  if (data.done) {
    clearInterval(poll)
    clearInterval(tick)
    syncing.value = false
    syncDone.value = true
    syncMsg.value = '완료됐습니다.'
  }
}

function beginPolling() {
  tick = setInterval(() => { now.value = Date.now() }, 1000)
  poll = setInterval(() => { pollOnce().catch(() => {}) }, 2000)
  // 각 단계가 몇 분씩 걸릴 수 있어(뉴스 수집은 10분 넘게 걸리기도 함) 중간에
  // 포기하지 않도록 넉넉하게 — 멈춘 것 같으면 위의 stalled 안내로 알려줌.
  timeout = setTimeout(() => { clearInterval(poll); clearInterval(tick); syncing.value = false }, 60 * 60 * 1000)
}

async function start() {
  // "지금 다시 시작하기"는 이미 폴링 중인(죽은 작업을 계속 지켜보던) 상태에서도
  // 눌릴 수 있어 — 기존 interval을 먼저 정리해야 중복으로 쌓이지 않음.
  clearInterval(poll)
  clearInterval(tick)
  clearTimeout(timeout)
  syncing.value = true
  syncDone.value = false
  syncLog.value = ''
  steps.value = []
  lastUpdatedAt.value = null
  try {
    const { data } = await axios.post('/api/admin/system/sync-all-content')
    syncMsg.value = data.message || '시작됐습니다.'
  } catch (e) {
    alert(e.response?.data?.message || '시작 실패')
    syncing.value = false
    return
  }
  beginPolling()
  pollOnce().catch(() => {})
}

// 새로고침하면 진행 중이던 체크리스트가 사라지던 문제 — 수집 자체는 서버
// 백그라운드에서 계속 돌고 있는데 화면 상태(steps/syncing 등)는 이 컴포넌트의
// 메모리에만 있어서 페이지를 새로고침하면 초기화돼버렸음. 마운트 시 현재
// 진행 상황을 한 번 조회해서, 아직 끝나지 않았으면 그대로 이어서 폴링을
// 재개하고, 이미 끝났으면 마지막 실행 결과를 그대로 보여줌.
async function checkExisting() {
  try {
    const { data } = await axios.get('/api/admin/system/sync-all-content/status')
    if (!data.steps || !data.steps.length) return
    syncLog.value = data.log || ''
    steps.value = data.steps
    if (data.updated_at) lastUpdatedAt.value = new Date(data.updated_at).getTime()
    if (data.done) {
      syncDone.value = true
      syncMsg.value = '완료됐습니다.'
    } else {
      syncing.value = true
      syncMsg.value = '백그라운드에서 계속 진행 중입니다.'
      beginPolling()
    }
  } catch {}
}

// ─── 자동 수집 현황 ───
const autoRows = ref([])
const autoLog = ref('')
const autoPaused = ref(false)
const autoLoading = ref(false)
const autoError = ref('')

async function loadAuto() {
  autoLoading.value = true
  autoError.value = ''
  try {
    const { data } = await axios.get('/api/admin/system/auto-collect-status')
    autoRows.value = data.data || []
    autoLog.value = data.log_tail || ''
    autoPaused.value = !!data.paused
  } catch (e) {
    autoError.value = e.response?.data?.message || '자동 수집 현황을 불러오지 못했어요'
  }
  autoLoading.value = false
}

// "3시간 전", "2일 전"
function ago(iso) {
  const t = new Date(iso).getTime()
  if (!t) return ''
  const sec = Math.max(0, Math.floor((Date.now() - t) / 1000))
  if (sec < 90) return '방금'
  if (sec < 3600) return `${Math.floor(sec / 60)}분 전`
  if (sec < 86400) return `${Math.floor(sec / 3600)}시간 전`
  const d = Math.floor(sec / 86400)
  return `${d}일 전 (${new Date(t).getMonth() + 1}/${new Date(t).getDate()})`
}
const autoStateLabel = r => ({ ok: '정상', late: '새 콘텐츠가 오래 없어요', fail: '마지막 실행 실패', never: '기록 대기' }[r.state] || r.state)
const autoDot = s => ({ ok: 'bg-green-500', late: 'bg-amber-500', fail: 'bg-red-500', never: 'bg-gray-300' }[s] || 'bg-gray-300')
const autoBox = s => ({ ok: 'border-gray-100', late: 'border-amber-300 bg-amber-50/50', fail: 'border-red-200 bg-red-50/50', never: 'border-gray-100' }[s] || 'border-gray-100')

onMounted(() => { checkExisting(); loadAuto() })

onUnmounted(() => {
  clearInterval(poll)
  clearInterval(tick)
  clearTimeout(timeout)
})
</script>
