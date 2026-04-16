<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Hero: 카카오 스타일 노란 그라디언트 -->
    <div class="bg-gradient-to-r from-amber-400 via-yellow-400 to-orange-400">
      <div class="max-w-7xl mx-auto px-4 py-8 text-center">
        <div class="inline-flex items-center gap-2 bg-white/30 rounded-full px-4 py-1 text-sm font-bold text-amber-900 mb-3">
          🇰🇷 미국 한인 No.1 커뮤니티
        </div>
        <h1 class="text-3xl md:text-4xl font-black text-amber-900">SomeKorean</h1>
        <p class="text-amber-800 mt-2">한인들의 일상을 함께하는 올인원 플랫폼</p>
        <div class="mt-5 max-w-xl mx-auto bg-white/80 rounded-xl flex items-center px-4 py-2.5 shadow-lg">
          <svg class="w-5 h-5 text-amber-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <input v-model="searchQ" @keyup.enter="goSearch" type="text" placeholder="업소, 구인, 장터를 검색하세요..."
            class="flex-1 bg-transparent text-sm outline-none text-gray-700 placeholder-gray-400" />
        </div>
      </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 py-5">
      <!-- 히어로 배너 슬라이드 -->
      <div v-if="heroBanners.length" class="mb-5 relative rounded-2xl overflow-hidden shadow-lg" style="height: 120px;">
        <transition-group name="hero-slide" tag="div" class="relative w-full h-full">
          <div v-for="(b, i) in heroBanners" :key="b.id" v-show="heroIdx === i"
            class="absolute inset-0 flex items-center justify-center cursor-pointer transition-all duration-500"
            :style="{ backgroundColor: b.bg_color || '#F5A623' }"
            @click="clickHeroBanner(b)">
            <img v-if="b.image_url" :src="b.image_url" class="absolute inset-0 w-full h-full object-cover" />
            <div class="relative z-10 text-center px-6">
              <div class="text-2xl md:text-3xl font-black" :style="{ color: b.text_color || '#fff' }">{{ b.title }}</div>
              <div v-if="b.subtitle" class="text-sm mt-1 opacity-90" :style="{ color: b.text_color || '#fff' }">{{ b.subtitle }}</div>
            </div>
          </div>
        </transition-group>
        <!-- 인디케이터 -->
        <div v-if="heroBanners.length > 1" class="absolute bottom-2 left-1/2 -translate-x-1/2 flex gap-1.5 z-20">
          <button v-for="(_, i) in heroBanners" :key="i" @click="heroIdx = i"
            class="w-2 h-2 rounded-full transition-all"
            :class="heroIdx === i ? 'bg-white scale-125' : 'bg-white/50'"></button>
        </div>
      </div>

      <!-- 퀵 메뉴: 카카오 카드 스타일 -->
      <div class="grid grid-cols-4 sm:grid-cols-6 lg:grid-cols-12 gap-2 mb-5">
        <RouterLink v-for="svc in services" :key="svc.to" :to="svc.to"
          class="bg-white rounded-xl p-3 text-center shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all cursor-pointer group border border-gray-100">
          <div class="text-2xl mb-1">{{ svc.icon }}</div>
          <div class="text-[11px] font-bold text-gray-600 group-hover:text-amber-700">{{ svc.name }}</div>
        </RouterLink>
      </div>

      <!-- 3컬럼 레이아웃: 네이버 카페 스타일 -->
      <div class="grid grid-cols-12 gap-4">

        <!-- 왼쪽 사이드바 -->
        <div class="col-span-12 lg:col-span-3 space-y-3 hidden lg:block">
          <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="px-4 py-3 border-b font-bold text-sm text-amber-900">📋 게시판</div>
            <div class="py-1">
              <RouterLink v-for="b in boards" :key="b.slug" :to="`/community/${b.slug}`"
                class="block px-4 py-2 text-sm text-gray-600 hover:bg-amber-50 hover:text-amber-700 transition">
                {{ b.name }}
              </RouterLink>
            </div>
          </div>
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <div class="font-bold text-sm text-amber-900 mb-3">⚡ 서비스</div>
            <div class="space-y-1.5">
              <RouterLink to="/jobs" class="block text-sm text-gray-600 hover:text-amber-700">💼 구인구직</RouterLink>
              <RouterLink to="/market" class="block text-sm text-gray-600 hover:text-amber-700">🛒 중고장터</RouterLink>
              <RouterLink to="/directory" class="block text-sm text-gray-600 hover:text-amber-700">🏪 업소록</RouterLink>
              <RouterLink to="/realestate" class="block text-sm text-gray-600 hover:text-amber-700">🏠 부동산</RouterLink>
              <RouterLink to="/events" class="block text-sm text-gray-600 hover:text-amber-700">🎉 이벤트</RouterLink>
              <RouterLink to="/games" class="block text-sm text-gray-600 hover:text-amber-700">🎮 게임</RouterLink>
            </div>
          </div>
          <!-- 왼쪽 광고 -->
          <AdSlot page="home" position="left" :maxSlots="3" />
        </div>

        <!-- 메인 콘텐츠 -->
        <div class="col-span-12 lg:col-span-6 space-y-4">
          <!-- 최신 게시글: 네이버 카페 테이블 -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 bg-amber-50 border-b">
              <h3 class="font-bold text-sm text-amber-900">💬 최신 게시글</h3>
              <RouterLink to="/community" class="text-xs text-amber-600 font-semibold hover:text-amber-800">더보기 →</RouterLink>
            </div>
            <div v-if="loading" class="py-8 text-center text-gray-400 text-sm">로딩중...</div>
            <div v-else>
              <RouterLink v-for="post in posts" :key="post.id" :to="`/community/${post.board?.slug || 'free'}/${post.id}`"
                class="flex items-center justify-between px-4 py-2.5 hover:bg-amber-50/50 transition border-b border-gray-50 last:border-0">
                <div class="flex items-center gap-2 flex-1 min-w-0">
                  <span class="text-[11px] bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-semibold flex-shrink-0">
                    {{ post.board?.name || '자유' }}
                  </span>
                  <span class="text-sm text-gray-800 truncate">{{ post.title }}</span>
                  <span v-if="post.comment_count" class="text-xs text-amber-500 font-bold flex-shrink-0">[{{ post.comment_count }}]</span>
                </div>
                <div class="flex items-center gap-3 flex-shrink-0 ml-2">
                  <button @click.prevent.stop="openPopup(post.user?.id)" class="text-xs text-gray-400 hover:text-amber-700">{{ post.user?.name }}</button>
                  <span class="text-xs text-gray-300">{{ post.view_count }}</span>
                </div>
              </RouterLink>
              <div v-if="!posts.length" class="py-8 text-center text-sm text-gray-400">게시글이 없습니다</div>
            </div>
          </div>

          <!-- 최신 구인 -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 bg-amber-50 border-b">
              <h3 class="font-bold text-sm text-amber-900">💼 최신 구인구직</h3>
              <RouterLink to="/jobs" class="text-xs text-amber-600 font-semibold hover:text-amber-800">더보기 →</RouterLink>
            </div>
            <div v-for="job in jobs" :key="job.id">
              <RouterLink :to="`/jobs/${job.id}`" class="block px-4 py-3 hover:bg-amber-50/50 transition border-b border-gray-50 last:border-0">
                <div class="flex items-center gap-2 mb-0.5">
                  <span class="text-[10px] px-2 py-0.5 rounded-full font-bold"
                    :class="job.type==='full'?'bg-blue-100 text-blue-700':job.type==='part'?'bg-green-100 text-green-700':'bg-orange-100 text-orange-700'">
                    {{ {full:'풀타임',part:'파트',contract:'계약직'}[job.type] || job.type }}
                  </span>
                  <span class="text-xs text-gray-400">{{ job.city }}, {{ job.state }}</span>
                </div>
                <div class="text-sm font-medium text-gray-800">{{ job.title }}</div>
                <div class="text-xs text-gray-500 mt-0.5">{{ job.company }} · ${{ job.salary_min }}~${{ job.salary_max }}/{{ job.salary_type }}</div>
              </RouterLink>
            </div>
            <div v-if="!jobs.length" class="py-6 text-center text-sm text-gray-400">채용공고가 없습니다</div>
          </div>
        </div>

        <!-- 오른쪽 사이드바 -->
        <div class="col-span-12 lg:col-span-3 space-y-3 hidden lg:block">
          <!-- 중고장터 -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 bg-amber-50 border-b">
              <h3 class="font-bold text-sm text-amber-900">🛍️ 중고장터</h3>
              <RouterLink to="/market" class="text-xs text-amber-600 font-semibold">더보기 →</RouterLink>
            </div>
            <div class="grid grid-cols-2 gap-1 p-2">
              <RouterLink v-for="item in market" :key="item.id" :to="`/market/${item.id}`"
                class="bg-gray-50 rounded-lg p-2 hover:bg-amber-50 transition">
                <div class="aspect-square bg-gray-200 rounded flex items-center justify-center text-2xl mb-1">📦</div>
                <div class="text-[11px] text-gray-700 truncate">{{ item.title }}</div>
                <div class="text-xs text-amber-600 font-bold">${{ Number(item.price).toLocaleString() }}</div>
              </RouterLink>
            </div>
            <div v-if="!market.length" class="py-4 text-center text-xs text-gray-400">등록된 물품이 없습니다</div>
          </div>

          <!-- 인기 키워드 -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <h4 class="font-bold text-sm text-amber-900 mb-3">🔥 인기 키워드</h4>
            <div class="flex flex-wrap gap-1.5">
              <span v-for="tag in ['이민','영주권','맛집','구인','중고차','부동산','세금','학교']" :key="tag"
                @click="router.push({path:'/search',query:{q:tag}})"
                class="bg-amber-50 text-amber-700 px-2 py-1 rounded-lg text-xs font-medium cursor-pointer hover:bg-amber-100">#{{ tag }}</span>
            </div>
          </div>
          <!-- 오른쪽 광고 -->
          <AdSlot page="home" position="right" :maxSlots="2" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import AdSlot from '../components/AdSlot.vue'
