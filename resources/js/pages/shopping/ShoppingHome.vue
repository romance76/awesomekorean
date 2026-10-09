<template>
<div class="min-h-screen">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <!-- 헤더: 모바일 -->
    <div class="lg:hidden mb-3 flex items-center justify-between gap-2">
      <h1 class="flex items-center gap-2 text-lg font-bold text-ink">
        <span class="icon-chip w-8 h-8 bg-lime-50 text-lime-600"><AppIcon name="shopping-bag" :size="17" /></span>
        {{ pageTitle }}
      </h1>
      <form @submit.prevent="load(1)" class="flex gap-1">
        <input v-model="search" type="text" placeholder="검색..." class="input-soft w-28 py-1.5 text-xs" />
        <button type="submit" class="btn-primary text-xs px-3 py-1.5">검색</button>
      </form>
    </div>
    <div class="lg:hidden mb-3"><button @click="goWrite" class="w-full btn-primary py-2 text-sm font-bold">내 리뷰 쓰기</button></div>

    <!-- 헤더: 데스크탑 -->
    <div class="hidden lg:flex items-center justify-between mb-4 flex-wrap gap-2">
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
        <span class="icon-chip w-9 h-9 bg-lime-50 text-lime-600"><AppIcon name="shopping-bag" :size="20" /></span>
        {{ pageTitle }}
      </h1>
      <div class="flex gap-2 items-center">
        <form @submit.prevent="load(1)" class="flex gap-1">
          <input v-model="search" type="text" placeholder="상품 검색..." class="input-soft w-40 px-3 py-1.5 text-sm" />
          <button type="submit" class="btn-primary px-3 py-1.5 text-xs">검색</button>
        </form>
        <button @click="goWrite" class="btn-primary px-3 py-1.5 text-xs font-bold">내 리뷰 쓰기</button>
      </div>
    </div>

    <!-- 내돈내산 소개 히어로 (검색/필터 중에는 숨김) -->
    <section v-if="showHero" class="card overflow-hidden mb-4">
      <div class="grid lg:grid-cols-2">
        <div class="p-6 lg:p-9 flex flex-col justify-center">
          <span class="text-xs font-bold tracking-wide" style="color:#FC226B">진짜 후기, 진짜 이야기</span>
          <h2 class="text-2xl lg:text-4xl font-black text-ink leading-tight mt-2" style="letter-spacing:-0.03em">
            내가 산 물건,<br>내가 쓴 후기.<br><span style="color:#FC226B">후기가 용돈이 된다면?</span>
          </h2>
          <p class="text-sm lg:text-base text-ink-light leading-relaxed mt-3">
            미국 생활에서 직접 써본 아마존 아이템을 한국어로 소개해 보세요. 이웃에게는 믿을 만한 쇼핑 정보가, 작성자에게는 새로운 기회가 됩니다.
          </p>
          <div class="flex flex-wrap gap-2 mt-5">
            <button @click="goWrite" class="btn-primary px-5 py-2.5 text-sm font-bold inline-flex items-center gap-1.5"><AppIcon name="edit" :size="15" />내 후기 남기기</button>
            <button @click="scrollToList" class="px-5 py-2.5 rounded-full text-sm font-bold border border-gray-200 bg-white text-ink hover:border-gray-300 inline-flex items-center gap-1.5">인기 후기 구경하기<AppIcon name="arrow-right" :size="14" /></button>
          </div>
          <p class="text-[11px] text-ink-faint mt-3">* 제휴 수익은 Amazon Associates 자격 및 적용 약관에 따라 달라집니다.</p>
        </div>
        <div class="p-4 lg:p-6 bg-amber-50/60 grid grid-cols-2 gap-3 content-center">
          <template v-if="heroProducts.length">
            <RouterLink v-for="p in heroProducts" :key="p.id" :to="`/shopping/${p.id}`" class="rounded-2xl overflow-hidden bg-white border border-gray-100 block">
              <div class="aspect-square bg-white flex items-center justify-center overflow-hidden"><img :src="p.image_url" :alt="p.title" loading="lazy" decoding="async" class="w-full h-full object-contain" @error="e=>e.target.style.display='none'" /></div>
              <div class="px-2.5 py-2 text-xs font-semibold text-ink line-clamp-1">{{ p.title }}</div>
            </RouterLink>
          </template>
          <div v-else class="col-span-2 rounded-2xl bg-white border border-gray-100 p-8 text-center text-ink-muted">
            <div class="icon-chip w-12 h-12 bg-amber-50 text-amber-600 mx-auto mb-2"><AppIcon name="shopping-bag" :size="24" /></div>
            <p class="text-sm font-bold text-ink">첫 번째 후기의 주인공이 되어 보세요</p>
          </div>
        </div>
      </div>
    </section>

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
        <div class="mb-3 text-xs text-ink-muted">내돈내산 리뷰를 쓰려면 Amazon Associates 계정이 필요해요. <RouterLink to="/shopping/guide" class="font-bold text-amber-700 underline">가입 방법 보기</RouterLink></div>
        <div class="flex gap-1.5 mb-3 flex-wrap">
          <button v-for="c in chips" :key="c.key" @click="chip = c.key; load(1)"
            class="px-3 py-1.5 rounded-full text-xs font-bold border transition-colors"
            :class="chip === c.key ? 'bg-ink text-white border-ink' : 'bg-white text-ink-light border-gray-200 hover:border-gray-300'">{{ c.label }}</button>
        </div>
        <div class="mb-2">
          <span class="font-bold text-amber-700 text-sm">{{ category || '전체' }}</span>
          <span v-if="!category" class="text-xs text-ink-muted ml-2">Awesome Korean이 고른 Amazon 추천 상품을 볼 수 있습니다</span>
        </div>

        <div id="review-list" class="scroll-mt-24"></div>
        <div v-if="loading" class="text-center py-12 text-ink-muted">로딩중...</div>
        <div v-else-if="!products.length" class="py-16 text-center">
          <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="shopping-bag" :size="28" :stroke-width="1.5" /></div>
          <p class="text-sm text-ink-muted">등록된 상품이 없습니다</p>
        </div>
        <div v-else class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          <RouterLink v-for="product in products" :key="product.id" :to="`/shopping/${product.id}`" class="block bg-white overflow-hidden cursor-pointer transition-transform hover:-translate-y-0.5"
            style="border-radius:20px;border:1px solid #f0e7de;box-shadow:0 4px 20px rgba(48,32,16,.06)">
            <div class="relative bg-white overflow-hidden flex items-center justify-center text-gray-300" style="aspect-ratio:1.18">
              <span v-if="product.is_hot" class="absolute top-2 left-2 bg-amber-500 text-white rounded-full px-2 py-0.5 text-[10px] font-black z-10 shadow inline-flex items-center gap-0.5"><AppIcon name="flame" :size="10" />이번주 HOT</span>
              <span v-else-if="product.is_featured" class="absolute top-2 left-2 badge-red !text-[10px] font-bold z-10">추천</span>
              <img v-if="product.image_url" :src="product.image_url" :alt="product.title" loading="lazy" decoding="async" class="w-full h-full object-contain p-3" @error="e=>e.target.style.display='none'" />
              <AppIcon v-else name="shopping-bag" :size="28" :stroke-width="1.5" />
            </div>
            <div class="p-4">
              <span v-if="product.category" class="inline-block text-[11px] font-extrabold px-2 py-1" style="color:#BB5C3C;background:#FFF0E7;border-radius:7px">{{ product.category }}</span>
              <h3 class="text-[16px] font-bold text-ink leading-snug mt-2.5 line-clamp-2">{{ product.title }}</h3>
              <div v-if="product.rating" class="text-xs mt-1.5" style="color:#E99B22">{{ '★'.repeat(product.rating) }}<span class="text-gray-300">{{ '★'.repeat(5 - product.rating) }}</span></div>
              <p class="text-[13px] text-ink-light leading-relaxed mt-1.5 line-clamp-2" style="min-height:42px">{{ plain(product.our_description) }}</p>
              <div class="flex items-center justify-between text-xs text-ink-faint pt-3 mt-3" style="border-top:1px solid #f1e8df">
                <span class="truncate">by {{ product.author?.name || 'Awesome Korean' }}</span>
                <span class="inline-flex items-center gap-1 font-bold flex-shrink-0" style="color:#C85F3D"><AppIcon name="eye" :size="13" />{{ product.view_count || 0 }}<template v-if="product.comment_count"><AppIcon name="message-circle" :size="13" class="ml-1" />{{ product.comment_count }}</template></span>
              </div>
            </div>
          </RouterLink>
        </div>

        <Pagination :page="page" :lastPage="lastPage" @page="load" />

        <!-- 우리끼리 공유해서 더 좋은 이유 -->
        <section v-if="showHero" class="mt-8">
          <h3 class="text-lg font-bold text-ink">우리끼리 공유해서 더 좋은 이유</h3>
          <p class="text-xs text-ink-muted mt-0.5 mb-3">미국에서 생활하는 한국인의 시선으로</p>
          <div class="grid sm:grid-cols-3 gap-3">
            <div class="card p-4"><div class="icon-chip w-9 h-9 bg-amber-50 text-amber-600 mb-2"><AppIcon name="heart-handshake" :size="18" /></div><div class="font-bold text-sm text-ink">한국 생활 방식에 맞는 후기</div><p class="text-xs text-ink-light leading-relaxed mt-1">한국 요리에 써봤어요, 아이 키우는 집에 좋아요 등 우리에게 중요한 정보를 모아요.</p></div>
            <div class="card p-4"><div class="icon-chip w-9 h-9 bg-amber-50 text-amber-600 mb-2"><AppIcon name="coins" :size="18" /></div><div class="font-bold text-sm text-ink">후기가 수익 기회로</div><p class="text-xs text-ink-light leading-relaxed mt-1">자격을 갖춘 작성자는 자신의 제휴 링크를 활용할 수 있어요. 적용 조건과 수익 표시를 명확히 안내합니다.</p></div>
            <div class="card p-4"><div class="icon-chip w-9 h-9 bg-amber-50 text-amber-600 mb-2"><AppIcon name="gift" :size="18" /></div><div class="font-bold text-sm text-ink">포인트와 커뮤니티 혜택</div><p class="text-xs text-ink-light leading-relaxed mt-1">참여형 이벤트와 배지를 통해 즐겁게 후기를 나누세요. 실제 보상 정책은 운영 기준에 따라 결정됩니다.</p></div>
          </div>
        </section>

        <!-- 제휴(Amazon Associates) 안내는 페이지 맨 아래에 -->
        <AffiliateDisclosure class="mt-6" />
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useSiteStore } from '../../stores/site'
import AppIcon from '../../components/AppIcon.vue'
import AffiliateDisclosure from '../../components/AffiliateDisclosure.vue'
import axios from 'axios'

