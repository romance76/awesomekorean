<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <!-- 헤더 -->
    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
      <h1 class="text-xl font-black text-gray-800">🍳 레시피</h1>
      <div class="flex items-center gap-2 flex-wrap">
        <form @submit.prevent="onSearch" class="flex gap-1">
          <input v-model="search" type="text" placeholder="검색..." class="border rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-amber-400 outline-none w-40" />
          <button type="submit" class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-amber-500">검색</button>
        </form>
        <RouterLink v-if="auth.isLoggedIn" to="/recipes/write" class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-amber-500">✏️ 내 레시피 등록</RouterLink>
      </div>
    </div>

    <div class="grid grid-cols-12 gap-4">
      <!-- 왼쪽: 카테고리 -->
      <div class="col-span-12 lg:col-span-2 hidden lg:block">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-20">
          <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">📋 분류</div>
          <button @click="selectCategory('', false)"
            class="w-full text-left px-3 py-2 text-xs transition"
            :class="!showFavorites && activeCat === '' ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">
            전체
          </button>
          <button v-for="c in categories" :key="c.category" @click="selectCategory(c.category, false)"
            class="w-full text-left px-3 py-2 text-xs transition"
            :class="!showFavorites && activeCat === c.category ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">
            {{ c.category }} <span class="text-[9px] text-gray-400">({{ c.count }})</span>
          </button>
          <!-- 찜한 레시피 (로그인 시) -->
          <button v-if="auth.isLoggedIn" @click="selectFavorites"
            class="w-full text-left px-3 py-2 text-xs transition border-t"
            :class="showFavorites ? 'bg-red-50 text-red-600 font-bold' : 'text-gray-600 hover:bg-red-50/50'">
            💖 찜한 레시피
          </button>
        </div>
      </div>

      <!-- 중앙: 리스트 -->
      <div class="col-span-12 lg:col-span-7">
        <!-- 모바일 카테고리 -->
        <div class="lg:hidden mb-3 flex gap-2">
          <select :value="activeCat" @change="e => selectCategory(e.target.value, false)" class="flex-1 border rounded-lg px-3 py-2 text-sm">
            <option value="">전체</option>
            <option v-for="c in categories" :key="c.category" :value="c.category">{{ c.category }} ({{ c.count }})</option>
          </select>
          <button v-if="auth.isLoggedIn" @click="selectFavorites"
            class="px-3 py-2 rounded-lg text-xs font-bold"
            :class="showFavorites ? 'bg-red-500 text-white' : 'bg-white border'">
            💖 찜
          </button>
        </div>
        <div class="mb-2 flex items-center justify-between">
          <div>
            <span v-if="showFavorites" class="font-bold text-red-600 text-sm">💖 찜한 레시피</span>
            <template v-else>
              <span class="font-bold text-amber-700 text-sm">{{ activeCat || '전체' }}</span>
              <span v-if="sort === 'random'" class="text-xs text-gray-400 ml-2">랜덤 순서</span>
            </template>
          </div>
          <!-- 정렬 (찜 탭 아닐 때만) -->
          <div v-if="!showFavorites" class="flex gap-1 items-center">
            <button v-if="sort === 'random'" @click="reshuffle" class="text-xs bg-amber-100 text-amber-700 px-2 py-1 rounded-full font-bold hover:bg-amber-200" title="새로 섞기">🔀 섞기</button>
            <select v-model="sort" @change="onSortChange" class="border rounded-lg px-2 py-1 text-xs outline-none">
              <option value="random">🎲 랜덤 (기본)</option>
              <option value="rating">⭐ 별점 높은 순</option>
              <option value="popular">👁 많이 본 순</option>
              <option value="latest">🕐 최신 순</option>
            </select>
          </div>
        </div>

        <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
        <div v-else-if="!items.length" class="text-center py-12">
          <div class="text-4xl mb-3">{{ showFavorites ? '💖' : '🍳' }}</div>
          <div class="text-gray-500 font-semibold">{{ showFavorites ? '찜한 레시피가 없습니다' : '검색 결과가 없습니다' }}</div>
        </div>

        <!-- 카드 그리드 -->
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <RouterLink v-for="item in items" :key="item.id" :to="'/recipes/' + item.id"
            class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md hover:-translate-y-0.5 transition-all flex h-32 relative">
            <!-- 왼쪽: 사진 (썸네일 프록시) -->
            <div class="w-28 flex-shrink-0 bg-gray-100 relative">
              <img v-if="item.thumbnail_url || item.thumbnail" :src="item.thumbnail_url || thumb(item.thumbnail, 240)" loading="lazy" decoding="async" class="w-full h-full object-cover"
                @error="e => e.target.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center text-3xl bg-amber-50\'>🍲</div>'" />
              <div v-else class="w-full h-full flex items-center justify-center text-3xl bg-amber-50">🍲</div>
            </div>
            <!-- 오른쪽: 정보 -->
            <div class="flex-1 p-3 min-w-0">
              <div class="text-sm font-bold text-gray-800 truncate">{{ item.title }}</div>
              <!-- 별점 -->
              <div class="flex items-center gap-1 mt-0.5">
                <span class="text-amber-400 text-[11px]">{{ '★'.repeat(Math.round(Number(item.rating_avg) || 0)) }}{{ '☆'.repeat(5 - Math.round(Number(item.rating_avg) || 0)) }}</span>
                <span class="text-[10px] text-gray-500">{{ Number(item.rating_avg || 0).toFixed(1) }}</span>
                <span class="text-[9px] text-gray-400">({{ item.rating_count || 0 }})</span>
              </div>
              <div class="text-[10px] text-gray-400 mt-0.5">
                <span v-if="item.category" class="inline-block bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full font-semibold mr-1">{{ item.category }}</span>
                <span v-if="item.cook_method">{{ item.cook_method }}</span>
              </div>
              <div class="text-[10px] text-gray-500 mt-1 flex items-center gap-2 flex-wrap">
                <span v-if="item.calories">🔥 {{ item.calories }}kcal</span>
                <span v-if="item.protein">🥩 {{ item.protein }}g</span>
              </div>
              <div class="text-[10px] text-gray-400 mt-0.5 flex items-center gap-2">
                <span>👁 {{ item.view_count || 0 }}</span>
                <span v-if="item.favorite_count">💖 {{ item.favorite_count }}</span>
              </div>
            </div>
            <!-- 찜 표시 (로그인 + 이미 찜한 경우) -->
            <span v-if="item.is_favorited" class="absolute top-1.5 right-1.5 text-red-500 text-base">❤️</span>
          </RouterLink>
        </div>

        <!-- 페이지네이션 -->
        <div v-if="lastPage > 1" class="flex justify-center gap-1.5 mt-4">
          <button v-for="pg in Math.min(lastPage, 10)" :key="pg" @click="loadPage(pg)"
            class="px-3 py-1 rounded text-sm" :class="pg === page ? 'bg-amber-400 text-amber-900 font-bold' : 'bg-white text-gray-600 border hover:bg-amber-50'">{{ pg }}</button>
        </div>
      </div>

      <!-- 오른쪽: 사이드바 위젯 -->
      <div class="col-span-12 lg:col-span-3 hidden lg:block">
        <SidebarWidgets api-url="/api/recipes" detail-path="/recipes/" :current-id="0"
          label="레시피"
          :second-tab="{ label: '⭐ 별점 순', sort: 'rating' }" />
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import { thumb } from '../../utils/thumb'
import axios from 'axios'

const auth = useAuthStore()
const route = useRoute()
const items = ref([])
const categories = ref([])
const activeCat = ref('')
const search = ref('')
const sort = ref('random')
const page = ref(1)
const lastPage = ref(1)
const loading = ref(true)
const showFavorites = ref(false)
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

onMounted(() => {
  loadCategories()
  if (route.query.category) activeCat.value = route.query.category
  if (route.query.favorites === '1' && auth.isLoggedIn) showFavorites.value = true
  loadPage()
})
</script>
