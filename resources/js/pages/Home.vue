<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900 pb-20">

    <!-- Hero Section -->
    <div class="bg-gradient-to-br from-blue-700 via-blue-600 to-indigo-700 text-white relative overflow-hidden">
      <div class="absolute inset-0 opacity-10"
        style="background-image:url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2260%22 height=%2260%22><g fill=%22%23fff%22 fill-opacity=%220.5%22><rect x=%2226%22 y=%220%22 width=%222%22 height=%2260%22/><rect x=%220%22 y=%2226%22 width=%2260%22 height=%222%22/></g></svg>')">
      </div>
      <div class="max-w-[1200px] mx-auto px-4 py-10 md:py-14 relative">
        <div class="text-center">
          <div class="inline-flex items-center gap-2 bg-white/20 rounded-full px-4 py-1.5 text-sm mb-4 backdrop-blur-sm">
            <span>🇰🇷</span>
            <span class="font-semibold">{{ locale === 'ko' ? '미국 한인 No.1 커뮤니티' : 'Korean American #1 Community' }}</span>
          </div>
          <h1 class="text-3xl md:text-5xl font-black tracking-tight">SomeKorean</h1>
          <p class="mt-3 text-blue-100 text-lg max-w-md mx-auto">
            {{ locale === 'ko' ? '미국 한인의 일상을 함께하는 올인원 플랫폼' : 'The all-in-one platform for Korean Americans' }}
          </p>

          <!-- Search Bar -->
          <div class="mt-6 max-w-lg mx-auto">
            <div class="relative">
              <input v-model="searchQuery" @keyup.enter="goSearch" type="text"
                :placeholder="locale === 'ko' ? '커뮤니티, 업소, 뉴스를 검색하세요...' : 'Search community, businesses, news...'"
                class="w-full bg-white/20 backdrop-blur-sm text-white placeholder-blue-200 rounded-xl px-5 py-3 pr-12 text-sm focus:outline-none focus:ring-2 focus:ring-white/50 border border-white/20" />
              <button @click="goSearch" class="absolute right-3 top-1/2 -translate-y-1/2 text-blue-200 hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
              </button>
            </div>
          </div>

          <!-- CTA Buttons -->
          <div v-if="!auth.isLoggedIn" class="mt-6 flex flex-col sm:flex-row gap-3 justify-center">
            <router-link to="/register"
              class="bg-white text-blue-700 font-bold px-8 py-3 rounded-xl hover:bg-blue-50 transition shadow-lg text-sm">
              {{ locale === 'ko' ? '무료로 시작하기' : 'Get Started Free' }}
            </router-link>
            <router-link to="/login"
              class="border-2 border-white/50 text-white font-semibold px-8 py-3 rounded-xl hover:bg-white/10 transition text-sm">
              {{ langStore.$t('auth.login') || (locale === 'ko' ? '로그인' : 'Login') }}
            </router-link>
          </div>
          <div v-else class="mt-6 inline-flex items-center gap-3 bg-white/15 backdrop-blur-sm rounded-2xl px-5 py-3">
            <div class="w-10 h-10 rounded-full overflow-hidden bg-white/30 flex items-center justify-center text-base font-bold flex-shrink-0">
              <img v-if="auth.user?.avatar" :src="auth.user.avatar" class="w-full h-full object-cover" @error="e => e.target.src=''" />
              <span v-else>{{ (auth.user?.name || '?')[0] }}</span>
            </div>
            <div class="text-left">
              <div class="font-bold text-sm">{{ locale === 'ko' ? '안녕하세요, ' : 'Welcome, ' }}{{ auth.user?.name }}!</div>
              <div class="text-blue-200 text-xs mt-0.5">{{ (auth.user?.points ?? 0).toLocaleString() }}P</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="max-w-[1200px] mx-auto px-4 py-6 space-y-6">

      <!-- Quick Category Grid (11 boards) -->
      <section>
        <h2 class="text-base font-bold text-gray-800 dark:text-white mb-3">
          {{ locale === 'ko' ? '주요 서비스' : 'Our Services' }}
        </h2>
        <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2.5">
          <router-link v-for="svc in services" :key="svc.path" :to="svc.path"
            class="bg-white dark:bg-gray-800 rounded-xl p-3 flex flex-col items-center gap-2 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all border border-gray-100 dark:border-gray-700 group">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl" :style="{ backgroundColor: svc.bg }">
              {{ svc.icon }}
            </div>
            <span class="text-xs font-semibold text-gray-700 dark:text-gray-300 group-hover:text-blue-600 transition text-center leading-tight">
              {{ svc.name }}
            </span>
          </router-link>
        </div>
      </section>

      <!-- Latest Posts + Jobs Side by Side -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        <!-- Latest Posts -->
        <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
          <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700">
            <h3 class="font-bold text-gray-800 dark:text-white text-sm flex items-center gap-2">
              <span class="text-base">💬</span>
              {{ locale === 'ko' ? '최신 게시글' : 'Latest Posts' }}
            </h3>
            <router-link to="/community" class="text-blue-600 text-xs font-semibold hover:text-blue-700">
              {{ locale === 'ko' ? '더보기' : 'More' }} →
            </router-link>
          </div>
          <div v-if="loadingPosts" class="text-center py-8 text-gray-400 text-sm">
            <div class="animate-spin w-5 h-5 border-2 border-gray-300 border-t-blue-500 rounded-full mx-auto mb-2"></div>
          </div>
          <div v-else>
            <router-link v-for="post in posts" :key="post.id"
              :to="`/community/${post.board_slug || 'free'}/${post.id}`"
              class="px-4 py-3 flex items-start gap-3 hover:bg-blue-50/50 dark:hover:bg-gray-700/50 transition block border-b border-gray-50 dark:border-gray-700 last:border-0">
              <div class="flex-1 min-w-0">
                <div class="text-sm font-medium text-gray-800 dark:text-white truncate">{{ post.title }}</div>
                <div class="flex items-center gap-2 mt-0.5">
                  <span class="text-xs text-gray-400">{{ post.user?.name }}</span>
                  <span class="text-gray-200 dark:text-gray-600 text-xs">|</span>
                  <span class="text-xs text-gray-400">{{ post.view_count || 0 }} {{ locale === 'ko' ? '조회' : 'views' }}</span>
                  <span class="text-xs text-gray-400">{{ post.comment_count || 0 }} {{ locale === 'ko' ? '댓글' : 'comments' }}</span>
                </div>
              </div>
            </router-link>
            <div v-if="!posts.length" class="px-4 py-8 text-center text-sm text-gray-400">
              {{ locale === 'ko' ? '게시글이 없습니다' : 'No posts yet' }}
            </div>
          </div>
        </section>

        <!-- Latest Jobs -->
        <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
          <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700">
            <h3 class="font-bold text-gray-800 dark:text-white text-sm flex items-center gap-2">
              <span class="text-base">💼</span>
              {{ locale === 'ko' ? '최신 구인구직' : 'Latest Jobs' }}
            </h3>
            <router-link to="/jobs" class="text-blue-600 text-xs font-semibold hover:text-blue-700">
              {{ locale === 'ko' ? '더보기' : 'More' }} →
            </router-link>
          </div>
          <div v-if="loadingJobs" class="text-center py-8 text-gray-400 text-sm">
            <div class="animate-spin w-5 h-5 border-2 border-gray-300 border-t-blue-500 rounded-full mx-auto mb-2"></div>
          </div>
          <div v-else>
            <router-link v-for="job in jobs" :key="job.id" :to="`/jobs/${job.id}`"
              class="px-4 py-3 hover:bg-blue-50/50 dark:hover:bg-gray-700/50 transition block border-b border-gray-50 dark:border-gray-700 last:border-0">
              <div class="flex items-center gap-2 mb-0.5">
                <span class="text-[11px] px-1.5 py-0.5 rounded-full font-semibold" :class="jobTypeColor(job.type)">
                  {{ jobTypeLabel(job.type) }}
                </span>
                <span class="text-xs text-gray-400">{{ job.city }}, {{ job.state }}</span>
              </div>
              <div class="text-sm font-medium text-gray-800 dark:text-white truncate">{{ job.title }}</div>
              <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ job.company }} · ${{ job.salary_min }}~${{ job.salary_max }}/{{ job.salary_type }}</div>
            </router-link>
            <div v-if="!jobs.length" class="px-4 py-8 text-center text-sm text-gray-400">
              {{ locale === 'ko' ? '채용공고가 없습니다' : 'No job posts yet' }}
            </div>
          </div>
        </section>
      </div>

      <!-- Marketplace -->
      <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700">
          <h3 class="font-bold text-gray-800 dark:text-white text-sm flex items-center gap-2">
            <span class="text-base">🛍️</span>
            {{ locale === 'ko' ? '중고장터' : 'Marketplace' }}
          </h3>
          <router-link to="/market" class="text-blue-600 text-xs font-semibold hover:text-blue-700">
            {{ locale === 'ko' ? '더보기' : 'More' }} →
          </router-link>
        </div>
        <div v-if="loadingMarket" class="text-center py-6 text-gray-400 text-sm">
          <div class="animate-spin w-5 h-5 border-2 border-gray-300 border-t-blue-500 rounded-full mx-auto"></div>
        </div>
        <div v-else-if="!market.length" class="px-4 py-8 text-center text-sm text-gray-400">
          {{ locale === 'ko' ? '등록된 물품이 없습니다' : 'No items yet' }}
        </div>
        <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6">
          <router-link v-for="item in market" :key="item.id" :to="`/market/${item.id}`"
            class="p-3 hover:bg-blue-50/50 dark:hover:bg-gray-700/50 transition block border-r border-b border-gray-50 dark:border-gray-700 last:border-r-0">
            <div class="w-full aspect-square rounded-lg bg-gray-100 dark:bg-gray-700 mb-2 overflow-hidden flex items-center justify-center">
              <img v-if="item.images?.[0]" :src="item.images[0]" class="w-full h-full object-cover" @error="e => e.target.src=''" />
              <span v-else class="text-2xl">📦</span>
            </div>
            <div class="text-xs font-medium text-gray-800 dark:text-gray-200 truncate">{{ item.title }}</div>
            <div class="text-blue-600 font-bold text-xs mt-0.5">${{ Number(item.price).toLocaleString() }}</div>
            <div class="text-[10px] text-gray-400 mt-0.5">{{ item.city }}, {{ item.state }}</div>
          </router-link>
        </div>
      </section>

      <!-- Upcoming Events -->
      <section v-if="events.length" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700">
          <h3 class="font-bold text-gray-800 dark:text-white text-sm flex items-center gap-2">
            <span class="text-base">🎉</span>
            {{ locale === 'ko' ? '다가오는 이벤트' : 'Upcoming Events' }}
          </h3>
          <router-link to="/events" class="text-blue-600 text-xs font-semibold hover:text-blue-700">
            {{ locale === 'ko' ? '더보기' : 'More' }} →
          </router-link>
        </div>
        <div class="divide-y divide-gray-50 dark:divide-gray-700">
          <router-link v-for="ev in events" :key="ev.id" :to="`/events/${ev.id}`"
            class="px-4 py-3 flex gap-3 hover:bg-blue-50/50 dark:hover:bg-gray-700/50 transition block">
            <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-xl flex flex-col items-center justify-center flex-shrink-0">
              <div class="text-[10px] text-blue-600 dark:text-blue-300 font-bold uppercase leading-none">
                {{ formatEventMonth(ev.start_date) }}
              </div>
              <div class="text-xl font-black text-blue-700 dark:text-blue-200 leading-tight">
                {{ formatEventDay(ev.start_date) }}
              </div>
            </div>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium text-gray-800 dark:text-white truncate">{{ ev.title }}</div>
              <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ ev.venue }} · {{ ev.city }}, {{ ev.state }}</div>
            </div>
          </router-link>
        </div>
      </section>

      <!-- Yesterday News -->
      <section v-if="yesterdayNews.items?.length" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700">
          <h3 class="font-bold text-gray-800 dark:text-white text-sm flex items-center gap-2">
            <span class="text-base">📰</span>
            {{ locale === 'ko' ? '최신 뉴스' : 'Latest News' }}
          </h3>
          <router-link to="/news" class="text-blue-600 text-xs font-semibold hover:text-blue-700">
            {{ locale === 'ko' ? '뉴스 전체' : 'All News' }} →
          </router-link>
        </div>
        <div class="divide-y divide-gray-50 dark:divide-gray-700">
          <router-link v-for="item in yesterdayNews.items" :key="item.id" :to="`/news/${item.id}`"
            class="flex items-start gap-3 px-4 py-2.5 hover:bg-blue-50/40 dark:hover:bg-gray-700/50 transition group">
            <span class="flex-shrink-0 mt-0.5 text-[10px] font-bold px-1.5 py-0.5 rounded-full whitespace-nowrap"
              :class="newsCatColor(item.category)">
              {{ item.category }}
            </span>
            <div class="flex-1 min-w-0">
              <div class="text-xs font-medium text-gray-800 dark:text-gray-200 line-clamp-1 group-hover:text-blue-600 transition leading-snug">
                {{ item.title }}
              </div>
              <div class="text-[10px] text-gray-400 mt-0.5">{{ item.source }} · {{ formatTimeAgo(item.published_at) }}</div>
            </div>
            <div v-if="item.image_url" class="flex-shrink-0 w-12 h-10 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-700">
              <img :src="item.image_url" class="w-full h-full object-cover" @error="e => e.target.src=''" />
            </div>
          </router-link>
        </div>
      </section>

      <!-- App Install CTA -->
      <section class="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-xl p-6 text-white text-center shadow-lg">
        <div class="text-3xl mb-2">📱</div>
        <h3 class="font-black text-lg">{{ locale === 'ko' ? '앱으로 더 편리하게!' : 'Use as an App!' }}</h3>
        <p class="text-blue-100 text-sm mt-1">
          {{ locale === 'ko' ? '홈화면에 추가하면 앱처럼 사용할 수 있어요' : 'Add to home screen for an app-like experience' }}
        </p>
        <div class="mt-3 text-xs text-blue-200 space-y-0.5">
          <div>{{ locale === 'ko' ? 'iOS: Safari > 공유 > 홈 화면에 추가' : 'iOS: Safari > Share > Add to Home Screen' }}</div>
          <div>{{ locale === 'ko' ? 'Android: Chrome > 메뉴 > 앱 설치' : 'Android: Chrome > Menu > Install App' }}</div>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useLangStore } from '../stores/lang'
