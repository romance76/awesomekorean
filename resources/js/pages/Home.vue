<template>
<div class="min-h-screen">

  <!-- ═════ 0. 라이브 티커 (다크 marquee) — 실제 최신 활동/숫자/시세를 1분마다 새로 받아 보여준다 ═════ -->
  <div v-if="tickerItems.length" ref="tickerEl" class="ticker bg-night text-[#EDE5DD] overflow-hidden" aria-label="실시간 커뮤니티 활동"
    @mouseenter="tickerPaused = true" @mouseleave="tickerPaused = false">
    <div ref="trackEl" class="ticker-track flex py-2 w-max">
      <component :is="t.link ? 'RouterLink' : 'span'" v-for="t in tickerView" :key="t.k" :to="t.link || undefined"
        class="flex items-center gap-2 text-[13px] whitespace-nowrap pr-12 hover:text-white transition-colors">
        <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="t.live ? 'bg-emerald-400 animate-pulse' : 'bg-amber-400'"></span>
        <b class="text-white font-semibold">{{ t.label }}</b>
        <span>{{ t.text }}</span>
        <span v-if="t.quote !== undefined" class="font-bold tabular-nums" :class="t.quote >= 0 ? 'text-[#4ADE80]' : 'text-[#F87171]'">
          {{ t.quote >= 0 ? '▲' : '▼' }}{{ Math.abs(t.quote).toFixed(2) }}%
        </span>
        <span v-if="t.when" class="text-[#8C8178]">{{ t.when }}</span>
      </component>
    </div>
  </div>

  <!-- ═════ 1. 사진 히어로 + 위젯 벤토 (데스크톱 2열 / 모바일 1열) ═════ -->
  <section class="max-w-7xl mx-auto px-4 lg:px-6 pt-4 lg:pt-7 grid grid-cols-1 lg:grid-cols-[1fr_1.28fr] gap-4 lg:gap-5">

    <!-- 언론사별 헤드라인 (네이버 뉴스스탠드 스타일, 원문 링크아웃) — 기존 마케팅 히어로 자리를 대체 -->
    <div class="card p-4 lg:p-5 min-h-[340px] lg:min-h-[440px] flex flex-col order-2">
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
        <RouterLink v-for="h in currentHeadlines" :key="h.id" :to="`/news/${h.id}`"
          class="flex gap-3 items-start group">
          <div class="shrink-0 w-16 h-16 rounded-xl overflow-hidden bg-surface border border-line">
            <img :src="h.image_url" alt="" class="w-full h-full object-cover" @error="dropHeadline(h.id)" />
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

    <!-- 위젯 벤토: 날씨 / NEW 전면광고 / 인기 주식 / 접속자 (2x2) -->
    <div class="grid grid-cols-2 gap-3.5 lg:gap-4 order-1">
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

        <!-- NEW 전면광고 홍보: 지금 방송 중인 전단을 바로 보러 가는 타일 → NEW 게시판 -->
        <RouterLink to="/new"
          class="relative overflow-hidden rounded-card p-4 lg:p-5 flex flex-col justify-between text-white bg-gradient-to-br from-amber-500 via-amber-500 to-amber-400 group hover:brightness-105 transition-all">
          <div class="flex items-center gap-1.5">
            <span class="w-1.5 h-1.5 bg-white rounded-full animate-pulse"></span>
            <span class="text-[10.5px] font-black tracking-wider bg-white/20 px-1.5 py-0.5 rounded">NEW 전면광고</span>
          </div>
          <div class="mt-3">
            <div class="text-[18px] lg:text-[21px] font-extrabold tracking-[-0.03em] leading-tight">신장개업 · 폐업정리<br>내 전단을 맨 앞에 🔥</div>
            <div class="text-[11px] text-white/90 mt-1.5">원하는 시간대만 골라 우리 동네 전체에 알려요</div>
          </div>
          <div class="mt-3 flex items-center justify-between gap-2">
            <span class="text-[11px] font-bold bg-white text-amber-600 rounded-full px-3 py-1.5 group-hover:translate-x-0.5 transition-transform">지금 보러가기 →</span>
            <span class="text-[10px] text-white/90 text-right leading-tight">지금 방송 중인<br>전단 확인</span>
          </div>
        </RouterLink>

        <!-- 인기 주식: 미국 주요 지수/지표 8개를 4초마다 로테이션 + 대표 종목 3개 (미국식 색: 상승 초록 / 하락 빨강) -->
        <RouterLink to="/stocks" class="bg-surface rounded-card p-4 lg:p-5 flex flex-col group hover:brightness-95 transition-all">
          <div class="flex items-center gap-1.5">
            <span class="text-[10.5px] font-bold tracking-wider text-ink-muted">인기 주식</span>
            <AppIcon name="chevron-right" :size="12" class="text-ink-faint group-hover:text-amber-500 transition-colors" />
            <span v-if="quoteTimeET" class="ml-auto text-[9.5px] text-ink-faint">{{ quoteTimeET }}</span>
          </div>
          <div class="flex gap-3 mt-2.5 flex-1">
            <div v-if="currentIndex" class="flex-1 min-w-0">
              <div class="text-[11px] font-semibold text-ink-muted truncate">{{ currentIndex.name }}</div>
              <div class="text-[17px] lg:text-[19px] font-extrabold tracking-[-0.03em] text-ink tabular-nums mt-0.5">
                {{ fmtPrice(currentIndex.price) }}
              </div>
              <div class="text-[10.5px] font-bold mt-0.5" :class="Number(currentIndex.change_pct) >= 0 ? 'text-[#16A34A]' : 'text-[#DC2626]'">
                {{ Number(currentIndex.change_pct) >= 0 ? '▲' : '▼' }} {{ Math.abs(Number(currentIndex.change_pct)).toFixed(2) }}%
              </div>
              <svg v-if="currentIndex.sparkline?.length > 1" viewBox="0 0 100 20" class="w-full h-5 mt-1.5" preserveAspectRatio="none">
                <path :d="sparkPath(currentIndex.sparkline)" fill="none"
                  :stroke="Number(currentIndex.change_pct) >= 0 ? '#16A34A' : '#DC2626'" stroke-width="1.6" vector-effect="non-scaling-stroke" />
              </svg>
            </div>
            <div v-if="watchlist.length" class="w-[86px] shrink-0 flex flex-col justify-center gap-1 border-l border-line pl-2.5">
              <div v-for="w in watchlist.slice(0, 3)" :key="w.symbol" class="text-[10px]">
                <div class="text-ink-muted truncate font-semibold">{{ w.symbol }}</div>
                <div class="font-bold" :class="Number(w.change_pct) >= 0 ? 'text-[#16A34A]' : 'text-[#DC2626]'">
                  {{ Number(w.change_pct) >= 0 ? '▲' : '▼' }}{{ Math.abs(Number(w.change_pct)).toFixed(1) }}%
                </div>
              </div>
            </div>
          </div>
        </RouterLink>

        <!-- 접속자: 오늘 방문자 / 채팅방 사용자를 4초마다 번갈아 보여준다 -->
        <div class="bg-night rounded-card p-4 lg:p-5 flex flex-col justify-between gap-3">
          <div class="flex items-center gap-2">
            <span class="live-pulse shrink-0"></span>
            <span class="text-[10.5px] font-bold tracking-wider text-[#9C9088]">{{ liveSlide === 0 ? '오늘 사이트 이용자' : '오픈 채팅방' }}</span>
          </div>
          <Transition name="live-swap" mode="out-in">
            <div v-if="liveSlide === 0" key="visitors">
              <div class="text-[24px] lg:text-[28px] font-extrabold tracking-[-0.04em] leading-none text-white tabular-nums">{{ liveStats.today_visitors.toLocaleString() }}명</div>
              <div class="text-[12px] text-white/65 mt-1.5">오늘 다녀간 사람 수예요 · 지금 접속 중 {{ liveStats.online_now.toLocaleString() }}명</div>
            </div>
            <div v-else key="chat">
              <div class="text-[24px] lg:text-[28px] font-extrabold tracking-[-0.04em] leading-none text-white tabular-nums">{{ liveStats.chat_users.toLocaleString() }}명</div>
              <div class="text-[12px] text-white/65 mt-1.5">{{ liveStats.chat_users > 0 ? '지금 채팅방에서 대화 중이에요' : '채팅방에서 첫 대화를 시작해 보세요' }}</div>
            </div>
          </Transition>
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

  <!-- ═════ 2-M. 모바일 전용: 배너 ═════ -->
  <div class="lg:hidden max-w-7xl mx-auto px-4 pt-4">
    <MobileBanner page="home" class="mb-1" />
  </div>

  <!-- ═════ 2-R. 내돈내산 리뷰 홍보 (후기가 용돈이 된다면?) ═════ -->
  <section class="max-w-7xl mx-auto px-4 lg:px-6 pt-7 lg:pt-9">
    <div class="card overflow-hidden grid grid-cols-1 lg:grid-cols-[1fr_1.5fr]">
      <div class="p-5 lg:p-7 flex flex-col justify-center">
        <span class="text-xs font-bold" style="color:#FC226B">내돈내산 리뷰</span>
        <h2 class="text-xl lg:text-2xl font-black text-ink leading-snug mt-1" style="letter-spacing:-0.03em">내가 쓴 후기,<br>용돈이 된다면?</h2>
        <p class="text-sm text-ink-light mt-2 leading-relaxed">미국 생활에서 직접 써본 아마존 아이템을 이웃과 나눠 보세요.</p>
        <RouterLink to="/shopping" class="btn-primary self-start mt-4 px-5 py-2.5 text-sm font-bold inline-flex items-center gap-1.5">후기 보러 가기<AppIcon name="arrow-right" :size="14" /></RouterLink>
      </div>
      <div v-if="reviewPicks.length" class="p-3 lg:p-5 bg-amber-50/60 grid grid-cols-2 sm:grid-cols-4 gap-2.5 content-center">
        <RouterLink v-for="p in reviewPicks" :key="p.id" :to="`/shopping/${p.id}`" class="rounded-xl overflow-hidden bg-white border border-gray-100 block">
          <div class="aspect-square bg-white flex items-center justify-center overflow-hidden"><img :src="p.image_url" :alt="p.title" loading="lazy" decoding="async" class="w-full h-full object-contain" @error="e=>e.target.style.display='none'" /></div>
          <div class="px-2 py-1.5 text-[11px] font-semibold text-ink line-clamp-1">{{ p.title }}</div>
        </RouterLink>
      </div>
    </div>
  </section>

  <!-- ═════ 3. 오늘의 커뮤니티 (에디토리얼) + 인기 게시판 ═════ -->
  <section class="max-w-7xl mx-auto px-4 lg:px-6 pt-7 lg:pt-9 grid grid-cols-1 lg:grid-cols-[1.6fr_1fr] gap-6">
    <div>
      <div class="flex items-baseline gap-2.5 mb-4">
        <h2 class="text-[19px] lg:text-xl font-extrabold tracking-[-0.03em] text-ink">오늘의 커뮤니티</h2>
        <span class="hidden sm:inline text-[13.5px] text-ink-muted">지금 가장 많이 읽히는 글</span>
        <span class="flex-1"></span>
        <RouterLink to="/community" class="text-[13.5px] font-semibold text-ink-muted hover:text-amber-500 transition-colors">전체 →</RouterLink>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-[330px_1fr] gap-5">
        <!-- 왼쪽: 렌트/매매 매물 2건을 위아래로 (이미지 폭 300px 고정) -->
        <div class="grid gap-4 content-start">
          <RouterLink v-for="re in homeRealEstateCards" :key="re.id" :to="re.to" class="group block">
            <div class="w-full aspect-[16/10] rounded-2xl overflow-hidden bg-surface border border-line">
              <img v-if="re.image" :src="re.image" alt=""
                class="w-full h-full object-cover"
                @error="e => e.target.style.display='none'" />
              <div v-else class="w-full h-full flex items-center justify-center text-ink-faint">
                <AppIcon name="home" :size="24" :stroke-width="1.5" />
              </div>
            </div>
            <div class="mt-2 text-xs font-bold tracking-wide text-amber-500">{{ re.label }}</div>
            <h3 class="mt-1 text-[14.5px] font-bold tracking-[-0.02em] text-ink leading-snug truncate">{{ re.title }}</h3>
            <div class="mt-1 text-[12.5px] text-ink-muted truncate">{{ re.meta }}</div>
          </RouterLink>
        </div>

        <!-- 오른쪽: 여러 게시판 최신글 (썸네일 없이, 2줄로 압축) -->
        <div class="grid content-start">
          <RouterLink v-for="p in sidePosts" :key="p.id" :to="`/community/${p.board?.slug || 'free'}/${p.id}`"
            class="group block py-2 border-b border-line last:border-b-0">
            <div class="flex items-center justify-between gap-2">
              <span class="text-[11.5px] font-bold tracking-wide text-ink-muted truncate">{{ p.board?.name || '커뮤니티' }}</span>
              <span class="text-[11px] text-ink-faint shrink-0">댓글 {{ p.comments_count || p.comment_count || 0 }}</span>
            </div>
            <div class="mt-0.5 text-[14px] font-semibold text-ink leading-snug truncate group-hover:text-amber-500 transition-colors">{{ p.title }}</div>
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

  <!-- ═════ 5. 광고 슬롯 (광고 없으면 섹션 자체를 렌더링하지 않아 빈 여백이 안 남게 함) ═════ -->
  <section v-if="hasHomeAds" class="max-w-7xl mx-auto px-4 lg:px-6 pt-7 lg:pt-9 grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2"><AdSlot page="home" position="left" :maxSlots="3" /></div>
    <div><AdSlot page="home" position="right" :maxSlots="2" :top-gap="false" /></div>
    <div class="lg:hidden"><MobileBanner page="home" /></div>
  </section>

  <!-- ═════ 6. 가입 CTA (나이트) + 즐겨찾기 퀵링크 ═════ -->
  <section class="max-w-7xl mx-auto px-4 lg:px-6 pt-5 lg:pt-6 pb-6 lg:pb-8">
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
import { useBannerStore } from '../stores/banners'
import AdSlot from '../components/AdSlot.vue'
import MobileBanner from '../components/MobileBanner.vue'
import AppIcon from '../components/AppIcon.vue'
import { menuIcon, menuChipColor } from '../utils/menuIcons'
import { useLocation } from '../composables/useLocation'
import axios from 'axios'

