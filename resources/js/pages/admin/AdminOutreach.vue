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
  <div v-if="st" class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <div class="card p-3"><div class="text-xs text-ink-muted">대기 중</div><div class="text-xl font-bold text-amber-600">{{ st.counts.queued }}</div></div>
    <div class="card p-3"><div class="text-xs text-ink-muted">보냄</div><div class="text-xl font-bold text-green-600">{{ st.counts.sent }}</div></div>
    <div class="card p-3"><div class="text-xs text-ink-muted">실패</div><div class="text-xl font-bold text-red-600">{{ st.counts.failed }}</div></div>
    <div class="card p-3"><div class="text-xs text-ink-muted">수신 거부(영구 제외)</div><div class="text-xl font-bold text-ink">{{ st.suppressed_total }}</div></div>
  </div>

  <!-- 하루 발송 개수 -->
  <div v-if="st" class="card p-4 mb-4 border border-amber-200">
    <h2 class="font-bold text-ink text-sm mb-1">📮 하루에 몇 통 보낼까요?</h2>
    <p class="text-xs text-ink-muted mb-3">하루 한도에 닿으면 그날은 멈추고, 다음 날(뉴욕 시각 자정 이후) 이어서 보내요. 새 도메인은 적게 시작해서 서서히 늘리는 게 스팸함에 안 가는 방법이에요.</p>
    <div class="flex flex-wrap items-center gap-2">
      <input v-model.number="cap" type="number" min="1" max="2000" class="input-soft w-28 text-center text-lg font-bold" />
      <span class="text-sm text-ink-muted">통 / 하루</span>
      <button v-for="n in [30, 50, 100, 200, 300]" :key="n" type="button" class="px-3 py-1.5 rounded-lg text-[13px] font-semibold border" :class="cap === n ? 'bg-amber-50 border-amber-300 text-amber-800' : 'border-gray-200 text-ink-muted'" @click="cap = n">{{ n }}</button>
      <button class="btn-primary act-btn" :disabled="busy || !cap || cap === st.daily_cap" @click="saveCap">저장</button>
    </div>
    <div class="mt-3 text-sm text-ink-light leading-relaxed">
      오늘 <b>{{ st.sent_today }}</b>통 보냄 · 오늘 남은 한도 <b>{{ Math.max(0, st.daily_cap - st.sent_today) }}</b>통
      <template v-if="st.counts.queued"> · 대기 {{ st.counts.queued }}통을 다 보내려면 약 <b>{{ Math.ceil(st.counts.queued / st.daily_cap) }}일</b></template>
    </div>
    <p class="text-[11px] text-ink-faint mt-1">참고: Resend 무료 요금제는 하루 100통까지예요. 그보다 많이 보내려면 요금제를 올려야 해요.</p>
  </div>

  <!-- 주소 붙여 넣기 -->
  <div class="card p-4 mb-4">
    <h2 class="font-bold text-ink text-sm mb-1">이메일 주소 붙여 넣기</h2>
    <p class="text-xs text-ink-muted mb-2">한 줄에 하나씩(쉼표·공백으로 구분해도 돼요). 중복·잘못된 주소·수신 거부한 주소·이미 보낸 주소는 자동으로 걸러져요.</p>
    <textarea v-model="raw" rows="6" class="input-soft w-full font-mono text-sm" placeholder="info@example.org&#10;owner@example.com"></textarea>
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

  <!-- 발송 기록 + 수신 거부 목록 (나란히) -->
  <div v-if="st" class="grid lg:grid-cols-2 gap-4 mb-4">
    <div class="card p-4 min-w-0">
      <div class="flex items-center justify-between mb-2">
        <h2 class="font-bold text-ink text-sm">대기열 · 발송 기록 <span class="text-ink-faint font-normal">(최근 {{ st.recent.length }})</span></h2>
        <button v-if="st.counts.queued" class="text-xs text-red-600 underline" :disabled="busy" @click="clearQueue">대기 중 모두 비우기</button>
      </div>
      <div v-if="!st.recent.length" class="text-sm text-ink-faint py-2">아직 넣은 주소가 없어요.</div>
      <div v-else class="overflow-auto max-h-[360px]">
        <table class="w-full text-sm">
          <thead class="sticky top-0 bg-white"><tr class="text-left text-xs text-ink-muted border-b"><th class="py-1.5 pr-3">주소</th><th class="pr-3">상태</th><th class="pr-3">보낸 시각</th><th></th></tr></thead>
          <tbody>
            <tr v-for="r in st.recent" :key="r.id" class="border-b border-gray-50">
              <td class="py-1.5 pr-3 break-all">{{ r.email }}</td>
              <td class="pr-3 whitespace-nowrap">
                <span class="text-xs rounded-full px-2 py-0.5" :class="{ 'bg-amber-50 text-amber-700': r.status === 'queued', 'bg-green-50 text-green-700': r.status === 'sent', 'bg-red-50 text-red-700': r.status === 'failed' }">{{ { queued: '대기', sent: '보냄', failed: '실패' }[r.status] }}</span>
                <span v-if="r.error" class="text-[11px] text-red-600 ml-1" :title="r.error">!</span>
              </td>
              <td class="pr-3 text-xs text-ink-muted whitespace-nowrap">{{ fmt(r.last_sent_at) }}</td>
              <td class="text-right"><button v-if="r.status === 'queued'" class="text-xs text-ink-muted underline" :disabled="busy" @click="removeOne(r.id)">빼기</button></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card p-4 min-w-0 border border-red-100">
      <div class="flex items-center justify-between mb-1">
        <h2 class="font-bold text-ink text-sm">🚫 수신 거부 목록 <span class="text-red-600">{{ st.suppressed_total }}</span></h2>
        <input v-model="supFilter" placeholder="주소 검색" class="input-soft text-xs w-32" />
      </div>
      <p class="text-xs text-ink-muted mb-2">여기 있는 주소는 다시 붙여 넣어도 자동으로 제외돼요. 전화·답장으로 거부한 주소는 아래에서 직접 추가하세요.</p>
      <div class="flex gap-2 mb-2">
        <input v-model="supRaw" placeholder="제외할 주소 (여러 개는 공백·쉼표로 구분)" class="input-soft flex-1 min-w-0 text-sm" />
        <button class="btn-secondary act-btn" :disabled="busy || !supRaw.trim()" @click="addSuppress">추가</button>
      </div>
      <div v-if="!filteredSup.length" class="text-sm text-ink-faint py-2">{{ st.suppressed.length ? '검색 결과가 없어요.' : '아직 수신 거부한 주소가 없어요.' }}</div>
      <div v-else class="overflow-auto max-h-[300px]">
        <table class="w-full text-sm">
          <thead class="sticky top-0 bg-white"><tr class="text-left text-xs text-ink-muted border-b"><th class="py-1.5 pr-3">주소</th><th class="pr-3">거부한 날</th><th>방법</th></tr></thead>
          <tbody>
            <tr v-for="s in filteredSup" :key="s.email" class="border-b border-gray-50">
              <td class="py-1.5 pr-3 break-all">{{ s.email }}</td>
              <td class="pr-3 text-xs text-ink-muted whitespace-nowrap">{{ fmt(s.created_at, true) }}</td>
              <td class="text-xs text-ink-muted whitespace-nowrap">{{ s.source === 'admin' ? '직접 추가' : '손님이 거부' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="st.suppressed_total > st.suppressed.length" class="text-[11px] text-ink-faint mt-2">최근 {{ st.suppressed.length }}개만 보여요 (전체 {{ st.suppressed_total }}개는 모두 제외 중).</p>
    </div>
  </div>

  <!-- 메일 편집 + 실시간 미리보기 -->
  <div v-if="st" class="card p-4 mb-4">
    <h2 class="font-bold text-ink text-sm mb-1">메일 꾸미기</h2>
    <p class="text-xs text-ink-muted mb-3">왼쪽에서 고치면 오른쪽 미리보기에 바로 반영돼요. 제목 앞에는 "(광고)"가, 맨 아래에는 수신 거부 버튼·우편 주소가 항상 자동으로 들어가요.</p>

    <div class="flex flex-wrap items-center gap-3 mb-3">
      <div class="flex gap-1 bg-gray-100 rounded-xl p-1 w-fit">
        <span class="text-[11px] text-ink-muted px-2 self-center">나가는 언어</span>
        <button v-for="l in langs" :key="l.key" type="button" class="px-3 py-1.5 rounded-lg text-[13px] font-semibold" :class="form.lang === l.key ? 'bg-white text-ink shadow-sm' : 'text-ink-muted'" @click="pickSendLang(l.key)">{{ l.label }}</button>
      </div>
      <div class="flex gap-1 bg-gray-100 rounded-xl p-1 w-fit">
        <span class="text-[11px] text-ink-muted px-2 self-center">고치는 글</span>
        <button v-for="l in [{key:'ko',label:'한국어'},{key:'en',label:'English'}]" :key="l.key" type="button" class="px-3 py-1.5 rounded-lg text-[13px] font-semibold" :class="editLang === l.key ? 'bg-white text-ink shadow-sm' : 'text-ink-muted'" @click="editLang = l.key">{{ l.label }}</button>
      </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-4 items-start">
      <!-- 편집 -->
      <div class="min-w-0 space-y-3">
        <div><label class="lbl">메일 제목</label><input v-model="cur.subject" maxlength="150" class="input-soft w-full" /></div>
        <div><label class="lbl">큰 제목 (맨 위 핑크 영역, 줄바꿈 가능)</label><textarea v-model="cur.hero_title" rows="2" maxlength="120" class="input-soft w-full"></textarea></div>
        <div><label class="lbl">큰 제목 아래 한 줄 설명</label><input v-model="cur.hero_sub" maxlength="200" class="input-soft w-full" /></div>
        <div>
          <label class="lbl">본문 (굵게·색·제목·목록·링크를 쓸 수 있어요)</label>
          <RichTextEditor v-model="cur.body" />
        </div>
        <div>
          <label class="lbl">기능 카드 영역 제목</label>
          <input v-model="cur.features_title" maxlength="60" class="input-soft w-full mb-2" />
          <div class="space-y-2">
            <div v-for="(f, i) in cur.features" :key="i" class="rounded-xl border border-gray-200 p-2.5 bg-gray-50/60">
              <div class="flex gap-2 items-center">
                <input v-model="f.icon" maxlength="4" class="input-soft w-14 text-center text-lg" title="이모지 아이콘" />
                <input v-model="f.title" maxlength="60" placeholder="카드 제목" class="input-soft flex-1 min-w-0 font-semibold" />
                <button type="button" class="text-xs text-ink-muted px-1" :disabled="i === 0" @click="move(i, -1)">▲</button>
                <button type="button" class="text-xs text-ink-muted px-1" :disabled="i === cur.features.length - 1" @click="move(i, 1)">▼</button>
                <button type="button" class="text-xs text-red-600 px-1" @click="cur.features.splice(i, 1)">삭제</button>
              </div>
              <textarea v-model="f.desc" rows="2" maxlength="220" placeholder="카드 설명" class="input-soft w-full mt-1.5 text-sm"></textarea>
            </div>
          </div>
          <button v-if="cur.features.length < 8" type="button" class="btn-secondary act-btn mt-2" @click="cur.features.push({ icon: '✨', title: '', desc: '' })">+ 카드 추가</button>
        </div>
        <div><label class="lbl">버튼 글자</label><input v-model="cur.button" maxlength="40" class="input-soft w-full" /></div>
        <div><label class="lbl">맨 아래 마무리 글 (어두운 박스, 줄바꿈 가능)</label><textarea v-model="cur.closing" rows="3" maxlength="600" class="input-soft w-full"></textarea></div>
        <div class="flex flex-wrap gap-2 pt-1">
          <button class="btn-primary act-btn" :disabled="busy" @click="saveContent">저장</button>
          <button class="btn-secondary act-btn" :disabled="busy" @click="resetText">이 언어 기본 문구로 되돌리기</button>
        </div>
      </div>

      <!-- 미리보기 -->
      <div class="min-w-0 lg:sticky lg:top-3">
        <div class="flex items-center justify-between mb-1.5">
          <span class="text-xs text-ink-muted truncate">제목: {{ previewSubject }}</span>
          <span class="text-[11px] text-ink-faint shrink-0">{{ previewBusy ? '갱신 중…' : '실시간 미리보기' }}</span>
        </div>
        <iframe :srcdoc="previewHtml" class="w-full h-[680px] rounded-xl border border-gray-200 bg-white" sandbox=""></iframe>
      </div>
    </div>
  </div>

  <!-- 시험 발송 -->
  <div class="card p-4 mb-8">
    <h2 class="font-bold text-ink text-sm mb-1">시험 발송</h2>
    <p class="text-xs text-ink-muted mb-2">내 주소로 먼저 한 통 보내서 실제 메일 앱에서의 모양과 수신 거부 화면을 확인하세요 (잠겨 있어도 가능, 대기열·하루 한도에 포함되지 않아요). <b>저장한 문구</b>로 나가니 먼저 저장하세요.</p>
    <div class="flex gap-2 flex-wrap">
      <input v-model="testEmail" type="email" placeholder="내 이메일 주소" class="input-soft act-input flex-1 min-w-[220px]" />
      <button class="btn-primary act-btn" :disabled="busy || !testEmail" @click="sendTest">시험 메일 보내기</button>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
import RichTextEditor from '../../components/admin/RichTextEditor.vue'

const st = ref(null)
const busy = ref(false)
const msg = ref(''); const msgOk = ref(true)
const raw = ref(''); const resend = ref(false); const result = ref(null)
const testEmail = ref(''); const supRaw = ref(''); const supFilter = ref('')
const cap = ref(100)
const editLang = ref('ko')
const previewHtml = ref(''); const previewSubject = ref(''); const previewBusy = ref(false)
const empty = () => ({ subject: '', hero_title: '', hero_sub: '', body: '', features_title: '', features: [], button: '', closing: '' })
const form = reactive({ lang: 'ko', texts: { ko: empty(), en: empty() } })
const langs = [{ key: 'ko', label: '한국어' }, { key: 'en', label: 'English' }, { key: 'both', label: '두 언어 모두' }]
const cur = computed(() => form.texts[editLang.value])
const filteredSup = computed(() => {
  const q = supFilter.value.trim().toLowerCase()
  return (st.value?.suppressed || []).filter(s => !q || s.email.includes(q))
})

function say(t, ok = true) { msg.value = t; msgOk.value = ok; setTimeout(() => { if (msg.value === t) msg.value = '' }, 6000) }
function fmt(v, dateOnly = false) { if (!v) return '-'; const s = String(v).replace('T', ' '); return dateOnly ? s.slice(0, 10) : s.slice(0, 16) }
const clone = (o) => JSON.parse(JSON.stringify(o))

function fill(d, keepEdit = false) {
  st.value = d
  cap.value = d.daily_cap
  if (!keepEdit) {
    form.lang = d.lang
    for (const l of ['ko', 'en']) form.texts[l] = clone(d.texts[l])
  }
}
async function run(fn, okMsg) {
  busy.value = true
  try { await fn(); if (okMsg) say(okMsg) }
  catch (e) { say(e?.response?.data?.message || '처리하지 못했어요.', false) }
  finally { busy.value = false }
}
async function load() { await run(async () => { const { data } = await axios.get('/api/admin/email-outreach'); fill(data.data); refreshPreview() }) }

const addEmails = () => run(async () => {
  const { data } = await axios.post('/api/admin/email-outreach/add', { emails: raw.value, resend: resend.value })
  result.value = data.result; fill(data.data, true); raw.value = ''
})
const saveCap = () => run(async () => {
  const { data } = await axios.put('/api/admin/email-outreach/settings', { daily_cap: cap.value }); fill(data.data, true)
}, '하루 발송 개수를 저장했어요.')
const saveContent = () => run(async () => {
  const { data } = await axios.put('/api/admin/email-outreach/settings', { lang: form.lang, texts: form.texts }); fill(data.data, true)
}, '메일 내용을 저장했어요.')
function pickSendLang(k) { form.lang = k; if (k !== 'both') editLang.value = k; axios.put('/api/admin/email-outreach/settings', { lang: k }).catch(() => {}) }
const toggleLive = () => run(async () => {
  const turnOn = !st.value.live_switch
  if (turnOn && !window.confirm('실제 발송을 켜면 대기열의 주소로 바로 메일이 나가기 시작해요 (하루 한도 ' + st.value.daily_cap + '통). 시험 발송으로 모양을 확인하셨나요?')) return
  const { data } = await axios.put('/api/admin/email-outreach/live', { live: turnOn }); fill(data.data, true)
  say(turnOn ? '실제 발송을 켰어요.' : '실제 발송을 껐어요.')
})
const clearQueue = () => run(async () => {
  if (!window.confirm('대기 중인 주소를 모두 비울까요? (이미 보낸 기록은 그대로예요)')) return
  const { data } = await axios.post('/api/admin/email-outreach/clear-queue'); fill(data.data, true)
}, '비웠어요.')
const removeOne = (id) => run(async () => { const { data } = await axios.post('/api/admin/email-outreach/remove/' + id); fill(data.data, true) })
const addSuppress = () => run(async () => {
  const { data } = await axios.post('/api/admin/email-outreach/suppress', { emails: supRaw.value }); fill(data.data, true); supRaw.value = ''
}, '제외 목록에 추가했어요.')
const sendTest = () => run(async () => {
  const { data } = await axios.post('/api/admin/email-outreach/test', { email: testEmail.value, lang: form.lang }); say(data.message)
})
function resetText() {
  if (!st.value || !window.confirm('이 언어의 문구를 기본 문구로 되돌릴까요? (저장 버튼을 눌러야 확정돼요)')) return
  form.texts[editLang.value] = clone(st.value.defaults[editLang.value])
}
function move(i, d) {
  const a = cur.value.features; const j = i + d
  if (j < 0 || j >= a.length) return
  ;[a[i], a[j]] = [a[j], a[i]]
}

// 실시간 미리보기: 고치는 글 기준(두 언어 모두 선택 시에도 지금 고치는 언어 하나만 보여 준다), 입력이 멈추고 0.8초 뒤 갱신
let timer = null
async function refreshPreview() {
  previewBusy.value = true
  try {
    const { data } = await axios.post('/api/admin/email-outreach/preview', { lang: editLang.value, texts: { [editLang.value]: form.texts[editLang.value] } })
    previewHtml.value = data.html; previewSubject.value = data.subject
  } catch { /* 미리보기는 실패해도 편집을 막지 않는다 */ }
  finally { previewBusy.value = false }
}
watch(() => [editLang.value, form.texts[editLang.value]], () => { clearTimeout(timer); timer = setTimeout(refreshPreview, 800) }, { deep: true })

onMounted(load)
</script>

<style scoped>
.lbl { display: block; font-size: 12px; color: #6b7280; margin-bottom: 4px; }
</style>
