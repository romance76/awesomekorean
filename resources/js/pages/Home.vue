<template>
<div class="min-h-screen">
  <!-- ═════ 1. 히어로 슬라이드 (컨테이너 안 라운드 카드형) ═════ -->
  <div class="max-w-7xl mx-auto px-3 pt-3">
    <section class="relative overflow-hidden h-[180px] md:h-[280px] rounded-2xl md:rounded-3xl shadow-card"
      @mouseenter="pauseHero" @mouseleave="resumeHero">
      <Transition name="hero">
        <div v-if="heroIdx === 0" class="absolute inset-0 bg-gradient-to-br from-[#FF8A53] via-[#FF6B2C] to-[#F2570F] flex items-center justify-center">
          <div class="text-center text-white px-6">
            <div class="inline-block text-[11px] font-bold bg-white/20 backdrop-blur px-3.5 py-1 rounded-full mb-3 tracking-wide">미국 한인 NO.1 커뮤니티</div>
            <h1 class="text-4xl md:text-6xl font-black leading-tight drop-shadow-sm">AwesomeKorean</h1>
            <p class="text-sm md:text-base opacity-95 mt-2">한인들의 일상을 함께하는 올인원 플랫폼</p>
          </div>
        </div>
        <div v-else-if="heroBanners[heroIdx - 1]" :key="heroIdx" @click="clickHeroBanner(heroBanners[heroIdx - 1])"
          class="absolute inset-0 cursor-pointer"
          :style="{ background: heroBanners[heroIdx - 1].bg_color ? `linear-gradient(135deg, ${heroBanners[heroIdx - 1].bg_color}, ${heroBanners[heroIdx - 1].bg_color}cc)` : '' }">
          <img v-if="heroBanners[heroIdx - 1].image_url" :src="heroBanners[heroIdx - 1].image_url" class="absolute inset-0 w-full h-full object-cover" />
          <div v-else class="absolute inset-0 flex items-center justify-center px-6 text-center text-white">
            <div>
              <div class="text-3xl md:text-5xl font-black drop-shadow-sm">{{ heroBanners[heroIdx - 1].title }}</div>
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
  </div>

  <!-- ═════ 2-M. 모바일 전용: 카테고리 카드 그리드 + 배너 1 ═════ -->
  <div class="lg:hidden max-w-7xl mx-auto px-3 pt-3">
    <!-- 카테고리 그리드 (3열 × 3행) — 흰 카드 + 파스텔 아이콘 칩 -->
    <div class="grid grid-cols-3 gap-2 mb-3">
      <RouterLink v-for="c in mobileCategories" :key="c.to" :to="c.to"
        class="card card-hover p-3 flex flex-col items-center justify-center aspect-[5/4]">
        <span class="icon-chip w-10 h-10" :class="menuChipColor(c.key)">
          <AppIcon :name="menuIcon(c.key)" :size="20" />
        </span>
        <span class="text-xs font-bold text-ink mt-1.5">{{ c.name }}</span>
        <span class="text-[11px] text-ink-muted mt-0.5">{{ c.desc }}</span>
      </RouterLink>
    </div>

    <!-- 모바일 배너 1 (프리미엄 — 히어로 직하) -->
    <MobileBanner page="home" class="mb-3" />
  </div>

  <!-- ═════ 2. 메인 3-column 포털 레이아웃 ═════ -->
  <div class="max-w-7xl mx-auto px-3 py-4 grid grid-cols-12 gap-4">
    <!-- 왼쪽 사이드바 (데스크톱 전용: 카테고리/위젯 먼저, 광고는 맨 아래) -->
    <aside class="col-span-12 lg:col-span-3 space-y-4 hidden lg:block">
      <!-- 인기 게시판 -->
      <div class="card overflow-hidden">
        <div class="px-4 py-3 flex items-center gap-2 border-b border-gray-50">
          <span class="icon-chip w-7 h-7 bg-red-50 text-red-500"><AppIcon name="flame" :size="15" /></span>
          <span class="text-[13px] font-bold text-ink">인기 게시판</span>
        </div>
        <RouterLink v-for="b in popularBoards" :key="b.slug" :to="`/community/${b.slug}`"
          class="flex items-center justify-between px-4 py-2.5 border-b border-gray-50 last:border-0 hover:bg-amber-50/40 transition-colors">
          <div class="flex items-center gap-2 min-w-0 flex-1">
            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" :style="{ background: b.color }"></span>
            <span class="text-xs text-ink-light truncate">{{ b.name }}</span>
          </div>
          <div class="flex items-center gap-1.5 flex-shrink-0">
            <span class="text-[11px] text-ink-faint font-semibold">{{ b.visitors }}</span>
            <span v-if="b.badge === 'NEW'" class="text-[11px] font-black text-emerald-500">NEW</span>
            <span v-else-if="b.badge === 'HOT'" class="text-[11px] font-black text-red-500">HOT</span>
          </div>
        </RouterLink>
      </div>

      <!-- 트렌딩 태그 -->
      <div class="card p-4">
        <div class="flex items-center gap-2 mb-2.5">
          <span class="icon-chip w-7 h-7 bg-violet-50 text-violet-500"><AppIcon name="tag" :size="15" /></span>
          <span class="text-[13px] font-bold text-ink">트렌딩 태그</span>
        </div>
        <div class="flex flex-wrap gap-1.5">
          <span v-for="t in trendingTags" :key="t"
            @click="router.push({path:'/search',query:{q:t}})"
            class="bg-amber-50 text-amber-700 px-2.5 py-1 rounded-full text-[11px] font-semibold cursor-pointer hover:bg-amber-100 transition-colors">#{{ t }}</span>
        </div>
      </div>

      <!-- 실시간 활동 -->
      <div class="card p-4">
        <div class="flex items-center gap-2 mb-2.5">
          <span class="icon-chip w-7 h-7 bg-emerald-50 text-emerald-500"><AppIcon name="users" :size="15" /></span>
          <span class="text-[13px] font-bold text-ink">실시간 활동</span>
        </div>
        <div class="space-y-2 text-xs">
          <div class="flex items-center justify-between">
            <span class="flex items-center gap-1.5"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span><span class="text-ink-light">현재 접속</span></span>
            <span class="font-bold text-emerald-600">{{ liveUsers }}명</span>
          </div>
          <div class="flex items-center justify-between"><span class="text-ink-muted">오늘 신규글</span><span class="font-bold text-amber-600">{{ todayPosts }}</span></div>
          <div class="flex items-center justify-between"><span class="text-ink-muted">신규 장터</span><span class="font-bold text-blue-600">{{ todayMarket }}</span></div>
          <div class="flex items-center justify-between"><span class="text-ink-muted">신규 가입</span><span class="font-bold text-violet-600">{{ todaySignups }}</span></div>
        </div>
      </div>

      <!-- 좌측 광고: 카테고리/위젯 아래 나란히 -->
      <AdSlot page="home" position="left" :maxSlots="3" />
    </aside>

    <!-- 중앙 콘텐츠 -->
    <main class="col-span-12 lg:col-span-6 space-y-4">
      <!-- 상단 공지 띠 -->
      <div class="card px-4 py-2.5 flex items-center gap-3 text-xs overflow-x-auto scrollbar-hide">
        <span class="font-bold text-amber-600 flex-shrink-0 flex items-center gap-1"><AppIcon name="megaphone" :size="14" />공지</span>
        <span class="text-ink-light whitespace-nowrap truncate">{{ posts[0]?.title || '오늘의 핫 이슈를 확인하세요' }}</span>
        <span class="text-gray-200">·</span>
        <span class="text-ink-muted whitespace-nowrap">이번주 인기: #이민 #맛집 #부동산 #장터</span>
      </div>

      <!-- 2x2 박스 그리드 -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- 최신글 -->
        <div class="card overflow-hidden">
          <div class="px-4 py-3 border-b border-gray-50 flex items-center justify-between">
            <span class="text-[13px] font-bold text-ink flex items-center gap-2">
              <span class="icon-chip w-7 h-7 bg-amber-50 text-amber-600"><AppIcon name="edit" :size="14" /></span>최신글
            </span>
            <RouterLink to="/community" class="text-[11px] text-ink-muted font-semibold hover:text-amber-600 transition-colors flex items-center gap-0.5">더보기<AppIcon name="chevron-right" :size="12" /></RouterLink>
          </div>
          <div class="divide-y divide-gray-50">
            <RouterLink v-for="p in posts.slice(0,7)" :key="p.id" :to="`/community/${p.board?.slug || 'free'}/${p.id}`"
              class="flex items-center justify-between px-4 py-2 hover:bg-amber-50/40 transition-colors">
              <span class="text-xs text-ink-light truncate flex-1">{{ p.title }}</span>
              <span class="text-[11px] text-ink-faint ml-2 flex-shrink-0 font-bold">{{ p.comments_count || 0 }}</span>
            </RouterLink>
          </div>
        </div>

        <!-- 구인구직 -->
        <div class="card overflow-hidden">
          <div class="px-4 py-3 border-b border-gray-50 flex items-center justify-between">
            <span class="text-[13px] font-bold text-ink flex items-center gap-2">
              <span class="icon-chip w-7 h-7 bg-emerald-50 text-emerald-600"><AppIcon name="briefcase" :size="14" /></span>구인구직
            </span>
            <RouterLink to="/jobs" class="text-[11px] text-ink-muted font-semibold hover:text-amber-600 transition-colors flex items-center gap-0.5">더보기<AppIcon name="chevron-right" :size="12" /></RouterLink>
          </div>
          <div class="divide-y divide-gray-50">
            <RouterLink v-for="j in jobs.slice(0,7)" :key="j.id" :to="`/jobs/${j.id}`"
              class="flex items-center justify-between px-4 py-2 hover:bg-amber-50/40 transition-colors">
              <span class="text-xs text-ink-light truncate flex-1">{{ j.title }}</span>
              <span class="text-[11px] text-emerald-600 ml-2 flex-shrink-0 font-bold">{{ j.wage || '협의' }}</span>
            </RouterLink>
          </div>
        </div>

        <!-- 중고장터 -->
        <div class="card overflow-hidden">
          <div class="px-4 py-3 border-b border-gray-50 flex items-center justify-between">
            <span class="text-[13px] font-bold text-ink flex items-center gap-2">
              <span class="icon-chip w-7 h-7 bg-orange-50 text-orange-600"><AppIcon name="shopping-cart" :size="14" /></span>중고장터
            </span>
            <RouterLink to="/market" class="text-[11px] text-ink-muted font-semibold hover:text-amber-600 transition-colors flex items-center gap-0.5">더보기<AppIcon name="chevron-right" :size="12" /></RouterLink>
          </div>
          <div class="divide-y divide-gray-50">
            <RouterLink v-for="m in market.slice(0,7)" :key="m.id" :to="`/market/${m.id}`"
              class="flex items-center justify-between px-4 py-2 hover:bg-amber-50/40 transition-colors">
              <span class="text-xs text-ink-light truncate flex-1">{{ m.title }}</span>
              <span class="text-[11px] text-amber-600 ml-2 flex-shrink-0 font-bold">${{ m.price || 0 }}</span>
            </RouterLink>
          </div>
        </div>

        <!-- 이벤트 -->
        <div class="card overflow-hidden">
          <div class="px-4 py-3 border-b border-gray-50 flex items-center justify-between">
            <span class="text-[13px] font-bold text-ink flex items-center gap-2">
              <span class="icon-chip w-7 h-7 bg-rose-50 text-rose-500"><AppIcon name="calendar" :size="14" /></span>이벤트
            </span>
            <RouterLink to="/events" class="text-[11px] text-ink-muted font-semibold hover:text-amber-600 transition-colors flex items-center gap-0.5">더보기<AppIcon name="chevron-right" :size="12" /></RouterLink>
          </div>
          <div class="divide-y divide-gray-50">
            <RouterLink v-for="e in eventsMock" :key="e.id" :to="e.to"
              class="flex items-center justify-between px-4 py-2 hover:bg-amber-50/40 transition-colors">
              <span class="text-xs text-ink-light truncate flex-1">{{ e.title }}</span>
              <span class="badge-red ml-2 flex-shrink-0 !text-[11px]">{{ e.badge }}</span>
            </RouterLink>
          </div>
        </div>
      </div>

      <!-- 최신 부동산 이미지 카드 -->
      <div v-if="realestateCards.length" class="card p-4">
        <div class="flex items-center justify-between mb-3">
          <span class="text-[13px] font-bold text-ink flex items-center gap-2">
            <span class="icon-chip w-7 h-7 bg-violet-50 text-violet-600"><AppIcon name="building" :size="14" /></span>최신 부동산
          </span>
          <RouterLink to="/realestate" class="text-[11px] text-ink-muted font-semibold hover:text-amber-600 transition-colors flex items-center gap-0.5">더보기<AppIcon name="chevron-right" :size="12" /></RouterLink>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
          <RouterLink v-for="c in realestateCards.slice(0,4)" :key="c.id" :to="c.to"
            class="block rounded-xl overflow-hidden bg-gray-50 card-hover group">
            <div class="aspect-square bg-gray-100 relative overflow-hidden">
              <img v-if="c.image" :src="c.image" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" @error="($event.target.style.display='none')" />
              <div v-else class="absolute inset-0 flex items-center justify-center text-gray-300">
                <AppIcon name="home" :size="32" :stroke-width="1.5" />
              </div>
              <span class="absolute top-1.5 left-1.5 text-[11px] font-bold px-2 py-0.5 rounded-full text-white"
                :class="c.type === 'rent' ? 'bg-blue-500/90' : c.type === 'sale' ? 'bg-red-500/90' : 'bg-emerald-500/90'">
                {{ c.typeLabel }}
              </span>
              <span class="absolute bottom-1.5 right-1.5 bg-black/60 backdrop-blur-sm text-white text-[11px] font-bold px-2 py-0.5 rounded-full">
                ${{ Number(c.price).toLocaleString() }}{{ c.type === 'rent' ? '/월' : '' }}
              </span>
            </div>
            <div class="p-2">
              <div class="text-[11px] font-semibold text-ink-light truncate">{{ c.title }}</div>
            </div>
          </RouterLink>
        </div>
      </div>

      <!-- 모바일 배너 2 (랜덤 — 중앙 콘텐츠 끝) -->
      <div class="lg:hidden">
        <MobileBanner page="home" />
      </div>
    </main>

    <!-- 오른쪽 사이드바 -->
    <aside class="col-span-12 lg:col-span-3 space-y-4">
      <!-- 날씨 -->
      <div class="bg-gradient-to-br from-sky-400 to-blue-500 rounded-2xl p-4 text-white shadow-card">
        <div class="text-[11px] opacity-90">애틀랜타 · Today</div>
        <div class="flex items-baseline gap-2 mt-1"><span class="text-3xl font-black">72°F</span><span class="text-sm">맑음</span></div>
        <div class="text-[11px] opacity-90 mt-2">내일 68° / 모레 75°</div>
      </div>

      <!-- 환율 -->
      <div class="card p-4">
        <div class="flex items-center gap-2 mb-2.5">
          <span class="icon-chip w-7 h-7 bg-blue-50 text-blue-500"><AppIcon name="dollar" :size="15" /></span>
          <span class="text-[13px] font-bold text-ink">환율</span>
        </div>
        <div class="space-y-2 text-xs">
          <div class="flex justify-between"><span class="text-ink-muted">USD</span><span class="font-bold text-ink">1,386원</span></div>
          <div class="flex justify-between"><span class="text-ink-muted">KRW</span><span class="font-bold text-ink">$0.00072</span></div>
          <div class="flex justify-between text-[11px] pt-2 border-t border-gray-50">
            <span class="text-red-500 font-semibold">▲ 2.4</span>
            <span class="text-ink-faint">15분 전 업데이트</span>
          </div>
        </div>
      </div>

      <!-- 즐겨찾기 -->
      <div class="card p-4">
        <div class="flex items-center gap-2 mb-2.5">
          <span class="icon-chip w-7 h-7 bg-amber-50 text-amber-500"><AppIcon name="star" :size="15" /></span>
          <span class="text-[13px] font-bold text-ink">즐겨찾기</span>
        </div>
        <div class="grid grid-cols-3 gap-1.5">
          <RouterLink v-for="svc in favorites" :key="svc.to" :to="svc.to"
            class="flex flex-col items-center py-2.5 rounded-xl hover:bg-amber-50/60 transition-colors">
            <span class="icon-chip w-9 h-9" :class="menuChipColor(svc.key)">
              <AppIcon :name="menuIcon(svc.key)" :size="18" />
            </span>
            <span class="text-[11px] text-ink-muted font-medium mt-1.5">{{ svc.name }}</span>
          </RouterLink>
        </div>
      </div>

      <!-- 우측 광고: 위젯 아래 나란히 (데스크톱만) -->
      <AdSlot page="home" position="right" :maxSlots="2" class="hidden lg:block" />

      <!-- 비로그인 CTA -->
      <div v-if="!auth.isLoggedIn" class="bg-gradient-to-br from-[#FF8A53] to-[#F2570F] text-white rounded-2xl p-5 shadow-card">
        <div class="text-sm font-black flex items-center gap-1.5"><AppIcon name="gift" :size="17" />가입하고 바로 받기</div>
        <div class="text-xs opacity-90 mt-1.5">회원가입 10P · 프로필 완성 30P</div>
        <RouterLink to="/register" class="mt-3.5 block text-center bg-white text-amber-600 font-bold py-2 rounded-full text-sm transition-transform hover:-translate-y-px">무료 가입</RouterLink>
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
import AppIcon from '../components/AppIcon.vue'
import { menuIcon, menuChipColor } from '../utils/menuIcons'
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
  { slug: 'free',        name: '자유게시판', color: '#FF6B2C', visitors: '2.4k', badge: 'HOT' },
  { slug: 'food',        name: '맛집후기',   color: '#ef4444', visitors: '1.8k', badge: 'HOT' },
  { slug: 'immigration', name: '이민생활',   color: '#10b981', visitors: '1.2k', badge: 'HOT' },
  { slug: 'tips',        name: '생활꿀팁',   color: '#3b82f6', visitors: '890',  badge: 'NEW' },
  { slug: 'education',   name: '자녀교육',   color: '#8b5cf6', visitors: '654',  badge: 'NEW' },
  { slug: 'info',        name: '정보공유',   color: '#ec4899', visitors: '421',  badge: '' },
  { slug: 'health',      name: '건강정보',   color: '#14b8a6', visitors: '312',  badge: 'NEW' },
]