const router = useRouter()
const auth = useAuthStore()
const { locationQuery, init: initLocation } = useLocation()
const lang = useLangStore()
const bannerStore = useBannerStore()
const hasHomeAds = computed(() => bannerStore.getLeft('home').length > 0 || bannerStore.getRight('home').length > 0)
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
onUnmounted(() => { if (heroInterval) clearInterval(heroInterval); if (indexInterval) clearInterval(indexInterval); liveTimers.forEach(clearInterval); cancelAnimationFrame(tickerRaf) })

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
  { slug: 'travel',      name: '여행이야기', visitors: '198',  badge: '' },
  { slug: 'humor',       name: '유머',       visitors: '165',  badge: '' },
  { slug: 'advice',      name: '고민상담',   visitors: '140',  badge: '' },
]

const trendingTags = ['이민','영주권','맛집','구인','중고차','부동산','세금','학교','병원','한의원','김치','미용실']

const favorites = [
  { key: 'community',  name: '커뮤니티', to: '/community' },
  { key: 'qa',         name: 'Q&A',      to: '/qa' },
  { key: 'jobs',       name: '구인구직', to: '/jobs' },
  { key: 'market',     name: '중고장터', to: '/market' },
  { key: 'realestate', name: '부동산',   to: '/realestate' },
  { key: 'directory',  name: '업소록',   to: '/directory' },
]

