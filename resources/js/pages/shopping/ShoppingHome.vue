<template>
  <div class="min-h-screen bg-gray-50 pb-16">

    <div class="max-w-[1200px] mx-auto px-4 pt-4">
    <!-- 헤더 -->
    <div class="bg-gradient-to-r from-orange-500 to-amber-500 rounded-2xl px-6 py-5 mb-4 text-white">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-black flex items-center gap-2">🛒 쇼핑 정보</h1>
          <p class="text-orange-100 text-sm mt-0.5">한인 · 미국 마트 주간 세일 & 특가 정보</p>
        </div>
        <button @click="detectLocation" class="bg-white/20 hover:bg-white/30 rounded-xl px-3 py-2 text-sm flex items-center gap-1.5 transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
          {{ locationText }}
        </button>
      </div>

    </div>

    <!-- 위치 미설정 안내 -->
    <div v-if="!userLat && !loadingLoc" class="bg-blue-50 border border-blue-200 rounded-xl p-3 mb-4 flex items-center gap-3">
      <span class="text-2xl">📍</span>
      <div class="flex-1">
        <p class="text-sm font-medium text-blue-800">위치를 설정하면 가까운 마트와 세일 정보를 볼 수 있어요</p>
        <button @click="detectLocation" class="text-blue-600 text-xs font-medium mt-1 hover:underline">현재 위치 감지하기 →</button>
      </div>
    </div>

    <!-- 카테고리 필터 -->
    <div class="flex gap-2 mb-4 overflow-x-auto pb-1 scrollbar-hide">
      <button v-for="cat in storeCats" :key="cat.value" @click="selectedCat=cat.value; loadStores()"
        :class="selectedCat===cat.value ? 'bg-orange-500 text-white border-orange-500' : 'bg-white text-gray-600 border-gray-200 hover:border-orange-300'"
        class="flex-shrink-0 px-4 py-2 rounded-full text-sm font-medium border transition">
        {{ cat.icon }} {{ cat.label }}
      </button>
    </div>

    <!-- Location Bar -->
    <div class="max-w-[1200px] mx-auto px-4 mt-2">
      <LocationBar placeholder="쇼핑정보 검색..." :radius-options="['10mi','20mi','30mi','50mi','전국']" @search="onLocationSearch" @location-change="onLocationChange" />
    </div>

    <!-- 마트 가로 스크롤 -->
    <div class="flex gap-3 overflow-x-auto pb-3 mb-5 scrollbar-hide">
      <button v-for="s in filteredStores" :key="s.id" @click="selectStore(s)"
        :class="[selectedStore?.id===s.id ? 'border-orange-500 bg-orange-50' : 'border-gray-200 bg-white hover:border-orange-300',
                 !s.active_deals_count && !s.nearby_count ? 'opacity-50' : '']"
        class="flex-shrink-0 w-28 border rounded-xl p-3 text-center transition">
        <div class="text-2xl mb-1">{{ storeEmoji(s.category) }}</div>
        <div class="text-xs font-bold text-gray-800 truncate">{{ s.name }}</div>
        <div v-if="userLat && s.nearby_count" class="text-[10px] text-orange-500 font-medium mt-0.5">내 주변 {{ s.nearby_count }}개</div>
        <div v-else class="text-[10px] text-gray-400 mt-0.5">{{ s.locations_count || 0 }}지점</div>
        <div v-if="s.active_deals_count" class="text-[10px] text-blue-500 mt-0.5">딜 {{ s.active_deals_count }}개</div>
      </button>
      <div v-if="!filteredStores.length && !loadingStores" class="text-center text-gray-400 text-sm py-6 w-full">마트가 없습니다</div>
    </div>

    <!-- 선택된 마트 상세 -->
    <div v-if="selectedStore && storeDetail" class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-5 overflow-hidden">
      <div class="bg-gray-50 p-4 border-b">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="font-bold text-lg text-gray-900">{{ storeDetail.name }}</h2>
            <p v-if="storeDetail.name_en" class="text-xs text-gray-400">{{ storeDetail.name_en }}</p>
          </div>
          <button @click="selectedStore=null; storeDetail=null" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>
        <!-- 가까운 지점 -->
        <div v-if="storeDetail.nearest_location" class="mt-3 bg-white rounded-xl p-3 border">
          <div class="flex items-start gap-2">
            <span class="text-lg">📍</span>
            <div class="flex-1 min-w-0">
              <div class="font-medium text-sm text-gray-800">{{ storeDetail.nearest_location.branch_name }}</div>
              <div class="text-xs text-gray-500 mt-0.5">{{ storeDetail.nearest_location.address }}, {{ storeDetail.nearest_location.city }} {{ storeDetail.nearest_location.state }}</div>
              <div class="flex items-center gap-3 mt-1.5 text-xs">
                <span :class="storeDetail.nearest_location.is_open_now ? 'text-green-600' : 'text-red-500'" class="font-medium">
                  {{ storeDetail.nearest_location.is_open_now ? '🟢 영업중' : '🔴 마감' }}
                </span>
                <span class="text-gray-400">{{ storeDetail.nearest_location.open_time }}~{{ storeDetail.nearest_location.close_time }}</span>
                <span v-if="storeDetail.nearest_location.distance" class="text-orange-500">{{ storeDetail.nearest_location.distance.toFixed(1) }}mi</span>
              </div>
              <div class="flex gap-2 mt-2">
                <a v-if="storeDetail.nearest_location.lat" :href="'https://maps.google.com/?q='+storeDetail.nearest_location.lat+','+storeDetail.nearest_location.lng" target="_blank"
                  class="text-xs bg-blue-50 text-blue-600 px-2 py-1 rounded-lg hover:bg-blue-100">🗺️ 지도보기</a>
                <a v-if="storeDetail.nearest_location.phone" :href="'tel:'+storeDetail.nearest_location.phone"
                  class="text-xs bg-green-50 text-green-600 px-2 py-1 rounded-lg hover:bg-green-100">📞 전화</a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 마트 탭 -->
      <div class="flex border-b">
        <button @click="storeTab='deals'" :class="storeTab==='deals' ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-500'"
          class="flex-1 py-3 text-sm font-medium border-b-2 transition">이번주 세일</button>
        <button @click="storeTab='flyers'" :class="storeTab==='flyers' ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-500'"
          class="flex-1 py-3 text-sm font-medium border-b-2 transition">전단지</button>
      </div>

      <!-- 세일 목록 -->
      <div v-if="storeTab==='deals'" class="p-4">
        <div v-if="storeDetail.deals && storeDetail.deals.length" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
          <div v-for="d in storeDetail.deals" :key="d.id" class="border rounded-xl overflow-hidden hover:shadow-md transition group">
            <div class="aspect-square bg-gray-100 relative overflow-hidden">
              <img v-if="d.image_url" :src="d.image_url" class="w-full h-full object-cover group-hover:scale-105 transition" />
              <div v-else class="w-full h-full flex items-center justify-center text-4xl text-gray-300">{{ categoryEmoji(d.category) }}</div>
              <span v-if="discountRate(d)" class="absolute top-2 right-2 bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">-{{ discountRate(d) }}%</span>
            </div>
            <div class="p-2.5">
              <div class="text-xs font-medium text-gray-800 line-clamp-2 leading-tight">{{ d.title }}</div>
              <div class="flex items-baseline gap-1.5 mt-1.5">
                <span v-if="d.sale_price" class="text-sm font-bold text-red-600">${{ Number(d.sale_price).toFixed(2) }}</span>
                <span v-if="d.original_price && d.original_price != d.sale_price" class="text-xs text-gray-400 line-through">${{ Number(d.original_price).toFixed(2) }}</span>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="text-center py-8 text-gray-400 text-sm">등록된 세일 정보가 없습니다</div>
      </div>

      <!-- 전단지 -->
      <div v-if="storeTab==='flyers'" class="p-4">
        <div v-if="storeDetail.flyers && storeDetail.flyers.length" class="grid grid-cols-2 gap-3">
          <div v-for="f in storeDetail.flyers" :key="f.id" class="border rounded-xl overflow-hidden">
            <img v-if="f.image_url" :src="f.image_url" class="w-full" />
            <div class="p-2 text-xs text-gray-500">{{ f.title || '전단지' }} · {{ formatDate(f.valid_from) }}~{{ formatDate(f.valid_until) }}</div>
          </div>
        </div>
        <div v-else class="text-center py-8 text-gray-400 text-sm">등록된 전단지가 없습니다</div>
      </div>
    </div>

    <!-- 이번 주 특가 -->
    <div v-if="specialDeals.length" class="mb-5">
      <h2 class="text-lg font-bold text-gray-900 mb-3">🔥 이번 주 특가</h2>
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
        <div v-for="d in specialDeals" :key="d.id" class="bg-white border border-red-100 rounded-xl overflow-hidden hover:shadow-md transition group">
          <div class="aspect-square bg-gray-50 relative overflow-hidden">
            <img v-if="d.image_url" :src="d.image_url" class="w-full h-full object-cover group-hover:scale-105 transition" />
            <div v-else class="w-full h-full flex items-center justify-center text-4xl text-gray-300">🏷️</div>
            <span class="absolute top-2 right-2 bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">-{{ discountRate(d) }}%</span>
          </div>
          <div class="p-2.5">
            <div class="text-[10px] text-orange-500 font-medium">{{ d.store_name || '마트' }}</div>
            <div class="text-xs font-medium text-gray-800 line-clamp-2 mt-0.5">{{ d.title }}</div>
            <div class="flex items-baseline gap-1.5 mt-1">
              <span class="text-sm font-bold text-red-600">${{ Number(d.sale_price).toFixed(2) }}</span>
              <span v-if="d.original_price" class="text-xs text-gray-400 line-through">${{ Number(d.original_price).toFixed(2) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 전체 딜 목록 -->
    <div>
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-lg font-bold text-gray-900">🛍️ 세일 정보</h2>
        <div class="flex gap-2">
          <select v-model="dealCategory" @change="loadDeals(true)" class="text-xs border rounded-lg px-2 py-1.5">
            <option value="">전체 카테고리</option>
            <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
          </select>
        </div>
      </div>

      <!-- 딜 그리드 -->
      <div v-if="deals.length" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
        <div v-for="d in deals" :key="d.id" class="bg-white border rounded-xl overflow-hidden hover:shadow-md transition group cursor-pointer" @click="openDeal(d)">
          <div class="aspect-[4/3] bg-gray-100 relative overflow-hidden">
            <img v-if="d.image_url" :src="d.image_url" class="w-full h-full object-cover group-hover:scale-105 transition" loading="lazy" />
            <div v-else class="w-full h-full flex items-center justify-center text-4xl text-gray-300">{{ categoryEmoji(d.category) }}</div>
            <span v-if="discountRate(d)" class="absolute top-2 right-2 bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">-{{ discountRate(d) }}%</span>
          </div>
          <div class="p-2.5">
            <div v-if="d.store_name" class="text-[10px] text-orange-500 font-medium mb-0.5">{{ d.store_name }}</div>
            <div class="text-xs font-medium text-gray-800 line-clamp-2 leading-tight">{{ d.title }}</div>
            <div class="flex items-baseline gap-1.5 mt-1.5">
              <span v-if="d.sale_price" class="text-sm font-bold text-red-600">${{ Number(d.sale_price).toFixed(2) }}</span>
              <span v-if="d.original_price && d.original_price != d.sale_price" class="text-xs text-gray-400 line-through">${{ Number(d.original_price).toFixed(2) }}</span>
            </div>
            <div v-if="d.valid_until" class="text-[10px] text-gray-400 mt-1">~{{ formatDate(d.valid_until) }}</div>
          </div>
        </div>
      </div>

      <!-- 로딩 -->
      <div v-if="loadingDeals" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
        <div v-for="i in 8" :key="i" class="border rounded-xl overflow-hidden animate-pulse">
          <div class="aspect-[4/3] bg-gray-200"></div>
          <div class="p-3 space-y-2"><div class="h-3 bg-gray-200 rounded w-3/4"></div><div class="h-4 bg-gray-200 rounded w-1/2"></div></div>
        </div>
      </div>

      <div v-if="!deals.length && !loadingDeals" class="text-center py-12 text-gray-400">
        <div class="text-4xl mb-2">🛒</div>
        <p class="text-sm">등록된 세일 정보가 없습니다</p>
      </div>

      <!-- 더보기 -->
      <div v-if="hasMore" class="text-center mt-4">
        <button @click="loadDeals()" class="bg-gray-100 hover:bg-gray-200 text-gray-600 px-6 py-2.5 rounded-xl text-sm font-medium transition">더보기</button>
      </div>
    </div>

    <!-- 딜 상세 모달 -->
    <div v-if="selectedDeal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="selectedDeal=null">
      <div class="bg-white rounded-2xl max-w-lg w-full max-h-[80vh] overflow-y-auto">
        <div class="relative">
          <img v-if="selectedDeal.image_url" :src="selectedDeal.image_url" class="w-full aspect-video object-cover rounded-t-2xl" />
          <button @click="selectedDeal=null" class="absolute top-3 right-3 bg-black/50 text-white w-8 h-8 rounded-full flex items-center justify-center">&times;</button>
        </div>
        <div class="p-5">
          <div v-if="selectedDeal.store_name" class="text-xs text-orange-500 font-medium mb-1">{{ selectedDeal.store_name }}</div>
          <h3 class="font-bold text-gray-900">{{ selectedDeal.title }}</h3>
          <div class="flex items-baseline gap-2 mt-2">
            <span v-if="selectedDeal.sale_price" class="text-xl font-bold text-red-600">${{ Number(selectedDeal.sale_price).toFixed(2) }}</span>
            <span v-if="selectedDeal.original_price" class="text-sm text-gray-400 line-through">${{ Number(selectedDeal.original_price).toFixed(2) }}</span>
            <span v-if="discountRate(selectedDeal)" class="bg-red-100 text-red-600 text-xs font-bold px-2 py-0.5 rounded-full">-{{ discountRate(selectedDeal) }}%</span>
          </div>
          <p v-if="selectedDeal.description" class="text-sm text-gray-600 mt-3">{{ selectedDeal.description }}</p>
          <div v-if="selectedDeal.valid_until" class="text-xs text-gray-400 mt-2">세일기간: {{ formatDate(selectedDeal.valid_from) }} ~ {{ formatDate(selectedDeal.valid_until) }}</div>
          <a v-if="selectedDeal.url" :href="selectedDeal.url" target="_blank"
            class="block mt-4 bg-orange-500 hover:bg-orange-600 text-white text-center py-3 rounded-xl font-medium transition">구매 페이지 바로가기 →</a>
        </div>
      </div>
    </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'
import LocationBar from '../../components/location/LocationBar.vue'

const authStore = useAuthStore()
const { user, isLoggedIn } = storeToRefs(authStore)

// 위치
const userLat = ref(null)
const userLng = ref(null)
const radius = ref(30)
const loadingLoc = ref(false)
const locationText = ref('위치 설정')

// 데이터
const stores = ref([])
const deals = ref([])
const specialDeals = ref([])
const categories = ref([])
const loadingStores = ref(false)
const loadingDeals = ref(false)
const selectedCat = ref('')
const selectedStore = ref(null)
const storeDetail = ref(null)
const storeTab = ref('deals')
const dealCategory = ref('')
const selectedDeal = ref(null)
const page = ref(1)
const hasMore = ref(false)

const storeCats = [
  { value: '', icon: '🏪', label: '전체' },
  { value: 'korean_mart', icon: '🇰🇷', label: '한인마트' },
  { value: 'us_mart', icon: '🇺🇸', label: '미국마트' },
  { value: 'asian_mart', icon: '🌏', label: '아시안마트' },
  { value: 'online', icon: '💻', label: '온라인' },
]

const filteredStores = computed(() => {
  if (!selectedCat.value) return stores.value
  return stores.value.filter(s => s.category === selectedCat.value)
})

function storeEmoji(cat) {
  const m = { korean_mart: '🇰🇷', us_mart: '🇺🇸', asian_mart: '🌏', online: '💻' }
  return m[cat] || '🏪'
}

function categoryEmoji(cat) {
  const m = { '정육/수산': '🥩', '채소/과일': '🥬', '냉동식품': '❄️', '음료': '🥤', '과자/간식': '🍪', '유제품': '🥛', '전자기기': '💻', '생활용품': '🧴', '패션/의류': '👕', '건강/뷰티': '💊', '식품/음료': '🍜' }
  return m[cat] || '🛒'
}

function discountRate(d) {
  if (d.discount_percent && d.discount_percent > 0) return d.discount_percent
  if (d.original_price && d.sale_price && d.original_price > 0) return Math.round((1 - d.sale_price / d.original_price) * 100)
  return null
}

function formatDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('ko-KR', { month: 'short', day: 'numeric' })
}

// 위치 감지
function detectLocation() {
  if (isLoggedIn.value && user.value?.lat && user.value?.lng) {
    userLat.value = user.value.lat
    userLng.value = user.value.lng
    locationText.value = '내 위치'
    radius.value = user.value.default_radius || 30
    refreshAll()
    return
  }
  if (!navigator.geolocation) { locationText.value = '위치 미지원'; return }
  loadingLoc.value = true
  locationText.value = '감지 중...'
  navigator.geolocation.getCurrentPosition(
    pos => {
      userLat.value = pos.coords.latitude
      userLng.value = pos.coords.longitude
      locationText.value = '현재 위치'
      loadingLoc.value = false
      refreshAll()
    },
    () => { locationText.value = '위치 거부됨'; loadingLoc.value = false },
    { timeout: 10000 }
  )
}

function setRadius(r) { radius.value = r; refreshAll() }

function refreshAll() { loadStores(); loadDeals(true); loadSpecial() }

// 마트 목록
async function loadStores() {
  loadingStores.value = true
  try {
    const params = {}
    if (userLat.value) { params.lat = userLat.value; params.lng = userLng.value; params.radius_miles = radius.value }
    const { data } = await axios.get('/api/shopping/stores', { params })
    stores.value = Array.isArray(data) ? data : (data.data || [])
  } catch { stores.value = [] }
  finally { loadingStores.value = false }
}

// 마트 상세
async function selectStore(s) {
  if (selectedStore.value?.id === s.id) { selectedStore.value = null; storeDetail.value = null; return }
  selectedStore.value = s
  storeTab.value = 'deals'
  try {
    const params = {}
    if (userLat.value) { params.lat = userLat.value; params.lng = userLng.value }
    const { data } = await axios.get(`/api/shopping/stores/${s.id}`, { params })
    storeDetail.value = data
  } catch { storeDetail.value = { ...s, deals: [], flyers: [], nearest_location: null } }
}

// 딜 목록
async function loadDeals(reset = false) {
  if (reset) { page.value = 1; deals.value = [] }
  loadingDeals.value = true
  try {
    const params = { page: page.value, per_page: 20 }
    if (dealCategory.value) params.category = dealCategory.value
    if (userLat.value) { params.lat = userLat.value; params.lng = userLng.value; params.radius_miles = radius.value }
    const { data } = await axios.get('/api/shopping/deals', { params })
    const items = data.data || data || []
    if (reset) deals.value = items; else deals.value.push(...items)
    hasMore.value = data.next_page_url ? true : false
    page.value++
  } catch {}
  finally { loadingDeals.value = false }
}

// 특가
async function loadSpecial() {
  try {
    const params = { is_special: 1, per_page: 8 }
    if (userLat.value) { params.lat = userLat.value; params.lng = userLng.value; params.radius_miles = radius.value }
    const { data } = await axios.get('/api/shopping/deals', { params })
    specialDeals.value = (data.data || data || []).filter(d => discountRate(d) >= 30)
  } catch { specialDeals.value = [] }
}

function openDeal(d) { selectedDeal.value = d }

onMounted(async () => {
  detectLocation()
  await Promise.all([loadStores(), loadDeals(true)])
  try { const { data } = await axios.get('/api/shopping/categories'); categories.value = data } catch {}
  loadSpecial()
})

// LocationBar handlers
function onLocationSearch(keyword) {
  searchKeyword.value = keyword
  loadStores()
}

function onLocationChange(location) {
  console.log('Location changed:', location)
  loadStores()
}

</script>