const route = useRoute()
const router = useRouter()
const site = useSiteStore()
// 관리자 > 메뉴 설정에서 바꾼 이름(예: 내돈내산 리뷰)을 화면 제목에도 그대로 사용
const pageTitle = computed(() => site.menuConfig?.find(m => m.key === 'shopping')?.label || '쇼핑')
// 추천 이유는 편집기로 쓴 HTML 이라, 목록 카드에서는 태그를 걷어낸 글자만 보여준다 (DOMParser 는 이미지/스크립트를 실행하지 않음)
const plain = (html) => (new DOMParser().parseFromString(String(html || ''), 'text/html').body.textContent || '').replace(/\s+/g, ' ').trim()
const products = ref([])
const loading = ref(true)
const page = ref(1)
const lastPage = ref(1)
const search = ref('')
const category = ref('')
const chip = ref('all')
const chips = [
  { key: 'all', label: '전체' }, { key: 'hot', label: '이번주 HOT' },
  { key: 'member', label: '회원 리뷰' }, { key: 'official', label: 'Awesome Korean 추천' },
]
function goWrite() { router.push('/shopping/write') }
// 소개 히어로는 필터/검색 없이 첫 페이지를 볼 때만 표시
const showHero = computed(() => !search.value && !category.value && chip.value === 'all' && page.value === 1)
const heroProducts = computed(() => products.value.filter(p => p.image_url).slice(0, 4))
function scrollToList() { document.getElementById('review-list')?.scrollIntoView({ behavior: 'smooth', block: 'start' }) }
const categories = [
  '인기상품', '오늘의 딜', '주방용품', '한국요리 관련 제품', '식품', '생활용품',
  '가전/전자제품', '육아/교육', 'Beauty/K-Beauty', 'Home', '계절상품', '선물', 'Awesome Korean 추천',
]

async function load(p = 1) {
  loading.value = true; page.value = p
  const params = { page: p, per_page: 20 }
  if (search.value) params.search = search.value
  if (category.value) params.category = category.value
  if (chip.value === 'hot') params.sort = 'hot'
  else if (chip.value === 'member') { params.source = 'member'; params.sort = 'latest' }
  else if (chip.value === 'official') params.source = 'official'
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
