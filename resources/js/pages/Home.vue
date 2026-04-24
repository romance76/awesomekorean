<template>
<div class="min-h-screen bg-gray-100">
  <!-- ═════ 1. 히어로 슬라이드 (모바일 180px / 데스크톱 280px) ═════ -->
  <section class="relative overflow-hidden h-[180px] md:h-[280px]"
    @mouseenter="pauseHero" @mouseleave="resumeHero">
    <Transition name="hero">
      <div v-if="heroIdx === 0" class="absolute inset-0 bg-gradient-to-br from-amber-400 via-orange-500 to-rose-500 flex items-center justify-center">
        <div class="text-center text-white px-6">
          <div class="inline-block text-[10px] font-bold bg-white/20 backdrop-blur px-3 py-1 rounded-full mb-3 tracking-widest">🇰🇷 미국 한인 NO.1 커뮤니티</div>
          <h1 class="text-4xl md:text-6xl font-black leading-tight drop-shadow">AwesomeKorean</h1>
          <p class="text-sm md:text-base opacity-95 mt-2">한인들의 일상을 함께하는 올인원 플랫폼</p>
        </div>
      </div>
      <div v-else-if="heroBanners[heroIdx - 1]" :key="heroIdx" @click="clickHeroBanner(heroBanners[heroIdx - 1])"
        class="absolute inset-0 cursor-pointer"
        :style="{ background: heroBanners[heroIdx - 1].bg_color ? `linear-gradient(135deg, ${heroBanners[heroIdx - 1].bg_color}, ${heroBanners[heroIdx - 1].bg_color}cc)` : '' }">
        <img v-if="heroBanners[heroIdx - 1].image_url" :src="heroBanners[heroIdx - 1].image_url" class="absolute inset-0 w-full h-full object-cover" />
        <div v-else class="absolute inset-0 flex items-center justify-center px-6 text-center text-white">
          <div>
            <div class="text-3xl md:text-5xl font-black drop-shadow">{{ heroBanners[heroIdx - 1].title }}</div>
            <div v-if="heroBanners[heroIdx - 1].subtitle" class="text-sm md:text-base mt-3 opacity-95">{{ heroBanners[heroIdx - 1].subtitle }}</div>
          </div>
        </div>
      </div>
    </Transition>
    <div v-if="totalSlides > 1" class="absolute bottom-4 left-0 right-0 flex justify-center gap-1.5">
      <button v-for="i in totalSlides" :key="i" @click="heroIdx = i - 1"
        class="h-2 rounded-full transition-all"
        :class="heroIdx === i - 1 ? 'bg-white w-8' : 'bg-white/50 w-2'"></button>
    </div>
  </section>

  <!-- ═════ 2-M. 모바일 전용: 카테고리 카드 그리드 + 배너 1 ═════ -->
  <div class="lg:hidden max-w-7xl mx-auto px-3 pt-3">
    <!-- 컬러 카테고리 그리드 (3열 × 3행) -->
    <div class="grid grid-cols-3 gap-2 mb-3">
      <RouterLink v-for="c in mobileCategories" :key="c.to" :to="c.to"
        class="relative overflow-hidden rounded-xl shadow-sm p-3 flex flex-col items-center justify-center aspect-[5/4] text-white hover:scale-[1.02] transition-transform"
        :style="{ background: c.gradient }">
        <span class="text-2xl drop-shadow">{{ c.icon }}</span>
        <span class="text-[11px] font-black mt-1 drop-shadow">{{ c.name }}</span>
        <span class="text-[9px] opacity-90 mt-0.5 drop-shadow">{{ c.desc }}</span>
      </RouterLink>
    </div>

    <!-- 모바일 배너 1 (프리미엄 — 히어로 직하) -->
    <MobileBanner page="home" slot="premium" class="mb-3" />
  </div>

  <!-- ═════ 2. 메인 3-column 포털 레이아웃 ═════ -->
  <div class="max-w-7xl mx-auto px-3 py-4 grid grid-cols-12 gap-3">
    <!-- 왼쪽 사이드바 (데스크톱 전용) -->
    <aside class="col-span-12 lg:col-span-3 space-y-3 hidden lg:block">
      <!-- 좌측 광고 배너 -->
      <AdSlot page="home" position="left" :maxSlots="2" />

      <!-- 인기 게시판 -->
      <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
        <div class="px-4 py-2.5 border-b bg-amber-50 flex items-center gap-1.5">
          <span class="text-sm">🔥</span>
          <span class="text-xs font-black text-amber-900">인기 게시판</span>
        </div>
        <RouterLink v-for="b in popularBoards" :key="b.slug" :to="`/community/${b.slug}`"
          class="flex items-center justify-between px-4 py-2.5 border-b last:border-0 hover:bg-amber-50/40 transition">
          <div class="flex items-center gap-2 min-w-0 flex-1">
            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" :style="{ background: b.color }"></span>
            <span class="text-xs text-gray-700 truncate">{{ b.name }}</span>
          </div>
          <div class="flex items-center gap-1 flex-shrink-0">
            <span class="text-[10px] text-gray-400 flex items-center gap-0.5">
              <span class="text-[9px]">👥</span>
              <span class="font-bold">{{ b.visitors }}</span>
            </span>
            <span v-if="b.badge === 'NEW'" class="text-[7px] font-black text-green-600">NEW</span>
            <span v-else-if="b.badge === 'HOT'" class="text-[7px] font-black text-red-500">HOT</span>
          </div>
        </RouterLink>
      </div>

      <!-- 트렌딩 태그 -->
      <div class="bg-white rounded-xl border shadow-sm p-4">
        <div class="text-xs font-black text-gray-800 mb-2 flex items-center gap-1.5"><span>🏷️</span><span>트렌딩 태그</span></div>
        <div class="flex flex-wrap gap-1">
          <span v-for="t in trendingTags" :key="t"
            @click="router.push({path:'/search',query:{q:t}})"
            class="bg-amber-50 text-amber-700 px-2 py-0.5 rounded text-[10px] font-bold cursor-pointer hover:bg-amber-100">#{{ t }}</span>
        </div>
      </div>

      <!-- 실시간 활동 -->
      <div class="bg-white rounded-xl border shadow-sm p-4">
        <div class="text-xs font-black text-gray-800 mb-2 flex items-center gap-1.5"><span>👥</span><span>실시간 활동</span></div>
        <div class="space-y-1.5 text-[11px]">
          <div class="flex items-center justify-between">
            <span class="flex items-center gap-1.5"><span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span><span class="text-gray-600">현재 접속</span></span>
            <span class="font-bold text-green-600">{{ liveUsers }}명</span>
          </div>
          <div class="flex items-center justify-between"><span class="text-gray-500">오늘 신규글</span><span class="font-bold text-amber-600">{{ todayPosts }}</span></div>
          <div class="flex items-center justify-between"><span class="text-gray-500">신규 장터</span><span class="font-bold text-blue-600">{{ todayMarket }}</span></div>
          <div class="flex items-center justify-between"><span class="text-gray-500">신규 가입</span><span class="font-bold text-purple-600">{{ todaySignups }}</span></div>
        </div>
      </div>
    </aside>

    <!-- 중앙 콘텐츠 -->
    <main class="col-span-12 lg:col-span-6 space-y-3">
      <!-- 상단 공지 띠 -->
      <div class="bg-white rounded-xl border shadow-sm px-3 py-2 flex items-center gap-3 text-xs overflow-x-auto">
        <span class="font-black text-amber-600 flex-shrink-0">📢 공지</span>
        <span class="text-gray-600 whitespace-nowrap truncate">{{ posts[0]?.title || '오늘의 핫 이슈를 확인하세요' }}</span>
        <span class="text-gray-300">·</span>
        <span class="text-gray-500 whitespace-nowrap">🔥 이번주 인기: #이민 #맛집 #부동산 #장터</span>
      </div>

      <!-- 2x2 박스 그리드 -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <!-- 최신글 -->
        <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
          <div class="px-3 py-2.5 border-b bg-amber-50 flex items-center justify-between">
            <span class="text-xs font-black text-amber-900 flex items-center gap-1">📝 최신글</span>
            <RouterLink to="/community" class="text-[10px] text-amber-600 font-bold hover:underline">더보기 →</RouterLink>
          </div>
          <div class="divide-y divide-gray-50">
            <RouterLink v-for="p in posts.slice(0,7)" :key="p.id" :to="`/community/${p.board?.slug || 'free'}/${p.id}`"
              class="flex items-center justify-between px-3 py-2 hover:bg-amber-50 transition">
              <span class="text-[11px] text-gray-700 truncate flex-1">{{ p.title }}</span>
              <span class="text-[9px] text-gray-400 ml-2 flex-shrink-0 font-bold">{{ p.comments_count || 0 }}</span>
            </RouterLink>
          </div>
        </div>

        <!-- 구인구직 -->
        <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
          <div class="px-3 py-2.5 border-b bg-blue-50 flex items-center justify-between">
            <span class="text-xs font-black text-blue-900 flex items-center gap-1">💼 구인구직</span>
            <RouterLink to="/jobs" class="text-[10px] text-blue-600 font-bold hover:underline">더보기 →</RouterLink>
          </div>
          <div class="divide-y divide-gray-50">
            <RouterLink v-for="j in jobs.slice(0,7)" :key="j.id" :to="`/jobs/${j.id}`"
              class="flex items-center justify-between px-3 py-2 hover:bg-blue-50 transition">
              <span class="text-[11px] text-gray-700 truncate flex-1">{{ j.title }}</span>
              <span class="text-[9px] text-blue-600 ml-2 flex-shrink-0 font-bold">{{ j.wage || '협의' }}</span>
            </RouterLink>
          </div>
        </div>

        <!-- 중고장터 -->
        <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
          <div class="px-3 py-2.5 border-b bg-green-50 flex items-center justify-between">
            <span class="text-xs font-black text-green-900 flex items-center gap-1">🛒 중고장터</span>
            <RouterLink to="/market" class="text-[10px] text-green-600 font-bold hover:underline">더보기 →</RouterLink>
          </div>
          <div class="divide-y divide-gray-50">
            <RouterLink v-for="m in market.slice(0,7)" :key="m.id" :to="`/market/${m.id}`"
              class="flex items-center justify-between px-3 py-2 hover:bg-green-50 transition">
              <span class="text-[11px] text-gray-700 truncate flex-1">{{ m.title }}</span>
              <span class="text-[9px] text-green-600 ml-2 flex-shrink-0 font-bold">${{ m.price || 0 }}</span>
            </RouterLink>
          </div>
        </div>

        <!-- 이벤트 -->
        <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
          <div class="px-3 py-2.5 border-b bg-red-50 flex items-center justify-between">
            <span class="text-xs font-black text-red-900 flex items-center gap-1">🎉 이벤트</span>
            <RouterLink to="/events" class="text-[10px] text-red-600 font-bold hover:underline">더보기 →</RouterLink>
          </div>
          <div class="divide-y divide-gray-50">
            <RouterLink v-for="e in eventsMock" :key="e.id" :to="e.to"
              class="flex items-center justify-between px-3 py-2 hover:bg-red-50 transition">
              <span class="text-[11px] text-gray-700 truncate flex-1">{{ e.title }}</span>
              <span class="text-[9px] text-red-600 ml-2 flex-shrink-0 font-bold">{{ e.badge }}</span>
            </RouterLink>
          </div>
        </div>
      </div>

      <!-- 최신 부동산 이미지 카드 -->
      <div v-if="realestateCards.length" class="bg-white rounded-xl border shadow-sm p-3">
        <div class="flex items-center justify-between mb-3">
          <span class="text-xs font-black text-gray-800">🏠 최신 부동산</span>
          <RouterLink to="/realestate" class="text-[10px] text-amber-600 font-bold">더보기 →</RouterLink>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
          <RouterLink v-for="c in realestateCards.slice(0,4)" :key="c.id" :to="c.to"
            class="block rounded-lg overflow-hidden border border-gray-100 hover:shadow-md transition group">
            <div class="aspect-square bg-gray-100 relative overflow-hidden">
              <img v-if="c.image" :src="c.image" class="w-full h-full object-cover group-hover:scale-105 transition-transform" @error="($event.target.style.display='none')" />
              <div v-else class="absolute inset-0 flex items-center justify-center text-4xl opacity-30">🏠</div>
              <span class="absolute top-1 left-1 text-[9px] font-black px-1.5 py-0.5 rounded text-white"
                :class="c.type === 'rent' ? 'bg-blue-500' : c.type === 'sale' ? 'bg-red-500' : 'bg-green-500'">
                {{ c.typeLabel }}
              </span>
              <span class="absolute bottom-1 right-1 bg-black/60 text-white text-[10px] font-black px-1.5 py-0.5 rounded">
                ${{ Number(c.price).toLocaleString() }}{{ c.type === 'rent' ? '/월' : '' }}
              </span>
            </div>
            <div class="p-1.5">
              <div class="text-[10px] font-bold text-gray-700 truncate">{{ c.title }}</div>
            </div>
          </RouterLink>
        </div>
      </div>

      <!-- 모바일 배너 2 (랜덤 — 중앙 콘텐츠 끝) -->
      <div class="lg:hidden">
        <MobileBanner page="home" slot="random" />
      </div>
    </main>

    <!-- 오른쪽 사이드바 -->
    <aside class="col-span-12 lg:col-span-3 space-y-3">
      <!-- 날씨 -->
      <div class="bg-gradient-to-br from-blue-400 to-blue-600 rounded-xl p-4 text-white shadow-sm">
        <div class="text-[10px] opacity-80">애틀랜타 · Today</div>
        <div class="flex items-baseline gap-2 mt-1"><span class="text-3xl font-black">72°F</span><span class="text-sm">맑음 ☀️</span></div>
        <div class="text-[10px] opacity-80 mt-2">내일 68° / 모레 75°</div>
      </div>

      <!-- 환율 -->
      <div class="bg-white rounded-xl border shadow-sm p-4">
        <div class="text-xs font-black text-gray-800 mb-2 flex items-center gap-1.5"><span>💱</span><span>환율</span></div>
        <div class="space-y-1.5 text-[11px]">
          <div class="flex justify-between"><span class="text-gray-500">🇺🇸 USD</span><span class="font-bold text-gray-800">1,386원</span></div>
          <div class="flex justify-between"><span class="text-gray-500">🇰🇷 KRW</span><span class="font-bold text-gray-800">$0.00072</span></div>
          <div class="flex justify-between text-[10px] pt-1 border-t">
            <span class="text-red-500">▲ 2.4</span>
            <span class="text-gray-400">15분 전 업데이트</span>
          </div>
        </div>
      </div>

      <!-- 우측 광고 -->
      <AdSlot page="home" position="right" :maxSlots="2" />

      <!-- 즐겨찾기 -->
      <div class="bg-white rounded-xl border shadow-sm p-4">
        <div class="text-xs font-black text-gray-800 mb-2 flex items-center gap-1.5"><span>⭐</span><span>즐겨찾기</span></div>
        <div class="grid grid-cols-3 gap-1.5">
          <RouterLink v-for="svc in favorites" :key="svc.to" :to="svc.to"
            class="flex flex-col items-center py-2 rounded-lg hover:bg-amber-50 transition">
            <span class="text-xl">{{ svc.icon }}</span>
            <span class="text-[9px] text-gray-500 mt-0.5">{{ svc.name }}</span>
          </RouterLink>
        </div>
      </div>

      <!-- 비로그인 CTA -->
      <div v-if="!auth.isLoggedIn" class="bg-gradient-to-br from-amber-500 to-orange-600 text-white rounded-xl p-4 shadow-sm">
        <div class="text-sm font-black">🎁 가입하고 바로 받기</div>
        <div class="text-[11px] opacity-90 mt-1">회원가입 10P · 프로필 완성 30P</div>
        <RouterLink to="/register" class="mt-3 block text-center bg-white text-amber-700 font-bold py-1.5 rounded-full text-sm">무료 가입</RouterLink>
      </div>
    </aside>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import AdSlot from '../components/AdSlot.vue'
