<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <!-- 헤더 -->
    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
      <h1 class="text-xl font-black text-gray-800">👥 동호회</h1>
      <div class="flex items-center gap-2 flex-wrap">
        <div class="flex items-center gap-1">
          <button v-for="t in types" :key="t.value" @click="type=t.value; loadClubs()"
            class="px-2.5 py-1 rounded-full text-[10px] font-bold transition"
            :class="type===t.value ? 'bg-amber-400 text-amber-900' : 'bg-white border text-gray-500 hover:bg-amber-50'">{{ t.label }}</button>
        </div>
        <template v-if="type !== 'online'">
          <span class="text-amber-600 text-sm">📍</span>
          <select v-model="selectedCityIdx" @change="onCityChange" class="border border-gray-200 rounded-lg px-2 py-1.5 text-xs font-semibold text-gray-700 outline-none focus:ring-2 focus:ring-amber-400 bg-amber-50">
            <option value="-2" v-if="myCity">📌 내 위치 ({{ myCity.label || myCity.name }})</option>
            <option value="-1">🇺🇸 전국</option>
            <optgroup label="한인 밀집 도시">
              <option v-for="(c, i) in koreanCities" :key="i" :value="i">{{ c.label }}</option>
            </optgroup>
          </select>
          <select v-if="selectedCityIdx !== '-1' && selectedCityIdx !== -1" v-model="radius" @change="loadClubs()" class="border border-gray-200 rounded-lg px-2 py-1.5 text-xs text-gray-600 outline-none">
            <option value="10">10mi</option><option value="30">30mi</option><option value="50">50mi</option><option value="100">100mi</option>
          </select>
        </template>
        <form @submit.prevent="loadClubs()" class="flex gap-1">
          <input v-model="search" type="text" placeholder="검색..." class="border rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-amber-400 outline-none w-40" />
          <button type="submit" class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-amber-500">검색</button>
        </form>
        <RouterLink v-if="auth.isLoggedIn" to="/clubs" class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-amber-500">+ 동호회 만들기</RouterLink>
      </div>
    </div>

    <div class="grid grid-cols-12 gap-4">
    <!-- 왼쪽: 카테고리 -->
    <div class="col-span-12 lg:col-span-2 hidden lg:block">
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-20">
        <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">📋 분류</div>
        <button v-for="c in clubCategories" :key="c.value" @click="catFilter=c.value; loadClubs()"
          class="w-full text-left px-3 py-2 text-xs transition"
          :class="catFilter===c.value ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">{{ c.label }}</button>
      </div>
    </div>
    <!-- 메인 -->
    <div class="col-span-12 lg:col-span-7">
    <div v-if="!clubs.length && !loading" class="text-center py-12 text-gray-400">동호회가 없습니다</div>
    <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <RouterLink v-for="club in clubs" :key="club.id" :to="`/clubs/${club.id}`"
        class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 hover:shadow-md hover:-translate-y-0.5 transition-all">
        <div class="flex items-center gap-3 mb-3">
          <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center text-2xl">👥</div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-bold text-gray-800 truncate">{{ club.name }}</div>
            <div class="text-[10px] text-gray-400">{{ club.category }} · {{ club.type === 'online' ? '온라인' : '지역' }}</div>
          </div>
        </div>
        <div class="text-xs text-gray-500 line-clamp-2 mb-2">{{ club.description }}</div>
        <div class="flex items-center justify-between text-[10px] text-gray-400">
          <span>👥 {{ club.member_count }}명</span>
          <div class="flex items-center gap-2">
            <span v-if="club.distance !== undefined && club.distance !== null" class="text-amber-600 font-semibold">{{ Number(club.distance).toFixed(1) }}mi</span>
            <span class="px-2 py-0.5 rounded-full" :class="club.type==='online' ? 'bg-blue-50 text-blue-600' : 'bg-green-50 text-green-600'">
              {{ club.type === 'online' ? '🌐 온라인' : '📍 지역' }}
            </span>
          </div>
        </div>
      </RouterLink>
    </div>
    </div>
    <!-- 오른쪽 위젯 -->
    <div class="col-span-12 lg:col-span-3 hidden lg:block">
      <SidebarWidgets api-url="/api/clubs" detail-path="/clubs/" :current-id="0"
        label="동호회" :filter-params="locationParams" />
    </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useLocation } from '../../composables/useLocation'
import { useAuthStore } from '../../stores/auth'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import axios from 'axios'

const auth = useAuthStore()
const { city, locationQuery, koreanCities, init: initLocation, selectKoreanCity } = useLocation()

const clubs = ref([])
const loading = ref(true)
const catFilter = ref('')
const clubCategories = [
  { value: '', label: '전체' },{ value: 'sports', label: '⚽ 운동' },{ value: 'books', label: '📚 독서' },
  { value: 'cooking', label: '🍳 요리' },{ value: 'photo', label: '📷 사진' },{ value: 'tech', label: '💻 기술' },
  { value: 'finance', label: '💰 재테크' },{ value: 'parenting', label: '👶 육아' },
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
}

onMounted(async () => {
  await initLocation()
  if (city.value) { myCity.value = { ...city.value }; selectedCityIdx.value = '-2' }
  else { selectedCityIdx.value = '-1'; radius.value = '0' }
  loadClubs()
})
</script>
