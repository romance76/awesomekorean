<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <!-- 헤더 -->
    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
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
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-20">
        <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">📋 유형</div>
        <button v-for="c in reCategories" :key="c.value" @click="activeCat=c.value; activeItem=null; loadPage()"
          class="w-full text-left px-3 py-2 text-xs transition"
          :class="activeCat===c.value ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">{{ c.label }}</button>
      </div>
    </div>
    <div class="col-span-12 lg:col-span-7">

    <div class="mb-2">
      <span class="font-bold text-amber-700 text-sm">{{ activeCat ? (reCategories.find(c => c.value === activeCat)?.label || activeCat) : '전체' }}</span>
      <span v-if="!activeCat" class="text-xs text-gray-400 ml-2">모든 부동산 매물을 볼 수 있습니다</span>
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
            <div class="bg-gray-50 rounded-lg py-1.5"><strong>{{ activeItem.view_count }}</strong> 조회</div>
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
    <!-- 카드형 -->
    <div v-else-if="viewMode==='card'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <div v-for="item in items" :key="item.id" @click="openItem(item)"
        class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 hover:shadow-md hover:-translate-y-0.5 transition-all cursor-pointer">
        <div class="flex items-center gap-3 mb-3">
          <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center text-2xl">{{ item.type==='sale'?'🏠':item.type==='rent'?'🔑':'👥' }}</div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-bold text-gray-800 truncate">{{ item.title }}</div>
            <div class="text-[10px] text-gray-400">{{ {rent:'렌트',sale:'매매',roommate:'룸메이트'}[item.type] || item.type }} · {{ item.property_type }}</div>
          </div>
          <div class="text-amber-600 font-black text-sm">${{ Number(item.price || 0).toLocaleString() }}{{ item.type==='rent'?'/월':'' }}</div>
        </div>
        <div class="text-xs text-gray-500 line-clamp-2 mb-2">{{ (item.content || '').slice(0, 80) }}</div>
        <div class="flex items-center justify-between text-[10px] text-gray-400">
          <span>📍 {{ item.city }}, {{ item.state }}</span>
          <div class="flex gap-2">
            <span v-if="item.bedrooms">🛏 {{ item.bedrooms }}방</span>
            <span v-if="item.sqft">📐 {{ item.sqft }}sqft</span>
            <span>👁 {{ item.view_count || 0 }}</span>
          </div>
        </div>
      </div>
    </div>
    <!-- 리스트형 -->
    <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div v-for="item in items" :key="item.id" @click="openItem(item)"
        class="px-4 py-3 border-b border-gray-50 hover:bg-amber-50/50 hover:border-l-2 hover:border-l-amber-400 transition cursor-pointer">
        <div class="flex items-center justify-between">
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium text-gray-800 truncate">{{ item.title || item.name }}</div>
            <div class="text-xs text-gray-400 mt-0.5 flex items-center gap-1.5 flex-wrap">
              <span v-if="item.user?.name"><UserName :userId="item.user?.id" :name="item.user?.name" /></span>
              <span v-else-if="item.company || item.organizer">{{ item.company || item.organizer }}</span>
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
    </div>

    <div v-if="lastPage > 1" class="flex justify-center gap-1.5 mt-4">
      <button v-for="pg in Math.min(lastPage, 10)" :key="pg" @click="loadPage(pg)"
        class="px-3 py-1 rounded text-sm" :class="pg===page?'bg-amber-400 text-amber-900 font-bold':'bg-white text-gray-600 border hover:bg-amber-50'">{{ pg }}</button>
    </div>
    </div>
    <!-- 오른쪽 위젯 -->
    <div class="col-span-12 lg:col-span-3 hidden lg:block">
      <SidebarWidgets :inline="true" @select="openItem" api-url="/api/realestate" detail-path="/realestate/" :current-id="0"
        label="매물" :filter-params="locationParams" />
    </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { useRoute } from 'vue-router'
import { ref, computed, onMounted } from 'vue'
import { useLocation } from '../../composables/useLocation'
import { useAuthStore } from '../../stores/auth'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import CommentSection from '../../components/CommentSection.vue'
import { useMenuConfig } from '../../composables/useMenuConfig'
import axios from 'axios'

const auth = useAuthStore()
const route = useRoute()
const activeCat = ref('')
const { loadConfig, getDefaultView } = useMenuConfig()
const viewMode = ref('list')
const reCategories = [
  { value: '', label: '전체' },{ value: 'rent', label: '🔑 렌트' },
  { value: 'sale', label: '🏠 매매' },{ value: 'roommate', label: '👥 룸메이트' },
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

async function loadPage(p = 1) {
  loading.value = true
  page.value = p

  const params = { page: p, per_page: 20 }
  if (search.value) params.search = search.value
  if (activeCat.value) params.type = activeCat.value

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
    const { data } = await axios.get('/api/realestate', { params })
    items.value = data.data?.data || []
    lastPage.value = data.data?.last_page || 1
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
</script>