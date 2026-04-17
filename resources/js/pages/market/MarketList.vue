<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <!-- 헤더: 모바일 -->
    <div class="lg:hidden mb-3">
      <div class="flex items-center justify-between mb-2">
        <h1 class="text-lg font-black text-gray-800">🛒 중고장터</h1>
        <div class="flex items-center gap-2">
          <button @click="showFilter = true" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold px-3 py-2 rounded-lg">🔍 필터</button>
          <RouterLink v-if="auth.isLoggedIn" to="/market/write" class="bg-amber-400 text-amber-900 text-xs font-bold px-3 py-2 rounded-lg">✏️ 등록</RouterLink>
        </div>
      </div>
      <div class="flex items-center gap-1.5 overflow-x-auto">
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
          <button v-for="c in marketCategories" :key="c.value" @click="activeCat = c.value"
            class="text-xs py-2 rounded-lg font-semibold border transition"
            :class="activeCat === c.value ? 'bg-amber-50 text-amber-700 border-amber-300' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
            {{ c.label }}
          </button>
        </div>
      </div>
    </MobileFilter>

    <!-- 헤더: 데스크탑 -->
    <div class="hidden lg:flex items-center justify-between mb-4 flex-wrap gap-2">
      <h1 class="text-xl font-black text-gray-800">🛒 중고장터</h1>
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
        <RouterLink v-if="auth.isLoggedIn" to="/market/write" class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-amber-500">✏️ 등록</RouterLink>
      </div>
    </div>

    <div class="grid grid-cols-12 gap-4">
    <!-- 왼쪽: 카테고리 -->
    <div class="col-span-12 lg:col-span-2 hidden lg:block">
      <div class="sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto space-y-3 pr-0.5">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">📋 카테고리</div>
          <button v-for="c in marketCategories" :key="c.value" @click="showFavorites=false; activeCat=c.value; activeItem=null; loadPage()"
            class="w-full text-left px-3 py-2 text-xs transition"
            :class="!showFavorites && activeCat===c.value ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">{{ c.label }}</button>
          <button v-if="auth.isLoggedIn" @click="showFavorites=true; activeItem=null; loadFavoritesPage()"
            class="w-full text-left px-3 py-2 text-xs transition border-t"
            :class="showFavorites ? 'bg-red-50 text-red-600 font-bold' : 'text-gray-600 hover:bg-red-50/50'">
            🔖 내 북마크<span v-if="favCount > 0" class="ml-0.5">({{ favCount }})</span>
          </button>
        </div>
        <AdSlot page="market" position="left" :maxSlots="2" />
      </div>
    </div>
    <div class="col-span-12 lg:col-span-7">

    <div class="mb-2">
      <span v-if="showFavorites" class="font-bold text-red-600 text-sm">🔖 내 북마크</span>
      <template v-else>
        <span class="font-bold text-amber-700 text-sm">{{ activeCat ? (marketCategories.find(c => c.value === activeCat)?.label || activeCat) : '전체' }}</span>
        <span v-if="!activeCat" class="text-xs text-gray-400 ml-2">모든 중고 물품을 볼 수 있습니다</span>
      </template>
    </div>

    <!-- 목록 -->
    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="!items.length" class="text-center py-12">
      <div class="text-4xl mb-3">🛒</div>
      <div class="text-gray-500 font-semibold">검색 결과가 없습니다</div>
      <div class="text-xs text-gray-400 mt-1">다른 도시를 선택하거나 '전국'으로 검색해보세요</div>
    </div>
    <!-- 상세 모드 -->
    <div v-if="activeItem">
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-4">
          <div class="flex items-center gap-2 mb-2">
            <span class="text-xs px-2 py-0.5 rounded-full font-bold" :class="{'bg-green-100 text-green-700':activeItem.status==='active','bg-amber-100 text-amber-700':activeItem.status==='reserved','bg-gray-200 text-gray-500':activeItem.status==='sold'}">{{ {active:'판매중',reserved:'예약중',sold:'판매완료'}[activeItem.status] }}</span>
            <span class="text-xs text-gray-400">{{ activeItem.condition }}</span>
          </div>
          <div class="flex items-center gap-2">
            <h2 class="text-lg font-bold text-gray-900 flex-1">{{ activeItem.title }}</h2>
            <BookmarkToggle v-if="auth.isLoggedIn" :active="favorited.has(activeItem.id)" @toggle="toggleFav(activeItem)" size="lg" class="flex-shrink-0" />
          </div>
          <div class="text-2xl font-black text-amber-600 mt-2">${{ Number(activeItem.price).toLocaleString() }}</div>
          <div class="text-xs text-gray-400 mt-1">{{ activeItem.city }}, {{ activeItem.state }} · {{ activeItem.view_count }}회</div>
        </div>
        <div class="px-5 py-4 border-t text-sm text-gray-700 whitespace-pre-wrap">{{ activeItem.content }}</div>
      </div>
      <!-- 작성자 전용 수정/삭제 -->
      <div v-if="auth.user?.id === activeItem.user_id" class="flex gap-2 mt-3 justify-end">
        <RouterLink :to="`/market/write?edit=${activeItem.id}`" class="text-xs text-amber-600 hover:text-amber-800">✏️ 수정</RouterLink>
        <button @click="deleteItem('market')" class="text-xs text-red-400 hover:text-red-600">🗑️ 삭제</button>
      </div>
      <CommentSection v-if="activeItem.id" type="market" :typeId="activeItem.id" class="mt-3" />
      <div class="flex justify-between mt-3">
        <button @click="navItem(-1)" :disabled="currentIdx <= 0" class="text-xs text-gray-500 hover:text-amber-700 disabled:opacity-30">← 이전글</button>
        <button @click="activeItem=null" class="text-xs text-gray-400 hover:text-gray-600">목록</button>
        <button @click="navItem(1)" :disabled="currentIdx >= items.length-1" class="text-xs text-gray-500 hover:text-amber-700 disabled:opacity-30">다음글 →</button>
      </div>
    </div>
    <!-- 목록 모드 -->
    <div v-else-if="!items.length" class="text-center py-12 text-gray-400">검색 결과 없음</div>
    <!-- 카드형 뷰 -->
    <div v-else-if="viewMode==='card'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <template v-for="(item, i) in items" :key="item.id">
      <div @click="openItem(item)"
        class="rounded-xl shadow-sm border overflow-hidden hover:shadow-md hover:-translate-y-0.5 transition-all cursor-pointer flex"
        :class="promoRowClass(item)">
        <!-- 왼쪽: 썸네일 -->
        <div class="w-32 h-32 flex-shrink-0 bg-gray-100">
          <img v-if="item.images?.length" :src="marketThumb(item)" loading="lazy" decoding="async"
            class="w-full h-full object-cover"
            @error="e=>e.target.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center text-4xl bg-amber-50\'>🛒</div>'" />
          <div v-else class="w-full h-full flex items-center justify-center text-4xl bg-amber-50">
            {{ item.category==='electronics'?'📱':item.category==='furniture'?'🪑':item.category==='auto'?'🚗':item.category==='clothing'?'👕':'📦' }}
          </div>
        </div>
        <!-- 오른쪽: 정보 -->
        <div class="flex-1 p-3 min-w-0 flex flex-col justify-between">
          <div>
            <!-- 태그 줄 (왼쪽: 뱃지들, 오른쪽: 가격) -->
            <div class="flex items-center justify-between gap-1 mb-0.5">
              <div class="flex items-center gap-1 flex-wrap min-w-0">
                <span v-if="item.promotion_tier === 'national'" class="text-[10px] bg-red-500 text-white font-bold px-1 py-px rounded">🌐 전국구</span>
                <span v-else-if="item.promotion_tier === 'state_plus'" class="text-[10px] bg-blue-500 text-white font-bold px-1 py-px rounded">⭐주+</span>
                <span v-else-if="item.promotion_tier === 'sponsored'" class="text-[10px] bg-amber-500 text-white font-bold px-1 py-px rounded">📢스폰</span>
                <span v-if="item.is_negotiable" class="text-[10px] bg-green-100 text-green-700 font-bold px-1 py-px rounded">가격협의</span>
                <span v-if="item.hold_enabled" class="text-[10px] bg-blue-100 text-blue-700 font-bold px-1 py-px rounded">홀드가능</span>
              </div>
              <div class="flex items-center gap-1 flex-shrink-0">
                <span class="text-amber-600 font-black text-base whitespace-nowrap">${{ Number(item.price || 0).toLocaleString() }}</span>
                <BookmarkToggle v-if="auth.isLoggedIn" :active="favorited.has(item.id)" @toggle="toggleFav(item)" size="sm" />
              </div>
            </div>
            <!-- 제목 -->
            <div class="text-sm font-bold text-gray-800 truncate">{{ item.title }}</div>
            <div class="text-[10px] text-gray-400 mt-0.5">
              <template v-if="!activeCat">{{ item.category || '기타' }} · </template><UserName :userId="item.user?.id" :name="item.user?.name" className="text-[10px] text-gray-400 inline" />
            </div>
            <div class="text-xs text-gray-500 line-clamp-1 mt-1">{{ (item.content || '').slice(0, 60) }}</div>
          </div>
          <!-- 하단: 위치 + 날짜 + 하트 -->
          <div class="text-[10px] text-gray-400 flex items-center gap-1.5 flex-wrap">
            <span v-if="item.city">📍 {{ item.city }}{{ item.state ? ', '+item.state : '' }}</span>
            <span v-if="item.distance !== undefined && item.distance !== null" class="text-amber-600 font-semibold">{{ Number(item.distance).toFixed(1) }}mi</span>
            <span v-if="item.created_at">🕐 {{ fmtDate(item.created_at) }}</span>
          </div>
        </div>
      </div>
      <MobileAdInline v-if="i === 4" page="market" />
      </template>
    </div>
    <!-- 리스트형 뷰 -->
    <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <template v-for="(item, i) in items" :key="item.id">
      <div @click="openItem(item)"
        class="flex border-b border-gray-50 hover:border-l-2 transition cursor-pointer overflow-hidden"
        :class="promoRowClass(item)">
        <!-- 썸네일 (사용자가 선택한 thumbnail_index 의 이미지) -->
        <div class="w-28 h-24 flex-shrink-0 bg-gray-100">
          <img v-if="item.images?.length"
            :src="marketThumb(item)" loading="lazy" decoding="async"
            class="w-full h-full object-cover"
            @error="e=>e.target.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center text-3xl bg-amber-50\'>🛒</div>'" />
          <div v-else class="w-full h-full flex items-center justify-center text-3xl bg-amber-50">🛒</div>
        </div>
        <div class="flex items-center justify-between flex-1 min-w-0 px-4 py-3">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-1.5 mb-0.5">
              <span v-if="item.promotion_tier === 'national'" class="text-[9px] bg-red-500 text-white font-bold px-1.5 py-0.5 rounded">🌐 전국구</span>
              <span v-else-if="item.promotion_tier === 'state_plus'" class="text-[9px] bg-blue-500 text-white font-bold px-1.5 py-0.5 rounded">⭐ 주+</span>
              <span v-else-if="item.promotion_tier === 'sponsored'" class="text-[9px] bg-amber-500 text-white font-bold px-1.5 py-0.5 rounded">📢 스폰서</span>
            </div>
            <div class="text-sm font-medium text-gray-800 truncate">{{ item.title || item.name }}</div>
            <div class="text-xs text-gray-400 mt-0.5 flex items-center gap-1.5 flex-wrap">
              <UserName v-if="item.user?.id" :userId="item.user?.id" :name="item.user?.name || item.company || item.organizer" className="text-gray-600" /><span v-else-if="item.company || item.organizer">{{ item.company || item.organizer }}</span>
              <span v-if="item.city" class="flex items-center gap-0.5">📍{{ item.city }}, {{ item.state }}</span>
              <span v-if="item.distance !== undefined && item.distance !== null" class="text-amber-600 font-semibold">{{ Number(item.distance).toFixed(1) }}mi</span>
              <span v-if="item.created_at">🕐 {{ fmtDate(item.created_at) }}</span>
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
      <MobileAdInline v-if="i === 4" page="market" />
      </template>
    </div>

    <Pagination v-if="!activeItem" :page="page" :lastPage="lastPage" @page="loadPage" />
    </div>
    <!-- 오른쪽 위젯 -->
    <div class="col-span-12 lg:col-span-3 hidden lg:block">
      <SidebarWidgets :currentCategory="activeItem ? (activeItem.category || '') : activeCat" :inline="true" @select="openItem" api-url="/api/market" detail-path="/market/" :current-id="activeItem?.id || 0"
        :mode="activeItem ? 'detail' : 'list'" :categoryLabel="marketCategories.find(c => c.value === (activeItem?.category || activeCat))?.label || ''" label="물품" :filter-params="locationParams" />
        <AdSlot page="market" position="right" :maxSlots="2" />
    </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { useRoute, useRouter } from 'vue-router'
