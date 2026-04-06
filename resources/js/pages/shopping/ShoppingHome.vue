<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
      <h1 class="text-xl font-black text-gray-800">🛍️ 쇼핑 / 핫딜</h1>
      <div class="flex items-center gap-2">
        <select v-model="category" @change="load()" class="border rounded-lg px-2 py-1.5 text-xs text-gray-600 outline-none">
          <option value="">전체</option>
          <option value="electronics">전자기기</option>
          <option value="fashion">패션</option>
          <option value="food">식품</option>
          <option value="home">홈/리빙</option>
          <option value="beauty">뷰티</option>
        </select>
        <form @submit.prevent="load()" class="flex gap-1">
          <input v-model="search" type="text" placeholder="검색..." class="border rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-amber-400 outline-none w-32" />
          <button type="submit" class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-amber-500">검색</button>
        </form>
      </div>
    </div>

    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="!deals.length" class="text-center py-12 text-gray-400">등록된 딜이 없습니다</div>
    <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
      <a v-for="deal in deals" :key="deal.id" :href="deal.url || '#'" target="_blank"
        class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition group">
        <div class="aspect-square bg-gray-100 flex items-center justify-center">
          <img v-if="deal.image_url" :src="deal.image_url" class="w-full h-full object-cover" @error="e=>e.target.style.display='none'" />
          <span v-else class="text-4xl">🛍️</span>
        </div>
        <div class="p-3">
          <div class="text-xs text-gray-500 mb-1">{{ deal.store?.name || '매장' }}</div>
          <div class="text-sm font-medium text-gray-800 line-clamp-2 group-hover:text-amber-700">{{ deal.title }}</div>
          <div class="flex items-center gap-2 mt-2">
            <span v-if="deal.original_price" class="text-xs text-gray-400 line-through">${{ Number(deal.original_price).toLocaleString() }}</span>
            <span v-if="deal.sale_price" class="text-sm font-bold text-red-500">${{ Number(deal.sale_price).toLocaleString() }}</span>
            <span v-if="deal.discount_percent" class="text-xs bg-red-100 text-red-600 px-1.5 py-0.5 rounded-full font-bold">-{{ deal.discount_percent }}%</span>
          </div>
        </div>
      </a>
    </div>

    <div v-if="lastPage > 1" class="flex justify-center gap-1.5 mt-4">
      <button v-for="pg in Math.min(lastPage, 10)" :key="pg" @click="load(pg)"
        class="px-3 py-1 rounded text-sm" :class="pg===page?'bg-amber-400 text-amber-900 font-bold':'bg-white text-gray-600 border hover:bg-amber-50'">{{ pg }}</button>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
const deals = ref([])
const loading = ref(true)
const page = ref(1)
const lastPage = ref(1)
const search = ref('')
const category = ref('')

async function load(p = 1) {
  loading.value = true; page.value = p
  const params = { page: p, per_page: 20 }
  if (search.value) params.search = search.value
  if (category.value) params.category = category.value
  try {
    const { data } = await axios.get('/api/shopping', { params })
    deals.value = data.data?.data || data.data || []
    lastPage.value = data.data?.last_page || 1
  } catch {}
  loading.value = false
}

onMounted(() => load())
</script>
