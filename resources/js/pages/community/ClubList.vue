<template>
<div class="min-h-screen">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <!-- 모바일 헤더 -->
    <div class="lg:hidden mb-3">
      <div class="flex items-center justify-between mb-2">
        <h1 class="flex items-center gap-2.5 text-lg font-bold text-ink">
          <span class="icon-chip w-9 h-9 bg-teal-50 text-teal-600"><AppIcon name="users" :size="20" /></span>
          동호회
        </h1>
        <div class="flex items-center gap-2">
          <button @click="showFilter = true" class="btn-secondary !px-3 !py-2 !text-xs"><AppIcon name="search" :size="14" />필터</button>
          <RouterLink v-if="auth.isLoggedIn" to="/clubs/create" class="btn-primary !px-3 !py-2 !text-xs"><AppIcon name="plus" :size="14" />만들기</RouterLink>
        </div>
      </div>
      <div class="flex items-center gap-1.5 overflow-x-auto scrollbar-hide">
        <span v-if="type" class="badge-primary">
          {{ types.find(t => t.value === type)?.label }}
        </span>
        <span v-if="catFilter" class="badge-primary">
          {{ clubCategories.find(c => c.value === catFilter)?.label }}
        </span>
        <span class="badge-gray">
          <AppIcon name="map-pin" :size="12" />{{ selectedCityIdx == -1 ? '전국' : (myCity?.label || '내 위치') }}
        </span>
      </div>
    </div>
    <MobileFilter v-model="showFilter" @apply="loadClubs()" @reset="type = ''; catFilter = ''; search = ''; selectedCityIdx = '-1'; onCityChange()">
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
        <input v-model="search" type="text" placeholder="동호회 이름..."
          class="input-soft" />
      </div>
      <div class="mb-4">
        <label class="input-label">유형</label>
        <div class="flex gap-2">
          <button v-for="t in types" :key="t.value" @click="type = t.value"
            class="flex-1 py-2 rounded-xl font-bold text-xs border-2 transition-colors"
            :class="type === t.value ? 'bg-amber-400 text-white border-amber-400' : 'border-gray-200 text-ink-muted'">{{ t.label }}</button>
        </div>
      </div>
      <div>
        <label class="input-label">분류</label>
        <div class="grid grid-cols-3 gap-1.5">
          <button v-for="c in clubCategories" :key="c.value" @click="catFilter = c.value"
            class="text-xs py-2 rounded-xl font-semibold border transition-colors"
            :class="catFilter === c.value ? 'bg-amber-50 text-amber-700 border-amber-300' : 'border-gray-200 text-ink-light'">{{ c.label }}</button>
        </div>
      </div>
    </MobileFilter>

    <!-- 데스크탑 헤더 -->
    <div class="hidden lg:flex items-center justify-between mb-4 flex-wrap gap-2">
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
        <span class="icon-chip w-9 h-9 bg-teal-50 text-teal-600"><AppIcon name="users" :size="20" /></span>
        동호회
      </h1>
      <div class="flex items-center gap-2 flex-wrap">
        <div class="flex items-center bg-gray-100 rounded-xl p-1">
          <button v-for="t in types" :key="t.value" @click="type=t.value; loadClubs()"
            class="px-2.5 py-1 rounded-lg text-xs transition"
            :class="type===t.value ? 'bg-white text-ink font-semibold shadow-sm' : 'text-ink-muted'">{{ t.label }}</button>
        </div>
        <template v-if="type !== 'online'">
          <AppIcon name="map-pin" :size="16" class="text-amber-600" />
          <select v-model="selectedCityIdx" @change="onCityChange" class="input-soft !w-auto !pl-3 !py-1.5 !text-xs font-semibold">
            <option value="-2" v-if="myCity">📌 내 위치 ({{ myCity.label || myCity.name }})</option>
            <option value="-1">🇺🇸 전국</option>
            <optgroup label="한인 밀집 도시">
              <option v-for="(c, i) in koreanCities" :key="i" :value="i">{{ c.label }}</option>
            </optgroup>
          </select>
          <select v-if="selectedCityIdx !== '-1' && selectedCityIdx !== -1" v-model="radius" @change="loadClubs()" class="input-soft !w-auto !pl-3 !py-1.5 !text-xs">
            <option value="10">10mi</option><option value="30">30mi</option><option value="50">50mi</option><option value="100">100mi</option>
          </select>
        </template>
        <form @submit.prevent="loadClubs()" class="flex gap-1.5">
          <input v-model="search" type="text" placeholder="검색..." class="input-soft !w-40 !py-1.5" />
          <button type="submit" class="btn-primary !px-3 !py-1.5 !text-xs">검색</button>
        </form>
        <RouterLink v-if="auth.isLoggedIn" to="/clubs/create" class="btn-primary !px-3 !py-1.5 !text-xs"><AppIcon name="plus" :size="14" />동호회 만들기</RouterLink>
      </div>
    </div>

    <div class="grid grid-cols-12 gap-4">
    <!-- 왼쪽: 카테고리 -->
    <div class="col-span-12 lg:col-span-2 hidden lg:block">
      <div class="sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto space-y-3 pr-0.5">
        <div class="card overflow-hidden">
          <div class="px-3 py-2.5 border-b border-gray-50 flex items-center gap-1.5 font-bold text-xs text-ink"><AppIcon name="list" :size="13" class="text-teal-600" />카테고리</div>
          <button v-for="c in clubCategories" :key="c.value" @click="showFavorites=false; catFilter=c.value; loadClubs()"
            class="w-full text-left px-3 py-2 text-xs transition-colors"
            :class="catFilter===c.value ? 'bg-amber-50 text-amber-700 font-bold' : 'text-ink-light hover:bg-amber-50/50'">{{ c.label }}</button>

          <button v-if="auth.isLoggedIn" @click="showFavorites=true; loadFavoritesPage()"
            class="w-full text-left px-3 py-2 text-xs transition-colors border-t border-gray-50 flex items-center gap-1"
            :class="showFavorites ? 'bg-red-50 text-red-600 font-bold' : 'text-ink-light hover:bg-red-50/50'">
            <AppIcon name="bookmark" :size="13" />내 북마크<span v-if="favCount > 0" class="ml-0.5">({{ favCount }})</span>
          </button>
          <template v-if="auth.isLoggedIn && myClubs.length">
            <div class="px-3 py-2.5 border-t border-b border-gray-50 flex items-center gap-1.5 font-bold text-xs text-ink mt-1"><AppIcon name="user" :size="13" class="text-teal-600" />내 동호회</div>
            <router-link v-for="mc in myClubs" :key="mc.id" :to="`/clubs/${mc.id}`"
              class="block w-full text-left px-3 py-2 text-xs text-ink-light hover:bg-amber-50/50 transition-colors truncate">
              {{ mc.name }}
            </router-link>
          </template>
        </div>
        <AdSlot page="clubs" position="left" :maxSlots="2" />
      </div>
    </div>
    <!-- 메인 -->
    <div class="col-span-12 lg:col-span-7">
    <div v-if="!clubs.length && !loading" class="py-16 text-center">
      <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="users" :size="28" :stroke-width="1.5" /></div>
      <p class="text-sm text-ink-muted">동호회가 없습니다</p>
    </div>
    <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <template v-for="(club, i) in clubs" :key="club.id">
      <RouterLink :to="`/clubs/${club.id}`"
        class="card card-hover p-4"
        :class="clubRowClass(club)">
        <div class="flex items-center gap-2 mb-1" v-if="isPromoted(club)">
          <span v-if="club.promotion_tier === 'national'" class="inline-flex items-center gap-0.5 text-[11px] bg-red-500 text-white font-bold px-1.5 py-0.5 rounded-md"><AppIcon name="globe" :size="11" />전국구</span>
          <span v-else-if="club.promotion_tier === 'state_plus'" class="inline-flex items-center gap-0.5 text-[11px] bg-blue-500 text-white font-bold px-1.5 py-0.5 rounded-md"><AppIcon name="star" :size="11" />주+</span>
          <span v-else-if="club.promotion_tier === 'sponsored'" class="inline-flex items-center gap-0.5 text-[11px] bg-amber-500 text-white font-bold px-1.5 py-0.5 rounded-md"><AppIcon name="megaphone" :size="11" />스폰</span>
          <span v-else class="inline-flex items-center gap-0.5 text-[11px] bg-purple-500 text-white font-bold px-1.5 py-0.5 rounded-md"><AppIcon name="sparkles" :size="11" />상위노출</span>
        </div>
        <div class="flex items-center gap-3 mb-3">
          <div class="icon-chip w-12 h-12 bg-teal-50 text-teal-600"><AppIcon name="users" :size="24" /></div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-bold text-ink truncate">{{ club.name }}</div>
            <div class="text-xs text-ink-muted">{{ club.category }} · {{ club.type === 'online' ? '온라인' : '지역' }}</div>
          </div>
        </div>
        <div class="text-xs text-ink-muted line-clamp-2 mb-2">{{ club.description }}</div>
        <div class="flex items-center justify-between text-xs text-ink-muted">
          <span class="inline-flex items-center gap-1"><AppIcon name="users" :size="12" />{{ club.member_count }}명</span>
          <div class="flex items-center gap-2">
            <span v-if="club.distance !== undefined && club.distance !== null" class="text-amber-600 font-semibold">{{ Number(club.distance).toFixed(1) }}mi</span>
            <span class="px-2 py-0.5 rounded-full font-semibold" :class="club.type==='online' ? 'bg-blue-50 text-blue-600' : 'bg-emerald-50 text-emerald-600'">
              {{ club.type === 'online' ? '온라인' : '지역' }}
            </span>
            <BookmarkToggle v-if="auth.isLoggedIn" :active="favorited.has(club.id)" @toggle="toggleFav(club)" size="sm" />
          </div>
        </div>
      </RouterLink>
      <MobileBanner v-if="i === 4" page="clubs" class="sm:col-span-2 lg:hidden" />
      </template>
    </div>
    <!-- 📝 텍스트 인라인 -->
    <TextInlineAd page="clubs" class="mt-3" />
    </div>
    <!-- 오른쪽 위젯 -->
    <div class="col-span-12 lg:col-span-3 hidden lg:block">
      <SidebarWidgets :currentCategory="catFilter" api-url="/api/clubs" detail-path="/clubs/" :current-id="0"
        label="동호회" :filter-params="locationParams" />
        <AdSlot page="clubs" position="right" :maxSlots="2" />
    </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useLocation } from '../../composables/useLocation'
