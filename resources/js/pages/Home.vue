<template>
<div class="min-h-screen">

  <!-- ═════ 0. 라이브 티커 (다크 marquee) ═════ -->
  <div class="ticker bg-night text-[#EDE5DD] overflow-hidden" aria-label="실시간 커뮤니티 활동">
    <div class="ticker-track flex gap-12 py-2 w-max">
      <span v-for="(t, i) in tickerLoop" :key="i" class="flex items-center gap-2 text-[13px] whitespace-nowrap">
        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0"></span>
        <b class="text-white font-semibold">{{ t.label }}</b>
        <span>{{ t.text }}</span>
        <span class="text-[#8C8178]">{{ t.time }}</span>
      </span>
    </div>
  </div>

  <!-- ═════ 1. 타이포 히어로 + 위젯 ═════ -->
  <section class="max-w-7xl mx-auto px-4 lg:px-6 pt-8 lg:pt-12 pb-6 grid grid-cols-1 lg:grid-cols-[1.5fr_1fr] gap-7 items-center">
    <div>
      <span class="inline-flex items-center gap-2 text-[12.5px] font-bold tracking-wide text-amber-500 bg-amber-50 px-3.5 py-1.5 rounded-full mb-4 lg:mb-5">미국 한인 NO.1 커뮤니티</span>
      <h1 class="text-[32px] lg:text-[50px] font-extrabold leading-[1.14] tracking-[-0.04em] text-ink">
        미국에서의 하루,<br><span class="hero-hl">어코</span>와 함께 시작하세요
      </h1>
      <p class="mt-3.5 text-[15px] lg:text-[16.5px] text-ink-light max-w-[46ch]">이민 생활 꿀팁부터 동네 맛집, 구인구직, 중고 거래까지 — 한인들의 일상이 모이는 올인원 플랫폼.</p>
      <div class="flex flex-wrap gap-2 mt-5">
        <button v-for="t in trendingTags.slice(0, 6)" :key="t"
          @click="router.push({path:'/search',query:{q:t}})"
          class="px-3.5 py-1.5 rounded-full text-[13.5px] font-semibold border-[1.5px] border-line text-ink-light bg-white transition-all duration-150 hover:border-amber-400 hover:text-amber-500 hover:bg-amber-50">#{{ t }}</button>
      </div>
    </div>
    <!-- 우측 위젯 3종 -->
    <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-1 gap-3.5">
      <div class="rounded-card p-5 text-white shadow-card" style="background:linear-gradient(150deg,#2A2017,#1B1613)">
        <div class="text-xs font-bold tracking-wider text-[#9C9088] uppercase">애틀랜타 · 오늘</div>
        <div class="flex items-end justify-between gap-3">
          <div>
            <div class="text-4xl font-extrabold tracking-tight leading-none mt-2">72°F</div>
            <div class="text-[13px] text-[#B7ABA1] mt-1.5">맑음 · 내일 68° / 모레 75°</div>
          </div>
          <AppIcon name="sun" :size="36" class="text-amber-300" :stroke-width="1.8" />
        </div>
      </div>
      <div class="card p-5">
        <div class="text-xs font-bold tracking-wider text-ink-muted uppercase">환율</div>
        <div class="flex items-center justify-between mt-2.5">
          <span class="text-[13.5px] font-semibold text-ink-light">USD → KRW</span>
          <span class="text-[19px] font-extrabold tracking-tight tabular-nums text-ink">1,386원</span>
        </div>
        <div class="flex justify-between mt-2 text-xs text-ink-muted">
          <span class="text-[#E8442E] font-bold">▲ 2.4</span>
          <span>15분 전 업데이트</span>
        </div>
      </div>
      <div class="card p-5 flex items-center gap-3.5">
        <span class="live-pulse shrink-0"></span>
        <div>
          <div class="text-[21px] font-extrabold tracking-tight text-ink">{{ liveUsers }}명</div>
          <div class="text-[13px] text-ink-muted">지금 접속해 있어요</div>
        </div>
      </div>
    </div>
  </section>

  <!-- ═════ 1-M. 모바일 전용: 카테고리 카드 그리드 + 배너 ═════ -->
  <div class="lg:hidden max-w-7xl mx-auto px-4 pt-2">
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
    <MobileBanner page="home" class="mb-3" />
  </div>

  <!-- ═════ 2. 관리자 히어로 배너 슬라이드 (있을 때만, 기존 기능 유지) ═════ -->
  <div v-if="heroBanners.length" class="max-w-7xl mx-auto px-4 lg:px-6 pb-2">
    <section class="relative overflow-hidden h-[140px] md:h-[200px] rounded-card shadow-card"
      @mouseenter="pauseHero" @mouseleave="resumeHero">
      <Transition name="hero">
        <div v-if="heroBanners[heroIdx]" :key="heroIdx" @click="clickHeroBanner(heroBanners[heroIdx])"
          class="absolute inset-0 cursor-pointer"
          :style="{ background: heroBanners[heroIdx].bg_color ? `linear-gradient(135deg, ${heroBanners[heroIdx].bg_color}, ${heroBanners[heroIdx].bg_color}cc)` : 'linear-gradient(120deg,#FF7A30,#FF4D12)' }">
          <img v-if="heroBanners[heroIdx].image_url" :src="heroBanners[heroIdx].image_url" class="absolute inset-0 w-full h-full object-cover" />
          <div v-else class="absolute inset-0 flex items-center justify-center px-6 text-center text-white">
            <div>
              <div class="text-2xl md:text-4xl font-extrabold drop-shadow-sm">{{ heroBanners[heroIdx].title }}</div>
              <div v-if="heroBanners[heroIdx].subtitle" class="text-sm md:text-base mt-2 opacity-95">{{ heroBanners[heroIdx].subtitle }}</div>
            </div>
          </div>
        </div>
      </Transition>
      <div v-if="heroBanners.length > 1" class="absolute bottom-3 left-0 right-0 flex justify-center gap-1.5">
        <button v-for="i in heroBanners.length" :key="i" @click="heroIdx = i - 1"
          class="h-1.5 rounded-full transition-all"
          :class="heroIdx === i - 1 ? 'bg-white w-7' : 'bg-white/50 w-1.5'"></button>
      </div>
    </section>
  </div>

  <!-- ═════ 3. 매거진 카드 그리드 ═════ -->
  <main class="max-w-7xl mx-auto px-4 lg:px-6 pt-3 pb-16 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">

    <!-- 인기 게시판 -->
    <section class="card card-hover overflow-hidden">
      <div class="flex items-center gap-2.5 px-5 pt-4 pb-2.5">
        <span class="icon-chip w-[30px] h-[30px] bg-amber-50 text-amber-500"><AppIcon name="flame" :size="15" /></span>
        <h3 class="flex-1 text-base font-extrabold tracking-tight text-ink">인기 게시판</h3>
        <RouterLink to="/community" class="text-[13px] font-semibold text-ink-muted hover:text-amber-500 transition-colors">전체 →</RouterLink>
      </div>
      <ul class="px-2 pb-2.5">
        <li v-for="(b, i) in popularBoards" :key="b.slug">
          <RouterLink :to="`/community/${b.slug}`" class="flex items-center gap-2.5 px-3 py-[9px] rounded-xl transition-colors hover:bg-surface">
            <span class="w-5 text-center text-[13.5px] font-extrabold shrink-0" :class="i < 3 ? 'text-amber-500' : 'text-ink-muted'">{{ i + 1 }}</span>
            <span class="flex-1 text-[14.5px] font-medium text-ink truncate">{{ b.name }}</span>
            <span class="text-[12.5px] text-ink-muted tabular-nums shrink-0">{{ b.visitors }}</span>
            <span v-if="b.badge === 'HOT'" class="badge-primary !text-[10.5px]">HOT</span>
            <span v-else-if="b.badge === 'NEW'" class="badge-green !text-[10.5px]">NEW</span>
          </RouterLink>
        </li>
      </ul>
    </section>

    <!-- 최신글 -->
    <section class="card card-hover overflow-hidden">
      <div class="flex items-center gap-2.5 px-5 pt-4 pb-2.5">
        <span class="icon-chip w-[30px] h-[30px] bg-[#EBF1FF] text-[#2E6BFF]"><AppIcon name="edit" :size="15" /></span>
        <h3 class="flex-1 text-base font-extrabold tracking-tight text-ink">최신글</h3>
        <RouterLink to="/community" class="text-[13px] font-semibold text-ink-muted hover:text-amber-500 transition-colors">더보기 →</RouterLink>
      </div>
      <ul class="px-2 pb-2.5">
        <li v-for="p in posts.slice(0, 7)" :key="p.id">
          <RouterLink :to="`/community/${p.board?.slug || 'free'}/${p.id}`" class="flex items-center gap-2.5 px-3 py-[9px] rounded-xl transition-colors hover:bg-surface">
            <span class="flex-1 text-[14.5px] font-medium text-ink truncate">{{ p.title }}</span>
            <span class="text-[12.5px] text-ink-muted tabular-nums shrink-0">댓글 {{ p.comments_count || 0 }}</span>
          </RouterLink>
        </li>
      </ul>
    </section>

    <!-- 구인구직 -->
    <section class="card card-hover overflow-hidden">
      <div class="flex items-center gap-2.5 px-5 pt-4 pb-2.5">
        <span class="icon-chip w-[30px] h-[30px] bg-[#E9F8F1] text-[#0EA56B]"><AppIcon name="briefcase" :size="15" /></span>
        <h3 class="flex-1 text-base font-extrabold tracking-tight text-ink">구인구직</h3>
        <RouterLink to="/jobs" class="text-[13px] font-semibold text-ink-muted hover:text-amber-500 transition-colors">더보기 →</RouterLink>
      </div>
      <ul class="px-2 pb-2.5">
        <li v-for="j in jobs.slice(0, 5)" :key="j.id">
          <RouterLink :to="`/jobs/${j.id}`" class="flex items-center gap-2.5 px-3 py-[9px] rounded-xl transition-colors hover:bg-surface">
            <span class="w-[42px] h-[42px] rounded-[10px] bg-surface border border-line grid place-items-center shrink-0 text-ink-muted overflow-hidden">
              <img v-if="j.logo_url || j.logo" :src="j.logo_url || j.logo" class="w-full h-full object-cover" @error="e=>e.target.style.display='none'" />
              <AppIcon v-else name="briefcase" :size="18" :stroke-width="1.8" />
            </span>
            <span class="flex-1 text-[14.5px] font-medium text-ink truncate">{{ j.title }}</span>
            <span class="badge-blue !text-[10.5px] shrink-0">{{ j.wage || '협의' }}</span>
          </RouterLink>
        </li>
      </ul>
    </section>

    <!-- 중고장터 -->
    <section class="card card-hover overflow-hidden">
      <div class="flex items-center gap-2.5 px-5 pt-4 pb-2.5">
        <span class="icon-chip w-[30px] h-[30px] bg-[#FFF6E0] text-[#C98A00]"><AppIcon name="shopping-cart" :size="15" /></span>
        <h3 class="flex-1 text-base font-extrabold tracking-tight text-ink">중고장터</h3>
        <RouterLink to="/market" class="text-[13px] font-semibold text-ink-muted hover:text-amber-500 transition-colors">더보기 →</RouterLink>
      </div>
      <ul class="px-2 pb-2.5">
        <li v-for="m in market.slice(0, 5)" :key="m.id">
          <RouterLink :to="`/market/${m.id}`" class="flex items-center gap-2.5 px-3 py-[9px] rounded-xl transition-colors hover:bg-surface">
            <span class="w-[42px] h-[42px] rounded-[10px] bg-surface border border-line grid place-items-center shrink-0 text-ink-muted overflow-hidden">
              <img v-if="m.images?.[0] || m.image" :src="m.images?.[0] || m.image" class="w-full h-full object-cover" @error="e=>e.target.style.display='none'" />
              <AppIcon v-else name="image" :size="18" :stroke-width="1.8" />
            </span>
            <span class="flex-1 text-[14.5px] font-medium text-ink truncate">{{ m.title }}</span>
            <span class="text-sm font-extrabold text-[#0EA56B] tabular-nums shrink-0">${{ m.price || 0 }}</span>
          </RouterLink>
        </li>
      </ul>
    </section>

    <!-- 이벤트 -->
    <section class="card card-hover overflow-hidden">
      <div class="flex items-center gap-2.5 px-5 pt-4 pb-2.5">
        <span class="icon-chip w-[30px] h-[30px] bg-[#FFEEF2] text-[#E8336E]"><AppIcon name="calendar" :size="15" /></span>
        <h3 class="flex-1 text-base font-extrabold tracking-tight text-ink">이벤트</h3>
        <RouterLink to="/events" class="text-[13px] font-semibold text-ink-muted hover:text-amber-500 transition-colors">더보기 →</RouterLink>
      </div>
      <ul class="px-2 pb-2.5">
        <li v-for="e in eventsMock" :key="e.id">
          <RouterLink :to="e.to" class="flex items-center gap-2.5 px-3 py-[9px] rounded-xl transition-colors hover:bg-surface">
            <span class="w-[42px] h-[42px] rounded-[10px] bg-surface border border-line grid place-items-center shrink-0 text-ink-muted">
              <AppIcon :name="e.icon" :size="18" :stroke-width="1.8" />
            </span>
            <span class="flex-1 text-[14.5px] font-medium text-ink truncate">{{ e.title }}</span>
            <span :class="e.badgeClass" class="!text-[10.5px] shrink-0">{{ e.badge }}</span>
          </RouterLink>
        </li>
      </ul>
    </section>

    <!-- 트렌딩 태그 -->
    <section class="card card-hover overflow-hidden">
      <div class="flex items-center gap-2.5 px-5 pt-4 pb-2.5">
        <span class="icon-chip w-[30px] h-[30px] bg-amber-50 text-amber-500"><AppIcon name="chart-bar" :size="15" /></span>
        <h3 class="flex-1 text-base font-extrabold tracking-tight text-ink">이번 주 트렌딩</h3>
      </div>
      <div class="px-5 pb-5 flex flex-wrap gap-2">
        <button v-for="t in trendingTags" :key="t"
          @click="router.push({path:'/search',query:{q:t}})"
          class="px-3.5 py-1.5 rounded-full text-[13px] font-semibold bg-surface text-ink-light transition-all duration-150 hover:bg-amber-400 hover:text-white">#{{ t }}</button>
      </div>
    </section>

    <!-- 최신 부동산 (사진 카드, 풀폭) -->
    <section v-if="realestateCards.length" class="card sm:col-span-2 lg:col-span-3 p-5">
      <div class="flex items-center gap-2.5 mb-4">
        <span class="icon-chip w-[30px] h-[30px] bg-violet-50 text-violet-500"><AppIcon name="building" :size="15" /></span>
        <h3 class="flex-1 text-base font-extrabold tracking-tight text-ink">최신 부동산</h3>
        <RouterLink to="/realestate" class="text-[13px] font-semibold text-ink-muted hover:text-amber-500 transition-colors">더보기 →</RouterLink>
      </div>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <RouterLink v-for="c in realestateCards.slice(0,4)" :key="c.id" :to="c.to"
          class="block rounded-2xl overflow-hidden border border-line card-hover group bg-white">
          <div class="aspect-[4/3] bg-surface relative overflow-hidden">
            <img v-if="c.image" :src="c.image" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" @error="($event.target.style.display='none')" />
            <div v-else class="absolute inset-0 flex items-center justify-center text-ink-faint">
              <AppIcon name="home" :size="30" :stroke-width="1.5" />
            </div>
            <span class="absolute top-2 left-2 text-[11px] font-bold px-2 py-0.5 rounded-full text-white"
              :class="c.type === 'rent' ? 'bg-[#2E6BFF]/90' : c.type === 'sale' ? 'bg-[#E8442E]/90' : 'bg-[#0EA56B]/90'">
              {{ c.typeLabel }}
            </span>
            <span class="absolute bottom-2 right-2 bg-black/60 backdrop-blur-sm text-white text-[11px] font-bold px-2 py-0.5 rounded-full tabular-nums">
              ${{ Number(c.price).toLocaleString() }}{{ c.type === 'rent' ? '/월' : '' }}
            </span>
          </div>
          <div class="p-2.5">
            <div class="text-[12.5px] font-semibold text-ink-light truncate">{{ c.title }}</div>
          </div>
        </RouterLink>
      </div>
    </section>

    <!-- 광고 (기존 슬롯 유지) -->
    <div class="sm:col-span-2 lg:col-span-2"><AdSlot page="home" position="left" :maxSlots="3" /></div>
    <div class="sm:col-span-2 lg:col-span-1"><AdSlot page="home" position="right" :maxSlots="2" /></div>

    <!-- 모바일 배너 2 -->
    <div class="lg:hidden sm:col-span-2"><MobileBanner page="home" /></div>

    <!-- 가입 CTA -->
    <section v-if="!auth.isLoggedIn" class="cta-banner sm:col-span-2 relative overflow-hidden rounded-card text-white p-7 lg:p-8 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-5">
      <div class="relative z-[1]">
        <h2 class="text-[21px] lg:text-[23px] font-extrabold tracking-tight leading-snug flex items-center gap-2">지금 가입하고 바로 받기 <AppIcon name="gift" :size="20" /></h2>
        <p class="mt-1.5 text-[14.5px] opacity-90">회원가입 10P · 프로필 완성 30P — 포인트로 게임센터와 공동구매를 즐겨보세요.</p>
      </div>
      <RouterLink to="/register" class="relative z-[1] shrink-0 bg-white text-amber-500 px-6 py-3 rounded-full font-extrabold text-[15px] shadow-lg transition-transform hover:-translate-y-0.5">무료로 시작하기</RouterLink>
    </section>

    <!-- 즐겨찾기 퀵링크 -->
    <section class="grid grid-cols-3 gap-2.5 content-start" :class="auth.isLoggedIn ? 'sm:col-span-2 lg:col-span-3 lg:grid-cols-6' : ''">
      <RouterLink v-for="svc in favorites" :key="svc.to" :to="svc.to"
        class="card card-hover py-3.5 px-2 grid place-items-center gap-1.5 text-[12.5px] font-semibold text-ink-light hover:!text-amber-500 hover:!border-amber-300">
        <span class="icon-chip w-9 h-9" :class="menuChipColor(svc.key)">
          <AppIcon :name="menuIcon(svc.key)" :size="18" />
        </span>
        {{ svc.name }}
      </RouterLink>
    </section>

  </main>
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

