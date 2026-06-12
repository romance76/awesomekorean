<template>
<div class="min-h-screen">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <!-- 헤더: 모바일 -->
    <div class="lg:hidden mb-3">
      <div class="flex items-center justify-between mb-2">
        <h1 class="flex items-center gap-2 text-lg font-bold text-ink">
          <span class="icon-chip w-8 h-8 bg-violet-50 text-violet-600"><AppIcon name="building" :size="18" /></span>
          부동산
        </h1>
        <div class="flex items-center gap-2">
          <button @click="showFilter = true" class="btn-secondary px-3 py-2 text-xs"><AppIcon name="search" :size="13" />필터</button>
          <RouterLink v-if="auth.isLoggedIn" to="/realestate/write" class="btn-primary px-3 py-2 text-xs"><AppIcon name="edit" :size="13" />등록</RouterLink>
        </div>
      </div>

      <!-- 세그먼트 컨트롤: 매매/렌트/룸메이트 (Issue #23) -->
      <div class="flex bg-gray-100 rounded-xl p-1 mb-2">
        <button v-for="t in reTypeTabs" :key="t.value"
          @click="changeReType(t.value)"
          :class="['flex-1 py-1.5 text-xs font-bold rounded-lg transition-colors flex items-center justify-center gap-1',
            reType===t.value ? `${t.activeBg} bg-white shadow-sm` : 'text-ink-muted hover:text-ink']">
          <AppIcon :name="t.icon" :size="13" /> {{ t.label }}
        </button>
      </div>

      <div class="flex items-center gap-1.5 overflow-x-auto">
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
          <button v-for="c in reCategories" :key="c.value" @click="activeCat = c.value"
            class="text-xs py-2 rounded-lg font-semibold border transition-colors"
            :class="activeCat === c.value ? 'bg-amber-50 text-amber-700 border-amber-300' : 'border-gray-200 text-ink-light hover:bg-gray-50'">
            {{ c.label }}
          </button>
        </div>
      </div>
    </MobileFilter>

    <!-- 헤더: 데스크탑 (좌 타이틀 | 중앙 토글 | 우 컨트롤 — grid 3컬럼) -->
    <div class="hidden lg:grid items-center mb-4 gap-3" style="grid-template-columns: 1fr auto 1fr;">
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink whitespace-nowrap justify-self-start">
        <span class="icon-chip w-9 h-9 bg-violet-50 text-violet-600"><AppIcon name="building" :size="20" /></span>
        부동산
      </h1>

      <!-- 렌트/매매/룸메이트 세그먼트 (정 중앙) -->
      <div class="flex bg-gray-100 rounded-xl p-1">
        <button v-for="t in reTypeTabs" :key="t.value"
          @click="changeReType(t.value)"
          :class="['px-3 py-1.5 text-xs font-bold rounded-lg transition-colors whitespace-nowrap flex items-center gap-1',
            reType===t.value ? `${t.activeBg} bg-white shadow-sm` : 'text-ink-muted hover:text-ink']">
          <AppIcon :name="t.icon" :size="13" /> {{ t.label }}
        </button>
      </div>

      <div class="flex items-center gap-2 flex-nowrap justify-self-end">
        <span class="text-amber-600"><AppIcon name="map-pin" :size="15" /></span>
        <select v-model="selectedCityIdx" @change="onCityChange" class="input-soft w-auto px-2 py-1.5 pr-8 text-xs font-semibold bg-amber-50">
          <option value="-2" v-if="myCity">📌 내 위치 ({{ myCity.label || myCity.name }})</option>
          <option value="-1">🇺🇸 전국</option>
          <optgroup label="한인 밀집 도시">
            <option v-for="(c, i) in koreanCities" :key="i" :value="i">{{ c.label }}</option>
          </optgroup>
        </select>
        <select v-if="selectedCityIdx !== '-1' && selectedCityIdx !== -1" v-model="radius" @change="loadPage()" class="input-soft w-auto px-2 py-1.5 pr-8 text-xs">
          <option value="10">10mi</option><option value="30">30mi</option><option value="50">50mi</option><option value="100">100mi</option>
        </select>
        <form @submit.prevent="loadPage()" class="flex gap-1">
          <input v-model="search" type="text" placeholder="검색..." class="input-soft px-3 py-1.5 text-sm w-40" />
          <button type="submit" class="btn-primary px-3 py-1.5 text-xs">검색</button>
        </form>
        <RouterLink v-if="auth.isLoggedIn" to="/realestate/write" class="btn-primary px-3 py-1.5 text-xs whitespace-nowrap"><AppIcon name="edit" :size="13" />등록</RouterLink>
      </div>
    </div>

    <div class="grid grid-cols-12 gap-4">
    <!-- 왼쪽: 카테고리 -->
    <div class="col-span-12 lg:col-span-2 hidden lg:block">
      <div class="sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto space-y-3 pr-0.5">
        <div class="card overflow-hidden">
          <div class="px-3 py-2.5 border-b border-gray-50 font-bold text-xs flex items-center gap-1"
            :class="reType==='rent' ? 'text-blue-700' : reType==='sale' ? 'text-red-700' : 'text-green-700'">
            <AppIcon :name="reType==='rent' ? 'key' : reType==='sale' ? 'home' : 'users'" :size="13" />
            {{ reType==='rent' ? '렌트' : reType==='sale' ? '매매' : '룸메이트' }} 카테고리
          </div>
          <!-- 전체 -->
          <button @click="showFavorites=false; activeCat=''; activeItem=null; loadPage()"
            class="w-full text-left px-3 py-1.5 text-xs transition-colors"
            :class="!showFavorites && !activeCat ? 'bg-amber-50 text-amber-700 font-bold' : 'text-ink-light hover:bg-amber-50/50'">
            전체
          </button>
          <!-- 세부 카테고리 (렌트/매매 따라 다름) -->
          <template v-for="group in currentSubcats" :key="group.label">
            <div class="px-3 py-1 bg-gray-50 text-[11px] text-ink-muted font-bold border-t border-gray-50">{{ group.label }}</div>
            <button v-for="c in group.items" :key="c.value"
              @click="showFavorites=false; activeCat=c.value; activeItem=null; loadPage()"
              class="w-full text-left px-3 py-1.5 text-[11px] transition-colors pl-5"
              :class="!showFavorites && activeCat===c.value ? 'bg-amber-50 text-amber-700 font-bold' : 'text-ink-light hover:bg-amber-50/50'">
              {{ c.label }}
            </button>
          </template>
          <button v-if="auth.isLoggedIn" @click="showFavorites=true; activeItem=null; loadFavoritesPage()"
            class="w-full flex items-center gap-1 px-3 py-1.5 text-xs transition-colors border-t border-gray-50"
            :class="showFavorites ? 'bg-red-50 text-red-600 font-bold' : 'text-ink-light hover:bg-red-50/50'">
            <AppIcon name="bookmark" :size="12" /> 내 북마크<span v-if="favCount > 0" class="ml-0.5">({{ favCount }})</span>
          </button>
        </div>
        <AdSlot page="realestate" position="left" :maxSlots="2" />
      </div>
    </div>
    <div class="col-span-12 lg:col-span-7">

    <div class="mb-2">
      <span v-if="showFavorites" class="inline-flex items-center gap-1 font-bold text-red-600 text-sm"><AppIcon name="bookmark" :size="14" /> 내 북마크</span>
      <template v-else>
        <span class="inline-flex items-center gap-1 font-bold text-sm" :class="reType==='rent' ? 'text-blue-700' : reType==='sale' ? 'text-red-700' : 'text-green-700'">
          <AppIcon :name="reType==='rent' ? 'key' : reType==='sale' ? 'home' : 'users'" :size="14" />
          {{ reType==='rent' ? '렌트' : reType==='sale' ? '매매' : '룸메이트' }}
          <span v-if="activeCat" class="text-ink-light"> · {{ currentSubcats.flatMap(g=>g.items).find(c=>c.value===activeCat)?.label || activeCat }}</span>
        </span>
        <span v-if="!activeCat" class="text-xs text-ink-muted ml-2">{{ reType==='rent' ? '모든 렌트 매물' : reType==='sale' ? '모든 매매 매물' : '모든 룸메이트 매물' }}</span>
      </template>
    </div>

    <!-- 목록 -->
    <div v-if="loading" class="text-center py-12 text-ink-muted">로딩중...</div>
    <div v-else-if="!items.length" class="py-16 text-center">
      <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="home" :size="28" :stroke-width="1.5" /></div>
      <div class="text-sm text-ink-light font-semibold">검색 결과가 없습니다</div>
      <div class="text-xs text-ink-muted mt-1">다른 도시를 선택하거나 '전국'으로 검색해보세요</div>
    </div>
    <!-- 상세 모드 -->
    <div v-if="activeItem">
      <div class="card overflow-hidden">
        <div class="px-5 py-4">
          <div class="flex items-center gap-2 mb-2">
            <span class="font-bold" :class="activeItem.type==='sale'?'badge-red':activeItem.type==='rent'?'badge-blue':'badge-green'">{{ {rent:'렌트',sale:'매매',roommate:'룸메이트'}[activeItem.type] }}</span>
            <span class="badge-gray">{{ activeItem.property_type }}</span>
          </div>
          <h2 class="text-lg font-bold text-ink">{{ activeItem.title }}</h2>
          <div class="text-2xl font-black text-amber-600 mt-2">${{ Number(activeItem.price).toLocaleString() }}{{ activeItem.type==='rent'?'/월':'' }}</div>
          <div class="grid grid-cols-4 gap-2 mt-3 text-center text-xs text-ink-light">
            <div class="bg-gray-50 rounded-lg py-1.5"><strong>{{ activeItem.bedrooms||'-' }}</strong> 방</div>
            <div class="bg-gray-50 rounded-lg py-1.5"><strong>{{ activeItem.bathrooms||'-' }}</strong> 화장실</div>
            <div class="bg-gray-50 rounded-lg py-1.5"><strong>{{ activeItem.sqft||'-' }}</strong> sqft</div>
            <div class="bg-gray-50 rounded-lg py-1.5"><strong>{{ activeItem.view_count }}</strong>회</div>
          </div>
        </div>
        <div class="px-5 py-4 border-t border-gray-50 text-sm text-ink-light whitespace-pre-wrap">{{ activeItem.content }}</div>
        <div v-if="activeItem.contact_phone||activeItem.contact_email" class="px-5 py-3 border-t border-gray-50 bg-amber-50 text-sm text-ink-light space-y-1">
          <div v-if="activeItem.contact_phone" class="flex items-center gap-1.5"><AppIcon name="phone" :size="14" class="text-amber-600" /> {{ activeItem.contact_phone }}</div>
          <div v-if="activeItem.contact_email" class="flex items-center gap-1.5"><AppIcon name="mail" :size="14" class="text-amber-600" /> {{ activeItem.contact_email }}</div>
        </div>
      </div>
      <div v-if="auth.user?.id === activeItem.user_id" class="flex gap-3 mt-3 justify-end">
        <RouterLink :to="`/realestate/write?edit=${activeItem.id}`" class="inline-flex items-center gap-1 text-xs text-amber-600 hover:text-amber-700 transition-colors"><AppIcon name="edit" :size="13" /> 수정</RouterLink>
        <button @click="deleteActiveItem" class="inline-flex items-center gap-1 text-xs text-red-400 hover:text-red-600 transition-colors"><AppIcon name="trash" :size="13" /> 삭제</button>
      </div>
      <CommentSection v-if="activeItem.id" type="realestate" :typeId="activeItem.id" class="mt-3" />
      <div class="flex justify-between mt-3">
        <button @click="navItem(-1)" :disabled="currentIdx <= 0" class="inline-flex items-center gap-0.5 text-xs text-ink-muted hover:text-amber-600 transition-colors disabled:opacity-30"><AppIcon name="chevron-left" :size="13" /> 이전글</button>
        <button @click="activeItem=null" class="text-xs text-ink-muted hover:text-ink transition-colors">목록</button>
        <button @click="navItem(1)" :disabled="currentIdx >= items.length-1" class="inline-flex items-center gap-0.5 text-xs text-ink-muted hover:text-amber-600 transition-colors disabled:opacity-30">다음글 <AppIcon name="chevron-right" :size="13" /></button>
      </div>
    </div>
    <!-- 목록 모드 -->
    <div v-else-if="!items.length" class="text-center py-12 text-ink-muted">검색 결과 없음</div>
    <!-- 카드형 (Zillow 스타일: 사진 위 + 정보 아래) -->
    <div v-else-if="viewMode==='card'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <template v-for="(item, i) in items" :key="item.id">
      <div @click="openItem(item)"
        class="relative card card-hover overflow-hidden cursor-pointer border-2"
        :class="promoBorderClass(item)">
        <!-- 사진 영역 -->
        <div class="relative h-[120px] bg-gray-100">
          <img v-if="item.images?.length" :src="realEstateThumb(item)" loading="lazy" decoding="async"
            class="w-full h-full object-cover"
            @error="e=>e.target.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center bg-gray-100 text-gray-300\'><svg width=\'32\' height=\'32\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.5\' stroke-linecap=\'round\' stroke-linejoin=\'round\'><path d=\'m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z\'/><polyline points=\'9 22 9 12 15 12 15 22\'/></svg></div>'" />
          <div v-else class="w-full h-full flex items-center justify-center bg-gray-100 text-gray-300">
            <AppIcon :name="item.type==='sale'?'home':item.type==='rent'?'key':'users'" :size="32" :stroke-width="1.5" />
          </div>
          <!-- 좌상단: 프로모션 뱃지 -->
          <div v-if="item.promotion_tier && item.promotion_tier !== 'none'" class="absolute top-2 left-2">
            <span v-if="item.promotion_tier === 'national'" class="inline-flex items-center gap-1 text-[11px] bg-red-500 text-white font-bold px-2 py-1 rounded-lg shadow"><AppIcon name="globe" :size="11" />전국구</span>
            <span v-else-if="item.promotion_tier === 'state_plus'" class="inline-flex items-center gap-1 text-[11px] bg-blue-500 text-white font-bold px-2 py-1 rounded-lg shadow"><AppIcon name="star" :size="11" />주+</span>
            <span v-else-if="item.promotion_tier === 'sponsored'" class="inline-flex items-center gap-1 text-[11px] bg-amber-500 text-white font-bold px-2 py-1 rounded-lg shadow"><AppIcon name="megaphone" :size="11" />스폰서</span>
          </div>
        </div>

        <!-- 정보 영역 -->
        <div class="p-3 space-y-1">
          <!-- 1. 종류/렌트매매 태그 -->
          <div class="flex items-center gap-1.5 flex-wrap">
            <span class="font-bold"
              :class="item.type==='rent' ? 'badge-blue' : item.type==='sale' ? 'badge-red' : 'badge-green'">
              {{ {rent:'렌트',sale:'매매',roommate:'룸메이트'}[item.type] || item.type }}
            </span>
            <span v-if="item.property_type" class="badge-gray font-bold">
              {{ propertyTypeLabel(item.property_type) }}
            </span>
          </div>

          <!-- 2. 가격 -->
          <div class="text-xl font-black text-ink">
            ${{ Number(item.price || 0).toLocaleString() }}<span v-if="item.type==='rent'" class="text-sm font-bold text-ink-muted">/월</span>
          </div>

          <!-- 3. 제목 -->
          <div class="text-sm font-bold text-ink truncate">{{ item.title }}</div>

          <!-- 4. 방/화장실/sqft -->
          <div class="flex items-center gap-2 text-[11px] text-ink-light font-semibold">
            <span v-if="item.bedrooms"><b>{{ item.bedrooms }}</b> bd</span>
            <span v-if="item.bathrooms" class="text-gray-300">|</span>
            <span v-if="item.bathrooms"><b>{{ item.bathrooms }}</b> ba</span>
            <span v-if="item.sqft" class="text-gray-300">|</span>
            <span v-if="item.sqft"><b>{{ Number(item.sqft).toLocaleString() }}</b> sqft</span>
          </div>

          <!-- 5. 위치 + 올린사람 + 올린날짜 -->
          <div class="text-xs text-ink-muted flex items-center gap-1.5 flex-wrap pt-1 border-t border-gray-100 mt-1">
            <span class="flex items-center gap-0.5"><AppIcon name="map-pin" :size="11" /> {{ item.address ? item.address + ', ' : '' }}{{ item.city }}{{ item.state ? ', '+item.state : '' }}</span>
          </div>
          <div class="text-xs text-ink-faint flex items-center gap-1.5">
            <UserName v-if="item.user?.id" :userId="item.user.id" :name="item.user.name" className="text-xs text-ink-faint" />
            <span v-if="item.created_at" class="flex items-center gap-0.5">· <AppIcon name="clock" :size="11" /> {{ fmtDate(item.created_at) }}</span>
            <span v-if="item.distance !== undefined && item.distance !== null" class="text-amber-600 font-semibold">· {{ Number(item.distance).toFixed(1) }}mi</span>
            <BookmarkToggle v-if="auth.isLoggedIn" :active="favorited.has(item.id)" @toggle="toggleFav(item)" size="sm" class="ml-auto" />
          </div>
        </div>
      </div>
      <MobileBanner v-if="i === 4" page="realestate" class="col-span-full lg:hidden" />
      </template>
    </div>
    <!-- 리스트형 -->
    <div v-else class="card overflow-hidden divide-y divide-gray-50">
      <template v-for="(item, i) in items" :key="item.id">
      <div @click="openItem(item)"
        class="flex hover:border-l-2 transition-colors cursor-pointer overflow-hidden"
        :class="promoRowClass(item)">
        <!-- 썸네일 -->
        <div class="w-28 h-24 flex-shrink-0 bg-gray-100">
          <img v-if="item.images?.length"
            :src="realEstateThumb(item)" loading="lazy" decoding="async"
            class="w-full h-full object-cover"
            @error="e=>e.target.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center bg-gray-100 text-gray-300\'><svg width=\'28\' height=\'28\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.5\' stroke-linecap=\'round\' stroke-linejoin=\'round\'><path d=\'m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z\'/><polyline points=\'9 22 9 12 15 12 15 22\'/></svg></div>'" />
          <div v-else class="w-full h-full flex items-center justify-center bg-gray-100 text-gray-300">
            <AppIcon :name="item.type==='sale'?'home':item.type==='rent'?'key':'users'" :size="28" :stroke-width="1.5" />
          </div>
        </div>
        <div class="flex items-center justify-between flex-1 min-w-0 px-4 py-3">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-1.5 mb-0.5">
              <span v-if="item.promotion_tier === 'national'" class="inline-flex items-center gap-0.5 text-[11px] bg-red-500 text-white font-bold px-1.5 py-0.5 rounded-md"><AppIcon name="globe" :size="10" />전국구</span>
              <span v-else-if="item.promotion_tier === 'state_plus'" class="inline-flex items-center gap-0.5 text-[11px] bg-blue-500 text-white font-bold px-1.5 py-0.5 rounded-md"><AppIcon name="star" :size="10" />주+</span>
              <span v-else-if="item.promotion_tier === 'sponsored'" class="inline-flex items-center gap-0.5 text-[11px] bg-amber-500 text-white font-bold px-1.5 py-0.5 rounded-md"><AppIcon name="megaphone" :size="10" />스폰서</span>
            </div>
            <div class="text-sm font-semibold text-ink truncate">{{ item.title || item.name }}</div>
            <div class="text-xs text-ink-muted mt-0.5 flex items-center gap-1.5 flex-wrap">
              <span v-if="item.user?.name"><UserName :userId="item.user?.id" :name="item.user?.name" /></span>
              <span v-else-if="item.company || item.organizer">{{ item.company || item.organizer }}</span>
              <span v-if="item.city" class="flex items-center gap-0.5"><AppIcon name="map-pin" :size="11" />{{ item.city }}, {{ item.state }}</span>
              <span v-if="item.distance !== undefined && item.distance !== null" class="text-amber-600 font-semibold">{{ Number(item.distance).toFixed(1) }}mi</span>
              <span v-if="item.bedrooms" class="flex items-center gap-0.5"><AppIcon name="home" :size="11" />{{ item.bedrooms }}방</span>
              <span v-if="item.created_at" class="flex items-center gap-0.5"><AppIcon name="clock" :size="11" />{{ fmtDate(item.created_at) }}</span>
              <span v-if="item.view_count" class="flex items-center gap-0.5"><AppIcon name="eye" :size="11" />{{ item.view_count }}</span>
            </div>
          </div>
          <div class="ml-3 flex-shrink-0 text-right">
            <div v-if="item.price !== undefined && item.price !== null" class="text-amber-600 font-bold text-sm">${{ Number(item.price).toLocaleString() }}{{ item.type==='rent'?'/월':'' }}</div>
          </div>
        </div>
      </div>
      <MobileBanner v-if="i === 4" page="realestate" class="lg:hidden" />
      </template>
    </div>

    <!-- 📝 텍스트 인라인: 페이지네이션 위 한 줄 -->
    <TextInlineAd page="realestate" class="mt-3" />
    <Pagination :page="page" :lastPage="lastPage" @page="loadPage" />
    </div>
    <!-- 오른쪽 위젯 -->
    <div class="col-span-12 lg:col-span-3 hidden lg:block">
      <SidebarWidgets :currentCategory="activeItem ? (activeItem.property_type || '') : activeCat" categoryParam="property_type" :inline="true" @select="openItem" api-url="/api/realestate" detail-path="/realestate/" :current-id="activeItem?.id || 0"
        :mode="activeItem ? 'detail' : 'list'" label="매물" :filter-params="locationParams" />
        <AdSlot page="realestate" position="right" :maxSlots="2" />
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
import CommentSection from '../../components/CommentSection.vue'
import { useMenuConfig } from '../../composables/useMenuConfig'
import axios from 'axios'
import AdSlot from '../../components/AdSlot.vue'
import BookmarkToggle from '../../components/BookmarkToggle.vue'
import MobileBanner from '../../components/MobileBanner.vue'
import TextInlineAd from '../../components/TextInlineAd.vue'
import AppIcon from '../../components/AppIcon.vue'

