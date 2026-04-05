<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <div class="max-w-[1200px] mx-auto px-4 pt-4">

      <!-- 상단 그라디언트 헤더 -->
      <div class="bg-gradient-to-r from-blue-600 to-blue-500 text-white px-6 py-5 rounded-2xl mb-4">
        <div class="flex items-center gap-2 mb-2">
          <router-link to="/community" class="text-blue-200 text-sm hover:text-white transition">← 커뮤니티</router-link>
          <span v-if="board" class="bg-white/20 text-xs px-3 py-1 rounded-full">{{ board.name }}</span>
        </div>
        <h1 class="text-xl font-black leading-tight">{{ board?.name || '게시판' }}</h1>
        <p v-if="board?.description" class="text-blue-200 text-sm mt-1">{{ board.description }}</p>
      </div>

      <!-- 2컬럼 레이아웃 -->
      <div class="flex gap-5 items-start">

        <!-- ── 왼쪽 사이드바: 게시판 목록 ── -->
        <aside class="hidden lg:block w-52 flex-shrink-0 sticky top-20">
          <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100">
              <router-link to="/community" class="text-sm font-bold text-blue-600 hover:text-blue-700">
                전체 커뮤니티
              </router-link>
            </div>

            <!-- 커뮤니티 게시판 -->
            <div v-if="communityBoards.length">
              <div class="px-4 py-2 bg-gray-50 border-b border-gray-100">
                <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">커뮤니티</span>
              </div>
              <ul>
                <li v-for="b in communityBoards" :key="b.id">
                  <router-link :to="'/community/' + b.slug"
                    class="flex items-center gap-2 px-4 py-2.5 text-sm transition"
                    :class="route.params.slug === b.slug
                      ? 'bg-blue-50 text-blue-600 font-semibold border-l-2 border-blue-500'
                      : 'text-gray-600 hover:bg-gray-50'">
                    {{ b.name }}
                  </router-link>
                </li>
              </ul>
            </div>

            <!-- 지역 게시판 -->
            <div v-if="localBoards.length">
              <div class="px-4 py-2 bg-gray-50 border-b border-gray-100">
                <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">지역</span>
              </div>
              <ul>
                <li v-for="b in localBoards" :key="b.id">
                  <router-link :to="'/community/' + b.slug"
                    class="flex items-center gap-2 px-4 py-2.5 text-sm transition"
                    :class="route.params.slug === b.slug
                      ? 'bg-blue-50 text-blue-600 font-semibold border-l-2 border-blue-500'
                      : 'text-gray-600 hover:bg-gray-50'">
                    {{ b.name }}
                  </router-link>
                </li>
              </ul>
            </div>
          </div>
        </aside>

        <!-- 모바일 게시판 탭 -->
        <div class="lg:hidden w-full mb-4" v-if="allBoards.length">
          <div class="flex overflow-x-auto gap-2 pb-2 scrollbar-hide">
            <router-link to="/community"
              class="whitespace-nowrap px-3 py-1.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 hover:bg-gray-200">
              전체
            </router-link>
            <router-link v-for="b in allBoards" :key="b.id"
              :to="'/community/' + b.slug"
              class="whitespace-nowrap px-3 py-1.5 rounded-full text-xs font-medium transition"
              :class="route.params.slug === b.slug
                ? 'bg-blue-500 text-white'
                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
              {{ b.name }}
            </router-link>
          </div>
        </div>

        <!-- ── 오른쪽: 게시글 목록 ── -->
        <div class="flex-1 min-w-0">

          <!-- 검색 + 글쓰기 -->
          <div class="flex gap-2 mb-4">
            <input v-model="searchQ" @keyup.enter="searchPosts" type="text"
              placeholder="검색어 입력"
              class="flex-1 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white" />
            <button @click="searchPosts"
              class="bg-white border border-gray-200 px-4 py-2.5 rounded-xl text-sm hover:bg-gray-50 transition">검색</button>
            <router-link v-if="authStore.isLoggedIn" to="/community/write"
              class="bg-blue-600 text-white px-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-blue-700 transition whitespace-nowrap">
              ✏ 글쓰기
            </router-link>
          </div>

          <!-- 게시글 목록 -->
          <div v-if="loading" class="text-center py-20 text-gray-400">불러오는 중...</div>
          <div v-else-if="posts.length === 0"
            class="bg-white rounded-2xl border border-gray-100 text-center py-16 text-gray-400">
            <p class="text-3xl mb-2">📭</p>
            <p class="text-sm">게시글이 없습니다. 첫 번째 글을 작성해보세요!</p>
          </div>
          <div v-else class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div v-for="post in posts" :key="post.id"
              class="border-b border-gray-50 last:border-0 hover:bg-gray-50 transition">
              <router-link :to="'/community/post/' + post.id" class="block px-5 py-4">
                <div class="flex items-start justify-between gap-3">
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                      <span v-if="post.is_pinned"
                        class="text-xs bg-red-100 text-red-600 px-1.5 py-0.5 rounded font-medium">공지</span>
                      <span class="text-gray-800 text-sm font-semibold">{{ post.title }}</span>
                      <span v-if="(post.comment_count || 0) > 0"
                        class="text-blue-500 text-xs font-medium">[{{ post.comment_count }}]</span>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-gray-400">
                      <span class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 font-bold text-[10px] flex-shrink-0">
                        {{ (post.user?.name || '?')[0] }}
                      </span>
                      <span>{{ post.user?.name || '익명' }}</span>
                      <span>·</span>
                      <span>{{ formatDate(post.created_at) }}</span>
                    </div>
                  </div>
                  <div class="flex items-center gap-3 text-xs text-gray-400 flex-shrink-0">
                    <span>👁 {{ post.view_count }}</span>
                    <span>❤️ {{ post.like_count || 0 }}</span>
                  </div>
                </div>
              </router-link>
            </div>
          </div>

          <!-- 페이지네이션 -->
          <div v-if="totalPages > 1" class="flex justify-center gap-1 mt-4">
            <button @click="loadPosts(currentPage - 1)" :disabled="currentPage === 1"
              class="px-3 py-1.5 rounded-lg text-sm bg-white border border-gray-200 text-gray-500 hover:bg-gray-50 disabled:opacity-40">‹</button>
            <button v-for="p in visiblePages" :key="p" @click="loadPosts(p)"
              :class="['px-3 py-1.5 rounded-lg text-sm', p === currentPage
                ? 'bg-blue-600 text-white'
                : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-200']">
              {{ p }}
            </button>
            <button @click="loadPosts(currentPage + 1)" :disabled="currentPage === totalPages"
              class="px-3 py-1.5 rounded-lg text-sm bg-white border border-gray-200 text-gray-500 hover:bg-gray-50 disabled:opacity-40">›</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'

