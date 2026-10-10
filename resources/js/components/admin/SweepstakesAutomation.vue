<template>
<div class="space-y-6">
  <!-- ───────── 반복 일정 ───────── -->
  <section class="space-y-3">
    <div class="flex items-center justify-between gap-2">
      <div>
        <h2 class="text-[17px] font-extrabold text-ink">반복 일정</h2>
        <p class="text-[13px] text-ink-muted leading-relaxed">같은 상품·같은 기간의 이벤트를 매일/매주/매월 자동으로 만들어요. 시간은 애틀랜타 기준이에요.</p>
      </div>
      <button @click="openSchedule(null)" class="shrink-0 min-h-[44px] px-4 rounded-xl bg-amber-500 text-white text-[14px] font-bold">+ 새 일정</button>
    </div>
    <div v-if="loading" class="text-center py-6 text-ink-muted text-[15px]">불러오는 중...</div>
    <div v-for="s in schedules" :key="s.id" class="rounded-2xl bg-white border border-gray-100 p-3.5">
      <div class="flex items-center gap-2 flex-wrap">
        <span class="text-[12px] font-bold px-2.5 py-1 rounded-full" :class="chip(s.status)">{{ statusLabel(s.status) }}</span>
        <span class="text-[16px] font-bold text-ink break-words">{{ s.name }}</span>
      </div>
      <div class="text-[14px] text-ink-muted mt-1 break-words">🎁 {{ s.prize_name }}<span v-if="s.prize_value"> (${{ s.prize_value }})</span><span v-if="s.winner_count > 1"> · {{ s.winner_count }}명</span></div>
      <div class="text-[13px] text-ink-light mt-0.5">{{ rule(s) }} · {{ durText(s.duration_hours) }} 진행<span v-if="s.auto_draw"> · 끝나면 자동 추첨</span></div>
      <div class="text-[13px] text-ink-faint mt-0.5">회차 {{ s.runs_done }}{{ s.total_runs ? ' / ' + s.total_runs : ' (계속)' }}</div>
      <div v-if="s.next_run_at && s.status === 'active'" class="text-[13px] font-bold text-emerald-700 mt-1">다음 생성: {{ tAtl(s.next_run_at) }} (애틀랜타) · {{ tUtc(s.next_run_at) }} (UTC)</div>
      <div v-else-if="s.next_run_at && s.status === 'paused'" class="text-[13px] text-amber-700 mt-1">일시정지 중 · 켜면 {{ tAtl(s.next_run_at) }} 부터 (애틀랜타)</div>
      <div class="flex flex-wrap gap-2 mt-3">
        <button v-if="s.status !== 'active' && s.status !== 'completed' && s.status !== 'stopped'" @click="act('sch', s, 'start')" class="min-h-[44px] px-4 rounded-xl bg-emerald-500 text-white text-[14px] font-bold">시작</button>
        <button v-if="s.status === 'active'" @click="act('sch', s, 'pause')" class="min-h-[44px] px-4 rounded-xl bg-gray-100 text-ink text-[14px] font-bold">일시정지</button>
        <button v-if="s.status === 'active' || s.status === 'paused'" @click="act('sch', s, 'run_now')" class="min-h-[44px] px-4 rounded-xl bg-amber-50 text-amber-800 text-[14px] font-bold">지금 한 번 만들기</button>
        <button @click="openSchedule(s)" class="min-h-[44px] px-4 rounded-xl bg-gray-100 text-ink text-[14px] font-bold">수정</button>
        <button v-if="s.status !== 'stopped' && s.status !== 'completed'" @click="ask('sch', s, 'stop', '이 일정을 중지할까요? 이미 만들어진 이벤트는 그대로 남아요.')" class="min-h-[44px] px-3 rounded-xl text-red-500 text-[14px] font-bold">중지</button>
      </div>
    </div>
    <div v-if="!loading && !schedules.length" class="rounded-2xl bg-white border border-gray-100 py-8 text-center text-[14px] text-ink-muted">만든 반복 일정이 없어요</div>
  </section>

  <!-- ───────── 가입 보너스 ───────── -->
  <section class="space-y-3">
    <div class="flex items-center justify-between gap-2">
      <div>
        <h2 class="text-[17px] font-extrabold text-ink">가입 보너스</h2>
        <p class="text-[13px] text-ink-muted leading-relaxed">"매 100번째로 가입한 회원에게 5달러 상품권"처럼 가입 순번에 맞는 회원에게 상품을 줘요. 현재 전체 회원 {{ memberCount }}명. <b>켠 뒤에 가입하는 사람부터</b> 세요.</p>
      </div>
      <button @click="openMilestone(null)" class="shrink-0 min-h-[44px] px-4 rounded-xl bg-amber-500 text-white text-[14px] font-bold">+ 새 보너스</button>
    </div>
    <div v-for="m in milestones" :key="m.id" class="rounded-2xl bg-white border border-gray-100 p-3.5">
      <div class="flex items-center gap-2 flex-wrap">
        <span class="text-[12px] font-bold px-2.5 py-1 rounded-full" :class="chip(m.status)">{{ statusLabel(m.status) }}</span>
        <span class="text-[16px] font-bold text-ink break-words">{{ m.name }}</span>
      </div>
      <div class="text-[14px] text-ink-muted mt-1">매 <b>{{ m.every_n }}번째</b> 가입 · 🎁 {{ m.prize_name }}<span v-if="m.prize_value"> (${{ m.prize_value }})</span> · {{ m.prize_type === 'physical' ? '실물' : '디지털' }}</div>
      <div class="text-[13px] text-ink-faint mt-0.5">지급된 당첨자 {{ m.awards_done }}명{{ m.max_awards ? ' / 최대 ' + m.max_awards + '명' : '' }}</div>
      <div class="flex flex-wrap gap-2 mt-3">
        <button v-if="m.status !== 'active' && m.status !== 'stopped'" @click="act('ms', m, 'start')" class="min-h-[44px] px-4 rounded-xl bg-emerald-500 text-white text-[14px] font-bold">시작</button>
        <button v-if="m.status === 'active'" @click="act('ms', m, 'pause')" class="min-h-[44px] px-4 rounded-xl bg-gray-100 text-ink text-[14px] font-bold">일시정지</button>
        <button @click="openMilestone(m)" class="min-h-[44px] px-4 rounded-xl bg-gray-100 text-ink text-[14px] font-bold">수정</button>
        <button v-if="m.status !== 'stopped'" @click="ask('ms', m, 'stop', '이 가입 보너스를 중지할까요? 이미 당첨된 분의 기록은 남아요.')" class="min-h-[44px] px-3 rounded-xl text-red-500 text-[14px] font-bold">중지</button>
      </div>
      <p v-if="m.awards_done" class="text-[12px] text-ink-faint mt-2">당첨자는 위쪽 "종료·당첨" 탭의 "🎯 가입 보너스 · {{ m.name }}"에서 상품을 보낼 수 있어요.</p>
    </div>
    <div v-if="!loading && !milestones.length" class="rounded-2xl bg-white border border-gray-100 py-8 text-center text-[14px] text-ink-muted">만든 가입 보너스가 없어요</div>
  </section>

  <!-- 일정 폼 -->
  <Teleport to="body">
    <div v-if="sf" class="fixed inset-0 z-[72] bg-black/45 flex items-end sm:items-center justify-center" @click.self="sf = null">
      <div class="w-full sm:max-w-lg bg-white rounded-t-3xl sm:rounded-2xl max-h-[92vh] overflow-y-auto px-4 pt-4" :style="{ paddingBottom: 'calc(20px + env(safe-area-inset-bottom, 0px))' }" role="dialog" aria-modal="true">
        <div class="flex items-center justify-between">
          <div class="text-[18px] font-extrabold text-ink">{{ sf.id ? '반복 일정 수정' : '새 반복 일정' }}</div>
          <button @click="sf = null" class="w-11 h-11 -mr-2 rounded-full text-ink-light text-[22px]" aria-label="닫기">✕</button>
        </div>
        <div class="space-y-3 mt-2">
          <label class="block"><span class="lbl">일정 이름 (관리용)</span><input v-model="sf.name" maxlength="120" class="fld" placeholder="예: 주간 스타벅스 $10" /></label>
          <label class="block"><span class="lbl">이벤트 제목 <span class="hint">{n}=회차, {date}=시작 날짜</span></span><input v-model="sf.title_template" maxlength="200" class="fld" placeholder="예: 주간 스타벅스 $10 추첨 {n}회차" /></label>
          <label class="block"><span class="lbl">상품 이름</span><input v-model="sf.prize_name" maxlength="255" class="fld" placeholder="예: Starbucks $10 디지털 상품권" /></label>
          <div class="grid grid-cols-2 gap-2">
            <label class="block"><span class="lbl">상품 가치 (USD)</span><input v-model="sf.prize_value" type="number" inputmode="decimal" min="0" step="0.01" class="fld" placeholder="10" /></label>
            <label class="block"><span class="lbl">당첨자 수</span><input v-model.number="sf.winner_count" type="number" inputmode="numeric" min="1" max="10" class="fld" /></label>
          </div>
          <div>
            <span class="lbl">반복</span>
            <div class="grid grid-cols-3 gap-2">
              <button v-for="o in [{v:'daily',l:'매일'},{v:'weekly',l:'매주'},{v:'monthly',l:'매월'}]" :key="o.v" @click="sf.repeat_unit = o.v" class="min-h-[46px] rounded-xl border-2 text-[15px] font-bold" :class="sf.repeat_unit === o.v ? 'border-amber-500 bg-amber-50 text-amber-800' : 'border-gray-200 text-ink'">{{ o.l }}</button>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <label v-if="sf.repeat_unit === 'weekly'" class="block"><span class="lbl">요일</span>
              <select v-model.number="sf.weekday" class="fld"><option v-for="(d, i) in DAYS" :key="i" :value="i">{{ d }}요일</option></select></label>
            <label v-if="sf.repeat_unit === 'monthly'" class="block"><span class="lbl">매월 며칠 (1~28)</span><input v-model.number="sf.month_day" type="number" min="1" max="28" class="fld" /></label>
            <label class="block"><span class="lbl">시작 시각 (애틀랜타)</span><input v-model="sf.start_time" type="time" class="fld" /></label>
          </div>
          <label class="block"><span class="lbl">이벤트 진행 기간</span>
            <select v-model.number="sf.duration_hours" class="fld"><option v-for="o in DURS" :key="o.h" :value="o.h">{{ o.l }}</option></select></label>
          <label class="block"><span class="lbl">총 몇 번 만들까요? <span class="hint">비우면 계속</span></span><input v-model.number="sf.total_runs" type="number" inputmode="numeric" min="1" max="500" class="fld" placeholder="예: 10 (비우면 계속)" /></label>
          <label v-if="!sf.id || sf.runs_done === 0" class="block"><span class="lbl">첫 시작 일시 (애틀랜타) <span class="hint">비우면 위 요일·시각 중 가장 가까운 때</span></span><input v-model="sf.first_start_at" type="datetime-local" class="fld" /></label>
          <label class="flex items-center gap-3 rounded-xl border border-gray-200 px-3" style="min-height:52px"><input v-model="sf.auto_draw" type="checkbox" style="width:22px;height:22px;accent-color:#FC226B" /><span class="text-[15px] font-bold text-ink">끝나면 당첨자를 자동으로 추첨</span></label>
          <p class="text-[12px] text-ink-faint -mt-1">끄면 이벤트가 끝난 뒤 직접 "당첨자 선정"을 눌러야 해요. 켜면 서버가 한 번만 공정하게 뽑아요.</p>
          <label class="block"><span class="lbl">설명 (선택)</span><textarea v-model="sf.description" rows="2" maxlength="2000" class="fld"></textarea></label>
        </div>
        <p v-if="sfErr" class="mt-3 text-[14px] text-red-600">{{ sfErr }}</p>
        <button @click="saveSchedule" :disabled="busy" class="mt-4 w-full min-h-[52px] rounded-xl bg-amber-500 text-white text-[16px] font-extrabold disabled:opacity-50">{{ busy ? '저장 중...' : '저장' }}</button>
        <p v-if="!sf.id" class="text-[12px] text-ink-faint text-center mt-2">저장하면 "일시정지" 상태로 만들어져요. 확인 후 "시작"을 누르면 돌기 시작해요.</p>
      </div>
    </div>
  </Teleport>

  <!-- 가입 보너스 폼 -->
  <Teleport to="body">
    <div v-if="mf" class="fixed inset-0 z-[72] bg-black/45 flex items-end sm:items-center justify-center" @click.self="mf = null">
      <div class="w-full sm:max-w-lg bg-white rounded-t-3xl sm:rounded-2xl max-h-[92vh] overflow-y-auto px-4 pt-4" :style="{ paddingBottom: 'calc(20px + env(safe-area-inset-bottom, 0px))' }" role="dialog" aria-modal="true">
        <div class="flex items-center justify-between">
          <div class="text-[18px] font-extrabold text-ink">{{ mf.id ? '가입 보너스 수정' : '새 가입 보너스' }}</div>
          <button @click="mf = null" class="w-11 h-11 -mr-2 rounded-full text-ink-light text-[22px]" aria-label="닫기">✕</button>
        </div>
        <div class="space-y-3 mt-2">
          <label class="block"><span class="lbl">이름 (관리용)</span><input v-model="mf.name" maxlength="120" class="fld" placeholder="예: 100번째 가입 5달러" /></label>
          <label class="block"><span class="lbl">매 몇 번째 가입마다?</span><input v-model.number="mf.every_n" type="number" inputmode="numeric" min="2" class="fld" placeholder="100" /></label>
          <label class="block"><span class="lbl">상품 이름</span><input v-model="mf.prize_name" maxlength="255" class="fld" placeholder="예: $5 디지털 상품권" /></label>
          <div class="grid grid-cols-2 gap-2">
            <label class="block"><span class="lbl">상품 가치 (USD)</span><input v-model="mf.prize_value" type="number" inputmode="decimal" min="0" step="0.01" class="fld" placeholder="5" /></label>
            <label class="block"><span class="lbl">최대 몇 명까지? <span class="hint">비우면 계속</span></span><input v-model.number="mf.max_awards" type="number" inputmode="numeric" min="1" class="fld" /></label>
          </div>
          <div>
            <span class="lbl">상품 종류</span>
            <div class="grid grid-cols-2 gap-2">
              <button v-for="o in [{v:'digital',l:'디지털'},{v:'physical',l:'실물'}]" :key="o.v" @click="mf.prize_type = o.v" class="min-h-[46px] rounded-xl border-2 text-[15px] font-bold" :class="mf.prize_type === o.v ? 'border-amber-500 bg-amber-50 text-amber-800' : 'border-gray-200 text-ink'">{{ o.l }}</button>
            </div>
          </div>
          <p class="text-[12px] text-ink-faint leading-relaxed">당첨된 회원에게는 바로 알림이 가고, 상품 링크/코드는 "종료·당첨" 탭의 가입 보너스 항목에서 직접 보내요. 가짜 가입을 막기 위해 상품은 사람이 확인한 뒤 보내는 방식이에요.</p>
        </div>
        <p v-if="mfErr" class="mt-3 text-[14px] text-red-600">{{ mfErr }}</p>
        <button @click="saveMilestone" :disabled="busy" class="mt-4 w-full min-h-[52px] rounded-xl bg-amber-500 text-white text-[16px] font-extrabold disabled:opacity-50">{{ busy ? '저장 중...' : '저장' }}</button>
        <p v-if="!mf.id" class="text-[12px] text-ink-faint text-center mt-2">저장하면 "일시정지" 상태예요. "시작"을 누른 뒤에 가입하는 사람부터 세요.</p>
      </div>
    </div>
    <!-- 확인 -->
    <div v-if="conf" class="fixed inset-0 z-[74] bg-black/45 flex items-center justify-center px-6" @click.self="conf = null">
      <div class="bg-white rounded-2xl p-5 w-full max-w-sm">
        <p class="text-[16px] text-ink leading-relaxed">{{ conf.text }}</p>
        <div class="grid grid-cols-2 gap-2 mt-4">
          <button @click="conf = null" class="min-h-[48px] rounded-xl bg-gray-100 text-ink text-[15px] font-bold">아니요</button>
          <button @click="doConf" :disabled="busy" class="min-h-[48px] rounded-xl bg-red-500 text-white text-[15px] font-bold disabled:opacity-50">네</button>
        </div>
      </div>
    </div>
    <div v-if="msg" class="fixed left-1/2 -translate-x-1/2 z-[90] max-w-[90vw] px-4 py-3 rounded-xl text-[15px] font-bold text-white shadow-lg" :class="msg.error ? 'bg-red-600' : 'bg-ink'" style="bottom: calc(24px + env(safe-area-inset-bottom, 0px))" role="status">{{ msg.text }}</div>
  </Teleport>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const emit = defineEmits(['changed'])
