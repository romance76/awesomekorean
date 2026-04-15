<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <!-- 헤더: 모바일 -->
    <div class="lg:hidden mb-3">
      <div class="flex items-center justify-between mb-2">
        <h1 class="text-lg font-black text-gray-800">🏠 부동산</h1>
        <div class="flex items-center gap-2">
          <button @click="showFilter = true" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold px-3 py-2 rounded-lg">🔍 필터</button>
          <RouterLink v-if="auth.isLoggedIn" to="/realestate/write" class="bg-amber-400 text-amber-900 text-xs font-bold px-3 py-2 rounded-lg">✏️ 등록</RouterLink>
        </div>
      </div>
      <div class="flex items-center gap-1.5 overflow-x-auto">
        <span v-if="activeCat" class="text-[10px] bg-amber-50 text-amber-700 px-2 py-0.5 rounded-full font-semibold whitespace-nowrap">
          {{ reCategories.find(c => c.value === activeCat)?.label || activeCat }}
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
          <button v-for="c in reCategories" :key="c.value" @click="activeCat = c.value"
            class="text-xs py-2 rounded-lg font-semibold border transition"
            :class="activeCat === c.value ? 'bg-amber-50 text-amber-700 border-amber-300' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
            {{ c.label }}
          </button>
        </div>
      </div>
    </MobileFilter>

    <!-- 헤더: 데스크탑 -->
    <div class="hidden lg:flex items-center justify-between mb-4 flex-wrap gap-2">
      <h1 class="text-xl font-black text-gray-800">🏠 부동산</h1>
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
        <RouterLink v-if="auth.isLoggedIn" to="/realestate/write" class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-amber-500">✏️ 등록</RouterLink>
      </div>
    </div>

    <div class="grid grid-cols-12 gap-4">
    <!-- 왼쪽: 카테고리 -->
    <div class="col-span-12 lg:col-span-2 hidden lg:block">
      <div class="sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto space-y-3 pr-0.5">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">📋 유형</div>
          <button v-for="c in reCategories" :key="c.value" @click="showFavorites=false; activeCat=c.value; activeItem=null; loadPage()"
            class="w-full text-left px-3 py-2 text-xs transition"
            :class="!showFavorites && activeCat===c.value ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">{{ c.label }}</button>
          <button v-if="auth.isLoggedIn" @click="showFavorites=true; activeItem=null; loadFavoritesPage()"
            class="w-full text-left px-3 py-2 text-xs transition border-t"
            :class="showFavorites ? 'bg-red-50 text-red-600 font-bold' : 'text-gray-600 hover:bg-red-50/50'">
            ❤️ 내 좋아요
          </button>
        </div>
        <AdSlot page="realestate" position="left" :maxSlots="2" />
      </div>
    </div>
    <div class="col-span-12 lg:col-span-7">

    <div class="mb-2">
      <span v-if="showFavorites" class="font-bold text-red-600 text-sm">❤️ 내 좋아요 부동산</span>
      <template v-else>
        <span class="font-bold text-amber-700 text-sm">{{ activeCat ? (reCategories.find(c => c.value === activeCat)?.label || activeCat) : '전체' }}</span>
        <span v-if="!activeCat" class="text-xs text-gray-400 ml-2">모든 부동산 매물을 볼 수 있습니다</span>
      </template>
    </div>

    <!-- 목록 -->
    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="!items.length" class="text-center py-12">
      <div class="text-4xl mb-3">🏠</div>
      <div class="text-gray-500 font-semibold">검색 결과가 없습니다</div>
      <div class="text-xs text-gray-400 mt-1">다른 도시를 선택하거나 '전국'으로 검색해보세요</div>
    </div>
    <!-- 상세 모드 -->
    <div v-if="activeItem">
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-4">
          <div class="flex items-center gap-2 mb-2">
            <span class="text-xs px-2 py-0.5 rounded-full font-bold" :class="activeItem.type==='sale'?'bg-red-100 text-red-700':activeItem.type==='rent'?'bg-blue-100 text-blue-700':'bg-green-100 text-green-700'">{{ {rent:'렌트',sale:'매매',roommate:'룸메이트'}[activeItem.type] }}</span>
            <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">{{ activeItem.property_type }}</span>
          </div>
          <h2 class="text-lg font-bold text-gray-900">{{ activeItem.title }}</h2>
          <div class="text-2xl font-black text-amber-600 mt-2">${{ Number(activeItem.price).toLocaleString() }}{{ activeItem.type==='rent'?'/월':'' }}</div>
          <div class="grid grid-cols-4 gap-2 mt-3 text-center text-xs">
            <div class="bg-gray-50 rounded-lg py-1.5"><strong>{{ activeItem.bedrooms||'-' }}</strong> 방</div>
            <div class="bg-gray-50 rounded-lg py-1.5"><strong>{{ activeItem.bathrooms||'-' }}</strong> 화장실</div>
            <div class="bg-gray-50 rounded-lg py-1.5"><strong>{{ activeItem.sqft||'-' }}</strong> sqft</div>
            <div class="bg-gray-50 rounded-lg py-1.5"><strong>{{ activeItem.view_count }}</strong>회</div>
          </div>
        </div>
        <div class="px-5 py-4 border-t text-sm text-gray-700 whitespace-pre-wrap">{{ activeItem.content }}</div>
        <div v-if="activeItem.contact_phone||activeItem.contact_email" class="px-5 py-3 border-t bg-amber-50 text-sm">
          <div v-if="activeItem.contact_phone">📱 {{ activeItem.contact_phone }}</div>
          <div v-if="activeItem.contact_email">📧 {{ activeItem.contact_email }}</div>
        </div>
      </div>
      <div v-if="auth.user?.id === activeItem.user_id" class="flex gap-2 mt-3 justify-end">
        <RouterLink :to="`/realestate/write?edit=${activeItem.id}`" class="text-xs text-amber-600 hover:text-amber-800">✏️ 수정</RouterLink>
        <button @click="deleteActiveItem" class="text-xs text-red-400 hover:text-red-600">🗑️ 삭제</button>
      </div>
      <CommentSection v-if="activeItem.id" type="realestate" :typeId="activeItem.id" class="mt-3" />
      <div class="flex justify-between mt-3">
        <button @click="navItem(-1)" :disabled="currentIdx <= 0" class="text-xs text-gray-500 hover:text-amber-700 disabled:opacity-30">← 이전글</button>
        <button @click="activeItem=null" class="text-xs text-gray-400 hover:text-gray-600">목록</button>
        <button @click="navItem(1)" :disabled="currentIdx >= items.length-1" class="text-xs text-gray-500 hover:text-amber-700 disabled:opacity-30">다음글 →</button>
      </div>
    </div>
    <!-- 목록 모드 -->
    <div v-else-if="!items.length" class="text-center py-12 text-gray-400">검색 결과 없음</div>
    <!-- 카드형 (Zillow 스타일: 사진 위 + 정보 아래) -->
    <div v-else-if="viewMode==='card'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <template v-for="(item, i) in items" :key="item.id">
      <div @click="openItem(item)"
        class="bg-white rounded-xl shadow-sm overflow-hidden hover:shadow-lg transition-all cursor-pointer border-2"
        :class="promoBorderClass(item)">
        <!-- 사진 영역 (좋아요 + 상단 뱃지 오버레이) -->
        <div class="relative h-[250px] bg-gray-100">
          <img v-if="item.images?.length" :src="realEstateThumb(item)" loading="lazy" decoding="async"
            class="w-full h-full object-cover"
            @error="e=>e.target.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center text-6xl bg-amber-50\'>🏠</div>'" />
          <div v-else class="w-full h-full flex items-center justify-center text-6xl bg-amber-50">
            {{ item.type==='sale'?'🏠':item.type==='rent'?'🔑':'👥' }}
          </div>
          <!-- 좌상단: 프로모션 뱃지 -->
          <div v-if="item.promotion_tier && item.promotion_tier !== 'none'" class="absolute top-2 left-2">
            <span v-if="item.promotion_tier === 'national'" class="text-[10px] bg-red-500 text-white font-bold px-2 py-1 rounded shadow">🌍 전국</span>
            <span v-else-if="item.promotion_tier === 'state_plus'" class="text-[10px] bg-blue-500 text-white font-bold px-2 py-1 rounded shadow">⭐ 주+</span>
            <span v-else-if="item.promotion_tier === 'sponsored'" class="text-[10px] bg-amber-500 text-white font-bold px-2 py-1 rounded shadow">📢 스폰서</span>
          </div>
          <!-- 우상단: 좋아요 -->
          <button v-if="auth.isLoggedIn" @click.stop="toggleFav(item)"
            class="absolute top-2 right-2 w-9 h-9 rounded-full bg-white/90 hover:bg-white shadow flex items-center justify-center transition">
            <span class="text-lg" :class="favorited.has(item.id) ? 'text-red-500' : 'text-gray-300 hover:text-red-400'">
              {{ favorited.has(item.id) ? '❤️' : '🤍' }}
            </span>
          </button>
        </div>

        <!-- 정보 영역 -->
        <div class="p-3 space-y-1">
          <!-- 1. 종류/렌트매매 태그 -->
          <div class="flex items-center gap-1.5 flex-wrap">
            <span class="text-[10px] px-1.5 py-0.5 rounded font-bold"
              :class="item.type==='rent' ? 'bg-blue-100 text-blue-700' : item.type==='sale' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'">
              {{ {rent:'렌트',sale:'매매',roommate:'룸메이트'}[item.type] || item.type }}
            </span>
            <span v-if="item.property_type" class="text-[10px] px-1.5 py-0.5 rounded font-bold bg-gray-100 text-gray-600">
              {{ propertyTypeLabel(item.property_type) }}
            </span>
          </div>

          <!-- 2. 가격 -->
          <div class="text-xl font-black text-gray-900">
            ${{ Number(item.price || 0).toLocaleString() }}<span v-if="item.type==='rent'" class="text-sm font-bold text-gray-500">/월</span>
          </div>

          <!-- 3. 제목 -->
          <div class="text-sm font-bold text-gray-800 truncate">{{ item.title }}</div>

          <!-- 4. 방/화장실/sqft -->
          <div class="flex items-center gap-2 text-[11px] text-gray-600 font-semibold">
            <span v-if="item.bedrooms"><b>{{ item.bedrooms }}</b> bd</span>
            <span v-if="item.bathrooms" class="text-gray-300">|</span>
            <span v-if="item.bathrooms"><b>{{ item.bathrooms }}</b> ba</span>
            <span v-if="item.sqft" class="text-gray-300">|</span>
            <span v-if="item.sqft"><b>{{ Number(item.sqft).toLocaleString() }}</b> sqft</span>
          </div>

          <!-- 5. 위치 + 올린사람 + 올린날짜 -->
          <div class="text-[10px] text-gray-500 flex items-center gap-1.5 flex-wrap pt-1 border-t border-gray-100 mt-1">
            <span>📍 {{ item.address ? item.address + ', ' : '' }}{{ item.city }}{{ item.state ? ', '+item.state : '' }}</span>
          </div>
          <div class="text-[10px] text-gray-400 flex items-center gap-1.5">
            <UserName v-if="item.user?.id" :userId="item.user.id" :name="item.user.name" className="text-[10px] text-gray-400" />
            <span v-if="item.created_at">· 🕐 {{ fmtDate(item.created_at) }}</span>
            <span v-if="item.distance !== undefined && item.distance !== null" class="text-amber-600 font-semibold">· {{ Number(item.distance).toFixed(1) }}mi</span>
          </div>
        </div>
      </div>
      <MobileAdInline v-if="i === 4" page="realestate" />
      </template>
    </div>
    <!-- 리스트형 -->
    <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <template v-for="(item, i) in items" :key="item.id">
      <div @click="openItem(item)"
        class="flex border-b border-gray-50 hover:border-l-2 transition cursor-pointer overflow-hidden"
        :class="promoRowClass(item)">
        <!-- 썸네일 -->
        <div class="w-28 h-24 flex-shrink-0 bg-gray-100">
          <img v-if="item.images?.length"
            :src="realEstateThumb(item)" loading="lazy" decoding="async"
            class="w-full h-full object-cover"
            @error="e=>e.target.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center text-3xl bg-amber-50\'>🏠</div>'" />
          <div v-else class="w-full h-full flex items-center justify-center text-3xl bg-amber-50">
            {{ item.type==='sale'?'🏠':item.type==='rent'?'🔑':'👥' }}
          </div>
        </div>
        <div class="flex items-center justify-between flex-1 min-w-0 px-4 py-3">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-1.5 mb-0.5">
              <span v-if="item.promotion_tier === 'national'" class="text-[9px] bg-red-500 text-white font-bold px-1.5 py-0.5 rounded">🌍 전국</span>
              <span v-else-if="item.promotion_tier === 'state_plus'" class="text-[9px] bg-blue-500 text-white font-bold px-1.5 py-0.5 rounded">⭐ 주+</span>
              <span v-else-if="item.promotion_tier === 'sponsored'" class="text-[9px] bg-amber-500 text-white font-bold px-1.5 py-0.5 rounded">📢 스폰서</span>
            </div>
            <div class="text-sm font-medium text-gray-800 truncate">{{ item.title || item.name }}</div>
            <div class="text-xs text-gray-400 mt-0.5 flex items-center gap-1.5 flex-wrap">
              <span v-if="item.user?.name"><UserName :userId="item.user?.id" :name="item.user?.name" /></span>
              <span v-else-if="item.company || item.organizer">{{ item.company || item.organizer }}</span>
              <span v-if="item.city" class="flex items-center gap-0.5">📍{{ item.city }}, {{ item.state }}</span>
              <span v-if="item.distance !== undefined && item.distance !== null" class="text-amber-600 font-semibold">{{ Number(item.distance).toFixed(1) }}mi</span>
              <span v-if="item.bedrooms">🛏 {{ item.bedrooms }}방</span>
              <span v-if="item.created_at">🕐 {{ fmtDate(item.created_at) }}</span>
              <span v-if="item.view_count">👁 {{ item.view_count }}</span>
            </div>
          </div>
          <div class="ml-3 flex-shrink-0 text-right">
            <div v-if="item.price !== undefined && item.price !== null" class="text-amber-600 font-bold text-sm">${{ Number(item.price).toLocaleString() }}{{ item.type==='rent'?'/월':'' }}</div>
          </div>
        </div>
      </div>
      <MobileAdInline v-if="i === 4" page="realestate" />
      </template>
    </div>

    <Pagination :page="page" :lastPage="lastPage" @page="loadPage" />
    </div>
    <!-- 오른쪽 위젯 -->
    <div class="col-span-12 lg:col-span-3 hidden lg:block">
      <SidebarWidgets :inline="true" @select="openItem" api-url="/api/realestate" detail-path="/realestate/" :current-id="0"
        label="매물" :filter-params="locationParams" />
        <AdSlot page="realestate" position="right" :maxSlots="2" />
    </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { useRoute } from 'vue-router'
