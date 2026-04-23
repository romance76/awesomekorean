<template>
<div class="min-h-screen bg-gray-50">
  <!-- 샘플 비교 헤더 -->
  <div class="bg-emerald-600 text-white text-xs font-bold px-4 py-2 flex items-center justify-between">
    <span>✨ 통합 샘플 — 3개 장점 합침 (중복 검색/메뉴 제거, 이미지·텍스트 구분)</span>
    <div class="flex gap-2">
      <RouterLink to="/home-sample/portal" class="underline">포털형</RouterLink>
      <RouterLink to="/home-sample/magazine" class="underline">매거진형</RouterLink>
      <RouterLink to="/home-sample/feed" class="underline">피드형</RouterLink>
      <RouterLink to="/" class="bg-emerald-900 px-2 py-0.5 rounded">원본 홈</RouterLink>
    </div>
  </div>

  <!-- ═════ 1. 히어로 슬라이드 (검색창/메뉴 아이콘 없음) ═════ -->
  <section class="relative overflow-hidden" style="height: 220px"
    @mouseenter="pauseHero" @mouseleave="resumeHero">
    <Transition name="slide">
      <!-- 기본 홈 슬라이드 -->
      <div v-if="heroIdx === 0" class="absolute inset-0 bg-gradient-to-r from-amber-400 via-orange-500 to-rose-500 flex items-center justify-center">
        <div class="text-center text-white px-6">
          <div class="text-[10px] font-bold opacity-90 tracking-widest mb-1">AWESOME KOREAN</div>
          <h1 class="text-3xl md:text-5xl font-black leading-tight drop-shadow">미국 한인의 일상,<br class="md:hidden"/> 한 곳에서</h1>
          <p class="text-xs md:text-sm opacity-90 mt-2">커뮤니티 · 장터 · 업소 · 이벤트 — 모든 것</p>
        </div>
      </div>
      <!-- 이벤트 배너 -->
      <div v-else-if="heroBanners[heroIdx - 1]" :key="heroIdx" @click="clickHeroBanner(heroBanners[heroIdx - 1])"
        class="absolute inset-0 cursor-pointer"
        :style="{ background: heroBanners[heroIdx - 1].bg_color ? `linear-gradient(135deg, ${heroBanners[heroIdx - 1].bg_color}, ${heroBanners[heroIdx - 1].bg_color}cc)` : '' }">
        <img v-if="heroBanners[heroIdx - 1].image_url" :src="heroBanners[heroIdx - 1].image_url" class="absolute inset-0 w-full h-full object-cover" />
        <div v-else class="absolute inset-0 flex items-center justify-center px-6 text-center text-white">
          <div>
            <div class="text-3xl md:text-4xl font-black drop-shadow">{{ heroBanners[heroIdx - 1].title }}</div>
            <div v-if="heroBanners[heroIdx - 1].subtitle" class="text-sm mt-2 opacity-90">{{ heroBanners[heroIdx - 1].subtitle }}</div>
          </div>
        </div>
      </div>
    </Transition>
    <!-- 슬라이드 인디케이터 -->
    <div v-if="totalSlides > 1" class="absolute bottom-3 left-0 right-0 flex justify-center gap-1.5">
      <button v-for="i in totalSlides" :key="i" @click="heroIdx = i - 1"
        class="w-2 h-2 rounded-full transition" :class="heroIdx === i - 1 ? 'bg-white w-6' : 'bg-white/50'"></button>
    </div>
  </section>

  <!-- ═════ 2. 카테고리 큰 컬러 블록 (5개 핵심만) ═════ -->
  <section class="max-w-6xl mx-auto px-4 -mt-6 relative z-10 mb-8">
    <div class="grid grid-cols-5 gap-2 md:gap-3">
      <RouterLink v-for="cat in mainCategories" :key="cat.to" :to="cat.to"
        class="flex flex-col items-center justify-center aspect-square md:aspect-[3/2] rounded-2xl text-white shadow-lg hover:scale-105 transition"
        :style="{ background: cat.bg }">
        <span class="text-2xl md:text-4xl">{{ cat.icon }}</span>
        <span class="text-[10px] md:text-sm font-black mt-1">{{ cat.name }}</span>
      </RouterLink>
    </div>
  </section>

  <!-- ═════ 3. 메인 컨텐츠 — 통합 리스트 + 사이드바 ═════ -->
  <section class="max-w-6xl mx-auto px-4 pb-10">
    <div class="grid grid-cols-12 gap-4">
      <!-- 메인 피드 (통합 리스트) -->
      <main class="col-span-12 lg:col-span-8 space-y-4">
        <!-- 섹션 타이틀 + 탭 -->
        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
          <div class="flex items-center justify-between px-5 pt-4">
            <h2 class="font-black text-gray-800">🔥 지금 이 순간</h2>
            <div class="flex gap-1 text-[11px]">
              <button v-for="f in filters" :key="f.key" @click="activeFilter = f.key"
                class="px-3 py-1 rounded-full font-bold transition"
                :class="activeFilter === f.key ? 'bg-amber-400 text-amber-900' : 'text-gray-400 hover:bg-gray-50'">
                {{ f.label }}
              </button>
            </div>
          </div>
          <!-- 심플 리스트 (제목 위주, 이미지는 섬네일 작게) -->
          <div class="divide-y divide-gray-50">
            <RouterLink v-for="item in textList" :key="item.id" :to="item.to"
              class="flex items-center gap-3 px-5 py-3 hover:bg-amber-50/40 transition">
              <span class="text-[10px] font-black text-white px-2 py-0.5 rounded flex-shrink-0" :style="{ background: item.color }">
                {{ item.badge }}
              </span>
              <div class="flex-1 min-w-0">
                <div class="text-sm font-semibold text-gray-800 truncate">{{ item.title }}</div>
                <div class="text-[11px] text-gray-400 truncate">
                  {{ item.author || '익명' }} · {{ item.time }}
                  <span v-if="item.comments" class="ml-1">· 💬 {{ item.comments }}</span>
                  <span v-if="item.likes" class="ml-1">· ❤️ {{ item.likes }}</span>
                </div>
              </div>
              <span v-if="item.price" class="text-sm font-black text-green-600 flex-shrink-0">${{ item.price }}</span>
              <img v-else-if="item.image" :src="item.image" class="w-12 h-12 rounded-lg object-cover flex-shrink-0 border" @error="($event.target.style.display='none')" />
            </RouterLink>
            <div v-if="!textList.length" class="text-center py-10 text-sm text-gray-400">게시글이 없습니다</div>
          </div>
        </div>

        <!-- 이미지 카드 섹션 (장터/업소/레시피) — 매거진 카드 -->
        <div>
          <div class="flex items-center justify-between mb-3 px-1">
            <h2 class="font-black text-gray-800">🛒 최신 장터 · 업소</h2>
            <RouterLink to="/market" class="text-xs text-amber-600 font-bold">더보기 →</RouterLink>
          </div>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <RouterLink v-for="card in imageCards" :key="card.id" :to="card.to"
              class="block bg-white rounded-xl overflow-hidden shadow-sm border border-gray-100 hover:shadow-lg transition group">
              <div class="aspect-square bg-gray-100 relative overflow-hidden">
                <img v-if="card.image" :src="card.image" class="w-full h-full object-cover group-hover:scale-105 transition-transform" @error="($event.target.style.display='none')" />
                <div v-else class="absolute inset-0 flex items-center justify-center text-4xl opacity-30">{{ card.emoji }}</div>
                <span class="absolute top-2 left-2 text-[9px] font-black px-2 py-0.5 rounded-full text-white" :style="{ background: card.color }">{{ card.badge }}</span>
                <span v-if="card.price" class="absolute bottom-2 right-2 bg-black/60 text-white text-[11px] font-black px-2 py-0.5 rounded-full">${{ card.price }}</span>
              </div>
              <div class="p-2.5">
                <div class="text-xs font-bold text-gray-800 truncate">{{ card.title }}</div>
                <div class="text-[10px] text-gray-400 truncate mt-0.5">{{ card.sub }}</div>
              </div>
            </RouterLink>
          </div>
        </div>
      </main>

      <!-- 사이드바 -->
      <aside class="col-span-12 lg:col-span-4 space-y-3">
        <!-- 트렌딩 태그 -->
        <div class="bg-white rounded-2xl border border-gray-100 p-4">
          <div class="font-black text-sm text-gray-800 mb-2">🔥 지금 뜨는 키워드</div>
          <div class="flex flex-wrap gap-1.5">
            <span v-for="(t, i) in trendingTags" :key="t"
              @click="router.push({path:'/search',query:{q:t}})"
              class="px-2.5 py-1 rounded-full text-[11px] font-bold cursor-pointer transition"
              :class="i < 3 ? 'bg-red-100 text-red-600 hover:bg-red-200' : 'bg-amber-50 text-amber-700 hover:bg-amber-100'">
              #{{ t }}<span v-if="i<3" class="ml-0.5">🔥</span>
            </span>
          </div>
        </div>

        <!-- 날씨 + 환율 (미니) -->
        <div class="grid grid-cols-2 gap-3">
          <div class="bg-gradient-to-br from-blue-400 to-blue-600 rounded-2xl p-3 text-white">
            <div class="text-[10px] opacity-80">애틀랜타</div>
            <div class="text-xl font-black mt-1">72°F</div>
            <div class="text-[10px] opacity-80">☀️ 맑음</div>
          </div>
          <div class="bg-white rounded-2xl border p-3">
            <div class="text-[10px] text-gray-400">USD/KRW</div>
            <div class="text-lg font-black text-gray-800 mt-1">1,386<span class="text-[10px] font-normal">원</span></div>
            <div class="text-[10px] text-red-500">▲ 2.4</div>
          </div>
        </div>

        <!-- 광고 슬롯 -->
        <AdSlot page="home" position="right" :maxSlots="2" />

        <!-- 인기 게시판 바로가기 -->
        <div class="bg-white rounded-2xl border border-gray-100 p-4">
          <div class="font-black text-sm text-gray-800 mb-2">📋 인기 게시판</div>
          <div class="space-y-1">
            <RouterLink v-for="b in boards" :key="b.slug" :to="`/community/${b.slug}`"
              class="flex items-center justify-between py-1.5 px-2 rounded-lg text-xs text-gray-600 hover:bg-amber-50 hover:text-amber-700 transition">
              <span class="flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full" :style="{ background: b.color }"></span>
                {{ b.name }}
              </span>
              <span class="text-[10px] text-gray-300">›</span>
            </RouterLink>
          </div>
        </div>

        <!-- 비로그인 유저 CTA -->
        <div v-if="!auth.isLoggedIn" class="bg-gradient-to-br from-amber-500 to-orange-600 text-white rounded-2xl p-4">
          <div class="text-sm font-black">AwesomeKorean 에 참여하세요</div>
          <div class="text-[11px] opacity-90 mt-1">포인트 받고 커뮤니티 활동</div>
          <RouterLink to="/register" class="mt-3 block text-center bg-white text-amber-700 font-bold py-1.5 rounded-full text-sm">무료 가입</RouterLink>
        </div>
      </aside>
    </div>
  </section>