import MobileBanner from '../components/MobileBanner.vue'
import axios from 'axios'

const router = useRouter()
const auth = useAuthStore()
const posts = ref([])
const jobs = ref([])
const market = ref([])
const realestate = ref([])
const heroBanners = ref([])
const heroIdx = ref(0)
let heroInterval = null

const totalSlides = computed(() => 1 + heroBanners.value.length)

function clickHeroBanner(b) {
  if (b.link_type === 'event' && b.event_id) router.push('/events?open=' + b.event_id)
  else if (b.link_type === 'page' && b.link_page) router.push(b.link_page)
  else if (b.link_type === 'url' && b.link_url) window.open(b.link_url, '_blank')
}
function startHeroSlide() {
  if (totalSlides.value <= 1) return
  heroInterval = setInterval(() => { heroIdx.value = (heroIdx.value + 1) % totalSlides.value }, 7000)
}
function pauseHero() { if (heroInterval) { clearInterval(heroInterval); heroInterval = null } }
function resumeHero() { if (!heroInterval && totalSlides.value > 1) startHeroSlide() }
onUnmounted(() => { if (heroInterval) clearInterval(heroInterval) })

const popularBoards = [
  { slug: 'free',        name: '자유게시판', color: '#f59e0b', visitors: '2.4k', badge: 'HOT' },
  { slug: 'food',        name: '맛집후기',   color: '#ef4444', visitors: '1.8k', badge: 'HOT' },
  { slug: 'immigration', name: '이민생활',   color: '#10b981', visitors: '1.2k', badge: 'HOT' },
  { slug: 'tips',        name: '생활꿀팁',   color: '#3b82f6', visitors: '890',  badge: 'NEW' },
  { slug: 'education',   name: '자녀교육',   color: '#8b5cf6', visitors: '654',  badge: 'NEW' },
  { slug: 'info',        name: '정보공유',   color: '#ec4899', visitors: '421',  badge: '' },
  { slug: 'health',      name: '건강정보',   color: '#14b8a6', visitors: '312',  badge: 'NEW' },
]

