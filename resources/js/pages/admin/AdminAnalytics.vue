<template>
<div>
  <div class="mb-4">
    <div class="text-xs text-ink-muted">관리자 › 시스템 › 방문 분석</div>
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mt-1">
      <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="chart-bar" :size="20" /></span>
      방문 분석 (구글 애널리틱스)
    </h1>
  </div>

  <!-- 통계 연결 / 대시보드 -->
  <div class="card p-4 mb-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
      <div>
        <div class="text-xs text-ink-muted">통계 연결 (구글 애널리틱스 데이터 읽기)</div>
        <div v-if="status" class="mt-1 flex items-center gap-2 flex-wrap">
          <span class="text-[11px] font-bold px-2 py-0.5 rounded-md" :class="status.connected ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-ink-muted'">{{ status.connected ? '연결됨' : '연결 안 됨' }}</span>
          <span v-if="status.connected" class="text-xs text-ink-light">속성 {{ status.property_id }} · <span class="font-mono break-all">{{ status.service_account_email }}</span></span>
        </div>
      </div>
      <button v-if="status?.connected" @click="disconnect" class="text-red-400 hover:text-red-600 text-xs font-bold">연결 해제</button>
    </div>

    <div v-if="status && !status.connected" class="mt-3 space-y-2">
      <p class="text-xs text-ink-light leading-relaxed">구글 클라우드에서 받은 <b>서비스 계정 JSON 파일</b>과 애널리틱스 <b>속성 ID(숫자)</b>를 넣으면 연결돼요. 파일은 서버에 암호화되어 저장되고 화면·메일에는 나오지 않아요. 먼저 애널리틱스 <b>속성 액세스 관리</b>에 그 서비스 계정 이메일을 <b>뷰어</b>로 추가해 두세요.</p>
      <div class="flex flex-wrap gap-2 items-center">
        <input type="file" accept="application/json,.json" @change="onFile" class="text-xs" />
        <input v-model="propertyId" inputmode="numeric" placeholder="속성 ID (숫자)" class="input-soft !w-40 !py-1.5 !text-sm" />
        <button @click="connect" :disabled="!jsonText || !propertyId || connecting" class="btn-primary px-4 py-1.5 text-sm disabled:opacity-50">{{ connecting ? '확인 중...' : '연결' }}</button>
      </div>
      <div v-if="fileName" class="text-[11px] text-ink-muted">선택한 파일: {{ fileName }}</div>
      <div v-if="connectMsg" class="text-xs rounded-lg p-2" :class="connectOk ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600'">{{ connectMsg }}</div>
    </div>
  </div>

  <div v-if="status?.connected" class="mb-4">
    <div class="flex items-center gap-2 mb-3 flex-wrap">
      <button v-for="d in [7, 28, 90]" :key="d" @click="days = d; loadDash()" class="px-3 py-1.5 rounded-full text-xs font-bold transition-colors"
        :class="days === d ? 'bg-amber-400 text-white' : 'bg-gray-100 text-ink-muted hover:bg-gray-200'">최근 {{ d }}일</button>
      <button @click="loadDash(true)" class="text-xs text-blue-500 hover:text-blue-700 font-bold ml-auto">새로고침</button>
    </div>
    <div v-if="dashErr" class="bg-red-50 border border-red-200 text-red-600 text-xs rounded-xl p-3 mb-3">{{ dashErr }}</div>
    <div v-if="dashLoading" class="card p-6 text-sm text-ink-muted text-center">불러오는 중...</div>

    <template v-if="dash && !dashLoading">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3">
        <div class="card p-3"><div class="text-xs text-ink-muted">지금 접속 중</div><div class="text-xl font-bold text-emerald-600">{{ dash.realtime_users }}</div></div>
        <div class="card p-3"><div class="text-xs text-ink-muted">방문자</div><div class="text-xl font-bold text-ink">{{ num(dash.totals.activeUsers) }}</div><div class="text-[11px] text-ink-faint">신규 {{ num(dash.totals.newUsers) }}</div></div>
        <div class="card p-3"><div class="text-xs text-ink-muted">페이지 조회</div><div class="text-xl font-bold text-ink">{{ num(dash.totals.pageViews) }}</div><div class="text-[11px] text-ink-faint">세션 {{ num(dash.totals.sessions) }}</div></div>
        <div class="card p-3"><div class="text-xs text-ink-muted">평균 머문 시간</div><div class="text-xl font-bold text-ink">{{ dur(dash.totals.avgSessionSec) }}</div><div class="text-[11px] text-ink-faint">참여율 {{ Math.round((dash.totals.engagementRate || 0) * 100) }}%</div></div>
      </div>

      <!-- 일별 방문자 -->
      <div class="card p-4 mb-3">
        <div class="flex items-center justify-between mb-2">
          <div class="text-sm font-bold text-ink">일별 방문자</div>
          <div class="text-xs text-ink-muted h-4">{{ hover ? `${hover.label} · 방문자 ${hover.users}명 · 조회 ${hover.views}회` : '막대 위에 올리면 숫자가 보여요' }}</div>
        </div>
        <div class="flex items-end gap-[3px] h-32" @mouseleave="hover = null">
          <div v-for="r in daily" :key="r.date" class="flex-1 min-w-[2px] max-w-[36px] h-full flex items-end" @mouseenter="hover = r" @touchstart.passive="hover = r">
            <div class="w-full rounded-t-[3px] bg-amber-400 hover:bg-amber-500" :style="{ height: Math.max(r.users ? 3 : 0, (r.users / maxUsers) * 100) + '%' }"></div>
          </div>
        </div>
        <div class="flex justify-between text-[10px] text-ink-faint mt-1"><span>{{ daily[0]?.label }}</span><span>{{ daily[daily.length - 1]?.label }}</span></div>
        <details class="mt-2"><summary class="text-[11px] text-blue-500 cursor-pointer">표로 보기</summary>
          <table class="w-full text-xs mt-1"><thead><tr class="text-left text-ink-muted"><th class="py-1">날짜</th><th>방문자</th><th>조회</th></tr></thead>
            <tbody><tr v-for="r in daily" :key="r.date" class="border-t border-gray-50"><td class="py-1">{{ r.label }}</td><td>{{ r.users }}</td><td>{{ r.views }}</td></tr></tbody></table>
        </details>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
        <div class="card overflow-hidden">
          <div class="px-4 py-3 border-b border-gray-50 text-sm font-bold text-ink">인기 페이지</div>
          <div v-for="p in dash.pages" :key="p.path" class="px-4 py-2 border-b border-gray-50 last:border-0 text-xs flex justify-between gap-3">
            <div class="min-w-0"><div class="text-ink truncate">{{ p.title || p.path }}</div><div class="text-ink-faint truncate">{{ decode(p.path) }}</div></div>
            <div class="shrink-0 text-right text-ink font-semibold">{{ num(p.views) }}<span class="text-ink-faint font-normal"> 조회</span></div>
          </div>
          <div v-if="!dash.pages.length" class="px-4 py-4 text-xs text-ink-muted text-center">아직 데이터가 없어요</div>
        </div>
        <div class="space-y-3">
          <div class="card overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-50 text-sm font-bold text-ink">어디서 들어왔나 (유입 경로)</div>
            <div v-for="c in dash.channels" :key="c.channel" class="px-4 py-2 border-b border-gray-50 last:border-0 text-xs flex justify-between"><span class="text-ink">{{ channelLabel(c.channel) }}</span><span class="font-semibold text-ink">{{ num(c.sessions) }}</span></div>
            <div v-if="!dash.channels.length" class="px-4 py-4 text-xs text-ink-muted text-center">아직 데이터가 없어요</div>
          </div>
          <div class="card overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-50 text-sm font-bold text-ink">국가별 방문자</div>
            <div v-for="c in dash.countries" :key="c.country" class="px-4 py-2 border-b border-gray-50 last:border-0 text-xs flex justify-between"><span class="text-ink">{{ c.country }}</span><span class="font-semibold text-ink">{{ num(c.users) }}</span></div>
            <div v-if="!dash.countries.length" class="px-4 py-4 text-xs text-ink-muted text-center">아직 데이터가 없어요</div>
          </div>
        </div>
      </div>
      <div class="text-[11px] text-ink-faint mt-2">구글 데이터는 몇 시간 늦게 반영되고, 추적 코드를 켠 시점부터 쌓여요. 10분마다 새로 받아와요.</div>
    </template>
  </div>

  <div class="card p-4 mb-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
      <div>
        <div class="text-xs text-ink-muted">추적 코드 상태</div>
        <div v-if="status" class="mt-1 flex items-center gap-2">
          <span class="text-[11px] font-bold px-2 py-0.5 rounded-md" :class="status.enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600'">{{ status.enabled ? '켜짐' : '꺼짐' }}</span>
          <span v-if="status.measurement_id" class="font-mono text-sm text-ink">{{ status.measurement_id }}</span>
        </div>
      </div>
      <button @click="runCheck" :disabled="checking" class="btn-primary px-4 py-2 text-sm disabled:opacity-50">
        <AppIcon name="search" :size="14" />{{ checking ? '검사 중... (최대 30초)' : '사이트 전체 검사' }}
      </button>
    </div>
    <p v-if="status && !status.enabled" class="text-xs text-ink-light mt-3 leading-relaxed">
      아직 측정 ID가 등록되지 않았어요. <b>관리자 › 설정 › API 키 관리</b>에서 서비스 코드 <span class="font-mono">google_analytics</span> 로 구글 애널리틱스의 <b>측정 ID(G-로 시작)</b>를 등록하면 켜지고, 아래 검사 버튼으로 사이트 전체에 들어갔는지 확인할 수 있어요.
    </p>
    <p v-else class="text-xs text-ink-faint mt-3">주요 화면 주소와 최근 정보 글 40개를 실제로 열어 보고, 각 페이지에 추적 코드가 들어 있는지 확인해요.</p>
  </div>

  <div v-if="error" class="mb-3 bg-red-50 border border-red-200 text-red-600 text-xs rounded-xl p-3">{{ error }}</div>

  <div v-if="result" class="card p-4">
    <div class="flex items-center gap-3 flex-wrap">
      <div class="text-2xl font-black" :class="result.missing.length ? 'text-amber-600' : 'text-emerald-600'">{{ result.with_tag }} / {{ result.total }}</div>
      <div class="text-sm text-ink">{{ result.message }}</div>
    </div>
    <div v-if="result.missing.length" class="mt-3 space-y-1">
      <div class="text-xs font-bold text-ink-muted">추적 코드가 없는 페이지</div>
      <div v-for="m in result.missing" :key="m.url" class="text-xs flex gap-2">
        <span class="font-mono text-red-500 shrink-0">{{ m.status || '응답없음' }}</span>
        <span class="text-ink-light break-all">{{ decode(m.url) }}</span>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const status = ref(null)
