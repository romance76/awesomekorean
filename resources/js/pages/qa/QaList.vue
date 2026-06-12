<template>
<div class="min-h-screen">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <!-- 헤더: 모바일 -->
    <div class="lg:hidden mb-3">
      <div class="flex items-center justify-between mb-2">
        <h1 class="flex items-center gap-2 text-lg font-bold text-ink">
          <span class="icon-chip w-8 h-8 bg-amber-50 text-amber-600"><AppIcon name="help-circle" :size="17" /></span>
          Q&A
        </h1>
        <div class="flex items-center gap-2">
          <button @click="showFilter = true" class="btn-secondary !text-xs !px-3 !py-2"><AppIcon name="search" :size="14" />필터</button>
          <RouterLink v-if="auth.isLoggedIn" to="/qa/write" class="btn-primary !text-xs !px-3 !py-2"><AppIcon name="edit" :size="13" />질문하기</RouterLink>
        </div>
      </div>
      <div class="flex items-center gap-1.5 overflow-x-auto">
        <span v-if="statusFilter" :class="statusFilter === 'true' ? 'badge-green' : 'badge-red'">
          {{ statusFilter === 'true' ? '해결됨' : '미해결' }}
        </span>
      </div>
    </div>

    <!-- 모바일 필터 바텀시트 -->
    <MobileFilter v-model="showFilter" @apply="loadQa()" @reset="activeCat = null; statusFilter = ''; loadQa()">
      <div class="mb-4">
        <label class="input-label">카테고리</label>
        <div class="grid grid-cols-3 gap-1.5">
          <button @click="activeCat = null"
            class="text-xs py-2 rounded-lg font-semibold border transition-colors"
            :class="!activeCat ? 'bg-amber-50 text-amber-700 border-amber-300' : 'border-gray-200 text-ink-light hover:bg-gray-50'">
            전체
          </button>
          <button v-for="c in categories" :key="c.id" @click="activeCat = c"
            class="text-xs py-2 rounded-lg font-semibold border transition-colors"
            :class="activeCat?.id === c.id ? 'bg-amber-50 text-amber-700 border-amber-300' : 'border-gray-200 text-ink-light hover:bg-gray-50'">
            {{ c.name }}
          </button>
        </div>
      </div>
      <div>
        <label class="input-label">상태</label>
        <div class="flex gap-2">
          <button @click="statusFilter = ''" class="flex-1 py-2.5 rounded-xl font-bold text-sm border-2 transition-colors"
            :class="statusFilter === '' ? 'bg-amber-400 text-white border-amber-400' : 'border-gray-200 text-ink-muted'">전체</button>
          <button @click="statusFilter = 'true'" class="flex-1 py-2.5 rounded-xl font-bold text-sm border-2 transition-colors"
            :class="statusFilter === 'true' ? 'bg-emerald-500 text-white border-emerald-500' : 'border-gray-200 text-ink-muted'">해결</button>
          <button @click="statusFilter = 'false'" class="flex-1 py-2.5 rounded-xl font-bold text-sm border-2 transition-colors"
            :class="statusFilter === 'false' ? 'bg-red-500 text-white border-red-500' : 'border-gray-200 text-ink-muted'">미해결</button>
        </div>
      </div>
    </MobileFilter>

    <!-- 헤더: 데스크탑 -->
    <div class="hidden lg:flex items-center justify-between mb-4">
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
        <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="help-circle" :size="20" /></span>
        Q&A
      </h1>
      <RouterLink v-if="auth.isLoggedIn" to="/qa/write" class="btn-primary"><AppIcon name="edit" :size="15" />질문하기</RouterLink>
    </div>

    <div class="grid grid-cols-12 gap-4">
      <!-- 왼쪽: 카테고리 + 상태 -->
      <div class="col-span-12 lg:col-span-2 hidden lg:block">
        <div class="sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto space-y-3 pr-0.5">
          <div class="card overflow-hidden">
            <div class="px-3 py-2.5 border-b border-gray-50 font-bold text-xs text-ink flex items-center gap-1.5"><AppIcon name="list" :size="13" class="text-amber-500" />카테고리</div>
            <button @click="showFavorites=false; activeCat=null; activeItem=null; loadQa()" class="w-full text-left px-3 py-2 text-xs transition-colors"
              :class="!showFavorites && !activeCat ? 'bg-amber-50 text-amber-700 font-bold' : 'text-ink-light hover:bg-amber-50/50'">전체</button>
            <button v-for="cat in categories" :key="cat.id" @click="showFavorites=false; activeCat=cat; activeItem=null; loadQa()"
              class="w-full text-left px-3 py-2 text-xs transition-colors"
              :class="!showFavorites && activeCat?.id===cat.id ? 'bg-amber-50 text-amber-700 font-bold' : 'text-ink-light hover:bg-amber-50/50'">{{ cat.name }}</button>
            <button v-if="auth.isLoggedIn" @click="showFavorites=true; activeItem=null; loadFavoritesPage()"
              class="w-full text-left px-3 py-2 text-xs transition-colors border-t border-gray-50 flex items-center gap-1"
              :class="showFavorites ? 'bg-red-50 text-red-600 font-bold' : 'text-ink-light hover:bg-red-50/50'">
              <AppIcon name="bookmark" :size="12" />내 북마크<span v-if="favCount > 0" class="ml-0.5">({{ favCount }})</span>
            </button>
          </div>
          <div class="card overflow-hidden">
            <div class="px-3 py-2.5 border-b border-gray-50 font-bold text-xs text-ink flex items-center gap-1.5"><AppIcon name="filter" :size="13" class="text-amber-500" />상태</div>
            <button v-for="s in statusFilters" :key="s.value" @click="statusFilter=s.value; activeItem=null; loadQa()"
              class="w-full text-left px-3 py-2 text-xs transition-colors"
              :class="statusFilter===s.value ? 'bg-amber-50 text-amber-700 font-bold' : 'text-ink-light hover:bg-amber-50/50'">{{ s.label }}</button>
          </div>
          <AdSlot page="qa" position="left" :maxSlots="2" />
        </div>
      </div>

      <!-- 메인: 목록 또는 상세 (인라인 전환) -->
      <div class="col-span-12 lg:col-span-7">

        <div class="mb-2">
          <span v-if="showFavorites" class="font-bold text-red-600 text-sm inline-flex items-center gap-1"><AppIcon name="bookmark" :size="14" />내 북마크</span>
          <template v-else>
            <span class="font-bold text-amber-700 text-sm">{{ activeCat ? activeCat.name : '전체' }}</span>
            <span v-if="!activeCat" class="text-xs text-ink-muted ml-2">모든 질문을 볼 수 있습니다</span>
          </template>
        </div>

        <!-- ═══ 상세 모드 ═══ -->
        <div v-if="activeItem">

          <!-- 질문 카드 -->
          <div class="card overflow-hidden mb-3">
            <div class="px-5 py-4">
              <div class="flex items-center gap-2 mb-2">
                <span class="badge-primary">{{ activeItem.category?.name }}</span>
                <span v-if="activeItem.bounty_points > 0" class="badge bg-amber-400 text-white font-bold"><AppIcon name="trophy" :size="11" />{{ activeItem.bounty_points }}P</span>
                <span v-if="activeItem.is_resolved" class="badge-green font-bold"><AppIcon name="check" :size="11" />해결</span>
                <span v-else class="badge-red">미해결</span>
              </div>
              <div class="flex items-center gap-2">
                <h2 class="text-lg font-bold text-ink flex-1">{{ activeItem.title }}</h2>
                <BookmarkToggle v-if="auth.isLoggedIn" :active="favorited.has(activeItem.id)" @toggle="toggleFav(activeItem)" size="lg" class="flex-shrink-0" />
              </div>
              <div class="text-xs text-ink-muted mt-1"><UserName :userId="activeItem.user?.id" :name="activeItem.user?.name" className="text-xs text-ink-muted inline" /> · {{ activeItem.view_count }}회 · 답변 {{ activeItem.answer_count }}개</div>
            </div>
            <div class="px-5 py-4 border-t border-gray-50 text-sm text-ink-light whitespace-pre-wrap">{{ activeItem.content }}</div>
          </div>

          <!-- 답변 목록 -->
          <div class="space-y-2 mb-3">
            <h3 class="font-bold text-sm text-ink flex items-center gap-1.5"><AppIcon name="message-circle" :size="15" class="text-amber-500" />답변 {{ answers.length }}개</h3>
            <div v-for="ans in answers" :key="ans.id"
              class="card overflow-hidden border"
              :class="ans.is_best ? 'border-amber-400' : 'border-transparent'">
              <div v-if="ans.is_best" class="bg-amber-50 px-4 py-1.5 text-xs font-bold text-amber-700 flex items-center gap-1"><AppIcon name="trophy" :size="13" />채택된 답변</div>
              <div class="px-5 py-4">
                <div class="text-sm text-ink-light whitespace-pre-wrap">{{ ans.content }}</div>
                <div class="flex items-center gap-3 mt-3 text-xs text-ink-muted">
                  <UserName :userId="ans.user?.id" :name="ans.user?.name" className="font-semibold text-ink-light" />
                  <button @click="voteAnswer(ans, 'like')" class="flex items-center gap-1 hover:text-blue-500 transition-colors"
                    :class="ans._vote === 'like' ? 'text-blue-600' : 'text-ink-muted'">
                    <AppIcon name="thumbs-up" :size="13" />{{ ans.like_count || 0 }}
                  </button>
                  <button @click="voteAnswer(ans, 'dislike')" class="flex items-center gap-1 hover:text-red-500 transition-colors"
                    :class="ans._vote === 'dislike' ? 'text-red-500' : 'text-ink-muted'">
                    <AppIcon name="thumbs-up" :size="13" class="rotate-180" />{{ ans.dislike_count || 0 }}
                  </button>
                  <span>{{ formatDate(ans.created_at) }}</span>
                  <button v-if="auth.user?.id === ans.user_id" @click="deleteAnswer(ans)"
                    class="ml-auto flex items-center gap-1 text-ink-faint hover:text-red-500 transition-colors"><AppIcon name="trash" :size="13" />삭제</button>
                </div>
              </div>
            </div>
          </div>

          <!-- 답변 작성 -->
          <div v-if="auth.isLoggedIn && !activeItem.is_resolved" class="card p-4">
            <h3 class="font-bold text-sm text-ink mb-2 flex items-center gap-1.5"><AppIcon name="edit" :size="14" class="text-amber-500" />답변 작성</h3>
            <textarea v-model="newAnswer" rows="4" placeholder="답변을 입력하세요..." class="input-soft"></textarea>
            <button @click="submitAnswer" :disabled="!newAnswer.trim()" class="mt-2 btn-primary">답변 등록</button>
          </div>

          <!-- 이전글 / 목록 / 다음글 (같은 카테고리 한정, 서버 prev/next 사용) -->
          <div class="mt-4 flex items-stretch card text-sm overflow-hidden">
            <button v-if="adjPrev" @click="navItem(-1)"
              class="flex-1 min-w-0 px-4 py-3 hover:bg-amber-50 text-left text-ink-light border-r border-gray-50 transition-colors">
              <div class="text-ink-muted text-xs flex items-center gap-1"><AppIcon name="chevron-left" :size="12" />이전글</div>
              <div class="text-xs text-ink-light truncate mt-0.5">{{ adjPrev.title || '' }}</div>
            </button>
            <div v-else class="flex-1 min-w-0 px-4 py-3 text-left text-ink-faint border-r border-gray-50 text-xs flex items-center gap-1"><AppIcon name="chevron-left" :size="12" />이전글 없음</div>

            <button @click="activeItem=null; answers=[]; adjPrev=null; adjNext=null"
              class="px-5 py-3 hover:bg-amber-50 text-center text-ink font-bold border-r border-gray-50 flex-shrink-0 transition-colors">
              목록
            </button>

            <button v-if="adjNext" @click="navItem(1)"
              class="flex-1 min-w-0 px-4 py-3 hover:bg-amber-50 text-right text-ink-light transition-colors">
              <div class="text-ink-muted text-xs flex items-center justify-end gap-1">다음글<AppIcon name="chevron-right" :size="12" /></div>
              <div class="text-xs text-ink-light truncate mt-0.5">{{ adjNext.title || '' }}</div>
            </button>
            <div v-else class="flex-1 min-w-0 px-4 py-3 text-right text-ink-faint text-xs flex items-center justify-end gap-1">다음글 없음<AppIcon name="chevron-right" :size="12" /></div>
          </div>
        </div>

        <!-- ═══ 목록 모드 ═══ -->
        <div v-else>

          <div v-if="loading" class="text-center py-12 text-ink-muted">로딩중...</div>
          <div v-else-if="!items.length" class="py-16 text-center">
            <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="help-circle" :size="28" :stroke-width="1.5" /></div>
            <p class="text-sm text-ink-muted">질문이 없습니다</p>
          </div>
          <div v-else class="space-y-2">
            <template v-for="(item, i) in items" :key="item.id">
            <div @click="openItem(item)"
              class="card card-hover px-4 py-3 cursor-pointer">
              <div class="flex items-center gap-2 mb-1">
                <span v-if="!activeCat" class="badge-primary !text-[11px] !px-2">{{ item.category?.name || 'Q&A' }}</span>
                <span v-if="item.bounty_points > 0" class="badge bg-amber-400 text-white font-bold !text-[11px] !px-2"><AppIcon name="trophy" :size="11" />{{ item.bounty_points }}P</span>
                <span v-if="item.is_resolved" class="badge-green font-bold !text-[11px] !px-2"><AppIcon name="check" :size="11" />해결</span>
                <span v-else class="badge-red !text-[11px] !px-2">미해결</span>
              </div>
              <div class="text-sm font-semibold text-ink">{{ item.title }}</div>
              <div class="flex items-center gap-3 mt-1.5 text-xs text-ink-muted">
                <UserName :userId="item.user?.id" :name="item.user?.name" className="text-xs text-ink-muted" />
                <span class="flex items-center gap-1"><AppIcon name="eye" :size="12" />{{ item.view_count }}</span>
                <span class="flex items-center gap-1"><AppIcon name="message-circle" :size="12" />{{ item.answer_count }}</span>
                <span>{{ formatDate(item.created_at) }}</span>
                <BookmarkToggle v-if="auth.isLoggedIn" :active="favorited.has(item.id)" @toggle="toggleFav(item)" size="sm" class="ml-auto" />
              </div>
            </div>
            <MobileAdInline v-if="i === 4" page="qa" />
            </template>
          </div>

          <Pagination :page="page" :lastPage="lastPage" @page="loadQa" />
        </div>
      </div>

      <!-- 오른쪽: 위젯 -->
      <div class="col-span-12 lg:col-span-3 hidden lg:block">
        <SidebarWidgets :currentCategory="activeItem ? (activeItem.category_id || '') : (activeCat?.id || '')" categoryParam="category_id" :inline="true" @select="openItem" api-url="/api/qa" detail-path="/qa/" :current-id="activeItem?.id || 0"
          :mode="activeItem ? 'detail' : 'list'" :categoryLabel="activeItem?.category?.name || activeCat?.name || ''" label="질문" />
        <AdSlot page="qa" position="right" :maxSlots="2" />
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { useRoute, useRouter } from 'vue-router'
import { ref, computed, watch, onMounted } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { useBookmarkStore } from '../../stores/bookmarks'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import axios from 'axios'
import AdSlot from '../../components/AdSlot.vue'
import BookmarkToggle from '../../components/BookmarkToggle.vue'
import AppIcon from '../../components/AppIcon.vue'

