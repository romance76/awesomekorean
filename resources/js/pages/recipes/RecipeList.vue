<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <!-- 헤더 -->
    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
      <h1 class="text-xl font-black text-gray-800">🍳 레시피</h1>
      <div class="flex items-center gap-2 flex-wrap">
        <form @submit.prevent="loadPage(1)" class="flex gap-1">
          <input v-model="search" type="text" placeholder="검색..." class="border rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-amber-400 outline-none w-40" />
          <button type="submit" class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-amber-500">검색</button>
        </form>
      </div>
    </div>

    <div class="grid grid-cols-12 gap-4">
      <!-- 왼쪽: 카테고리 -->
      <div class="col-span-12 lg:col-span-2 hidden lg:block">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-20">
          <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">📋 분류</div>
          <button @click="selectCategory('')"
            class="w-full text-left px-3 py-2 text-xs transition"
            :class="activeCat === '' ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">
            전체
          </button>
          <button v-for="c in categories" :key="c.category" @click="selectCategory(c.category)"
            class="w-full text-left px-3 py-2 text-xs transition"
            :class="activeCat === c.category ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">
            {{ c.category }} <span class="text-[9px] text-gray-400">({{ c.count }})</span>
          </button>
        </div>
      </div>

      <!-- 중앙: 리스트 -->
      <div class="col-span-12 lg:col-span-7">
        <!-- 모바일 카테고리 -->
        <div class="lg:hidden mb-3">
          <select :value="activeCat" @change="e => selectCategory(e.target.value)" class="w-full border rounded-lg px-3 py-2 text-sm">
            <option value="">전체</option>
            <option v-for="c in categories" :key="c.category" :value="c.category">{{ c.category }} ({{ c.count }})</option>
          </select>
        </div>
        <div class="mb-2">
          <span class="font-bold text-amber-700 text-sm">{{ activeCat || '전체' }}</span>
          <span v-if="!activeCat" class="text-xs text-gray-400 ml-2">모든 레시피를 볼 수 있습니다</span>
        </div>

        <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
        <div v-else-if="!items.length" class="text-center py-12">
          <div class="text-4xl mb-3">🍳</div>
          <div class="text-gray-500 font-semibold">검색 결과가 없습니다</div>
        </div>

        <!-- 카드형 (업소록과 동일한 수평 카드) -->
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <RouterLink v-for="item in items" :key="item.id" :to="'/recipes/' + item.id"
            class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md hover:-translate-y-0.5 transition-all flex h-32">
            <!-- 왼쪽: 사진 -->
            <div class="w-28 flex-shrink-0 bg-gray-100">
              <img v-if="item.thumbnail" :src="item.thumbnail" class="w-full h-full object-cover"
                @error="e => e.target.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center text-3xl bg-amber-50\'>🍲</div>'" />
              <div v-else class="w-full h-full flex items-center justify-center text-3xl bg-amber-50">🍲</div>
            </div>
            <!-- 오른쪽: 정보 -->
            <div class="flex-1 p-3 min-w-0">
              <div class="text-sm font-bold text-gray-800 truncate">{{ item.title }}</div>
              <div class="text-[10px] text-gray-400 mt-1">
                <span v-if="item.category" class="inline-block bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full font-semibold mr-1">{{ item.category }}</span>
                <span v-if="item.cook_method">{{ item.cook_method }}</span>
              </div>
              <div class="text-[10px] text-gray-500 mt-2 flex items-center gap-2 flex-wrap">
                <span v-if="item.calories">🔥 {{ item.calories }}kcal</span>
                <span v-if="item.protein">🥩 {{ item.protein }}g</span>
              </div>
              <div class="text-[10px] text-gray-400 mt-1">👁 {{ item.view_count || 0 }}</div>
            </div>
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
          label="레시피" />
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import axios from 'axios'

const items = ref([])
const categories = ref([])
const activeCat = ref('')
const search = ref('')
const page = ref(1)
const lastPage = ref(1)
const loading = ref(true)

async function loadPage(p = 1) {
  loading.value = true
  page.value = p
  const params = { page: p, per_page: 20 }
  if (search.value) params.search = search.value
  if (activeCat.value) params.category = activeCat.value
  try {
    const { data } = await axios.get('/api/recipes', { params })
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

function selectCategory(cat) {
  activeCat.value = cat
  loadPage(1)
}

onMounted(() => {
  loadCategories()
  loadPage()
})
</script>
