<template>
<div class="min-h-screen">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <!-- 헤더: 모바일 -->
    <div class="lg:hidden mb-3 flex items-center justify-between gap-2">
      <h1 class="flex items-center gap-2 text-lg font-bold text-ink">
        <span class="icon-chip w-8 h-8 bg-lime-50 text-lime-600"><AppIcon name="shopping-bag" :size="17" /></span>
        쇼핑
      </h1>
      <form @submit.prevent="load(1)" class="flex gap-1">
        <input v-model="search" type="text" placeholder="검색..." class="input-soft w-28 py-1.5 text-xs" />
        <button type="submit" class="btn-primary text-xs px-3 py-1.5">검색</button>
      </form>
    </div>

    <!-- 헤더: 데스크탑 -->
    <div class="hidden lg:flex items-center justify-between mb-4 flex-wrap gap-2">
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
        <span class="icon-chip w-9 h-9 bg-lime-50 text-lime-600"><AppIcon name="shopping-bag" :size="20" /></span>
        쇼핑
      </h1>
      <form @submit.prevent="load(1)" class="flex gap-1">
        <input v-model="search" type="text" placeholder="상품 검색..." class="input-soft w-40 px-3 py-1.5 text-sm" />
        <button type="submit" class="btn-primary px-3 py-1.5 text-xs">검색</button>
      </form>
    </div>

    <AffiliateDisclosure class="mb-4" />

    <div class="grid grid-cols-12 gap-4">
      <!-- 왼쪽: 카테고리 -->
      <div class="col-span-12 lg:col-span-2 hidden lg:block">
        <div class="sticky top-20 space-y-3">
          <div class="card overflow-hidden">
            <div class="px-3 py-2.5 border-b border-gray-50 font-bold text-xs text-ink flex items-center gap-1.5"><AppIcon name="list" :size="13" class="text-lime-600" />카테고리</div>
            <button @click="category=''; load(1)" class="w-full text-left px-3 py-2 text-xs transition-colors"
              :class="!category ? 'bg-amber-50 text-amber-700 font-bold' : 'text-ink-light hover:bg-amber-50/50'">전체</button>
            <button v-for="c in categories" :key="c" @click="category=c; load(1)" class="w-full text-left px-3 py-2 text-xs transition-colors"
              :class="category===c ? 'bg-amber-50 text-amber-700 font-bold' : 'text-ink-light hover:bg-amber-50/50'">{{ c }}</button>
          </div>
        </div>
      </div>

      <!-- 메인: 상품 목록 -->
      <div class="col-span-12 lg:col-span-10">
        <div class="mb-2">
          <span class="font-bold text-amber-700 text-sm">{{ category || '전체' }}</span>
          <span v-if="!category" class="text-xs text-ink-muted ml-2">Awesome Korean이 고른 Amazon 추천 상품을 볼 수 있습니다</span>
        </div>

        <div v-if="loading" class="text-center py-12 text-ink-muted">로딩중...</div>
        <div v-else-if="!products.length" class="py-16 text-center">
          <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="shopping-bag" :size="28" :stroke-width="1.5" /></div>
          <p class="text-sm text-ink-muted">등록된 상품이 없습니다</p>
        </div>
        <div v-else class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <RouterLink v-for="product in products" :key="product.id" :to="`/shopping/${product.id}`" class="card card-hover overflow-hidden cursor-pointer block">
            <div class="w-full h-40 bg-gray-100 overflow-hidden flex items-center justify-center text-gray-300 relative">
              <span v-if="product.is_featured" class="absolute top-1.5 left-1.5 badge-red !text-[10px] font-bold z-10">추천</span>
              <img v-if="product.image_url" :src="product.image_url" :alt="product.title" loading="lazy" decoding="async" class="w-full h-full object-cover" @error="e=>e.target.style.display='none'" />
              <AppIcon v-else name="shopping-bag" :size="28" :stroke-width="1.5" />
            </div>
            <div class="p-3">
              <span v-if="product.category" class="badge-primary !text-[11px] !px-2">{{ product.category }}</span>
              <div class="text-sm font-semibold text-ink line-clamp-2 leading-snug mt-1">{{ product.title }}</div>
              <p v-if="product.our_description" class="text-xs text-ink-muted line-clamp-2 mt-1">{{ product.our_description }}</p>
            </div>
          </RouterLink>
        </div>

        <Pagination :page="page" :lastPage="lastPage" @page="load" />
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import AppIcon from '../../components/AppIcon.vue'
import AffiliateDisclosure from '../../components/AffiliateDisclosure.vue'
import axios from 'axios'

const route = useRoute()
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

onMounted(() => {
  if (route.query.category) category.value = route.query.category
  load()
})
</script>