const auth = useAuthStore()
const bStore = useBookmarkStore()
const BM_TYPE = 'App\\Models\\QaPost'
const route = useRoute()
const router = useRouter()
const showFilter = ref(false)
const showFavorites = ref(false)
const favorited = ref(new Set())
const favCount = computed(() => bStore.getBookmarkedIds(BM_TYPE).length)
const items = ref([])
const categories = ref([])
const activeCat = ref(null)
const statusFilter = ref('')
const activeItem = ref(null)
const answers = ref([])
const newAnswer = ref('')
const currentIdx = ref(-1)
const adjPrev = ref(null) // 같은 카테고리 내 이전글 (API 제공)
const adjNext = ref(null) // 같은 카테고리 내 다음글 (API 제공)

function navItem(dir) {
  const target = dir < 0 ? adjPrev.value : adjNext.value
  if (target && target.id) {
    // API id 기반: items 에 있으면 그대로 openItem, 없으면 fetch
    const inList = items.value.find(i => i.id === target.id)
    if (inList) openItem(inList)
    else openItem({ id: target.id, title: target.title })
  }
}
const loading = ref(true)
const page = ref(1)
const lastPage = ref(1)

const statusFilters = [
  { value: '', label: '전체' },
  { value: 'false', label: '미해결' },
  { value: 'true', label: '해결됨' },
]