// ── 실시간 숫자 / 마퀴 ──────────────────────────────────────
const liveStats = ref({ today_visitors: 0, online_now: 0, chat_users: 0 })
const liveSlide = ref(0)                 // 0: 오늘 방문자, 1: 채팅방 사용자
const tickerRaw = ref([])
const nowMs = ref(Date.now())
let liveTimers = []

function relTime(iso) {
  if (!iso) return ''
  const m = Math.max(0, Math.round((nowMs.value - new Date(iso).getTime()) / 60000))
  if (m < 1) return '방금 전'
  if (m < 60) return `${m}분 전`
  if (m < 1440) return `${Math.floor(m / 60)}시간 전`
  return `${Math.floor(m / 1440)}일 전`
}
async function loadLive() {
  try { const { data } = await axios.get('/api/site/live-stats'); liveStats.value = { ...liveStats.value, ...(data.data || {}) } } catch {}
}
async function loadTicker() {
  try { const { data } = await axios.get('/api/site/ticker'); tickerRaw.value = data.data?.items || [] } catch {}
}
function fmtPrice(v) { return Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }

// 업로드 이미지 경로 정규화 (DB에 상대 경로로 저장된 경우 /storage/ prefix)
function imgUrl(path) {
  if (!path) return ''
  const s = String(path)
  return s.startsWith('http') || s.startsWith('/') ? s : '/storage/' + s
}
// 에디토리얼 섹션: 왼쪽 렌트/매매 매물 2건 + 오른쪽 여러 게시판 최신글(썸네일 없이,
// 오른쪽 "인기 게시판" 박스 높이에 맞춰 더 많이) — 룸메이트는 제외
const sidePosts = computed(() => posts.value.slice(0, 10))

