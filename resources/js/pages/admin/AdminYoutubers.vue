<template>
<!-- 동호회·업소록 관리(AdminBoardManager)와 같은 구조: 머리글 · 통계 카드 · 탭 · 검색줄 · 표 -->
<div>
  <!-- 헤더 -->
  <div class="mb-4 flex items-start justify-between gap-3">
    <div>
      <div class="text-xs text-ink-muted">관리자 › 콘텐츠 › 한인 유튜버</div>
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mt-1">
        <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600 text-lg">📺</span>
        한인 유튜버 관리
      </h1>
      <p class="text-xs text-ink-faint mt-0.5">채널·분야·승인·주인 인증을 한 페이지에서 관리합니다</p>
    </div>
    <RouterLink to="/youtubers" target="_blank" class="shrink-0 text-xs font-semibold text-amber-700 hover:underline mt-1">사이트에서 보기 ↗</RouterLink>
  </div>

  <!-- 통계 카드 -->
  <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
    <div class="card p-3"><div class="text-xs text-ink-muted">전체 채널</div><div class="text-xl font-bold text-ink">{{ counts.total || 0 }}</div></div>
    <div class="card p-3"><div class="text-xs text-ink-muted">오늘 등록</div><div class="text-xl font-bold text-blue-600">{{ counts.today || 0 }}</div></div>
    <div class="card p-3"><div class="text-xs text-ink-muted">공개 중</div><div class="text-xl font-bold text-green-600">{{ counts.approved || 0 }}</div></div>
    <div class="card p-3"><div class="text-xs text-ink-muted">승인 대기</div><div class="text-xl font-bold text-red-600">{{ counts.pending || 0 }}</div></div>
    <div class="card p-3"><div class="text-xs text-ink-muted">주인 인증</div><div class="text-xl font-bold text-purple-600">{{ counts.verified || 0 }}</div></div>
  </div>

  <!-- 탭 네비 -->
  <div class="bg-white rounded-t-2xl border border-b-0 border-gray-100">
    <div class="flex overflow-x-auto">
      <button v-for="t in tabs" :key="t.key" @click="setTab(t.key)"
        class="flex items-center gap-1.5 px-4 py-3 text-sm whitespace-nowrap border-b-2 transition-colors"
        :class="tab === t.key ? 'border-amber-500 text-amber-700 font-bold bg-amber-50' : 'border-transparent text-ink-muted hover:text-ink'">
        <AppIcon v-if="t.icon" :name="t.icon" :size="14" />{{ t.label }}
        <span v-if="t.badge" class="ml-0.5 bg-red-500 text-white text-[11px] font-bold px-1.5 rounded-full">{{ t.badge }}</span>
      </button>
    </div>
  </div>

  <!-- 탭 컨텐츠 -->
  <div class="bg-white rounded-b-2xl border border-t-0 border-gray-100 p-4 min-h-[400px]">
    <div v-if="msg" class="mb-3 text-[13px] rounded-lg px-3 py-2" :class="ok ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600'">{{ msg }}</div>

    <!-- 채널 목록 / 승인 대기 -->
    <div v-if="tab === 'list' || tab === 'pending'">
      <!-- 검색 + 필터 -->
      <div class="card p-3 mb-4">
        <div class="flex flex-wrap gap-2 items-center">
          <select v-model="category" @change="load(1)" class="input-soft w-auto px-3 py-1.5">
            <option value="">📂 전체 분야</option>
            <option v-for="c in categories" :key="c.key" :value="c.key">{{ c.label }}</option>
          </select>
          <select v-if="tab === 'list'" v-model="status" @change="load(1)" class="input-soft w-auto px-3 py-1.5">
            <option value="">전체 상태</option>
            <option value="approved">공개 중</option>
            <option value="pending">승인 대기</option>
            <option value="rejected">거절됨</option>
          </select>
          <form @submit.prevent="load(1)" class="flex-1 flex gap-1.5 min-w-[150px]">
            <input v-model="search" type="text" placeholder="채널 이름/핸들 검색..." class="flex-1 input-soft px-3 py-1.5" />
            <button type="submit" class="btn-primary px-3 py-1.5 text-xs">검색</button>
          </form>
        </div>
        <div class="flex items-center justify-between mt-1">
          <div class="text-[11px] text-ink-faint">
            전체 {{ total }}건
            <span v-if="selected.length" class="text-red-600 ml-2">· 선택됨 {{ selected.length }}건</span>
          </div>
          <div v-if="selected.length" class="flex items-center gap-1.5">
            <button @click="bulk('approve')" class="inline-flex items-center gap-1 bg-green-500 hover:bg-green-600 text-white font-bold px-3 py-1.5 rounded-xl text-xs transition-colors">
              <AppIcon name="check" :size="13" />선택 {{ selected.length }}건 승인
            </button>
            <button @click="bulk('delete')" class="inline-flex items-center gap-1 bg-red-500 hover:bg-red-600 text-white font-bold px-3 py-1.5 rounded-xl text-xs transition-colors">
              <AppIcon name="trash" :size="13" />선택 {{ selected.length }}건 삭제
            </button>
          </div>
        </div>
      </div>

      <div v-if="loading" class="text-center py-8 text-ink-muted">로딩중...</div>
      <div v-else-if="!items.length" class="text-center py-12 text-ink-muted text-sm">채널이 없어요.</div>

      <!-- PC: 표 -->
      <div v-else class="hidden md:block">
        <div class="card overflow-hidden">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100"><tr>
              <th class="px-2 py-2 w-8 text-center"><input type="checkbox" :checked="allSelected" @change="toggleAll" /></th>
              <th class="px-3 py-2 text-left text-xs text-ink-muted w-8">#</th>
              <th class="px-3 py-2 text-left text-xs text-ink-muted">채널</th>
              <th class="px-3 py-2 text-left text-xs text-ink-muted">분야</th>
              <th class="px-3 py-2 text-xs text-ink-muted">구독자</th>
              <th class="px-3 py-2 text-xs text-ink-muted">영상</th>
              <th class="px-3 py-2 text-xs text-ink-muted">상태</th>
              <th class="px-3 py-2 text-xs text-ink-muted">인증</th>
              <th class="px-3 py-2 text-xs text-ink-muted"><span class="inline-flex items-center gap-0.5"><AppIcon name="eye" :size="13" /></span></th>
              <th class="px-3 py-2 text-xs text-ink-muted">날짜</th>
              <th class="px-3 py-2 text-xs text-ink-muted text-right">관리</th>
            </tr></thead>
            <tbody>
              <tr v-for="c in items" :key="c.id" class="border-b border-gray-50 last:border-0 hover:bg-amber-50/40 transition-colors" :class="selected.includes(c.id) ? 'bg-red-50' : ''">
                <td class="px-2 py-2 text-center"><input type="checkbox" :checked="selected.includes(c.id)" @change="toggleOne(c.id)" /></td>
                <td class="px-3 py-2 text-xs text-ink-faint">{{ c.id }}</td>
                <td class="px-3 py-2.5 max-w-[280px]">
                  <div class="flex items-center gap-2.5">
                    <img v-if="c.thumbnail_url" :src="c.thumbnail_url" alt="" class="w-8 h-8 rounded-full object-cover bg-surface shrink-0" @error="e => e.target.style.display = 'none'" />
                    <div class="min-w-0">
                      <div class="flex items-center gap-1">
                        <span v-if="c.is_featured" title="추천" class="text-[11px] font-bold text-white bg-purple-500 px-1 py-px rounded">추천</span>
                        <a :href="`https://www.youtube.com/channel/${c.channel_id}`" target="_blank" rel="noopener noreferrer" class="truncate text-sm font-medium text-ink hover:text-amber-700">{{ c.title }}</a>
                      </div>
                      <div class="text-[11px] text-ink-faint truncate mt-0.5">{{ c.handle || c.channel_id }}<template v-if="c.intro"> · {{ c.intro }}</template></div>
                    </div>
                  </div>
                </td>
                <td class="px-3 py-2.5">
                  <select :value="c.category" @change="e => update(c, { category: e.target.value })" class="text-[11px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full font-semibold border-0 cursor-pointer">
                    <option v-for="k in categories" :key="k.key" :value="k.key">{{ k.label }}</option>
                  </select>
                </td>
                <td class="px-3 py-2.5 text-center text-xs text-ink-light tabular-nums">{{ Number(c.subscriber_count).toLocaleString() }}</td>
                <td class="px-3 py-2.5 text-center text-xs text-ink-faint">{{ c.videos }}</td>
                <td class="px-3 py-2.5 text-center">
                  <span class="text-[11px] px-1.5 py-0.5 rounded-full font-semibold" :class="statusClass(c.status)">{{ statusLabel(c.status) }}</span>
                  <div v-if="c.reject_reason" class="text-[10.5px] text-red-500 mt-0.5 max-w-[110px] truncate" :title="c.reject_reason">{{ c.reject_reason }}</div>
                </td>
                <td class="px-3 py-2.5 text-center">
                  <span v-if="c.verified" class="text-[11px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full font-semibold" :title="`회원 #${c.owner_user_id}`">인증됨</span>
                  <span v-else class="text-[11px] text-ink-faint">-</span>
                </td>
                <td class="px-3 py-2.5 text-center text-xs text-ink-faint">{{ c.view_count || 0 }}</td>
                <td class="px-3 py-2.5 text-[11px] text-ink-faint whitespace-nowrap">{{ String(c.created_at || '').slice(0, 10) }}</td>
                <td class="px-3 py-2.5">
                  <div class="flex items-center justify-end gap-1 flex-wrap">
                    <button v-if="c.status !== 'approved'" @click="act(c, 'approve')" class="px-2 py-1 rounded-lg text-[11.5px] font-bold bg-green-500 text-white hover:bg-green-600">승인</button>
                    <button v-if="c.status !== 'rejected'" @click="reject(c)" class="px-2 py-1 rounded-lg text-[11.5px] font-bold bg-gray-100 text-ink-light hover:bg-gray-200">거절</button>
                    <button @click="update(c, { is_featured: !c.is_featured })" class="px-2 py-1 rounded-lg text-[11.5px] font-bold" :class="c.is_featured ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-ink-light hover:bg-gray-200'">{{ c.is_featured ? '추천 해제' : '추천' }}</button>
                    <button @click="act(c, 'resync')" class="px-2 py-1 rounded-lg text-[11.5px] font-bold bg-gray-100 text-ink-light hover:bg-gray-200" title="유튜브에서 정보·영상 새로 읽기">새로 읽기</button>
                    <button v-if="c.verified" @click="unverify(c)" class="px-2 py-1 rounded-lg text-[11.5px] font-bold bg-gray-100 text-ink-light hover:bg-gray-200" title="잘못 인증된 경우 주인 인증을 풀어요">인증 해제</button>
                    <button @click="remove(c)" class="px-2 py-1 rounded-lg text-[11.5px] font-bold text-red-500 hover:bg-red-50">삭제</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- 휴대폰·좁은 화면: 카드 -->
      <div v-if="!loading && items.length" class="md:hidden space-y-2">
        <div v-for="c in items" :key="c.id" class="bg-white border border-gray-100 rounded-2xl p-3.5" :class="selected.includes(c.id) ? 'border-red-300 bg-red-50' : ''">
          <div class="flex items-center gap-2.5">
            <input type="checkbox" :checked="selected.includes(c.id)" @change="toggleOne(c.id)" class="w-5 h-5" />
            <img v-if="c.thumbnail_url" :src="c.thumbnail_url" alt="" class="w-10 h-10 rounded-full object-cover bg-surface shrink-0" @error="e => e.target.style.display = 'none'" />
            <div class="min-w-0 flex-1">
              <div class="text-[15px] font-bold text-ink truncate">{{ c.title }}</div>
              <div class="text-[12.5px] text-ink-muted truncate">{{ c.handle || c.channel_id }} · 구독 {{ Number(c.subscriber_count).toLocaleString() }} · 영상 {{ c.videos }}</div>
            </div>
            <span class="shrink-0 text-[12px] px-2 py-1 rounded-full font-bold" :class="statusClass(c.status)">{{ statusLabel(c.status) }}</span>
          </div>
          <div class="flex flex-wrap items-center gap-1.5 mt-2.5">
            <span v-if="c.verified" class="text-[12px] bg-green-100 text-green-700 px-2 py-1 rounded-full font-bold">인증됨</span>
            <span v-if="c.is_featured" class="text-[12px] bg-purple-100 text-purple-700 px-2 py-1 rounded-full font-bold">추천</span>
            <button v-if="c.status !== 'approved'" @click="act(c, 'approve')" class="min-h-[40px] px-3 rounded-xl text-[13.5px] font-bold bg-green-500 text-white">승인</button>
            <button v-if="c.status !== 'rejected'" @click="reject(c)" class="min-h-[40px] px-3 rounded-xl text-[13.5px] font-bold bg-gray-100 text-ink-light">거절</button>
            <button @click="update(c, { is_featured: !c.is_featured })" class="min-h-[40px] px-3 rounded-xl text-[13.5px] font-bold bg-gray-100 text-ink-light">{{ c.is_featured ? '추천 해제' : '추천' }}</button>
            <button @click="act(c, 'resync')" class="min-h-[40px] px-3 rounded-xl text-[13.5px] font-bold bg-gray-100 text-ink-light">새로 읽기</button>
            <button v-if="c.verified" @click="unverify(c)" class="min-h-[40px] px-3 rounded-xl text-[13.5px] font-bold bg-gray-100 text-ink-light">인증 해제</button>
            <button @click="remove(c)" class="min-h-[40px] px-3 rounded-xl text-[13.5px] font-bold bg-red-50 text-red-600">삭제</button>
          </div>
        </div>
      </div>

      <div v-if="lastPage > 1" class="flex justify-center gap-1.5 mt-4">
        <button v-for="pg in Math.min(lastPage, 10)" :key="pg" @click="load(pg)"
          class="w-8 h-8 rounded-lg text-sm transition-colors" :class="pg === page ? 'bg-amber-400 text-white font-bold' : 'text-ink-muted hover:bg-gray-100'">{{ pg }}</button>
      </div>
    </div>

    <!-- 분야 -->
    <div v-else-if="tab === 'cats'">
      <div class="text-sm text-ink-muted mb-3">공개 중인 채널의 분야별 개수예요. 누르면 그 분야의 채널 목록으로 가요. 채널의 분야는 목록 표에서 바꿀 수 있어요.</div>
      <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
        <button v-for="c in categories" :key="c.key" @click="goCategory(c.key)" class="card card-hover p-3 text-left">
          <div class="text-sm font-bold text-ink">{{ c.label }}</div>
          <div class="text-xl font-bold text-amber-600 tabular-nums mt-1">{{ categoryCounts[c.key] || 0 }}<span class="text-xs font-normal text-ink-muted ml-1">채널</span></div>
        </button>
      </div>
    </div>

    <!-- 직접 등록 -->
    <div v-else-if="tab === 'add'">
      <div class="text-sm text-ink-muted mb-3">채널 주소를 붙여 넣으면 정보와 영상이 자동으로 채워지고 <b>바로 공개</b>돼요. 한 줄에 하나씩 여러 개를 한 번에 넣을 수도 있어요.</div>
      <div class="card p-4">
        <textarea v-model="addUrls" rows="5" class="input-soft w-full px-3 py-2 font-mono text-[13px]" placeholder="https://www.youtube.com/@채널이름&#10;https://www.youtube.com/@다른채널"></textarea>
        <div class="flex flex-col sm:flex-row gap-2 mt-3">
          <select v-model="addCat" class="input-soft sm:!w-48"><option v-for="c in categories" :key="c.key" :value="c.key">{{ c.label }}</option></select>
          <button @click="addChannels" :disabled="busy" class="btn-primary px-5 py-2 text-sm">{{ busy ? '등록 중...' : '등록' }}</button>
        </div>
        <ul v-if="addResults.length" class="mt-3 space-y-1 text-[12.5px]">
          <li v-for="(r, i) in addResults" :key="i" :class="r.ok ? 'text-green-700' : 'text-red-600'">{{ r.ok ? '✓' : '✗' }} {{ r.url }} — {{ r.message }}</li>
        </ul>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const items = ref([])
