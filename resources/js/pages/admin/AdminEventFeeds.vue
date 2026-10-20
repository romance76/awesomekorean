<template>
<div class="space-y-5 pb-6">
  <div>
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-1">
      <span class="icon-chip w-9 h-9 bg-pink-50 text-pink-600"><AppIcon name="calendar" :size="20" /></span>
      공식 행사 피드 (축제·지역 행사)
    </h1>
    <p class="text-sm text-ink-muted leading-relaxed">관광청·시청·카운티가 공개한 행사 중 <b>한인이 갈 만한 큰 행사</b>(축제·공연·퍼레이드, 한국·아시아 관련 가산, 수업·모임·회의 제외)만 골라 <b>매주 일요일 새벽에 자동으로</b> 가져와요. 아래 화면에서 매번 돌릴 필요는 없어요.</p>
  </div>

  <p v-if="msg" class="text-sm rounded-xl p-3" :class="msgOk ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600'">{{ msg }}</p>
  <div v-if="loading" class="text-center py-12 text-ink-muted">불러오는 중...</div>

  <template v-else>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      <div class="card p-4"><div class="text-xs text-ink-muted">지금 올라 있는 공식 행사</div><div class="text-2xl font-black text-ink tabular-nums">{{ info.total }}</div></div>
      <div class="card p-4"><div class="text-xs text-ink-muted">켜 둔 출처</div><div class="text-2xl font-black text-ink tabular-nums">{{ enabledCount }} <span class="text-sm font-semibold text-ink-faint">/ {{ info.sources.length }}</span></div></div>
      <div class="card p-4"><div class="text-xs text-ink-muted">주별</div><div class="text-[13px] text-ink-light mt-1 leading-snug"><span v-for="(n, st) in info.by_state" :key="st" class="inline-block mr-2">{{ st }} <b class="tabular-nums">{{ n }}</b></span><span v-if="!Object.keys(info.by_state || {}).length" class="text-ink-faint">아직 없어요</span></div></div>
      <div class="card p-4"><div class="text-xs text-ink-muted">마지막 실행 / 다음 자동 실행</div><div class="text-sm font-bold text-ink mt-1">{{ info.last_run_at ? fmt(info.last_run_at) : '아직 없음' }}</div><div class="text-[11px] text-ink-faint">매주 일요일 06:30 (애틀랜타)</div></div>
    </div>

    <!-- 몫 -->
    <div class="card p-4 lg:p-5 space-y-2">
      <div class="font-bold text-ink">가져오는 양 (한 번에)</div>
      <p class="text-xs text-ink-muted leading-relaxed">점수가 높은 행사부터, 같은 행사(제목+날짜)는 한 번만, 이미 있는 Ticketmaster·회원 행사와도 겹치지 않게 골라요.</p>
      <div class="flex flex-wrap items-center gap-3 text-sm">
        <label class="flex items-center gap-1.5">주당 <input v-model.number="limits.per_state" type="number" min="1" max="40" class="input-soft !w-20 !py-1.5 text-right tabular-nums" aria-label="주당" /> 개</label>
        <label class="flex items-center gap-1.5">카운티당 <input v-model.number="limits.per_county" type="number" min="1" max="20" class="input-soft !w-20 !py-1.5 text-right tabular-nums" aria-label="카운티당" /> 개</label>
        <label class="flex items-center gap-1.5">도시당 <input v-model.number="limits.per_city" type="number" min="1" max="10" class="input-soft !w-20 !py-1.5 text-right tabular-nums" aria-label="도시당" /> 개</label>
        <button @click="saveLimits" :disabled="busy" class="btn-secondary !px-4 !py-1.5 text-sm">저장</button>
      </div>
    </div>

    <!-- 출처 -->
    <div class="card p-4 lg:p-5 space-y-3">
      <div class="flex items-center gap-2 flex-wrap">
        <div class="font-bold text-ink mr-auto">출처 ({{ info.sources.length }}곳)</div>
        <button @click="runAll" :disabled="busy || info.running" class="btn-secondary !px-4 !py-1.5 text-xs">{{ info.running ? '가져오는 중…' : '지금 한 번 모두 실행 (테스트용)' }}</button>
      </div>
      <div v-if="info.running" class="rounded-xl bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800" role="status">가져오는 중이에요(몇 분 걸려요). 이 화면은 자동으로 갱신돼요.</div>
      <div class="divide-y divide-gray-50">
        <div v-for="s in info.sources" :key="s.key" class="py-3 flex flex-wrap items-center gap-x-3 gap-y-1" :class="s.enabled ? '' : 'opacity-60'">
          <span class="text-xs font-black px-1.5 py-0.5 rounded bg-gray-100 text-ink-light flex-shrink-0">{{ s.state }}</span>
          <div class="min-w-0 flex-1 basis-56">
            <div class="text-sm font-bold text-ink truncate">{{ s.name }}</div>
            <div class="text-[11px] text-ink-faint">{{ typeLabel[s.type] || s.type }}<template v-if="s.county"> · {{ s.county }} 카운티</template><template v-if="s.city"> · {{ s.city }}</template></div>
            <div class="text-[11px] text-ink-muted">{{ s.last_run_at ? '마지막 ' + fmt(s.last_run_at) + ' · 후보 ' + s.last_candidates + ' → 선택 ' + s.last_selected : '아직 실행 전' }} · 지금 올라 있음 <b>{{ s.live_count }}</b></div>
            <div v-if="s.last_error" class="text-[11px] text-red-500 break-words">⚠ {{ s.last_error }}</div>
          </div>
          <button @click="runOne(s)" :disabled="busy || info.running" class="text-xs font-bold text-primary disabled:opacity-40 flex-shrink-0">{{ runningKey === s.key ? '가져오는 중…' : '이 출처만 실행' }}</button>
          <button @click="toggleSource(s)" :disabled="busy" role="switch" :aria-checked="!!s.enabled" :aria-label="s.name + ' 켜기/끄기'" class="relative w-11 h-6 rounded-full transition flex-shrink-0" :class="s.enabled ? 'bg-emerald-500' : 'bg-gray-300'">
            <span class="absolute top-0.5 w-5 h-5 rounded-full bg-white shadow transition-all" :style="{ left: s.enabled ? '22px' : '2px' }"></span>
          </button>
        </div>
      </div>
    </div>

    <!-- 가져온 행사 -->
    <div class="card p-4 lg:p-5 space-y-3">
      <div class="font-bold text-ink">가져온 공식 행사</div>
      <div class="flex flex-wrap gap-2">
        <select v-model="f.state" @change="loadEvents(1)" class="input-soft !py-1.5 text-sm !w-auto" aria-label="주">
          <option value="">모든 주</option>
          <option v-for="(n, st) in info.by_state" :key="st" :value="st">{{ st }} ({{ n }})</option>
        </select>
        <select v-model="f.is_active" @change="loadEvents(1)" class="input-soft !py-1.5 text-sm !w-auto" aria-label="상태">
          <option value="">보이는 것 + 숨긴 것</option><option value="1">보이는 것</option><option value="0">숨긴 것</option>
        </select>
        <input v-model="f.q" @keyup.enter="loadEvents(1)" placeholder="제목·장소·도시 검색" aria-label="검색" class="input-soft !py-1.5 text-sm flex-1 min-w-[160px]" />
        <button @click="loadEvents(1)" class="btn-secondary !px-4 !py-1.5 text-sm">검색</button>
      </div>
      <div v-if="evLoading" class="text-center py-6 text-ink-muted text-sm">불러오는 중...</div>
      <div v-else-if="!events.length" class="text-center py-8 text-ink-faint text-sm">가져온 행사가 없어요.</div>
      <div v-else class="divide-y divide-gray-50">
        <div v-for="e in events" :key="e.id" class="py-2.5 flex items-center gap-3" :class="e.is_active ? '' : 'opacity-50'">
          <img v-if="e.image_url" :src="e.image_url" alt="" loading="lazy" referrerpolicy="no-referrer" class="w-16 h-10 object-cover rounded-md bg-gray-100 flex-shrink-0 hidden sm:block" @error="x => x.target.style.display = 'none'" />
          <div class="min-w-0 flex-1">
            <div class="text-sm font-semibold text-ink truncate">{{ e.title }}</div>
            <div class="text-xs text-ink-muted truncate">{{ catLabel[e.category] || e.category }} · {{ dateOnly(e.start_date) }} · {{ [e.city, e.state].filter(Boolean).join(', ') }} · {{ e.organizer }}</div>
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
      <p class="text-[11px] text-ink-faint">숨긴 행사는 다음 자동 실행 때도 다시 켜지지 않아요. 삭제는 최고관리자만 할 수 있어요.</p>
    </div>
  </template>
