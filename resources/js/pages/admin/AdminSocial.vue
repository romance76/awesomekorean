<template>
<div>
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-1">
    <span class="icon-chip w-9 h-9 bg-pink-50 text-pink-600"><AppIcon name="megaphone" :size="18" /></span> SNS 게시 (인스타그램·스레드)
  </h1>
  <p class="text-sm text-ink-muted mb-4">
    MONEY 글·어썸코리안 이벤트·직접 쓴 글을 대기열에 모아, <b>승인한 것만</b> 정해 둔 시간(애틀랜타)에 올려요. 인스타·스레드에서 직접 지운 글은 "목록에서 지우기"로 치우세요(SNS 쪽은 건드리지 않아요).
    계정 정보는 <RouterLink to="/admin/settings" class="underline font-bold text-pink-700">사이트 설정 › SNS 계정</RouterLink>.
  </p>

  <div v-if="paused" class="card p-3 mb-3 bg-red-50 text-red-700 text-sm font-bold">비상 스위치로 SNS 자동 게시가 멈춰 있어요. (시스템 › 비상 스위치)</div>
  <div v-if="!settings.enabled" class="card p-3 mb-3 bg-amber-50 text-amber-800 text-sm font-bold">설정에서 "자동 게시"가 꺼져 있어요. 예약 게시가 돌지 않아요(지금 올리기는 가능).</div>

  <!-- 계정 상태 -->
  <div class="grid sm:grid-cols-2 gap-3 mb-4">
    <div v-for="pf in ['instagram','threads']" :key="pf" class="card p-4 flex items-center gap-3">
      <span class="w-10 h-10 rounded-xl flex items-center justify-center text-white font-black text-sm" :style="pfStyle(pf)">{{ pf === 'instagram' ? 'IG' : '@' }}</span>
      <div class="min-w-0 flex-1">
        <b class="text-ink">{{ pfName(pf) }}</b> <span class="text-xs text-ink-muted">{{ accounts[pf]?.handle }}</span>
        <div class="text-xs text-ink-muted mt-0.5">
          오늘 {{ accounts[pf]?.posted_today || 0 }} / {{ settings[pf + '_daily'] }}개 ·
          토큰 만료 {{ accounts[pf]?.token_expires_at || '—' }}
        </div>
      </div>
      <div class="flex flex-col items-end gap-1">
        <span :class="accounts[pf]?.connected ? 'badge-green' : 'badge-red'">{{ accounts[pf]?.connected ? '연결됨' : '연결 안 됨' }}</span>
        <span :class="accounts[pf]?.autopost_enabled ? 'badge-green' : 'badge-red'" class="!text-[10px]">{{ accounts[pf]?.autopost_enabled ? '자동 게시 켜짐' : '자동 게시 꺼짐(SNS 계정에서 켜기)' }}</span>
      </div>
    </div>
  </div>

  <!-- 탭 -->
  <div class="flex gap-2 mb-3 flex-wrap" role="tablist">
    <button v-for="t in tabs" :key="t.k" @click="setTab(t.k)" class="rounded-full border px-4 py-2 text-sm font-bold"
      :class="tab === t.k ? 'bg-ink text-white border-ink' : 'bg-white text-ink border-gray-200'">{{ t.l }}<span v-if="t.count !== undefined" class="ml-1 opacity-70">{{ count(t.count) }}</span></button>
  </div>

  <!-- 목록 탭 -->
  <template v-if="listTabs.includes(tab)">
    <div class="flex items-center gap-2 mb-3 flex-wrap">
      <select v-model="platform" @change="load(1)" class="input-soft !w-auto text-sm">
        <option value="">두 곳 모두</option><option value="instagram">인스타그램</option><option value="threads">스레드</option>
      </select>
      <template v-if="tab === 'pending'">
        <button @click="bulk('approve')" :disabled="!picked.length" class="btn-primary px-4 py-2 text-sm disabled:opacity-40">고른 {{ picked.length }}개 승인</button>
        <button @click="pickAll" class="text-sm font-bold text-ink-light underline">이 쪽 전부 고르기</button>
      </template>
      <span class="text-xs text-ink-muted ml-auto">{{ tabHelp }}</span>
    </div>

    <div v-if="loading" class="card p-8 text-center text-ink-muted text-sm">불러오는 중…</div>
    <div v-else-if="!rows.length" class="card p-8 text-center text-ink-muted text-sm">없어요. <template v-if="tab === 'pending'">"새로 넣기" 탭에서 MONEY 글이나 이벤트를 넣어 보세요.</template></div>

    <div v-for="p in rows" :key="p.id" class="card p-3 mb-2">
      <div class="flex gap-3">
        <input v-if="tab === 'pending'" type="checkbox" :value="p.id" v-model="picked" class="mt-1 w-4 h-4 flex-shrink-0" />
        <a v-if="p.image_url" :href="p.image_url" target="_blank" class="flex-shrink-0"><img :src="p.image_url" class="w-20 h-[100px] object-cover rounded-lg border border-gray-100" loading="lazy" /></a>
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-1.5 flex-wrap text-[11px] font-bold">
            <span class="rounded-full px-2 py-0.5 text-white" :style="pfStyle(p.platform)">{{ pfName(p.platform) }}</span>
            <span class="rounded-full px-2 py-0.5 bg-gray-100 text-ink-light">{{ srcName(p.source_type) }}</span>
            <span v-if="p.priority >= 10" class="rounded-full px-2 py-0.5 bg-pink-50 text-pink-700">우선</span>
            <span v-if="p.not_before" class="rounded-full px-2 py-0.5 bg-amber-50 text-amber-800">{{ fmt(p.not_before) }} 이후</span>
            <span class="text-ink-faint font-normal">#{{ p.id }}</span>
          </div>
          <div class="font-bold text-ink text-sm mt-1 truncate">{{ p.title }}</div>
          <textarea v-if="editing === p.id" v-model="draft" rows="8" class="input-soft w-full text-sm mt-1"></textarea>
          <p v-else class="text-xs text-ink-light whitespace-pre-line mt-1 line-clamp-4">{{ p.caption }}</p>
          <div v-if="editing === p.id" class="text-[11px] mt-0.5" :class="draft.length > limit(p) ? 'text-red-600 font-bold' : 'text-ink-faint'">{{ draft.length }} / {{ limit(p) }}자</div>
          <div v-if="p.error" class="text-xs text-red-600 mt-1">⚠ {{ p.error }}</div>
          <div v-if="p.external_url" class="text-xs mt-1"><a :href="p.external_url" target="_blank" class="text-pink-700 font-bold underline">올라간 글 보기 ↗</a> · {{ fmt(p.posted_at) }}</div>
          <div class="flex gap-1.5 flex-wrap mt-2">
            <template v-if="p.status !== 'posted'">
              <button v-if="editing !== p.id" @click="editing = p.id; draft = p.caption" class="sbtn">글 고치기</button>
              <template v-else><button @click="saveCaption(p)" class="sbtn !bg-ink !text-white">저장</button><button @click="editing = null" class="sbtn">취소</button></template>
              <button v-if="p.status === 'pending' || p.status === 'skipped'" @click="act([p.id], 'approve')" class="sbtn !bg-emerald-600 !text-white">승인</button>
              <button v-if="p.status === 'approved'" @click="act([p.id], 'unapprove')" class="sbtn">승인 취소</button>
              <button v-if="p.status === 'failed'" @click="act([p.id], 'retry')" class="sbtn !bg-emerald-600 !text-white">다시 시도</button>
              <button v-if="p.status !== 'skipped'" @click="act([p.id], 'skip')" class="sbtn">건너뛰기</button>
              <button v-if="p.platform === 'instagram'" @click="regen(p)" class="sbtn">카드 다시 만들기</button>
              <button @click="publishNow(p)" :disabled="busy === p.id" class="sbtn !border-pink-300 !text-pink-700">{{ busy === p.id ? '올리는 중…' : '지금 올리기' }}</button>
            </template>
            <a v-if="p.link_url" :href="p.link_url" target="_blank" class="sbtn">원본 보기 ↗</a>
            <button @click="remove(p)" class="sbtn !border-red-200 !text-red-600">목록에서 지우기</button>
          </div>
        </div>
      </div>
    </div>
    <div v-if="lastPage > 1" class="flex justify-center gap-2 mt-3">
      <button :disabled="page <= 1" @click="load(page - 1)" class="sbtn">이전</button>
      <span class="text-sm text-ink-muted py-1">{{ page }} / {{ lastPage }}</span>
      <button :disabled="page >= lastPage" @click="load(page + 1)" class="sbtn">다음</button>
    </div>
  </template>

  <!-- 새로 넣기 -->
  <div v-else-if="tab === 'add'" class="grid lg:grid-cols-2 gap-4">
    <div class="card p-4 space-y-3">
      <b class="text-ink">MONEY 글 넣기</b>
      <p class="text-xs text-ink-muted">운영팀 글을 오래된 것부터 골라 두 곳(인스타·스레드)에 넣어요. 이미 넣은 글은 건너뛰어요. 새로 올라오는 MONEY 글은 자동으로 들어가요.</p>
      <div class="grid grid-cols-2 gap-2">
        <label><span class="lbl2">카페</span>
          <select v-model="money.cafe" class="input-soft w-full text-sm"><option value="">모든 카페</option><option v-for="c in cafes" :key="c.slug" :value="c.slug">{{ c.name }}</option></select>
        </label>
        <label><span class="lbl2">몇 개 (최대 30)</span><input v-model.number="money.limit" type="number" min="1" max="30" class="input-soft w-full text-sm" /></label>
      </div>
      <button @click="addMoney" :disabled="adding" class="btn-primary px-4 py-2 text-sm">{{ adding ? '넣는 중…(카드뉴스 만드는 중)' : '대기열에 넣기' }}</button>
    </div>

    <div class="card p-4 space-y-2">
      <b class="text-ink">어썸코리안 이벤트</b>
      <p class="text-xs text-ink-muted">새 이벤트·경품 이벤트는 만들어지면 자동으로 들어가요(우선 게시). 예전 이벤트는 여기서 넣어요.</p>
      <div v-if="!events.length" class="text-sm text-ink-muted">진행 중인 어썸코리안 이벤트가 없어요.</div>
      <div v-for="e in events" :key="e.id" class="flex items-center gap-2 text-sm border-b border-gray-50 py-1.5">
        <span class="text-[11px] font-bold rounded-full px-2 py-0.5" :class="e.event_type === 'sweepstakes' ? 'bg-pink-50 text-pink-700' : 'bg-orange-50 text-orange-700'">{{ e.event_type === 'sweepstakes' ? '경품' : '이벤트' }}</span>
        <span class="flex-1 min-w-0 truncate">{{ e.title }}</span>
        <span v-if="e.queued" class="text-xs text-ink-muted">넣음</span>
        <button v-else @click="addEvent(e)" class="sbtn">넣기</button>
      </div>
    </div>

    <form @submit.prevent="addManual" class="card p-4 space-y-3 lg:col-span-2">
      <b class="text-ink">직접 쓰기 (사이트 소개·공지·기본 정보)</b>
      <div class="grid sm:grid-cols-2 gap-3">
        <label class="block"><span class="lbl2">제목 (카드뉴스 큰 글씨)</span><input v-model="manual.title" maxlength="120" class="input-soft w-full" placeholder="예) 미국 한인 커뮤니티 어썸코리안을 소개해요" /></label>
        <label class="block"><span class="lbl2">링크 (선택, https://)</span><input v-model="manual.link_url" class="input-soft w-full" placeholder="https://awesomekorean.com/money" /></label>
      </div>
      <label class="block"><span class="lbl2">본문 (인스타 캡션 / 스레드 글 — 스레드는 앞 440자만)</span><textarea v-model="manual.caption" rows="5" maxlength="2000" class="input-soft w-full text-sm"></textarea></label>
      <div class="grid sm:grid-cols-3 gap-2">
        <label v-for="i in 3" :key="i" class="block"><span class="lbl2">카드 핵심 {{ i }} (선택)</span><input v-model="manual.lines[i-1]" maxlength="80" class="input-soft w-full text-sm" /></label>
      </div>
      <div class="grid sm:grid-cols-4 gap-2 items-end">
        <label class="block"><span class="lbl2">카드 색</span><select v-model="manual.theme" class="input-soft w-full text-sm"><option v-for="t in themes" :key="t" :value="t">{{ t }}</option></select></label>
        <label class="block"><span class="lbl2">작은 꼬리표</span><input v-model="manual.tag" maxlength="30" class="input-soft w-full text-sm" placeholder="어썸코리안 소식" /></label>
        <label class="block"><span class="lbl2">사진 직접 올리기 (선택)</span><input type="file" accept="image/jpeg,image/png" @change="e => manual.image = e.target.files[0]" class="text-xs" /></label>
        <label class="block"><span class="lbl2">이 시각 이후에 (선택)</span><input v-model="manual.not_before" type="datetime-local" class="input-soft w-full text-sm" /></label>
      </div>
      <div class="flex items-center gap-4 text-sm">
        <label class="inline-flex items-center gap-1.5"><input type="checkbox" value="instagram" v-model="manual.platforms" /> 인스타그램</label>
        <label class="inline-flex items-center gap-1.5"><input type="checkbox" value="threads" v-model="manual.platforms" /> 스레드</label>
        <button type="submit" :disabled="adding" class="btn-primary px-4 py-2 text-sm ml-auto">{{ adding ? '넣는 중…' : '대기열에 넣기' }}</button>
      </div>
      <p class="text-xs text-ink-muted">사진을 안 올리면 인스타용 카드뉴스를 자동으로 만들어요. 넣은 뒤 "승인 대기" 탭에서 미리보고 승인하세요.</p>
    </form>
  </div>

  <!-- 설정 -->
  <form v-else-if="tab === 'settings'" @submit.prevent="saveSettings" class="card p-4 space-y-3 max-w-2xl">
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="form.enabled" /> <b>자동 게시 켜기</b> (끄면 예약 게시가 멈춰요)</label>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="form.require_approval" /> 승인한 글만 올리기 (끄면 대기 글도 바로 예약돼요)</label>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="form.auto_money" /> 새 MONEY 운영팀 글을 자동으로 대기열에</label>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="form.auto_events" /> 어썸코리안 이벤트를 자동으로 대기열에</label>
    <div class="grid grid-cols-2 gap-3">
      <label><span class="lbl2">인스타그램 하루 개수</span><input v-model.number="form.instagram_daily" type="number" min="0" max="20" class="input-soft w-full" /></label>
      <label><span class="lbl2">스레드 하루 개수</span><input v-model.number="form.threads_daily" type="number" min="0" max="30" class="input-soft w-full" /></label>
    </div>
    <label class="block"><span class="lbl2">게시 시간 (애틀랜타, 쉼표로) — 예: 08:00, 12:00, 19:00</span><input v-model="timesText" class="input-soft w-full" /></label>
    <label class="block"><span class="lbl2">기본 해시태그 (인스타)</span><input v-model="form.hashtags" class="input-soft w-full" /></label>
    <button type="submit" class="btn-primary px-5 py-2 text-sm">설정 저장</button>
    <p class="text-xs text-ink-muted">하루 개수는 남은 시간대에 나눠 올려요. 계정별 "자동 게시" 체크는 사이트 설정 › SNS 계정에서 켜요.</p>
  </form>
  <div v-if="note.show" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[10050] rounded-xl px-4 py-2.5 text-sm font-bold text-white shadow-lg" :class="note.err ? 'bg-red-500' : 'bg-emerald-600'">{{ note.msg }}</div>
</div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

// 화면 아래 작은 알림 (3초)
const note = reactive({ show: false, msg: '', err: false })
let noteTimer = null
const say = (m, type = 'success') => { Object.assign(note, { show: true, msg: m, err: type === 'error' }); clearTimeout(noteTimer); noteTimer = setTimeout(() => note.show = false, 3500) }

const tab = ref('pending')
const listTabs = ['pending', 'approved', 'posted', 'failed', 'skipped']
const tabs = computed(() => [
  { k: 'pending', l: '승인 대기', count: 'pending' }, { k: 'approved', l: '예약됨', count: 'approved' },
  { k: 'posted', l: '올린 글', count: 'posted' }, { k: 'failed', l: '실패', count: 'failed' }, { k: 'skipped', l: '건너뜀', count: 'skipped' },
  { k: 'add', l: '+ 새로 넣기' }, { k: 'settings', l: '설정' },
])
const tabHelp = computed(() => ({ pending: '승인하면 "예약됨"으로 가서 정해 둔 시간에 올라가요.', approved: '우선순위 높은 것 → 먼저 들어온 것 순서로 올라가요.',
  posted: '최근에 올린 순서예요.', failed: '3번 실패하면 여기로 와요. 고친 뒤 "다시 시도".', skipped: '올리지 않기로 한 글. 승인하면 다시 예약돼요.' })[tab.value] || '')

const rows = ref([]); const loading = ref(false); const page = ref(1); const lastPage = ref(1)
const platform = ref(''); const counts = ref([]); const accounts = ref({}); const paused = ref(false)
const settings = ref({ enabled: true, instagram_daily: 2, threads_daily: 3, times: [] })
const picked = ref([]); const editing = ref(null); const draft = ref(''); const busy = ref(null); const adding = ref(false)
const cafes = ref([]); const events = ref([])
const money = reactive({ cafe: '', limit: 10 })
const themes = ['orange', 'emerald', 'teal', 'sky', 'indigo', 'slate', 'violet', 'blue', 'amber', 'rose', 'yellow', 'pink']
const manual = reactive({ title: '', caption: '', link_url: '', lines: ['', '', ''], theme: 'orange', tag: '', image: null, not_before: '', platforms: ['instagram', 'threads'] })
const form = reactive({}); const timesText = ref('')

const pfName = pf => pf === 'instagram' ? '인스타그램' : '스레드'
const pfStyle = pf => pf === 'instagram' ? 'background:linear-gradient(135deg,#F58529,#DD2A7B,#8134AF)' : 'background:#111'
const srcName = s => ({ money_post: 'MONEY 글', event: '이벤트', manual: '직접 쓰기' })[s] || s
const limit = p => p.platform === 'threads' ? 500 : 2200
const fmt = d => d ? new Date(d).toLocaleString('ko-KR', { timeZone: 'America/New_York', month: 'numeric', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : ''
const count = st => counts.value.filter(c => c.status === st && (!platform.value || c.platform === platform.value)).reduce((a, c) => a + Number(c.c), 0)
const errMsg = e => { const er = e.response?.data?.errors; return er ? Object.values(er)[0][0] : (e.response?.data?.message || '실패했어요') }

function setTab(k) {
  tab.value = k; picked.value = []; editing.value = null
  if (listTabs.includes(k)) load(1)
  if (k === 'add') { loadCafes(); loadEvents() }
  if (k === 'settings') { Object.assign(form, JSON.parse(JSON.stringify(settings.value))); timesText.value = (settings.value.times || []).join(', ') }
}

async function load(p = 1) {
  loading.value = true; page.value = p
  try {
    const { data } = await axios.get('/api/admin/social-posts', { params: { status: listTabs.includes(tab.value) ? tab.value : 'pending', platform: platform.value || undefined, page: p } })
    rows.value = data.data.data; lastPage.value = data.data.last_page
    counts.value = data.counts; accounts.value = data.accounts; settings.value = data.settings; paused.value = data.paused
  } catch (e) { say(errMsg(e), 'error') }
  loading.value = false
}
function pickAll() { picked.value = rows.value.map(r => r.id) }
async function act(ids, action) {
  try { const { data } = await axios.post('/api/admin/social-posts/status', { ids, action }); say(`${data.changed}개 처리했어요`); picked.value = []; load(page.value) } catch (e) { say(errMsg(e), 'error') }
}
const bulk = a => act(picked.value, a)
async function saveCaption(p) {
  try { await axios.put(`/api/admin/social-posts/${p.id}`, { caption: draft.value }); p.caption = draft.value; editing.value = null; say('저장했어요') } catch (e) { say(errMsg(e), 'error') }
}
async function regen(p) {
  try { const { data } = await axios.post(`/api/admin/social-posts/${p.id}/card`); p.image_url = data.data.image_url; say('카드뉴스를 다시 만들었어요') } catch (e) { say(errMsg(e), 'error') }
}
async function publishNow(p) {
  if (!confirm(`지금 바로 ${pfName(p.platform)}에 올릴까요?\n\n"${p.title}"\n\n올린 글은 SNS 앱에서 직접 지워야 해요(여기 목록은 \"목록에서 지우기\"로 치울 수 있어요).`)) return
  busy.value = p.id
  try { const { data } = await axios.post(`/api/admin/social-posts/${p.id}/publish`); say(data.message); load(page.value) } catch (e) { say(errMsg(e), 'error'); load(page.value) }
  busy.value = null
}
async function remove(p) {
  const posted = p.status === 'posted'
  if (!confirm(`이 목록에서 지울까요?\n\n"${p.title}" (${pfName(p.platform)})\n\n` + (posted ? '이미 올라간 글은 인스타·스레드에 그대로 남아요. SNS 앱에서 직접 지운 글을 목록에서도 치울 때만 쓰세요.' : '아직 올리지 않은 글이에요. 지우면 다시 살릴 수 없어요.'))) return
  try { await axios.delete(`/api/admin/social-posts/${p.id}`); say('목록에서 지웠어요'); load(page.value) } catch (e) { say(errMsg(e), 'error') }
}
async function loadCafes() { if (cafes.value.length) return; try { const { data } = await axios.get('/api/money/cafes'); cafes.value = data.data || data.cafes || [] } catch {} }
async function loadEvents() { try { const { data } = await axios.get('/api/admin/social-posts/events'); events.value = data.data } catch {} }
async function addMoney() {
  adding.value = true
  try { const { data } = await axios.post('/api/admin/social-posts/enqueue-money', { cafe: money.cafe || null, limit: money.limit }); say(data.message); load(1) } catch (e) { say(errMsg(e), 'error') }
  adding.value = false
}
async function addEvent(e) {
  try { const { data } = await axios.post(`/api/admin/social-posts/events/${e.id}`); say(data.message); loadEvents() } catch (er) { say(errMsg(er), 'error') }
}
async function addManual() {
  if (!manual.title.trim() || !manual.caption.trim() || !manual.platforms.length) return say('제목·본문·올릴 곳을 채워 주세요', 'error')
  adding.value = true
  const fd = new FormData()
  fd.append('title', manual.title); fd.append('caption', manual.caption); fd.append('theme', manual.theme); fd.append('tag', manual.tag || '')
  if (manual.link_url) fd.append('link_url', manual.link_url)
  if (manual.not_before) fd.append('not_before', manual.not_before)
  manual.lines.filter(Boolean).forEach(l => fd.append('lines[]', l))
  manual.platforms.forEach(p => fd.append('platforms[]', p))
  if (manual.image) fd.append('image', manual.image)
  try {
    const { data } = await axios.post('/api/admin/social-posts/manual', fd)
    say(data.message); Object.assign(manual, { title: '', caption: '', link_url: '', lines: ['', '', ''], image: null, not_before: '' }); setTab('pending')
  } catch (e) { say(errMsg(e), 'error') }
  adding.value = false
}
async function saveSettings() {
  const times = timesText.value.split(/[,\s]+/).map(s => s.trim()).filter(Boolean)
  try { const { data } = await axios.put('/api/admin/social-posts/settings', { ...form, times }); settings.value = data.settings; say(data.message) } catch (e) { say(errMsg(e), 'error') }
}

onMounted(() => load(1))
</script>

<style scoped>
.sbtn { @apply rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-xs font-bold text-ink hover:bg-gray-50 disabled:opacity-40; }
.lbl2 { @apply block text-xs font-bold text-ink-light mb-1; }
</style>
