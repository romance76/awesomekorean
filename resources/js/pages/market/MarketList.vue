<template>
  <div class="min-h-screen bg-gray-50 pb-16">
    <div class="max-w-[1200px] mx-auto px-4 pt-4">
      <div class="bg-gradient-to-r from-blue-600 to-blue-500 text-white px-6 py-6 rounded-2xl">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-xl font-black">🛒 중고장터</h1>
            <p class="text-blue-100 text-sm mt-0.5">한인 중고 거래 · 물물 교환</p>
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
    <!-- Search bar -->
    <div class="max-w-[1200px] mx-auto px-4 mt-2">
      <div class="bg-white rounded-2xl shadow-sm p-3">
        <div class="flex items-center gap-2">
          <select v-model="radius" class="border border-gray-200 rounded-lg px-2 py-2 text-sm bg-white flex-shrink-0">
            <option :value="5">📍 5mi</option>
            <option :value="10">📍 10mi</option>
            <option :value="20">📍 20mi</option>
            <option :value="30">📍 30mi</option>
            <option :value="50">📍 50mi</option>
            <option :value="100">📍 100mi</option>
            <option :value="0">📍 전체</option>
          </select>
          <input v-model="search" @keyup.enter="load(1)" type="text" placeholder="검색..."
            class="flex-1 min-w-[100px] border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400" />
          <button @click="load(1)" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-blue-700 flex-shrink-0">검색</button>
          <div class="flex gap-1 flex-shrink-0">
            <button @click="viewMode = 'grid'" :class="viewMode === 'grid' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600'" class="p-2 rounded-lg">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
            </button>
            <button @click="viewMode = 'list'" :class="viewMode === 'list' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600'" class="p-2 rounded-lg">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>
            </button>
          </div>
        </div>
      </div>
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

const authStore = useAuthStore();
const radius = ref(30);
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
    const { data } = await axios.get('/api/market', { params: { page, search: search.value, category: category.value } });
    items.value = data.data;
    currentPage.value = data.current_page;
    totalPages.value = data.last_page;
  } catch {}
  loading.value = false;
}

function formatDate(d) {
  return new Date(d).toLocaleDateString('ko-KR');
}

onMounted(() => load());
</script>
