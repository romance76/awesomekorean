<template>
  <div class="min-h-screen bg-gray-50 pb-16">

    <!-- Location Bar -->
    <LocationBar placeholder="업소록 검색..." @search="onLocationSearch" @location-change="onLocationChange" />

    <div class="max-w-[1200px] mx-auto px-4 pt-4">
      <div class="bg-gradient-to-r from-blue-600 to-blue-500 text-white px-6 py-6 rounded-2xl">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-xl font-black">📋 한인 업소록</h1>
            <p class="text-blue-100 text-sm mt-0.5">한인 비즈니스 정보 디렉토리</p>
          </div>
          <router-link v-if="authStore.isLoggedIn" to="/directory/register"
            class="bg-white text-blue-600 px-4 py-2 rounded-lg text-sm font-bold hover:bg-blue-50">+ 업소 등록</router-link>
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
    <!-- State filter buttons -->
    <div class="max-w-[1200px] mx-auto px-4 mt-2">
      <div class="flex gap-1.5 overflow-x-auto pb-1 flex-wrap" style="scrollbar-width:none">
        <button v-for="st in stateButtons" :key="st.code" @click="selectState(st)"
          :class="['flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold border transition',
            selectedState===st.code
              ? 'bg-blue-600 text-white border-blue-600'
              : 'bg-white text-gray-600 border-gray-200 hover:border-blue-400 hover:text-blue-600']">
          {{ st.label }}
        </button>
      </div>
    </div>

    <!-- Content area -->
    <div class="max-w-[1200px] mx-auto px-4 py-4">

    <div v-if="loading" class="text-center py-16 text-gray-400">불러오는 중...</div>
    <div v-else-if="businesses.length === 0" class="text-center py-16 text-gray-400">등록된 업소가 없습니다.</div>

    <!-- Grid View -->
    <div v-else-if="viewMode === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <router-link v-for="biz in businesses" :key="biz.id" :to="`/directory/${biz.id}`" class="block">
        <div class="bg-white rounded-2xl shadow-sm overflow-hidden hover:shadow-md transition">
          <div class="h-24 bg-gradient-to-r from-blue-400 to-blue-500"></div>
          <div class="p-4">
            <div class="flex items-center justify-between mb-1">
              <span v-if="biz.is_sponsored" class="bg-yellow-100 text-yellow-700 text-xs px-2 py-0.5 rounded-full font-bold">프리미엄</span>
              <span v-else></span>
              <span class="text-yellow-500 text-sm font-bold">⭐ {{ Number(biz.rating_avg).toFixed(1) }}</span>
            </div>
            <h3 class="font-bold text-gray-800">{{ biz.name }}</h3>
            <p class="text-xs text-gray-500">{{ biz.category }} · 📍 {{ biz.region }}</p>
            <p v-if="biz.description" class="text-sm text-gray-600 mt-2 line-clamp-2">{{ biz.description }}</p>
          </div>
        </div>
      </router-link>
    </div>

    <!-- List View -->
    <div v-else class="space-y-3">
      <router-link v-for="biz in businesses" :key="biz.id" :to="`/directory/${biz.id}`"
        class="block bg-white rounded-xl shadow-sm border border-gray-100 p-4 hover:shadow-md transition">
        <div class="flex items-start justify-between">
          <div class="flex-1 min-w-0">
            <div class="flex items-center space-x-2 mb-1">
              <span v-if="biz.is_sponsored" class="text-xs bg-yellow-100 text-yellow-700 px-1.5 py-0.5 rounded font-medium">프리미엄</span>
              <h3 class="font-semibold text-gray-800 truncate">{{ biz.name }}</h3>
            </div>
            <div class="flex items-center space-x-3 text-xs text-gray-500 mb-1">
              <span class="bg-gray-100 px-2 py-0.5 rounded">{{ biz.category }}</span>
              <span v-if="biz.region">📍 {{ biz.region }}</span>
            </div>
            <p v-if="biz.description" class="text-gray-500 text-xs truncate">{{ biz.description }}</p>
          </div>
          <div class="flex flex-col items-end ml-3 flex-shrink-0">
            <div class="flex items-center space-x-1 text-sm">
              <span class="text-yellow-400">★</span>
              <span class="font-medium text-gray-700">{{ Number(biz.rating_avg).toFixed(1) }}</span>
              <span class="text-xs text-gray-400">({{ biz.review_count }})</span>
            </div>
          </div>
        </div>
      </router-link>
    </div>

    <div v-if="totalPages > 1" class="flex justify-center items-center gap-1 mt-6 flex-wrap">
      <!-- Prev -->
      <button @click="load(currentPage-1)" :disabled="currentPage===1"
        class="px-3 py-1.5 rounded-lg text-sm border bg-white text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
        ‹ 이전
      </button>
      <!-- First page -->
      <button v-if="pageList[0] > 1" @click="load(1)"
        class="px-3 py-1.5 rounded-lg text-sm border bg-white text-gray-700 hover:bg-blue-50">1</button>
      <span v-if="pageList[0] > 2" class="px-1 text-gray-400">…</span>
      <!-- Page window -->
      <button v-for="p in pageList" :key="p" @click="load(p)"
        :class="['px-3 py-1.5 rounded-lg text-sm border transition', p === currentPage
          ? 'bg-blue-600 text-white border-blue-600 font-semibold'
          : 'bg-white text-gray-700 hover:bg-blue-50']">
        {{ p }}
      </button>
      <!-- Last page -->
      <span v-if="pageList[pageList.length-1] < totalPages-1" class="px-1 text-gray-400">…</span>
      <button v-if="pageList[pageList.length-1] < totalPages" @click="load(totalPages)"
        class="px-3 py-1.5 rounded-lg text-sm border bg-white text-gray-700 hover:bg-blue-50">{{ totalPages }}</button>
      <!-- Next -->
      <button @click="load(currentPage+1)" :disabled="currentPage===totalPages"
        class="px-3 py-1.5 rounded-lg text-sm border bg-white text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
        다음 ›
      </button>
      <span class="ml-2 text-xs text-gray-400">{{ currentPage }}/{{ totalPages }}페이지 · 총 {{ totalCount }}개</span>
    </div>
    </div><!-- /max-w-[1200px] -->
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAuthStore } from '../../stores/auth';
import axios from 'axios';
import LocationBar from '../../components/location/LocationBar.vue'