import { ref, computed, onMounted, watch } from 'vue'
import { useLocation } from '../../composables/useLocation'
import { useAuthStore } from '../../stores/auth'
import { useBookmarkStore } from '../../stores/bookmarks'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import CommentSection from '../../components/CommentSection.vue'
import { useMenuConfig } from '../../composables/useMenuConfig'
import axios from 'axios'
import AdSlot from '../../components/AdSlot.vue'
import BookmarkToggle from '../../components/BookmarkToggle.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const { city, radius: locRadius, locationQuery, koreanCities, init: initLocation, selectKoreanCity, setRadius } = useLocation()

const bStore = useBookmarkStore()
const BM_TYPE = 'App\\Models\\MarketItem'
const showFilter = ref(false)
const showFavorites = ref(false)
const favorited = ref(new Set())
const favCount = computed(() => bStore.getBookmarkedIds(BM_TYPE).length)
const activeCat = ref('')
const { loadConfig, getDefaultView } = useMenuConfig()
const viewMode = ref('list')
const marketCategories = [
  { value: '', label: '전체' },{ value: 'electronics', label: '📱 전자기기' },{ value: 'furniture', label: '🪑 가구' },
  { value: 'clothing', label: '👕 의류' },{ value: 'auto', label: '🚗 자동차' },{ value: 'baby', label: '👶 유아' },
  { value: 'sports', label: '⚽ 스포츠' },{ value: 'books', label: '📚 도서' },{ value: 'etc', label: '📋 기타' },
]
const items = ref([])
const loading = ref(true)
const activeItem = ref(null)
const currentIdx = ref(-1)
function openItem(item) {
  router.push(`/market/${item.id}`)
}
function navItem(dir) {
  const newIdx = currentIdx.value + dir
  if (newIdx >= 0 && newIdx < items.value.length) openItem(items.value[newIdx])
}