const DAYS = ['일', '월', '화', '수', '목', '금', '토']
const DURS = [
  { h: 24, l: '하루 (24시간)' }, { h: 72, l: '3일' }, { h: 120, l: '5일' }, { h: 168, l: '일주일' },
  { h: 240, l: '10일' }, { h: 336, l: '2주' }, { h: 720, l: '30일' },
]
const loading = ref(true)
const schedules = ref([])
const milestones = ref([])
const memberCount = ref(0)
const sf = ref(null), mf = ref(null), conf = ref(null)
const sfErr = ref(''), mfErr = ref('')
const busy = ref(false)
const msg = ref(null)
let msgTimer = null
function say(text, error = false) { msg.value = { text, error }; clearTimeout(msgTimer); msgTimer = setTimeout(() => { msg.value = null }, 3500) }

const statusLabel = (s) => ({ active: '실행 중', paused: '일시정지', stopped: '중지됨', completed: '모두 완료' }[s] || s)
const chip = (s) => ({ active: 'bg-emerald-100 text-emerald-700', paused: 'bg-amber-100 text-amber-800', stopped: 'bg-gray-200 text-gray-600', completed: 'bg-violet-100 text-violet-700' }[s] || 'bg-gray-100 text-gray-600')
const rule = (s) => s.repeat_unit === 'daily' ? `매일 ${s.start_time}` : s.repeat_unit === 'monthly' ? `매월 ${s.month_day}일 ${s.start_time}` : `매주 ${DAYS[s.weekday ?? 1]}요일 ${s.start_time}`
const durText = (h) => (DURS.find(d => d.h === h)?.l) || (h % 24 === 0 ? `${h / 24}일` : `${h}시간`)
const tAtl = (iso) => iso ? new Date(iso).toLocaleString('ko-KR', { timeZone: 'America/New_York', month: 'numeric', day: 'numeric', weekday: 'short', hour: '2-digit', minute: '2-digit', hour12: false }) : ''
const tUtc = (iso) => iso ? new Date(iso).toLocaleString('ko-KR', { timeZone: 'UTC', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false }) : ''