const auth = useAuthStore()
const bStore = useBookmarkStore()
const BM_TYPE = 'App\\Models\\RealEstateListing'
const route = useRoute()
const router = useRouter()
const showFilter = ref(false)
const activeCat = ref('')
const reType = ref('rent') // rent | sale | roommate

// 세그먼트 컨트롤 탭 정의 (Issue #23)
const reTypeTabs = [
  { value: 'rent',     label: '렌트',     icon: 'key',   activeBg: 'text-blue-600' },
  { value: 'sale',     label: '매매',     icon: 'home',  activeBg: 'text-red-600' },
  { value: 'roommate', label: '룸메이트', icon: 'users', activeBg: 'text-green-600' },
]

function changeReType(v) {
  if (reType.value === v) return
  reType.value = v
  activeCat.value = ''
  showFavorites.value = false
  activeItem.value = null
  // URL 쿼리 동기화
  const query = { ...route.query, type: v }
  router.replace({ query })
  loadPage()
}
const showFavorites = ref(false)
const favCount = computed(() => bStore.getBookmarkedIds(BM_TYPE).length)

// 렌트 카테고리
const rentSubcats = [
  { label: '주거용', items: [
    { value: 'studio', label: '스튜디오' },
    { value: '1br', label: '1BR' },
    { value: '2br', label: '2BR' },
    { value: '3br_plus', label: '3BR 이상' },
    { value: 'roommate', label: '룸메이트' },
    { value: 'minbak', label: '민박' },
    { value: 'etc_home', label: '기타' },
  ]},
  { label: '상업용', items: [
    { value: 'office_rent', label: '오피스' },
    { value: 'retail_rent', label: '소매' },
    { value: 'store_rent', label: '상가' },
    { value: 'building_rent', label: '건물' },
    { value: 'etc_commercial', label: '기타' },
  ]},
]
// 매매 카테고리
const saleSubcats = [
  { label: '주거용 매매', items: [
    { value: 'house', label: '하우스' },
    { value: 'condo', label: '콘도' },
    { value: 'duplex', label: '듀플렉스' },
    { value: 'villa', label: '빌라' },
    { value: 'townhouse', label: '타운하우스' },
    { value: 'etc_home', label: '기타' },
  ]},
  { label: '상업용 매매', items: [
    { value: 'office_sale', label: '오피스' },
    { value: 'retail_sale', label: '소매' },
    { value: 'store_sale', label: '상가' },
    { value: 'building', label: '건물' },
    { value: 'etc_commercial', label: '기타' },
  ]},
]
// 룸메이트 전용 서브카테고리 (선택 사항 필터)
const roommateSubcats = [
  { label: '룸메이트', items: [
    { value: 'shared_room', label: '쉐어 룸' },
    { value: 'private_room', label: '개인 룸' },
    { value: 'master_room', label: '마스터 룸' },
    { value: 'etc_room', label: '기타' },
  ]},
]
const currentSubcats = computed(() => {
  if (reType.value === 'rent') return rentSubcats
  if (reType.value === 'sale') return saleSubcats
  return roommateSubcats
})

