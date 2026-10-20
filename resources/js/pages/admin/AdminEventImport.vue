<template>
<div class="space-y-5 pb-6">
  <div>
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-1">
      <span class="icon-chip w-9 h-9 bg-pink-50 text-pink-600"><AppIcon name="ticket" :size="20" /></span>
      지역 행사 가져오기
    </h1>
    <p class="text-sm text-ink-muted leading-relaxed">Ticketmaster 에서 한인이 많이 사는 주의 콘서트·스포츠·축제를 매일 새벽 자동으로 가져와 "생활정보 › 이벤트"에 올려요. 주마다 켜고 끌 수 있고, 마음에 안 드는 행사는 숨길 수 있어요.</p>
  </div>

  <p v-if="msg" class="text-sm rounded-xl p-3" :class="msgOk ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600'">{{ msg }}</p>
  <div v-if="loading" class="text-center py-12 text-ink-muted">불러오는 중...</div>

  <template v-else>
    <!-- 요약 -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      <div class="card p-4"><div class="text-xs text-ink-muted">가져온 행사(전체)</div><div class="text-2xl font-black text-ink tabular-nums">{{ info.total }}</div></div>
      <div class="card p-4"><div class="text-xs text-ink-muted">켜 둔 주</div><div class="text-2xl font-black text-ink tabular-nums">{{ enabledCount }} <span class="text-sm font-semibold text-ink-faint">/ {{ info.states.length }}</span></div></div>
      <div class="card p-4"><div class="text-xs text-ink-muted">앞으로 열릴 행사</div>
        <div class="text-[13px] text-ink-light mt-1 leading-snug">
          <template v-if="Object.keys(info.upcoming_by_category || {}).length"><span v-for="(n, c) in info.upcoming_by_category" :key="c" class="inline-block mr-2">{{ catLabel[c] || c }} <b class="tabular-nums">{{ n }}</b></span></template>
          <span v-else class="text-ink-faint">아직 없어요</span>
        </div></div>
      <div class="card p-4"><div class="text-xs text-ink-muted">마지막 실행</div><div class="text-sm font-bold text-ink mt-1">{{ info.last_run_at ? fmt(info.last_run_at) : '아직 없음' }}</div></div>
    </div>

    <!-- API 키 -->
    <div class="card p-4 lg:p-5 space-y-3">
      <div class="flex items-center gap-2 flex-wrap">
        <div class="font-bold text-ink">Ticketmaster API 키</div>
        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full" :class="info.key_set ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600'">{{ info.key_set ? '등록됨 ' + (info.key_masked || '') + (info.key_source === 'env' ? ' (서버 설정)' : '') : '없음 — 가져오기가 멈춰 있어요' }}</span>
      </div>
      <p class="text-xs text-ink-muted leading-relaxed">무료 키는 <a href="https://developer.ticketmaster.com/" target="_blank" rel="noopener" class="underline text-primary">developer.ticketmaster.com</a> 에서 가입하면 바로 받을 수 있어요(Consumer Key). 하루 5,000건까지 무료예요. 키는 암호화해서 저장하고, 화면에는 앞뒤 3글자만 보여요.</p>
      <div class="flex flex-wrap gap-2">
        <input v-model="keyInput" type="password" autocomplete="off" placeholder="새 API 키 붙여넣기" aria-label="Ticketmaster API 키" class="input-soft flex-1 min-w-[220px] !py-2 text-sm font-mono" />
        <button @click="saveKey" :disabled="busy || !keyInput.trim()" class="btn-primary !px-5 !py-2 disabled:opacity-40">저장</button>
        <button v-if="info.key_source === 'admin'" @click="clearKey" :disabled="busy" class="btn-secondary !px-4 !py-2">키 지우기</button>
      </div>
      <p class="text-[11px] text-ink-faint">키 저장·삭제는 최고관리자만 할 수 있어요.</p>
    </div>

    <!-- 주마다 가져올 개수 -->
    <div class="card p-4 lg:p-5 space-y-2">
      <div class="font-bold text-ink">주마다 가져올 행사 수</div>
      <p class="text-xs text-ink-muted leading-relaxed">각 주에서 가장 인기 있는 행사만 이 개수만큼 가져와요(음악·스포츠·공연·축제를 골고루, 같은 투어의 중복 일정은 하나만). 영어 소개문은 자동으로 한글 번역해요. 다음 가져오기부터 적용돼요.</p>
      <div class="flex items-center gap-2">
        <input v-model.number="perState" type="number" min="3" max="40" inputmode="numeric" aria-label="주마다 가져올 행사 수" class="input-soft !w-24 !py-2 text-sm text-right tabular-nums" />
        <span class="text-sm text-ink-muted">개 / 주</span>
        <button @click="savePerState" :disabled="busy" class="btn-secondary !px-4 !py-2 text-sm">저장</button>
      </div>
    </div>

    <!-- 주 목록 -->
    <div class="card p-4 lg:p-5 space-y-3">
      <div class="flex items-center gap-2 flex-wrap">
        <div class="font-bold text-ink mr-auto">가져올 주 (한인 많은 순)</div>
        <button @click="setAll(true)" :disabled="busy" class="btn-secondary !px-3 !py-1.5 text-xs">모두 켜기</button>
        <button @click="setAll(false)" :disabled="busy" class="btn-secondary !px-3 !py-1.5 text-xs">모두 끄기</button>
        <button @click="runAll" :disabled="busy || info.running || !info.key_set" class="btn-primary !px-4 !py-1.5 text-xs disabled:opacity-40">{{ info.running ? '가져오는 중…' : '켜 둔 주 지금 모두 가져오기' }}</button>
      </div>
      <div v-if="info.running" class="rounded-xl bg-amber-50 border border-amber-200 p-3 space-y-2" role="status" aria-live="polite">
        <div class="flex items-center gap-2 text-sm font-bold text-amber-800">
          <span class="inline-block w-4 h-4 rounded-full border-2 border-amber-500 border-t-transparent animate-spin"></span>
          <template v-if="info.progress && info.progress.total">가져오는 중… {{ info.progress.done }} / {{ info.progress.total }}개 주<span v-if="info.progress.current"> (지금: {{ info.progress.current }})</span></template>
          <template v-else>가져오는 중이에요…</template>
        </div>
        <div v-if="info.progress && info.progress.total" class="h-2 rounded-full bg-amber-100 overflow-hidden"><div class="h-full bg-amber-500 transition-all" :style="{ width: Math.round(info.progress.done / info.progress.total * 100) + '%' }"></div></div>
        <p class="text-xs text-amber-700">지금까지 {{ info.progress?.fetched || 0 }}건 확인 · 새로 {{ info.progress?.created || 0 }}건 추가. 몇 분 걸려요. 이 화면은 자동으로 갱신돼요.</p>
      </div>
      <div class="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-3">
        <div v-for="s in info.states" :key="s.code" class="rounded-xl border p-3 flex flex-col gap-1.5" :class="s.enabled ? 'border-gray-200 bg-white' : 'border-gray-100 bg-gray-50 opacity-70'">
          <div class="flex items-center gap-2">
            <span class="text-xs font-black px-1.5 py-0.5 rounded bg-gray-100 text-ink-light">{{ s.code }}</span>
            <span class="font-bold text-sm text-ink flex-1 truncate">{{ s.name }}</span>
            <button @click="toggleState(s)" :disabled="busy" role="switch" :aria-checked="!!s.enabled" :aria-label="s.name + ' 가져오기'" class="relative w-11 h-6 rounded-full transition" :class="s.enabled ? 'bg-emerald-500' : 'bg-gray-300'">
              <span class="absolute top-0.5 w-5 h-5 rounded-full bg-white shadow transition-all" :style="{ left: s.enabled ? '22px' : '2px' }"></span>
            </button>
          </div>
          <div class="text-xs text-ink-muted tabular-nums">행사 {{ s.event_count }}<template v-if="s.hidden_count">(숨김 {{ s.hidden_count }})</template></div>
          <div class="text-[11px] text-ink-faint">{{ s.last_run_at ? '마지막 ' + fmt(s.last_run_at) + ' · 새로 ' + s.last_created + ' · 갱신 ' + s.last_updated : '아직 가져온 적 없음' }}</div>
          <div v-if="s.last_error" class="text-[11px] text-red-500 break-words">⚠ {{ s.last_error }}</div>
          <button @click="runOne(s)" :disabled="busy || info.running || !info.key_set" class="self-start text-xs font-bold text-primary disabled:opacity-40">{{ runningCode === s.code ? '가져오는 중…' : '이 주만 지금 가져오기' }}</button>
        </div>
      </div>
    </div>

    <!-- 가져온 행사 -->
    <div class="card p-4 lg:p-5 space-y-3">
      <div class="font-bold text-ink">가져온 행사</div>
      <div class="flex flex-wrap gap-2">
        <select v-model="f.state" @change="loadEvents(1)" class="input-soft !py-1.5 text-sm !w-auto" aria-label="주">
          <option value="">모든 주</option>
          <option v-for="s in info.states" :key="s.code" :value="s.code">{{ s.name }}</option>
        </select>
        <select v-model="f.category" @change="loadEvents(1)" class="input-soft !py-1.5 text-sm !w-auto" aria-label="카테고리">
          <option value="">모든 카테고리</option>
          <option v-for="(l, c) in catLabel" :key="c" :value="c">{{ l }}</option>
        </select>
        <select v-model="f.is_active" @change="loadEvents(1)" class="input-soft !py-1.5 text-sm !w-auto" aria-label="상태">
          <option value="">보이는 것 + 숨긴 것</option>
          <option value="1">보이는 것</option>
          <option value="0">숨긴 것</option>
        </select>
        <input v-model="f.q" @keyup.enter="loadEvents(1)" placeholder="제목·장소·도시 검색" aria-label="검색" class="input-soft !py-1.5 text-sm flex-1 min-w-[160px]" />
        <button @click="loadEvents(1)" class="btn-secondary !px-4 !py-1.5 text-sm">검색</button>
      </div>
      <div v-if="evLoading" class="text-center py-6 text-ink-muted text-sm">불러오는 중...</div>
      <div v-else-if="!events.length" class="text-center py-8 text-ink-faint text-sm">가져온 행사가 없어요.</div>
      <div v-else class="divide-y divide-gray-50">
        <div v-for="e in events" :key="e.id" class="py-2.5 flex items-center gap-3" :class="e.is_active ? '' : 'opacity-50'">
          <img v-if="e.image_url" :src="e.image_url" alt="" loading="lazy" class="w-16 h-10 object-cover rounded-md bg-gray-100 flex-shrink-0 hidden sm:block" />
          <div class="min-w-0 flex-1">
            <div class="text-sm font-semibold text-ink truncate">{{ e.title }}</div>
            <div class="text-xs text-ink-muted truncate">{{ catLabel[e.category] || e.category }} · {{ fmt(e.start_date) }} · {{ [e.venue, e.city, e.state].filter(Boolean).join(', ') }}</div>
          </div>
          <a v-if="e.url" :href="e.url" target="_blank" rel="noopener" class="text-xs text-primary underline flex-shrink-0">원본</a>
          <button @click="toggleEvent(e)" class="btn-secondary !px-3 !py-1 text-xs flex-shrink-0">{{ e.is_active ? '숨기기' : '다시 보이기' }}</button>
          <button @click="delEvent(e)" class="text-xs text-red-500 flex-shrink-0 px-1" aria-label="삭제">삭제</button>
        </div>
      </div>
      <div v-if="meta.last_page > 1" class="flex items-center justify-center gap-3 pt-1">
        <button @click="loadEvents(meta.current_page - 1)" :disabled="meta.current_page <= 1" class="btn-secondary !px-3 !py-1 text-sm disabled:opacity-40">이전</button>
        <span class="text-sm text-ink-muted tabular-nums">{{ meta.current_page }} / {{ meta.last_page }} (총 {{ meta.total }}건)</span>
        <button @click="loadEvents(meta.current_page + 1)" :disabled="meta.current_page >= meta.last_page" class="btn-secondary !px-3 !py-1 text-sm disabled:opacity-40">다음</button>
      </div>
      <p class="text-[11px] text-ink-faint">숨긴 행사는 다음 가져오기 때도 다시 켜지지 않아요. 삭제는 최고관리자만 할 수 있고, 삭제해도 다음 가져오기 때 다시 들어올 수 있으니 안 보이게만 하려면 "숨기기"를 쓰세요.</p>
    </div>
  </template>
