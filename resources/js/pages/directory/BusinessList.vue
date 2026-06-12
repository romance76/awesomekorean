<template>
<div class="min-h-screen">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <!-- 헤더: 모바일 -->
    <div class="lg:hidden mb-3">
      <div class="flex items-center justify-between mb-2">
        <h1 class="flex items-center gap-2 text-lg font-bold text-ink">
          <span class="icon-chip w-8 h-8 bg-pink-50 text-pink-600"><AppIcon name="store" :size="18" /></span>
          업소록
        </h1>
        <div class="flex items-center gap-2">
          <button @click="showFilter = true" class="btn-secondary px-3 py-2 rounded-lg text-xs"><AppIcon name="filter" :size="13" />필터</button>
          <RouterLink v-if="auth.isLoggedIn" to="/directory/register" class="btn-primary px-3 py-2 rounded-lg text-xs"><AppIcon name="edit" :size="13" />등록</RouterLink>
        </div>
      </div>
      <div class="flex items-center gap-1.5 overflow-x-auto">
        <span v-if="activeCat" class="badge-primary">
          {{ bizCategories.find(c => c.value === activeCat)?.label || activeCat }}
        </span>
        <span class="badge-gray">
          <AppIcon name="map-pin" :size="11" />{{ selectedCityIdx == -1 ? '전국' : (koreanCities[selectedCityIdx]?.label || '내 위치') }}
        </span>
        <span v-if="search" class="badge-gray">
          "{{ search }}"
        </span>
      </div>
    </div>

    <!-- 모바일 필터 바텀시트 -->
    <MobileFilter v-model="showFilter" @apply="loadPage()" @reset="activeCat = ''; search = ''; selectedCityIdx = '-1'; onCityChange()">
      <div class="mb-4">
        <label class="input-label">지역</label>
        <select v-model="selectedCityIdx" @change="onCityChange"
          class="input-soft">
          <option value="-2" v-if="myCity">📌 내 위치 ({{ myCity.label || myCity.name }})</option>
          <option value="-1">🇺🇸 전국</option>
          <optgroup label="한인 밀집 도시">
            <option v-for="(c, i) in koreanCities" :key="i" :value="i">{{ c.label }}</option>
          </optgroup>
        </select>
      </div>
      <div class="mb-4">
        <label class="input-label">검색어</label>
        <input v-model="search" type="text" placeholder="검색어 입력..."
          class="input-soft" />
      </div>
      <div>
        <label class="input-label">카테고리</label>
        <div class="grid grid-cols-3 gap-1.5">
          <button v-for="c in bizCategories" :key="c.value" @click="activeCat = c.value"
            class="text-xs py-2 rounded-lg font-semibold border transition"
            :class="activeCat === c.value ? 'bg-amber-50 text-amber-700 border-amber-300' : 'border-gray-200 text-ink-light hover:bg-gray-50'">
            {{ c.label }}
          </button>
        </div>
      </div>
    </MobileFilter>

    <!-- 헤더: 데스크탑 -->
    <div class="hidden lg:flex items-center justify-between mb-4 flex-wrap gap-2">
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
        <span class="icon-chip w-9 h-9 bg-pink-50 text-pink-600"><AppIcon name="store" :size="20" /></span>
        업소록
      </h1>
      <div class="flex items-center gap-2 flex-wrap">
        <span class="text-amber-500"><AppIcon name="map-pin" :size="15" /></span>
        <select v-model="selectedCityIdx" @change="onCityChange" class="input-soft w-auto px-2.5 py-1.5 pr-8 text-xs font-semibold">
          <option value="-2" v-if="myCity">📌 내 위치 ({{ myCity.label || myCity.name }})</option>
          <option value="-1">🇺🇸 전국</option>
          <optgroup label="한인 밀집 도시">
            <option v-for="(c, i) in koreanCities" :key="i" :value="i">{{ c.label }}</option>
          </optgroup>
        </select>
        <select v-if="selectedCityIdx !== '-1' && selectedCityIdx !== -1" v-model="radius" @change="loadPage()" class="input-soft w-auto px-2 py-1.5 pr-7 text-xs">
          <option value="10">10mi</option><option value="30">30mi</option><option value="50">50mi</option><option value="100">100mi</option>
        </select>
        <form @submit.prevent="loadPage()" class="flex gap-1">
          <input v-model="search" type="text" placeholder="검색..." class="input-soft w-40 px-3 py-1.5 text-sm" />
          <button type="submit" class="btn-primary px-3 py-1.5 rounded-lg text-xs">검색</button>
        </form>
        <RouterLink v-if="auth.isLoggedIn" to="/directory/register" class="btn-primary px-3 py-1.5 rounded-lg text-xs whitespace-nowrap"><AppIcon name="edit" :size="13" />등록</RouterLink>
      </div>
    </div>

    <div class="grid grid-cols-12 gap-4">
    <!-- 왼쪽: 카테고리 -->
    <div class="col-span-12 lg:col-span-2 hidden lg:block">
      <div class="sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto space-y-3 pr-0.5">
        <div class="card overflow-hidden">
          <div class="px-3 py-2.5 border-b border-gray-50 font-bold text-xs text-amber-700">업종</div>
          <button v-for="c in bizCategories" :key="c.value" @click="showFavorites=false; activeCat=c.value; activeItem=null; loadPage()"
            class="w-full text-left px-3 py-2 text-xs transition-colors"
            :class="!showFavorites && activeCat===c.value ? 'bg-amber-50 text-amber-700 font-bold' : 'text-ink-light hover:bg-gray-50'">{{ c.label }}</button>
          <button v-if="auth.isLoggedIn" @click="showFavorites=true; activeItem=null; loadFavoritesPage()"
            class="w-full text-left px-3 py-2 text-xs transition-colors border-t border-gray-50 flex items-center gap-1"
            :class="showFavorites ? 'bg-red-50 text-red-600 font-bold' : 'text-ink-light hover:bg-red-50/50'">
            <AppIcon name="bookmark" :size="12" />내 북마크<span v-if="favCount > 0" class="ml-0.5">({{ favCount }})</span>
          </button>
        </div>
        <AdSlot page="directory" position="left" :maxSlots="2" />
      </div>
    </div>
    <div class="col-span-12 lg:col-span-7">

    <div class="mb-2">
      <span v-if="showFavorites" class="font-bold text-red-600 text-sm inline-flex items-center gap-1"><AppIcon name="bookmark" :size="14" />내 북마크</span>
      <template v-else>
        <span class="font-bold text-amber-700 text-sm">{{ activeCat ? (bizCategories.find(c => c.value === activeCat)?.label || activeCat) : '전체' }}</span>
        <span v-if="!activeCat" class="text-xs text-ink-muted ml-2">모든 업소를 볼 수 있습니다</span>
      </template>
    </div>

    <!-- 상세 모드 -->
    <div v-if="activeItem">
      <div class="card overflow-hidden">
        <!-- 사진 갤러리 (클릭 확대) -->
        <div v-if="activeItem.images?.length" class="flex gap-1 overflow-x-auto p-2 bg-gray-50">
          <img v-for="(img, i) in activeItem.images" :key="i" :src="img" @click="lightboxImg=img" class="h-32 rounded-lg object-cover flex-shrink-0 cursor-pointer hover:opacity-80 transition" @error="e=>e.target.style.display='none'" />
        </div>
        <div class="px-5 py-4">
          <div class="flex items-start justify-between gap-3">
            <div>
              <span class="badge-primary">{{ activeItem.subcategory || activeItem.category }}</span>
              <h2 class="text-lg font-bold text-ink mt-2">{{ activeItem.name }}</h2>
              <div class="flex items-center gap-1 mt-1"><span class="text-amber-400">{{'★'.repeat(Math.round(activeItem.rating))}}</span><span class="text-sm text-ink-light">{{ activeItem.rating }}</span><span class="text-xs text-ink-muted">({{ activeItem.review_count }}리뷰)</span></div>
            </div>
            <div class="flex-shrink-0 mt-2 flex items-center gap-2">
              <BookmarkToggle v-if="auth.isLoggedIn" :active="favorited.has(activeItem.id)" @toggle="toggleFav(activeItem)" size="lg" />
              <span v-if="activeItem.is_claimed" class="text-xs bg-emerald-50 text-emerald-700 px-3 py-1.5 rounded-full font-bold inline-flex items-center gap-1"><AppIcon name="check" :size="12" />인증업체</span>
              <span v-else-if="claimStatus==='pending'" class="text-xs bg-amber-50 text-amber-700 px-3 py-1.5 rounded-full font-bold inline-flex items-center gap-1"><AppIcon name="clock" :size="12" />승인대기</span>
              <button v-else-if="auth.isLoggedIn" @click="showClaimModal=true" class="btn-primary px-3 py-1.5 rounded-full text-xs"><AppIcon name="store" :size="12" />내가 주인</button>
            </div>
          </div>
        </div>
        <div class="px-5 py-3 border-t border-gray-50 text-sm text-ink-light space-y-1">
          <div v-if="activeItem.phone" class="flex items-center gap-1.5"><AppIcon name="phone" :size="14" class="text-ink-muted" /><a :href="'tel:'+activeItem.phone" class="text-amber-600 hover:underline">{{ activeItem.phone }}</a></div>
          <div v-if="activeItem.address" class="flex items-center gap-1.5"><AppIcon name="map-pin" :size="14" class="text-ink-muted" />{{ activeItem.address }}</div>
          <div v-if="activeItem.website" class="truncate flex items-center gap-1.5"><AppIcon name="globe" :size="14" class="text-ink-muted" /><a :href="activeItem.website" target="_blank" class="text-amber-600 hover:underline truncate">{{ activeItem.website }}</a></div>
        </div>
        <div v-if="activeItem.description" class="px-5 py-4 border-t border-gray-50 text-sm text-ink-light whitespace-pre-wrap">{{ activeItem.description }}</div>
        <!-- 영업시간 + 지도 (좌우 배치) -->
        <div v-if="(activeItem.hours && Object.keys(activeItem.hours).length) || (activeItem.lat && activeItem.lng)" class="px-5 py-3 border-t border-gray-50">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- 왼쪽: 영업시간 -->
            <div v-if="activeItem.hours && Object.keys(activeItem.hours).length">
              <div class="text-xs font-bold text-ink mb-2 flex items-center gap-1"><AppIcon name="clock" :size="13" class="text-ink-muted" />영업시간</div>
              <div v-for="(time, day) in activeItem.hours" :key="day" class="flex justify-between text-xs text-ink-muted py-0.5">
                <span>{{ day }}</span><span class="ml-2">{{ time }}</span>
              </div>
            </div>
            <!-- 오른쪽: 지도 -->
            <div v-if="activeItem.lat && activeItem.lng">
              <div class="text-xs font-bold text-ink mb-2 flex items-center gap-1"><AppIcon name="map-pin" :size="13" class="text-ink-muted" />위치</div>
              <div class="rounded-lg overflow-hidden border border-gray-100">
                <iframe :src="`https://www.openstreetmap.org/export/embed.html?bbox=${Number(activeItem.lng)-0.008},${Number(activeItem.lat)-0.004},${Number(activeItem.lng)+0.008},${Number(activeItem.lat)+0.004}&layer=mapnik&marker=${activeItem.lat},${activeItem.lng}`"
                  class="w-full h-44 border-0" loading="lazy"></iframe>
              </div>
              <a :href="`https://www.google.com/maps/search/?api=1&query=${activeItem.lat},${activeItem.lng}`"
                target="_blank" class="block text-xs text-amber-600 font-bold text-center mt-1 hover:underline">
                Google Maps에서 보기 →
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- 메뉴 섹션 -->
      <div v-if="activeItem.menus?.length" class="card overflow-hidden mt-3">
        <div class="px-5 py-3 border-b border-gray-50 font-bold text-sm text-ink flex items-center gap-1.5"><AppIcon name="utensils" :size="15" class="text-ink-muted" />메뉴 {{ activeItem.menus.length }}개</div>
        <div class="divide-y divide-gray-50">
          <div v-for="menu in activeItem.menus" :key="menu.id" class="px-5 py-3 flex items-center gap-3">
            <div class="flex-1">
              <div class="flex items-center gap-2">
                <span class="text-xs px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 font-bold">{{ {main:'메인',side:'사이드',drink:'음료',dessert:'디저트',set:'세트',other:'기타'}[menu.category] || menu.category }}</span>
                <span class="text-sm font-bold text-ink">{{ menu.name }}</span>
              </div>
              <div v-if="menu.description" class="text-xs text-ink-muted mt-0.5">{{ menu.description }}</div>
              <div v-if="menu.options?.length" class="flex flex-wrap gap-1 mt-1">
                <span v-for="opt in menu.options" :key="opt.name" class="text-[11px] bg-gray-100 px-1.5 py-0.5 rounded text-ink-light">{{ opt.name }} +${{ opt.price }}</span>
              </div>
            </div>
            <div class="text-sm font-black text-amber-700">${{ Number(menu.price).toFixed(2) }}</div>
          </div>
        </div>
      </div>

      <!-- 리뷰 섹션 -->
      <div class="card overflow-hidden mt-3">
        <div class="px-5 py-3 border-b border-gray-50 font-bold text-sm text-ink flex items-center gap-1.5"><AppIcon name="star" :size="15" class="text-amber-400" />리뷰 {{ activeItem.review_count || 0 }}개</div>

        <!-- 리뷰 작성 -->
        <div v-if="auth.isLoggedIn" class="px-5 py-3 border-b border-gray-50">
          <div class="flex items-center gap-1 mb-2">
            <span class="text-xs text-ink-muted mr-1">평점:</span>
            <button v-for="s in 5" :key="s" @click="reviewRating=s" class="text-lg transition" :class="s<=reviewRating?'text-amber-400':'text-gray-300'">★</button>
          </div>
          <textarea v-model="reviewText" rows="2" placeholder="리뷰를 작성하세요..." class="input-soft"></textarea>
          <button @click="submitReview" :disabled="!reviewRating" class="btn-primary mt-2 px-4 py-1.5 rounded-lg text-xs">리뷰 등록</button>
        </div>

        <!-- 구글 리뷰 + 사이트 리뷰 -->
        <div class="divide-y divide-gray-50 max-h-96 overflow-y-auto">
          <div v-for="r in activeReviews" :key="r.id" class="px-5 py-3">
            <div class="flex items-start gap-2">
              <img v-if="r.profile_photo" :src="r.profile_photo" class="w-7 h-7 rounded-full flex-shrink-0 mt-0.5" @error="e=>e.target.style.display='none'" />
              <div class="w-7 h-7 bg-amber-50 rounded-full flex items-center justify-center text-[11px] font-bold text-amber-700 flex-shrink-0 mt-0.5" v-else>{{ (r.user?.name || r.author || '?')[0] }}</div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-0.5">
                  <span class="text-xs font-bold text-ink">{{ r.user?.name || r.author || '익명' }}</span>
                  <span class="text-amber-400 text-[11px]">{{'★'.repeat(r.rating)}}</span>
                  <span class="text-[11px] text-ink-faint">{{ r.relative_time || r.time || formatDate(r.created_at) }}</span>
                </div>
                <div class="text-xs text-ink-light leading-relaxed">{{ r.content || r.text }}</div>
              </div>
            </div>
          </div>
          <div v-if="!activeReviews.length" class="px-5 py-6 text-center text-xs text-ink-muted">아직 리뷰가 없습니다</div>
        </div>
      </div>

      <!-- 이전 / 목록 / 다음 (같은 카테고리 한정, 서버 prev/next) -->
      <div class="mt-4 flex items-stretch card text-sm overflow-hidden">
        <button v-if="bizPrev" @click="openItem(bizPrev)"
          class="flex-1 min-w-0 px-4 py-3 hover:bg-amber-50/60 text-left text-ink-light border-r border-gray-50 transition-colors">
          <div class="text-ink-faint text-xs">← 이전</div>
          <div class="text-xs text-ink-light truncate mt-0.5">{{ bizPrev.title || bizPrev.name || '' }}</div>
        </button>
        <div v-else class="flex-1 min-w-0 px-4 py-3 text-left text-gray-300 border-r border-gray-50 text-xs flex items-center">← 이전 없음</div>

        <button @click="activeItem=null; bizPrev=null; bizNext=null"
          class="px-5 py-3 hover:bg-amber-50/60 text-center text-ink font-bold border-r border-gray-50 flex-shrink-0 transition-colors">
          목록
        </button>

        <button v-if="bizNext" @click="openItem(bizNext)"
          class="flex-1 min-w-0 px-4 py-3 hover:bg-amber-50/60 text-right text-ink-light transition-colors">
          <div class="text-ink-faint text-xs">다음 →</div>
          <div class="text-xs text-ink-light truncate mt-0.5">{{ bizNext.title || bizNext.name || '' }}</div>
        </button>
        <div v-else class="flex-1 min-w-0 px-4 py-3 text-right text-gray-300 text-xs flex items-center justify-end">다음 없음 →</div>
      </div>
    </div>
    <!-- 목록 모드 -->
    <!-- 스켈레톤 로딩 -->
    <div v-else-if="loading" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <div v-for="n in 8" :key="n" class="card overflow-hidden flex h-32 animate-pulse">
        <div class="w-28 flex-shrink-0 bg-gray-200"></div>
        <div class="flex-1 p-3 space-y-2">
          <div class="h-4 bg-gray-200 rounded w-3/4"></div>
          <div class="h-3 bg-gray-100 rounded w-1/2"></div>
          <div class="h-3 bg-gray-100 rounded w-2/3"></div>
          <div class="flex gap-1 mt-2">
            <div class="h-3 w-8 bg-amber-100 rounded"></div>
            <div class="h-3 w-16 bg-gray-100 rounded"></div>
          </div>
        </div>
      </div>
    </div>
    <div v-else-if="!items.length" class="py-16 text-center">
      <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="store" :size="28" :stroke-width="1.5" /></div>
      <p class="text-sm text-ink-muted">검색 결과가 없습니다</p>
      <p class="text-xs text-ink-faint mt-1">다른 도시를 선택하거나 '전국'으로 검색해보세요</p>
    </div>
    <!-- 카드형 (Yelp 스타일) -->
    <div v-else-if="viewMode==='card'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <template v-for="(item, i) in items" :key="item.id">
      <div @click="openItem(item)"
        class="rounded-2xl shadow-card overflow-hidden hover:shadow-lift hover:-translate-y-0.5 transition-all cursor-pointer flex h-32"
        :class="bizPromoClass(item)">
        <!-- 왼쪽: 사진 -->
        <div class="w-28 flex-shrink-0 bg-gray-100">
          <img v-if="item.thumbnail_url || item.images?.length" :src="item.thumbnail_url || thumb(item.images[0], 240)" loading="lazy" decoding="async" class="w-full h-full object-cover" @error="e=>e.target.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center bg-gray-100 text-gray-300\'><svg width=\'28\' height=\'28\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.5\' stroke-linecap=\'round\' stroke-linejoin=\'round\'><rect x=\'3\' y=\'3\' width=\'18\' height=\'18\' rx=\'2\'/><circle cx=\'8.5\' cy=\'8.5\' r=\'1.5\'/><polyline points=\'21 15 16 10 5 21\'/></svg></div>'" />
          <div v-else class="w-full h-full bg-gray-100 flex items-center justify-center text-gray-300"><AppIcon name="store" :size="28" :stroke-width="1.5" /></div>
        </div>
        <!-- 오른쪽: 정보 -->
        <div class="flex-1 p-3 min-w-0">
          <div class="flex items-center gap-1.5 mb-0.5" v-if="item.promotion_tier && item.promotion_tier !== 'none'">
            <span v-if="item.promotion_tier === 'national'" class="inline-flex items-center gap-0.5 text-[11px] bg-red-500 text-white font-bold px-1.5 py-px rounded"><AppIcon name="globe" :size="10" />전국구</span>
            <span v-else-if="item.promotion_tier === 'state_plus'" class="inline-flex items-center gap-0.5 text-[11px] bg-blue-500 text-white font-bold px-1.5 py-px rounded"><AppIcon name="star" :size="10" />주+</span>
            <span v-else-if="item.promotion_tier === 'sponsored'" class="inline-flex items-center gap-0.5 text-[11px] bg-amber-500 text-white font-bold px-1.5 py-px rounded"><AppIcon name="megaphone" :size="10" />스폰서</span>
          </div>
          <div class="flex items-start justify-between gap-1">
            <div class="text-sm font-bold text-ink truncate">{{ item.name }}</div>
            <span v-if="item.is_claimed" class="inline-flex items-center text-[11px] bg-emerald-50 text-emerald-700 px-1.5 py-0.5 rounded-full flex-shrink-0"><AppIcon name="check" :size="11" /></span>
          </div>
          <div class="flex items-center gap-1 mt-0.5">
            <span class="text-amber-400 text-xs">{{'★'.repeat(Math.round(item.rating || 0))}}</span>
            <span class="text-xs text-ink-muted">{{ item.rating || 0 }}</span>
            <span class="text-[11px] text-ink-faint">({{ item.review_count || 0 }})</span>
          </div>
          <div class="text-[11px] text-ink-muted mt-1">{{ item.subcategory || item.category }}</div>
          <div class="text-[11px] text-ink-muted mt-1 flex items-center gap-1">
            <span class="inline-flex items-center gap-0.5"><AppIcon name="map-pin" :size="11" />{{ item.city }}, {{ item.state }}</span>
            <span v-if="item.distance !== undefined && item.distance !== null" class="text-amber-600 font-semibold">{{ Number(item.distance).toFixed(1) }}mi</span>
          </div>
          <div class="flex items-center justify-between mt-0.5">
            <span v-if="item.phone" class="text-[11px] text-ink-faint inline-flex items-center gap-0.5"><AppIcon name="phone" :size="11" />{{ item.phone }}</span>
            <span v-else></span>
            <BookmarkToggle v-if="auth.isLoggedIn" :active="favorited.has(item.id)" @toggle="toggleFav(item)" size="sm" />
          </div>
        </div>
      </div>
      <MobileBanner v-if="i === 4" page="directory" class="col-span-full lg:hidden" />
      </template>
    </div>
    <!-- 리스트형 -->
    <div v-else class="card overflow-hidden">
      <template v-for="(item, i) in items" :key="item.id">
      <div @click="openItem(item)"
        class="list-row border-b border-gray-50">
        <div class="flex items-center justify-between">
          <div class="flex-1 min-w-0">
            <div class="text-sm font-semibold text-ink truncate">{{ item.title || item.name }}</div>
            <div class="text-xs text-ink-muted mt-0.5 flex items-center gap-1.5 flex-wrap">
              <span v-if="item.user?.name || item.company || item.organizer">{{ item.user?.name || item.company || item.organizer }}</span>
              <span v-if="item.city" class="flex items-center gap-0.5"><AppIcon name="map-pin" :size="11" />{{ item.city }}, {{ item.state }}</span>
              <span v-if="item.distance !== undefined && item.distance !== null" class="text-amber-600 font-semibold">{{ Number(item.distance).toFixed(1) }}mi</span>
              <span v-if="item.view_count" class="flex items-center gap-0.5"><AppIcon name="eye" :size="11" />{{ item.view_count }}</span>
            </div>
          </div>
          <div class="ml-3 flex-shrink-0 text-right">
            <div v-if="item.price !== undefined && item.price !== null" class="text-amber-600 font-bold text-sm">${{ Number(item.price).toLocaleString() }}</div>
            <div v-if="item.salary_min" class="text-amber-600 font-bold text-xs">${{ item.salary_min }}~${{ item.salary_max }}/{{ item.salary_type }}</div>
            <div v-if="item.rating" class="text-amber-400 text-xs">{{'★'.repeat(Math.round(item.rating))}} {{ item.rating }}</div>
          </div>
        </div>
      </div>
      <MobileBanner v-if="i === 4" page="directory" class="lg:hidden" />
      </template>
    </div>

    <!-- 📝 텍스트 인라인: 페이지네이션 위 한 줄 -->
    <TextInlineAd v-if="!activeItem" page="directory" class="mt-3" />
    <Pagination v-if="!activeItem" :page="page" :lastPage="lastPage" @page="loadPage" />
    </div>
    <!-- 오른쪽 위젯 -->
    <div class="col-span-12 lg:col-span-3 hidden lg:block">
      <SidebarWidgets :currentCategory="activeItem ? (activeItem.category || '') : activeCat" :inline="true" @select="openItem" api-url="/api/businesses" detail-path="/directory/" :current-id="activeItem?.id || 0"
        :mode="activeItem ? 'detail' : 'list'" label="업소" :filter-params="locationParams"
        :use-page-data="!activeItem" :preloaded-popular="activeItem ? null : sidebarPopular" :preloaded-latest="activeItem ? null : sidebarLatest" />
        <AdSlot page="directory" position="right" :maxSlots="2" />
    </div>
    </div>
  </div>
  <!-- 사진 라이트박스 -->
  <div v-if="lightboxImg" class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4" @click="lightboxImg=null">
    <button @click="lightboxImg=null" class="absolute top-4 right-4 text-white hover:text-gray-300 transition-colors"><AppIcon name="x" :size="28" /></button>
    <img :src="lightboxImg" class="max-w-full max-h-[90vh] rounded-lg shadow-2xl" @click.stop />
  </div>
  <!-- 클레임 모달 -->
  <div v-if="showClaimModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showClaimModal=false">
    <div class="card shadow-xl w-full max-w-md p-5">
      <h3 class="font-bold text-ink mb-3 flex items-center gap-2">
        <span class="icon-chip w-7 h-7 bg-pink-50 text-pink-600"><AppIcon name="store" :size="15" /></span>
        업소 소유권 신청
      </h3>
      <p class="text-sm text-ink-light mb-3">{{ activeItem?.name }}의 실제 운영자임을 확인합니다.</p>
      <div class="mb-3">
        <label class="input-label">연락처 (본인 확인용)</label>
        <input v-model="claimPhone" placeholder="000-000-0000" class="input-soft" />
      </div>
      <div class="mb-3">
        <label class="input-label">사업자등록증 또는 증빙서류</label>
        <input type="file" accept="image/*,.pdf" @change="e => claimFile = e.target.files[0]" class="input-soft text-xs" />
        <p class="text-xs text-ink-faint mt-1">선택사항. 빠른 승인을 위해 증빙서류를 첨부해주세요</p>
      </div>
      <div class="flex gap-2">
        <button @click="submitClaim" :disabled="!claimPhone" class="btn-primary flex-1 py-2 rounded-lg text-sm">신청하기</button>
        <button @click="showClaimModal=false" class="btn-ghost px-4 py-2 text-sm">취소</button>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { useRoute, useRouter } from 'vue-router'