function clickHeroBanner(b) {
  if (b.link_type === 'event' && b.event_id) router.push('/events?open=' + b.event_id)
  else if (b.link_type === 'page' && b.link_page) router.push(b.link_page)
  else if (b.link_type === 'url' && b.link_url) window.open(b.link_url, '_blank')
}
function startHeroSlide() {
  if (heroBanners.value.length <= 1) return
  heroInterval = setInterval(() => { heroIdx.value = (heroIdx.value + 1) % heroBanners.value.length }, 7000)
}
function pauseHero() { if (heroInterval) { clearInterval(heroInterval); heroInterval = null } }
function resumeHero() { if (!heroInterval && heroBanners.value.length > 1) startHeroSlide() }
onUnmounted(() => { if (heroInterval) clearInterval(heroInterval) })

const popularBoards = [
  { slug: 'free',        name: '자유게시판', visitors: '2.4k', badge: 'HOT' },
  { slug: 'food',        name: '맛집후기',   visitors: '1.8k', badge: 'HOT' },
  { slug: 'immigration', name: '이민생활',   visitors: '1.2k', badge: 'HOT' },
  { slug: 'tips',        name: '생활꿀팁',   visitors: '890',  badge: 'NEW' },
  { slug: 'education',   name: '자녀교육',   visitors: '654',  badge: 'NEW' },
  { slug: 'info',        name: '정보공유',   visitors: '421',  badge: '' },
  { slug: 'health',      name: '건강정보',   visitors: '312',  badge: 'NEW' },
]