</div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import AdSlot from '../../components/AdSlot.vue'
import axios from 'axios'

const router = useRouter()
const auth = useAuthStore()
const posts = ref([])
const jobs = ref([])
const market = ref([])
const heroBanners = ref([])
const heroIdx = ref(0)
let heroInterval = null
const activeFilter = ref('all')

const totalSlides = computed(() => 1 + heroBanners.value.length)

function clickHeroBanner(b) {
  if (b.link_type === 'event' && b.event_id) router.push('/events?open=' + b.event_id)
  else if (b.link_type === 'page' && b.link_page) router.push(b.link_page)
  else if (b.link_type === 'url' && b.link_url) window.open(b.link_url, '_blank')
}

function startHeroSlide() {
  if (totalSlides.value <= 1) return
  heroInterval = setInterval(() => {
    heroIdx.value = (heroIdx.value + 1) % totalSlides.value
  }, 7000)
}
function pauseHero() { if (heroInterval) { clearInterval(heroInterval); heroInterval = null } }
function resumeHero() { if (!heroInterval && totalSlides.value > 1) startHeroSlide() }
onUnmounted(() => { if (heroInterval) clearInterval(heroInterval) })

const mainCategories = [
  { name: '커뮤니티', icon: '💬', to: '/community', bg: 'linear-gradient(135deg,#f59e0b,#f97316)' },
  { name: '중고장터', icon: '🛒', to: '/market', bg: 'linear-gradient(135deg,#3b82f6,#6366f1)' },
  { name: '업소록', icon: '🏪', to: '/directory', bg: 'linear-gradient(135deg,#10b981,#059669)' },
  { name: '부동산', icon: '🏠', to: '/realestate', bg: 'linear-gradient(135deg,#8b5cf6,#6366f1)' },
  { name: '이벤트', icon: '🎉', to: '/events', bg: 'linear-gradient(135deg,#ec4899,#f43f5e)' },
]

