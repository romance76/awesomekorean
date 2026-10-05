<template>
<div class="min-h-screen">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
        <span class="icon-chip w-9 h-9 bg-lime-50 text-lime-600"><AppIcon name="shopping-bag" :size="20" /></span>
        쇼핑
      </h1>
      <div class="flex items-center gap-2">
        <select v-model="category" @change="load()" class="input-soft w-auto text-xs py-1.5">
          <option value="">전체</option>
          <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
        </select>
        <form @submit.prevent="load()" class="flex gap-1">
          <input v-model="search" type="text" placeholder="검색..." class="input-soft w-32 py-1.5" />
          <button type="submit" class="btn-primary text-xs px-3 py-1.5">검색</button>
        </form>
      </div>
    </div>

    <AffiliateDisclosure class="mb-4" />

    <div v-if="loading" class="text-center py-12 text-ink-muted">로딩중...</div>
    <div v-else-if="!products.length" class="py-16 text-center">
      <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="shopping-bag" :size="28" :stroke-width="1.5" /></div>
      <p class="text-sm text-ink-muted">등록된 상품이 없습니다</p>
    </div>
    <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
      <a v-for="product in products" :key="product.id" :href="goUrl(product.id)" target="_blank" rel="noopener sponsored"
        class="card card-hover overflow-hidden group">
        <div class="aspect-square bg-gray-100 flex items-center justify-center text-gray-300 relative">
          <span v-if="product.is_featured" class="absolute top-1.5 left-1.5 badge-red !text-[10px] font-bold">추천</span>
          <img v-if="product.image_url" :src="product.image_url" loading="lazy" decoding="async" class="w-full h-full object-cover" @error="e=>e.target.style.display='none'" />
          <AppIcon v-else name="shopping-bag" :size="28" :stroke-width="1.5" />
        </div>
        <div class="p-3">
          <div v-if="product.category" class="text-xs text-ink-muted mb-1">{{ product.category }}</div>
          <div class="text-sm font-medium text-ink line-clamp-2 group-hover:text-amber-700 transition-colors">{{ product.title }}</div>
          <p v-if="product.our_description" class="text-xs text-ink-light line-clamp-2 mt-1">{{ product.our_description }}</p>
          <div class="mt-2">
            <span class="inline-flex items-center gap-1 text-xs font-semibold text-orange-600">Amazon에서 보기 →</span>
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
import AppIcon from '../../components/AppIcon.vue'
import AffiliateDisclosure from '../../components/AffiliateDisclosure.vue'
import axios from 'axios'

const products = ref([])
const loading = ref(true)
const page = ref(1)
const lastPage = ref(1)
const search = ref('')
const category = ref('')
const categories = [
  '인기상품', '오늘의 딜', '주방용품', '한국요리 관련 제품', '식품', '생활용품',
  '가전/전자제품', '육아/교육', 'Beauty/K-Beauty', 'Home', '계절상품', '선물', 'Awesome Korean 추천',
]

function goUrl(id) {
  return `/go/amazon/${id}`
}

async function load(p = 1) {
  loading.value = true; page.value = p
  const params = { page: p, per_page: 20 }
  if (search.value) params.search = search.value
  if (category.value) params.category = category.value
  try {
    const { data } = await axios.get('/api/shopping', { params })
    products.value = data.data?.data || data.data || []
    lastPage.value = data.data?.last_page || 1
  } catch {}
  loading.value = false
}

onMounted(() => load())
</script>
