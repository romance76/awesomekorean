<template>
  <div class="min-h-screen bg-gray-50 pb-16">
    <div class="max-w-[1200px] mx-auto px-4 pt-4">
      <div class="bg-gradient-to-r from-blue-600 to-blue-500 text-white px-6 py-5 rounded-2xl">
        <div class="flex items-center justify-between gap-2">
          <div>
            <h1 class="text-xl font-black">💼 구인구직</h1>
            <p class="text-blue-100 text-sm mt-0.5 opacity-80">한인 채용 정보와 구직 공고</p>
          </div>
          <router-link v-if="authStore.isLoggedIn" to="/jobs/write"
            class="bg-white text-blue-600 px-4 py-2 rounded-lg text-sm font-bold hover:bg-blue-50">+ 공고 등록</router-link>
        </div>
      </div>
    </div>
    <!-- Category tabs -->
    <div class="max-w-[1200px] mx-auto px-4 mt-3">
      <div class="flex gap-2 overflow-x-auto pb-1" style="scrollbar-width:none">
        <button v-for="cat in jobCategories" :key="cat.value"
          @click="jobType = cat.value; load(1)"
          class="flex-shrink-0 px-4 py-2 rounded-full text-sm font-semibold transition"
          :class="jobType === cat.value ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:border-blue-300'">
          {{ cat.label }}
        </button>
      </div>
    </div>
    <!-- LocationBar -->
    <div class="max-w-[1200px] mx-auto px-4 mt-2">
      <LocationBar placeholder="구인구직 검색..." @search="onLocationSearch" @location-change="onLocationChange" />
    </div>
    <!-- Content area -->
    <div class="max-w-[1200px] mx-auto px-4 py-4">

    <div v-if="loading" class="text-center py-16 text-gray-400">불러오는 중...</div>
    <div v-else-if="jobs.length === 0" class="text-center py-16 text-gray-400">등록된 공고가 없습니다.</div>
    <template v-else>
    <!-- Grid View -->
    <div v-if="viewMode === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <router-link v-for="job in jobs" :key="job.id" :to="`/jobs/${job.id}`"
        class="bg-white rounded-2xl shadow-sm overflow-hidden hover:shadow-md transition p-4">
        <div class="flex items-center gap-2 mb-2">
          <span class="bg-blue-100 text-blue-700 text-xs px-2 py-0.5 rounded-full font-bold">{{ job.type === 'offer' ? '구인' : '구직' }}</span>
          <span class="text-xs text-gray-400">{{ job.region }}</span>
        </div>
        <h3 class="font-bold text-gray-800 mb-1 line-clamp-2">{{ job.title }}</h3>
        <p class="text-sm text-gray-500 line-clamp-2 mb-3">{{ job.description }}</p>
        <div class="flex items-center justify-between text-xs text-gray-400">
          <span>{{ job.user?.name }}</span>
          <span>{{ formatDate(job.created_at) }}</span>
        </div>
      </router-link>
    </div>
    <!-- List View -->
    <div v-else class="space-y-3">
      <router-link v-for="job in jobs" :key="job.id" :to="`/jobs/${job.id}`"
        class="block bg-white rounded-xl shadow-sm border border-gray-100 p-4 hover:shadow-md transition">
        <div class="flex items-start justify-between">
          <div class="flex-1 min-w-0">
            <div class="flex items-center space-x-2 mb-1">
              <span v-if="job.is_pinned" class="text-xs bg-red-100 text-red-600 px-1.5 py-0.5 rounded font-medium">상단</span>
              <span class="font-semibold text-gray-800 truncate">{{ job.title }}</span>
            </div>
            <div class="flex items-center flex-wrap gap-x-2 gap-y-1 text-xs text-gray-500">
              <span>🏢 {{ job.company_name || '비공개' }}</span>
              <span v-if="job.region">📍 {{ job.region }}</span>
              <span v-if="job.job_type" class="bg-blue-50 text-blue-600 px-2 py-0.5 rounded">{{ job.job_type }}</span>
            </div>
            <p v-if="job.salary_range" class="text-sm text-green-600 font-medium mt-1">💰 {{ job.salary_range }}</p>
          </div>
          <div class="text-xs text-gray-400 flex-shrink-0 ml-3">{{ formatDate(job.created_at) }}</div>
        </div>
      </router-link>
    </div>
    </template>

    <!-- 페이지네이션 -->
    <div v-if="totalPages > 1" class="flex justify-center space-x-1 mt-5">
      <button v-for="p in totalPages" :key="p" @click="load(p)"
        :class="['px-3 py-1.5 rounded text-sm', p === currentPage ? 'bg-red-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-300']">
        {{ p }}
      </button>
    </div>
    </div><!-- /max-w-[1200px] -->
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useAuthStore } from '../../stores/auth';

import axios from 'axios';
import LocationBar from '../../components/location/LocationBar.vue';

const authStore = useAuthStore();
const radius = ref(30);
const jobs = ref([]);
const viewMode = ref('grid');

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
const region = ref('');
const jobType = ref('');
const currentPage = ref(1);
const totalPages = ref(1);

const jobCategories = [
  { value: '', label: '전체' },
  { value: 'offer', label: '구인' },
  { value: 'seek', label: '구직' },
  { value: 'part-time', label: '아르바이트' },
  { value: 'full-time', label: '정규직' },
];

async function load(page = 1) {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/jobs', { params: { page, search: search.value, region: region.value, type: jobType.value } });
    jobs.value = data.data;
    currentPage.value = data.current_page;
    totalPages.value = data.last_page;
  } catch {}
  loading.value = false;
}

function formatDate(d) {
  return new Date(d).toLocaleDateString('ko-KR');
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

onMounted(() => load());
</script>
