<template>
<div class="min-h-screen">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <!-- 헤더: 모바일 -->
    <div class="lg:hidden mb-3">
      <div class="flex items-center justify-between mb-2">
        <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
          <span class="icon-chip w-9 h-9 bg-blue-50 text-blue-600"><AppIcon name="message-circle" :size="20" /></span>
          커뮤니티
        </h1>
        <div class="flex items-center gap-2">
          <button @click="showFilter = true" class="btn-secondary text-xs px-3 py-2"><AppIcon name="search" :size="14" />필터</button>
          <RouterLink v-if="auth.isLoggedIn" to="/community/write" class="btn-primary text-xs px-3 py-2"><AppIcon name="edit" :size="14" />글쓰기</RouterLink>
        </div>
      </div>
      <div class="flex items-center gap-1.5 overflow-x-auto">
        <span class="badge-gray">
          {{ {latest:'최신순',popular:'인기순',views:'조회순',comments:'댓글순'}[sortBy] }}
        </span>
      </div>
    </div>

    <!-- 모바일 필터 바텀시트 -->
    <MobileFilter v-model="showFilter" @apply="loadPosts()" @reset="activeBoard = null; sortBy = 'latest'; loadPosts()">
      <div class="mb-4">
        <label class="input-label mb-2">게시판</label>
        <div class="grid grid-cols-3 gap-1.5">
          <button @click="activeBoard = null"
            class="text-xs py-2 rounded-lg font-semibold border transition-colors"
            :class="!activeBoard ? 'bg-amber-50 text-amber-700 border-amber-200' : 'border-gray-200 text-ink-light hover:bg-gray-50'">
            전체
          </button>
          <button v-for="b in boards" :key="b.id" @click="activeBoard = b"
            class="text-xs py-2 rounded-lg font-semibold border transition-colors"
            :class="activeBoard?.id === b.id ? 'bg-amber-50 text-amber-700 border-amber-200' : 'border-gray-200 text-ink-light hover:bg-gray-50'">
            {{ b.name }}
          </button>
        </div>
      </div>
      <div>
        <label class="input-label mb-2">정렬</label>
        <div class="grid grid-cols-2 gap-1.5">
          <button @click="sortBy = 'latest'" class="text-xs py-2 rounded-lg font-semibold border transition-colors flex items-center justify-center gap-1"
            :class="sortBy === 'latest' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'border-gray-200 text-ink-light hover:bg-gray-50'"><AppIcon name="clock" :size="13" />최신순</button>
          <button @click="sortBy = 'popular'" class="text-xs py-2 rounded-lg font-semibold border transition-colors flex items-center justify-center gap-1"
            :class="sortBy === 'popular' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'border-gray-200 text-ink-light hover:bg-gray-50'"><AppIcon name="flame" :size="13" />인기순</button>
          <button @click="sortBy = 'views'" class="text-xs py-2 rounded-lg font-semibold border transition-colors flex items-center justify-center gap-1"
            :class="sortBy === 'views' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'border-gray-200 text-ink-light hover:bg-gray-50'"><AppIcon name="eye" :size="13" />조회순</button>
          <button @click="sortBy = 'comments'" class="text-xs py-2 rounded-lg font-semibold border transition-colors flex items-center justify-center gap-1"
            :class="sortBy === 'comments' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'border-gray-200 text-ink-light hover:bg-gray-50'"><AppIcon name="message-circle" :size="13" />댓글순</button>
        </div>
      </div>
    </MobileFilter>

    <!-- 헤더: 데스크탑 -->
    <div class="hidden lg:flex items-center justify-between mb-4">
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
        <span class="icon-chip w-9 h-9 bg-blue-50 text-blue-600"><AppIcon name="message-circle" :size="20" /></span>
        커뮤니티
      </h1>
      <div class="flex items-center gap-2">
        <select v-model="sortBy" @change="loadPosts()" class="input-soft w-auto text-xs py-1.5">
          <option value="latest">최신순</option>
          <option value="popular">인기순</option>
          <option value="views">조회순</option>
          <option value="comments">댓글순</option>
        </select>
        <RouterLink v-if="auth.isLoggedIn" to="/community/write" class="btn-primary px-4 py-2"><AppIcon name="edit" :size="15" />글쓰기</RouterLink>
      </div>
    </div>

    <div class="grid grid-cols-12 gap-4">
      <!-- 왼쪽: 게시판 카테고리 -->
      <div class="col-span-12 lg:col-span-2 hidden lg:block">
        <div class="sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto space-y-3 pr-0.5">
          <div class="card overflow-hidden">
            <div class="px-3 py-2.5 border-b border-gray-50 flex items-center gap-2">
              <span class="icon-chip w-7 h-7 bg-blue-50 text-blue-600"><AppIcon name="list" :size="14" /></span>
              <span class="text-xs font-bold text-ink">게시판</span>
            </div>
            <button @click="showFavorites=false; activeBoard=null; activeItem=null; loadPosts()" class="w-full text-left px-3 py-2 text-xs transition-colors"
              :class="!showFavorites && !activeBoard ? 'bg-amber-50 text-amber-700 font-bold' : 'text-ink-light hover:bg-amber-50/50'">전체</button>
            <button v-for="b in boards" :key="b.id" @click="showFavorites=false; activeBoard=b; activeItem=null; loadPosts()"
              class="w-full text-left px-3 py-2 text-xs transition-colors"
              :class="!showFavorites && activeBoard?.id === b.id ? 'bg-amber-50 text-amber-700 font-bold' : 'text-ink-light hover:bg-amber-50/50'">
              {{ b.name }}
            </button>
            <button v-if="auth.isLoggedIn" @click="showFavorites=true; activeItem=null; loadFavoritesPage()"
              class="w-full text-left px-3 py-2 text-xs transition-colors border-t border-gray-50"
              :class="showFavorites ? 'bg-red-50 text-red-600 font-bold' : 'text-ink-light hover:bg-red-50/50'">
              <span class="flex items-center gap-1"><AppIcon name="bookmark" :size="12" />내 북마크<span v-if="favCount > 0" class="ml-0.5">({{ favCount }})</span></span>
            </button>
          </div>
          <AdSlot page="community" position="left" :maxSlots="2" />
        </div>
      </div>

      <!-- 메인: 게시글 목록 -->
      <div class="col-span-12 lg:col-span-7">

        <div class="mb-2">
          <span v-if="showFavorites" class="font-bold text-red-600 text-sm inline-flex items-center gap-1"><AppIcon name="bookmark" :size="14" />내 북마크</span>
          <template v-else>
            <span class="font-bold text-amber-700 text-sm">{{ activeBoard ? activeBoard.name : '전체' }}</span>
            <span v-if="activeBoard?.description" class="text-xs text-ink-muted ml-2">{{ activeBoard.description }}</span>
            <span v-if="!activeBoard" class="text-xs text-ink-muted ml-2">모든 게시판의 글을 볼 수 있습니다</span>
          </template>
        </div>

        <!-- ═══ 상세 모드 ═══ -->
        <div v-if="activeItem">
          <div class="card overflow-hidden mb-3">
            <div class="px-5 py-4 border-b border-gray-50">
              <div class="flex items-center gap-2 mb-2">
                <span class="badge-primary">{{ activeItem.board?.name }}</span>
              </div>
              <h2 class="text-lg font-bold text-ink">{{ activeItem.title }}</h2>
              <div class="flex items-center gap-3 mt-2 text-xs text-ink-muted">
                <button @click="openPopup(activeItem.user?.id)" class="hover:text-amber-700 transition-colors">{{ activeItem.user?.name }}</button>
                <span>{{ formatDate(activeItem.created_at) }}</span>
                <span class="flex items-center gap-1"><AppIcon name="eye" :size="13" />{{ activeItem.view_count }}</span>
                <span class="flex items-center gap-1"><AppIcon name="heart" :size="13" />{{ activeItem.like_count }}</span>
                <span class="flex items-center gap-1"><AppIcon name="message-circle" :size="13" />{{ activeItem.comment_count }}</span>
              </div>
            </div>
            <div class="px-5 py-5 text-sm text-ink-light leading-relaxed whitespace-pre-wrap">{{ activeItem.content }}</div>
            <div class="px-5 py-3 border-t border-gray-50 flex gap-4 items-center">
              <button @click="toggleLike" class="text-sm flex items-center gap-1.5 font-semibold transition-colors" :class="liked ? 'text-red-500' : 'text-ink-muted hover:text-red-400'"><AppIcon name="heart" :size="16" :filled="liked" /> 좋아요 {{ activeItem.like_count }}</button>
              <BookmarkToggle :active="favoritedList.has(activeItem.id)" @toggle="toggleFavList(activeItem)" size="lg" />
              <ShareButton :title="activeItem.title" :text="activeItem.content?.slice(0, 100)" label="공유" />
            </div>
          </div>

          <!-- 댓글 -->
          <CommentSection :type="'post'" :typeId="activeItem.id" ref="commentSection" />

          <!-- 이전글 / 목록 / 다음글 (같은 게시판 한정, 서버 prev/next) -->
          <div class="mt-4 flex items-stretch card text-sm overflow-hidden">
            <button v-if="adjPrev" @click="navItem(-1)"
              class="flex-1 min-w-0 px-4 py-3 hover:bg-amber-50/60 text-left text-ink-light border-r border-gray-50 transition-colors">
              <div class="text-ink-muted text-xs flex items-center gap-1"><AppIcon name="chevron-left" :size="12" />이전글</div>
              <div class="text-xs text-ink-light truncate mt-0.5">{{ adjPrev.title || '' }}</div>
            </button>
            <div v-else class="flex-1 min-w-0 px-4 py-3 text-left text-ink-faint border-r border-gray-50 text-xs flex items-center gap-1"><AppIcon name="chevron-left" :size="12" />이전글 없음</div>

            <button @click="activeItem=null; adjPrev=null; adjNext=null"
              class="px-5 py-3 hover:bg-amber-50/60 text-center text-ink font-bold border-r border-gray-50 flex-shrink-0 transition-colors">
              목록
            </button>

            <button v-if="adjNext" @click="navItem(1)"
              class="flex-1 min-w-0 px-4 py-3 hover:bg-amber-50/60 text-right text-ink-light transition-colors">
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
          <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="message-circle" :size="28" :stroke-width="1.5" /></div>
          <p class="text-sm text-ink-muted">게시글이 없습니다</p>
        </div>

        <!-- 리스트 뷰 -->
        <div v-else class="card overflow-hidden divide-y divide-gray-50">
          <template v-for="(item, i) in items" :key="item.id">
          <div @click="openItem(item)"
            class="list-row">
            <div class="flex items-center gap-2">
              <span v-if="!activeBoard" class="badge-primary flex-shrink-0">{{ item.board?.name || '자유' }}</span>
              <span class="text-sm font-semibold text-ink truncate flex-1">{{ item.title }}</span>
            </div>
            <div class="flex items-center gap-2 mt-1 text-xs text-ink-muted">
              <button @click.stop="openPopup(item.user?.id)" class="hover:text-amber-700 transition-colors">{{ item.user?.name }}</button>
              <span class="flex items-center gap-0.5"><AppIcon name="eye" :size="12" />{{ item.view_count }}</span>
              <span class="flex items-center gap-0.5"><AppIcon name="heart" :size="12" />{{ item.like_count }}</span>
              <span v-if="item.comment_count" class="flex items-center gap-0.5"><AppIcon name="message-circle" :size="12" />{{ item.comment_count }}</span>
              <span>{{ formatDate(item.created_at) }}</span>
              <BookmarkToggle v-if="auth.isLoggedIn" :active="favoritedList.has(item.id)" @toggle="toggleFavList(item)" size="sm" class="ml-auto" />
            </div>
          </div>
          <MobileAdInline v-if="i === 4" page="community" />
          </template>
        </div>

        <!-- 페이지네이션 -->
        <Pagination :page="page" :lastPage="lastPage" @page="loadPosts" />
        </div>
      </div>

      <!-- 오른쪽: 사이드바 위젯 -->
      <div class="col-span-12 lg:col-span-3 hidden lg:block">
        <SidebarWidgets :currentCategory="activeItem ? (activeItem.board_id || '') : (activeBoard?.id || '')" categoryParam="board_id" :inline="true" @select="openItem"
          :api-url="'/api/posts'" detail-path="/community/free/" :current-id="activeItem?.id || 0"
          :mode="activeItem ? 'detail' : 'list'" :categoryLabel="activeItem?.board?.name || activeBoard?.name || ''" label="게시글" />
        <AdSlot page="community" position="right" :maxSlots="2" />
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useBookmarkStore } from '../../stores/bookmarks'
import AdSlot from '../../components/AdSlot.vue'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import CommentSection from '../../components/CommentSection.vue'
import BookmarkToggle from '../../components/BookmarkToggle.vue'
import ShareButton from '../../components/ShareButton.vue'
import AppIcon from '../../components/AppIcon.vue'
import axios from 'axios'

