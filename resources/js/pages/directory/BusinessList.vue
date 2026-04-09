<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <!-- 헤더: 제목 + 위치필터 + 등록 (구인 스타일 통일) -->
    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
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
        <RouterLink v-if="auth.isLoggedIn" to="/directory/register" class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-lg text-sm hover:bg-amber-500">✏️ 등록</RouterLink>
      </div>
    </div>

    <div class="grid grid-cols-12 gap-4">
    <!-- 왼쪽: 카테고리 -->
    <div class="col-span-12 lg:col-span-2 hidden lg:block">
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-20">
        <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">📋 업종</div>
        <button v-for="c in bizCategories" :key="c.value" @click="activeCat=c.value; loadPage()"
          class="w-full text-left px-3 py-2 text-xs transition"
          :class="activeCat===c.value ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">{{ c.label }}</button>
      </div>
    </div>
    <div class="col-span-12 lg:col-span-7">

    <!-- 목록 -->
    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="!items.length" class="text-center py-12">
      <div class="text-4xl mb-3">🏪</div>
      <div class="text-gray-500 font-semibold">검색 결과가 없습니다</div>
      <div class="text-xs text-gray-400 mt-1">다른 도시를 선택하거나 '전국'으로 검색해보세요</div>
    </div>
    <!-- 상세 모드 -->
    <div v-if="activeItem">
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <!-- 사진 갤러리 (클릭 확대) -->
        <div v-if="activeItem.images?.length" class="flex gap-1 overflow-x-auto p-2 bg-gray-50">
          <img v-for="(img, i) in activeItem.images" :key="i" :src="img" @click="lightboxImg=img" class="h-32 rounded-lg object-cover flex-shrink-0 cursor-pointer hover:opacity-80 transition" @error="e=>e.target.style.display='none'" />
        </div>
        <div class="px-5 py-4">
          <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-semibold">{{ activeItem.subcategory || activeItem.category }}</span>
          <h2 class="text-lg font-bold text-gray-900 mt-2">🏪 {{ activeItem.name }}</h2>
          <div class="flex items-center gap-1 mt-1"><span class="text-amber-400">{{'★'.repeat(Math.round(activeItem.rating))}}</span><span class="text-sm text-gray-600">{{ activeItem.rating }}</span><span class="text-xs text-gray-400">({{ activeItem.review_count }}리뷰)</span></div>
        </div>
        <div class="px-5 py-3 border-t text-sm text-gray-600 space-y-1">
          <div v-if="activeItem.phone">📱 <a :href="'tel:'+activeItem.phone" class="text-amber-600 hover:underline">{{ activeItem.phone }}</a></div>
          <div v-if="activeItem.address">📍 {{ activeItem.address }}</div>
          <div v-if="activeItem.website">🌐 <a :href="activeItem.website" target="_blank" class="text-amber-600 hover:underline truncate block">{{ activeItem.website }}</a></div>
        </div>
        <!-- 지도 -->
        <div v-if="activeItem.lat && activeItem.lng" class="px-5 py-3 border-t">
          <div class="rounded-lg overflow-hidden border">
            <iframe :src="`https://www.openstreetmap.org/export/embed.html?bbox=${Number(activeItem.lng)-0.008},${Number(activeItem.lat)-0.004},${Number(activeItem.lng)+0.008},${Number(activeItem.lat)+0.004}&layer=mapnik&marker=${activeItem.lat},${activeItem.lng}`"
              class="w-full h-40 border-0" loading="lazy"></iframe>
          </div>
          <a :href="`https://www.google.com/maps/search/?api=1&query=${activeItem.lat},${activeItem.lng}`"
            target="_blank" class="block bg-gray-50 px-3 py-1.5 text-xs text-amber-600 font-bold text-center rounded-b-lg border border-t-0 hover:bg-amber-50 transition">
            📍 Google Maps에서 보기 →
          </a>
        </div>
        <div v-if="activeItem.description" class="px-5 py-4 border-t text-sm text-gray-700 whitespace-pre-wrap">{{ activeItem.description }}</div>
        <!-- 영업시간 -->
        <div v-if="activeItem.hours && Object.keys(activeItem.hours).length" class="px-5 py-3 border-t">
          <div class="text-xs font-bold text-gray-700 mb-1">🕐 영업시간</div>
          <div v-for="(time, day) in activeItem.hours" :key="day" class="flex justify-between text-xs text-gray-500 py-0.5">
            <span>{{ day }}</span><span>{{ time }}</span>
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
    <div v-else-if="!items.length" class="text-center py-12 text-gray-400">검색 결과 없음</div>
    <!-- 카드형 -->
    <div v-else-if="viewMode==='card'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <div v-for="item in items" :key="item.id" @click="openItem(item)"
        class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 hover:shadow-md hover:-translate-y-0.5 transition-all cursor-pointer">
        <div class="flex items-center gap-3 mb-3">
          <img v-if="item.images?.length" :src="item.images[0]" class="w-14 h-14 rounded-xl object-cover flex-shrink-0" @error="e=>{e.target.style.display='none'; e.target.nextElementSibling && (e.target.nextElementSibling.style.display='flex')}" />
          <div class="w-14 h-14 bg-amber-100 rounded-xl flex items-center justify-center text-2xl flex-shrink-0" :style="item.images?.length ? 'display:none' : ''">🏪</div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-bold text-gray-800 truncate">{{ item.name }}</div>
            <div class="text-[10px] text-gray-400">{{ item.subcategory || item.category }}</div>
          </div>
          <div v-if="item.rating" class="text-amber-400 text-xs">{{ '★'.repeat(Math.round(item.rating)) }} {{ item.rating }}</div>
        </div>
        <div class="text-xs text-gray-500 line-clamp-2 mb-2">{{ (item.description || '').slice(0, 80) }}</div>
        <div class="flex items-center justify-between text-[10px] text-gray-400">
          <span>📍 {{ item.city }}, {{ item.state }}</span>
          <div class="flex gap-2">
            <span v-if="item.phone">📱 {{ item.phone }}</span>
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
    </div>

    <div v-if="lastPage > 1" class="flex justify-center gap-1.5 mt-4">
      <button v-for="pg in Math.min(lastPage, 10)" :key="pg" @click="loadPage(pg)"
        class="px-3 py-1 rounded text-sm" :class="pg===page?'bg-amber-400 text-amber-900 font-bold':'bg-white text-gray-600 border hover:bg-amber-50'">{{ pg }}</button>
    </div>
    </div>
    <!-- 오른쪽 위젯 -->
    <div class="col-span-12 lg:col-span-3 hidden lg:block">
      <SidebarWidgets :inline="true" @select="openItem" api-url="/api/businesses" detail-path="/directory/" :current-id="0"
        label="업소" :filter-params="locationParams" />
    </div>
    </div>
  </div>
  <!-- 사진 라이트박스 -->
  <div v-if="lightboxImg" class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4" @click="lightboxImg=null">
    <button @click="lightboxImg=null" class="absolute top-4 right-4 text-white text-3xl hover:text-gray-300">✕</button>
    <img :src="lightboxImg" class="max-w-full max-h-[90vh] rounded-lg shadow-2xl" @click.stop />
  </div>
