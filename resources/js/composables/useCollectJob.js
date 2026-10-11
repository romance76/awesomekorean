// 관리자 "지금 수집"(음악·뉴스·숏츠·레시피) 공용 로직.
// 버튼을 누르면 서버가 바로 "수집을 시작했어요" 로 답하고 백그라운드(큐)에서 돈다.
// 이 composable 은 (1) 시작 요청, (2) GET /api/admin/collect-status 를 7초마다 확인해 진행·완료를 따라가기,
// (3) 화면을 떠나면 확인 중지, (4) 이미 돌고 있으면(409) 새로 시작하지 않고 진행 상황만 따라가기 를 맡는다.
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'

const POLL_MS = 7000                 // 5~10초 사이
const POLL_MAX_MS = 45 * 60 * 1000   // 이보다 오래 걸리면 확인 중단(화면 새로고침 안내)

function fmtTime(iso) {
  if (!iso) return ''
  try {
    return new Date(iso).toLocaleString('ko-KR', { month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit' })
  } catch { return '' }
}

/**
 * @param {object} opts
 * @param {string} opts.kind      'music' | 'news' | 'shorts' | 'recipes'
 * @param {string} opts.endpoint  기본 시작 주소 (예: /api/admin/fetch-music)
 * @param {(text:string, isError:boolean, phase:'start'|'done'|'error')=>void} [opts.onNotify]
 * @param {(last:object|null)=>void} [opts.onDone]  수집이 끝났을 때(성공·실패 모두) — 목록 새로고침용
 */
export function useCollectJob({ kind, endpoint, onNotify, onDone }) {
  const state = ref('idle')          // idle | queued | running
  const startedAt = ref(null)
  const last = ref(null)
  const busy = ref(false)            // 시작 요청을 보내는 중
  const now = ref(Date.now())
  const pollStopped = ref(false)     // 확인이 중단됨(오래 걸림·권한·네트워크)

  let timer = null
  let alive = true
  let halted = false
  let stopAt = 0
  let fails = 0

  const running = computed(() => busy.value || state.value !== 'idle')
  const elapsedMin = computed(() => startedAt.value
    ? Math.max(0, Math.floor((now.value - new Date(startedAt.value).getTime()) / 60000)) : 0)

  const runningText = computed(() => {
    if (pollStopped.value) return '진행 상황을 확인하지 못하고 있어요. 화면을 새로고침해 주세요.'
    if (state.value === 'idle') return '수집을 시작하는 중이에요…'
    if (state.value === 'queued') {
      return elapsedMin.value >= 3
        ? '대기열에서 순서를 기다리고 있어요. 오래 걸리면 서버의 작업 처리기를 확인해 주세요.'
        : '곧 시작돼요. 잠시만 기다려 주세요.'
    }
    return elapsedMin.value < 1
      ? '수집 중이에요. 끝나면 알려 드려요.'
      : `수집 중이에요 (${elapsedMin.value}분째). 끝나면 알려 드려요.`
  })

  const lastText = computed(() => {
    const l = last.value
    if (!l) return ''
    const when = fmtTime(l.finished_at)
    const base = `${l.ok ? '마지막 수집' : '마지막 수집(문제 있었음)'} ${when} · ${l.summary || ''}`
    return !l.ok && l.error ? `${base} — ${l.error}` : base
  })
  const lastOk = computed(() => !!last.value?.ok)

  function notify(text, isError, phase) {
    try { onNotify?.(text, isError, phase) } catch { /* 알림 실패가 수집 상태 표시를 막지 않게 */ }
  }

  function stopPolling() {
    if (timer) { clearTimeout(timer); timer = null }
  }
  function schedule() {
    stopPolling()
    if (!alive || halted) return
    timer = setTimeout(tick, POLL_MS)
  }
  function startPolling() {
    halted = false
    pollStopped.value = false
    fails = 0
    stopAt = Date.now() + POLL_MAX_MS
    schedule()
  }

  async function tick() {
    timer = null
    if (!alive || halted || state.value === 'idle') return
    if (Date.now() > stopAt) { halted = true; pollStopped.value = true; return }
    if (!document.hidden) await refresh()      // 탭이 가려져 있으면 요청을 아낀다
    if (alive && !halted && state.value !== 'idle' && !timer) schedule()
  }

  function apply(s) {
    now.value = Date.now()
    if (!s) return
    const wasActive = state.value !== 'idle'
    last.value = s.last || null
    startedAt.value = s.started_at || null
    state.value = s.running ? (s.state === 'running' ? 'running' : 'queued') : 'idle'
    if (wasActive && state.value === 'idle') {
      halted = true
      stopPolling()
      const l = last.value
      if (l) {
        const text = (l.summary || (l.ok ? '수집을 마쳤어요' : '수집에 실패했어요')) + (!l.ok && l.error ? ` (${l.error})` : '')
        notify(text, !l.ok, 'done')
      }
      try { onDone?.(l) } catch { /* 목록 새로고침 실패는 무시 */ }
    }
  }

  async function refresh() {
    try {
      const { data } = await axios.get('/api/admin/collect-status')
      fails = 0
      apply(data?.data?.[kind])
    } catch (e) {
      const st = e.response?.status
      fails += 1
      if (st === 401 || st === 403 || fails >= 5) {
        halted = true
        stopPolling()
        pollStopped.value = state.value !== 'idle'
      }
    }
  }

  /** 수집 시작. url/body 를 주면 기본 주소 대신 쓴다 (레시피 범위 동기화 등) */
  async function start(body = {}, url = endpoint) {
    if (running.value) return false
    busy.value = true
    try {
      const { data } = await axios.post(url, body)
      state.value = 'queued'
      startedAt.value = data?.started_at || new Date().toISOString()
      now.value = Date.now()
      notify(data?.message || '수집을 시작했어요. 끝나면 알려 드릴게요.', false, 'start')
      startPolling()
      return true
    } catch (e) {
      const d = e.response?.data
      notify(d?.message || '수집을 시작하지 못했어요. 잠시 뒤에 다시 시도해 주세요.', true, 'error')
      if (e.response?.status === 409) {
        // 이미 돌고 있음 — 새로 시작하지 않고 진행 상황만 따라간다
        await refresh()
        if (state.value !== 'idle') startPolling()
      }
      return false
    } finally {
      busy.value = false
    }
  }

  onMounted(async () => {
    await refresh()                                  // 화면을 열었을 때 이미 돌고 있으면 이어서 따라간다
    if (alive && state.value !== 'idle') startPolling()
  })
  onBeforeUnmount(() => {                            // 화면을 떠나면 확인 중지
    alive = false
    stopPolling()
  })

  return { state, running, startedAt, last, busy, elapsedMin, runningText, lastText, lastOk, pollStopped, start, refresh }
}