</div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const catLabel = { festival: '🎪 지역 축제·행사', concert: '🎤 콘서트·공연', sports: '⚽ 스포츠', community: '👥 모임·커뮤니티', education: '📚 교육·체험' }

const loading = ref(true)
const busy = ref(false)
const runningCode = ref('')
const msg = ref('')
const msgOk = ref(true)
const info = ref({ states: [], total: 0, key_set: false, running: false })
const keyInput = ref('')
const perState = ref(12)

const events = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const evLoading = ref(false)
const f = ref({ state: '', category: '', is_active: '', q: '' })

const enabledCount = computed(() => info.value.states.filter(s => s.enabled).length)

function say(text, ok = true) { msg.value = text; msgOk.value = ok }
function errText(e, fallback) { return e.response?.data?.message || fallback }
function fmt(v) {
  if (!v) return ''
  return new Date(v).toLocaleString('ko-KR', { month: 'numeric', day: 'numeric', hour: 'numeric', minute: '2-digit' })
}

async function load() {
  try {
    const { data } = await axios.get('/api/admin/event-import')
    info.value = data.data
    if (!perStateTouched) perState.value = data.data.per_state || 12
  } catch (e) { say(errText(e, '불러오지 못했어요'), false) }
  loading.value = false
}

async function loadEvents(page = 1) {
  evLoading.value = true
  try {
    const params = { page, per_page: 30 }
    for (const k of ['state', 'category', 'is_active', 'q']) if (f.value[k] !== '') params[k] = f.value[k]
    const { data } = await axios.get('/api/admin/event-import/events', { params })
    events.value = data.data.data || []
    meta.value = { current_page: data.data.current_page, last_page: data.data.last_page, total: data.data.total }
  } catch (e) { say(errText(e, '행사 목록을 불러오지 못했어요'), false) }
  evLoading.value = false
}