async function deleteItem(type) {
  if (!confirm('정말 삭제하시겠습니까?')) return
  try { await axios.delete(`/api/${type}/${activeItem.value.id}`); activeItem.value = null; loadPage() } catch {}
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

// 상대시간 포맷 (방금/N분 전/N시간 전/N일 전/MM-DD)
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

// 프로모션: 전체 박스 보더
function promoRowClass(item) {
  if (item.promotion_tier === 'national') return 'border-2 border-red-400 rounded-lg hover:bg-gray-50'
  if (item.promotion_tier === 'state_plus') return 'border-2 border-blue-400 rounded-lg hover:bg-gray-50'
  if (item.promotion_tier === 'sponsored') return 'border-2 border-amber-400 rounded-lg hover:bg-gray-50'
  return 'hover:bg-amber-50/50'
}

// 사용자가 썸네일로 선택한 이미지. 없으면 첫 번째 이미지.
function marketThumb(item) {
  const imgs = item.images || []
  if (!imgs.length) return ''
  const idx = Math.max(0, Math.min(imgs.length - 1, Number(item.thumbnail_index ?? 0)))
  const path = imgs[idx]
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
  if (activeCat.value) params.category = activeCat.value

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
    // 전국 모드라도 user_state 는 프로모션 가중치에 사용
    if (myCity.value?.state) params.user_state = myCity.value.state
  }

  try {
    const { data } = await axios.get('/api/market', { params })
    items.value = data.data?.data || []
    lastPage.value = data.data?.last_page || 1
  } catch {}
  loading.value = false
  loadFavorited()
}

