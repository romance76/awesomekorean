<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <!-- 헤더: 모바일 -->
    <div class="lg:hidden mb-3">
      <div class="flex items-center justify-between mb-2">
        <h1 class="text-lg font-black text-gray-800">📋 업소록</h1>
        <div class="flex items-center gap-2">
          <button @click="showFilter = true" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold px-3 py-2 rounded-lg">🔍 필터</button>
          <RouterLink v-if="auth.isLoggedIn" to="/directory/register" class="bg-amber-400 text-amber-900 text-xs font-bold px-3 py-2 rounded-lg">✏️ 등록</RouterLink>
        </div>
      </div>
      <div class="flex items-center gap-1.5 overflow-x-auto">
        <span v-if="activeCat" class="text-[10px] bg-amber-50 text-amber-700 px-2 py-0.5 rounded-full font-semibold whitespace-nowrap">
          {{ bizCategories.find(c => c.value === activeCat)?.label || activeCat }}
        </span>
        <span class="text-[10px] bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-semibold whitespace-nowrap">
          📍{{ selectedCityIdx == -1 ? '전국' : (koreanCities[selectedCityIdx]?.label || '내 위치') }}
        </span>
        <span v-if="search" class="text-[10px] bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-semibold whitespace-nowrap">
          "{{ search }}"
        </span>
      </div>
    </div>

    <!-- 모바일 필터 바텀시트 -->
    <MobileFilter v-model="showFilter" @apply="loadPage()" @reset="activeCat = ''; search = ''; selectedCityIdx = '-1'; onCityChange()">
      <div class="mb-4">
        <label class="text-xs font-bold text-gray-600 mb-2 block">지역</label>
        <select v-model="selectedCityIdx" @change="onCityChange"
          class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-white outline-none focus:ring-2 focus:ring-amber-400">
          <option value="-2" v-if="myCity">📌 내 위치 ({{ myCity.label || myCity.name }})</option>
          <option value="-1">🇺🇸 전국</option>
          <optgroup label="한인 밀집 도시">
            <option v-for="(c, i) in koreanCities" :key="i" :value="i">{{ c.label }}</option>
          </optgroup>
        </select>
      </div>
      <div class="mb-4">
        <label class="text-xs font-bold text-gray-600 mb-2 block">검색어</label>
        <input v-model="search" type="text" placeholder="검색어 입력..."
          class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-amber-400" />
      </div>
      <div>
        <label class="text-xs font-bold text-gray-600 mb-2 block">카테고리</label>
        <div class="grid grid-cols-3 gap-1.5">
          <button v-for="c in bizCategories" :key="c.value" @click="activeCat = c.value"
            class="text-xs py-2 rounded-lg font-semibold border transition"
            :class="activeCat === c.value ? 'bg-amber-50 text-amber-700 border-amber-300' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
            {{ c.label }}
          </button>
        </div>
      </div>
    </MobileFilter>

    <!-- 헤더: 데스크탑 -->
    <div class="hidden lg:flex items-center justify-between mb-4 flex-wrap gap-2">
      <h1 class="text-xl font-black text-gray-800">🏪 업소록</h1>
      <div class="flex items-center gap-2 flex-wrap">
        <span class="text-amber-600 text-sm">📍</span>
        <select v-model="selectedCityIdx" @change="onCityChange" class="border border-gray-200 rounded-lg px-2 py-1.5 text-xs font-semibold text-gray-700 outline-none focus:ring-2 focus:ring-amber-400 bg-amber-50">
          <option value="-2" v-if="myCity">📌 내 위치 ({{ myCity.label || myCity.name }})</option>
          <option value="-1">🇺🇸 전국</option>
          <optgroup label="한인 밀집 도시">
            <option v-for="(c, i) in koreanCities" :key="i" :value="i">{{ c.label }}</option>
          </optgroup>
        </select>
        <select v-if="selectedCityIdx !== '-1' && selectedCityIdx !== -1" v-model="radius" @change="loadPage()" class="border border-gray-200 rounded-lg px-2 py-1.5 text-xs text-gray-600 outline-none">
          <option value="10">10mi</option><option value="30">30mi</option><option value="50">50mi</option><option value="100">100mi</option>
        </select>
        <form @submit.prevent="loadPage()" class="flex gap-1">
          <input v-model="search" type="text" placeholder="검색..." class="border rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-amber-400 outline-none w-40" />
          <button type="submit" class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-amber-500">검색</button>
        </form>
        <RouterLink v-if="auth.isLoggedIn" to="/directory/register" class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-amber-500">✏️ 등록</RouterLink>
      </div>
    </div>

    <div class="grid grid-cols-12 gap-4">
    <!-- 왼쪽: 카테고리 -->
    <div class="col-span-12 lg:col-span-2 hidden lg:block">
      <div class="sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto space-y-3 pr-0.5">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">📋 업종</div>
          <button v-for="c in bizCategories" :key="c.value" @click="showFavorites=false; activeCat=c.value; activeItem=null; loadPage()"
            class="w-full text-left px-3 py-2 text-xs transition"
            :class="!showFavorites && activeCat===c.value ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">{{ c.label }}</button>
          <button v-if="auth.isLoggedIn" @click="showFavorites=true; activeItem=null; loadFavoritesPage()"
            class="w-full text-left px-3 py-2 text-xs transition border-t"
            :class="showFavorites ? 'bg-red-50 text-red-600 font-bold' : 'text-gray-600 hover:bg-red-50/50'">
            🔖 내 북마크<span v-if="favCount > 0" class="ml-0.5">({{ favCount }})</span>
          </button>
        </div>
        <AdSlot page="directory" position="left" :maxSlots="2" />
      </div>
    </div>
    <div class="col-span-12 lg:col-span-7">

    <div class="mb-2">
      <span v-if="showFavorites" class="font-bold text-red-600 text-sm">🔖 내 북마크</span>
      <template v-else>
        <span class="font-bold text-amber-700 text-sm">{{ activeCat ? (bizCategories.find(c => c.value === activeCat)?.label || activeCat) : '전체' }}</span>
        <span v-if="!activeCat" class="text-xs text-gray-400 ml-2">모든 업소를 볼 수 있습니다</span>
      </template>
    </div>

    <!-- 상세 모드 -->
    <div v-if="activeItem">
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <!-- 사진 갤러리 (클릭 확대) -->
        <div v-if="activeItem.images?.length" class="flex gap-1 overflow-x-auto p-2 bg-gray-50">
          <img v-for="(img, i) in activeItem.images" :key="i" :src="img" @click="lightboxImg=img" class="h-32 rounded-lg object-cover flex-shrink-0 cursor-pointer hover:opacity-80 transition" @error="e=>e.target.style.display='none'" />
        </div>
        <div class="px-5 py-4">
          <div class="flex items-start justify-between gap-3">
            <div>
              <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-semibold">{{ activeItem.subcategory || activeItem.category }}</span>
              <h2 class="text-lg font-bold text-gray-900 mt-2">🏪 {{ activeItem.name }}</h2>
              <div class="flex items-center gap-1 mt-1"><span class="text-amber-400">{{'★'.repeat(Math.round(activeItem.rating))}}</span><span class="text-sm text-gray-600">{{ activeItem.rating }}</span><span class="text-xs text-gray-400">({{ activeItem.review_count }}리뷰)</span></div>
            </div>
            <div class="flex-shrink-0 mt-2 flex items-center gap-2">
              <BookmarkToggle v-if="auth.isLoggedIn" :active="favorited.has(activeItem.id)" @toggle="toggleFav(activeItem)" size="lg" />
              <span v-if="activeItem.is_claimed" class="text-xs bg-green-100 text-green-700 px-3 py-1.5 rounded-full font-bold">✅ 인증업체</span>
              <span v-else-if="claimStatus==='pending'" class="text-xs bg-amber-100 text-amber-700 px-3 py-1.5 rounded-full font-bold">⏳ 승인대기</span>
              <button v-else-if="auth.isLoggedIn" @click="showClaimModal=true" class="text-xs bg-amber-400 text-amber-900 px-3 py-1.5 rounded-full font-bold hover:bg-amber-500">🏪 내가 주인</button>
            </div>
          </div>
        </div>
        <div class="px-5 py-3 border-t text-sm text-gray-600 space-y-1">
          <div v-if="activeItem.phone">📱 <a :href="'tel:'+activeItem.phone" class="text-amber-600 hover:underline">{{ activeItem.phone }}</a></div>
          <div v-if="activeItem.address">📍 {{ activeItem.address }}</div>
          <div v-if="activeItem.website" class="truncate">🌐 <a :href="activeItem.website" target="_blank" class="text-amber-600 hover:underline">{{ activeItem.website }}</a></div>
        </div>
        <div v-if="activeItem.description" class="px-5 py-4 border-t text-sm text-gray-700 whitespace-pre-wrap">{{ activeItem.description }}</div>
        <!-- 영업시간 + 지도 (좌우 배치) -->
        <div v-if="(activeItem.hours && Object.keys(activeItem.hours).length) || (activeItem.lat && activeItem.lng)" class="px-5 py-3 border-t">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- 왼쪽: 영업시간 -->
            <div v-if="activeItem.hours && Object.keys(activeItem.hours).length">
              <div class="text-xs font-bold text-gray-700 mb-2">🕐 영업시간</div>
              <div v-for="(time, day) in activeItem.hours" :key="day" class="flex justify-between text-xs text-gray-500 py-0.5">
                <span>{{ day }}</span><span class="ml-2">{{ time }}</span>
              </div>
            </div>
            <!-- 오른쪽: 지도 -->
            <div v-if="activeItem.lat && activeItem.lng">
              <div class="text-xs font-bold text-gray-700 mb-2">📍 위치</div>
              <div class="rounded-lg overflow-hidden border">
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
      <div v-if="activeItem.menus?.length" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mt-3">
        <div class="px-5 py-3 border-b font-bold text-sm text-gray-800">🍽️ 메뉴 {{ activeItem.menus.length }}개</div>
        <div class="divide-y">
          <div v-for="menu in activeItem.menus" :key="menu.id" class="px-5 py-3 flex items-center gap-3">
            <div class="flex-1">
              <div class="flex items-center gap-2">
                <span class="text-xs px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 font-bold">{{ {main:'메인',side:'사이드',drink:'음료',dessert:'디저트',set:'세트',other:'기타'}[menu.category] || menu.category }}</span>
                <span class="text-sm font-bold text-gray-800">{{ menu.name }}</span>
              </div>
              <div v-if="menu.description" class="text-xs text-gray-500 mt-0.5">{{ menu.description }}</div>
              <div v-if="menu.options?.length" class="flex flex-wrap gap-1 mt-1">
                <span v-for="opt in menu.options" :key="opt.name" class="text-[10px] bg-gray-100 px-1.5 py-0.5 rounded text-gray-600">{{ opt.name }} +${{ opt.price }}</span>
              </div>
            </div>
            <div class="text-sm font-black text-amber-700">${{ Number(menu.price).toFixed(2) }}</div>
          </div>
        </div>
      </div>

      <!-- 리뷰 섹션 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mt-3">
        <div class="px-5 py-3 border-b font-bold text-sm text-gray-800">⭐ 리뷰 {{ activeItem.review_count || 0 }}개</div>

        <!-- 리뷰 작성 -->
        <div v-if="auth.isLoggedIn" class="px-5 py-3 border-b">
          <div class="flex items-center gap-1 mb-2">
            <span class="text-xs text-gray-500 mr-1">평점:</span>
            <button v-for="s in 5" :key="s" @click="reviewRating=s" class="text-lg transition" :class="s<=reviewRating?'text-amber-400':'text-gray-300'">★</button>
          </div>
          <textarea v-model="reviewText" rows="2" placeholder="리뷰를 작성하세요..." class="w-full border rounded-lg px-3 py-2 text-sm resize-none focus:ring-2 focus:ring-amber-400 outline-none"></textarea>
          <button @click="submitReview" :disabled="!reviewRating" class="mt-2 bg-amber-400 text-amber-900 font-bold px-4 py-1.5 rounded-lg text-xs hover:bg-amber-500 disabled:opacity-50">리뷰 등록</button>
        </div>

        <!-- 구글 리뷰 + 사이트 리뷰 -->
        <div class="divide-y max-h-96 overflow-y-auto">
          <div v-for="r in activeReviews" :key="r.id" class="px-5 py-3">
            <div class="flex items-start gap-2">
              <img v-if="r.profile_photo" :src="r.profile_photo" class="w-7 h-7 rounded-full flex-shrink-0 mt-0.5" @error="e=>e.target.style.display='none'" />
              <div class="w-7 h-7 bg-amber-100 rounded-full flex items-center justify-center text-[10px] font-bold text-amber-700 flex-shrink-0 mt-0.5" v-else>{{ (r.user?.name || r.author || '?')[0] }}</div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-0.5">
                  <span class="text-xs font-bold text-gray-700">{{ r.user?.name || r.author || '익명' }}</span>
                  <span class="text-amber-400 text-[10px]">{{'★'.repeat(r.rating)}}</span>
                  <span class="text-[10px] text-gray-400">{{ r.relative_time || r.time || formatDate(r.created_at) }}</span>
                </div>
                <div class="text-xs text-gray-600 leading-relaxed">{{ r.content || r.text }}</div>
              </div>
            </div>
          </div>
          <div v-if="!activeReviews.length" class="px-5 py-6 text-center text-xs text-gray-400">아직 리뷰가 없습니다</div>
        </div>
      </div>

      <!-- 이전글/다음글 -->
      <div class="flex justify-between mt-3">
        <button @click="activeItem=null" class="text-xs text-gray-400 hover:text-gray-600">← 목록</button>
      </div>
    </div>
    <!-- 목록 모드 -->
    <!-- 스켈레톤 로딩 -->
    <div v-else-if="loading" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <div v-for="n in 8" :key="n" class="rounded-xl border overflow-hidden flex h-32 animate-pulse">
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
    <div v-else-if="!items.length" class="text-center py-12">
      <div class="text-4xl mb-3">🏪</div>
      <div class="text-gray-500 font-semibold">검색 결과가 없습니다</div>
      <div class="text-xs text-gray-400 mt-1">다른 도시를 선택하거나 '전국'으로 검색해보세요</div>
    </div>
    <!-- 카드형 (Yelp 스타일) -->
    <div v-else-if="viewMode==='card'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <template v-for="(item, i) in items" :key="item.id">
      <div @click="openItem(item)"
        class="rounded-xl shadow-sm border overflow-hidden hover:shadow-md hover:-translate-y-0.5 transition-all cursor-pointer flex h-32"
        :class="bizPromoClass(item)">
        <!-- 왼쪽: 사진 -->
        <div class="w-28 flex-shrink-0 bg-gray-100">
          <img v-if="item.thumbnail_url || item.images?.length" :src="item.thumbnail_url || thumb(item.images[0], 240)" loading="lazy" decoding="async" class="w-full h-full object-cover" @error="e=>e.target.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center text-3xl bg-amber-50\'>🏪</div>'" />
          <div v-else class="w-full h-full flex items-center justify-center text-3xl bg-amber-50">🏪</div>
        </div>
        <!-- 오른쪽: 정보 -->
        <div class="flex-1 p-3 min-w-0">
          <div class="flex items-center gap-1.5 mb-0.5" v-if="item.promotion_tier && item.promotion_tier !== 'none'">
            <span v-if="item.promotion_tier === 'national'" class="text-[9px] bg-red-500 text-white font-bold px-1.5 py-0.5 rounded">🌐 전국구</span>
            <span v-else-if="item.promotion_tier === 'state_plus'" class="text-[9px] bg-blue-500 text-white font-bold px-1.5 py-0.5 rounded">⭐ 주+</span>
            <span v-else-if="item.promotion_tier === 'sponsored'" class="text-[9px] bg-amber-500 text-white font-bold px-1.5 py-0.5 rounded">📢 스폰서</span>
          </div>
          <div class="flex items-start justify-between gap-1">
            <div class="text-sm font-bold text-gray-800 truncate">{{ item.name }}</div>
            <span v-if="item.is_claimed" class="text-[9px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full flex-shrink-0">✅</span>
          </div>
          <div class="flex items-center gap-1 mt-0.5">
            <span class="text-amber-400 text-xs">{{'★'.repeat(Math.round(item.rating || 0))}}</span>
            <span class="text-xs text-gray-500">{{ item.rating || 0 }}</span>
            <span class="text-[10px] text-gray-400">({{ item.review_count || 0 }})</span>
          </div>
          <div class="text-[10px] text-gray-400 mt-1">{{ item.subcategory || item.category }}</div>
          <div class="text-[10px] text-gray-500 mt-1 flex items-center gap-1">
            <span>📍 {{ item.city }}, {{ item.state }}</span>
            <span v-if="item.distance !== undefined && item.distance !== null" class="text-amber-600 font-semibold">{{ Number(item.distance).toFixed(1) }}mi</span>
          </div>
          <div class="flex items-center justify-between mt-0.5">
            <span v-if="item.phone" class="text-[10px] text-gray-400">📱 {{ item.phone }}</span>
            <span v-else></span>
            <BookmarkToggle v-if="auth.isLoggedIn" :active="favorited.has(item.id)" @toggle="toggleFav(item)" size="sm" />
          </div>
        </div>
      </div>
      <MobileAdInline v-if="i === 4" page="directory" />
      </template>
    </div>
    <!-- 리스트형 -->
    <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <template v-for="(item, i) in items" :key="item.id">
      <div @click="openItem(item)"
        class="px-4 py-3 border-b border-gray-50 hover:bg-amber-50/50 hover:border-l-2 hover:border-l-amber-400 transition cursor-pointer">
        <div class="flex items-center justify-between">
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium text-gray-800 truncate">{{ item.title || item.name }}</div>
            <div class="text-xs text-gray-400 mt-0.5 flex items-center gap-1.5 flex-wrap">
              <span v-if="item.user?.name || item.company || item.organizer">{{ item.user?.name || item.company || item.organizer }}</span>
              <span v-if="item.city" class="flex items-center gap-0.5">📍{{ item.city }}, {{ item.state }}</span>
              <span v-if="item.distance !== undefined && item.distance !== null" class="text-amber-600 font-semibold">{{ Number(item.distance).toFixed(1) }}mi</span>
              <span v-if="item.view_count">👁{{ item.view_count }}</span>
            </div>
          </div>
          <div class="ml-3 flex-shrink-0 text-right">
            <div v-if="item.price !== undefined && item.price !== null" class="text-amber-600 font-bold text-sm">${{ Number(item.price).toLocaleString() }}</div>
            <div v-if="item.salary_min" class="text-amber-600 font-bold text-xs">${{ item.salary_min }}~${{ item.salary_max }}/{{ item.salary_type }}</div>
            <div v-if="item.rating" class="text-amber-400 text-xs">{{'★'.repeat(Math.round(item.rating))}} {{ item.rating }}</div>
          </div>
        </div>
      </div>
      <MobileAdInline v-if="i === 4" page="directory" />
      </template>
    </div>

    <Pagination :page="page" :lastPage="lastPage" @page="loadPage" />
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
    <button @click="lightboxImg=null" class="absolute top-4 right-4 text-white text-3xl hover:text-gray-300">✕</button>
    <img :src="lightboxImg" class="max-w-full max-h-[90vh] rounded-lg shadow-2xl" @click.stop />
  </div>
  <!-- 클레임 모달 -->
  <div v-if="showClaimModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showClaimModal=false">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-5">
      <h3 class="font-bold text-gray-800 mb-3">🏪 업소 소유권 신청</h3>
      <p class="text-sm text-gray-600 mb-3">{{ activeItem?.name }}의 실제 운영자임을 확인합니다.</p>
      <div class="mb-3">
        <label class="text-xs font-bold text-gray-600 mb-1 block">연락처 (본인 확인용)</label>
        <input v-model="claimPhone" placeholder="000-000-0000" class="w-full border rounded-lg px-3 py-2 text-sm" />
      </div>
      <div class="mb-3">
        <label class="text-xs font-bold text-gray-600 mb-1 block">사업자등록증 또는 증빙서류</label>
        <input type="file" accept="image/*,.pdf" @change="e => claimFile = e.target.files[0]" class="w-full border rounded-lg px-3 py-2 text-xs" />
        <p class="text-[10px] text-gray-400 mt-1">선택사항. 빠른 승인을 위해 증빙서류를 첨부해주세요</p>
      </div>
      <div class="flex gap-2">
        <button @click="submitClaim" :disabled="!claimPhone" class="flex-1 bg-amber-400 text-amber-900 font-bold py-2 rounded-lg text-sm hover:bg-amber-500 disabled:opacity-50">신청하기</button>
        <button @click="showClaimModal=false" class="px-4 py-2 text-gray-500 text-sm">취소</button>
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
const lightboxImg = ref(null)
const googleKey = import.meta.env.VITE_GOOGLE_MAPS_KEY || 'AIzaSyAeG46feoDm6HJbre4_FODaxyhz9SBBsAE'
const reviewRating = ref(0)
const reviewText = ref('')
const showClaimModal = ref(false)
const claimPhone = ref('')
const claimFile = ref(null)
const claimStatus = ref(null) // null, 'pending', 'approved'

async function openItem(item) {
  try { const { data } = await axios.get(`/api/businesses/${item.id}`); activeItem.value = data.data }
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
  { value: '', label: '전체' },{ value: 'restaurant', label: '🍽️ 음식점' },{ value: 'mart', label: '🛒 마트' },
  { value: 'beauty', label: '💅 미용' },{ value: 'medical', label: '🏥 의료' },{ value: 'professional', label: '💼 전문서비스' },
  { value: 'auto', label: '🚗 자동차' },{ value: 'realestate', label: '🏠 부동산' },{ value: 'education', label: '📚 교육' },{ value: 'religion', label: '⛪ 종교' },{ value: 'etc', label: '📋 기타' },
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
  return 'bg-white border border-gray-100'
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