const trendingTags = ['이민','영주권','맛집','구인','중고차','부동산','세금','학교','병원','한의원','김치','미용실']

// 모바일 전용 카테고리 그리드 (히어로 바로 아래) — 아이콘/색상은 menuIcons.js 공용 매핑 사용
const mobileCategories = [
  { to: '/community',  key: 'community',  name: '커뮤니티', desc: '한인 이야기' },
  { to: '/qa',         key: 'qa',         name: 'Q&A',      desc: '질문/답변' },
  { to: '/jobs',       key: 'jobs',       name: '구인구직', desc: '일자리' },
  { to: '/market',     key: 'market',     name: '중고장터', desc: '사고 팔기' },
  { to: '/realestate', key: 'realestate', name: '부동산',   desc: '렌트/매매' },
  { to: '/directory',  key: 'directory',  name: '업소록',   desc: '한인 업소' },
  { to: '/clubs',      key: 'clubs',      name: '동호회',   desc: '모임 찾기' },
  { to: '/events',     key: 'events',     name: '이벤트',   desc: '포인트 기회' },
  { to: '/news',       key: 'news',       name: '뉴스',     desc: '오마이뉴스' },
]

const favorites = [
  { key: 'community',  name: '커뮤니티', to: '/community' },
  { key: 'qa',         name: 'Q&A',      to: '/qa' },
  { key: 'jobs',       name: '구인구직', to: '/jobs' },
  { key: 'market',     name: '중고장터', to: '/market' },
  { key: 'realestate', name: '부동산',   to: '/realestate' },
  { key: 'directory',  name: '업소록',   to: '/directory' },
]

const eventsMock = [
  { id: 1, title: '버그를 잡아라!',     to: '/events', badge: '포인트' },
  { id: 2, title: '부동산 포인트 2배', to: '/events', badge: '진행중' },
  { id: 3, title: '음악 감상회',       to: '/events', badge: '예정' },
  { id: 4, title: '오픈 채팅방',       to: '/events', badge: '상시' },
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