const authStore = useAuthStore();
const radius = ref(30);
const viewMode = ref('grid');
const businesses = ref([]);

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
const loading = ref(true);
const search = ref('');
const category = ref('');
const region = ref('');
const currentPage = ref(1);
const totalPages = ref(1);
const totalCount = ref(0);
const selectedState = ref('');

const stateButtons = [
  { code: '', label: '전체' },
  { code: 'CA', label: '🌴 CA', cities: ['Los Angeles','San Francisco','San Diego'] },
  { code: 'NY', label: '🗽 NY', cities: ['New York','Flushing'] },
  { code: 'TX', label: '🤠 TX', cities: ['Houston','Dallas'] },
  { code: 'WA', label: '🌲 WA', cities: ['Seattle'] },
  { code: 'IL', label: '🏙️ IL', cities: ['Chicago'] },
  { code: 'GA', label: '🍑 GA', cities: ['Atlanta'] },
  { code: 'DC', label: '🏛️ DC', cities: ['Washington'] },
  { code: 'NV', label: '🎰 NV', cities: ['Las Vegas'] },
  { code: 'FL', label: '🌊 FL', cities: ['Miami'] },
  { code: 'MA', label: '🦞 MA', cities: ['Boston'] },
  { code: 'HI', label: '🌺 HI', cities: ['Honolulu'] },
  { code: 'CO', label: '⛰️ CO', cities: ['Denver'] },
  { code: 'NJ', label: '🏘️ NJ', cities: ['Fort Lee'] },
  { code: 'VA', label: '🌿 VA', cities: ['Annandale'] },
  { code: 'OR', label: '🌧️ OR', cities: ['Portland'] },
];

function selectState(st) {
  selectedState.value = st.code;
  if (st.cities && st.cities.length === 1) {
    region.value = st.cities[0];
  } else if (st.code === '') {
    region.value = '';
  } else {
    // Multiple cities - clear region to show all in state
    region.value = '';
  }
  load(1);
}

const categories = [
  { value: '', label: '전체' },
  { value: '한식당', label: '🍽️ 식당' },
  { value: '미용실', label: '💇 미용' },
  { value: '의원/한의원', label: '🏥 의료' },
  { value: '변호사', label: '⚖️ 법률' },
  { value: '부동산', label: '🏠 부동산' },
  { value: '한국마트', label: '🛒 마트' },
  { value: '한국BBQ', label: '🥩 BBQ' },
  { value: '스파/네일', label: '💅 스파' },
  { value: '교회', label: '⛪ 교회' },
  { value: '한국학교', label: '📚 교육' },
];

const regions = [
  '', 'Los Angeles', 'New York', 'Chicago', 'Houston', 'Seattle',
  'Atlanta', 'Dallas', 'San Francisco', 'Washington', 'Las Vegas',
  'Boston', 'Philadelphia', 'Miami', 'San Diego', 'Denver',
  'Annandale', 'Fort Lee', 'Flushing', 'Honolulu', 'Portland',
  'Minneapolis', 'Detroit', 'Phoenix', 'Baltimore',
];

// Smart pagination: show window of 5 pages around current
const pageList = computed(() => {
  const total = totalPages.value;
  const cur = currentPage.value;
  if (total <= 11) return Array.from({ length: total }, (_, i) => i + 1);
  const delta = 4;
  const start = Math.max(2, cur - delta);
  const end = Math.min(total - 1, cur + delta);
  const pages = [];
  for (let i = start; i <= end; i++) pages.push(i);
  return pages;
});

async function load(page = 1) {
  if (page < 1 || page > totalPages.value) return;
  loading.value = true;
  currentPage.value = page;
  try {
    const params = {
      page,
      search: search.value || undefined,
      category: category.value || undefined,
      region: region.value || undefined,
      state: selectedState.value || undefined,
      per_page: 24,
    };
    const { data } = await axios.get('/api/businesses', { params });
    businesses.value = data.data || [];
    currentPage.value = data.current_page || page;
    totalPages.value = data.last_page || 1;
    totalCount.value = data.total || businesses.value.length;
  } catch(e) { console.error(e) }
  loading.value = false;
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

onMounted(() => load());

// LocationBar handlers
function onLocationSearch(keyword) {
  search.value = keyword
  load()
}

function onLocationChange(location) {
  console.log('Location changed:', location)
  load()
}

</script>