const filters = [
  { key: 'all',       label: '전체' },
  { key: 'community', label: '커뮤니티' },
  { key: 'jobs',      label: '구인' },
  { key: 'market',    label: '장터' },
]

const trendingTags = ['이민','영주권','맛집','구인','중고차','부동산','세금','학교','병원','김치']

const boards = [
  { slug: 'free', name: '자유게시판', color: '#f59e0b' },
  { slug: 'immigration', name: '이민생활', color: '#10b981' },
  { slug: 'food', name: '맛집후기', color: '#ef4444' },
  { slug: 'tips', name: '생활꿀팁', color: '#3b82f6' },
  { slug: 'education', name: '자녀교육', color: '#8b5cf6' },
]

function fmtTime(dt) {
  if (!dt) return ''
  const diff = Math.floor((Date.now() - new Date(dt).getTime()) / 1000)
  if (diff < 60) return '방금'
  if (diff < 3600) return Math.floor(diff/60) + '분 전'
  if (diff < 86400) return Math.floor(diff/3600) + '시간 전'
  return Math.floor(diff/86400) + '일 전'
}

// 텍스트 위주 통합 리스트 (커뮤니티·구인·Q&A)
const textList = computed(() => {
  const items = []
  posts.value.forEach(p => items.push({
    id: 'p' + p.id, type: 'community',
    to: `/community/${p.board?.slug || 'free'}/${p.id}`,
    badge: p.board?.name || '커뮤니티',
    color: '#f59e0b',
    title: p.title,
    author: p.user?.name, time: fmtTime(p.created_at),
    likes: p.likes_count, comments: p.comments_count,
    image: p.thumbnail || null,
  }))
  jobs.value.forEach(j => items.push({
    id: 'j' + j.id, type: 'jobs',
    to: `/jobs/${j.id}`,
    badge: '구인',
    color: '#10b981',
    title: j.title,
    author: j.company || j.user?.name, time: fmtTime(j.created_at),
  }))
  market.value.forEach(m => items.push({
    id: 'm' + m.id, type: 'market',
    to: `/market/${m.id}`,
    badge: '장터',
    color: '#3b82f6',
    title: m.title,
    author: m.user?.name, time: fmtTime(m.created_at),
    likes: m.likes_count, comments: m.comments_count,
    price: m.price,
  }))
  const sorted = items.sort((a, b) => (b.id > a.id ? 1 : -1)).slice(0, 10)
  if (activeFilter.value === 'all') return sorted
  return sorted.filter(i => i.type === activeFilter.value)
})