import { ref, computed, watch, onMounted } from 'vue'
import { useLocation } from '../../composables/useLocation'
import { useAuthStore } from '../../stores/auth'
import { useBookmarkStore } from '../../stores/bookmarks'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import { useMenuConfig } from '../../composables/useMenuConfig'
import { thumb } from '../../utils/thumb'
import axios from 'axios'
import AdSlot from '../../components/AdSlot.vue'
import BookmarkToggle from '../../components/BookmarkToggle.vue'
import MobileBanner from '../../components/MobileBanner.vue'
import TextInlineAd from '../../components/TextInlineAd.vue'
import AppIcon from '../../components/AppIcon.vue'

const auth = useAuthStore()
const bStore = useBookmarkStore()
const BM_TYPE = 'App\\Models\\Business'
const route = useRoute()
const router = useRouter()
const { city, radius: locRadius, locationQuery, koreanCities, init: initLocation, selectKoreanCity, setRadius } = useLocation()
const showFilter = ref(false)
const activeCat = ref('')
const { loadConfig, getDefaultView } = useMenuConfig()
const viewMode = ref('list')
const activeItem = ref(null)
const activeReviews = ref([])
const bizPrev = ref(null) // 같은 카테고리 내 이전 (서버 prev)
const bizNext = ref(null) // 같은 카테고리 내 다음 (서버 next)
const lightboxImg = ref(null)
const googleKey = import.meta.env.VITE_GOOGLE_MAPS_KEY || 'AIzaSyAeG46feoDm6HJbre4_FODaxyhz9SBBsAE'
const reviewRating = ref(0)
const reviewText = ref('')
const showClaimModal = ref(false)
const claimPhone = ref('')
const claimFile = ref(null)
const claimStatus = ref(null) // null, 'pending', 'approved'

