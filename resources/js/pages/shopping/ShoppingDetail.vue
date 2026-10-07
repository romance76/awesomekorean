<template>
<div class="min-h-screen">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <DetailHeader :title="product?.title || pageTitle" fallback="/shopping" />
    <div class="hidden lg:flex items-center justify-between mb-4">
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
        <span class="icon-chip w-9 h-9 bg-lime-50 text-lime-600"><AppIcon name="shopping-bag" :size="20" /></span>
        {{ pageTitle }}
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

      <!-- 가운데: 이미지 갤러리 -->
      <div class="col-span-12 lg:col-span-6 space-y-4">
        <div class="card overflow-hidden">
          <div class="w-full bg-gray-100 flex items-center justify-center text-gray-300" style="height:360px;">
            <img v-if="mainImage" :src="mainImage" :alt="product.title" class="w-full h-full object-contain" @error="e=>e.target.style.display='none'" />
            <AppIcon v-else name="shopping-bag" :size="40" :stroke-width="1.5" />
          </div>
          <div v-if="allImages.length > 1" class="flex gap-1 p-2 overflow-x-auto bg-gray-50">
            <div v-for="(img, i) in allImages" :key="i" @click="selectedIdx = i"
              class="flex-shrink-0 rounded cursor-pointer border-2 transition overflow-hidden"
              :class="i === selectedIdx ? 'border-amber-400' : 'border-transparent hover:border-gray-300'"
              style="width:56px; height:42px;">
              <img :src="img" class="w-full h-full object-contain bg-white" />
            </div>
          </div>
        </div>

        <!-- 회원 리뷰: 글자 그대로(HTML 해석 안 함) -->
        <div v-if="product.is_member_review" class="card p-4">
          <div class="flex items-center gap-2 mb-2 flex-wrap">
            <span class="text-amber-400 text-base">{{ '★'.repeat(product.rating || 0) }}<span class="text-gray-300">{{ '★'.repeat(5 - (product.rating || 0)) }}</span></span>
            <span class="text-xs font-bold text-ink-light">✍️ {{ product.author?.name }}님의 내돈내산 리뷰</span>
          </div>
          <!-- 글 + 사진 블록 (예전 방식으로 쓴 글은 본문만) -->
          <template v-if="product.review_blocks?.length">
            <template v-for="(b, i) in product.review_blocks" :key="i">
              <p v-if="b.type === 'text'" class="text-sm text-ink leading-relaxed whitespace-pre-line break-words my-2">{{ b.text }}</p>
              <img v-else :src="b.url" alt="" loading="lazy" class="w-full rounded-lg my-3 border border-gray-100" />
            </template>
          </template>
          <div v-else class="text-sm text-ink leading-relaxed whitespace-pre-line break-words">{{ product.our_description }}</div>
        </div>
        <div v-else-if="product.our_description" class="card p-4">
          <div class="text-xs font-bold text-ink-light mb-1.5">✍️ Awesome Korean의 추천 이유</div>
          <div class="shopping-review text-sm text-ink leading-relaxed" v-html="product.our_description"></div>
        </div>

        <div v-if="product.is_member_review" class="flex items-start gap-2 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2 text-[11px] text-amber-800 leading-snug">
          <span class="shrink-0">ℹ️</span>
          <p>이 리뷰는 회원 <b>{{ product.author?.name }}</b>님이 직접 쓴 글이에요. "Amazon에서 보기" 링크는 작성자의 Amazon Associates 링크이며, 이 링크로 구매하면 <b>작성자</b>가 Amazon으로부터 수수료를 받을 수 있어요. Awesome Korean은 이 수수료를 받지 않아요. 리뷰는 개인 의견이며 Awesome Korean의 보증이 아니에요.</p>
        </div>
        <AffiliateDisclosure v-else />

        <!-- 내 리뷰 상태 안내 -->
        <div v-if="product.is_owner && product.status !== 'published'" class="rounded-xl border px-4 py-3 text-sm"
          :class="product.status === 'pending' ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-red-200 bg-red-50 text-red-700'">
          <b>{{ statusText[product.status] || product.status }}</b>
          <span v-if="product.admin_note"> — {{ product.admin_note }}</span>
          <span v-if="product.status === 'pending'"> (관리자 확인 후 다른 회원에게 공개돼요)</span>
        </div>

        <CommentSection v-if="product.is_member_review && product.status === 'published'" type="shopping" :typeId="product.id" />
      </div>

      <!-- 오른쪽: 기본정보 -->
      <div class="col-span-12 lg:col-span-4">
        <div class="sticky top-20 card p-4 space-y-3">
          <div class="flex items-center gap-2 flex-wrap">
            <span v-if="product.category" class="badge-primary !text-[11px] !px-2">{{ product.category }}</span>
            <span v-if="product.is_hot" class="bg-rose-500 text-white rounded-full px-2 py-0.5 text-[11px] font-black">🔥 이번주 HOT</span>
            <span v-if="product.is_featured" class="badge-red !text-[11px]">Awesome Korean 추천</span>
            <span v-if="product.is_member_review" class="badge-primary !text-[11px] !px-2">회원 리뷰</span>
          </div>
          <h1 class="text-base font-bold text-ink leading-snug">{{ product.title }}</h1>
          <div v-if="product.price" class="text-2xl font-black text-amber-600">
            ${{ Number(product.price).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
            <span class="text-xs font-normal text-ink-faint block mt-0.5">참고 가격 — 실시간 Amazon 가격과 다를 수 있습니다</span>
          </div>
          <a v-if="product.status === 'published'" :href="`/go/amazon/${product.id}`" target="_blank" rel="noopener sponsored nofollow"
            class="btn-primary w-full flex items-center justify-center gap-1.5 py-3 text-sm font-bold">
            Amazon에서 보기 <AppIcon name="external-link" :size="14" />
          </a>
          <p v-if="product.is_member_review" class="text-[11px] text-ink-faint text-center">작성자의 제휴 링크예요 · 👁 {{ product.view_count || 0 }}명 조회</p>

          <div v-if="product.is_owner" class="flex gap-2 pt-1">
            <RouterLink :to="{ path: '/shopping/write', query: { edit: product.id } }" class="btn-secondary flex-1 text-center text-xs py-2">수정</RouterLink>
            <button @click="removeMine" class="flex-1 text-xs py-2 rounded-xl border border-red-200 text-red-500 hover:bg-red-50 font-bold">삭제</button>
          </div>
          <button v-else-if="product.is_member_review" @click="reportOpen = true" class="w-full text-[11px] text-ink-faint hover:text-red-500 flex items-center justify-center gap-1 pt-1">
            <AppIcon name="flag" :size="12" /> 이 리뷰 신고 (광고·홍보·허위)
          </button>
        </div>
      </div>
    </div>
  </div>
  <ReportModal :show="reportOpen" reportableType="App\Models\AmazonProduct" :reportableId="product?.id" contentType="post" @close="reportOpen = false" @reported="reportOpen = false" />
</div>
</template>
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import AppIcon from '../../components/AppIcon.vue'
import DetailHeader from '../../components/DetailHeader.vue'
import AffiliateDisclosure from '../../components/AffiliateDisclosure.vue'
import CommentSection from '../../components/CommentSection.vue'
import ReportModal from '../../components/ReportModal.vue'
import { useSiteStore } from '../../stores/site'
import axios from 'axios'

const route = useRoute()
const router = useRouter()
const site = useSiteStore()
// 관리자 > 메뉴 설정에서 바꾼 이름(예: 내돈내산 리뷰)을 제목에도 사용
const pageTitle = computed(() => site.menuConfig?.find(m => m.key === 'shopping')?.label || '쇼핑')
const product = ref(null)
const loading = ref(true)
const selectedIdx = ref(0)
const reportOpen = ref(false)
const statusText = { pending: '승인 대기 중', hidden: '내려간 리뷰', rejected: '반려됨' }
async function removeMine() {
  if (!window.confirm('이 리뷰를 삭제할까요?')) return
  try { await axios.delete(`/api/shopping/reviews/${product.value.id}`); router.replace('/dashboard?tab=reviews') } catch {}
}
const categories = [
  '인기상품', '오늘의 딜', '주방용품', '한국요리 관련 제품', '식품', '생활용품',
  '가전/전자제품', '육아/교육', 'Beauty/K-Beauty', 'Home', '계절상품', '선물', 'Awesome Korean 추천',
]

const allImages = computed(() => {
  if (!product.value) return []
  // 글 사이에 사진을 넣어 쓴 회원 리뷰는 사진이 본문에 있으므로 위 갤러리엔 Amazon 사진만
  const own = product.value.review_blocks?.length ? [] : (product.value.own_image_urls || [])
  return [...(product.value.amazon_image_urls || []), ...own].filter(Boolean)
})
const mainImage = computed(() => allImages.value[selectedIdx.value] || allImages.value[0] || product.value?.image_url || '')

async function load() {
  loading.value = true
  selectedIdx.value = 0
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
<style scoped>
.shopping-review :deep(img) { max-width: 100%; height: auto; border-radius: 6px; margin: 8px 0; }
.shopping-review :deep(h2) { font-size: 1.1rem; font-weight: 700; margin: 0.75rem 0 0.375rem; }
.shopping-review :deep(h3) { font-size: 1rem; font-weight: 600; margin: 0.625rem 0 0.375rem; }
.shopping-review :deep(ul) { list-style: disc; padding-left: 1.5rem; }
.shopping-review :deep(ol) { list-style: decimal; padding-left: 1.5rem; }
</style>
