<template>
  <div class="max-w-[1200px] mx-auto px-4 pt-4">

    <!-- 상단 배너 -->
    <div class="bg-gradient-to-r from-red-500 to-pink-500 rounded-2xl px-6 py-5 mb-8 shadow-lg">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-white text-xl font-black mb-1">💬 커뮤니티</h1>
          <p class="text-red-100 text-sm">자유롭게 소통하는 한인 커뮤니티</p>
        </div>
        <router-link
          to="/community/write"
          class="bg-white text-red-600 px-5 py-2.5 rounded-xl text-sm font-bold hover:bg-red-50 transition flex items-center gap-1.5 shadow"
        >
          ✏️ 새 글쓰기
        </router-link>
      </div>
    </div>

    <!-- 3컬럼 레이아웃 -->
    <div class="flex gap-5">

      <!-- 좌측: 카테고리 사이드바 -->
      <aside class="hidden lg:block w-52 flex-shrink-0">
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden sticky top-20">
          <h3 class="text-sm font-bold text-gray-700 px-4 py-3 border-b border-gray-100">📂 카테고리</h3>
          <ul>
            <li>
              <router-link
                to="/community"
                class="block w-full text-left px-4 py-2.5 text-sm transition"
                :class="!currentSlug ? 'bg-red-50 text-red-600 font-semibold border-l-2 border-red-500' : 'text-gray-600 hover:bg-gray-50'"
              >
                전체
              </router-link>
            </li>
            <li v-for="cat in categories" :key="cat.slug">
              <router-link
                :to="'/community/' + cat.slug" @click="markCategoryRead(cat.slug)"
                class="block w-full text-left px-4 py-2.5 text-sm transition"
                :class="currentSlug === cat.slug ? 'bg-red-50 text-red-600 font-semibold border-l-2 border-red-500' : 'text-gray-600 hover:bg-gray-50'"
              >
                {{ cat.icon }} {{ cat.name }} <span class="text-xs text-gray-400">({{ cat.count || 0 }})</span> <span v-if="isNewCategory(cat)" class="text-[10px] bg-red-500 text-white px-1 py-0.5 rounded font-bold ml-1">N</span>
              </router-link>
            </li>
          </ul>
        </div>
      </aside>

      <!-- 중앙: 글 목록 -->
      <main class="flex-1 min-w-0">
        <!-- 모바일 카테고리 -->
        <div class="lg:hidden mb-4">
          <select v-model="mobileCategory" @change="onMobileCategory" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm">
            <option value="">전체 카테고리</option>
            <option v-for="cat in categories" :key="cat.slug" :value="cat.slug">{{ cat.icon }} {{ cat.name }} <span class="text-xs text-gray-400">({{ cat.count || 0 }})</span></option>
          </select>
        </div>

        <div v-if="loading" class="space-y-3">
          <div v-for="i in 5" :key="i" class="bg-white rounded-xl border border-gray-100 p-4 animate-pulse">
            <div class="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
            <div class="h-3 bg-gray-100 rounded w-1/2"></div>
          </div>
        </div>

        <div v-else-if="posts.length === 0" class="bg-white rounded-xl border border-gray-100 p-12 text-center">
          <p class="text-gray-400 text-sm">아직 게시글이 없습니다.</p>
        </div>

        <div v-else class="space-y-3">
          <div
            v-for="post in posts"
            :key="post.id"
            @click="goToPost(post)"
            class="bg-white rounded-xl border border-gray-100 p-4 hover:shadow-md hover:border-red-100 transition cursor-pointer"
          >
            <div class="flex items-start gap-3">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1.5">
                  <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                    :style="{ background: getCategoryColor(post.category_slug) + '20', color: getCategoryColor(post.category_slug) }">
                    {{ post.category_name || '일반' }}
                  </span>
                  <span v-if="post.is_pinned" class="text-xs text-orange-500 font-bold">📌 고정</span>
                </div>
                <h3 class="text-sm font-bold text-gray-800 truncate mb-1">{{ post.title }}</h3>
                <p class="text-xs text-gray-400 line-clamp-1 mb-2">{{ stripHtml(post.content) }}</p>
                <div class="flex items-center gap-3 text-xs text-gray-400">
                  <span>{{ post.is_anonymous ? '🎭 익명' : (post.author_name || '알수없음') }}</span>
                  <span>❤️ {{ post.likes_count || 0 }}</span>
                  <span>💬 {{ post.comments_count || 0 }}</span>
                  <span>👁 {{ post.views || 0 }}</span>
                  <span>{{ timeAgo(post.created_at) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>

      <!-- 우측: 인기글 사이드바 -->
      <aside class="hidden xl:block w-64 flex-shrink-0">
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden sticky top-20">
          <h3 class="text-sm font-bold text-gray-700 px-4 py-3 border-b border-gray-100">🔥 인기글 TOP 10</h3>
          <ul>
            <li v-for="(item, idx) in popularPosts" :key="item.id">
              <div
                @click="goToPost(item)"
                class="px-4 py-2.5 hover:bg-gray-50 cursor-pointer transition flex gap-2.5 items-start"
              >
                <span class="text-sm font-extrabold leading-none min-w-[20px]"
                  :class="idx < 3 ? 'text-red-500' : 'text-gray-400'">{{ idx + 1 }}</span>
                <div class="flex-1 min-w-0">
                  <p class="text-xs font-medium text-gray-700 truncate">{{ item.title }}</p>
                  <p class="text-[10px] text-gray-400 mt-0.5">👁 {{ item.views }} · 💬 {{ item.comments_count || 0 }}</p>
                </div>
              </div>
            </li>
          </ul>
          <div v-if="popularPosts.length === 0" class="px-4 py-6 text-center text-xs text-gray-400">
            인기글이 없습니다.
          </div>
        </div>
      </aside>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const loading = ref(true)
const posts = ref([])
const popularPosts = ref([])
const categories = ref([])
const currentSlug = ref('')
const mobileCategory = ref('')

const categoryColors = {
  free: '#3B82F6', anonymous: '#8B5CF6', life: '#10B981', food: '#F59E0B',
  parenting: '#EC4899', health: '#06B6D4', finance: '#6366F1', immigration: '#EF4444',
  travel: '#14B8A6', hobby: '#F97316', tech: '#2563EB', humor: '#A855F7',
  news: '#DC2626', seniors: '#78716C', faith: '#7C3AED'
}

function getCategoryColor(slug) {
  return categoryColors[slug] || '#6B7280'
}

function getAuthHeaders() {
  const token = localStorage.getItem('sk_token')
  return token ? { Authorization: `Bearer ${token}` } : {}
}

function stripHtml(html) {
  if (!html) return ''
  return html.replace(/<[^>]*>/g, '').substring(0, 100)
}

function timeAgo(dateStr) {
  if (!dateStr) return ''
  const now = new Date()
  const date = new Date(dateStr)
  const diff = Math.floor((now - date) / 1000)
  if (diff < 60) return '방금 전'
  if (diff < 3600) return Math.floor(diff / 60) + '분 전'
  if (diff < 86400) return Math.floor(diff / 3600) + '시간 전'
  if (diff < 172800) return '어제'
  if (diff < 604800) return Math.floor(diff / 86400) + '일 전'
  return date.toLocaleDateString('ko-KR')
}

function goToPost(post) {
  router.push(`/community/${post.category_slug || 'free'}/${post.id}`)
}

function onMobileCategory() {
  if (mobileCategory.value) {
    router.push(`/community/${mobileCategory.value}`)
  } else {
    router.push('/community')
  }
}

function isNewCategory(cat) {
  if (!cat.latest_at) return false
  const readKey = 'community_read_' + cat.slug
  const lastRead = localStorage.getItem(readKey)
  if (!lastRead) return true
  return new Date(cat.latest_at) > new Date(lastRead)
}

function markCategoryRead(slug) {
  localStorage.setItem('community_read_' + slug, new Date().toISOString())
}

async function fetchCategories() {
  try {
    const { data } = await axios.get('/api/community-v2/categories', { headers: getAuthHeaders() })
    categories.value = data.data || data || []
  } catch (e) {
    console.error('카테고리 로딩 실패', e)
  }
}

async function fetchPosts() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/community-v2/free/posts', {
      params: { sort: 'latest', per_page: 20 },
      headers: getAuthHeaders()
    })
    posts.value = data.data || data || []
  } catch (e) {
    console.error('글 목록 로딩 실패', e)
  } finally {
    loading.value = false
  }
}

async function fetchPopular() {
  try {
    const { data } = await axios.get('/api/community-v2/free/posts', {
      params: { sort: 'popular', per_page: 10 },
      headers: getAuthHeaders()
    })
    popularPosts.value = data.data || data || []
  } catch (e) {
    console.error('인기글 로딩 실패', e)
  }
}

onMounted(() => {
  fetchCategories()
  fetchPosts()
  fetchPopular()
})
</script>