const trendingTags = ['이민','영주권','맛집','구인','중고차','부동산','세금','학교','병원','한의원','김치','미용실']

// 모바일 전용 컬러 카테고리 그리드 (히어로 바로 아래)
const mobileCategories = [
  { to: '/community',  icon: '💬', name: '커뮤니티', desc: '한인 이야기',  gradient: 'linear-gradient(135deg,#f59e0b,#ea580c)' },
  { to: '/qa',         icon: '❓', name: 'Q&A',      desc: '질문/답변',     gradient: 'linear-gradient(135deg,#fbbf24,#d97706)' },
  { to: '/jobs',       icon: '💼', name: '구인구직', desc: '일자리',        gradient: 'linear-gradient(135deg,#3b82f6,#1d4ed8)' },
  { to: '/market',     icon: '🛒', name: '중고장터', desc: '사고 팔기',     gradient: 'linear-gradient(135deg,#10b981,#047857)' },
  { to: '/realestate', icon: '🏠', name: '부동산',   desc: '렌트/매매',     gradient: 'linear-gradient(135deg,#6366f1,#4338ca)' },
  { to: '/directory',  icon: '🏪', name: '업소록',   desc: '한인 업소',     gradient: 'linear-gradient(135deg,#ef4444,#dc2626)' },
  { to: '/clubs',      icon: '👥', name: '동호회',   desc: '모임 찾기',     gradient: 'linear-gradient(135deg,#8b5cf6,#6d28d9)' },
  { to: '/events',     icon: '🎉', name: '이벤트',   desc: '포인트 기회',   gradient: 'linear-gradient(135deg,#ec4899,#be185d)' },
  { to: '/news',       icon: '📰', name: '뉴스',     desc: '오마이뉴스',    gradient: 'linear-gradient(135deg,#64748b,#334155)' },
]