import axios from 'axios'

const router = useRouter()
const auth = useAuthStore()
const searchQ = ref('')

// 히어로 배너
const heroBanners = ref([])
const heroIdx = ref(0)
let heroInterval = null

function clickHeroBanner(b) {
  if (b.link_type === 'event' && b.event_id) router.push('/events/' + b.event_id)
  else if (b.link_type === 'page' && b.link_page) router.push(b.link_page)
  else if (b.link_type === 'url' && b.link_url) window.open(b.link_url, '_blank')
}

function startHeroSlide() {
  if (heroBanners.value.length <= 1) return
  heroInterval = setInterval(() => {
    heroIdx.value = (heroIdx.value + 1) % heroBanners.value.length
  }, 4000)
}

onUnmounted(() => { if (heroInterval) clearInterval(heroInterval) })
const posts = ref([])
const jobs = ref([])
const market = ref([])
const loading = ref(true)

const services = [
  { to: '/community', icon: '💬', name: '커뮤니티' },
  { to: '/jobs', icon: '💼', name: '구인구직' },
  { to: '/market', icon: '🛒', name: '중고장터' },
  { to: '/directory', icon: '🏪', name: '업소록' },
  { to: '/realestate', icon: '🏠', name: '부동산' },
  { to: '/news', icon: '📰', name: '뉴스' },
  { to: '/events', icon: '🎉', name: '이벤트' },
  { to: '/qa', icon: '❓', name: 'Q&A' },
  { to: '/clubs', icon: '👥', name: '동호회' },
  { to: '/recipes', icon: '🍳', name: '레시피' },
  { to: '/games', icon: '🎮', name: '게임' },
  { to: '/elder', icon: '💙', name: '안심서비스' },
]