const homeRealEstateCards = computed(() => {
  return realestate.value
    .filter(r => r.type === 'rent' || r.type === 'sale')
    .slice(0, 2)
    .map(r => ({
      id: r.id, to: `/realestate/${r.id}`,
      image: imgUrl(r.images?.[0] || r.image),
      label: typeLabels[r.type] || '부동산', title: r.title,
      meta: [r.location, r.city].filter(Boolean).join(' · '),
    }))
})

// 마퀴 항목: 서버가 준 실제 최신 활동/숫자/시세 (시간 표시는 화면에서 계속 새로 계산)
const tickerItems = computed(() => tickerRaw.value.map(t => ({
  ...t,
  when: t.live ? 'LIVE' : (t.tag || (t.at ? relTime(t.at) : '')),
})))
// marquee: 항목을 통째로 2배로 이어 붙인 긴 띠(수천 px)를 움직이면 폰 브라우저에서 일부가 비거나 끊겨 보일 수 있어,
// 화면에 보이는 만큼(+여유)만 그리고 맨 앞 항목이 완전히 지나가면 맨 뒤로 돌려 붙이는 방식으로 계속 흘려보낸다.
// 데이터가 새로 와도 움직임이 처음으로 돌아가지 않고, 이어 붙이는 자리의 어긋남(튐)도 없다.
const tickerEl = ref(null)
const trackEl = ref(null)
const tickerQueue = ref([])               // 지금 그리고 있는 항목들 [{k, i}] — i 는 tickerItems 의 순번(순환)
const tickerPaused = ref(false)
const tickerView = computed(() => {
  const n = tickerItems.value.length
  return n ? tickerQueue.value.map(q => ({ ...tickerItems.value[q.i % n], k: q.k })) : []
})
const TICKER_SPEED = 50                   // 초당 px
let tickerOffset = 0, tickerNext = 0, tickerKey = 0, tickerLast = 0, tickerRaf = 0
const reduceMotion = typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches

