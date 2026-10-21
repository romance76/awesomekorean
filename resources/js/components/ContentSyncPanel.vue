<!-- 관리자 대시보드/시스템 페이지에서 공용으로 쓰는 "전체 콘텐츠 자동 수집" 패널.
     버튼 클릭 시 /api/admin/system/sync-all-content 를 호출해 백그라운드로
     시작시키고, /status 를 2초 간격으로 폴링해 단계별(뉴스/레시피/중고장터/
     부동산/정보/쇼츠 등) 진행 상태를 실시간 체크리스트+진행률 바로 보여줌. -->
<template>
<div>
  <!-- 접힌 상태: 한 줄 요약. 누르면 펼쳐져서 지금 수집 버튼·자동 수집 현황이 보임 -->
  <button type="button" @click="open = !open" :aria-expanded="open"
    class="w-full flex items-center gap-2 text-left">
    <span class="icon-chip w-8 h-8 bg-amber-50 text-amber-600 shrink-0"><AppIcon name="refresh" :size="15" :class="{ 'animate-spin': syncing }" /></span>
    <span class="font-bold text-sm text-ink">콘텐츠 자동 수집</span>
    <span v-if="syncing" class="text-xs text-amber-700 font-bold">진행 중 {{ doneCount }}/{{ steps.length }}</span>
    <span v-else-if="autoRows.length" class="flex items-center gap-1.5 text-[11px]">
      <span class="inline-flex items-center gap-1 text-green-700"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>정상 {{ countOf('ok') + countOf('never') }}</span>
      <span v-if="countOf('late')" class="inline-flex items-center gap-1 text-amber-700 font-bold"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>확인 {{ countOf('late') }}</span>
      <span v-if="countOf('fail')" class="inline-flex items-center gap-1 text-red-600 font-bold"><span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>실패 {{ countOf('fail') }}</span>
    </span>
    <span class="flex-1"></span>
    <AppIcon :name="open ? 'chevron-up' : 'chevron-down'" :size="16" class="text-ink-muted shrink-0" />
  </button>

  <div v-show="open" class="mt-3">
    <div class="flex items-center gap-2 flex-wrap">
      <button @click="start" :disabled="syncing" class="btn-primary !px-3.5 !py-1.5 text-xs disabled:opacity-50">
        <AppIcon name="refresh" :size="13" :class="{ 'animate-spin': syncing }" />{{ syncing ? '진행 중...' : buttonLabel }}
      </button>
      <button @click="loadAuto" :disabled="autoLoading" class="text-xs text-amber-700 font-bold disabled:opacity-50">{{ autoLoading ? '불러오는 중...' : '새로고침' }}</button>
      <span class="text-[11px] text-ink-muted">버튼은 모든 항목을 지금 한 번에 돌려요. 평소에는 서버가 알아서 돌려요.</span>
    </div>

    <!-- 이번에 누른 수동 실행의 진행 상황 (끝나면 사라지고, 예전 기록은 보여 주지 않음) -->
    <div v-if="syncing && steps.length" class="mt-3">
      <div class="h-1.5 bg-surface rounded-full overflow-hidden">
        <div class="h-full bg-amber-500 transition-all duration-500" :style="{ width: progressPct + '%' }"></div>
      </div>
      <div class="mt-2 flex flex-wrap gap-1.5">
        <span v-for="s in steps" :key="s.key" class="inline-flex items-center gap-1 text-[11px] rounded-full border px-2 py-0.5" :class="stepBoxClass(s.status)">
          <AppIcon v-if="s.status === 'done'" name="check" :size="11" />
          <AppIcon v-else-if="s.status === 'failed'" name="x" :size="11" />
          <AppIcon v-else-if="s.status === 'running'" name="refresh" :size="11" class="animate-spin" />
          {{ s.label }}<template v-if="s.status === 'running'"> ({{ elapsedLabel(s) }})</template>
        </span>
      </div>
      <div v-if="failedSteps.length" class="mt-1.5 text-[11px] text-red-600">이번 실행에서 실패: {{ failedSteps.map(s => s.label).join(', ') }}</div>
      <div v-if="stalled" class="mt-2 text-xs text-amber-600 flex items-center gap-2 flex-wrap">
        <span>진행이 멈춘 것 같습니다 — 배포 등으로 서버가 재시작되면서 기존 작업이 끊겼을 수 있어요.</span>
        <button @click="start" class="text-amber-700 font-bold underline shrink-0">지금 다시 시작하기</button>
      </div>
    </div>
    <div v-if="syncMsg" class="text-xs mt-2" :class="syncDone ? 'text-green-600' : 'text-ink-muted'">{{ syncMsg }}</div>
    <details v-if="syncLog && (syncing || syncDone)" class="mt-1.5">
      <summary class="text-[11px] text-ink-muted cursor-pointer select-none">이번 실행 로그</summary>
      <pre class="mt-1.5 text-[11px] bg-surface rounded-lg p-2.5 whitespace-pre-wrap max-h-48 overflow-y-auto">{{ syncLog }}</pre>
    </details>

    <!-- 자동(스케줄러) 수집 현황: 항목마다 한 줄 -->
    <div v-if="autoPaused" class="mt-3 text-xs rounded-lg bg-amber-50 text-amber-800 px-3 py-2">비상 스위치로 자동 수집(음악·뉴스·쇼츠)이 멈춰 있어요.</div>
    <div v-if="autoError" class="mt-3 text-xs text-red-600">{{ autoError }}</div>
    <div v-else-if="autoRows.length" class="mt-3 grid grid-cols-1 xl:grid-cols-2 gap-x-4">
      <div v-for="r in autoRows" :key="r.key" class="flex items-start gap-2 py-1.5 border-b border-gray-100 text-xs">
        <span class="mt-1 w-2 h-2 rounded-full shrink-0" :class="autoDot(r.state)"></span>
        <div class="min-w-0 flex-1">
          <div class="flex items-baseline justify-between gap-2">
            <span class="font-semibold text-ink truncate">{{ r.label }}</span>
            <span class="text-[11px] shrink-0" :class="r.state === 'fail' ? 'text-red-600 font-bold' : r.state === 'late' ? 'text-amber-700 font-bold' : 'text-ink-muted'">
              {{ r.state === 'ok' || r.state === 'never' ? (r.last_ok_at ? ago(r.last_ok_at) : '기록 대기') : autoStateLabel(r) }}
            </span>
          </div>
          <div class="text-[11px] text-ink-muted truncate">{{ r.schedule }}</div>
          <div v-if="r.state === 'fail' && r.last_fail_at" class="text-[11px] text-red-600">마지막 실패 {{ ago(r.last_fail_at) }}<template v-if="r.last_ok_at"> · 마지막 성공 {{ ago(r.last_ok_at) }}</template></div>
          <div v-else-if="r.state === 'late' && r.has_content_metric" class="text-[11px] text-amber-700">새 콘텐츠가 마지막으로 들어온 때: {{ r.latest_content_at ? ago(r.latest_content_at) : '없음' }}</div>
        </div>
      </div>
    </div>
    <div v-else-if="autoLoading" class="mt-3 text-xs text-ink-muted">불러오는 중...</div>

    <details v-if="autoLog" class="mt-2">
      <summary class="text-[11px] text-ink-muted cursor-pointer select-none">자동 수집 로그 끝부분 (실패 이유 확인용)</summary>
      <pre class="mt-1.5 text-[11px] bg-surface rounded-lg p-2.5 whitespace-pre-wrap break-words max-h-72 overflow-y-auto">{{ autoLog }}</pre>
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

const open = ref(false)
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
const countOf = st => autoRows.value.filter(r => r.state === st).length
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
    const bad = failedSteps.value.map(x => x.label)
    syncMsg.value = bad.length ? '완료됐습니다. (실패 ' + bad.length + '건: ' + bad.join(', ') + ')' : '완료됐습니다.'
    steps.value = []
    loadAuto()
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
    if (!data.steps || !data.steps.length || data.done) return   // 이미 끝난 예전 실행은 보여 주지 않음
    syncLog.value = data.log || ''
    steps.value = data.steps
    if (data.updated_at) lastUpdatedAt.value = new Date(data.updated_at).getTime()
    syncing.value = true
    open.value = true
    syncMsg.value = '백그라운드에서 계속 진행 중입니다.'
    beginPolling()
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