import axios from 'axios'

const router = useRouter()
const auth = useAuthStore()
const langStore = useLangStore()
const locale = computed(() => langStore.locale)

const searchQuery = ref('')
const posts = ref([])
const jobs = ref([])
const market = ref([])
const events = ref([])
const yesterdayNews = ref({ date: '', total: 0, items: [] })

const loadingPosts = ref(false)
const loadingJobs = ref(false)
const loadingMarket = ref(false)

const services = computed(() => {
  const ko = locale.value === 'ko'
  return [
    { path: '/community', icon: '💬', name: ko ? '커뮤니티' : 'Community', bg: '#dbeafe' },
    { path: '/jobs',      icon: '💼', name: ko ? '구인구직' : 'Jobs',      bg: '#dcfce7' },
    { path: '/market',    icon: '🛍️', name: ko ? '중고장터' : 'Market',    bg: '#fef9c3' },
    { path: '/directory', icon: '🏪', name: ko ? '업소록' : 'Directory',    bg: '#f3e8ff' },
    { path: '/realestate',icon: '🏠', name: ko ? '부동산' : 'Real Estate',  bg: '#e0f2fe' },
    { path: '/news',      icon: '📰', name: ko ? '뉴스' : 'News',          bg: '#f0fdf4' },
    { path: '/events',    icon: '🎉', name: ko ? '이벤트' : 'Events',      bg: '#d1fae5' },
    { path: '/qa',        icon: '❓', name: ko ? '지식인' : 'Q&A',         bg: '#fef3c7' },
    { path: '/clubs',     icon: '👥', name: ko ? '동호회' : 'Clubs',       bg: '#f0fdf4' },
    { path: '/chat',      icon: '💬', name: ko ? '채팅' : 'Chat',          bg: '#e0f2fe' },
    { path: '/games',     icon: '🎮', name: ko ? '게임' : 'Games',         bg: '#fef3c7' },
    { path: '/elder',     icon: '💙', name: ko ? '안심서비스' : 'Elder Care', bg: '#e0e7ff' },
  ]
})