const route = useRoute()
const router = useRouter()

const auth = useAuthStore()
const bStore = useBookmarkStore()
const BM_TYPE = 'App\\Models\\Post'
const showFilter = ref(false)
const showFavorites = ref(false)
const favoritedList = ref(new Set())
const favCount = computed(() => bStore.getBookmarkedIds(BM_TYPE).length)
const items = ref([])
const boards = ref([])
const popularPosts = ref([])
const activeBoard = ref(null)
const mobileBoardId = ref(null)
const sortBy = ref('latest')
const popPage = ref(1)
const popLastPage = ref(1)
const popPages = computed(() => {
  const start = Math.max(1, popPage.value - 2)
  const end = Math.min(popLastPage.value, start + 4)
  const pages = []
  for (let i = Math.max(1, end - 4); i <= end; i++) pages.push(i)
  return pages
})
const loading = ref(true)
const page = ref(1)
const lastPage = ref(1)

// 인라인 상세
const activeItem = ref(null)
const currentIdx = ref(-1)
const adjPrev = ref(null) // 같은 게시판 내 이전글 (서버 prev)
const adjNext = ref(null) // 같은 게시판 내 다음글 (서버 next)
const liked = ref(false)
const bookmarked = ref(false)
const commentSection = ref(null)

function navItem(dir) {
  const target = dir < 0 ? adjPrev.value : adjNext.value
  if (target && target.id) {
    const inList = items.value.find(i => i.id === target.id)
    openItem(inList || { id: target.id, title: target.title })
  }
}