import { ref, computed, watch, onMounted } from 'vue'
import { useLocation } from '../../composables/useLocation'
import { useAuthStore } from '../../stores/auth'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import CommentSection from '../../components/CommentSection.vue'
import { useMenuConfig } from '../../composables/useMenuConfig'
import axios from 'axios'
import AdSlot from '../../components/AdSlot.vue'

const auth = useAuthStore()
const route = useRoute()
const showFilter = ref(false)
const activeCat = ref('')
const showFavorites = ref(false)

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
// 부동산 유형 (property_type) — 사용자 요청 항목
const reCategories = [
  { value: '', label: '전체' },
  { value: 'house', label: '🏠 하우스' },
  { value: 'apt', label: '🏢 아파트' },
  { value: 'condo', label: '🏬 콘도' },
  { value: 'studio', label: '🛏 스튜디오' },
  { value: 'office', label: '🏛 오피스' },
  { value: 'commercial', label: '🏪 상가' },
  { value: 'etc', label: '📋 기타' },
]
const { city, radius: locRadius, locationQuery, koreanCities, init: initLocation, selectKoreanCity, setRadius } = useLocation()

const items = ref([])
const loading = ref(true)
const activeItem = ref(null)
const currentIdx = ref(-1)
async function openItem(item) {
  currentIdx.value = items.value.findIndex(i => i.id === item.id)
  try { const { data } = await axios.get(`/api/realestate/${item.id}`); activeItem.value = data.data }
  catch { activeItem.value = item }
  if (activeItem.value?.type) activeCat.value = activeItem.value.type
  window.scrollTo({ top: 0, behavior: 'smooth' })
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
  if (item.promotion_tier === 'national') return 'bg-red-50/70 border-l-4 border-l-red-500 hover:bg-red-100/70'
  if (item.promotion_tier === 'state_plus') return 'bg-blue-50/70 border-l-4 border-l-blue-500 hover:bg-blue-100/70'
  if (item.promotion_tier === 'sponsored') return 'bg-amber-50/70 border-l-4 border-l-amber-500 hover:bg-amber-100/70'
  return 'hover:bg-amber-50/50 hover:border-l-amber-400'
}

// 카드형: 배경색 대신 테두리 색만 (너무 어지럽지 않게)
function promoBorderClass(item) {
  if (item.promotion_tier === 'national') return 'border-red-400'
  if (item.promotion_tier === 'state_plus') return 'border-blue-400'
  if (item.promotion_tier === 'sponsored') return 'border-amber-400'
  return 'border-gray-100'
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
  try {
    const { data } = await axios.post('/api/bookmarks', {
      bookmarkable_type: 'App\\Models\\RealEstateListing',
      bookmarkable_id: item.id,
    })
    if (data.bookmarked) favorited.value.add(item.id)
    else favorited.value.delete(item.id)
    // reactivity trigger
    favorited.value = new Set(favorited.value)
  } catch {}
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
  await loadConfig(); viewMode.value = getDefaultView('realestate')
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

watch(() => route.params.id, (newId, oldId) => {
  if (oldId && !newId) {
    loadPage()
    activeItem.value = null
  }
})
</script>