import { useAuthStore } from '../../stores/auth'
import { useBookmarkStore } from '../../stores/bookmarks'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import axios from 'axios'
import AdSlot from '../../components/AdSlot.vue'
import BookmarkToggle from '../../components/BookmarkToggle.vue'
import MobileBanner from '../../components/MobileBanner.vue'
import TextInlineAd from '../../components/TextInlineAd.vue'
import AppIcon from '../../components/AppIcon.vue'

const auth = useAuthStore()
const bStore = useBookmarkStore()
const BM_TYPE = 'App\\Models\\Club'
const route = useRoute()
const { city, locationQuery, koreanCities, init: initLocation, selectKoreanCity } = useLocation()

const clubs = ref([])
const loading = ref(true)
const showFilter = ref(false)
const showFavorites = ref(false)
const favorited = ref(new Set())
const favCount = computed(() => bStore.getBookmarkedIds(BM_TYPE).length)
const myClubs = ref([])
const catFilter = ref('')
const clubCategories = [
  { value: '', label: '전체' },
  { value: '등산', label: '🥾 등산' },
  { value: '스포츠', label: '⚽ 스포츠' },
  { value: '요리', label: '🍳 요리' },
  { value: '게임', label: '🎮 게임' },
  { value: '여행', label: '✈️ 여행' },
  { value: '온라인', label: '🌐 온라인' },
  { value: '기타', label: '📋 기타' },
]
const type = ref('')
const search = ref('')
const radius = ref(String(auth.user?.default_radius || 30))
const selectedCityIdx = ref('-2')
const myCity = ref(null)