// 좋아요 (Bookmark)
async function loadFavorited() {
  if (!auth.isLoggedIn || !items.value.length) return
  try {
    const ids = items.value.map(i => i.id).join(',')
    const { data } = await axios.get('/api/bookmarks/check', { params: { type: 'App\\Models\\MarketItem', ids } })
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
    const { data } = await axios.get('/api/bookmarks', { params: { type: 'App\\Models\\MarketItem', per_page: 50 } })
    const bms = data.data?.data || []
    items.value = bms.map(b => b.bookmarkable).filter(Boolean)
    lastPage.value = 1
    loadFavorited()
  } catch {}
  loading.value = false
}

// URL 쿼리 변경 시 반영
watch(() => route.query, (q) => {
  if (route.path !== '/market') return
  if (q.category !== undefined) activeCat.value = q.category || ''
  if (q.search !== undefined) search.value = q.search || ''
  loadPage()
})

onMounted(async () => {
  bStore.loadAll()
  await loadConfig()
  viewMode.value = getDefaultView('market')
  if (route.query.category) activeCat.value = route.query.category
  if (route.query.search) search.value = route.query.search
  await initLocation()
  if (city.value) {
    myCity.value = { ...city.value }
    selectedCityIdx.value = '-2'
  } else {
    selectedCityIdx.value = '-1'
    radius.value = '0'
  }
  loadPage()
})
</script>