<template>
<div>
  <div class="mb-4">
    <div class="text-xs text-ink-muted">관리자 › 광고·매출 › 손님 메일</div>
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mt-1">
      <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="mail" :size="18" /></span>
      손님 메일 (소개 메일)
    </h1>
    <p class="text-xs text-ink-faint mt-0.5">이메일 주소를 붙여 넣으면 {{ st ? st.from : 'noreply@awesomekorean.com' }} 로 어썸코리안 소개 메일을 보냅니다. 수신 거부한 주소는 영구 제외되고, 같은 주소는 한 번만 보냅니다.</p>
  </div>

  <div v-if="msg" class="mb-3 rounded-lg px-3 py-2 text-sm" :class="msgOk ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">{{ msg }}</div>

  <!-- 잠금 상태 -->
  <div v-if="st" class="card p-4 mb-4 border" :class="st.live ? 'border-green-200 bg-green-50/40' : 'border-amber-200 bg-amber-50/50'">
    <div class="flex items-center gap-2 font-bold" :class="st.live ? 'text-green-700' : 'text-amber-800'">
      <AppIcon :name="st.live ? 'check-circle' : 'lock'" :size="16" />
      {{ st.live ? '실제 발송이 켜져 있어요 — 대기열이 자동으로 나가고 있어요' : '실제 발송은 잠겨 있어요' }}
    </div>
    <p v-if="st.lock_reason" class="text-sm text-ink-light mt-1">{{ st.lock_reason }}</p>
    <div class="flex flex-wrap items-center gap-2 mt-3">
      <button class="act-btn" :class="st.live_switch ? 'btn-secondary' : 'btn-primary'" :disabled="busy || (!st.postal_address && !st.live_switch)" @click="toggleLive">{{ st.live_switch ? '실제 발송 끄기' : '실제 발송 켜기' }}</button>
      <span v-if="!st.postal_address" class="text-xs text-amber-700">우편 주소가 없어요 — <RouterLink to="/admin/email-digest" class="underline font-bold">메일 발송</RouterLink> 화면에서 먼저 저장하세요 (두 메일이 같은 주소를 써요)</span>
      <span v-else class="text-xs text-ink-muted">메일 맨 아래 주소: {{ st.postal_address }}</span>
    </div>
  </div>

  <!-- 통계 -->
  <div v-if="st" class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
    <div class="card p-3"><div class="text-xs text-ink-muted">대기 중</div><div class="text-xl font-bold text-amber-600">{{ st.counts.queued }}</div></div>
    <div class="card p-3"><div class="text-xs text-ink-muted">보냄</div><div class="text-xl font-bold text-green-600">{{ st.counts.sent }}</div></div>
    <div class="card p-3"><div class="text-xs text-ink-muted">실패</div><div class="text-xl font-bold text-red-600">{{ st.counts.failed }}</div></div>
    <div class="card p-3"><div class="text-xs text-ink-muted">오늘 보냄 / 하루 한도</div><div class="text-xl font-bold text-ink">{{ st.sent_today }} / {{ st.daily_cap }}</div></div>
    <div class="card p-3"><div class="text-xs text-ink-muted">수신 거부(영구 제외)</div><div class="text-xl font-bold text-ink">{{ st.suppressed_total }}</div></div>
  </div>

  <!-- 주소 붙여 넣기 -->
  <div class="card p-4 mb-4">
    <h2 class="font-bold text-ink text-sm mb-1">1. 이메일 주소 붙여 넣기</h2>
    <p class="text-xs text-ink-muted mb-2">한 줄에 하나씩(쉼표·공백으로 구분해도 돼요). 중복·잘못된 주소·수신 거부한 주소·이미 보낸 주소는 자동으로 걸러져요.</p>
    <textarea v-model="raw" rows="7" class="input-soft w-full font-mono text-sm" placeholder="info@example.org&#10;owner@example.com"></textarea>
    <div class="flex flex-wrap items-center gap-3 mt-2">
      <button class="btn-primary act-btn" :disabled="busy || !raw.trim()" @click="addEmails">대기열에 넣기</button>
      <label class="text-xs text-ink-muted flex items-center gap-1.5"><input v-model="resend" type="checkbox" class="accent-amber-600" /> 이미 보낸 주소도 다시 보내기</label>
    </div>
    <div v-if="result" class="mt-3 text-sm rounded-lg bg-gray-50 p-3 leading-relaxed">
      <b>주소 {{ result.found }}개 확인</b> — 새로 넣음 <b class="text-green-700">{{ result.added }}</b>
      <template v-if="result.requeued"> · 다시 대기 {{ result.requeued }}</template>
      <template v-if="result.already_queued"> · 이미 대기 중 {{ result.already_queued }}</template>
      <template v-if="result.already_sent"> · 이미 보냄 {{ result.already_sent }}</template>
      <template v-if="result.suppressed"> · <span class="text-red-600">수신 거부라 제외 {{ result.suppressed }}</span></template>
      <template v-if="result.member_opted_out"> · 수신을 끈 회원이라 제외 {{ result.member_opted_out }}</template>
      <div v-if="result.invalid.length" class="text-xs text-red-600 mt-1">잘못된 주소 {{ result.invalid.length }}개: {{ result.invalid.join(', ') }}</div>
    </div>
  </div>

  <!-- 메일 문구 -->
  <div class="card p-4 mb-4" v-if="st">
    <h2 class="font-bold text-ink text-sm mb-1">2. 메일 내용 · 발송 설정</h2>
    <p class="text-xs text-ink-muted mb-3">기본 문구가 채워져 있어요. 본문에서 빈 줄은 문단 구분, 줄 맨 앞에 <code>- </code> 를 쓰면 글머리표예요. 제목 앞에는 "(광고)"가 자동으로 붙고, 맨 아래 수신 거부 버튼·우편 주소는 항상 들어가요.</p>
    <div class="flex flex-wrap items-center gap-3 mb-3">
      <div class="flex gap-1 bg-gray-100 rounded-xl p-1 w-fit">
        <button v-for="l in langs" :key="l.key" type="button" class="px-3 py-1.5 rounded-lg text-[13px] font-semibold" :class="form.lang === l.key ? 'bg-white text-ink shadow-sm' : 'text-ink-muted'" @click="form.lang = l.key; if (l.key !== 'both') editLang = l.key">{{ l.label }}</button>
      </div>
      <span class="text-xs text-ink-faint">실제로 나가는 언어 (영어/한국어 모두 = 한 통에 두 언어)</span>
    </div>
    <div class="flex gap-1 bg-gray-100 rounded-xl p-1 w-fit mb-2">
      <button v-for="l in [{key:'ko',label:'한국어 문구'},{key:'en',label:'English 문구'}]" :key="l.key" type="button" class="px-3 py-1.5 rounded-lg text-[13px] font-semibold" :class="editLang === l.key ? 'bg-white text-ink shadow-sm' : 'text-ink-muted'" @click="editLang = l.key">{{ l.label }}</button>
    </div>
    <label class="text-xs text-ink-muted">제목</label>
    <input v-model="form.texts[editLang].subject" maxlength="150" class="input-soft w-full mb-2" />
    <label class="text-xs text-ink-muted">본문</label>
    <textarea v-model="form.texts[editLang].body" rows="12" maxlength="4000" class="input-soft w-full mb-2 text-sm leading-relaxed"></textarea>
    <label class="text-xs text-ink-muted">버튼 글자</label>
    <input v-model="form.texts[editLang].button" maxlength="40" class="input-soft w-full mb-3" />
    <div class="flex flex-wrap items-center gap-3">
      <label class="text-xs text-ink-muted">하루 최대 발송
        <input v-model.number="form.daily_cap" type="number" min="1" max="2000" class="input-soft w-24 ml-1" />
      </label>
      <span class="text-[11px] text-ink-faint">새 도메인은 하루 50~100통으로 시작해 서서히 늘리세요. Resend 무료 요금제는 하루 100통까지예요.</span>
    </div>
    <div class="flex flex-wrap gap-2 mt-3">
      <button class="btn-primary act-btn" :disabled="busy" @click="saveSettings">저장</button>
      <button class="btn-secondary act-btn" :disabled="busy" @click="resetText">이 언어 기본 문구로 되돌리기</button>
      <button class="btn-secondary act-btn" :disabled="busy" @click="openPreview">미리보기</button>
    </div>
  </div>

  <!-- 시험 발송 -->
  <div class="card p-4 mb-4">
    <h2 class="font-bold text-ink text-sm mb-1">3. 시험 발송</h2>
    <p class="text-xs text-ink-muted mb-2">내 주소로 먼저 한 통 보내서 모양과 수신 거부 화면을 확인하세요 (잠겨 있어도 가능, 대기열·하루 한도에 포함되지 않아요). 저장하지 않은 문구는 반영되지 않으니 먼저 저장하세요.</p>
    <div class="flex gap-2 flex-wrap">
      <input v-model="testEmail" type="email" placeholder="내 이메일 주소" class="input-soft act-input flex-1 min-w-[220px]" />
      <button class="btn-primary act-btn" :disabled="busy || !testEmail" @click="sendTest">시험 메일 보내기</button>
    </div>
  </div>

  <!-- 대기열·기록 -->
  <div class="card p-4 mb-4" v-if="st">
    <div class="flex items-center justify-between mb-2">
      <h2 class="font-bold text-ink text-sm">최근 대기열·발송 기록</h2>
      <button v-if="st.counts.queued" class="text-xs text-red-600 underline" :disabled="busy" @click="clearQueue">대기 중인 주소 모두 비우기</button>
    </div>
    <div v-if="!st.recent.length" class="text-sm text-ink-faint py-2">아직 넣은 주소가 없어요.</div>
    <div v-else class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead><tr class="text-left text-xs text-ink-muted border-b"><th class="py-1.5 pr-3">주소</th><th class="pr-3">상태</th><th class="pr-3">보낸 시각</th><th></th></tr></thead>
        <tbody>
          <tr v-for="r in st.recent" :key="r.id" class="border-b border-gray-50">
            <td class="py-1.5 pr-3 break-all">{{ r.email }}</td>
            <td class="pr-3 whitespace-nowrap">
              <span class="text-xs rounded-full px-2 py-0.5" :class="{ 'bg-amber-50 text-amber-700': r.status === 'queued', 'bg-green-50 text-green-700': r.status === 'sent', 'bg-red-50 text-red-700': r.status === 'failed' }">{{ { queued: '대기', sent: '보냄', failed: '실패' }[r.status] }}</span>
              <span v-if="r.error" class="text-[11px] text-red-600 ml-1" :title="r.error">!</span>
            </td>
            <td class="pr-3 text-xs text-ink-muted whitespace-nowrap">{{ r.last_sent_at ? r.last_sent_at.slice(0, 16).replace('T', ' ') : '-' }}</td>
            <td class="text-right"><button v-if="r.status === 'queued'" class="text-xs text-ink-muted underline" :disabled="busy" @click="removeOne(r.id)">빼기</button></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- 수신 거부 목록 -->
  <div class="card p-4 mb-8" v-if="st">
    <h2 class="font-bold text-ink text-sm mb-1">수신 거부 목록 ({{ st.suppressed_total }})</h2>
    <p class="text-xs text-ink-muted mb-2">여기 있는 주소는 다시 붙여 넣어도 자동으로 제외돼요. 전화·답장으로 "보내지 말라"고 한 주소는 아래에 직접 추가하세요.</p>
    <div class="flex gap-2 flex-wrap mb-3">
      <input v-model="supRaw" placeholder="제외할 주소 (여러 개는 공백·쉼표로 구분)" class="input-soft act-input flex-1 min-w-[220px]" />
      <button class="btn-secondary act-btn" :disabled="busy || !supRaw.trim()" @click="addSuppress">제외 목록에 추가</button>
    </div>
    <div v-if="st.suppressed.length" class="flex flex-wrap gap-2">
      <span v-for="s in st.suppressed" :key="s.email" class="text-xs rounded-full bg-gray-100 px-2.5 py-1" :title="s.source === 'admin' ? '관리자가 추가' : '손님이 직접 수신 거부'">{{ s.email }}</span>
    </div>
  </div>

  <!-- 미리보기 모달 -->
  <div v-if="previewHtml" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @click.self="previewHtml = ''">
    <div class="bg-white rounded-2xl w-full max-w-[680px] max-h-[90vh] flex flex-col overflow-hidden">
      <div class="flex items-center justify-between px-4 py-3 border-b">
        <div class="text-sm font-bold text-ink truncate">제목: {{ previewSubject }}</div>
        <button class="text-ink-muted text-lg leading-none px-2" @click="previewHtml = ''">×</button>
      </div>
      <iframe :srcdoc="previewHtml" class="flex-1 w-full min-h-[60vh] border-0" sandbox=""></iframe>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const st = ref(null)
