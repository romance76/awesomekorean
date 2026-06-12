<template>
<div class="min-h-screen">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <!-- 헤더: 모바일 -->
    <div class="lg:hidden mb-3">
      <div class="flex items-center justify-between mb-2">
        <h1 class="flex items-center gap-2 text-lg font-bold text-ink">
          <span class="icon-chip w-8 h-8 bg-orange-50 text-orange-600"><AppIcon name="utensils" :size="17" /></span>
          레시피
        </h1>
        <div class="flex items-center gap-2">
          <button @click="showFilter = true" class="btn-secondary px-3 py-2 rounded-lg text-xs"><AppIcon name="filter" :size="13" />필터</button>
          <RouterLink v-if="auth.isLoggedIn" to="/recipes/write" class="btn-primary px-3 py-2 rounded-lg text-xs"><AppIcon name="edit" :size="13" />등록</RouterLink>
        </div>
      </div>
      <div class="flex items-center gap-1.5 overflow-x-auto">
        <span v-if="search" class="badge-gray">
          "{{ search }}"
        </span>
        <span class="badge-gray">
          {{ {random:'랜덤',rating:'별점순',popular:'인기순',latest:'최신순'}[sort] }}
        </span>
      </div>
    </div>

    <!-- 모바일 필터 바텀시트 -->
    <MobileFilter v-model="showFilter" @apply="loadPage()" @reset="activeCat = ''; search = ''; sort = 'random'; loadPage()">
      <div class="mb-4">
        <label class="input-label">검색어</label>
        <input v-model="search" type="text" placeholder="검색어 입력..."
          class="input-soft" />
      </div>
      <div class="mb-4">
        <label class="input-label">카테고리</label>
        <div class="grid grid-cols-3 gap-1.5">
          <button @click="activeCat = ''"
            class="text-xs py-2 rounded-lg font-semibold border transition-colors"
            :class="activeCat === '' ? 'bg-amber-50 text-amber-700 border-amber-300' : 'border-gray-200 text-ink-light hover:bg-gray-50'">
            전체
          </button>
          <button v-for="c in categories" :key="c.category" @click="activeCat = c.category"
            class="text-xs py-2 rounded-lg font-semibold border transition-colors"
            :class="activeCat === c.category ? 'bg-amber-50 text-amber-700 border-amber-300' : 'border-gray-200 text-ink-light hover:bg-gray-50'">
            {{ c.category }}
          </button>
        </div>
      </div>
      <div>
        <label class="input-label">정렬</label>
        <div class="grid grid-cols-2 gap-1.5">
          <button @click="sort = 'random'" class="text-xs py-2 rounded-lg font-semibold border transition-colors inline-flex items-center justify-center gap-1"
            :class="sort === 'random' ? 'bg-amber-50 text-amber-700 border-amber-300' : 'border-gray-200 text-ink-light hover:bg-gray-50'"><AppIcon name="refresh" :size="12" />랜덤</button>
          <button @click="sort = 'rating'" class="text-xs py-2 rounded-lg font-semibold border transition-colors inline-flex items-center justify-center gap-1"
            :class="sort === 'rating' ? 'bg-amber-50 text-amber-700 border-amber-300' : 'border-gray-200 text-ink-light hover:bg-gray-50'"><AppIcon name="star" :size="12" />별점순</button>
          <button @click="sort = 'popular'" class="text-xs py-2 rounded-lg font-semibold border transition-colors inline-flex items-center justify-center gap-1"
            :class="sort === 'popular' ? 'bg-amber-50 text-amber-700 border-amber-300' : 'border-gray-200 text-ink-light hover:bg-gray-50'"><AppIcon name="eye" :size="12" />인기순</button>
          <button @click="sort = 'latest'" class="text-xs py-2 rounded-lg font-semibold border transition-colors inline-flex items-center justify-center gap-1"
            :class="sort === 'latest' ? 'bg-amber-50 text-amber-700 border-amber-300' : 'border-gray-200 text-ink-light hover:bg-gray-50'"><AppIcon name="clock" :size="12" />최신순</button>
        </div>
      </div>
    </MobileFilter>

    <!-- 헤더: 데스크탑 -->
    <div class="hidden lg:flex items-center justify-between mb-4 flex-wrap gap-2">
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
        <span class="icon-chip w-9 h-9 bg-orange-50 text-orange-600"><AppIcon name="utensils" :size="20" /></span>
        레시피
      </h1>
      <div class="flex items-center gap-2 flex-wrap">
        <form @submit.prevent="onSearch" class="flex gap-1">
          <input v-model="search" type="text" placeholder="검색..." class="input-soft w-40 py-1.5 text-sm" />
          <button type="submit" class="btn-primary px-3 py-1.5 text-xs">검색</button>
        </form>
        <RouterLink v-if="auth.isLoggedIn" to="/recipes/write" class="btn-primary px-3 py-1.5 text-xs"><AppIcon name="edit" :size="13" />내 레시피 등록</RouterLink>
      </div>
    </div>

    <div class="grid grid-cols-12 gap-4">
      <!-- 왼쪽: 카테고리 + 광고 (함께 sticky, 뷰포트 초과시 스크롤) -->
      <div class="col-span-12 lg:col-span-2 hidden lg:block">
        <div class="sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto space-y-3 pr-0.5">
          <div class="card overflow-hidden">
            <div class="px-3 py-2.5 border-b border-gray-50 font-bold text-xs text-ink flex items-center gap-1.5"><AppIcon name="list" :size="13" class="text-orange-600" />분류</div>
            <button @click="selectCategory('', false)"
              class="w-full text-left px-3 py-2 text-xs transition-colors"
              :class="!showFavorites && activeCat === '' ? 'bg-amber-50 text-amber-700 font-bold' : 'text-ink-light hover:bg-amber-50/50'">
              전체
            </button>
            <button v-for="c in categories" :key="c.category" @click="selectCategory(c.category, false)"
              class="w-full text-left px-3 py-2 text-xs transition-colors"
              :class="!showFavorites && activeCat === c.category ? 'bg-amber-50 text-amber-700 font-bold' : 'text-ink-light hover:bg-amber-50/50'">
              {{ c.category }} <span class="text-[11px] text-ink-faint">({{ c.count }})</span>
            </button>
            <button v-if="auth.isLoggedIn" @click="selectFavorites"
              class="w-full text-left px-3 py-2 text-xs transition-colors border-t border-gray-50 flex items-center gap-1"
              :class="showFavorites ? 'bg-red-50 text-red-600 font-bold' : 'text-ink-light hover:bg-red-50/50'">
              <AppIcon name="heart" :size="12" />내 하트<span v-if="favCount > 0" class="ml-0.5">({{ favCount }})</span>
            </button>
          </div>
          <AdSlot page="recipes" position="left" :maxSlots="2" />
        </div>
      </div>

      <!-- 중앙: 리스트 -->
      <div class="col-span-12 lg:col-span-7">
        <div class="mb-2 flex items-center justify-between">
          <div>
            <span v-if="showFavorites" class="font-bold text-red-600 text-sm inline-flex items-center gap-1"><AppIcon name="heart" :size="14" />내 하트</span>
            <template v-else>
              <span class="font-bold text-amber-700 text-sm">{{ activeCat || '전체' }}</span>
              <span v-if="sort === 'random'" class="text-xs text-ink-muted ml-2">랜덤 순서</span>
            </template>
          </div>
          <!-- 정렬 (찜 탭 아닐 때만) -->
          <div v-if="!showFavorites" class="flex gap-1 items-center">
            <button v-if="sort === 'random'" @click="reshuffle" class="btn-soft px-2.5 py-1 text-xs rounded-full font-bold" title="새로 섞기"><AppIcon name="refresh" :size="12" />섞기</button>
            <select v-model="sort" @change="onSortChange" class="input-soft w-auto pl-2.5 pr-8 py-1 text-xs">
              <option value="random">🎲 랜덤 (기본)</option>
              <option value="rating">⭐ 별점 높은 순</option>
              <option value="popular">👁 많이 본 순</option>
              <option value="latest">🕐 최신 순</option>
            </select>
          </div>
        </div>

        <div v-if="loading" class="text-center py-12 text-ink-muted">로딩중...</div>
        <div v-else-if="!items.length" class="py-16 text-center">
          <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon :name="showFavorites ? 'heart' : 'utensils'" :size="28" :stroke-width="1.5" /></div>
          <p class="text-sm text-ink-muted">{{ showFavorites ? '하트한 레시피가 없습니다' : '검색 결과가 없습니다' }}</p>
        </div>

        <!-- 카드 그리드 -->
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <template v-for="(item, i) in items" :key="item.id">
          <RouterLink :to="'/recipes/' + item.id"
            class="card card-hover overflow-hidden flex h-32 relative">
            <!-- 왼쪽: 사진 (썸네일 프록시) -->
            <div class="w-28 flex-shrink-0 bg-gray-100 relative">
              <img v-if="item.thumbnail_url || item.thumbnail" :src="item.thumbnail_url || thumb(item.thumbnail, 240)" loading="lazy" decoding="async" class="w-full h-full object-cover"
                @error="e => e.target.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center bg-gray-100 text-gray-300\'><svg width=\'28\' height=\'28\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.5\' stroke-linecap=\'round\' stroke-linejoin=\'round\'><rect x=\'3\' y=\'3\' width=\'18\' height=\'18\' rx=\'2\'/><circle cx=\'8.5\' cy=\'8.5\' r=\'1.5\'/><polyline points=\'21 15 16 10 5 21\'/></svg></div>'" />
              <div v-else class="w-full h-full flex items-center justify-center bg-gray-100 text-gray-300"><AppIcon name="utensils" :size="28" :stroke-width="1.5" /></div>
            </div>
            <!-- 오른쪽: 정보 -->
            <div class="flex-1 p-3 min-w-0">
              <div class="text-sm font-semibold text-ink truncate">{{ item.title }}</div>
              <!-- 별점 -->
              <div class="flex items-center gap-1 mt-0.5">
                <span class="text-amber-400 text-[11px]">{{ '★'.repeat(Math.round(Number(item.rating_avg) || 0)) }}{{ '☆'.repeat(5 - Math.round(Number(item.rating_avg) || 0)) }}</span>
                <span class="text-[11px] text-ink-muted">{{ Number(item.rating_avg || 0).toFixed(1) }}</span>
                <span class="text-[11px] text-ink-faint">({{ item.rating_count || 0 }})</span>
              </div>
              <div class="text-[11px] text-ink-muted mt-0.5">
                <span v-if="!activeCat && item.category" class="badge-primary !px-1.5 mr-1">{{ item.category }}</span>
                <span v-if="item.cook_method">{{ item.cook_method }}</span>
              </div>
              <div class="text-[11px] text-ink-muted mt-1 flex items-center gap-2 flex-wrap">
                <span v-if="item.calories">🔥 {{ item.calories }}kcal</span>
                <span v-if="item.protein">🥩 {{ item.protein }}g</span>
              </div>
              <div class="text-[11px] text-ink-faint mt-0.5 flex items-center gap-2">
                <span class="inline-flex items-center gap-0.5"><AppIcon name="eye" :size="11" />{{ item.view_count || 0 }}</span>
                <span v-if="item.favorite_count" class="inline-flex items-center gap-0.5 text-red-400"><AppIcon name="heart" :size="11" />{{ item.favorite_count }}</span>
                <BookmarkToggle v-if="auth.isLoggedIn" :active="recipeFavorited.has(item.id)" @toggle="toggleFav(item)" size="sm" class="ml-auto" />
              </div>
            </div>
          </RouterLink>
          <MobileAdInline v-if="i === 4" page="recipes" />
          </template>
        </div>

        <!-- 페이지네이션 -->
        <Pagination :page="page" :lastPage="lastPage" @page="loadPage" />
      </div>

      <!-- 오른쪽: 사이드바 위젯 -->
      <div class="col-span-12 lg:col-span-3 hidden lg:block">
        <SidebarWidgets :currentCategory="activeCat" api-url="/api/recipes" detail-path="/recipes/" :current-id="0"
          label="레시피"
          :second-tab="{ label: '⭐ 별점 순', sort: 'rating' }" />
        <AdSlot page="recipes" position="right" :maxSlots="2" />
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useBookmarkStore } from '../../stores/bookmarks'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import { thumb } from '../../utils/thumb'
import axios from 'axios'
import AdSlot from '../../components/AdSlot.vue'
import BookmarkToggle from '../../components/BookmarkToggle.vue'
import AppIcon from '../../components/AppIcon.vue'