const result = ref(null)
const checking = ref(false)
const error = ref('')

const decode = (u) => { try { return decodeURIComponent(u) } catch { return u } }

const days = ref(7)
const dash = ref(null)
const dashErr = ref('')
const dashLoading = ref(false)
const hover = ref(null)
const jsonText = ref('')
const fileName = ref('')
const propertyId = ref('')
const connecting = ref(false)
const connectMsg = ref('')
const connectOk = ref(false)

const num = (n) => Math.round(n || 0).toLocaleString()
const dur = (s) => { s = Math.round(s || 0); return s >= 60 ? `${Math.floor(s / 60)}분 ${s % 60}초` : `${s}초` }
const channelMap = { 'Direct': '직접 접속', 'Organic Search': '검색', 'Organic Social': 'SNS', 'Referral': '다른 사이트', 'Email': '이메일', 'Paid Search': '유료 검색', 'Unassigned': '분류 안 됨' }
const channelLabel = (c) => channelMap[c] || c
const daily = computed(() => (dash.value?.daily || []).map(r => ({ ...r, label: `${r.date.slice(4, 6)}/${r.date.slice(6, 8)}` })))
const maxUsers = computed(() => Math.max(1, ...daily.value.map(r => r.users)))

function onFile(e) {
  const f = e.target.files?.[0]
  connectMsg.value = ''
  if (!f) { jsonText.value = ''; fileName.value = ''; return }
  if (f.size > 20000) { connectMsg.value = '파일이 너무 커요. 서비스 계정 JSON 파일이 맞는지 확인하세요.'; connectOk.value = false; return }
  fileName.value = f.name
  const reader = new FileReader()
  reader.onload = () => { jsonText.value = String(reader.result || '') }
  reader.readAsText(f)
}