// 내 좋아요 부동산만 로드
async function loadFavoritesPage() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/bookmarks', {
      params: { type: 'App\\Models\\RealEstateListing', per_page: 50 },
    })
    const bms = data.data?.data || []
    items.value = bms.map(b => b.bookmarkable).filter(Boolean)
    lastPage.value = 1
    loadFavorited()
  } catch {}
  loading.value = false
}
const { loadConfig, getDefaultView } = useMenuConfig()
const viewMode = ref('list')
// (레거시 — 모바일 필터에서 아직 참조 가능)
const reCategories = computed(() => {
  const flat = currentSubcats.value.flatMap(g => g.items)
  return [{ value: '', label: '전체' }, ...flat]
})
const { city, radius: locRadius, locationQuery, koreanCities, init: initLocation, selectKoreanCity, setRadius } = useLocation()

const items = ref([])
const loading = ref(true)
const activeItem = ref(null)
const currentIdx = ref(-1)
function openItem(item) {
  router.push('/realestate/' + item.id)
}
function navItem(dir) {
  const newIdx = currentIdx.value + dir
  if (newIdx >= 0 && newIdx < items.value.length) openItem(items.value[newIdx])
}
async function deleteActiveItem() {
  if (!confirm('정말 삭제하시겠습니까?')) return
  try { await axios.delete(`/api/realestate/${activeItem.value.id}`); activeItem.value = null; loadPage() } catch {}
}
const page = ref(1)
const lastPage = ref(1)
const search = ref('')
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