function tickerFrame(ts) {
  tickerRaf = requestAnimationFrame(tickerFrame)
  const track = trackEl.value, box = tickerEl.value
  if (!track || !box || !tickerItems.value.length) { tickerLast = 0; return }
  const dt = tickerLast ? Math.min(0.1, (ts - tickerLast) / 1000) : 0
  tickerLast = ts
  if (!tickerPaused.value && !reduceMotion) tickerOffset -= TICKER_SPEED * dt

  const first = track.firstElementChild
  if (first && tickerQueue.value.length > 1 && -tickerOffset >= first.offsetWidth) {   // 맨 앞 항목이 완전히 지나감
    tickerOffset += first.offsetWidth
    tickerQueue.value.shift()
  }
  // 오른쪽 끝이 화면보다 짧아지면 다음 항목을 뒤에 이어 붙인다 (한 프레임에 하나씩)
  if (track.offsetWidth + tickerOffset < box.clientWidth + 300 && tickerQueue.value.length < 60) {
    tickerQueue.value.push({ k: ++tickerKey, i: tickerNext++ })
  }
  track.style.transform = `translate3d(${tickerOffset}px, 0, 0)`
}

const typeLabels = { rent: '렌트', sale: '매매', roommate: '룸메' }

// 언론사별 헤드라인 위젯: 8개씩 묶어서 2열 4행 그리드 + 이전/다음 페이지 (항상 8개씩, 부족한 마지막 페이지는 버림)
const headlineGroups = computed(() => {
  const groups = []
  for (let i = 0; i + 8 <= headlines.value.length; i += 8) groups.push(headlines.value.slice(i, i + 8))
  return groups
})
const currentHeadlines = computed(() => headlineGroups.value[Math.min(headlinePage.value, Math.max(headlineGroups.value.length - 1, 0))] || [])
function nextHeadlinePage() { headlinePage.value = (headlinePage.value + 1) % Math.max(headlineGroups.value.length, 1) }
function prevHeadlinePage() { headlinePage.value = (headlinePage.value - 1 + headlineGroups.value.length) % Math.max(headlineGroups.value.length, 1) }
// 썸네일 로드 실패한 헤드라인은 후보군에서 제거 — 뒤에 받아둔 여분 헤드라인이 자동으로 그 자리를 채운다
function dropHeadline(id) { headlines.value = headlines.value.filter(h => h.id !== id) }
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