// 이미지 중심 카드 (장터 + 이미지 있는 콘텐츠만)
const imageCards = computed(() => {
  return market.value
    .filter(m => m.images?.[0] || m.image)
    .slice(0, 8)
    .map(m => ({
      id: m.id,
      to: `/market/${m.id}`,
      image: m.images?.[0] || m.image,
      title: m.title,
      sub: m.user?.name || m.category || '',
      price: m.price,
      badge: '장터',
      color: '#3b82f6',
      emoji: '🛒',
    }))
})

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/hero-banners')
    heroBanners.value = data.data || []
    startHeroSlide()
  } catch {}
  const [p, j, m] = await Promise.allSettled([
    axios.get('/api/posts?per_page=10'),
    axios.get('/api/jobs?per_page=5'),
    axios.get('/api/market?per_page=10'),
  ])
  if (p.status === 'fulfilled') posts.value = p.value.data?.data?.data || []
  if (j.status === 'fulfilled') jobs.value = j.value.data?.data?.data || []
  if (m.status === 'fulfilled') market.value = m.value.data?.data?.data || []
})
</script>

<style scoped>
.slide-enter-active, .slide-leave-active { transition: opacity 0.6s ease; }
.slide-enter-from, .slide-leave-to { opacity: 0; }
</style>