const locationParams = computed(() => {
  const idx = parseInt(selectedCityIdx.value)
  if (idx === -1) return {}
  let lat, lng
  if (idx >= 0) { lat = koreanCities[idx]?.lat; lng = koreanCities[idx]?.lng }
  else if (myCity.value?.lat) { lat = myCity.value.lat; lng = myCity.value.lng }
  return lat && lng ? { lat, lng, radius: parseInt(radius.value) } : {}
})

const types = [
  { value: '', label: '전체' },
  { value: 'online', label: '🌐 온라인' },
  { value: 'local', label: '📍 지역' },
]

const locationInfo = computed(() => {
  if (selectedCityIdx.value === -1 || selectedCityIdx.value === '-1') return '전국 검색 중'
  const c = selectedCityIdx.value === '-2' || selectedCityIdx.value === -2
    ? myCity.value
    : koreanCities[selectedCityIdx.value]
  if (!c) return '위치를 선택해주세요'
  return (c.label || c.name) + ' 기준 ' + radius.value + 'mi 반경'
})

function isPromoted(club) {
  if (club.promotion_tier && club.promotion_tier !== 'none' && club.promotion_expires_at
      && new Date(club.promotion_expires_at) > new Date()) return true
  if (club.boosted_until && new Date(club.boosted_until) > new Date()) return true
  return false
}
function clubRowClass(club) {
  if (club.promotion_tier === 'national') return 'bg-white border-2 border-red-400'
  if (club.promotion_tier === 'state_plus') return 'bg-white border-2 border-blue-400'
  if (club.promotion_tier === 'sponsored') return 'bg-white border-2 border-amber-400'
  if (isPromoted(club)) return 'bg-purple-50/30 border-2 border-purple-400'
  return 'bg-white'
}