// 지수 미니 차트: 최근 N일 값을 0~h 뷰박스에 맞춘 SVG path 로 변환
function sparkPathH(points, h) {
  if (!points || points.length < 2) return ''
  const min = Math.min(...points), max = Math.max(...points)
  const range = (max - min) || 1
  const stepX = 100 / (points.length - 1)
  return points.map((v, i) => `${i === 0 ? 'M' : 'L'} ${(i * stepX).toFixed(2)} ${(h - ((v - min) / range) * (h - 2)).toFixed(2)}`).join(' ')
}
function sparkPath(points) { return sparkPathH(points, 20) }

// 인기 주식 위젯: 미국 지수/지표 8개를 4초마다 로테이션
const currentIndex = computed(() => indices.value[indexIdx.value] || null)
// 시세 기준 시각: 미국 동부시간(ET)
const quoteTimeET = computed(() => {
  const t = currentIndex.value?.quoted_at || currentIndex.value?.updated_at
  if (!t) return ''
  return new Date(t).toLocaleString('en-US', { timeZone: 'America/New_York', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) + ' ET'
})
function startIndexRotation() {
  if (indices.value.length <= 1) return
  indexInterval = setInterval(() => { indexIdx.value = (indexIdx.value + 1) % indices.value.length }, 4000)
}
async function loadMarketQuotes() {
  try {
    const { data } = await axios.get('/api/market-quotes')
    indices.value = data.data?.indices || []
    watchlist.value = data.data?.watchlist || []
    if (!indexInterval) startIndexRotation()
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

// 내돈내산 리뷰 홍보 섹션: 이번 주 인기 후기 4개 (없으면 최신 후기)
const reviewPicks = ref([])
async function loadReviewPicks() {
  try {
    let { data } = await axios.get('/api/shopping', { params: { sort: 'hot' } })
    let list = (data?.data?.data || data?.data || []).filter(p => p.image_url)
    if (list.length < 4) {
      const r = await axios.get('/api/shopping', { params: { sort: 'latest' } })
      const more = (r.data?.data?.data || r.data?.data || []).filter(p => p.image_url && !list.some(x => x.id === p.id))
      list = [...list, ...more]
    }
    reviewPicks.value = list.slice(0, 4)
  } catch {}
}

onMounted(async () => {
  tickerRaf = requestAnimationFrame(tickerFrame)
  loadReviewPicks()
  loadWeather()
  loadMarketQuotes()
  loadLive(); loadTicker()
  liveTimers = [
    setInterval(() => { liveSlide.value = (liveSlide.value + 1) % 2 }, 4500),
    setInterval(() => { nowMs.value = Date.now() }, 30000),
    setInterval(loadLive, 30000),
    setInterval(() => { loadTicker(); loadMarketQuotes() }, 60000),
  ]
  bannerStore.loadForPage('home')
  try {
    const { data } = await axios.get('/api/hero-banners')
    heroBanners.value = data.data || []
    startHeroSlide()
  } catch {}
  // 로그인한 회원이 프로필에 위치를 저장해뒀으면(위치 선택 페이지들과 동일한
  // 로직) 홈 피드의 구인구직/중고장터/부동산/업소록도 그 위치 근처로 보여줌
  // — 저장된 위치가 없으면(또는 비로그인) 기존처럼 전국 최신순 그대로.
  initLocation()
  const loc = locationQuery.value
  const [p, j, m, r, ev, gb, rc, cl, bz, hl] = await Promise.allSettled([
    axios.get('/api/posts?per_page=10'),
    axios.get('/api/jobs', { params: { per_page: 10, ...loc } }),
    axios.get('/api/market', { params: { per_page: 10, ...loc } }),
    axios.get('/api/realestate', { params: { per_page: 6, ...loc } }),
    axios.get('/api/events?per_page=6'),
    axios.get('/api/groupbuys?per_page=6'),
    axios.get('/api/recipes?per_page=6'),
    axios.get('/api/clubs?per_page=6'),
    axios.get('/api/businesses', { params: { per_page: 6, ...loc } }),
    axios.get('/api/external-headlines?per_page=40'),
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

  // 부동산/중고장터 등은 한인 밀집 지역을 매일 랜덤으로 돌며 스크랩하는
  // 특성상 오늘은 내 반경 안에 매물이 아예 없을 수도 있음 — 그 경우 섹션이
  // 통째로 비어 보이는 것보다는 전국 최신순이라도 보여주는 게 나으니,
  // 위치 필터를 걸었는데 결과가 0건인 섹션만 필터 없이 다시 조회.
  if (loc.lat) {
    const fallbacks = []
    if (!jobs.value.length) fallbacks.push(axios.get('/api/jobs?per_page=10').then(({ data }) => { jobs.value = data?.data?.data || [] }))
    if (!market.value.length) fallbacks.push(axios.get('/api/market?per_page=10').then(({ data }) => { market.value = data?.data?.data || [] }))
    if (!realestate.value.length) fallbacks.push(axios.get('/api/realestate?per_page=6').then(({ data }) => { realestate.value = data?.data?.data || data?.data || [] }))
    if (!businesses.value.length) fallbacks.push(axios.get('/api/businesses?per_page=6').then(({ data }) => { businesses.value = data?.data?.data || [] }))
    if (fallbacks.length) await Promise.allSettled(fallbacks)
  }
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

/* 접속자/채팅 숫자 전환 */
.live-swap-enter-active, .live-swap-leave-active { transition: opacity .35s ease, transform .35s ease; }
.live-swap-enter-from { opacity: 0; transform: translateY(6px); }
.live-swap-leave-to { opacity: 0; transform: translateY(-6px); }
/* 라이브 티커 marquee — 움직임은 스크립트(tickerFrame)가 transform 으로 처리 */
.ticker-track { will-change: transform; }

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
