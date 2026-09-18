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

    <!-- 언론사별 헤드라인 (네이버 뉴스스탠드 스타일, 원문 링크아웃) — 기존 마케팅 히어로 자리를 대체 -->
    <div class="card p-4 lg:p-5 min-h-[340px] lg:min-h-[440px] flex flex-col">
      <div class="flex items-center gap-2.5 mb-3.5">
        <h2 class="text-[15px] font-extrabold tracking-[-0.02em] text-ink">언론사별 헤드라인</h2>
        <span class="flex-1"></span>
        <button v-if="headlineGroups.length > 1" @click="prevHeadlinePage" class="icon-chip w-7 h-7 bg-surface text-ink-muted hover:text-amber-500 transition-colors">
          <AppIcon name="chevron-left" :size="14" />
        </button>
        <span v-if="headlineGroups.length > 1" class="text-[12px] text-ink-faint tabular-nums">{{ headlinePage + 1 }}/{{ headlineGroups.length }}</span>
        <button v-if="headlineGroups.length > 1" @click="nextHeadlinePage" class="icon-chip w-7 h-7 bg-surface text-ink-muted hover:text-amber-500 transition-colors">
          <AppIcon name="chevron-right" :size="14" />
        </button>
      </div>
      <div v-if="currentHeadlines.length" class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 flex-1">
        <RouterLink v-for="h in currentHeadlines" :key="h.id" :to="`/news/external/${h.id}`"
          class="flex gap-3 items-start group">
          <div class="shrink-0 w-16 h-16 rounded-xl overflow-hidden bg-surface border border-line">
            <img :src="h.image_url" alt="" class="w-full h-full object-cover" @error="e => e.target.closest('a').style.display='none'" />
          </div>
          <div class="min-w-0">
            <div class="text-[12px] font-bold text-ink-muted">{{ h.source }}</div>
            <div class="mt-1 text-[13.5px] font-semibold text-ink leading-snug line-clamp-2 group-hover:text-amber-500 transition-colors">{{ h.title }}</div>
            <div class="mt-1 text-[11px] text-ink-faint">{{ headlineTime(h) }}</div>
          </div>
        </RouterLink>
      </div>
      <div v-else class="flex-1 flex items-center justify-center text-[13px] text-ink-faint">헤드라인을 불러오는 중...</div>
    </div>

    <!-- 위젯 벤토: 날씨 / 환율 / 인기 주식 / 접속자 (2x2) -->
    <div class="grid grid-cols-2 gap-3.5 lg:gap-4">
        <!-- 날씨: 실시간(open-meteo) 아이콘형 -->
        <div class="bg-amber-400 rounded-card p-4 lg:p-5 flex flex-col justify-between text-white">
          <div class="flex items-start justify-between gap-2">
            <span class="text-[10.5px] font-bold tracking-wider text-white/85">수와니 · 오늘</span>
            <AppIcon v-if="weather" :name="weatherIcon(weather.code)" :size="18" :stroke-width="1.8" class="text-white/90" />
          </div>
          <div class="mt-3">
            <div class="flex items-baseline gap-1.5">
              <span class="text-[26px] lg:text-[30px] font-extrabold tracking-[-0.04em] leading-none">{{ weather ? weather.temp + '°F' : '--' }}</span>
            </div>
            <div class="text-[11px] text-white/90 mt-1.5">
              {{ weather ? weatherLabel(weather.code) : '불러오는 중' }}
              <span v-if="weather">· {{ weather.minT }}° / {{ weather.maxT }}°</span>
            </div>
            <div v-if="weather" class="text-[10px] text-white/75 mt-0.5">대기질 {{ aqiLabel(weather.aqi) }}</div>
          </div>
          <div v-if="weather?.hourly?.length" class="flex justify-between mt-3 pt-2.5 border-t border-white/20">
            <div v-for="h in weather.hourly" :key="h.hour" class="flex flex-col items-center gap-1">
              <span class="text-[9.5px] text-white/75">{{ h.hour }}시</span>
              <AppIcon :name="weatherIcon(h.code)" :size="13" :stroke-width="2" class="text-white/90" />
              <span class="text-[10px] font-bold tabular-nums">{{ h.temp }}°</span>
            </div>
          </div>
        </div>

        <!-- 환율: 실시간(frankfurter) 미니 그래프 -->
        <div class="bg-surface rounded-card p-4 lg:p-5 flex flex-col justify-between">
          <span class="text-[10.5px] font-bold tracking-wider text-ink-muted">USD → KRW</span>
          <div class="mt-3">
            <div class="text-[22px] lg:text-[26px] font-extrabold tracking-[-0.04em] leading-none text-ink tabular-nums">
              {{ fx ? Math.round(fx.rate).toLocaleString() : '--' }}<span class="text-[13px] text-ink-muted">원</span>
            </div>
            <div v-if="fx" class="flex gap-2 items-baseline mt-1.5">
              <span class="text-[11px] font-bold" :class="fx.change >= 0 ? 'text-[#E8442E]' : 'text-blue-500'">
                {{ fx.change >= 0 ? '▲' : '▼' }} {{ Math.abs(fx.change).toFixed(1) }}
              </span>
              <span class="text-[10.5px] text-ink-faint">{{ fx.date }}</span>
            </div>
          </div>
          <svg v-if="fx?.path" viewBox="0 0 100 28" class="w-full h-7 mt-2.5" preserveAspectRatio="none">
            <path :d="fx.path" fill="none" :stroke="fx.change >= 0 ? '#E8442E' : '#3B82F6'" stroke-width="1.6" vector-effect="non-scaling-stroke" />
          </svg>
        </div>

        <!-- 인기 주식: 지수(나스닥/다우/S&P/코스피) 로테이션 + 관심종목 -->
        <RouterLink to="/stocks" class="bg-surface rounded-card p-4 lg:p-5 flex flex-col group hover:brightness-95 transition-all">
          <div class="flex items-center gap-1.5">
            <span class="text-[10.5px] font-bold tracking-wider text-ink-muted">인기 주식</span>
            <AppIcon name="chevron-right" :size="12" class="text-ink-faint group-hover:text-amber-500 transition-colors" />
          </div>
          <div class="flex gap-3 mt-2.5 flex-1">
            <div v-if="currentIndex" class="flex-1 min-w-0">
              <div class="text-[11px] font-semibold text-ink-muted truncate">{{ currentIndex.name }}</div>
              <div class="text-[17px] lg:text-[19px] font-extrabold tracking-[-0.03em] text-ink tabular-nums mt-0.5">
                {{ Number(currentIndex.price).toLocaleString(undefined, {maximumFractionDigits: 0}) }}
              </div>
              <div class="text-[10.5px] font-bold mt-0.5" :class="Number(currentIndex.change_pct) >= 0 ? 'text-[#E8442E]' : 'text-blue-500'">
                {{ Number(currentIndex.change_pct) >= 0 ? '▲' : '▼' }} {{ Math.abs(Number(currentIndex.change_pct)).toFixed(2) }}%
              </div>
              <svg v-if="currentIndex.sparkline?.length > 1" viewBox="0 0 100 20" class="w-full h-5 mt-1.5" preserveAspectRatio="none">
                <path :d="sparkPath(currentIndex.sparkline)" fill="none"
                  :stroke="Number(currentIndex.change_pct) >= 0 ? '#E8442E' : '#3B82F6'" stroke-width="1.6" vector-effect="non-scaling-stroke" />
              </svg>
            </div>
            <div v-if="watchlist.length" class="w-[86px] shrink-0 flex flex-col justify-center gap-1 border-l border-line pl-2.5">
              <div v-for="w in watchlist.slice(0, 3)" :key="w.symbol" class="text-[10px]">
                <div class="text-ink-muted truncate">{{ w.name }}</div>
                <div class="font-bold" :class="Number(w.change_pct) >= 0 ? 'text-[#E8442E]' : 'text-blue-500'">
                  {{ Number(w.change_pct) >= 0 ? '▲' : '▼' }}{{ Math.abs(Number(w.change_pct)).toFixed(1) }}%
                </div>
              </div>
            </div>
          </div>
        </RouterLink>

        <!-- 접속자 -->
        <div class="bg-night rounded-card p-4 lg:p-5 flex flex-col justify-between gap-3">
          <div class="flex items-center gap-2">
            <span class="live-pulse shrink-0"></span>
            <span class="text-[10.5px] font-bold tracking-wider text-[#9C9088]">지금 접속 중</span>
          </div>
          <div>
            <div class="text-[24px] lg:text-[28px] font-extrabold tracking-[-0.04em] leading-none text-white tabular-nums">{{ liveUsers }}명</div>
            <div class="text-[12px] text-white/65 mt-1.5">오픈 채팅방에서 대화가 진행 중이에요</div>
          </div>
          <div class="flex flex-wrap gap-1.5">
            <button v-for="t in trendingTags.slice(0, 5)" :key="t"
              @click="router.push({path:'/search',query:{q:t}})"
              class="text-[11.5px] font-semibold text-white/85 bg-white/10 px-2.5 py-1 rounded-full transition-colors hover:bg-white/20">#{{ t }}</button>
          </div>
        </div>
    </div>
  </section>

  <!-- ═════ 2. 이벤트 배너 (관리자 히어로 배너 슬라이드 — 사진 + 좌측 스크림) ═════ -->
  <div v-if="heroBanners.length" class="max-w-7xl mx-auto px-4 lg:px-6 pt-4 lg:pt-5">
    <section class="relative overflow-hidden rounded-card shadow-card aspect-[1232/222]"
      @mouseenter="pauseHero" @mouseleave="resumeHero">
      <Transition name="hero">
        <div v-if="heroBanners[heroIdx]" :key="heroIdx" @click="clickHeroBanner(heroBanners[heroIdx])"
          class="absolute inset-0 cursor-pointer"
          :style="{ background: heroBanners[heroIdx].bg_color || '#1B1613' }">
          <img v-if="heroBannerImage(heroBanners[heroIdx])" :src="heroBannerImage(heroBanners[heroIdx])" alt=""
            class="absolute inset-0 w-full h-full object-cover" />
          <template v-if="!heroBanners[heroIdx].image_only">
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
          </template>
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
        <!-- 대표 글 (사진 없는 커뮤니티 글 대신, 사진 있는 다른 섹션 콘텐츠로 대체될 수 있음) -->
        <RouterLink v-if="featureCard" :to="featureCard.to" class="group block">
          <div class="aspect-[16/10] rounded-2xl overflow-hidden bg-surface border border-line">
            <img v-if="featureCard.image" :src="featureCard.image" alt=""
              class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-[1.03]"
              @error="e => e.target.style.display='none'" />
            <div v-else class="w-full h-full flex items-center justify-center text-ink-faint">
              <AppIcon name="image" :size="28" :stroke-width="1.5" />
            </div>
          </div>
          <div class="mt-3 text-xs font-bold tracking-wide text-amber-500">{{ featureCard.label }}</div>
          <h3 class="mt-1.5 text-[16.5px] font-bold tracking-[-0.02em] text-ink leading-snug">{{ featureCard.title }}</h3>
          <div class="mt-2 text-[12.5px] text-ink-muted">{{ featureCard.meta }}</div>
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
import { useLangStore } from '../stores/lang'
import AdSlot from '../components/AdSlot.vue'
import MobileBanner from '../components/MobileBanner.vue'
import AppIcon from '../components/AppIcon.vue'
import { menuIcon, menuChipColor } from '../utils/menuIcons'
import axios from 'axios'

