<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <div class="flex items-center justify-between mb-4">
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
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-20">
          <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">📋 게시판</div>
          <button @click="activeBoard=null; activeItem=null; loadPosts()" class="w-full text-left px-3 py-2 text-xs transition"
            :class="!activeBoard ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">전체</button>
          <button v-for="b in boards" :key="b.id" @click="activeBoard=b; activeItem=null; loadPosts()"
            class="w-full text-left px-3 py-2 text-xs transition"
            :class="activeBoard?.id === b.id ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">
            {{ b.name }}
          </button>
        </div>
      </div>

      <!-- 메인: 게시글 목록 -->
      <div class="col-span-12 lg:col-span-7">
        <!-- 모바일 게시판 선택 -->
        <div class="lg:hidden mb-3">
          <select v-model="mobileBoardId" @change="onMobileBoard" class="w-full border rounded-lg px-3 py-2 text-sm">
            <option :value="null">전체 게시판</option>
            <option v-for="b in boards" :key="b.id" :value="b.id">{{ b.name }}</option>
          </select>
        </div>

        <div class="mb-2">
          <span class="font-bold text-amber-700 text-sm">{{ activeBoard ? activeBoard.name : '전체' }}</span>
          <span v-if="activeBoard?.description" class="text-xs text-gray-400 ml-2">{{ activeBoard.description }}</span>
          <span v-if="!activeBoard" class="text-xs text-gray-400 ml-2">모든 게시판의 글을 볼 수 있습니다</span>
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
              <button @click="toggleLike" class="text-sm" :class="liked ? 'text-red-500' : 'text-gray-400'">{{ liked ? '❤️' : '🤍' }} 좋아요</button>
              <span class="text-gray-400 text-sm">🔖 북마크</span>
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
          <div v-for="item in items" :key="item.id" @click="openItem(item)"
            class="px-4 py-3 border-b border-gray-50 hover:bg-amber-50/50 hover:border-l-2 hover:border-l-amber-400 transition cursor-pointer">
            <div class="flex items-center gap-2">
              <span class="text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full font-semibold flex-shrink-0">{{ item.board?.name || '자유' }}</span>
              <span class="text-sm font-medium text-gray-800 truncate flex-1">{{ item.title }}</span>
              <span v-if="item.comment_count" class="text-[10px] text-amber-500 font-bold flex-shrink-0">[{{ item.comment_count }}]</span>
            </div>
            <div class="flex items-center gap-2 mt-1 text-xs text-gray-400">
              <button @click.stop="openPopup(item.user?.id)" class="hover:text-amber-700">{{ item.user?.name }}</button>
              <span>{{ item.view_count }}조회</span>
              <span>❤️{{ item.like_count }}</span>
              <span>{{ formatDate(item.created_at) }}</span>
            </div>
          </div>
        </div>

        <!-- 페이지네이션 -->
        <div v-if="lastPage > 1" class="flex justify-center gap-1.5 mt-4">
          <button v-for="pg in Math.min(lastPage, 10)" :key="pg" @click="loadPosts(pg)"
            class="px-3 py-1 rounded text-sm" :class="pg===page?'bg-amber-400 text-amber-900 font-bold':'bg-white border text-gray-600 hover:bg-amber-50'">{{ pg }}</button>
        </div>
        </div>
      </div>

      <!-- 오른쪽: 인기글 -->
      <div class="col-span-12 lg:col-span-3 hidden lg:block">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-20">
          <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">🔥 인기 게시글</div>
          <div class="py-1">
            <button v-for="(p, i) in popularPosts" :key="p.id" @click="openItem(p)"
              class="flex items-start gap-2 px-3 py-1.5 hover:bg-amber-50/50 transition w-full text-left">
              <span class="text-[10px] font-bold w-4 text-center flex-shrink-0" :class="(popPage-1)*10+i<3?'text-amber-600':'text-gray-400'">{{ (popPage-1)*10+i+1 }}</span>
              <span class="text-xs text-gray-700 leading-snug line-clamp-1 flex-1">{{ p.title }}</span>
            </button>
          </div>
          <!-- 인기글 페이지네이션 -->
          <div v-if="popLastPage > 1" class="px-3 py-2 border-t flex justify-center items-center gap-1">
            <button @click="loadPopular(1)" :disabled="popPage<=1" class="text-[10px] text-gray-400 hover:text-amber-700 disabled:opacity-30 px-1">«</button>
            <button @click="loadPopular(popPage-1)" :disabled="popPage<=1" class="text-[10px] text-gray-400 hover:text-amber-700 disabled:opacity-30 px-1">‹</button>
            <template v-for="pg in popPages" :key="pg">
              <button @click="loadPopular(pg)" class="w-6 h-6 rounded text-[10px] font-bold" :class="pg===popPage?'bg-amber-400 text-amber-900':'text-gray-400 hover:bg-amber-50'">{{ pg }}</button>
            </template>
            <button @click="loadPopular(popPage+1)" :disabled="popPage>=popLastPage" class="text-[10px] text-gray-400 hover:text-amber-700 disabled:opacity-30 px-1">›</button>
            <button @click="loadPopular(popLastPage)" :disabled="popPage>=popLastPage" class="text-[10px] text-gray-400 hover:text-amber-700 disabled:opacity-30 px-1">»</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import CommentSection from '../../components/CommentSection.vue'
import axios from 'axios'

const route = useRoute()

const auth = useAuthStore()
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
const commentSection = ref(null)

function navItem(dir) {
  const newIdx = currentIdx.value + dir
  if (newIdx >= 0 && newIdx < items.value.length) openItem(items.value[newIdx])
}

async function openItem(item) {
  currentIdx.value = items.value.findIndex(i => i.id === item.id)
  try {
    const { data } = await axios.get(`/api/posts/${item.id}`)
    activeItem.value = data.data
  } catch { activeItem.value = item }
  // 해당 글의 게시판으로 사이드바+타이틀 변경
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
}

onMounted(async () => {
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

  // URL에 id가 있으면 해당 글을 인라인으로 열기
  const postId = route.params.id
  if (postId) {
    try {
      const { data } = await axios.get(`/api/posts/${postId}`)
      activeItem.value = data.data
      // 댓글도 로드
      try {
        const { data: cData } = await axios.get(`/api/comments/post/${postId}`)
        comments.value = cData.data || []
      } catch {}
    } catch {}
  }

  loading.value = false
})
</script>
