<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <!-- 헤더: 모바일 -->
    <div class="lg:hidden mb-3">
      <div class="flex items-center justify-between mb-2">
        <h1 class="text-lg font-black text-gray-800">🤝 공동구매</h1>
        <div class="flex items-center gap-2">
          <button @click="showFilter = true" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold px-3 py-2 rounded-lg">🔍 필터</button>
          <RouterLink v-if="auth.isLoggedIn" to="/groupbuy/create" class="bg-amber-400 text-amber-900 text-xs font-bold px-3 py-2 rounded-lg">✏️ 등록</RouterLink>
        </div>
      </div>
      <div class="flex items-center gap-1.5 overflow-x-auto">
        <span v-if="statusFilter" class="text-[10px] bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-semibold whitespace-nowrap">
          {{ statusFilters.find(s => s.value === statusFilter)?.label || statusFilter }}
        </span>
        <span class="text-[10px] bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-semibold whitespace-nowrap">
          📍{{ selectedCityIdx == -1 ? '전국' : (myCity?.label || '내 위치') }}
        </span>
      </div>
    </div>
    <MobileFilter v-model="showFilter" @apply="loadPage()" @reset="statusFilter = ''; search = ''; selectedCityIdx = '-1'; onCityChange()">
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
        <input v-model="search" type="text" placeholder="상품명, 키워드..."
          class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-amber-400" />
      </div>
      <div>
        <label class="text-xs font-bold text-gray-600 mb-2 block">상태</label>
        <div class="grid grid-cols-2 gap-1.5">
          <button v-for="s in statusFilters" :key="s.value" @click="statusFilter = s.value"
            class="text-xs py-2 rounded-lg font-semibold border transition"
            :class="statusFilter === s.value ? 'bg-amber-50 text-amber-700 border-amber-300' : 'border-gray-200 text-gray-600'">
            {{ s.label }}
          </button>
        </div>
      </div>
    </MobileFilter>

    <!-- 헤더: 데스크탑 -->
    <div class="hidden lg:flex items-center justify-between mb-4 flex-wrap gap-2">
      <h1 class="text-xl font-black text-gray-800">🤝 공동구매</h1>
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
        <RouterLink v-if="auth.isLoggedIn" to="/groupbuy/create" class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-amber-500">✏️ 등록</RouterLink>
      </div>
    </div>

    <div class="grid grid-cols-12 gap-4">
    <!-- 왼쪽: 상태 필터 -->
    <div class="col-span-12 lg:col-span-2 hidden lg:block">
      <div class="sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto space-y-3 pr-0.5">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">📋 상태</div>
          <button v-for="s in statusFilters" :key="s.value" @click="showFavorites=false; statusFilter=s.value; loadPage()"
            class="w-full text-left px-3 py-2 text-xs transition"
            :class="!showFavorites && statusFilter===s.value ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">{{ s.label }}</button>
          <button v-if="auth.isLoggedIn" @click="showFavorites=true; loadFavoritesPage()"
            class="w-full text-left px-3 py-2 text-xs transition border-t"
            :class="showFavorites ? 'bg-red-50 text-red-600 font-bold' : 'text-gray-600 hover:bg-red-50/50'">
            🔖 내 북마크<span v-if="favCount > 0" class="ml-0.5">({{ favCount }})</span>
          </button>
        </div>
        <AdSlot page="groupbuy" position="left" :maxSlots="2" />
      </div>
    </div>
    <div class="col-span-12 lg:col-span-7">

    <!-- 목록 -->
    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="!items.length" class="text-center py-12">
      <div class="text-4xl mb-3">🤝</div>
      <div class="text-gray-500 font-semibold">검색 결과가 없습니다</div>
      <div class="text-xs text-gray-400 mt-1">다른 도시를 선택하거나 '전국'으로 검색해보세요</div>
    </div>
    <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <template v-for="(item, i) in items" :key="item.id">
      <RouterLink :to="'/groupbuy/' + item.id"
        class="relative block px-4 py-3 border-b border-gray-50 hover:bg-amber-50/50 transition"
        :class="item.status === 'completed' || item.status === 'cancelled' ? 'opacity-50' : ''">
        <BookmarkToggle v-if="auth.isLoggedIn" :active="favorited.has(item.id)" @toggle="toggleFav(item)" ribbon />
        <div class="flex items-start justify-between gap-3">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-1.5 mb-1">
              <span class="text-[10px] px-1.5 py-0.5 rounded font-bold"
                :class="{'bg-green-100 text-green-700':item.status==='recruiting','bg-blue-100 text-blue-700':item.status==='confirmed','bg-gray-100 text-gray-500':item.status==='completed','bg-red-100 text-red-600':item.status==='cancelled'}">
                {{ {recruiting:'모집중',confirmed:'확정',completed:'완료',cancelled:'취소'}[item.status] || item.status }}
              </span>
              <span v-if="item.category" class="text-[10px] bg-amber-50 text-amber-600 px-1.5 py-0.5 rounded">{{ item.category }}</span>
              <span v-if="myJoinedIds.has(item.id)" class="text-[10px] px-1.5 py-0.5 rounded font-bold bg-amber-400 text-amber-900">🙋 참여중</span>
            </div>
            <div class="text-sm font-medium text-gray-800 truncate">{{ item.title }}</div>
            <div class="text-xs text-gray-400 mt-1 flex items-center gap-1.5 flex-wrap">
              <UserName v-if="item.user?.id" :userId="item.user.id" :name="item.user.name" className="text-gray-500" />
              <span v-if="item.city">📍{{ item.city }}</span>
              <span v-if="item.deadline">⏰ {{ daysLeft(item.deadline) }}</span>
              <span v-if="item.created_at">🕐 {{ fmtDate(item.created_at) }}</span>
            </div>
            <!-- 프로그레스바 -->
            <div class="mt-2 flex items-center gap-2">
              <div class="flex-1 bg-gray-100 rounded-full h-2 overflow-hidden">
                <div class="h-full rounded-full transition-all"
                  :class="item.status==='recruiting' ? 'bg-amber-400' : 'bg-green-400'"
                  :style="{width: Math.min(100, (item.current_participants/(item.max_participants||item.min_participants||1))*100)+'%'}"></div>
              </div>
              <span class="text-[10px] text-gray-500 whitespace-nowrap">{{ item.current_participants }}명/{{ item.max_participants || item.min_participants }}명</span>
            </div>
          </div>
          <div class="flex-shrink-0 text-right">
            <div v-if="item.original_price" class="text-xs text-gray-400 line-through">${{ Number(item.original_price).toLocaleString() }}</div>
            <div class="text-amber-600 font-bold text-sm">${{ Number(item.group_price || item.original_price).toLocaleString() }}</div>
            <div v-if="currentDiscount(item)" class="text-[10px] text-red-500 font-bold">{{ currentDiscount(item) }}% OFF</div>
          </div>
        </div>
      </RouterLink>
      <MobileAdInline v-if="i === 4" page="groupbuy" />
      </template>
    </div>

    <Pagination :page="page" :lastPage="lastPage" @page="loadPage" />
    </div>
    <div class="col-span-12 lg:col-span-3 hidden lg:block">
      <SidebarWidgets api-url="/api/groupbuys" detail-path="/groupbuy/" :current-id="0"
        label="공동구매" :filter-params="locationParams" />
        <AdSlot page="groupbuy" position="right" :maxSlots="2" />
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
import { useBookmarkStore } from '../../stores/bookmarks'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import axios from 'axios'
import AdSlot from '../../components/AdSlot.vue'
import BookmarkToggle from '../../components/BookmarkToggle.vue'