const trendingTags = ['이민','영주권','맛집','구인','중고차','부동산','세금','학교','병원','한의원','김치','미용실']

// 모바일 전용 카테고리 그리드 — 아이콘/색상은 menuIcons.js 공용 매핑 사용
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
  { id: 1, title: '버그를 잡아라!',    to: '/events', badge: '포인트', badgeClass: 'badge-primary', icon: 'sparkles' },
  { id: 2, title: '부동산 포인트 2배', to: '/events', badge: '진행중', badgeClass: 'badge-green',   icon: 'building' },
  { id: 3, title: '음악 감상회',       to: '/events', badge: '예정',   badgeClass: 'badge-blue',    icon: 'music' },
  { id: 4, title: '오픈 채팅방',       to: '/events', badge: '상시',   badgeClass: 'badge-pink',    icon: 'message-circle' },
]

const liveUsers = computed(() => 230 + (posts.value.length * 5))

// 라이브 티커: 실제 최신 데이터로 구성 (없으면 기본 문구)
const tickerItems = computed(() => {
  const items = []
  if (market.value[0]) items.push({ label: '중고장터', text: `"${market.value[0].title}" 새 매물이 올라왔어요`, time: '방금 전' })
  if (posts.value[0]) items.push({ label: '커뮤니티', text: `"${posts.value[0].title}" 글이 올라왔어요`, time: '2분 전' })
  if (jobs.value[0]) items.push({ label: '구인구직', text: `"${jobs.value[0].title}" 채용 공고`, time: '5분 전' })
  items.push({ label: '오픈 채팅방', text: `지금 ${liveUsers.value}명 대화 중`, time: 'LIVE' })
  items.push({ label: '이벤트', text: '부동산 포인트 2배 진행 중', time: 'D-3' })
  if (realestate.value[0]) items.push({ label: '부동산', text: `"${realestate.value[0].title}" 새 매물`, time: '10분 전' })
  return items
})
// marquee 무한 루프용 2배 반복
const tickerLoop = computed(() => [...tickerItems.value, ...tickerItems.value])

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