const busy = ref(false)
const msg = ref(''); const msgOk = ref(true)
const raw = ref(''); const resend = ref(false); const result = ref(null)
const testEmail = ref(''); const supRaw = ref('')
const editLang = ref('ko')
const previewHtml = ref(''); const previewSubject = ref('')
const form = reactive({ lang: 'ko', daily_cap: 100, texts: { ko: { subject: '', body: '', button: '' }, en: { subject: '', body: '', button: '' } } })
const langs = [{ key: 'ko', label: '한국어' }, { key: 'en', label: 'English' }, { key: 'both', label: '두 언어 모두' }]

function say(t, ok = true) { msg.value = t; msgOk.value = ok; setTimeout(() => { if (msg.value === t) msg.value = '' }, 6000) }
function fill(d) {
  st.value = d
  form.lang = d.lang; form.daily_cap = d.daily_cap
  for (const l of ['ko', 'en']) form.texts[l] = { ...d.texts[l] }
}
async function run(fn, okMsg) {
  busy.value = true
  try { await fn(); if (okMsg) say(okMsg) }
  catch (e) { say(e?.response?.data?.message || '처리하지 못했어요.', false) }
  finally { busy.value = false }
}
async function load() { await run(async () => { const { data } = await axios.get('/api/admin/email-outreach'); fill(data.data) }) }