const router = useRouter()
const auth = useAuthStore()
const lang = useLangStore()
const posts = ref([])
const jobs = ref([])
const market = ref([])
const realestate = ref([])
const events = ref([])
const groupbuys = ref([])
const recipes = ref([])
const clubs = ref([])
const businesses = ref([])
const headlines = ref([])
const headlinePage = ref(0)
const weather = ref(null)
const fx = ref(null)
const indices = ref([])
const watchlist = ref([])
const indexIdx = ref(0)
let indexInterval = null
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
onUnmounted(() => { if (heroInterval) clearInterval(heroInterval); if (indexInterval) clearInterval(indexInterval) })

// 영어 모드면 image_url_en 우선 사용, 없으면 기본(한글) 이미지로 폴백
function heroBannerImage(b) {
  if (!b) return ''
  if (lang.locale === 'en' && b.image_url_en) return b.image_url_en
  return b.image_url || ''
}

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

// 에디토리얼 섹션: 대표 글 1 + 사이드 글 3
const featurePost = computed(() => posts.value[0] || null)
const sidePosts = computed(() => posts.value.slice(1, 4))

// 대표 글에 사진이 없으면 텅 빈 박스로 보이는 대신, 사진이 있는 다른 섹션의
// 글(구인구직/중고장터/부동산/이벤트/공동구매/레시피/동호회/업소록 중 하나)을
// 랜덤으로 보여줌 — 뉴스/채팅/게임/쇼츠/친구/음악듣기는 이 자리와 안 어울려서 제외.
const spotlightPool = computed(() => {
  const pool = []
  jobs.value.forEach(j => imgUrl(j.logo_url || j.logo) && pool.push({
    type: 'job', to: `/jobs/${j.id}`, image: imgUrl(j.logo_url || j.logo),
    label: '구인구직', title: j.title, meta: [j.location, j.city].filter(Boolean).join(' · '),
  }))
  market.value.forEach(m => imgUrl(m.images?.[0] || m.image) && pool.push({
    type: 'market', to: `/market/${m.id}`, image: imgUrl(m.images?.[0] || m.image),
    label: '중고장터', title: m.title, meta: '$' + Number(m.price || 0).toLocaleString(),
  }))
  realestate.value.forEach(r => imgUrl(r.images?.[0] || r.image) && pool.push({
    type: 'realestate', to: `/realestate/${r.id}`, image: imgUrl(r.images?.[0] || r.image),
    label: typeLabels[r.type] || '부동산', title: r.title, meta: [r.location, r.city].filter(Boolean).join(' · '),
  }))
  events.value.forEach(e => imgUrl(e.image_url || e.banner_image) && pool.push({
    type: 'event', to: `/events/${e.id}`, image: imgUrl(e.image_url || e.banner_image),
    label: '이벤트', title: e.title, meta: e.organizer || '',
  }))
  groupbuys.value.forEach(g => imgUrl(g.images?.[0]) && pool.push({
    type: 'groupbuy', to: `/groupbuy/${g.id}`, image: imgUrl(g.images?.[0]),
    label: '공동구매', title: g.title, meta: `참여 ${g.current_participants || 0}명`,
  }))
  recipes.value.forEach(rc => imgUrl(rc.thumbnail_url || rc.thumbnail) && pool.push({
    type: 'recipe', to: `/recipes/${rc.id}`, image: imgUrl(rc.thumbnail_url || rc.thumbnail),
    label: '레시피', title: rc.title, meta: '',
  }))
  clubs.value.forEach(c => imgUrl(c.cover_image || c.image) && pool.push({
    type: 'club', to: `/clubs/${c.id}`, image: imgUrl(c.cover_image || c.image),
    label: '동호회', title: c.name, meta: `멤버 ${c.member_count || 0}명`,
  }))
  businesses.value.forEach(b => imgUrl(b.images?.[0] || b.logo) && pool.push({
    type: 'business', to: `/directory/${b.id}`, image: imgUrl(b.images?.[0] || b.logo),
    label: '업소록', title: b.name, meta: [b.category, b.city].filter(Boolean).join(' · '),
  }))
  return pool
})