async function openItem(item) {
  currentIdx.value = items.value.findIndex(i => i.id === item.id)
  liked.value = false
  bookmarked.value = false
  adjPrev.value = null
  adjNext.value = null
  try {
    const { data } = await axios.get(`/api/posts/${item.id}`)
    activeItem.value = data.data
    adjPrev.value = data.prev || null
    adjNext.value = data.next || null
    // 좋아요/북마크 상태 로드
    liked.value = !!data.data?.is_liked
    bookmarked.value = !!data.data?.is_bookmarked
  } catch { activeItem.value = item }
  const post = activeItem.value
  if (post?.board_id) {
    activeBoard.value = boards.value.find(b => b.id === post.board_id) || null
    mobileBoardId.value = post.board_id
  }
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function toggleLike() {
  if (!auth.isLoggedIn || !activeItem.value) return
  try {
    const { data } = await axios.post(`/api/posts/${activeItem.value.id}/like`)
    liked.value = data.liked
    activeItem.value.like_count += data.liked ? 1 : -1
  } catch {}
}

async function toggleBookmark() {
  if (!auth.isLoggedIn || !activeItem.value) return
  try {
    const { data } = await axios.post('/api/bookmarks', {
      bookmarkable_type: 'App\\Models\\Post',
      bookmarkable_id: activeItem.value.id
    })
    bookmarked.value = data.bookmarked
  } catch {}
}

function openPopup(userId) {
  if (userId && window.openUserPopup) window.openUserPopup(userId)
}

function formatDate(dt) {
  if (!dt) return ''
  const h = Math.floor((Date.now() - new Date(dt).getTime()) / 3600000)
  if (h < 1) return '방금'
  if (h < 24) return h + '시간 전'
  return Math.floor(h / 24) + '일 전'
}

function onMobileBoard() {
  activeBoard.value = boards.value.find(b => b.id === mobileBoardId.value) || null
  loadPosts()
}

async function loadPopular(p = 1) {
  popPage.value = p
  try {
    const { data } = await axios.get('/api/posts', { params: { sort: 'popular', per_page: 10, page: p } })
    popularPosts.value = data.data?.data || []
    popLastPage.value = data.data?.last_page || 1
  } catch {}
}

async function loadPosts(p = 1) {
  loading.value = true
  page.value = p
  const params = { page: p, per_page: 20, sort: sortBy.value }
  if (activeBoard.value) params.board_id = activeBoard.value.id
  try {
    const { data } = await axios.get('/api/posts', { params })
    items.value = data.data?.data || []
    lastPage.value = data.data?.last_page || 1
  } catch {}
  loading.value = false
  loadFavoritedList()
}

// 리스트 하트 (Bookmark)
async function loadFavoritedList() {
  if (!auth.isLoggedIn || !items.value.length) return
  try {
    const ids = items.value.map(i => i.id).join(',')
    const { data } = await axios.get('/api/bookmarks/check', { params: { type: BM_TYPE, ids } })
    favoritedList.value = new Set(data.data || [])
  } catch {}
}
async function toggleFavList(item) {
  if (!auth.isLoggedIn) return
  const result = await bStore.toggle(BM_TYPE, item.id)
  if (result !== null) {
    if (result) favoritedList.value.add(item.id)
    else favoritedList.value.delete(item.id)
    favoritedList.value = new Set(favoritedList.value)
  }
}
async function loadFavoritesPage() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/bookmarks', { params: { type: 'App\\Models\\Post', per_page: 50 } })
    const bms = data.data?.data || []
    items.value = bms.map(b => b.bookmarkable).filter(Boolean)
    lastPage.value = 1
    loadFavoritedList()
  } catch {}
  loading.value = false
}