function onCityChange() {
  const idx = parseInt(selectedCityIdx.value)
  if (idx === -1) radius.value = '0'
  else if (idx >= 0) { selectKoreanCity(idx); radius.value = '30' }
  loadClubs()
}

async function loadClubs() {
  loading.value = true
  const params = {}
  if (type.value) params.type = type.value
  if (search.value) params.search = search.value
  if (catFilter.value) params.category = catFilter.value

  // 지역 동호회일 때 위치 필터
  if (type.value !== 'online' && radius.value !== '0') {
    const idx = parseInt(selectedCityIdx.value)
    let lat, lng
    if (idx >= 0) { lat = koreanCities[idx].lat; lng = koreanCities[idx].lng }
    else if (myCity.value?.lat) { lat = myCity.value.lat; lng = myCity.value.lng }
    if (lat && lng) { params.lat = lat; params.lng = lng; params.radius = parseInt(radius.value) }
  }

  try {
    const { data } = await axios.get('/api/clubs', { params })
    clubs.value = data.data?.data || data.data || []
  } catch {}
  loading.value = false
  loadFavorited()
}

// 하트 (Bookmark)
async function loadFavorited() {
  if (!auth.isLoggedIn || !clubs.value.length) return
  try {
    const ids = clubs.value.map(i => i.id).join(',')
    const { data } = await axios.get('/api/bookmarks/check', { params: { type: BM_TYPE, ids } })
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
    const { data } = await axios.get('/api/bookmarks', { params: { type: BM_TYPE, per_page: 50 } })
    const bms = data.data?.data || []
    clubs.value = bms.map(b => b.bookmarkable).filter(Boolean)
    loadFavorited()
  } catch {}
  loading.value = false
}

async function loadMyClubs() {
  if (!auth.isLoggedIn) return
  try { const { data } = await axios.get('/api/my-clubs'); myClubs.value = data.data || [] } catch {}
}

onMounted(async () => {
  bStore.loadAll()
  await initLocation()
  if (city.value) { myCity.value = { ...city.value }; selectedCityIdx.value = '-2' }
  else { selectedCityIdx.value = '-1'; radius.value = '0' }
  loadClubs()
  loadMyClubs()
})

watch(() => route.params.id, (newId, oldId) => {
  if (oldId && !newId) {
    loadClubs()
  }
})
</script>