const addEmails = () => run(async () => {
  const { data } = await axios.post('/api/admin/email-outreach/add', { emails: raw.value, resend: resend.value })
  result.value = data.result; fill(data.data); raw.value = ''
})
const saveSettings = () => run(async () => {
  const { data } = await axios.put('/api/admin/email-outreach/settings', { lang: form.lang, daily_cap: form.daily_cap, texts: form.texts })
  fill(data.data)
}, '저장했어요.')
const toggleLive = () => run(async () => {
  const turnOn = !st.value.live_switch
  if (turnOn && !window.confirm('실제 발송을 켜면 대기열의 주소로 바로 메일이 나가기 시작해요 (하루 한도 ' + st.value.daily_cap + '통). 시험 발송으로 모양을 확인하셨나요?')) return
  const { data } = await axios.put('/api/admin/email-outreach/live', { live: turnOn }); fill(data.data)
  say(turnOn ? '실제 발송을 켰어요.' : '실제 발송을 껐어요.')
})
const clearQueue = () => run(async () => {
  if (!window.confirm('대기 중인 주소를 모두 비울까요? (이미 보낸 기록은 그대로예요)')) return
  const { data } = await axios.post('/api/admin/email-outreach/clear-queue'); fill(data.data)
}, '비웠어요.')
const removeOne = (id) => run(async () => { const { data } = await axios.post('/api/admin/email-outreach/remove/' + id); fill(data.data) })
const addSuppress = () => run(async () => {
  const { data } = await axios.post('/api/admin/email-outreach/suppress', { emails: supRaw.value }); fill(data.data); supRaw.value = ''
}, '제외 목록에 추가했어요.')
const sendTest = () => run(async () => {
  const { data } = await axios.post('/api/admin/email-outreach/test', { email: testEmail.value, lang: form.lang }); say(data.message)
})
const openPreview = () => run(async () => {
  const { data } = await axios.get('/api/admin/email-outreach/preview', { params: { lang: form.lang } })
  previewHtml.value = data.html; previewSubject.value = data.subject
})
function resetText() {
  if (!st.value) return
  form.texts[editLang.value] = { ...st.value.defaults[editLang.value] }
}

onMounted(load)
</script>