async function openItem(item) {
  bizPrev.value = null
  bizNext.value = null
  try {
    const { data } = await axios.get(`/api/businesses/${item.id}`)
    activeItem.value = data.data
    bizPrev.value = data.prev || null
    bizNext.value = data.next || null
  }
  catch { activeItem.value = item }
  if (activeItem.value?.category) activeCat.value = activeItem.value.category
  // 리뷰 로드 (구글리뷰 + 사이트리뷰 합치기)
  activeReviews.value = []
  reviewRating.value = 0; reviewText.value = ''
  claimStatus.value = null
  try {
    const { data } = await axios.get(`/api/businesses/${activeItem.value.id}/reviews`)
    const siteReviews = data.data?.data || data.data || []
    const googleReviews = (activeItem.value.google_reviews || []).map((r, i) => ({ id: 'g'+i, author: r.author, rating: r.rating, text: r.text, relative_time: r.time }))
    activeReviews.value = [...siteReviews, ...googleReviews]
  } catch {}
  // 클레임 상태 확인
  if (auth.isLoggedIn) {
    try {
      const { data } = await axios.get(`/api/businesses/${activeItem.value.id}/claim`)
      if (data.data?.status === 'pending') claimStatus.value = 'pending'
      else if (data.data?.status === 'approved') claimStatus.value = 'approved'
    } catch {}
  }
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function submitReview() {
  if (!reviewRating.value || !activeItem.value) return
  try {
    await axios.post(`/api/businesses/${activeItem.value.id}/reviews`, { rating: reviewRating.value, content: reviewText.value })
    reviewText.value = ''; reviewRating.value = 0
    openItem(activeItem.value) // 리뷰 새로고침
  } catch (e) { alert(e.response?.data?.message || '리뷰 등록 실패') }
}

async function submitClaim() {
  try {
    const fd = new FormData()
    fd.append('phone', claimPhone.value)
    if (claimFile.value) fd.append('document', claimFile.value)
    await axios.post(`/api/businesses/${activeItem.value.id}/claim`, fd)
    claimStatus.value = 'pending'
    showClaimModal.value = false
    claimPhone.value = ''
    claimFile.value = null
    alert('소유권 신청이 완료되었습니다. 관리자 승인 후 이용 가능합니다.')
  } catch(e) { alert(e.response?.data?.message || '신청 실패') }
}

function formatDate(dt) { return dt ? new Date(dt).toLocaleDateString('ko-KR') : '' }
const bizCategories = [
  { value: '', label: '전체' },{ value: 'restaurant', label: '음식점' },{ value: 'mart', label: '마트' },
  { value: 'beauty', label: '미용' },{ value: 'medical', label: '의료' },{ value: 'professional', label: '전문서비스' },
  { value: 'auto', label: '자동차' },{ value: 'realestate', label: '부동산' },{ value: 'education', label: '교육' },{ value: 'religion', label: '종교' },{ value: 'etc', label: '기타' },
]

const showFavorites = ref(false)
const favorited = ref(new Set())
const favCount = computed(() => bStore.getBookmarkedIds(BM_TYPE).length)
const items = ref([])
const loading = ref(true)
const page = ref(1)
const lastPage = ref(1)
const search = ref('')
const sidebarPopular = ref(null)
const sidebarLatest = ref(null)
const radius = ref(String(auth.user?.default_radius || 30))
const selectedCityIdx = ref('-2') // -2=내위치, -1=전국, 0~=도시
const myCity = ref(null)

const locationParams = computed(() => {
  const idx = parseInt(selectedCityIdx.value)
  if (idx === -1) return {}
  let lat, lng
  if (idx >= 0) { lat = koreanCities[idx]?.lat; lng = koreanCities[idx]?.lng }
  else if (myCity.value?.lat) { lat = myCity.value.lat; lng = myCity.value.lng }
  return lat && lng ? { lat, lng, radius: parseInt(radius.value) } : {}
})

const locationInfo = computed(() => {
  if (selectedCityIdx.value === -1 || selectedCityIdx.value === '-1') return '전국 검색 중'
  const c = selectedCityIdx.value === '-2' || selectedCityIdx.value === -2
    ? myCity.value
    : koreanCities[selectedCityIdx.value]
  if (!c) return '위치를 선택해주세요'
  return c.label || c.name + ', ' + c.state + ' 기준 ' + radius.value + 'mi 반경'
})

function onCityChange() {
  const idx = parseInt(selectedCityIdx.value)
  if (idx === -1) {
    // 전국
    radius.value = '0'
  } else if (idx === -2) {
    // 내 위치 복원
    if (myCity.value) {
      selectKoreanCity(-1) // reset first
      city.value = myCity.value
      radius.value = '30'
    }
  } else {
    selectKoreanCity(idx)
    radius.value = '30'
  }
  loadPage()
}

function bizPromoClass(item) {
  if (item.promotion_tier === 'national') return 'bg-white border-2 border-red-400'
  if (item.promotion_tier === 'state_plus') return 'bg-white border-2 border-blue-400'
  if (item.promotion_tier === 'sponsored') return 'bg-white border-2 border-amber-400'
  return 'bg-white'
}

async function loadPage(p = 1) {
  loading.value = true
  page.value = p

  // 항상 랜덤 정렬: 업소록은 카테고리 클릭/재방문마다 다른 순서로 노출되도록
  // seed 는 매 요청마다 갱신 (MySQL RAND(seed) 로 동일 페이지 내 일관성 + 재로드시 변경)
  const params = { page: p, per_page: 16, sort: 'random', rand_seed: Math.floor(Math.random() * 100000) }
  if (search.value) params.search = search.value
  if (activeCat.value) params.category = activeCat.value

  if (radius.value !== '0') {
    // 도시 선택에 따라 좌표 결정
    let lat, lng
    const idx = parseInt(selectedCityIdx.value)
    if (idx >= 0) {
      const kc = koreanCities[idx]
      lat = kc.lat; lng = kc.lng
    } else if (idx === -2 && myCity.value?.lat) {
      lat = myCity.value.lat; lng = myCity.value.lng
    } else {
      const loc = locationQuery.value
      lat = loc.lat; lng = loc.lng
    }

    if (lat && lng) {
      params.lat = lat
      params.lng = lng
      params.radius = parseInt(radius.value)
    }
  }

  try {
    const { data } = await axios.get('/api/businesses/page-data', { params })
    const pd = data.data || {}
    items.value = pd.main?.data || []
    lastPage.value = pd.main?.last_page || 1
    // 사이드바 데이터도 함께 받음 (SidebarWidgets API 호출 제거)
    if (pd.sidebar_popular) sidebarPopular.value = pd.sidebar_popular
    if (pd.sidebar_latest) sidebarLatest.value = pd.sidebar_latest
  } catch {}
  loading.value = false
  loadFavorited()
}

// 좋아요 (Bookmark)
async function loadFavorited() {
  if (!auth.isLoggedIn || !items.value.length) return
  try {
    const ids = items.value.map(i => i.id).join(',')
    const { data } = await axios.get('/api/bookmarks/check', { params: { type: 'App\\Models\\Business', ids } })
    favorited.value = new Set(data.data || [])
  } catch {}
}
async function toggleFav(item) {
  if (!auth.isLoggedIn) return
  const result = await bStore.toggle(BM_TYPE, item.id)
  if (result !== null) {
    if (result) favorited.value.add(item.id)
    else favorited.value.delete(item.id)
    favorited.value = new Set(favorited.value)
  }
}
async function loadFavoritesPage() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/bookmarks', { params: { type: 'App\\Models\\Business', per_page: 50 } })
    const bms = data.data?.data || []
    items.value = bms.map(b => b.bookmarkable).filter(Boolean)
    lastPage.value = 1
    loadFavorited()
  } catch {}
  loading.value = false
}