</div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const catLabel = { festival: '🎪 지역 축제·행사', concert: '🎤 콘서트·공연', sports: '⚽ 스포츠', community: '👥 모임·커뮤니티', education: '📚 교육·체험' }
const typeLabel = { tribe: 'WordPress 행사 API', socrata_nycparks: '뉴욕시 공개 데이터', ics: 'iCal', sitemap: '사이트맵 + 행사 페이지', microdata: '행사 페이지(마이크로데이터)' }

const loading = ref(true)
const busy = ref(false)
const runningKey = ref('')
const msg = ref('')
const msgOk = ref(true)
const info = ref({ sources: [], total: 0, by_state: {}, running: false })
const limits = ref({ per_state: 10, per_county: 3, per_city: 2 })
let limitsTouched = false

const events = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const evLoading = ref(false)
const f = ref({ state: '', is_active: '', q: '' })

const enabledCount = computed(() => info.value.sources.filter(s => s.enabled).length)

function say(text, ok = true) { msg.value = text; msgOk.value = ok }
function errText(e, fallback) { return e.response?.data?.message || fallback }
function fmt(v) { return v ? new Date(v).toLocaleString('ko-KR', { month: 'numeric', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : '' }
function dateOnly(v) { return v ? new Date(v).toLocaleDateString('ko-KR', { month: 'numeric', day: 'numeric' }) : '' }

async function load() {
  try {
    const { data } = await axios.get('/api/admin/event-feeds')
    info.value = data.data
    if (!limitsTouched) limits.value = { ...data.data.limits }
  } catch (e) { say(errText(e, '불러오지 못했어요'), false) }
  loading.value = false
}
async function loadEvents(page = 1) {
  evLoading.value = true
  try {
    const params = { page, per_page: 30, source: 'feed' }
    for (const k of ['state', 'is_active', 'q']) if (f.value[k] !== '') params[k] = f.value[k]
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

// 브라우저 확인창(confirm)은 막히면 버튼이 무반응이 되므로, 같은 버튼을 5초 안에 한 번 더 눌러야 실행되는 방식으로 확인
const asked = ref('')
let askTimer = null
function twice(key, hint) {
  if (asked.value === key) { asked.value = ''; clearTimeout(askTimer); return true }
  asked.value = key; say(hint + ' — 맞으면 같은 버튼을 한 번 더 누르세요', true)
  clearTimeout(askTimer); askTimer = setTimeout(() => { asked.value = ''; msg.value = '' }, 5000)
  return false
}

const saveLimits = () => act(async () => {
  limitsTouched = true
  const { data } = await axios.put('/api/admin/event-feeds/limits', limits.value)
  limits.value = { ...data.limits }
  say(data.message)
})
const toggleSource = (s) => act(async () => {
  const { data } = await axios.post(`/api/admin/event-feeds/sources/${s.key}/toggle`)
  s.enabled = data.enabled
})
const runOne = (s) => act(async () => {
  runningKey.value = s.key
  try {
    const { data } = await axios.post('/api/admin/event-feeds/run', { key: s.key }, { timeout: 600000 })
    say(data.message, data.success)
  } finally { runningKey.value = '' }
  await Promise.all([load(), loadEvents(1)])
})
const runAll = () => act(async () => {
  say('가져오기를 시작하는 중…', true)
  info.value.running = true
  let r
  try {
    r = (await axios.post('/api/admin/event-feeds/run', {}, { timeout: 20000 })).data
  } catch (e) {
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

let poll = null
watch(() => info.value.running, (on) => {
  clearInterval(poll)
  if (on) poll = setInterval(async () => { await load(); if (!info.value.running) { say('가져오기가 끝났어요.', true); loadEvents(1) } }, 5000)
})
onMounted(() => { load(); loadEvents(1) })
onBeforeUnmount(() => { clearInterval(poll); clearTimeout(askTimer) })
</script>