onMounted(async () => {
  bStore.loadAll()
  // 게시판 목록 + 게시글 + 인기글 + 구인 동시 로딩
  const [bRes, pRes] = await Promise.allSettled([
    axios.get('/api/boards'),
    axios.get('/api/posts?per_page=20'),
  ])
  if (bRes.status === 'fulfilled') boards.value = bRes.value.data?.data || []
  if (pRes.status === 'fulfilled') { items.value = pRes.value.data?.data?.data || []; lastPage.value = pRes.value.data?.data?.last_page || 1 }
  loadPopular()

  // URL에 board slug가 있으면 해당 게시판 자동 선택
  const boardSlug = route.params.board
  if (boardSlug && boards.value.length) {
    const found = boards.value.find(b => b.slug === boardSlug)
    if (found) {
      activeBoard.value = found
      await loadPosts()
    }
  }

  // URL에 id가 있으면 해당 글을 인라인으로 열기 (Issue #16)
  await ensureActivePostFromRoute()

  loading.value = false
})

async function ensureActivePostFromRoute() {
  const postId = route.params.id
  if (!postId) return
  try {
    const { data } = await axios.get(`/api/posts/${postId}`)
    activeItem.value = data.data
    // 댓글 로드
    try {
      const { data: cData } = await axios.get(`/api/comments/post/${postId}`)
      comments.value = cData.data || []
    } catch {}
  } catch (err) {
    if (err.response?.status === 404) router.replace('/404')
  }
}

// 같은 컴포넌트 내 라우트 변경 감지 (상세↔리스트)
watch(() => route.params.id, (newId, oldId) => {
  if (oldId && !newId) {
    // 상세에서 리스트로 복귀 → 리스트 새로고침
    loadPosts()
    activeItem.value = null
    return
  }
  if (newId && String(newId) !== String(activeItem.value?.id)) {
    ensureActivePostFromRoute()
  }
})

watch(() => route.params.board, (newBoard, oldBoard) => {
  if (newBoard !== oldBoard) {
    const found = boards.value.find(b => b.slug === newBoard)
    if (found) {
      activeBoard.value = found
      page.value = 1
      loadPosts()
    }
    activeItem.value = null
  }
})
</script>