onMounted(async () => {
  // 병렬: 설정 + 위치 + 북마크 동시 로드, 데이터는 전국으로 즉시 시작
  bStore.loadAll()
  const configP = loadConfig().then(() => { viewMode.value = getDefaultView('directory') })
  // 전국 모드로 즉시 로드 (위치 감지 기다리지 않음)
  selectedCityIdx.value = '-1'
  radius.value = '0'
  loadPage()
  // 위치 감지 완료 후 재로드
  await configP
  await initLocation()
  if (city.value) {
    myCity.value = { ...city.value }
    selectedCityIdx.value = '-2'
    radius.value = '30'
    loadPage() // 위치 기반으로 재로드
  }
  // URL /directory/:id 자동 오픈 (Issue #16)
  await ensureActiveBizFromRoute()
})

async function ensureActiveBizFromRoute() {
  const bizId = route.params.id
  if (!bizId) return
  try {
    const { data } = await axios.get(`/api/businesses/${bizId}`)
    // openItem 로직과 동일하게 상세 진입 처리
    await openItem(data.data)
  } catch (err) {
    if (err.response?.status === 404) router.replace('/404')
  }
}

watch(() => route.params.id, (newId, oldId) => {
  if (oldId && !newId) {
    loadPage()
    activeItem.value = null
    activeReviews.value = []
    return
  }
  if (newId && String(newId) !== String(activeItem.value?.id)) {
    ensureActiveBizFromRoute()
  }
})
</script>