const boards = [
  { slug: 'free', name: '자유게시판' },
  { slug: 'info', name: '정보공유' },
  { slug: 'tips', name: '생활꿀팁' },
  { slug: 'food', name: '맛집후기' },
  { slug: 'immigration', name: '이민생활' },
  { slug: 'health', name: '건강정보' },
  { slug: 'education', name: '자녀교육' },
]

function goSearch() {
  if (searchQ.value.trim()) router.push({ path: '/search', query: { q: searchQ.value.trim() } })
}

function openPopup(userId) {
  if (userId && window.openUserPopup) window.openUserPopup(userId)
}

onMounted(async () => {
  loading.value = true
  // 히어로 배너 로드
  try {
    const { data } = await axios.get('/api/hero-banners')
    heroBanners.value = data.data || []
    startHeroSlide()
  } catch {}

  const [postsRes, jobsRes, marketRes] = await Promise.allSettled([
    axios.get('/api/posts?per_page=8'),
    axios.get('/api/jobs?per_page=5'),
    axios.get('/api/market?per_page=4'),
  ])
  // API returns { success, data: { data: [...] } }
  if (postsRes.status === 'fulfilled') posts.value = postsRes.value.data?.data?.data?.slice(0, 8) || []
  if (jobsRes.status === 'fulfilled') jobs.value = jobsRes.value.data?.data?.data?.slice(0, 5) || []
  if (marketRes.status === 'fulfilled') market.value = marketRes.value.data?.data?.data?.slice(0, 4) || []
  loading.value = false
})
</script>
<style scoped>
.hero-slide-enter-active, .hero-slide-leave-active { transition: opacity 0.5s ease; }
.hero-slide-enter-from, .hero-slide-leave-to { opacity: 0; }
</style>
