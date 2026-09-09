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

  <!-- ═════ 1. 사진 히어로 + 위젯 벤토 (데스크톱 2열 / 모바일 1열) ═════ -->
  <section class="max-w-7xl mx-auto px-4 lg:px-6 pt-4 lg:pt-7 grid grid-cols-1 lg:grid-cols-[1.28fr_1fr] gap-4 lg:gap-5">

    <!-- 히어로: 관리자 히어로 배너 첫 장을 배경 사진으로 사용, 없으면 웜 그라데이션 -->
    <div class="relative rounded-card overflow-hidden min-h-[320px] lg:min-h-[430px] shadow-card">
      <img v-if="heroImage" :src="heroImage" alt=""
        class="absolute inset-0 w-full h-full object-cover"
        @error="e => e.target.style.display='none'" />
      <div v-else class="absolute inset-0" style="background:linear-gradient(140deg,#2A2017,#1B1613)"></div>
      <div class="absolute inset-0 hero-scrim"></div>
      <div class="relative h-full flex flex-col justify-end p-7 lg:p-10">
        <span class="self-start text-[11.5px] font-bold tracking-wide text-white bg-amber-400 px-3.5 py-1.5 rounded-full">미국 한인 NO.1 커뮤니티</span>
        <h1 class="mt-4 lg:mt-5 text-[30px] lg:text-[46px] font-extrabold leading-[1.16] tracking-[-0.045em] text-white">
          미국에서의 하루,<br>어코와 함께 시작하세요
        </h1>
        <p class="mt-3.5 text-[14.5px] lg:text-base leading-relaxed text-white/80 max-w-[42ch]">
          이민 생활 꿀팁부터 동네 맛집, 구인구직, 중고 거래까지 — 한인들의 일상이 모이는 올인원 플랫폼.
        </p>
        <div class="flex flex-wrap gap-2.5 mt-6">
          <RouterLink v-if="!auth.isLoggedIn" to="/register"
            class="bg-white text-ink font-bold text-[15px] px-6 py-3 rounded-full transition-transform hover:-translate-y-0.5">무료로 시작하기</RouterLink>
          <RouterLink to="/community"
            class="text-white font-semibold text-[15px] px-5 py-3 rounded-full border-[1.5px] border-white/40 transition-colors hover:bg-white/10">둘러보기</RouterLink>
        </div>
      </div>
    </div>

    <!-- 위젯 벤토: 날씨(오렌지) / 환율(웜 그레이) / 접속자(나이트) -->
    <div class="grid grid-rows-[auto_1fr] gap-4 lg:gap-5">
      <div class="grid grid-cols-2 gap-4 lg:gap-5">
        <div class="bg-amber-400 rounded-card p-5 lg:p-6 flex flex-col justify-between text-white">
          <div class="flex items-start justify-between gap-2">
            <span class="text-[11.5px] font-bold tracking-wider text-white/85">애틀랜타 · 오늘</span>
            <AppIcon name="sun" :size="22" :stroke-width="1.8" class="text-white/90" />
          </div>
          <div class="mt-6">
            <div class="text-[34px] lg:text-[40px] font-extrabold tracking-[-0.04em] leading-none">72°F</div>
            <div class="text-[12.5px] text-white/90 mt-2">맑음 · 내일 68° / 모레 75°</div>
          </div>
        </div>
        <div class="bg-surface rounded-card p-5 lg:p-6 flex flex-col justify-between">
          <span class="text-[11.5px] font-bold tracking-wider text-ink-muted">USD → KRW</span>
          <div class="mt-6">
            <div class="text-[28px] lg:text-[32px] font-extrabold tracking-[-0.04em] leading-none text-ink tabular-nums">1,386<span class="text-[16px] text-ink-muted">원</span></div>
            <div class="flex gap-2.5 items-baseline mt-2">
              <span class="text-[12.5px] font-bold text-[#E8442E]">▲ 2.4</span>
              <span class="text-xs text-ink-faint">15분 전</span>
            </div>
          </div>
        </div>
      </div>
      <div class="bg-night rounded-card p-6 lg:p-7 flex flex-col justify-between gap-5">
        <div class="flex items-center gap-2.5">
          <span class="live-pulse shrink-0"></span>
          <span class="text-[11.5px] font-bold tracking-wider text-[#9C9088]">지금 접속 중</span>
        </div>
        <div>
          <div class="text-[36px] lg:text-[44px] font-extrabold tracking-[-0.04em] leading-none text-white tabular-nums">{{ liveUsers }}명</div>
          <div class="text-[13px] text-white/65 mt-2.5">오픈 채팅방에서 대화가 진행 중이에요</div>
        </div>
        <div class="flex flex-wrap gap-1.5">
          <button v-for="t in trendingTags.slice(0, 5)" :key="t"
            @click="router.push({path:'/search',query:{q:t}})"
            class="text-[12.5px] font-semibold text-white/85 bg-white/10 px-3 py-1.5 rounded-full transition-colors hover:bg-white/20">#{{ t }}</button>
        </div>
      </div>
    </div>
  </section>

  <!-- ═════ 2. 이벤트 배너 (관리자 히어로 배너 슬라이드 — 사진 + 좌측 스크림) ═════ -->
  <div v-if="heroBanners.length" class="max-w-7xl mx-auto px-4 lg:px-6 pt-4 lg:pt-5">
    <section class="relative overflow-hidden rounded-card shadow-card h-[180px] md:h-[280px]"
      @mouseenter="pauseHero" @mouseleave="resumeHero">
      <Transition name="hero">
        <div v-if="heroBanners[heroIdx]" :key="heroIdx" @click="clickHeroBanner(heroBanners[heroIdx])"
          class="absolute inset-0 cursor-pointer"
          :style="{ background: heroBanners[heroIdx].bg_color || '#1B1613' }">
          <img v-if="heroBanners[heroIdx].image_url" :src="heroBanners[heroIdx].image_url" alt=""
            class="absolute inset-0 w-full h-full object-cover" />
          <div class="absolute inset-0 banner-scrim"></div>
          <div class="relative h-full flex flex-col justify-center px-6 md:px-11 max-w-[62%]">
            <span v-if="heroBanners[heroIdx].subtitle" class="self-start text-[10.5px] md:text-[11px] font-bold tracking-[0.1em] text-amber-200">
              {{ heroBanners[heroIdx].subtitle }}
            </span>
            <div class="text-[22px] md:text-[36px] font-extrabold tracking-[-0.04em] text-white mt-2 md:mt-3">
              {{ heroBanners[heroIdx].title }}
            </div>
            <span class="self-start mt-4 md:mt-5 bg-white text-ink font-bold text-[13px] md:text-sm px-5 py-2.5 rounded-full">참여하기</span>
          </div>
        </div>
      </Transition>
      <div v-if="heroBanners.length > 1" class="absolute bottom-4 right-5 flex gap-1.5">
        <button v-for="i in heroBanners.length" :key="i" @click="heroIdx = i - 1"
          class="h-1.5 rounded-full transition-all"
          :class="heroIdx === i - 1 ? 'bg-white w-6' : 'bg-white/45 w-1.5'"></button>
      </div>
    </section>
  </div>

  <!-- ═════ 2-M. 모바일 전용: 카테고리 카드 그리드 + 배너 ═════ -->
  <div class="lg:hidden max-w-7xl mx-auto px-4 pt-4">
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
    <MobileBanner page="home" class="mb-1" />
  </div>

  <!-- ═════ 3. 오늘의 커뮤니티 (에디토리얼) + 인기 게시판 ═════ -->
  <section class="max-w-7xl mx-auto px-4 lg:px-6 pt-9 lg:pt-11 grid grid-cols-1 lg:grid-cols-[1.6fr_1fr] gap-6">
    <div>
      <div class="flex items-baseline gap-2.5 mb-4">
        <h2 class="text-[19px] lg:text-xl font-extrabold tracking-[-0.03em] text-ink">오늘의 커뮤니티</h2>
        <span class="hidden sm:inline text-[13.5px] text-ink-muted">지금 가장 많이 읽히는 글</span>
        <span class="flex-1"></span>
        <RouterLink to="/community" class="text-[13.5px] font-semibold text-ink-muted hover:text-amber-500 transition-colors">전체 →</RouterLink>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <!-- 대표 글 -->
        <RouterLink v-if="featurePost" :to="`/community/${featurePost.board?.slug || 'free'}/${featurePost.id}`" class="group block">
          <div class="aspect-[16/10] rounded-2xl overflow-hidden bg-surface border border-line">
            <img v-if="postImage(featurePost)" :src="postImage(featurePost)" alt=""
              class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-[1.03]"
              @error="e => e.target.style.display='none'" />
            <div v-else class="w-full h-full flex items-center justify-center text-ink-faint">
              <AppIcon name="image" :size="28" :stroke-width="1.5" />
            </div>
          </div>
          <div class="mt-3 text-xs font-bold tracking-wide text-amber-500">{{ featurePost.board?.name || '커뮤니티' }}</div>
          <h3 class="mt-1.5 text-[16.5px] font-bold tracking-[-0.02em] text-ink leading-snug">{{ featurePost.title }}</h3>
          <div class="mt-2 text-[12.5px] text-ink-muted">
            {{ featurePost.user?.name || '회원' }} · 댓글 {{ featurePost.comments_count || featurePost.comment_count || 0 }}
          </div>
        </RouterLink>

        <!-- 그 외 최신글 -->
        <div class="grid gap-3.5 content-start">
          <RouterLink v-for="p in sidePosts" :key="p.id" :to="`/community/${p.board?.slug || 'free'}/${p.id}`"
            class="flex gap-3 items-start group">
            <div class="shrink-0 w-[72px] h-[72px] rounded-xl overflow-hidden bg-surface border border-line">
              <img v-if="postImage(p)" :src="postImage(p)" alt="" class="w-full h-full object-cover"
                @error="e => e.target.style.display='none'" />
              <div v-else class="w-full h-full flex items-center justify-center text-ink-faint">
                <AppIcon name="image" :size="20" :stroke-width="1.5" />
              </div>
            </div>
            <div class="min-w-0">
              <div class="text-[11.5px] font-bold tracking-wide text-ink-muted">{{ p.board?.name || '커뮤니티' }}</div>
              <div class="mt-1 text-[14.5px] font-semibold text-ink leading-snug group-hover:text-amber-500 transition-colors">{{ p.title }}</div>
              <div class="mt-1 text-xs text-ink-faint">댓글 {{ p.comments_count || p.comment_count || 0 }}</div>
            </div>
          </RouterLink>
        </div>
      </div>
    </div>

    <!-- 인기 게시판 + 트렌딩 -->
    <aside class="bg-surface rounded-card p-5 lg:p-6">
      <div class="flex items-center gap-2.5 mb-3.5">
        <span class="icon-chip w-[30px] h-[30px] bg-amber-50 text-amber-500"><AppIcon name="flame" :size="15" /></span>
        <h2 class="flex-1 text-base font-extrabold tracking-[-0.02em] text-ink">인기 게시판</h2>
        <RouterLink to="/community" class="text-[12.5px] font-semibold text-ink-muted hover:text-amber-500 transition-colors">전체 →</RouterLink>
      </div>
      <ul>
        <li v-for="(b, i) in popularBoards" :key="b.slug">
          <RouterLink :to="`/community/${b.slug}`"
            class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors hover:bg-white"
            :class="i === 0 ? 'bg-white' : ''">
            <span class="w-3.5 text-[13px] font-extrabold shrink-0" :class="i < 3 ? 'text-amber-500' : 'text-ink-muted'">{{ i + 1 }}</span>
            <span class="flex-1 text-[14.5px] font-semibold text-ink truncate">{{ b.name }}</span>
            <span class="text-[12.5px] text-ink-muted tabular-nums shrink-0">{{ b.visitors }}</span>
            <span v-if="b.badge === 'HOT'" class="badge-primary">HOT</span>
            <span v-else-if="b.badge === 'NEW'" class="badge-green">NEW</span>
          </RouterLink>
        </li>
      </ul>
      <div class="h-px bg-line my-4"></div>
      <div class="text-[12.5px] font-bold tracking-wide text-ink-muted mb-2.5">이번 주 트렌딩</div>
      <div class="flex flex-wrap gap-1.5">
        <button v-for="t in trendingTags" :key="t"
          @click="router.push({path:'/search',query:{q:t}})"
          class="text-[13px] font-semibold text-ink-light bg-white px-3 py-1.5 rounded-full transition-all duration-150 hover:bg-amber-400 hover:text-white">#{{ t }}</button>
      </div>
    </aside>
  </section>

  <!-- ═════ 4. 지금 거래 중 (사진 카드 4장: 중고장터 · 구인구직 · 부동산) ═════ -->
  <section v-if="dealCards.length" class="max-w-7xl mx-auto px-4 lg:px-6 pt-9 lg:pt-11">
    <div class="flex items-baseline gap-2.5 mb-4">
      <h2 class="text-[19px] lg:text-xl font-extrabold tracking-[-0.03em] text-ink">지금 거래 중</h2>
      <span class="hidden sm:inline text-[13.5px] text-ink-muted">중고장터 · 구인구직 · 부동산</span>
      <span class="flex-1"></span>
      <RouterLink to="/market" class="text-[13.5px] font-semibold text-ink-muted hover:text-amber-500 transition-colors">더보기 →</RouterLink>
    </div>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
      <RouterLink v-for="c in dealCards" :key="c.key" :to="c.to" class="card card-hover overflow-hidden group">
        <div class="aspect-[4/3] bg-surface relative overflow-hidden">
          <img v-if="c.image" :src="c.image" alt=""
            class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
            @error="e => e.target.style.display='none'" />
          <div v-else class="absolute inset-0 flex items-center justify-center text-ink-faint">
            <AppIcon :name="c.icon" :size="28" :stroke-width="1.5" />
          </div>
        </div>
        <div class="p-3.5">
          <span :class="c.badgeClass">{{ c.badge }}</span>
          <div class="mt-2 text-[14.5px] font-semibold text-ink truncate">{{ c.title }}</div>
          <div class="mt-1.5 text-[17px] font-extrabold tracking-[-0.03em] text-ink tabular-nums">{{ c.price }}</div>
          <div class="mt-1 text-xs text-ink-faint truncate">{{ c.meta }}</div>
        </div>
      </RouterLink>
    </div>
  </section>

  <!-- ═════ 5. 광고 슬롯 (기존 유지) ═════ -->
  <section class="max-w-7xl mx-auto px-4 lg:px-6 pt-9 lg:pt-11 grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2"><AdSlot page="home" position="left" :maxSlots="3" /></div>
    <div><AdSlot page="home" position="right" :maxSlots="2" /></div>
    <div class="lg:hidden"><MobileBanner page="home" /></div>
  </section>

  <!-- ═════ 6. 가입 CTA (나이트) + 즐겨찾기 퀵링크 ═════ -->
  <section class="max-w-7xl mx-auto px-4 lg:px-6 pt-9 lg:pt-11 pb-14">
    <div v-if="!auth.isLoggedIn" class="bg-night rounded-card px-7 py-8 lg:px-12 lg:py-11 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6">
      <div>
        <h2 class="text-[21px] lg:text-[26px] font-extrabold tracking-[-0.035em] text-white flex items-center gap-2">
          지금 가입하고 40포인트 받기 <AppIcon name="gift" :size="20" class="text-amber-300" />
        </h2>
        <p class="mt-2.5 text-[14px] lg:text-[15px] text-white/70">회원가입 10P · 프로필 완성 30P — 포인트로 게임센터와 공동구매를 이용할 수 있어요.</p>
      </div>
      <RouterLink to="/register" class="shrink-0 bg-white text-ink font-bold text-[15px] px-7 py-3.5 rounded-full transition-transform hover:-translate-y-0.5">무료로 시작하기</RouterLink>
    </div>

    <div class="grid grid-cols-3 lg:grid-cols-6 gap-2.5 mt-4">
      <RouterLink v-for="svc in favorites" :key="svc.to" :to="svc.to"
        class="card card-hover py-3.5 px-2 grid place-items-center gap-1.5 text-[12.5px] font-semibold text-ink-light hover:!text-amber-500 hover:!border-amber-300">
        <span class="icon-chip w-9 h-9" :class="menuChipColor(svc.key)">
          <AppIcon :name="menuIcon(svc.key)" :size="18" />
        </span>
        {{ svc.name }}
      </RouterLink>
    </div>
  </section>
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

