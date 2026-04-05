<template>
  <div class="min-h-screen bg-gray-50 pb-16">
    <div class="max-w-[1200px] mx-auto px-4 pt-4">
      <div class="bg-gradient-to-r from-blue-600 to-blue-500 text-white px-6 py-5 rounded-2xl">
        <div class="flex items-center justify-between gap-2">
          <div>
            <h1 class="text-xl font-black">🛒 중고장터</h1>
            <p class="text-blue-100 text-sm mt-0.5 opacity-80">한인 중고 거래 · 물물 교환</p>
          </div>
          <router-link v-if="authStore.isLoggedIn" to="/market/write"
            class="bg-white text-blue-600 px-4 py-2 rounded-lg text-sm font-bold hover:bg-blue-50">+ 물품 등록</router-link>
        </div>
      </div>
    </div>
    <!-- Category tabs -->
    <div class="max-w-[1200px] mx-auto px-4 mt-3">
      <div class="flex gap-2 overflow-x-auto pb-1" style="scrollbar-width:none">
        <button v-for="cat in marketCategories" :key="cat.value"
          @click="category = cat.value; load(1)"
          class="flex-shrink-0 px-4 py-2 rounded-full text-sm font-semibold transition"
          :class="category === cat.value ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:border-blue-300'">
          {{ cat.label }}
        </button>
      </div>
    </div>
    <!-- LocationBar -->
    <div class="max-w-[1200px] mx-auto px-4 mt-2">
      <LocationBar placeholder="중고장터 검색..." @search="onLocationSearch" @location-change="onLocationChange" />
    </div>
    <!-- Content area -->
    <div class="max-w-[1200px] mx-auto px-4 py-4">

    <div v-if="loading" class="text-center py-16 text-gray-400">불러오는 중...</div>
    <div v-else-if="items.length === 0" class="text-center py-16 text-gray-400">등록된 물품이 없습니다.</div>
    <template v-else>
    <!-- Grid View -->
    <div v-if="viewMode === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <router-link v-for="item in items" :key="item.id" :to="`/market/${item.id}`"
        class="bg-white rounded-2xl shadow-sm overflow-hidden hover:shadow-md transition">
        <div class="h-40 bg-gray-100 flex items-center justify-center">
          <img v-if="item.images?.[0]" :src="item.images[0]" class="w-full h-full object-cover" />
          <span v-else class="text-4xl text-gray-300">📦</span>
        </div>
        <div class="p-4">
          <h3 class="font-bold text-gray-800 mb-1 truncate">{{ item.title }}</h3>
          <p class="text-blue-600 font-black">${{ item.price?.toLocaleString() || '가격협의' }}</p>
          <div class="flex items-center justify-between mt-2 text-xs text-gray-400">
            <span>📍 {{ item.region }}</span>
            <span>{{ formatDate(item.created_at) }}</span>
          </div>
        </div>
      </router-link>
    </div>
    <!-- List View (original) -->
    <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
      <router-link v-for="item in items" :key="item.id" :to="`/market/${item.id}`"
        class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition">
        <div class="aspect-square bg-gray-100 flex items-center justify-center">
          <img v-if="item.images?.[0]" :src="item.images[0]" :alt="item.title" class="w-full h-full object-cover" />
          <span v-else class="text-4xl">📦</span>
        </div>
        <div class="p-3">
          <p class="text-gray-800 text-sm font-medium truncate mb-1">{{ item.title }}</p>
          <p class="text-red-600 font-bold text-sm">${{ Number(item.price).toLocaleString() }}
            <span v-if="item.price_negotiable" class="text-xs text-gray-400 font-normal">(협의)</span>
          </p>
          <div class="flex items-center justify-between mt-1">
            <span class="text-xs text-gray-400">{{ item.region }}</span>
            <span class="text-xs text-gray-400">{{ item.condition }}</span>
          </div>
        </div>
      </router-link>
    </div>
    </template>

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
const userLat = ref(null);
const userLng = ref(null);
const userRadius = ref(30);

function getUserLocation() {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      (pos) => { userLat.value = pos.coords.latitude; userLng.value = pos.coords.longitude; load(); },
      () => {},
      { timeout: 5000 }
    );
  }
}
const viewMode = ref('grid');
const items = ref([]);
const loading = ref(true);
const search = ref('');
const category = ref('');
const currentPage = ref(1);
const totalPages = ref(1);

const marketCategories = [
  { value: '', label: '전체' },
  { value: '전자제품', label: '전자제품' },
  { value: '의류/잡화', label: '의류/잡화' },
  { value: '가구/인테리어', label: '가구/인테리어' },
  { value: '식품', label: '식품' },
  { value: '도서', label: '도서' },
  { value: '유아동', label: '유아동' },
  { value: '기타', label: '기타' },
];

async function load(page = 1) {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/market', { params: { page, search: search.value, category: category.value, lat: userLat.value, lng: userLng.value, radius: radius.value } });
    items.value = data.data;
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

onMounted(() => { getUserLocation(); load(); });
</script>
