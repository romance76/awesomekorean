<template>
  <div class="min-h-screen bg-gray-50 pb-16">
    <!-- Header -->
    <div class="max-w-[1200px] mx-auto px-4 pt-4">
      <div class="bg-gradient-to-r from-blue-600 to-blue-500 text-white px-6 py-5 rounded-2xl">
        <div class="flex items-center justify-between gap-2">
          <div>
            <h1 class="text-xl font-black">🏠 부동산</h1>
            <p class="text-blue-100 text-sm mt-0.5 opacity-80">한인 부동산 매물 정보</p>
          </div>
          <router-link v-if="authStore.isLoggedIn" to="/realestate/write"
            class="bg-white text-blue-600 px-4 py-2 rounded-lg text-sm font-bold hover:bg-blue-50">+ 매물 등록</router-link>
        </div>
      </div>
    </div>

    <!-- Category tabs -->
    <div class="max-w-[1200px] mx-auto px-4 mt-3">
      <div class="flex gap-2 overflow-x-auto pb-1" style="scrollbar-width:none">
        <button v-for="cat in categories" :key="cat.value"
          @click="category = cat.value; load(1)"
          class="flex-shrink-0 px-4 py-2 rounded-full text-sm font-semibold transition"
          :class="category === cat.value ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:border-blue-300'">
          {{ cat.label }}
        </button>
      </div>
    </div>

    <!-- LocationBar -->
    <div class="max-w-[1200px] mx-auto px-4 mt-2">
      <LocationBar placeholder="부동산 검색..." @search="onLocationSearch" @location-change="onLocationChange" />
    </div>

    <!-- Content -->
    <div class="max-w-[1200px] mx-auto px-4 py-4">
      <div v-if="loading" class="text-center py-16 text-gray-400">불러오는 중...</div>
      <div v-else-if="items.length === 0" class="text-center py-16 text-gray-400">등록된 매물이 없습니다.</div>
      <template v-else>
        <!-- Grid View -->
        <div v-if="viewMode === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <router-link v-for="item in items" :key="item.id" :to="`/realestate/${item.id}`"
            class="bg-white rounded-2xl shadow-sm overflow-hidden hover:shadow-md transition group">
            <div class="h-48 bg-gray-100 flex items-center justify-center relative overflow-hidden">
              <img v-if="item.photos?.[0]" :src="item.photos[0]" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" />
              <span v-else class="text-5xl text-gray-300">🏠</span>
              <span class="absolute top-2 left-2 text-xs font-bold px-2 py-1 rounded-full"
                :class="item.type === '매매' ? 'bg-red-500 text-white' : item.type === '렌트' ? 'bg-blue-500 text-white' : item.type === '룸메이트' ? 'bg-green-500 text-white' : 'bg-orange-500 text-white'">
                {{ item.type }}
              </span>
            </div>
            <div class="p-4">
              <h3 class="font-bold text-gray-800 mb-1 truncate">{{ item.title }}</h3>
              <p class="text-blue-600 font-black text-lg">{{ item.price ? '$' + Number(item.price).toLocaleString() : '가격문의' }}
                <span v-if="item.type === '렌트' && item.price" class="text-sm font-medium text-gray-400">/월</span>
              </p>
              <p class="text-xs text-gray-500 mt-1 truncate">📍 {{ item.address }}</p>
              <div class="flex items-center gap-3 mt-2 text-xs text-gray-400">
                <span v-if="item.bedrooms">🛏️ {{ item.bedrooms }}방</span>
                <span v-if="item.bathrooms">🚿 {{ item.bathrooms }}욕실</span>
                <span v-if="item.sqft">📐 {{ Number(item.sqft).toLocaleString() }}sqft</span>
              </div>
              <div class="flex items-center justify-between mt-2 text-xs text-gray-400">
                <span>{{ item.region }}</span>
                <span>{{ formatDate(item.created_at) }}</span>
              </div>
            </div>
          </router-link>
        </div>

        <!-- List View -->
        <div v-else class="space-y-3">
          <router-link v-for="item in items" :key="item.id" :to="`/realestate/${item.id}`"
            class="bg-white rounded-2xl shadow-sm overflow-hidden hover:shadow-md transition flex group">
            <div class="w-28 sm:w-40 md:w-56 flex-shrink-0 bg-gray-100 flex items-center justify-center relative overflow-hidden">
              <img v-if="item.photos?.[0]" :src="item.photos[0]" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" />
              <span v-else class="text-4xl text-gray-300">🏠</span>
              <span class="absolute top-2 left-2 text-xs font-bold px-2 py-1 rounded-full"
                :class="item.type === '매매' ? 'bg-red-500 text-white' : item.type === '렌트' ? 'bg-blue-500 text-white' : item.type === '룸메이트' ? 'bg-green-500 text-white' : 'bg-orange-500 text-white'">
                {{ item.type }}
              </span>
            </div>
            <div class="flex-1 p-4 min-w-0">
              <h3 class="font-bold text-gray-800 mb-1 truncate">{{ item.title }}</h3>
              <p class="text-blue-600 font-black text-lg">{{ item.price ? '$' + Number(item.price).toLocaleString() : '가격문의' }}
                <span v-if="item.type === '렌트' && item.price" class="text-sm font-medium text-gray-400">/월</span>
              </p>
              <p class="text-xs text-gray-500 mt-1 truncate">📍 {{ item.address }}</p>
              <div class="flex items-center gap-3 mt-2 text-xs text-gray-400">
                <span v-if="item.bedrooms">🛏️ {{ item.bedrooms }}방</span>
                <span v-if="item.bathrooms">🚿 {{ item.bathrooms }}욕실</span>
                <span v-if="item.sqft">📐 {{ Number(item.sqft).toLocaleString() }}sqft</span>
              </div>
              <div class="flex items-center gap-3 mt-2 text-xs text-gray-400">
                <span>{{ item.region }}</span>
                <span>{{ formatDate(item.created_at) }}</span>
              </div>
            </div>
          </router-link>
        </div>
      </template>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="flex justify-center space-x-1 mt-5">
        <button v-for="p in totalPages" :key="p" @click="load(p)"
          class="w-8 h-8 rounded-lg text-sm font-bold transition"
          :class="page === p ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-100'">
          {{ p }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'
import LocationBar from '../../components/location/LocationBar.vue'

const authStore = useAuthStore()

const categories = [
  { value: '', label: '전체' },
  { value: '렌트', label: '렌트' },
  { value: '매매', label: '매매' },
  { value: '룸메이트', label: '룸메이트' },
  { value: '상가', label: '상가' },
]

const regions = [
  'Los Angeles', 'New York', 'Atlanta', 'Chicago', 'Seattle',
  'Dallas', 'Houston', 'San Francisco', 'Boston', 'Miami',
  'Washington DC', 'Las Vegas', 'Phoenix', 'San Diego', 'Denver',
  'Portland', 'Nashville', 'Austin', 'New Jersey', 'Virginia',
]

const category = ref('')
const search = ref('')
const region = ref('')
const radius = ref(30)
const viewMode = ref('grid')

const userLat = ref(null);
const userLng = ref(null);
const userRadius = ref(30);

function getUserLocation() {
  const saved = localStorage.getItem('sk_user_location');
  if (saved) {
    const loc = JSON.parse(saved);
    userLat.value = loc.lat;
    userLng.value = loc.lng;
    return;
  }
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        userLat.value = pos.coords.latitude;
        userLng.value = pos.coords.longitude;
        localStorage.setItem('sk_user_location', JSON.stringify({lat: pos.coords.latitude, lng: pos.coords.longitude}));
      },
      () => {},
      { timeout: 5000 }
    );
  }
}
const loading = ref(false)
const items = ref([])
const page = ref(1)
const totalPages = ref(1)