function goSearch() {
  if (searchQuery.value.trim()) {
    router.push({ path: '/search', query: { q: searchQuery.value.trim() } })
  }
}

function jobTypeLabel(type) {
  const ko = locale.value === 'ko'
  return { full_time: ko ? '풀타임' : 'Full-time', part_time: ko ? '파트타임' : 'Part-time', contract: ko ? '계약직' : 'Contract', freelance: ko ? '프리랜서' : 'Freelance' }[type] ?? type
}

function jobTypeColor(type) {
  return { full_time: 'bg-blue-100 text-blue-700', part_time: 'bg-green-100 text-green-700', contract: 'bg-orange-100 text-orange-700', freelance: 'bg-purple-100 text-purple-700' }[type] ?? 'bg-gray-100 text-gray-600'
}

function newsCatColor(cat) {
  return { '이민/비자': 'bg-purple-100 text-purple-700', '미국생활': 'bg-blue-100 text-blue-700', '정치/사회': 'bg-red-100 text-red-700', '경제': 'bg-green-100 text-green-700', '생활': 'bg-orange-100 text-orange-700', '문화': 'bg-pink-100 text-pink-700', '스포츠': 'bg-yellow-100 text-yellow-700' }[cat] ?? 'bg-gray-100 text-gray-600'
}

