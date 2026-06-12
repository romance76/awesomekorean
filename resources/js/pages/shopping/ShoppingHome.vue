<template>
<div class="min-h-screen">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
        <span class="icon-chip w-9 h-9 bg-lime-50 text-lime-600"><AppIcon name="shopping-bag" :size="20" /></span>
        쇼핑 / 핫딜
      </h1>
      <div class="flex items-center gap-2">
        <select v-model="category" @change="load()" class="input-soft w-auto text-xs py-1.5">
          <option value="">전체</option>
          <option value="electronics">전자기기</option>
          <option value="fashion">패션</option>
          <option value="food">식품</option>
          <option value="home">홈/리빙</option>
          <option value="beauty">뷰티</option>
        </select>
        <form @submit.prevent="load()" class="flex gap-1">
          <input v-model="search" type="text" placeholder="검색..." class="input-soft w-32 py-1.5" />
          <button type="submit" class="btn-primary text-xs px-3 py-1.5">검색</button>
        </form>
      </div>
    </div>

    <div v-if="loading" class="text-center py-12 text-ink-muted">로딩중...</div>
    <div v-else-if="!deals.length" class="py-16 text-center">
      <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="shopping-bag" :size="28" :stroke-width="1.5" /></div>
      <p class="text-sm text-ink-muted">등록된 딜이 없습니다</p>
    </div>
    <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
      <a v-for="deal in deals" :key="deal.id" :href="deal.url || '#'" target="_blank"
        class="card card-hover overflow-hidden group">
        <div class="aspect-square bg-gray-100 flex items-center justify-center text-gray-300">
          <img v-if="deal.image_url" :src="deal.thumbnail_url || thumb(deal.image_url, 320)" loading="lazy" decoding="async" class="w-full h-full object-cover" @error="e=>e.target.style.display='none'" />
          <AppIcon v-else name="shopping-bag" :size="28" :stroke-width="1.5" />
        </div>
        <div class="p-3">
          <div class="text-xs text-ink-muted mb-1">{{ deal.store?.name || '매장' }}</div>
          <div class="text-sm font-medium text-ink line-clamp-2 group-hover:text-amber-700 transition-colors">{{ deal.title }}</div>
          <div class="flex items-center gap-2 mt-2">
            <span v-if="deal.original_price" class="text-xs text-ink-faint line-through">${{ Number(deal.original_price).toLocaleString() }}</span>
            <span v-if="deal.sale_price" class="text-sm font-bold text-red-500">${{ Number(deal.sale_price).toLocaleString() }}</span>
            <span v-if="deal.discount_percent" class="badge-red font-bold">-{{ deal.discount_percent }}%</span>
          </div>
        </div>
      </a>
    </div>

    <Pagination :page="page" :lastPage="lastPage" @page="load" />
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { thumb } from '../../utils/thumb'
import AppIcon from '../../components/AppIcon.vue'
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