// Mock data
const mockListings = [
  { id: 1, title: 'LA 한인타운 1BR 아파트', type: '렌트', price: 2200, address: '3456 Wilshire Blvd, Los Angeles, CA 90010', bedrooms: 2, bathrooms: 1, sqft: 950, region: 'Los Angeles', photos: [], created_at: '2026-03-20', deposit: 2200, pet_policy: '협의', description: '에이버 지역 깨끗한 아파트, 주차 포함' },
  { id: 2, title: '풀러턴 타운하우스 매매', type: '매매', price: 685000, address: '125 Peachtree St NE, Atlanta, GA 30303', bedrooms: 3, bathrooms: 2, sqft: 1800, region: 'Atlanta', photos: [], created_at: '2026-03-18', deposit: 0, pet_policy: '가능', description: '풀러턴 지역 타운하우스, 복층 구조' },
  { id: 3, title: '플러싱 룸메이트 구합니다', type: '룸메이트', price: 900, address: '456 Main St, Flushing, NY 11355', bedrooms: 1, bathrooms: 1, sqft: 0, region: 'New York', photos: [], created_at: '2026-03-22', deposit: 900, pet_policy: '불가', description: '플러싱 한인타운 바로 앞, 마스터룸 사용' },
  { id: 4, title: 'LA 한인타운 상가 임대', type: '상가', price: 4500, address: '621 S Western Ave, Los Angeles, CA 90005', bedrooms: 0, bathrooms: 1, sqft: 1200, region: 'Los Angeles', photos: [], created_at: '2026-03-15', deposit: 9000, pet_policy: '불가', description: '한인타운 메인 거리 상가, 주차 포함' },
  { id: 5, title: '뉴저지 포트리 1BR 렌트', type: '렌트', price: 1800, address: '300 Summit Ave, Fort Lee, NJ 07024', bedrooms: 1, bathrooms: 1, sqft: 750, region: 'New Jersey', photos: [], created_at: '2026-03-21', deposit: 1800, pet_policy: '불가', description: 'GW 브릿지 앞 깨끗한 아파트' },
  { id: 6, title: '애틀랜타 풀옵션 스튜디오', type: '렌트', price: 1500, address: '3200 Buford Hwy, Duluth, GA 30096', bedrooms: 0, bathrooms: 1, sqft: 450, region: 'Atlanta', photos: [], created_at: '2026-03-25', deposit: 1500, pet_policy: '불가', description: '뷰포드하이웨이 한인타운 인접, 가구 포함' },
  { id: 7, title: '뉴욕 퀸즈 2BR 콘도', type: '매매', price: 550000, address: '1000 1st Ave, Queens, NY 11101', bedrooms: 2, bathrooms: 1, sqft: 1050, region: 'New York', photos: [], created_at: '2026-03-16', deposit: 0, pet_policy: '협의', description: '퀸즈 지역 전망 좋은 콘도' },
  { id: 8, title: '달라스 한인 마트 근처 3BR', type: '렌트', price: 1600, address: '2310 Royal Ln, Dallas, TX 75229', bedrooms: 3, bathrooms: 2, sqft: 1400, region: 'Dallas', photos: [], created_at: '2026-03-17', deposit: 1600, pet_policy: '가능', description: '한인 마트 근처 넓은 집' },
  { id: 9, title: '시애틀 다운타운 콘도 매매', type: '매매', price: 520000, address: '1000 1st Ave, Seattle, WA 98104', bedrooms: 2, bathrooms: 1, sqft: 1050, region: 'Seattle', photos: [], created_at: '2026-03-16', deposit: 0, pet_policy: '협의', description: '다운타운 시애틀 조망 좋은 콘도' },
  { id: 10, title: '시카고 링컨빌 룸메이트', type: '룸메이트', price: 750, address: '4500 N Lincoln Ave, Chicago, IL 60625', bedrooms: 1, bathrooms: 1, sqft: 0, region: 'Chicago', photos: [], created_at: '2026-03-24', deposit: 750, pet_policy: '불가', description: '링컨빌 한인타운 근처, 각방 사용' },
  { id: 11, title: '비버리힐즈 상가 임대 (식당용)', type: '상가', price: 5500, address: '9450 S Western Ave, Los Angeles, CA 90047', bedrooms: 0, bathrooms: 2, sqft: 2000, region: 'Los Angeles', photos: [], created_at: '2026-03-14', deposit: 11000, pet_policy: '불가', description: '식당 운영 가능, 주방 설비 포함' },
  { id: 12, title: '보스턴 케임브리지 2BR 렌트', type: '렌트', price: 2500, address: '100 Cambridge St, Cambridge, MA 02141', bedrooms: 2, bathrooms: 1, sqft: 900, region: 'Boston', photos: [], created_at: '2026-03-20', deposit: 2500, pet_policy: '협의', description: 'MIT 근처 학생 및 직장인에게 좋은 위치' },
  { id: 13, title: '휴스턴 스프링브랜치 4BR 매매', type: '매매', price: 380000, address: '15700 Woodforest Blvd, Houston, TX 77049', bedrooms: 4, bathrooms: 3, sqft: 2800, region: 'Houston', photos: [], created_at: '2026-03-13', deposit: 0, pet_policy: '가능', description: '한인 마트 및 학교 근처, 넓은 마당' },
  { id: 14, title: '버지니아 타운하우스 렌트', type: '렌트', price: 2100, address: '7000 Columbia Pike, Annandale, VA 22003', bedrooms: 3, bathrooms: 2, sqft: 1600, region: 'Virginia', photos: [], created_at: '2026-03-22', deposit: 2100, pet_policy: '가능', description: '애난데일 한인타운 바로 앞' },
  { id: 15, title: '마이애미 비치 콘도 매매', type: '매매', price: 520000, address: '1500 Collins Ave, Miami Beach, FL 33139', bedrooms: 2, bathrooms: 2, sqft: 1100, region: 'Miami', photos: [], created_at: '2026-03-11', deposit: 0, pet_policy: '협의', description: '오션뷰 콘도, 풀 및 헬스장 이용 가능' },
  { id: 16, title: '맨해튼 스튜디오 렌트', type: '렌트', price: 2800, address: '350 W 42nd St, New York, NY 10036', bedrooms: 0, bathrooms: 1, sqft: 500, region: 'New York', photos: [], created_at: '2026-03-23', deposit: 5600, pet_policy: '불가', description: '타임스스퀘어 인접, 새 리모델링' },
]