const auth = useAuthStore()
const bStore = useBookmarkStore()
const BM_TYPE = 'App\\Models\\RecipePost'
const route = useRoute()
const showFilter = ref(false)
const items = ref([])
const categories = ref([])
const activeCat = ref('')
const search = ref('')
const sort = ref('random')
const page = ref(1)
const lastPage = ref(1)
const loading = ref(true)
const showFavorites = ref(false)
const recipeFavorited = ref(new Set())
const favCount = computed(() => bStore.getBookmarkedIds(BM_TYPE).length)
const randomSeed = ref(Math.floor(Math.random() * 999999) + 1)

function newSeed() {
  randomSeed.value = Math.floor(Math.random() * 999999) + 1
}

function reshuffle() {
  newSeed()
  loadPage(1)
}

function onSortChange() {
  if (sort.value === 'random') newSeed()
  loadPage(1)
}

function onSearch() {
  if (sort.value === 'random') newSeed()
  loadPage(1)
}

async function loadPage(p = 1) {
  loading.value = true
  page.value = p

  const url = showFavorites.value ? '/api/recipes/my/favorites' : '/api/recipes'
  const params = { page: p, per_page: 20 }
  if (!showFavorites.value) {
    if (search.value) params.search = search.value
    if (activeCat.value) params.category = activeCat.value
    if (sort.value) params.sort = sort.value
    // 랜덤 정렬일 때는 seed 를 전달해야 페이지 넘어가도 같은 셔플 유지
    if (sort.value === 'random') params.seed = randomSeed.value
  }

  try {
    const { data } = await axios.get(url, { params })
    items.value = data.data?.data || []
    lastPage.value = data.data?.last_page || 1
  } catch {}
  loading.value = false
  loadRecipeFavorited()
}

