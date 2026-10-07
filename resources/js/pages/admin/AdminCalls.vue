<template>
<div>
  <div class="flex items-start justify-between flex-wrap gap-2 mb-4">
    <div>
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
        <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="phone" :size="20" /></span>
        통화 로그
      </h1>
      <p class="text-xs text-ink-muted mt-1">누가 누구에게 걸었는지, 받았는지, 왜 끊겼는지, 어떤 연결(직접/중계)이었고 지연은 얼마였는지 보여줘요. 시간은 미국 동부시간(ET) 기준이에요.</p>
    </div>
    <div class="flex gap-1">
      <button v-for="r in ranges" :key="r.days" @click="days = r.days; page = 1; reload()"
        class="px-3 py-1.5 rounded-full text-xs font-bold border transition-colors"
        :class="days === r.days ? 'bg-ink text-white border-ink' : 'bg-white text-ink-light border-gray-200'">{{ r.label }}</button>
    </div>
  </div>

  <!-- 연결 진단: 지금 쓰는 기기/네트워크에서 중계(TURN) 서버가 실제로 쓸 수 있는지 시험 -->
  <div class="card p-3 mb-3">
    <div class="flex items-center justify-between gap-2 flex-wrap">
      <div>
        <div class="text-sm font-bold text-ink">🔧 통화 연결 진단</div>
        <div class="text-[11px] text-ink-muted">지금 보고 있는 기기(폰이면 폰, 와이파이/LTE 각각)에서 통화 서버에 닿는지 시험해요. 중계 서버(TURN)가 안 되면 폰↔PC 통화가 "연결중"에서 멈춥니다.</div>
      </div>
      <button @click="runDiag" :disabled="diagRunning" class="px-3 py-1.5 rounded-lg bg-ink text-white text-xs font-bold disabled:opacity-50">{{ diagRunning ? '시험 중…(최대 25초)' : '진단 시작' }}</button>
    </div>
    <div v-if="diag.length" class="mt-2 space-y-1">
      <div v-for="d in diag" :key="d.name" class="text-xs flex items-start gap-2">
        <span>{{ d.ok ? '✅' : '❌' }}</span>
        <span class="font-semibold text-ink w-40 flex-shrink-0">{{ d.name }}</span>
        <span class="text-ink-light">{{ d.detail }}</span>
      </div>
      <div v-if="diagAdvice" class="mt-2 text-xs rounded-lg px-3 py-2" :class="diagAdviceBad ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700'">{{ diagAdvice }}</div>
    </div>
  </div>

  <!-- 통계 -->
  <div class="grid grid-cols-2 md:grid-cols-5 gap-2 mb-2">
    <div class="card p-3"><div class="text-xs text-ink-muted">전체 시도</div><div class="text-xl font-black text-ink">{{ stats.total ?? 0 }}</div></div>
    <div class="card p-3"><div class="text-xs text-ink-muted">연결 성공</div><div class="text-xl font-black text-green-600">{{ stats.connected ?? 0 }} <span class="text-xs font-bold">({{ stats.connect_rate ?? 0 }}%)</span></div></div>
    <div class="card p-3"><div class="text-xs text-ink-muted">응답 없음/취소</div><div class="text-xl font-black text-amber-600">{{ stats.unanswered ?? 0 }}</div></div>
    <div class="card p-3"><div class="text-xs text-ink-muted">거절</div><div class="text-xl font-black text-gray-600">{{ stats.declined ?? 0 }}</div></div>
    <div class="card p-3"><div class="text-xs text-ink-muted">연결 실패</div><div class="text-xl font-black text-red-600">{{ stats.failed ?? 0 }}</div></div>
  </div>
  <div class="grid grid-cols-2 md:grid-cols-5 gap-2 mb-4">
    <div class="card p-3"><div class="text-xs text-ink-muted">상대 오프라인</div><div class="text-lg font-black text-ink">{{ stats.offline ?? 0 }}</div></div>
    <div class="card p-3"><div class="text-xs text-ink-muted">통화 중이라 못 받음</div><div class="text-lg font-black text-ink">{{ stats.busy ?? 0 }}</div></div>
    <div class="card p-3"><div class="text-xs text-ink-muted">평균 통화시간</div><div class="text-lg font-black text-purple-600">{{ fmtSec(stats.avg_duration || 0) }}</div></div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">평균 지연(왕복)</div>
      <div class="text-lg font-black" :class="(stats.avg_rtt_ms || 0) > 300 ? 'text-red-600' : 'text-blue-600'">{{ stats.avg_rtt_ms ? stats.avg_rtt_ms + 'ms' : '-' }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">직접 / 중계 연결</div>
      <div class="text-lg font-black text-ink">{{ stats.direct ?? 0 }} / <span :class="(stats.relay || 0) > (stats.direct || 0) ? 'text-red-600' : ''">{{ stats.relay ?? 0 }}</span></div>
    </div>
  </div>
  <p class="text-[11px] text-ink-faint -mt-2 mb-4">중계 연결이 많으면 서로 직접 연결이 안 되는 네트워크가 많다는 뜻이고, 이때 음성이 늦게 들릴 수 있어요. 지연은 300ms가 넘으면 대화가 답답해져요.</p>

  <div v-if="stats.fail_notes?.length" class="card p-3 mb-4">
    <div class="text-xs font-bold text-ink mb-1.5">자주 나온 실패 원인</div>
    <div v-for="n in stats.fail_notes" :key="n.failure_note" class="flex justify-between text-xs py-0.5">
      <span class="text-ink-light break-all">{{ noteLabel(n.failure_note) }}</span><span class="font-bold text-red-600 ml-3">{{ n.c }}건</span>
    </div>
  </div>

  <!-- 필터 -->
  <div class="flex gap-2 mb-3 flex-wrap">
    <select v-model="outcome" @change="page = 1; load()" class="input-soft !w-auto !px-3 !py-1.5 !text-xs">
      <option value="">전체 결과</option>
      <option value="connected">연결 성공</option>
      <option value="unanswered">응답 없음/취소</option>
      <option value="declined">거절</option>
      <option value="offline">상대 오프라인</option>
      <option value="busy">통화 중</option>
      <option value="failed">연결 실패</option>
    </select>
    <select v-model="conn" @change="page = 1; load()" class="input-soft !w-auto !px-3 !py-1.5 !text-xs">
      <option value="">연결 방식 전체</option>
      <option value="direct">직접 연결</option>
      <option value="relay">중계 연결</option>
    </select>
    <select v-model="type" @change="page = 1; reload()" class="input-soft !w-auto !px-3 !py-1.5 !text-xs">
      <option value="">전체 유형</option>
      <option value="friend">친구 통화</option>
      <option value="elder">안심서비스</option>
    </select>
    <input v-model="search" @keyup.enter="page = 1; load()" placeholder="이름/닉네임/이메일 검색" class="input-soft flex-1 min-w-[160px] !w-auto !px-3 !py-1.5 !text-xs" />
    <button @click="page = 1; load()" class="btn-primary !px-4 !py-1.5 !text-xs">검색</button>
  </div>

  <!-- 목록 -->
  <div v-if="loading" class="text-center py-8 text-ink-muted">로딩중...</div>
  <div v-else-if="!calls.length" class="py-16 text-center">
    <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="phone" :size="28" :stroke-width="1.5" /></div>
    <p class="text-sm text-ink-muted">해당하는 통화 기록이 없습니다</p>
  </div>
  <div v-else class="card overflow-x-auto">
    <table class="w-full text-xs min-w-[900px]">
      <thead class="bg-gray-50 border-b border-gray-50">
        <tr>
          <th class="px-3 py-2 text-left text-ink-muted">시간(ET)</th>
          <th class="px-3 py-2 text-center text-ink-muted">유형</th>
          <th class="px-3 py-2 text-left text-ink-muted">발신 → 수신</th>
          <th class="px-3 py-2 text-center text-ink-muted">결과</th>
          <th class="px-3 py-2 text-center text-ink-muted">통화시간</th>
          <th class="px-3 py-2 text-center text-ink-muted">벨 대기</th>
          <th class="px-3 py-2 text-center text-ink-muted">연결</th>
          <th class="px-3 py-2 text-left text-ink-muted">기기 (발신 / 수신)</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="c in calls" :key="c.id" class="border-b border-gray-50 last:border-0 hover:bg-amber-50/40 transition-colors align-top">
          <td class="px-3 py-2 text-ink-muted whitespace-nowrap">{{ fmtDate(c.created_at) }}<div class="text-[10px] text-ink-faint">#{{ c.id }}</div></td>
          <td class="px-3 py-2 text-center">
            <span class="text-[11px] px-2 py-0.5 rounded-full font-bold" :class="c.call_type === 'elder' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'">{{ c.call_type === 'elder' ? '안심' : '친구' }}</span>
          </td>
          <td class="px-3 py-2">
            <div class="font-bold text-ink">{{ c.caller?.name || '-' }} <span class="text-ink-faint font-normal">→</span> {{ c.callee?.name || '-' }}</div>
            <div class="text-[10px] text-ink-faint">{{ c.caller?.email }} → {{ c.callee?.email }}</div>
          </td>
          <td class="px-3 py-2 text-center">
            <span class="text-[11px] px-2 py-0.5 rounded-full font-bold whitespace-nowrap" :class="resultCls(c)">{{ resultLabel(c) }}</span>
            <div v-if="c.ended_by && !(c.answered_at === null && c.end_reason === 'offline')" class="text-[10px] text-ink-faint mt-0.5">{{ { caller: '발신자가 끊음', callee: '수신자가 끊음', system: '자동 정리' }[c.ended_by] }}</div>
            <div v-if="c.note" class="text-[10px] text-red-500 mt-0.5 max-w-[220px] break-all">{{ noteLabel(c.note) }}</div>
          </td>
          <td class="px-3 py-2 text-center font-bold" :class="c.duration > 0 ? 'text-green-700' : 'text-ink-faint'">{{ c.duration > 0 ? fmtSec(c.duration) : '-' }}</td>
          <td class="px-3 py-2 text-center text-ink-muted">{{ c.ring_seconds != null ? c.ring_seconds + '초' : '-' }}</td>
          <td class="px-3 py-2 text-center">
            <template v-if="c.conn_type">
              <span class="text-[11px] px-2 py-0.5 rounded-full font-bold" :class="c.conn_type === 'relay' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'">{{ c.conn_type === 'relay' ? '중계' : '직접' }}</span>
              <div v-if="c.rtt_ms != null" class="text-[10px] mt-0.5" :class="c.rtt_ms > 300 ? 'text-red-500 font-bold' : 'text-ink-faint'">{{ c.rtt_ms }}ms</div>
            </template>
            <span v-else class="text-ink-faint">-</span>
          </td>
          <td class="px-3 py-2 text-ink-muted">{{ c.caller_device || '-' }}<div class="text-ink-faint">{{ c.callee_device || '-' }}</div></td>
        </tr>
      </tbody>
    </table>

    <div v-if="lastPage > 1" class="flex justify-center gap-1 py-3 border-t border-gray-50 flex-wrap">
      <button v-for="p in lastPage" :key="p" @click="page = p; load()" class="w-8 h-8 rounded-lg text-xs font-bold transition-colors"
        :class="p === page ? 'bg-amber-400 text-white' : 'text-ink-muted hover:bg-gray-100'">{{ p }}</button>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const ranges = [{ days: 1, label: '오늘' }, { days: 7, label: '7일' }, { days: 30, label: '30일' }, { days: 90, label: '90일' }]
const days = ref(7)
const calls = ref([])
const stats = ref({})
const loading = ref(true)
const outcome = ref('')
const conn = ref('')
const type = ref('')
const search = ref('')
const page = ref(1)
const lastPage = ref(1)

function fmtSec(s) {
  if (!s) return '0초'
  const m = Math.floor(s / 60), sec = s % 60
  return m > 0 ? `${m}분 ${sec}초` : `${sec}초`
}
function fmtDate(dt) {
  if (!dt) return ''
  return new Date(dt).toLocaleString('ko-KR', { timeZone: 'America/New_York', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false })
}

const REASON = {
  completed: '통화 완료', declined: '거절', cancelled: '취소', no_answer: '응답 없음', offline: '상대 오프라인',
  busy: '통화 중', failed: '연결 실패', stale: '자동 종료',
}
function resultLabel(c) {
  if (c.answered_at && c.end_reason !== 'failed') return '통화 완료'
  return REASON[c.end_reason] || (c.status === 'ringing' ? '벨 울리는 중' : c.status)
}
function resultCls(c) {
  if (c.answered_at && c.end_reason !== 'failed') return 'bg-green-100 text-green-700'
  if (c.end_reason === 'failed') return 'bg-red-100 text-red-700'
  if (c.end_reason === 'declined') return 'bg-gray-100 text-gray-600'
  return 'bg-amber-100 text-amber-700'
}
const NOTE = {
  ice_failed: '네트워크 연결 실패(ICE)', ice_failed_midcall: '통화 중 연결 끊김', ice_disconnected: '통화 중 연결 끊김(응답 없음)',
  connect_timeout_caller: '연결 시간 초과(발신 쪽)', connect_timeout_callee: '연결 시간 초과(수신 쪽)',
  no_mic_callee: '수신자 마이크 사용 불가', busy: '이미 통화 중', caller_busy: '발신자가 이미 통화 중',
}
function noteLabel(n) {
  if (!n) return ''
  const [head, ...rest] = String(n).split(' | ')
  const key = head.split(':')[0]
  const label = NOTE[head] || NOTE[key] || head
  return rest.length ? `${label} (${rest.join(' ')})` : label
}

async function load() {
  loading.value = true
  try {
    const params = { page: page.value, days: days.value }
    if (outcome.value) params.outcome = outcome.value
    if (conn.value) params.conn = conn.value
    if (type.value) params.type = type.value
    if (search.value) params.search = search.value
    const { data } = await axios.get('/api/admin/calls', { params })
    calls.value = data.data?.data || []
    lastPage.value = data.data?.last_page || 1
  } catch {}
  loading.value = false
}
async function loadStats() {
  try {
    const { data } = await axios.get('/api/admin/calls/stats', { params: { days: days.value, ...(type.value ? { type: type.value } : {}) } })
    stats.value = data.data || {}
  } catch {}
}
function reload() { load(); loadStats() }

// ── 연결 진단 ─────────────────────────────────────────────────
const diag = ref([])
const diagRunning = ref(false)
const diagAdvice = ref('')
const diagAdviceBad = ref(false)

/** 주어진 ICE 서버로 후보를 모아서 어떤 종류(host/srflx/relay)가 나오는지 본다 */
function gather(iceServers, relayOnly, ms = 7000) {
  return new Promise(resolve => {
    const types = {}
    let pc
    try { pc = new RTCPeerConnection({ iceServers, iceTransportPolicy: relayOnly ? 'relay' : 'all' }) } catch (e) { return resolve({ error: e.message, types }) }
    const done = () => { try { pc.close() } catch {} resolve({ types }) }
    const t = setTimeout(done, ms)
    pc.onicecandidate = (e) => {
      if (!e.candidate) { clearTimeout(t); return done() }
      const ty = e.candidate.type || (/ typ (\w+)/.exec(e.candidate.candidate || '') || [])[1]
      if (ty) types[ty] = (types[ty] || 0) + 1
    }
    pc.createDataChannel('x')
    pc.createOffer().then(o => pc.setLocalDescription(o)).catch(e => { clearTimeout(t); resolve({ error: e.message, types }) })
  })
}

async function runDiag() {
  diagRunning.value = true; diag.value = []; diagAdvice.value = ''
  const out = []
  try {
    const { data } = await axios.get('/api/comms/ice-servers')
    const all = data.iceServers || []
    const stun = all.filter(x => String(x.urls).startsWith('stun'))
    const turnUdp = all.filter(x => String(x.urls).startsWith('turn') && !String(x.urls).includes('transport=tcp'))
    const turnTcp = all.filter(x => String(x.urls).includes('transport=tcp'))

    const r1 = await gather(stun, false)
    out.push({ name: '인터넷 주소 확인(STUN)', ok: !!r1.types.srflx, detail: r1.types.srflx ? '정상' : '응답 없음 — 이 네트워크에서 외부 STUN 이 막혀 있을 수 있어요' })
    diag.value = [...out]

    const r2 = await gather(turnUdp, true)
    out.push({ name: '중계 서버 TURN (UDP)', ok: !!r2.types.relay, detail: r2.types.relay ? '정상 — 중계 주소를 받았어요' : '실패 — 서버가 꺼져 있거나 계정/포트(3478)가 막혀 있어요' })
    diag.value = [...out]

    const r3 = await gather(turnTcp, true)
    out.push({ name: '중계 서버 TURN (TCP)', ok: !!r3.types.relay, detail: r3.types.relay ? '정상' : '실패 — 서버가 꺼져 있거나 TCP 3478 이 막혀 있어요' })
    diag.value = [...out]

    const turnOk = r2.types.relay || r3.types.relay
    diagAdviceBad.value = !turnOk
    diagAdvice.value = turnOk
      ? '중계 서버는 정상이에요. 그래도 안 되면 통화내역 맨 오른쪽 메모(내후보/상대후보)를 확인해 주세요.'
      : '중계(TURN) 서버를 쓸 수 없어요. 서로 다른 네트워크(폰 LTE ↔ PC 와이파이 등)에서는 통화가 연결되지 않을 수 있어요. 서버의 TURN(coturn) 상태를 점검하거나 외부 TURN 서비스로 바꿔야 해요.'
  } catch (e) {
    out.push({ name: '진단', ok: false, detail: e.response?.data?.message || e.message })
    diag.value = out
  }
  diagRunning.value = false
}

onMounted(reload)
</script>