function load(p = 1) {
  loading.value = true
  page.value = p

  setTimeout(() => {
    let filtered = [...mockListings]
    if (category.value) filtered = filtered.filter(i => i.type === category.value)
    if (search.value) {
      const q = search.value.toLowerCase()
      filtered = filtered.filter(i =>
        i.title.toLowerCase().includes(q) ||
        i.address.toLowerCase().includes(q) ||
        i.region.toLowerCase().includes(q)
      )
    }
    if (region.value) filtered = filtered.filter(i => i.region === region.value)

    items.value = filtered
    totalPages.value = Math.max(1, Math.ceil(filtered.length / 12))
    loading.value = false
  }, 300)
}

function formatDate(d) {
  return new Date(d).toLocaleDateString('ko-KR', { month: 'short', day: 'numeric' })
}


function onLocationSearch({ keyword, city, radius }) {
  search.value = keyword
  if (city?.lat) { userLat.value = city.lat; userLng.value = city.lng }
  if (radius !== '전국') userRadius.value = parseInt(radius)
  load()
}
function onLocationChange({ city, radius }) {
  if (city?.lat) { userLat.value = city.lat; userLng.value = city.lng }
  userRadius.value = radius === '전국' ? 0 : parseInt(radius)
  load()
}

onMounted(() => load())
</script>