// 리스트 하트 토글
async function loadRecipeFavorited() {
  if (!auth.isLoggedIn || !items.value.length) return
  try {
    const ids = items.value.map(i => i.id).join(',')
    const { data } = await axios.get('/api/bookmarks/check', { params: { type: 'App\\Models\\RecipePost', ids } })
    recipeFavorited.value = new Set(data.data || [])
  } catch {
    // fallback: is_favorited 필드 사용
    const set = new Set()
    items.value.forEach(i => { if (i.is_favorited) set.add(i.id) })
    recipeFavorited.value = set
  }
}
async function toggleFav(item) {
  if (!auth.isLoggedIn) return
  const result = await bStore.toggle(BM_TYPE, item.id)
  if (result !== null) {
    if (result) recipeFavorited.value.add(item.id)
    else recipeFavorited.value.delete(item.id)
    recipeFavorited.value = new Set(recipeFavorited.value)
  }
}

async function loadCategories() {
  try {
    const { data } = await axios.get('/api/recipes/categories')
    categories.value = data.data || []
  } catch {}
}

function selectCategory(cat, fav) {
  showFavorites.value = fav
  activeCat.value = cat
  if (sort.value === 'random') newSeed() // 카테고리 바뀔 때마다 새 셔플
  loadPage(1)
}

function selectFavorites() {
  showFavorites.value = true
  activeCat.value = ''
  loadPage(1)
}

watch(() => route.query.category, (newCat) => {
  if (newCat !== undefined) {
    showFavorites.value = false
    activeCat.value = newCat || ''
    if (sort.value === 'random') newSeed()
    loadPage(1)
  }
})

// URL 쿼리 변경 시 카테고리 반영
watch(() => route.query, (q) => {
  if (route.path !== '/recipes') return
  if (q.category !== undefined) activeCat.value = q.category || ''
  loadPage()
})

onMounted(() => {
  bStore.loadAll()
  loadCategories()
  if (route.query.category) activeCat.value = route.query.category
  if (route.query.favorites === '1' && auth.isLoggedIn) showFavorites.value = true
  loadPage()
})
</script>