const auth = useAuthStore()
const bStore = useBookmarkStore()
const BM_TYPE = 'App\\Models\\GroupBuy'
const route = useRoute()
const showFavorites = ref(false)
const favorited = ref(new Set())
const favCount = computed(() => bStore.getBookmarkedIds(BM_TYPE).length)
const statusFilter = ref('')
const statusFilters = [
  { value: '', label: '전체' },{ value: 'recruiting', label: '🟢 모집중' },
  { value: 'confirmed', label: '🔵 확정' },{ value: 'completed', label: '✅ 완료' },
]
const { city, radius: locRadius, locationQuery, koreanCities, init: initLocation, selectKoreanCity, setRadius } = useLocation()

const items = ref([])
const loading = ref(true)
const myJoinedIds = ref(new Set())
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

const showFilter = ref(false)

function daysLeft(deadline) {
  if (!deadline) return ''
  const diff = new Date(deadline) - new Date()
  if (diff <= 0) return '마감'
  const days = Math.floor(diff / 86400000)
  const hours = Math.floor((diff % 86400000) / 3600000)
  return days > 0 ? `${days}일 남음` : `${hours}시간 남음`
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

function currentDiscount(item) {
  if (!item.discount_tiers || !Array.isArray(item.discount_tiers)) return 0
  let best = 0
  for (const t of item.discount_tiers) {
    if (item.current_participants >= t.min_people && t.discount_pct > best) best = t.discount_pct
  }
  return best
}

async function loadPage(p = 1) {
  loading.value = true
  page.value = p

  const params = { page: p, per_page: 20 }
  if (search.value) params.search = search.value
  if (statusFilter.value) params.status = statusFilter.value

  if (radius.value !== '0') {
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
    const { data } = await axios.get('/api/groupbuys', { params })
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
    const { data } = await axios.get('/api/bookmarks/check', { params: { type: 'App\\Models\\GroupBuy', ids } })
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
async function loadMyJoined() {
  if (!auth.isLoggedIn) return
  try {
    const { data } = await axios.get('/api/groupbuys/my-joined')
    myJoinedIds.value = new Set(data.data || [])
  } catch {}
}

async function loadFavoritesPage() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/bookmarks', { params: { type: 'App\\Models\\GroupBuy', per_page: 50 } })
    const bms = data.data?.data || []
    items.value = bms.map(b => b.bookmarkable).filter(Boolean)
    lastPage.value = 1
    loadFavorited()
  } catch {}
  loading.value = false
}

onMounted(async () => {
  bStore.loadAll()
  loadMyJoined()
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
  }
})
</script>