function formatDate(dt) {
  if (!dt) return ''
  const h = Math.floor((Date.now() - new Date(dt).getTime()) / 3600000)
  if (h < 1) return '방금'
  if (h < 24) return h + '시간 전'
  return Math.floor(h / 24) + '일 전'
}

async function openItem(item) {
  currentIdx.value = items.value.findIndex(i => i.id === item.id)
  try {
    const { data } = await axios.get(`/api/qa/${item.id}`)
    activeItem.value = data.data
    answers.value = data.data?.answers || []
    adjPrev.value = data.prev || null
    adjNext.value = data.next || null
  } catch {
    activeItem.value = item
    answers.value = []
    adjPrev.value = null
    adjNext.value = null
  }
  if (activeItem.value?.category_id) activeCat.value = categories.value.find(c => c.id === activeItem.value.category_id) || null
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function submitAnswer() {
  if (!newAnswer.value.trim() || !activeItem.value) return
  try {
    const { data } = await axios.post(`/api/qa/${activeItem.value.id}/answer`, { content: newAnswer.value })
    answers.value.push(data.data)
    newAnswer.value = ''
    activeItem.value.answer_count++
  } catch {}
}

async function voteAnswer(ans, type) {
  if (!auth.isLoggedIn) { alert('로그인이 필요합니다.'); return }
  try {
    const { data } = await axios.post(`/api/qa/${activeItem.value.id}/answer/${ans.id}/like`, { type })
    ans.like_count = data.like_count
    ans.dislike_count = data.dislike_count
    ans._vote = data.my_vote // 'like', 'dislike', or null
  } catch {}
}

async function deleteAnswer(ans) {
  if (!confirm('답변을 삭제하시겠습니까?')) return
  try {
    await axios.delete(`/api/qa/${activeItem.value.id}/answer/${ans.id}`)
    answers.value = answers.value.filter(a => a.id !== ans.id)
    activeItem.value.answer_count--
  } catch { alert('삭제에 실패했습니다.') }
}

async function loadQa(p = 1) {
  loading.value = true; page.value = p
  const params = { page: p, per_page: 20 }
  if (activeCat.value) params.category_id = activeCat.value.id
  if (statusFilter.value) params.resolved = statusFilter.value
  try {
    const { data } = await axios.get('/api/qa', { params })
    items.value = data.data?.data || []
    lastPage.value = data.data?.last_page || 1
  } catch {}
  loading.value = false
  loadFavorited()
}

// 좋아요 (Bookmark)
async function loadFavorited() {
  if (!auth.isLoggedIn || !items.value.length) return
  try {
    const ids = items.value.map(i => i.id).join(',')
    const { data } = await axios.get('/api/bookmarks/check', { params: { type: 'App\\Models\\QaPost', ids } })
    favorited.value = new Set(data.data || [])
  } catch {}
}
async function toggleFav(item) {
  if (!auth.isLoggedIn) return
  const result = await bStore.toggle(BM_TYPE, item.id)
  if (result !== null) {
    if (result) favorited.value.add(item.id)
    else favorited.value.delete(item.id)
    favorited.value = new Set(favorited.value)
  }
}
async function loadFavoritesPage() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/bookmarks', { params: { type: 'App\\Models\\QaPost', per_page: 50 } })
    const bms = data.data?.data || []
    items.value = bms.map(b => b.bookmarkable).filter(Boolean)
    lastPage.value = 1
    loadFavorited()
  } catch {}
  loading.value = false
}

