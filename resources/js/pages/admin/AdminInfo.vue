<template>
<div>
  <!-- 헤더 — 다른 게시판 관리 화면(AdminBoardManager)과 동일한 형식 -->
  <div class="mb-4 flex items-start justify-between flex-wrap gap-2">
    <div>
      <div class="text-xs text-ink-muted">관리자 › 게시판 관리 › 정보</div>
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mt-1">
        <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600 text-lg">📘</span>
        정보 관리
      </h1>
      <p class="text-xs text-ink-faint mt-0.5">자동 생성된 생활정보 글을 관리합니다</p>
    </div>
    <button @click="triggerGeneration" :disabled="generating"
      class="inline-flex items-center gap-1.5 bg-orange-500 text-white font-semibold px-4 py-2 rounded-xl text-sm hover:bg-orange-600 transition-colors disabled:opacity-50">
      🚀 {{ generating ? '생성 중...' : '지금 자동 생성' }}
    </button>
  </div>

  <div v-if="genStatus && genStatus.status === 'running'" class="mb-4 bg-orange-50 border border-orange-200 rounded-xl p-3">
    <div class="flex justify-between text-xs font-semibold text-orange-700 mb-1.5">
      <span>자동 생성 진행 중...</span>
      <span>{{ genStatus.completed || 0 }} / {{ genStatus.target || 10 }}</span>
    </div>
    <div class="h-2 bg-orange-100 rounded-full overflow-hidden">
      <div class="h-full bg-orange-500 transition-all" :style="{ width: progressPct + '%' }"></div>
    </div>
  </div>
  <div v-else-if="genStatus && genStatus.status === 'done' && genStatus.message" class="mb-4 bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-xs text-emerald-700">
    ✅ {{ genStatus.message }}
  </div>

  <!-- 통계 카드 — 다른 게시판과 동일한 5칸 구성(신고/광고는 정보 게시판엔 해당 없어 항상 0) -->
  <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
    <div class="card p-3">
      <div class="text-xs text-ink-muted">전체 게시글</div>
      <div class="text-xl font-bold text-ink">{{ overview.total ?? 0 }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">오늘 등록</div>
      <div class="text-xl font-bold text-blue-600">{{ overview.today ?? 0 }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">주간 등록</div>
      <div class="text-xl font-bold text-green-600">{{ overview.week ?? 0 }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">신고 대기</div>
      <div class="text-xl font-bold text-red-600">{{ overview.pending_reports ?? 0 }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">광고 활성</div>
      <div class="text-xl font-bold text-purple-600">{{ overview.active_banners ?? 0 }}</div>
    </div>
  </div>

  <!-- 탭 네비 — 정보 게시판은 포인트/배너/신고 개념이 없어 게시글/카테고리 2탭만 -->
  <div class="bg-white rounded-t-2xl border border-b-0 border-gray-100">
    <div class="flex overflow-x-auto">
      <button v-for="t in tabs" :key="t.key" @click="activeTab = t.key"
        class="flex items-center gap-1.5 px-4 py-3 text-sm whitespace-nowrap border-b-2 transition-colors"
        :class="activeTab === t.key ? 'border-amber-500 text-amber-700 font-bold bg-amber-50' : 'border-transparent text-ink-muted hover:text-ink'">
        <AppIcon :name="t.icon" :size="14" />{{ t.label }}
      </button>
    </div>
  </div>

  <div class="bg-white rounded-b-2xl border border-t-0 border-gray-100 p-4 min-h-[400px]">
    <!-- 📝 게시글 -->
    <div v-if="activeTab === 'posts'">
      <div class="flex flex-wrap gap-2 mb-4">
        <select v-model="category" @change="load(1)" class="border border-line rounded-lg px-3 py-1.5 text-sm">
          <option value="">전체 카테고리</option>
          <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
        </select>
        <input v-model="search" @keyup.enter="load(1)" placeholder="제목 검색" class="border border-line rounded-lg px-3 py-1.5 text-sm flex-1 min-w-[160px]" />
      </div>

      <div class="overflow-x-auto border border-line rounded-xl">
        <table class="w-full text-sm">
          <thead class="bg-surface text-ink-light text-xs">
            <tr>
              <th class="text-left px-3 py-2">제목</th>
              <th class="text-left px-3 py-2">카테고리</th>
              <th class="text-left px-3 py-2">발행일</th>
              <th class="text-left px-3 py-2">조회</th>
              <th class="text-left px-3 py-2">상태</th>
              <th class="text-right px-3 py-2">관리</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="post in posts" :key="post.id" class="border-t border-line">
              <td class="px-3 py-2 max-w-xs truncate">{{ post.title }}</td>
              <td class="px-3 py-2">{{ post.category }}</td>
              <td class="px-3 py-2 text-xs text-ink-light">{{ post.published_at ? post.published_at.slice(0,10) : '-' }}</td>
              <td class="px-3 py-2">{{ post.view_count }}</td>
              <td class="px-3 py-2">
                <span :class="post.is_published ? 'text-emerald-600' : 'text-ink-faint'">{{ post.is_published ? '발행됨' : '비공개' }}</span>
              </td>
              <td class="px-3 py-2 text-right whitespace-nowrap">
                <button @click="openEdit(post)" class="text-blue-600 hover:underline mr-2">수정</button>
                <button @click="toggle(post)" class="text-amber-600 hover:underline mr-2">{{ post.is_published ? '비공개' : '발행' }}</button>
                <button @click="remove(post)" class="text-red-600 hover:underline">삭제</button>
              </td>
            </tr>
            <tr v-if="!posts.length">
              <td colspan="6" class="px-3 py-8 text-center text-ink-faint text-sm">글이 없습니다</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex justify-center gap-2 mt-4" v-if="lastPage > 1">
        <button v-for="p in lastPage" :key="p" @click="load(p)"
          class="w-8 h-8 rounded-lg text-sm" :class="p === page ? 'bg-orange-500 text-white' : 'bg-surface text-ink-light'">{{ p }}</button>
      </div>
    </div>

    <!-- 📂 카테고리 — 고정된 11개 분류라 추가/삭제는 없고, 실제 집계만 참고용으로 보여줌 -->
    <div v-else-if="activeTab === 'cat'">
      <div class="text-sm text-ink-light mb-3">정보 글 카테고리는 11개로 고정돼 있습니다 (자동 생성 시 하나를 배정). 실제 글 수 기준 집계입니다.</div>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
        <div v-for="c in categoryStats" :key="c.name" class="flex items-center justify-between border border-line rounded-xl px-3 py-2.5">
          <span class="flex items-center gap-1.5 text-sm text-ink"><span>🏷</span>{{ c.name }}</span>
          <span class="badge-gray !text-xs">{{ c.post_count }}개</span>
        </div>
        <div v-if="!categoryStats.length" class="col-span-full py-8 text-center text-ink-faint text-sm">아직 발행된 글이 없습니다</div>
      </div>
    </div>
  </div>

  <!-- 수정 모달 -->
  <div v-if="editing" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="editing=null">
    <div class="bg-white rounded-2xl p-5 w-full max-w-xl max-h-[85vh] overflow-y-auto">
      <h2 class="font-bold mb-3">글 수정</h2>
      <div class="space-y-3 text-sm">
        <div>
          <label class="block text-xs text-ink-light mb-1">제목</label>
          <input v-model="editing.title" class="w-full border border-line rounded-lg px-3 py-2" />
        </div>
        <div>
          <label class="block text-xs text-ink-light mb-1">카테고리</label>
          <select v-model="editing.category" class="w-full border border-line rounded-lg px-3 py-2">
            <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-ink-light mb-1">요약(excerpt)</label>
          <textarea v-model="editing.excerpt" rows="2" class="w-full border border-line rounded-lg px-3 py-2"></textarea>
        </div>
        <div>
          <label class="block text-xs text-ink-light mb-1">커버 이미지 URL</label>
          <input v-model="editing.cover_image_url" class="w-full border border-line rounded-lg px-3 py-2" />
        </div>
        <div>
          <label class="block text-xs text-ink-light mb-1">본문 HTML</label>
          <textarea v-model="editing.body" rows="10" class="w-full border border-line rounded-lg px-3 py-2 font-mono text-xs"></textarea>
        </div>
        <div>
          <label class="block text-xs text-ink-light mb-1">meta_title</label>
          <input v-model="editing.meta_title" class="w-full border border-line rounded-lg px-3 py-2" />
        </div>
        <div>
          <label class="block text-xs text-ink-light mb-1">meta_description</label>
          <input v-model="editing.meta_description" class="w-full border border-line rounded-lg px-3 py-2" />
        </div>
      </div>
      <div class="flex justify-end gap-2 mt-4">
        <button @click="editing=null" class="px-4 py-2 rounded-lg bg-surface text-sm">취소</button>
        <button @click="save" class="px-4 py-2 rounded-lg bg-orange-500 text-white text-sm font-semibold">저장</button>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const activeTab = ref('posts')
const tabs = [
  { key: 'posts', icon: 'edit', label: '게시글' },
  { key: 'cat',   icon: 'tag',  label: '카테고리' },
]

const overview = ref({})
const categoryStats = ref([])

const posts = ref([])
const page = ref(1)
const lastPage = ref(1)
const category = ref('')
const search = ref('')
const editing = ref(null)
const generating = ref(false)
const genStatus = ref(null)
let pollTimer = null

const categories = ['생활정보', '이민·비자', '세금', '금융', '보험', '부동산', '교통', '교육', '날씨·안전', '통신', '창업·비즈니스']

const progressPct = computed(() => {
  if (!genStatus.value?.target) return 0
  return Math.min(100, Math.round((genStatus.value.completed / genStatus.value.target) * 100))
})

async function loadOverview() {
  try {
    const { data } = await axios.get('/api/admin/board-manager/info/overview')
    overview.value = data.data
  } catch (e) { console.warn('overview load failed', e) }
}

async function loadCategoryStats() {
  try {
    const { data } = await axios.get('/api/admin/board-manager/info/categories')
    categoryStats.value = data.data || []
  } catch (e) { console.warn('categories load failed', e) }
}

async function load(p = 1) {
  page.value = p
  const { data } = await axios.get('/api/admin/info-posts', { params: { page: p, category: category.value || undefined, search: search.value || undefined } })
  posts.value = data.data.data
  lastPage.value = data.data.last_page
}

function openEdit(post) {
  editing.value = { ...post }
}

async function save() {
  try {
    await axios.put(`/api/admin/info-posts/${editing.value.id}`, {
      title: editing.value.title,
      category: editing.value.category,
      excerpt: editing.value.excerpt,
      cover_image_url: editing.value.cover_image_url,
      body: editing.value.body,
      meta_title: editing.value.meta_title,
      meta_description: editing.value.meta_description,
    })
    editing.value = null
    load(page.value)
  } catch (e) { alert(e.response?.data?.message || '저장 실패') }
}

async function toggle(post) {
  await axios.patch(`/api/admin/info-posts/${post.id}/toggle`)
  load(page.value)
  loadOverview()
}

async function remove(post) {
  if (!confirm(`"${post.title}" 글을 삭제할까요?`)) return
  await axios.delete(`/api/admin/info-posts/${post.id}`)
  load(page.value)
  loadOverview()
  loadCategoryStats()
}

async function fetchGenStatus() {
  const { data } = await axios.get('/api/admin/info-generation/status')
  genStatus.value = data
  if (data.status === 'running') {
    generating.value = true
  } else {
    generating.value = false
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null }
    loadOverview()
    loadCategoryStats()
  }
}

async function triggerGeneration() {
  if (!confirm('글 10개를 자동 생성합니다. 보통 수 분~1시간 내 처리됩니다. 시작할까요?')) return
  generating.value = true
  await axios.post('/api/admin/info-generation/trigger')
  startPolling()
}

function startPolling() {
  if (pollTimer) return
  pollTimer = setInterval(fetchGenStatus, 10000)
  fetchGenStatus()
}

onMounted(() => {
  load(1)
  loadOverview()
  loadCategoryStats()
  fetchGenStatus().then(() => { if (genStatus.value?.status === 'running') startPolling() })
})
onUnmounted(() => { if (pollTimer) clearInterval(pollTimer) })
</script>