const liveUsers = computed(() => 230 + (posts.value.length * 5))

// 업로드 이미지 경로 정규화 (DB에 상대 경로로 저장된 경우 /storage/ prefix)
function imgUrl(path) {
  if (!path) return ''
  const s = String(path)
  return s.startsWith('http') || s.startsWith('/') ? s : '/storage/' + s
}
function postImage(p) {
  if (!p) return ''
  return imgUrl(p.images?.[0] || p.image || p.thumbnail || '')
}

// 히어로 배경 사진: 관리자 히어로 배너 중 이미지가 있는 첫 장을 사용
const heroImage = computed(() => {
  const withImg = heroBanners.value.find(b => b.image_url)
  return withImg ? withImg.image_url : ''
})

// 에디토리얼 섹션: 대표 글 1 + 사이드 글 3
const featurePost = computed(() => posts.value[0] || null)
const sidePosts = computed(() => posts.value.slice(1, 4))

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

// 지금 거래 중: 중고장터 2 + 구인 1 + 부동산 1 을 사진 카드로 혼합
const dealCards = computed(() => {
  const cards = []
  market.value.slice(0, 2).forEach(m => cards.push({
    key: 'm' + m.id, to: `/market/${m.id}`,
    image: imgUrl(m.images?.[0] || m.image) || null,
    icon: 'shopping-cart',
    badge: '중고장터', badgeClass: 'badge-green',
    title: m.title,
    price: '$' + Number(m.price || 0).toLocaleString(),
    meta: [m.location, m.city].filter(Boolean).join(' · '),
  }))
  const j = jobs.value[0]
  if (j) cards.push({
    key: 'j' + j.id, to: `/jobs/${j.id}`,
    image: imgUrl(j.logo_url || j.logo) || null,
    icon: 'briefcase',
    badge: '구인구직', badgeClass: 'badge-blue',
    title: j.title,
    price: j.wage || '협의',
    meta: [j.location, j.city].filter(Boolean).join(' · '),
  })
  const r = realestate.value[0]
  if (r) cards.push({
    key: 'r' + r.id, to: `/realestate/${r.id}`,
    image: imgUrl(r.images?.[0] || r.image) || null,
    icon: 'home',
    badge: typeLabels[r.type] || '부동산', badgeClass: 'badge-purple',
    title: r.title,
    price: '$' + Number(r.price || 0).toLocaleString() + (r.type === 'rent' ? '/월' : ''),
    meta: [r.location, r.city].filter(Boolean).join(' · '),
  })
  return cards.slice(0, 4)
})

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

/* 히어로 사진 위 스크림 — 하단 텍스트 가독성 확보 (4.5:1 이상) */
.hero-scrim {
  background: linear-gradient(180deg, rgba(27,22,19,.22) 0%, rgba(27,22,19,.55) 45%, rgba(27,22,19,.9) 100%);
  pointer-events: none;
}
/* 이벤트 배너: 좌측 텍스트 영역만 진하게 */
.banner-scrim {
  background: linear-gradient(90deg, rgba(27,22,19,.88) 0%, rgba(27,22,19,.55) 45%, rgba(27,22,19,.05) 75%);
  pointer-events: none;
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
</style>