function formatTimeAgo(dt) {
  if (!dt) return ''
  const diff = Date.now() - new Date(dt).getTime()
  const h = Math.floor(diff / 3600000)
  if (h < 1) return locale.value === 'ko' ? '방금' : 'just now'
  if (h < 24) return locale.value === 'ko' ? `${h}시간 전` : `${h}h ago`
  const d = Math.floor(h / 24)
  return locale.value === 'ko' ? `${d}일 전` : `${d}d ago`
}

function formatEventMonth(dt) {
  if (!dt) return '?'
  return new Date(dt).toLocaleDateString(locale.value === 'ko' ? 'ko-KR' : 'en-US', { month: 'short' })
}

function formatEventDay(dt) {
  if (!dt) return '?'
  return new Date(dt).getDate()
}

async function loadAll() {
  loadingPosts.value = true
  loadingJobs.value = true
  loadingMarket.value = true

  const [postsRes, jobsRes, marketRes, eventsRes, newsRes] = await Promise.allSettled([
    axios.get('/api/posts?per_page=6'),
    axios.get('/api/jobs?per_page=6'),
    axios.get('/api/market?per_page=6'),
    axios.get('/api/events?per_page=3'),
    axios.get('/api/news/yesterday-summary'),
  ])

  // API returns { success, data: { current_page, data: [...items] } }
  if (postsRes.status === 'fulfilled') posts.value = postsRes.value.data?.data?.data?.slice(0, 6) ?? []
  if (jobsRes.status === 'fulfilled') jobs.value = jobsRes.value.data?.data?.data?.slice(0, 6) ?? []
  if (marketRes.status === 'fulfilled') market.value = marketRes.value.data?.data?.data?.slice(0, 6) ?? []
  if (eventsRes.status === 'fulfilled') events.value = eventsRes.value.data?.data?.data?.slice(0, 3) ?? []
  if (newsRes.status === 'fulfilled') {
    const newsData = newsRes.value.data?.data?.data ?? newsRes.value.data?.data ?? []
    yesterdayNews.value = { items: Array.isArray(newsData) ? newsData.slice(0, 5) : [] }
  }

  loadingPosts.value = false
  loadingJobs.value = false
  loadingMarket.value = false
}

onMounted(loadAll)
</script>