const counts = ref({})
const categories = ref([])
const categoryCounts = ref({})
const tab = ref('list')
const status = ref('')
const category = ref('')
const search = ref('')
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const loading = ref(false)
const busy = ref(false)
const msg = ref('')
const ok = ref(true)
const selected = ref([])
const addUrls = ref('')
const addCat = ref('life')
const addResults = ref([])

const tabs = computed(() => [
  { key: 'list', label: '채널', icon: 'video' },
  { key: 'pending', label: '승인 대기', icon: 'clock', badge: counts.value.pending || 0 },
  { key: 'cats', label: '분야', icon: 'list' },
  { key: 'add', label: '직접 등록', icon: 'plus' },
])
const allSelected = computed(() => items.value.length > 0 && items.value.every(c => selected.value.includes(c.id)))

function statusLabel(s) { return s === 'approved' ? '공개 중' : s === 'pending' ? '승인 대기' : '거절됨' }
function statusClass(s) { return s === 'approved' ? 'bg-green-100 text-green-700' : s === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-600' }
function say(text, good = true) { msg.value = text; ok.value = good }
function toggleOne(id) { selected.value = selected.value.includes(id) ? selected.value.filter(x => x !== id) : selected.value.concat(id) }
function toggleAll() { selected.value = allSelected.value ? [] : items.value.map(c => c.id) }

async function load(p = 1) {
  loading.value = true
  const st = tab.value === 'pending' ? 'pending' : status.value
  try {
    const { data } = await axios.get('/api/admin/youtubers', { params: { status: st || undefined, category: category.value || undefined, q: search.value || undefined, page: p } })
    const d = data.data || {}
    items.value = d.items || []
    counts.value = d.counts || {}
    categoryCounts.value = d.category_counts || {}
    if (d.categories?.length) categories.value = d.categories
    page.value = d.meta?.current_page || 1
    lastPage.value = d.meta?.last_page || 1
    total.value = d.meta?.total || 0
    selected.value = []
  } catch (e) { say(e.response?.data?.message || '목록을 불러오지 못했어요.', false) }
  finally { loading.value = false }
}
function setTab(k) { tab.value = k; msg.value = ''; if (k === 'list' || k === 'pending' || k === 'cats') load(1) }
function goCategory(k) { category.value = k; status.value = 'approved'; tab.value = 'list'; load(1) }

async function call(fn, okText) {
  busy.value = true
  try { const { data } = await fn(); say(data.message || okText); await load(page.value); return true }
  catch (e) { say(e.response?.data?.message || '처리하지 못했어요.', false); return false }
  finally { busy.value = false }
}
async function addChannels() {
  const urls = addUrls.value.split(/\r?\n/).map(s => s.trim()).filter(Boolean)
  if (!urls.length) return say('채널 주소를 입력해 주세요.', false)
  busy.value = true
  addResults.value = []
  for (const url of urls) {
    try { const { data } = await axios.post('/api/admin/youtubers', { url, category: addCat.value }); addResults.value.push({ url, ok: true, message: data.message || '등록했어요.' }) }
    catch (e) { addResults.value.push({ url, ok: false, message: e.response?.data?.message || '등록하지 못했어요.' }) }
  }
  busy.value = false
  const good = addResults.value.filter(r => r.ok).length
  say(`${good}개 등록, ${addResults.value.length - good}개 실패`, good > 0)
  if (good) { addUrls.value = addResults.value.filter(r => !r.ok).map(r => r.url).join('\n'); load(1) }
}
function act(c, what) { call(() => axios.post(`/api/admin/youtubers/${c.id}/${what}`), '처리했어요.') }
function reject(c) {
  const reason = window.prompt('거절 사유를 적어 주세요 (신청자에게 보여요, 비워도 돼요)', '')
  if (reason === null) return
  call(() => axios.post(`/api/admin/youtubers/${c.id}/reject`, { reason }), '거절했어요.')
}
function unverify(c) {
  if (!window.confirm(`'${c.title}' 채널의 주인 인증을 해제할까요?`)) return
  call(() => axios.post(`/api/admin/youtubers/${c.id}/unverify`), '해제했어요.')
}
function update(c, body) { call(() => axios.put(`/api/admin/youtubers/${c.id}`, body), '저장했어요.') }
function remove(c) {
  if (!window.confirm(`'${c.title}' 채널과 저장된 영상 목록을 삭제할까요?`)) return
  call(() => axios.delete(`/api/admin/youtubers/${c.id}`), '삭제했어요.')
}
async function bulk(kind) {
  const ids = selected.value.slice()
  if (!ids.length) return
  if (kind === 'delete' && !window.confirm(`선택한 ${ids.length}개 채널과 영상 목록을 삭제할까요?`)) return
  busy.value = true
  let done = 0
  for (const id of ids) {
    try {
      if (kind === 'approve') await axios.post(`/api/admin/youtubers/${id}/approve`)
      else await axios.delete(`/api/admin/youtubers/${id}`)
      done++
    } catch { /* 하나 실패해도 나머지는 계속 */ }
  }
  busy.value = false
  say(`${ids.length}개 중 ${done}개를 ${kind === 'approve' ? '승인' : '삭제'}했어요.`, done > 0)
  load(page.value)
}
onMounted(() => load(1))
</script>