onMounted(async () => {
  bStore.loadAll()
  const [qRes, cRes] = await Promise.allSettled([
    axios.get('/api/qa?per_page=20'),
    axios.get('/api/qa/categories'),
  ])
  if (cRes.status === 'fulfilled') categories.value = cRes.value.data?.data || []

  // URL 쿼리 파라미터로 카테고리 반영
  const catQuery = route.query.category
  if (catQuery && categories.value.length) {
    const found = categories.value.find(c => String(c.id) === String(catQuery))
    if (found) activeCat.value = found
  }

  // 카테고리 선택됐으면 필터 적용해서 다시 로드, 아니면 초기 결과 사용
  if (activeCat.value) {
    await loadQa()
  } else if (qRes.status === 'fulfilled') {
    items.value = qRes.value.data?.data?.data || []; lastPage.value = qRes.value.data?.data?.last_page || 1
  }

  // URL에 id가 있으면 해당 항목 인라인 열기 (Issue #16)
  await ensureActiveFromRoute()
  loading.value = false
})

// URL /qa/:id 자동 오픈 + 404 처리 (Issue #16 / #17)
async function ensureActiveFromRoute() {
  const itemId = route.params.id
  if (!itemId) return
  try {
    const { data } = await axios.get('/api/qa/' + itemId)
    activeItem.value = data.data
    answers.value = data.data?.answers || []
    currentIdx.value = items.value.findIndex(i => Number(i.id) === Number(itemId))
    if (activeItem.value?.category_id) {
      activeCat.value = categories.value.find(c => c.id === activeItem.value.category_id) || activeCat.value
    }
  } catch (err) {
    if (err.response?.status === 404) router.replace('/404')
  }
}

// URL 쿼리 변경 시 카테고리 반영
watch(() => route.query.category, (catId) => {
  if (!catId) { activeCat.value = null; loadQa(); return }
  const found = categories.value.find(c => String(c.id) === String(catId))
  if (found) { activeCat.value = found; loadQa() }
})

watch(() => route.params.id, (newId, oldId) => {
  if (oldId && !newId) {
    loadQa()
    activeItem.value = null
    answers.value = []
    return
  }
  if (newId && String(newId) !== String(activeItem.value?.id)) {
    ensureActiveFromRoute()
  }
})
</script>