const favorites = [
  { icon: '💬', name: '커뮤니티', to: '/community' },
  { icon: '❓', name: 'Q&A',      to: '/qa' },
  { icon: '💼', name: '구인구직', to: '/jobs' },
  { icon: '🛒', name: '중고장터', to: '/market' },
  { icon: '🏠', name: '부동산',   to: '/realestate' },
  { icon: '🏪', name: '업소록',   to: '/directory' },
]

const eventsMock = [
  { id: 1, title: '🐛 버그를 잡아라!',     to: '/events', badge: '포인트' },
  { id: 2, title: '🏠 부동산 포인트 2배', to: '/events', badge: '진행중' },
  { id: 3, title: '🎵 음악 감상회',       to: '/events', badge: '예정' },
  { id: 4, title: '💬 오픈 채팅방',       to: '/events', badge: '상시' },
]

const liveUsers = computed(() => 230 + (posts.value.length * 5))
const todayPosts = computed(() => posts.value.length * 12)
const todayMarket = computed(() => market.value.length * 3)
const todaySignups = computed(() => Math.max(12, Math.floor(posts.value.length * 1.5)))

const typeLabels = { rent: '렌트', sale: '매매', roommate: '룸메' }
const realestateCards = computed(() => realestate.value.slice(0, 4).map(r => ({
  id: r.id, to: `/realestate/${r.id}`,
  image: r.images?.[0] || r.image || null,
  title: r.title,
  price: r.price,
  type: r.type || 'sale',
  typeLabel: typeLabels[r.type] || '매매',
})))

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/hero-banners')
    heroBanners.value = data.data || []
    startHeroSlide()
  } catch {}
  const [p, j, m, r] = await Promise.allSettled([
    axios.get('/api/posts?per_page=10'),
    axios.get('/api/jobs?per_page=10'),
    axios.get('/api/market?per_page=10'),
    axios.get('/api/realestate?per_page=6'),
  ])
  if (p.status === 'fulfilled') posts.value = p.value.data?.data?.data || []
  if (j.status === 'fulfilled') jobs.value = j.value.data?.data?.data || []
  if (m.status === 'fulfilled') market.value = m.value.data?.data?.data || []
  if (r.status === 'fulfilled') realestate.value = r.value.data?.data?.data || r.value.data?.data || []
})
</script>

<style scoped>
.hero-enter-active, .hero-leave-active { transition: opacity 0.6s ease; }
.hero-enter-from, .hero-leave-to { opacity: 0; }
</style>