</div>
</template>
<script setup>
import { useRoute } from 'vue-router'
import { ref, computed, onMounted } from 'vue'
import { useLocation } from '../../composables/useLocation'
import { useAuthStore } from '../../stores/auth'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import { useMenuConfig } from '../../composables/useMenuConfig'
import axios from 'axios'

const auth = useAuthStore()
const route = useRoute()
const { city, radius: locRadius, locationQuery, koreanCities, init: initLocation, selectKoreanCity, setRadius } = useLocation()
const activeCat = ref('')
const { loadConfig, getDefaultView } = useMenuConfig()
const viewMode = ref('list')
const activeItem = ref(null)
const activeReviews = ref([])
const lightboxImg = ref(null)
const googleKey = import.meta.env.VITE_GOOGLE_MAPS_KEY || 'AIzaSyAeG46feoDm6HJbre4_FODaxyhz9SBBsAE'
const reviewRating = ref(0)
const reviewText = ref('')

async function openItem(item) {
  try { const { data } = await axios.get(`/api/businesses/${item.id}`); activeItem.value = data.data }
  catch { activeItem.value = item }
  if (activeItem.value?.category) activeCat.value = activeItem.value.category
  // 리뷰 로드 (구글리뷰 + 사이트리뷰 합치기)
  activeReviews.value = []
  reviewRating.value = 0; reviewText.value = ''
  try {
    const { data } = await axios.get(`/api/businesses/${activeItem.value.id}/reviews`)
    const siteReviews = data.data?.data || data.data || []
    const googleReviews = (activeItem.value.google_reviews || []).map((r, i) => ({ id: 'g'+i, author: r.author, rating: r.rating, text: r.text, relative_time: r.time }))
    activeReviews.value = [...siteReviews, ...googleReviews]
  } catch {}
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

function formatDate(dt) { return dt ? new Date(dt).toLocaleDateString('ko-KR') : '' }
const bizCategories = [
  { value: '', label: '전체' },{ value: 'restaurant', label: '🍽️ 음식점' },{ value: 'grocery', label: '🛒 마트' },
  { value: 'beauty', label: '💅 미용' },{ value: 'medical', label: '🏥 의료' },{ value: 'professional', label: '💼 전문서비스' },
  { value: 'auto', label: '🚗 자동차' },{ value: 'realestate', label: '🏠 부동산' },{ value: 'education', label: '📚 교육' },{ value: 'etc', label: '📋 기타' },
]

const items = ref([])
const loading = ref(true)
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
    const { data } = await axios.get('/api/businesses', { params })
    items.value = data.data?.data || []
    lastPage.value = data.data?.last_page || 1
  } catch {}
  loading.value = false
}

onMounted(async () => {
  await loadConfig(); viewMode.value = getDefaultView('directory')
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