const route = useRoute()
const authStore = useAuthStore()
const posts = ref([])
const board = ref(null)
const allBoards = ref([])
const loading = ref(true)
const searchQ = ref('')
const currentPage = ref(1)
const totalPages = ref(1)

const communityBoards = computed(() => allBoards.value.filter(b => b.category === 'community'))
const localBoards = computed(() => allBoards.value.filter(b => b.category === 'local'))

const visiblePages = computed(() => {
  const pages = []
  const start = Math.max(1, currentPage.value - 2)
  const end = Math.min(totalPages.value, currentPage.value + 2)
  for (let i = start; i <= end; i++) pages.push(i)
  return pages
})

async function loadBoards() {
  try {
    const { data } = await axios.get('/api/boards')
    allBoards.value = data
  } catch {}
}

async function loadPosts(page = 1) {
  loading.value = true
  try {
    const params = { board: route.params.slug, page, per_page: 20 }
    if (searchQ.value.trim()) params.search = searchQ.value.trim()
    const { data } = await axios.get('/api/posts', { params })
    posts.value = data.data || []
    currentPage.value = data.current_page || 1
    totalPages.value = data.last_page || 1
    // 현재 게시판 정보
    if (allBoards.value.length) {
      board.value = allBoards.value.find(b => b.slug === route.params.slug) || null
    }
  } catch {}
  loading.value = false
}

function searchPosts() { loadPosts(1) }

function formatDate(d) {
  const date = new Date(d)
  const now = new Date()
  const diff = (now - date) / 1000
  if (diff < 60) return '방금 전'
  if (diff < 3600) return Math.floor(diff / 60) + '분 전'
  if (diff < 86400) return Math.floor(diff / 3600) + '시간 전'
  if (diff < 604800) return Math.floor(diff / 86400) + '일 전'
  return date.toLocaleDateString('ko-KR')
}

onMounted(async () => {
  await loadBoards()
  await loadPosts()
})

watch(() => route.params.slug, async () => {
  searchQ.value = ''
  currentPage.value = 1
  board.value = allBoards.value.find(b => b.slug === route.params.slug) || null
  await loadPosts()
})
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>
