<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <!-- 헤더: 데스크탑 -->
    <div class="hidden lg:flex items-center justify-between mb-4 flex-wrap gap-2">
      <h1 class="text-xl font-black text-gray-800">💼 구인구직</h1>
      <div class="flex items-center gap-2 flex-wrap">
        <select v-model="selectedCityIdx" @change="onCityChange"
          class="border border-gray-200 rounded-lg px-2 py-1.5 text-xs font-semibold text-gray-700 outline-none focus:ring-2 focus:ring-amber-400 bg-amber-50">
          <option value="-2" v-if="myCity">📌 내 위치 ({{ myCity.label || myCity.name }})</option>
          <option value="-1">🇺🇸 전국</option>
          <optgroup label="한인 밀집 도시">
            <option v-for="(c, i) in koreanCities" :key="i" :value="i">{{ c.label }}</option>
          </optgroup>
        </select>
        <select v-if="selectedCityIdx !== '-1' && selectedCityIdx !== -1" v-model="radius" @change="loadPage()"
          class="border border-gray-200 rounded-lg px-2 py-1.5 text-xs text-gray-600 outline-none">
          <option value="10">10mi</option><option value="30">30mi</option><option value="50">50mi</option><option value="100">100mi</option>
        </select>
        <form @submit.prevent="loadPage()" class="flex gap-1">
          <input v-model="search" type="text" placeholder="검색..." class="border rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-amber-400 outline-none w-40" />
          <button type="submit" class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-amber-500">검색</button>
        </form>
        <RouterLink v-if="auth.isLoggedIn" to="/jobs/write" class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-amber-500">✏️ 등록</RouterLink>
      </div>
    </div>

    <!-- 헤더: 모바일 (필터 바텀시트) -->
    <div class="lg:hidden mb-3">
      <div class="flex items-center justify-between mb-2">
        <h1 class="text-lg font-black text-gray-800">💼 구인구직</h1>
        <div class="flex items-center gap-2">
          <button @click="showFilter = true" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold px-3 py-2 rounded-lg flex items-center gap-1">
            🔍 필터
          </button>
          <RouterLink v-if="auth.isLoggedIn" to="/jobs/write" class="bg-amber-400 text-amber-900 text-xs font-bold px-3 py-2 rounded-lg">✏️ 등록</RouterLink>
        </div>
      </div>
      <!-- 선택된 필터 태그 -->
      <div class="flex items-center gap-1.5 overflow-x-auto">
        <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold whitespace-nowrap"
          :class="postType === 'hiring' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800'">
          {{ postType === 'hiring' ? '💼 구인' : '🙋 구직' }}
        </span>
        <span v-if="activeCat" class="text-[10px] bg-amber-50 text-amber-700 px-2 py-0.5 rounded-full font-semibold whitespace-nowrap">
          {{ jobCategories.find(c => c.value === activeCat)?.label || activeCat }}
        </span>
        <span class="text-[10px] bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-semibold whitespace-nowrap">
          📍{{ selectedCityIdx == -1 ? '전국' : (koreanCities[selectedCityIdx]?.label || '내 위치') }}
        </span>
        <span v-if="search" class="text-[10px] bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-semibold whitespace-nowrap">
          "{{ search }}"
        </span>
      </div>
    </div>

    <!-- 모바일 필터 (위→아래) -->
    <MobileFilter v-model="showFilter" @apply="loadPage()" @reset="postType = 'hiring'; activeCat = ''; search = ''; selectedCityIdx = -1; onCityChange()">
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
        <input v-model="search" type="text" placeholder="직종, 회사명, 키워드..."
          class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-amber-400" />
      </div>
      <div class="mb-4">
        <label class="text-xs font-bold text-gray-600 mb-2 block">공고 유형</label>
        <div class="flex gap-2">
          <button @click="postType = 'hiring'" class="flex-1 py-2.5 rounded-lg font-bold text-sm border-2 transition"
            :class="postType === 'hiring' ? 'bg-amber-400 text-amber-900 border-amber-400' : 'border-gray-200 text-gray-500'">💼 구인</button>
          <button @click="postType = 'seeking'" class="flex-1 py-2.5 rounded-lg font-bold text-sm border-2 transition"
            :class="postType === 'seeking' ? 'bg-blue-500 text-white border-blue-500' : 'border-gray-200 text-gray-500'">🙋 구직</button>
        </div>
      </div>
      <div>
        <label class="text-xs font-bold text-gray-600 mb-2 block">카테고리</label>
        <div class="grid grid-cols-3 gap-1.5">
          <button v-for="c in jobCategories" :key="c.value" @click="activeCat = c.value"
            class="text-xs py-2 rounded-lg font-semibold border transition"
            :class="activeCat === c.value ? 'bg-amber-50 text-amber-700 border-amber-300' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
            {{ c.label }}
          </button>
        </div>
      </div>
    </MobileFilter>

    <div class="grid grid-cols-12 gap-4">
    <!-- 왼쪽: 카테고리 사이드바 + 구인/구직 토글 (lg 이상) -->
    <div class="col-span-12 lg:col-span-2 hidden lg:block">
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-20">
        <div class="px-3 py-2.5 border-b font-bold text-xs text-gray-800 flex items-center justify-between">
          <span>📋 카테고리</span>
        </div>
        <!-- 구인/구직 작은 토글 -->
        <div class="flex border-b">
          <button @click="postType = 'hiring'; loadPage()"
            class="flex-1 py-1.5 text-[10px] font-bold transition"
            :class="postType === 'hiring' ? 'bg-amber-400 text-amber-900' : 'text-gray-400 hover:bg-gray-50'">
            💼 구인
          </button>
          <button @click="postType = 'seeking'; loadPage()"
            class="flex-1 py-1.5 text-[10px] font-bold transition"
            :class="postType === 'seeking' ? 'bg-blue-500 text-white' : 'text-gray-400 hover:bg-gray-50'">
            🙋 구직
          </button>
        </div>
        <button v-for="c in jobCategories" :key="c.value" @click="activeCat = c.value; loadPage()"
          class="w-full text-left px-3 py-2 text-xs transition"
          :class="activeCat === c.value
            ? (postType === 'hiring' ? 'bg-amber-50 text-amber-700 font-bold' : 'bg-blue-50 text-blue-700 font-bold')
            : 'text-gray-600 hover:bg-gray-50'">{{ c.label }}</button>
      </div>
      <AdSlot page="jobs" position="left" :maxSlots="2" class="mt-3" />
    </div>
    <div class="col-span-12 lg:col-span-7">

    <div class="mb-2">
      <span class="text-sm font-bold" :class="postType === 'hiring' ? 'text-amber-700' : 'text-blue-700'">
        {{ activeCat ? (jobCategories.find(c => c.value === activeCat)?.label || activeCat) : '전체' }}
      </span>
      <span v-if="!activeCat" class="text-xs text-gray-400 ml-2">
        {{ postType === 'hiring' ? '모든 채용 공고를 볼 수 있습니다' : '모든 구직 인재를 볼 수 있습니다' }}
      </span>
    </div>

    <!-- 목록 -->
    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="!items.length" class="text-center py-12">
      <div class="text-4xl mb-3">{{ postType === 'hiring' ? '💼' : '🙋' }}</div>
      <div class="text-gray-500 font-semibold">검색 결과가 없습니다</div>
    </div>
    <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div v-for="item in items" :key="item.id" @click="goDetail(item)"
        class="px-4 py-3 border-b border-gray-50 transition cursor-pointer"
        :class="postType === 'hiring'
          ? 'hover:bg-amber-50/50 hover:border-l-2 hover:border-l-amber-400'
          : 'hover:bg-blue-50/50 hover:border-l-2 hover:border-l-blue-400'">
        <div class="flex items-center justify-between">
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium text-gray-800 truncate">{{ item.title || item.name }}</div>
            <div class="text-xs text-gray-400 mt-0.5 flex items-center gap-1.5 flex-wrap">
              <!-- 구인: 회사명 표시, 구직: 유저 이름 표시 -->
              <template v-if="postType === 'seeking'">
                <UserName v-if="item.user?.id" :userId="item.user?.id" :name="item.user?.name" className="text-blue-600 font-semibold" />
                <span v-else class="text-blue-600 font-semibold">{{ item.user?.name || '익명' }}</span>
              </template>
              <template v-else>
                <UserName v-if="item.user?.id" :userId="item.user?.id" :name="item.user?.name || item.company || item.organizer" className="text-gray-600" />
                <span v-else-if="item.company || item.organizer">{{ item.company || item.organizer }}</span>
              </template>
              <span v-if="item.city" class="flex items-center gap-0.5">📍{{ item.city }}, {{ item.state }}</span>
              <span v-if="item.distance !== undefined && item.distance !== null"
                class="font-semibold" :class="postType === 'hiring' ? 'text-amber-600' : 'text-blue-600'">
                {{ Number(item.distance).toFixed(1) }}mi
              </span>
              <span v-if="item.view_count">👁{{ item.view_count }}</span>
            </div>
          </div>
          <div class="ml-3 flex-shrink-0 text-right">
            <div v-if="item.price !== undefined && item.price !== null"
              class="font-bold text-sm" :class="postType === 'hiring' ? 'text-amber-600' : 'text-blue-600'">
              ${{ Number(item.price).toLocaleString() }}
            </div>
            <div v-if="item.salary_min"
              class="font-bold text-xs" :class="postType === 'hiring' ? 'text-amber-600' : 'text-blue-600'">
              ${{ item.salary_min }}~${{ item.salary_max }}/{{ item.salary_type }}
            </div>
            <div v-if="item.rating" class="text-amber-400 text-xs">{{'★'.repeat(Math.round(item.rating))}} {{ item.rating }}</div>
          </div>
        </div>
      </div>
    </div>

    <Pagination :page="page" :lastPage="lastPage" @page="loadPage" />
    </div>

    <!-- 오른쪽: 위젯 -->
    <div class="col-span-12 lg:col-span-3 hidden lg:block">
      <SidebarWidgets :inline="true" api-url="/api/jobs" detail-path="/jobs/" :current-id="0"
        :label="postType === 'hiring' ? '채용' : '구직'" :filter-params="locationParams" />
        <AdSlot page="jobs" position="right" :maxSlots="2" />
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
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import axios from 'axios'
import AdSlot from '../../components/AdSlot.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const postType = ref('hiring')
const activeCat = ref('')
const showFilter = ref(false)
const jobCategories = [
  { value: '', label: '전체' },
  { value: 'restaurant', label: '🍳 요식업' },
  { value: 'it', label: '💻 IT' },
  { value: 'beauty', label: '💅 미용' },
  { value: 'driving', label: '🚗 운전' },
  { value: 'retail', label: '🛒 판매' },
  { value: 'office', label: '🏢 사무직' },
  { value: 'construction', label: '🔨 건설' },
  { value: 'medical', label: '🏥 의료' },
  { value: 'education', label: '📚 교육' },
  { value: 'etc', label: '📋 기타' },
]
const { city, radius: locRadius, locationQuery, koreanCities, init: initLocation, selectKoreanCity, setRadius } = useLocation()

const items = ref([])
const loading = ref(true)

// URL 쿼리 변경 시 반영 (상세→목록 이동, 카테고리 클릭 등)
watch(() => route.query, (q) => {
  if (route.path !== '/jobs') return
  if (q.category !== undefined) activeCat.value = q.category || ''
  if (q.type === 'seeking') postType.value = 'seeking'
  else if (q.type === 'hiring') postType.value = 'hiring'
  if (q.search !== undefined) search.value = q.search || ''
  loadPage()
})

function goDetail(item) {
  router.push('/jobs/' + item.id)
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
  if (activeCat.value) params.category = activeCat.value
  params.post_type = postType.value

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
    const { data } = await axios.get('/api/jobs', { params })
    items.value = data.data?.data || []
    lastPage.value = data.data?.last_page || 1
  } catch {}
  loading.value = false
}

onMounted(async () => {
  // URL 쿼리 파라미터 반영
  if (route.query.category) activeCat.value = route.query.category
  if (route.query.type === 'seeking') postType.value = 'seeking'
  if (route.query.type === 'hiring') postType.value = 'hiring'
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
<style scoped>
.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
</style>