async function load() {
  loading.value = true
  try {
    const [a, b] = await Promise.all([axios.get('/api/admin/sweepstakes-schedules'), axios.get('/api/admin/sweepstakes-milestones')])
    schedules.value = a.data.data || []
    milestones.value = b.data.data || []
    memberCount.value = b.data.member_count || 0
  } catch {}
  loading.value = false
}

function openSchedule(s) {
  sfErr.value = ''
  sf.value = s ? { ...s, prize_value: s.prize_value ?? '', first_start_at: '' }
    : { id: null, name: '', title_template: '', prize_name: '', prize_value: '', winner_count: 1, repeat_unit: 'weekly', weekday: 1, month_day: 1, start_time: '09:00', duration_hours: 168, total_runs: null, runs_done: 0, auto_draw: false, description: '', first_start_at: '' }
}
function openMilestone(m) {
  mfErr.value = ''
  mf.value = m ? { ...m, prize_value: m.prize_value ?? '' } : { id: null, name: '', every_n: 100, prize_name: '', prize_value: '', prize_type: 'digital', max_awards: null }
}
function errOf(e, fallback) {
  const er = e.response?.data?.errors
  if (er) return Object.values(er).flat()[0]
  return e.response?.data?.message || fallback
}
async function saveSchedule() {
  if (busy.value) return
  const f = sf.value
  if (!f.name.trim() || !f.title_template.trim() || !f.prize_name.trim()) { sfErr.value = '이름, 이벤트 제목, 상품 이름을 입력해 주세요'; return }
  busy.value = true; sfErr.value = ''
  const body = {
    name: f.name.trim(), title_template: f.title_template.trim(), description: f.description || null,
    prize_name: f.prize_name.trim(), prize_value: f.prize_value === '' ? null : Number(f.prize_value),
    winner_count: f.winner_count || 1, auto_draw: !!f.auto_draw, repeat_unit: f.repeat_unit,
    weekday: f.repeat_unit === 'weekly' ? f.weekday : null, month_day: f.repeat_unit === 'monthly' ? f.month_day : null,
    start_time: f.start_time, duration_hours: f.duration_hours, total_runs: f.total_runs || null,
  }
  if (f.first_start_at) body.first_start_at = f.first_start_at
  try {
    if (f.id) await axios.put(`/api/admin/sweepstakes-schedules/${f.id}`, body)
    else await axios.post('/api/admin/sweepstakes-schedules', body)
    sf.value = null; say('저장했어요'); await load()
  } catch (e) { sfErr.value = errOf(e, '저장하지 못했어요') }
  busy.value = false
}
async function saveMilestone() {
  if (busy.value) return
  const f = mf.value
  if (!f.name.trim() || !f.prize_name.trim() || !(f.every_n >= 2)) { mfErr.value = '이름, 상품 이름, 가입 순번(2 이상)을 입력해 주세요'; return }
  busy.value = true; mfErr.value = ''
  const body = { name: f.name.trim(), every_n: f.every_n, prize_name: f.prize_name.trim(), prize_value: f.prize_value === '' ? null : Number(f.prize_value), prize_type: f.prize_type, max_awards: f.max_awards || null }
  try {
    if (f.id) await axios.put(`/api/admin/sweepstakes-milestones/${f.id}`, body)
    else await axios.post('/api/admin/sweepstakes-milestones', body)
    mf.value = null; say('저장했어요'); await load()
  } catch (e) { mfErr.value = errOf(e, '저장하지 못했어요') }
  busy.value = false
}
async function act(kind, row, action) {
  if (busy.value) return
  busy.value = true
  try {
    const url = kind === 'sch' ? `/api/admin/sweepstakes-schedules/${row.id}/action` : `/api/admin/sweepstakes-milestones/${row.id}/action`
    await axios.post(url, { action })
    say({ start: '시작했어요', pause: '일시정지했어요', stop: '중지했어요', run_now: '이벤트를 하나 만들었어요. "진행·예정" 탭에서 확인하세요' }[action] || '처리했어요')
    await load()
    if (action === 'run_now') emit('changed')
  } catch (e) { say(errOf(e, '처리하지 못했어요'), true) }
  busy.value = false
}
function ask(kind, row, action, text) { conf.value = { kind, row, action, text } }
async function doConf() { const c = conf.value; conf.value = null; if (c) await act(c.kind, c.row, c.action) }

onMounted(load)
</script>

<style scoped>
.lbl { display: block; font-size: 14px; font-weight: 700; color: #1b1613; margin-bottom: 4px; }
.hint { font-weight: 400; font-size: 12px; color: #9b9189; margin-left: 4px; }
.fld { width: 100%; min-height: 46px; border-radius: 12px; border: 1px solid #e5e7eb; padding: 8px 12px; font-size: 16px; background: #fff; }
</style>
