<template>
  <div class="max-w-[1200px] mx-auto px-4 py-4">
    <!-- 헤더 -->
    <div class="flex items-center justify-between mb-4">
      <div>
        <h1 class="text-xl font-bold text-gray-900">🛒 쇼핑 정보</h1>
        <p class="text-xs text-gray-500 mt-0.5">한인·미국 마트 주간 세일 & 특가 정보</p>
      </div>
      <div class="text-xs text-gray-400">매주 업데이트</div>
    </div>

    <!-- 필터 바 -->
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-3 mb-4">
      <div class="flex flex-wrap gap-2 items-center">
        <!-- 지역 필터 -->
        <div class="flex items-center gap-1.5">
          <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
          </svg>
          <select v-model="filters.region" @change="load(true)"
            class="border-0 bg-gray-50 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
            <option value="all">전체 지역</option>
            <option v-for="r in regions" :key="r" :value="r">{{ r }}</option>
          </select>
        </div>

        <!-- 마트 종류 -->
        <div class="flex gap-1.5">
          <button v-for="t in storeTypes" :key="t.value"
            @click="filters.type = filters.type === t.value ? '' : t.value; load(true)"
            :class="filters.type === t.value ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-600'"
            class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors">
            {{ t.label }}
          </button>
        </div>

        <!-- 카테고리 -->
        <select v-model="filters.category" @change="load(true)"
          class="border border-gray-200 rounded-lg px-2 py-1.5 text-sm focus:outline-none ml-auto">
          <option value="">전체 카테고리</option>
          <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
        </select>
      </div>

      <!-- 검색 -->
      <div class="mt-2">
        <div class="relative">
          <input v-model="filters.search" @input="debouncedLoad" type="text"
            placeholder="상품명, 마트명으로 검색..."
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm pr-8 focus:outline-none focus:ring-2 focus:ring-blue-400" />
          <svg class="w-4 h-4 absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
        </div>
      </div>
    </div>

    <!-- 마트 목록 (가로 스크롤) -->
    <div class="flex gap-3 overflow-x-auto pb-2 mb-4 scrollbar-hide">
      <button @click="filters.store_id = ''; load(true)"
        :class="!filters.store_id ? 'bg-blue-500 text-white border-blue-500' : 'bg-white text-gray-600 border-gray-200'"
        class="flex-shrink-0 flex flex-col items-center gap-1 border rounded-xl px-4 py-2 transition-colors min-w-[70px]">
        <span class="text-xl">🏪</span>
        <span class="text-xs font-medium whitespace-nowrap">전체</span>
      </button>

      <button v-for="store in stores" :key="store.id"
        @click="filters.store_id = store.id; load(true)"
        :class="filters.store_id == store.id ? 'bg-blue-500 text-white border-blue-500' : 'bg-white text-gray-600 border-gray-200'"
        class="flex-shrink-0 flex flex-col items-center gap-1 border rounded-xl px-4 py-2 transition-colors min-w-[80px]">
        <div class="w-8 h-8 rounded-full overflow-hidden bg-gray-100 flex items-center justify-center">
          <img v-if="store.logo" :src="store.logo" :alt="store.name" class="w-full h-full object-contain" onerror="this.style.display='none'" />
          <span v-else class="text-sm font-bold text-gray-500">{{ store.name[0] }}</span>
        </div>
        <span class="text-xs font-medium whitespace-nowrap truncate max-w-[72px]">{{ store.name }}</span>
        <span class="text-[10px] opacity-60">{{ store.deals_count }}개</span>
      </button>
    </div>

    <!-- Featured 딜 -->
    <div v-if="!filters.store_id && !filters.search && featuredDeals.length" class="mb-5">
      <h2 class="text-sm font-bold text-gray-700 mb-2">🔥 이번 주 특가</h2>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div v-for="deal in featuredDeals" :key="deal.id"
          @click="openDeal(deal)"
          class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden cursor-pointer hover:shadow-md transition-shadow">
          <div class="relative">
            <img v-if="deal.image_url" :src="deal.image_url" :alt="deal.title"
              class="w-full h-32 object-cover" onerror="this.style.display='none'" />
            <div v-else class="w-full h-32 bg-gradient-to-br from-blue-50 to-blue-100 flex items-center justify-center">
              <span class="text-3xl">{{ categoryEmoji(deal.category) }}</span>
            </div>
            <!-- 할인율 뱃지 -->
            <div v-if="deal.original_price && deal.sale_price"
              class="absolute top-2 left-2 bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded">
              {{ discountRate(deal) }}% 할인
            </div>
          </div>
          <div class="p-2.5">
            <div class="text-[10px] text-blue-500 font-medium mb-0.5">{{ deal.store?.name }}</div>
            <div class="text-xs font-semibold text-gray-900 line-clamp-2 mb-1">{{ deal.title }}</div>
            <div class="flex items-baseline gap-1.5">
              <span v-if="deal.sale_price" class="text-sm font-bold text-red-500">${{ deal.sale_price }}</span>
              <span v-if="deal.unit" class="text-[10px] text-gray-400">/ {{ deal.unit }}</span>
              <span v-if="deal.original_price" class="text-[10px] text-gray-400 line-through">${{ deal.original_price }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 전체 딜 목록 -->
    <div>
      <div class="flex items-center justify-between mb-2">
        <h2 class="text-sm font-bold text-gray-700">
          {{ filters.store_id ? stores.find(s=>s.id==filters.store_id)?.name : '전체' }} 세일 정보
        </h2>
        <span class="text-xs text-gray-400">{{ totalCount }}개</span>
      </div>

      <div v-if="loading" class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div v-for="n in 8" :key="n" class="bg-gray-100 rounded-xl h-52 animate-pulse"></div>
      </div>

      <div v-else-if="deals.length === 0" class="text-center py-12 text-gray-400">
        <div class="text-4xl mb-2">🛒</div>
        <p>해당 지역의 세일 정보가 없습니다.</p>
      </div>

      <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
        <div v-for="deal in deals" :key="deal.id"
          @click="openDeal(deal)"
          class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden cursor-pointer hover:shadow-md hover:-translate-y-0.5 transition-all">
          <div class="relative">
            <img v-if="deal.image_url" :src="deal.image_url" :alt="deal.title"
              class="w-full h-36 object-cover" onerror="this.style.display='none'" />
            <div v-else class="w-full h-36 bg-gradient-to-br from-gray-50 to-gray-100 flex items-center justify-center">
              <span class="text-4xl">{{ categoryEmoji(deal.category) }}</span>
            </div>
            <div v-if="discountRate(deal)"
              class="absolute top-2 left-2 bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-md">
              {{ discountRate(deal) }}%↓
            </div>
            <div v-if="deal.valid_until"
              class="absolute top-2 right-2 bg-black/50 text-white text-[10px] px-1.5 py-0.5 rounded">
              ~{{ formatDate(deal.valid_until) }}
            </div>
          </div>
          <div class="p-2.5">
            <div class="flex items-center gap-1 mb-0.5">
              <span class="text-[10px] text-blue-500 font-medium">{{ deal.store?.name }}</span>
              <span class="text-[10px] text-gray-300">·</span>
              <span class="text-[10px] text-gray-400">{{ deal.store?.region }}</span>
            </div>
            <div class="text-xs font-semibold text-gray-900 line-clamp-2 mb-1.5 leading-tight">{{ deal.title }}</div>
            <div class="flex items-baseline gap-1">
              <span v-if="deal.sale_price" class="text-sm font-bold text-red-500">${{ deal.sale_price }}</span>
              <span v-else-if="deal.original_price" class="text-sm font-bold text-gray-800">${{ deal.original_price }}</span>
              <span v-else class="text-xs text-gray-400">가격 문의</span>
              <span v-if="deal.unit" class="text-[10px] text-gray-400">/ {{ deal.unit }}</span>
            </div>
            <div v-if="deal.original_price && deal.sale_price" class="text-[10px] text-gray-400 line-through">
              원가 ${{ deal.original_price }}
            </div>
          </div>
        </div>
      </div>

      <!-- 더 보기 -->
      <div v-if="hasMore && !loading" class="text-center mt-5">
        <button @click="loadMore"
          class="px-6 py-2 bg-gray-100 text-gray-600 rounded-full text-sm hover:bg-gray-200 transition-colors">
          더 보기
        </button>
      </div>
    </div>

    <!-- 딜 상세 모달 -->
    <div v-if="selectedDeal" class="fixed inset-0 z-50 bg-black/50 flex items-end md:items-center justify-center p-4">
      <div class="bg-white rounded-2xl w-full max-w-md max-h-[85vh] overflow-y-auto">
        <div class="relative">
          <img v-if="selectedDeal.image_url" :src="selectedDeal.image_url"
            class="w-full h-48 object-cover rounded-t-2xl" onerror="this.style.display='none'" />
          <div v-else class="w-full h-48 bg-gradient-to-br from-blue-50 to-blue-100 rounded-t-2xl flex items-center justify-center">
            <span class="text-5xl">{{ categoryEmoji(selectedDeal.category) }}</span>
          </div>
          <button @click="selectedDeal = null"
            class="absolute top-3 right-3 w-8 h-8 bg-black/40 rounded-full flex items-center justify-center text-white hover:bg-black/60">
            ✕
          </button>
          <div v-if="discountRate(selectedDeal)"
            class="absolute bottom-3 left-3 bg-red-500 text-white font-bold px-3 py-1 rounded-full text-sm">
            {{ discountRate(selectedDeal) }}% 할인
          </div>
        </div>
        <div class="p-5">
          <div class="flex items-center gap-2 mb-2">
            <div class="w-6 h-6 rounded-full bg-gray-100 overflow-hidden flex-shrink-0">
              <img v-if="selectedDeal.store?.logo" :src="selectedDeal.store.logo" class="w-full h-full object-contain" onerror="this.style.display='none'" />
            </div>
            <span class="text-sm font-semibold text-blue-600">{{ selectedDeal.store?.name }}</span>
            <span class="text-xs text-gray-400">{{ selectedDeal.store?.region }}</span>
          </div>
          <h3 class="text-lg font-bold text-gray-900 mb-2">{{ selectedDeal.title }}</h3>
          <div class="flex items-baseline gap-2 mb-3">
            <span v-if="selectedDeal.sale_price" class="text-2xl font-bold text-red-500">${{ selectedDeal.sale_price }}</span>
            <span v-if="selectedDeal.unit" class="text-sm text-gray-500">/ {{ selectedDeal.unit }}</span>
            <span v-if="selectedDeal.original_price" class="text-sm text-gray-400 line-through">${{ selectedDeal.original_price }}</span>
          </div>
          <p v-if="selectedDeal.description" class="text-sm text-gray-600 mb-3">{{ selectedDeal.description }}</p>
          <div v-if="selectedDeal.valid_until" class="text-xs text-orange-500 mb-4">
            ⏰ {{ formatDate(selectedDeal.valid_until) }}까지 유효
          </div>
          <div class="flex gap-2">
            <a v-if="selectedDeal.source_url" :href="selectedDeal.source_url" target="_blank" rel="noopener"
              class="flex-1 bg-blue-500 text-white text-center py-3 rounded-xl font-semibold hover:bg-blue-600 transition-colors">
              마트 사이트 방문
            </a>
            <a :href="selectedDeal.store?.website" target="_blank" rel="noopener"
              class="flex-1 bg-gray-100 text-gray-700 text-center py-3 rounded-xl font-semibold hover:bg-gray-200 transition-colors">
              {{ selectedDeal.store?.name }} 홈페이지
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';

const stores       = ref([]);
const deals        = ref([]);
const featuredDeals = ref([]);
const categories   = ref([]);
const loading      = ref(false);
const selectedDeal = ref(null);
const totalCount   = ref(0);
const hasMore      = ref(true);
const currentPage  = ref(1);
let debounceTimer  = null;

const filters = reactive({ region: 'all', type: '', category: '', search: '', store_id: '' });
const regions = ['Atlanta', 'NY', 'Los Angeles', 'Dallas', 'Chicago', 'Seattle'];
const storeTypes = [
  { value: 'korean',   label: '한인마트' },
  { value: 'american', label: '미국마트' },
  { value: 'asian',    label: '아시안마트' },
];

async function loadStores() {
  const { data } = await axios.get('/api/shopping/stores', {
    params: { region: filters.region }
  });
  stores.value = data;
}

async function load(reset = false) {
  if (reset) { deals.value = []; currentPage.value = 1; hasMore.value = true; }
  if (loading.value || !hasMore.value) return;
  loading.value = true;
  try {
    const { data } = await axios.get('/api/shopping/deals', {
      params: { ...filters, page: currentPage.value }
    });
    deals.value.push(...data.data);
    totalCount.value = data.total;
    hasMore.value    = !!data.next_page_url;
    currentPage.value++;

    // Featured는 첫 페이지에만
    if (currentPage.value === 2) {
      const { data: fd } = await axios.get('/api/shopping/deals', {
        params: { ...filters, featured: 1, page: 1 }
      });
      featuredDeals.value = fd.data.slice(0, 4);
    }
  } catch {}
  loading.value = false;
}

function loadMore() { load(); }

function debouncedLoad() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => load(true), 400);
}

function openDeal(deal) { selectedDeal.value = deal; }

function discountRate(deal) {
  if (deal.original_price && deal.sale_price && deal.original_price > 0) {
    return Math.round((1 - deal.sale_price / deal.original_price) * 100);
  }
  return null;
}

function categoryEmoji(cat) {
  const map = { '육류':'🥩','생선/해산물':'🐟','채소':'🥦','과일/채소':'🍎','라면/국수':'🍜','떡/빵':'🍞','반찬/김치':'🥘','냉동식품':'❄️','양념/조미료':'🫙','생활용품':'🧴','과자':'🍪' };
  return map[cat] || '🛒';
}

function formatDate(d) {
  if (!d) return '';
  return new Date(d).toLocaleDateString('ko-KR', { month: 'short', day: 'numeric' });
}

onMounted(async () => {
  await Promise.all([loadStores(), load(true)]);
  const { data } = await axios.get('/api/shopping/categories');
  categories.value = data;
});
</script>