const featureCard = computed(() => {
  if (featurePost.value && postImage(featurePost.value)) {
    return {
      type: 'post', to: `/community/${featurePost.value.board?.slug || 'free'}/${featurePost.value.id}`,
      image: postImage(featurePost.value), label: featurePost.value.board?.name || '커뮤니티',
      title: featurePost.value.title,
      meta: `${featurePost.value.user?.name || '회원'} · 댓글 ${featurePost.value.comments_count || featurePost.value.comment_count || 0}`,
    }
  }
  const pool = spotlightPool.value
  if (pool.length) return pool[Math.floor(Math.random() * pool.length)]
  if (featurePost.value) {
    return {
      type: 'post', to: `/community/${featurePost.value.board?.slug || 'free'}/${featurePost.value.id}`,
      image: '', label: featurePost.value.board?.name || '커뮤니티', title: featurePost.value.title,
      meta: `${featurePost.value.user?.name || '회원'} · 댓글 ${featurePost.value.comments_count || featurePost.value.comment_count || 0}`,
    }
  }
  return null
})

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

// 언론사별 헤드라인 위젯: 6개씩 묶어서 2열 3행 그리드 + 이전/다음 페이지
const headlineGroups = computed(() => {
  const groups = []
  for (let i = 0; i < headlines.value.length; i += 6) groups.push(headlines.value.slice(i, i + 6))
  return groups
})
const currentHeadlines = computed(() => headlineGroups.value[headlinePage.value] || [])
function nextHeadlinePage() { headlinePage.value = (headlinePage.value + 1) % Math.max(headlineGroups.value.length, 1) }
function prevHeadlinePage() { headlinePage.value = (headlinePage.value - 1 + headlineGroups.value.length) % Math.max(headlineGroups.value.length, 1) }
function headlineTime(h) {
  if (!h.published_at) return ''
  const d = new Date(h.published_at)
  return `${d.getMonth() + 1}월 ${d.getDate()}일 ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}

// 날씨 위젯: WMO weather_code → 아이콘/한글 설명 (open-meteo 기준)
const WEATHER_CODE_MAP = {
  0: ['sun', '맑음'], 1: ['cloud-sun', '대체로 맑음'], 2: ['cloud-sun', '구름 조금'], 3: ['cloud', '흐림'],
  45: ['cloud-fog', '안개'], 48: ['cloud-fog', '안개'],
  51: ['cloud-rain', '이슬비'], 53: ['cloud-rain', '이슬비'], 55: ['cloud-rain', '이슬비'],
  61: ['cloud-rain', '비'], 63: ['cloud-rain', '비'], 65: ['cloud-rain', '강한 비'],
  71: ['cloud-snow', '눈'], 73: ['cloud-snow', '눈'], 75: ['cloud-snow', '폭설'],
  80: ['cloud-rain', '소나기'], 81: ['cloud-rain', '소나기'], 82: ['cloud-rain', '강한 소나기'],
  95: ['cloud-lightning', '뇌우'], 96: ['cloud-lightning', '뇌우'], 99: ['cloud-lightning', '뇌우'],
}
function weatherIcon(code) { return (WEATHER_CODE_MAP[code] || ['cloud', ''])[0] }
function weatherLabel(code) { return (WEATHER_CODE_MAP[code] || ['cloud', '-'])[1] }
function aqiLabel(aqi) {
  if (aqi == null) return ''
  if (aqi <= 50) return '좋음'
  if (aqi <= 100) return '보통'
  if (aqi <= 150) return '민감군 주의'
  return '나쁨'
}

// 환율/지수 미니 차트 공용: 최근 N일 값을 0~h 뷰박스에 맞춘 SVG path 로 변환
function sparkPathH(points, h) {
  if (!points || points.length < 2) return ''
  const min = Math.min(...points), max = Math.max(...points)
  const range = (max - min) || 1
  const stepX = 100 / (points.length - 1)
  return points.map((v, i) => `${i === 0 ? 'M' : 'L'} ${(i * stepX).toFixed(2)} ${(h - ((v - min) / range) * (h - 2)).toFixed(2)}`).join(' ')
}
function fxSparkPath(points) { return sparkPathH(points, 28) }
function sparkPath(points) { return sparkPathH(points, 20) }

// 인기 주식 위젯: 지수 4개(나스닥/다우/S&P/코스피)를 4초마다 로테이션
const currentIndex = computed(() => indices.value[indexIdx.value] || null)
function startIndexRotation() {
  if (indices.value.length <= 1) return
  indexInterval = setInterval(() => { indexIdx.value = (indexIdx.value + 1) % indices.value.length }, 4000)
}
async function loadMarketQuotes() {
  try {
    const { data } = await axios.get('/api/market-quotes')
    indices.value = data.data?.indices || []
    watchlist.value = data.data?.watchlist || []
    startIndexRotation()
  } catch {}
}

// 외부 공개 API(무인증) 직접 호출 — 사이트 axios 인스턴스는 Authorization 헤더가
// 기본 적용돼 있어 제3자 API에 토큰이 새지 않도록 순수 fetch 사용
async function loadWeather() {
  try {
    const lat = 34.0904, lon = -84.0733 // Suwanee, GA
    const [wRes, aRes] = await Promise.all([
      fetch(`https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current=temperature_2m,weather_code&hourly=temperature_2m,weather_code&daily=temperature_2m_max,temperature_2m_min&timezone=America%2FNew_York&temperature_unit=fahrenheit&forecast_days=2`),
      fetch(`https://air-quality-api.open-meteo.com/v1/air-quality?latitude=${lat}&longitude=${lon}&current=us_aqi&timezone=America%2FNew_York`),
    ])
    const w = await wRes.json()
    const a = await aRes.json()
    const nowIdx = w.hourly.time.findIndex(t => t === w.current.time.slice(0, 13) + ':00')
    const startIdx = nowIdx >= 0 ? nowIdx : 0
    weather.value = {
      temp: Math.round(w.current.temperature_2m),
      code: w.current.weather_code,
      maxT: Math.round(w.daily.temperature_2m_max[0]),
      minT: Math.round(w.daily.temperature_2m_min[0]),
      aqi: a?.current?.us_aqi ?? null,
      hourly: w.hourly.time.slice(startIdx + 1, startIdx + 5).map((t, i) => ({
        hour: new Date(t).getHours(),
        temp: Math.round(w.hourly.temperature_2m[startIdx + 1 + i]),
        code: w.hourly.weather_code[startIdx + 1 + i],
      })),
    }
  } catch {}
}
async function loadFx() {
  try {
    const end = new Date()
    const start = new Date(end.getTime() - 9 * 86400000)
    const fmt = d => d.toISOString().slice(0, 10)
    const res = await fetch(`https://api.frankfurter.dev/v1/${fmt(start)}..${fmt(end)}?base=USD&symbols=KRW`)
    const data = await res.json()
    const dates = Object.keys(data.rates).sort()
    const points = dates.map(d => data.rates[d].KRW)
    const latest = points[points.length - 1]
    const prev = points[points.length - 2] ?? latest
    fx.value = {
      rate: latest,
      change: latest - prev,
      changePct: prev ? ((latest - prev) / prev * 100) : 0,
      path: fxSparkPath(points),
      date: dates[dates.length - 1],
    }
  } catch {}
}

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
  loadWeather()
  loadFx()
  loadMarketQuotes()
  try {
    const { data } = await axios.get('/api/hero-banners')
    heroBanners.value = data.data || []
    startHeroSlide()
  } catch {}
  const [p, j, m, r, ev, gb, rc, cl, bz, hl] = await Promise.allSettled([
    axios.get('/api/posts?per_page=10'),
    axios.get('/api/jobs?per_page=10'),
    axios.get('/api/market?per_page=10'),
    axios.get('/api/realestate?per_page=6'),
    axios.get('/api/events?per_page=6'),
    axios.get('/api/groupbuys?per_page=6'),
    axios.get('/api/recipes?per_page=6'),
    axios.get('/api/clubs?per_page=6'),
    axios.get('/api/businesses?per_page=6'),
    axios.get('/api/external-headlines?per_page=24'),
  ])
  if (p.status === 'fulfilled') posts.value = p.value.data?.data?.data || []
  if (j.status === 'fulfilled') jobs.value = j.value.data?.data?.data || []
  if (m.status === 'fulfilled') market.value = m.value.data?.data?.data || []
  if (r.status === 'fulfilled') realestate.value = r.value.data?.data?.data || r.value.data?.data || []
  if (ev.status === 'fulfilled') events.value = ev.value.data?.data?.data || []
  if (gb.status === 'fulfilled') groupbuys.value = gb.value.data?.data?.data || []
  if (rc.status === 'fulfilled') recipes.value = rc.value.data?.data?.data || []
  if (cl.status === 'fulfilled') clubs.value = cl.value.data?.data?.data || []
  if (bz.status === 'fulfilled') businesses.value = bz.value.data?.data?.data || []
  if (hl.status === 'fulfilled') headlines.value = hl.value.data?.data || []
})
</script>

<style scoped>
.hero-enter-active, .hero-leave-active { transition: opacity 0.6s ease; }
.hero-enter-from, .hero-leave-to { opacity: 0; }

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