async function act(fn) {
  busy.value = true; msg.value = ''
  try { await fn() } catch (e) { say(errText(e, '실패했어요'), false) }
  busy.value = false
}

const saveKey = () => act(async () => {
  const { data } = await axios.put('/api/admin/event-import/key', { key: keyInput.value.trim() })
  keyInput.value = ''
  say(data.message); await load()
})
// 브라우저 확인창(confirm)은 막히면 버튼이 무반응이 되므로, 같은 버튼을 5초 안에 한 번 더 눌러야 실행되는 방식으로 확인
const asked = ref('')
let askTimer = null
function twice(key, hint) {
  if (asked.value === key) { asked.value = ''; clearTimeout(askTimer); return true }
  asked.value = key; say(hint + ' — 맞으면 같은 버튼을 한 번 더 누르세요', true)
  clearTimeout(askTimer); askTimer = setTimeout(() => { asked.value = ''; msg.value = '' }, 5000)
  return false
}

const clearKey = () => act(async () => {
  if (!twice('clearKey', '저장해 둔 API 키를 지울까요?')) return
  const { data } = await axios.put('/api/admin/event-import/key', { key: '' })
  say(data.message); await load()
})
let perStateTouched = false
const savePerState = () => act(async () => {
  perStateTouched = true
  const { data } = await axios.put('/api/admin/event-import/settings', { per_state: perState.value })
  perState.value = data.per_state
  say(data.message)
})
const toggleState = (s) => act(async () => {
  const { data } = await axios.post(`/api/admin/event-import/states/${s.code}/toggle`)
  s.enabled = data.enabled
})
const setAll = (enabled) => act(async () => {
  await axios.post('/api/admin/event-import/states/all', { enabled })
  await load()
})
const runOne = (s) => act(async () => {
  runningCode.value = s.code
  try {
    const { data } = await axios.post('/api/admin/event-import/run', { state: s.code }, { timeout: 200000 })
    say(data.message, data.success)
  } finally { runningCode.value = '' }
  await Promise.all([load(), loadEvents(1)])
})
const runAll = () => act(async () => {
  say('가져오기를 시작하는 중…', true)
  info.value.running = true   // 눌렀다는 것이 바로 보이게
  let r
  try {
    r = (await axios.post('/api/admin/event-import/run', {}, { timeout: 20000 })).data
  } catch (e) {
    // 백그라운드 실행은 응답이 먼저 오거나 시간이 오래 걸려 끊길 수 있어, 응답이 없어도 진행 상태를 다시 읽는다
    if (e.response) { info.value.running = false; throw e }
    r = { success: true, message: '가져오기를 시작했어요. 아래에 진행 상황이 표시돼요.' }
  }
  say(r.message, r.success)
  await load()
})
const toggleEvent = (e) => act(async () => {
  const { data } = await axios.post(`/api/admin/event-import/events/${e.id}/toggle`)
  e.is_active = data.is_active
})
const delEvent = (e) => act(async () => {
  if (!twice('del' + e.id, `"${e.title}" 을(를) 삭제할까요?`)) return
  await axios.delete(`/api/admin/event-import/events/${e.id}`)
  events.value = events.value.filter(x => x.id !== e.id)
  await load()
})

// 가져오는 중이면 4초마다 진행 상태를 다시 읽고, 끝나면 행사 목록도 새로 고침
let poll = null
watch(() => info.value.running, (on) => {
  clearInterval(poll)
  if (on) poll = setInterval(async () => { await load(); if (!info.value.running) { say('가져오기가 끝났어요.', true); loadEvents(1) } }, 4000)
})
onMounted(() => { load(); loadEvents(1) })
onBeforeUnmount(() => { clearInterval(poll); clearTimeout(askTimer) })
</script>
