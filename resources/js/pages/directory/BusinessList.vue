<template>
  <div class="min-h-screen bg-gray-50 pb-16">
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
          <input v-model="search" @keyup.enter="load(1)" type="text" placeholder="업소명 검색..."
            class="flex-1 min-w-[100px] border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400" />
          <select v-model="region" @change="load(1)" class="border border-gray-200 rounded-lg px-2 py-2 text-sm bg-white flex-shrink-0">
            <option value="">전체 지역</option>
            <option>Atlanta</option><option>New York</option><option>Los Angeles</option>
            <option>Dallas</option><option>Chicago</option>
          </select>
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
const businesses = ref([]);
const loading = ref(true);
const search = ref('');
const category = ref('');
const region = ref('');
const currentPage = ref(1);
const totalPages = ref(1);

const categories = [
  { value: '', label: '전체' },
  { value: '식당', label: '🍽️ 식당' },
  { value: '미용', label: '💇 미용' },
  { value: '의료', label: '🏥 의료' },
  { value: '법률', label: '⚖️ 법률' },
  { value: '부동산', label: '🏠 부동산' },
  { value: '쇼핑', label: '🛍️ 쇼핑' },
  { value: '교육', label: '📚 교육' },
  { value: '기타', label: '기타' },
];

async function load(page = 1) {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/businesses', { params: { page, search: search.value, category: category.value, region: region.value } });
    businesses.value = data.data;
    currentPage.value = data.current_page;
    totalPages.value = data.last_page;
  } catch {}
  loading.value = false;
}

onMounted(() => load());
</script>