async function connect() {
  connecting.value = true; connectMsg.value = ''
  try {
    await axios.post('/api/admin/analytics/credentials', { service_account_json: jsonText.value, property_id: propertyId.value.trim() })
    connectOk.value = true; connectMsg.value = '연결되었어요.'
    jsonText.value = ''; fileName.value = ''; propertyId.value = ''
    await loadStatus(); loadDash()
  } catch (e) {
    connectOk.value = false
    connectMsg.value = e.response?.data?.message || e.response?.data?.errors?.property_id?.[0] || '연결하지 못했어요.'
  } finally { connecting.value = false }
}

async function disconnect() {
  if (!confirm('구글 애널리틱스 통계 연결을 해제할까요?')) return
  try { await axios.delete('/api/admin/analytics/credentials'); dash.value = null; await loadStatus() } catch { error.value = '해제하지 못했어요.' }
}

async function loadDash(fresh = false) {
  dashLoading.value = true; dashErr.value = ''
  try { const { data } = await axios.get('/api/admin/analytics/dashboard', { params: { days: days.value, fresh: fresh ? 1 : 0 } }); dash.value = data.data }
  catch (e) { dash.value = null; dashErr.value = e.response?.data?.message || '통계를 불러오지 못했어요.' }
  finally { dashLoading.value = false }
}

async function loadStatus() {
  try { const { data } = await axios.get('/api/admin/analytics/status'); status.value = data.data; if (data.data.connected && !dash.value) loadDash() }
  catch (e) { error.value = e.response?.status === 403 ? '최고 관리자만 볼 수 있는 화면이에요.' : '상태를 불러오지 못했어요.' }
}

async function runCheck() {
  checking.value = true; error.value = ''; result.value = null
  try { const { data } = await axios.post('/api/admin/analytics/check', {}, { timeout: 60000 }); result.value = data.data; status.value = { enabled: data.data.enabled, measurement_id: data.data.measurement_id } }
  catch (e) { error.value = e.response?.status === 429 ? '잠시 후 다시 시도해 주세요.' : '검사하지 못했어요.' }
  finally { checking.value = false }
}

onMounted(loadStatus)
</script>
