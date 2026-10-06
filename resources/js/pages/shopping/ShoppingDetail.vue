<template>
<div class="min-h-screen">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <DetailHeader :title="product?.title || '쇼핑'" fallback="/shopping" />
    <div class="hidden lg:flex items-center justify-between mb-4">
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
        <span class="icon-chip w-9 h-9 bg-lime-50 text-lime-600"><AppIcon name="shopping-bag" :size="20" /></span>
        쇼핑
      </h1>
    </div>

    <div v-if="loading" class="text-center py-12 text-ink-muted">로딩중...</div>
    <div v-else-if="!product" class="py-16 text-center text-ink-muted">상품을 찾을 수 없습니다</div>
    <div v-else class="grid grid-cols-12 gap-4">
      <!-- 왼쪽: 카테고리 -->
      <div class="col-span-12 lg:col-span-2 hidden lg:block">
        <div class="sticky top-20 space-y-3">
          <div class="card overflow-hidden">
            <div class="px-3 py-2.5 border-b border-gray-50 font-bold text-xs text-ink flex items-center gap-1.5"><AppIcon name="list" :size="13" class="text-lime-600" />카테고리</div>
            <RouterLink to="/shopping" class="block w-full text-left px-3 py-2 text-xs text-ink-light hover:bg-amber-50/50 transition-colors">전체</RouterLink>
            <RouterLink v-for="c in categories" :key="c" :to="{ path: '/shopping', query: { category: c } }"
              class="block w-full text-left px-3 py-2 text-xs transition-colors"
              :class="product.category === c ? 'bg-amber-50 text-amber-700 font-bold' : 'text-ink-light hover:bg-amber-50/50'">{{ c }}</RouterLink>
          </div>
        </div>
      </div>

      <!-- 가운데: 상세 -->
      <div class="col-span-12 lg:col-span-7 space-y-4">
        <div class="card overflow-hidden">
          <div class="w-full bg-gray-100 flex items-center justify-center text-gray-300" style="height:360px;">
            <img v-if="product.image_url" :src="product.image_url" :alt="product.title" class="w-full h-full object-contain" @error="e=>e.target.style.display='none'" />
            <AppIcon v-else name="shopping-bag" :size="40" :stroke-width="1.5" />
          </div>
          <div class="p-4">
            <div class="flex items-center gap-2 mb-2 flex-wrap">
              <span v-if="product.category" class="badge-primary !text-[11px] !px-2">{{ product.category }}</span>
              <span v-if="product.is_featured" class="badge-red !text-[11px]">Awesome Korean 추천</span>
            </div>
            <h1 class="text-lg font-bold text-ink">{{ product.title }}</h1>
          </div>
        </div>

        <div v-if="product.our_description" class="card p-4">
          <div class="text-xs font-bold text-ink-light mb-1.5">✍️ Awesome Korean의 추천 이유</div>
          <p class="text-sm text-ink whitespace-pre-wrap leading-relaxed">{{ product.our_description }}</p>
        </div>

        <AffiliateDisclosure />

        <a :href="`/go/amazon/${product.id}`" target="_blank" rel="noopener sponsored"
          class="btn-primary w-full flex items-center justify-center gap-1.5 py-3 text-sm font-bold">
          Amazon에서 보기 <AppIcon name="external-link" :size="14" />
        </a>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import AppIcon from '../../components/AppIcon.vue'
import DetailHeader from '../../components/DetailHeader.vue'
import AffiliateDisclosure from '../../components/AffiliateDisclosure.vue'
import axios from 'axios'

const route = useRoute()
const product = ref(null)
const loading = ref(true)
const categories = [
  '인기상품', '오늘의 딜', '주방용품', '한국요리 관련 제품', '식품', '생활용품',
  '가전/전자제품', '육아/교육', 'Beauty/K-Beauty', 'Home', '계절상품', '선물', 'Awesome Korean 추천',
]

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get(`/api/shopping/${route.params.id}`)
    product.value = data.data
  } catch {
    product.value = null
  }
  loading.value = false
}

watch(() => route.params.id, load)
onMounted(load)
</script>
