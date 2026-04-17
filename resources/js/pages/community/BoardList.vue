<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <!-- 헤더: 모바일 -->
    <div class="lg:hidden mb-3">
      <div class="flex items-center justify-between mb-2">
        <h1 class="text-lg font-black text-gray-800">💬 커뮤니티</h1>
        <div class="flex items-center gap-2">
          <button @click="showFilter = true" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold px-3 py-2 rounded-lg">🔍 필터</button>
          <RouterLink v-if="auth.isLoggedIn" to="/community/write" class="bg-amber-400 text-amber-900 text-xs font-bold px-3 py-2 rounded-lg">✏️ 글쓰기</RouterLink>
        </div>
      </div>
      <div class="flex items-center gap-1.5 overflow-x-auto">
        <span class="text-[10px] bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-semibold whitespace-nowrap">
          {{ {latest:'최신순',popular:'인기순',views:'조회순',comments:'댓글순'}[sortBy] }}
        </span>
      </div>
    </div>

    <!-- 모바일 필터 바텀시트 -->
    <MobileFilter v-model="showFilter" @apply="loadPosts()" @reset="activeBoard = null; sortBy = 'latest'; loadPosts()">
      <div class="mb-4">
        <label class="text-xs font-bold text-gray-600 mb-2 block">게시판</label>
        <div class="grid grid-cols-3 gap-1.5">
          <button @click="activeBoard = null"
            class="text-xs py-2 rounded-lg font-semibold border transition"
            :class="!activeBoard ? 'bg-amber-50 text-amber-700 border-amber-300' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
            전체
          </button>
          <button v-for="b in boards" :key="b.id" @click="activeBoard = b"
            class="text-xs py-2 rounded-lg font-semibold border transition"
            :class="activeBoard?.id === b.id ? 'bg-amber-50 text-amber-700 border-amber-300' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
            {{ b.name }}
          </button>
        </div>
      </div>
      <div>
        <label class="text-xs font-bold text-gray-600 mb-2 block">정렬</label>
        <div class="grid grid-cols-2 gap-1.5">
          <button @click="sortBy = 'latest'" class="text-xs py-2 rounded-lg font-semibold border transition"
            :class="sortBy === 'latest' ? 'bg-amber-50 text-amber-700 border-amber-300' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">🕐 최신순</button>
          <button @click="sortBy = 'popular'" class="text-xs py-2 rounded-lg font-semibold border transition"
            :class="sortBy === 'popular' ? 'bg-amber-50 text-amber-700 border-amber-300' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">🔥 인기순</button>
          <button @click="sortBy = 'views'" class="text-xs py-2 rounded-lg font-semibold border transition"
            :class="sortBy === 'views' ? 'bg-amber-50 text-amber-700 border-amber-300' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">👁 조회순</button>
          <button @click="sortBy = 'comments'" class="text-xs py-2 rounded-lg font-semibold border transition"
            :class="sortBy === 'comments' ? 'bg-amber-50 text-amber-700 border-amber-300' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">💬 댓글순</button>
        </div>
      </div>
    </MobileFilter>

    <!-- 헤더: 데스크탑 -->
    <div class="hidden lg:flex items-center justify-between mb-4">
      <h1 class="text-xl font-black text-gray-800">💬 커뮤니티</h1>
      <div class="flex items-center gap-2">
        <select v-model="sortBy" @change="loadPosts()" class="border rounded-lg px-2 py-1.5 text-xs text-gray-600 outline-none">
          <option value="latest">최신순</option>
          <option value="popular">인기순</option>
          <option value="views">조회순</option>
          <option value="comments">댓글순</option>
        </select>
        <RouterLink v-if="auth.isLoggedIn" to="/community/write" class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-lg text-sm hover:bg-amber-500">✏️ 글쓰기</RouterLink>
      </div>
    </div>

    <div class="grid grid-cols-12 gap-4">
      <!-- 왼쪽: 게시판 카테고리 -->
      <div class="col-span-12 lg:col-span-2 hidden lg:block">
        <div class="sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto space-y-3 pr-0.5">
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">📋 게시판</div>
            <button @click="showFavorites=false; activeBoard=null; activeItem=null; loadPosts()" class="w-full text-left px-3 py-2 text-xs transition"
              :class="!showFavorites && !activeBoard ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">전체</button>
            <button v-for="b in boards" :key="b.id" @click="showFavorites=false; activeBoard=b; activeItem=null; loadPosts()"
              class="w-full text-left px-3 py-2 text-xs transition"
              :class="!showFavorites && activeBoard?.id === b.id ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">
              {{ b.name }}
            </button>
            <button v-if="auth.isLoggedIn" @click="showFavorites=true; activeItem=null; loadFavoritesPage()"
              class="w-full text-left px-3 py-2 text-xs transition border-t"
              :class="showFavorites ? 'bg-red-50 text-red-600 font-bold' : 'text-gray-600 hover:bg-red-50/50'">
              🔖 내 북마크<span v-if="favCount > 0" class="ml-0.5">({{ favCount }})</span>
            </button>
          </div>
          <AdSlot page="community" position="left" :maxSlots="2" />
        </div>
      </div>

      <!-- 메인: 게시글 목록 -->
      <div class="col-span-12 lg:col-span-7">

        <div class="mb-2">
          <span v-if="showFavorites" class="font-bold text-red-600 text-sm">🔖 내 북마크</span>
          <template v-else>
            <span class="font-bold text-amber-700 text-sm">{{ activeBoard ? activeBoard.name : '전체' }}</span>
            <span v-if="activeBoard?.description" class="text-xs text-gray-400 ml-2">{{ activeBoard.description }}</span>
            <span v-if="!activeBoard" class="text-xs text-gray-400 ml-2">모든 게시판의 글을 볼 수 있습니다</span>
          </template>
        </div>

        <!-- ═══ 상세 모드 ═══ -->
        <div v-if="activeItem">
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-3">
            <div class="px-5 py-4 border-b">
              <div class="flex items-center gap-2 mb-2">
                <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-semibold">{{ activeItem.board?.name }}</span>
              </div>
              <h2 class="text-lg font-bold text-gray-900">{{ activeItem.title }}</h2>
              <div class="flex items-center gap-3 mt-2 text-xs text-gray-400">
                <button @click="openPopup(activeItem.user?.id)" class="hover:text-amber-700">{{ activeItem.user?.name }}</button>
                <span>{{ formatDate(activeItem.created_at) }}</span>
                <span>👁 {{ activeItem.view_count }}</span>
                <span>❤️ {{ activeItem.like_count }}</span>
                <span>💬 {{ activeItem.comment_count }}</span>
              </div>
            </div>
            <div class="px-5 py-5 text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">{{ activeItem.content }}</div>
            <div class="px-5 py-3 border-t flex gap-4">
              <button @click="toggleLike" class="text-sm" :class="liked ? 'text-red-500' : 'text-gray-400'">{{ liked ? '❤️' : '🤍' }} 좋아요 {{ activeItem.like_count }}</button>
              <BookmarkToggle :active="favoritedList.has(activeItem.id)" @toggle="toggleFavList(activeItem)" size="lg" />
            </div>
          </div>

          <!-- 댓글 -->
          <CommentSection :type="'post'" :typeId="activeItem.id" ref="commentSection" />

          <!-- 이전/다음글 -->
          <div class="flex justify-between mt-3">
            <button @click="navItem(-1)" :disabled="currentIdx <= 0" class="text-xs text-gray-500 hover:text-amber-700 disabled:opacity-30">← 이전글</button>
            <button @click="activeItem=null" class="text-xs text-gray-400 hover:text-gray-600">목록</button>
            <button @click="navItem(1)" :disabled="currentIdx >= items.length-1" class="text-xs text-gray-500 hover:text-amber-700 disabled:opacity-30">다음글 →</button>
          </div>
        </div>

        <!-- ═══ 목록 모드 ═══ -->
        <div v-else>
        <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
        <div v-else-if="!items.length" class="text-center py-12 text-gray-400">게시글이 없습니다</div>

        <!-- 리스트 뷰 -->
        <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <template v-for="(item, i) in items" :key="item.id">
          <div @click="openItem(item)"
            class="px-4 py-3 border-b border-gray-50 hover:bg-amber-50/50 hover:border-l-2 hover:border-l-amber-400 transition cursor-pointer">
            <div class="flex items-center gap-2">
              <span v-if="!activeBoard" class="text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full font-semibold flex-shrink-0">{{ item.board?.name || '자유' }}</span>
              <span class="text-sm font-medium text-gray-800 truncate flex-1">{{ item.title }}</span>
            </div>
            <div class="flex items-center gap-2 mt-1 text-xs text-gray-400">
              <button @click.stop="openPopup(item.user?.id)" class="hover:text-amber-700">{{ item.user?.name }}</button>
              <span>👁{{ item.view_count }}</span>
              <span>❤️{{ item.like_count }}</span>
              <span v-if="item.comment_count">💬{{ item.comment_count }}</span>
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
const liked = ref(false)
const bookmarked = ref(false)
const commentSection = ref(null)

function navItem(dir) {
  const newIdx = currentIdx.value + dir
  if (newIdx >= 0 && newIdx < items.value.length) openItem(items.value[newIdx])
}

async function openItem(item) {
  currentIdx.value = items.value.findIndex(i => i.id === item.id)
  liked.value = false
  bookmarked.value = false
  try {
    const { data } = await axios.get(`/api/posts/${item.id}`)
    activeItem.value = data.data
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