/* 히어로 타이틀 그라데이션 하이라이트 */
.hero-hl {
  background: linear-gradient(120deg, #FF7A30, #FF3D00);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}

/* 라이브 티커 marquee */
.ticker-track { animation: ticker-scroll 40s linear infinite; }
.ticker:hover .ticker-track { animation-play-state: paused; }
@keyframes ticker-scroll { from { transform: translateX(0); } to { transform: translateX(-50%); } }
@media (prefers-reduced-motion: reduce) { .ticker-track { animation: none; } }

/* 실시간 접속 펄스 */
.live-pulse {
  width: 10px; height: 10px; border-radius: 50%;
  background: #0EA56B; position: relative;
}
.live-pulse::after {
  content: ""; position: absolute; inset: -5px; border-radius: 50%;
  border: 2px solid #0EA56B; opacity: .5;
  animation: live-pulse-ring 1.8s ease-out infinite;
}
@keyframes live-pulse-ring {
  from { transform: scale(.6); opacity: .7; }
  to { transform: scale(1.5); opacity: 0; }
}

/* CTA 배너: 그라데이션 + 장식 원 */
.cta-banner { background: linear-gradient(120deg, #FF7A30 0%, #FF4D12 60%, #E8336E 130%); }
.cta-banner::before {
  content: ""; position: absolute; right: -60px; top: -80px;
  width: 260px; height: 260px; border-radius: 50%;
  background: rgba(255, 255, 255, .12);
}
.cta-banner::after {
  content: ""; position: absolute; right: 40px; bottom: -100px;
  width: 200px; height: 200px; border-radius: 50%;
  background: rgba(255, 255, 255, .08);
}
</style>