function promoRowClass(item) {
  if (item.promotion_tier === 'national') return 'border-2 border-red-400 rounded-lg hover:bg-gray-50'
  if (item.promotion_tier === 'state_plus') return 'border-2 border-blue-400 rounded-lg hover:bg-gray-50'
  if (item.promotion_tier === 'sponsored') return 'border-2 border-amber-400 rounded-lg hover:bg-gray-50'
  return 'hover:bg-amber-50/50'
}

// 카드형: 전체 박스 보더
function promoBorderClass(item) {
  if (item.promotion_tier === 'national') return 'border-2 border-red-400'
  if (item.promotion_tier === 'state_plus') return 'border-2 border-blue-400'
  if (item.promotion_tier === 'sponsored') return 'border-2 border-amber-400'
  return 'border border-gray-200'
}

const propertyTypeLabels = { house:'하우스', apt:'아파트', condo:'콘도', studio:'스튜디오', office:'오피스', commercial:'상가', etc:'기타' }
function propertyTypeLabel(t) { return propertyTypeLabels[t] || t }

// 좋아요 (Bookmark polymorphic)
const favorited = ref(new Set())
async function loadFavorited() {
  if (!auth.isLoggedIn || !items.value.length) return
  try {
    const ids = items.value.map(i => i.id).join(',')
    const { data } = await axios.get('/api/bookmarks/check', {
      params: { type: 'App\\Models\\RealEstateListing', ids },
    })
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

function fmtDate(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  const diff = (Date.now() - d.getTime()) / 1000
  if (diff < 60) return '방금'
  if (diff < 3600) return Math.floor(diff/60) + '분 전'
  if (diff < 86400) return Math.floor(diff/3600) + '시간 전'
  if (diff < 604800) return Math.floor(diff/86400) + '일 전'
  return d.toLocaleDateString('ko-KR', { month: 'short', day: 'numeric' })
}

function realEstateThumb(item) {
  const imgs = item.images || []
  if (!imgs.length) return ''
  const path = imgs[0]
  if (!path) return ''
  return String(path).startsWith('http') || String(path).startsWith('/storage/')
    ? path
    : '/storage/' + path
}

async function loadPage(p = 1) {
  loading.value = true
  page.value = p

  const params = { page: p, per_page: 20 }
  if (search.value) params.search = search.value
  params.type = reType.value
  if (activeCat.value) params.property_type = activeCat.value

  if (radius.value !== '0') {
    let lat, lng, state
    const idx = parseInt(selectedCityIdx.value)
    if (idx >= 0) {
      const kc = koreanCities[idx]
      lat = kc.lat; lng = kc.lng; state = kc.state
    } else if (idx === -2 && myCity.value?.lat) {
      lat = myCity.value.lat; lng = myCity.value.lng; state = myCity.value.state
    } else {
      const loc = locationQuery.value
      lat = loc.lat; lng = loc.lng
      state = myCity.value?.state
    }

    if (lat && lng) {
      params.lat = lat
      params.lng = lng
      params.radius = parseInt(radius.value)
      if (state) params.user_state = state
    }
  } else {
    if (myCity.value?.state) params.user_state = myCity.value.state
  }

  try {
    const { data } = await axios.get('/api/realestate', { params })
    items.value = data.data?.data || []
    lastPage.value = data.data?.last_page || 1
    loadFavorited()
  } catch {}
  loading.value = false
}

onMounted(async () => {
  bStore.loadAll()
  await loadConfig(); viewMode.value = getDefaultView('realestate')

  // URL 쿼리 파라미터 반영 (?type=sale|rent|roommate, cat, fav) — Issue #23
  if (['rent', 'sale', 'roommate'].includes(route.query.type)) {
    reType.value = route.query.type
  }
  if (route.query.cat) activeCat.value = route.query.cat
  if (route.query.fav === '1') { showFavorites.value = true }

  await initLocation()
  if (city.value) {
    myCity.value = { ...city.value }
    selectedCityIdx.value = '-2'
  } else {
    selectedCityIdx.value = '-1'
    radius.value = '0'
  }

  if (showFavorites.value) loadFavoritesPage()
  else loadPage()
})

watch(() => route.params.id, (newId, oldId) => {
  if (oldId && !newId) {
    loadPage()
    activeItem.value = null
  }
})
</script>