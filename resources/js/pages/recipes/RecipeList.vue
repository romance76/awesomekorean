<template>
  <div class="min-h-screen bg-gray-50 pb-16">
    <div class="max-w-[1200px] mx-auto px-4 pt-4">
      <!-- 헤더 -->
      <div class="bg-gradient-to-r from-orange-500 to-amber-400 text-white px-6 py-5 rounded-2xl mb-4">
        <h1 class="text-xl font-black">🍳 레시피</h1>
        <p class="text-orange-100 text-sm mt-0.5">미국 한인들의 요리 레시피 모음</p>
      </div>

      <!-- 필터 바 -->
      <div class="bg-white rounded-2xl shadow-sm p-3 mb-4">
        <div class="flex overflow-x-auto gap-2 pb-2 mb-3">
          <button @click="activeCategory = ''; loadRecipes()" :class="catTabClass('')" class="flex-shrink-0 px-3 py-1.5 rounded-full text-sm font-medium transition">
            전체
          </button>
          <button v-for="cat in categories" :key="cat.id"
            @click="activeCategory = cat.key; loadRecipes()"
            :class="catTabClass(cat.key)" class="flex-shrink-0 px-3 py-1.5 rounded-full text-sm font-medium transition">
            {{ cat.icon }} {{ cat.name }}
          </button>
        </div>
        <div class="flex gap-2">
          <input v-model="search" @keyup.enter="loadRecipes" type="text" placeholder="재료나 음식명 검색..."
            class="flex-1 min-w-0 border border-gray-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:border-orange-400" />
          <select v-model="difficulty" @change="loadRecipes"
            class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-orange-400">
            <option value="">난이도 전체</option>
            <option value="쉬움">쉬움</option>
            <option value="보통">보통</option>
            <option value="어려움">어려움</option>
          </select>
          <button @click="loadRecipes" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-xl text-sm font-medium transition">검색</button>
        </div>
      </div>

      <!-- 레시피 그리드 -->
      <div v-if="loading" class="text-center py-16 text-gray-400">불러오는 중...</div>
      <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
        <div v-for="recipe in recipes" :key="recipe.id"
          @click="$router.push('/recipes/' + recipe.id)"
          class="bg-white rounded-2xl shadow-sm overflow-hidden cursor-pointer hover:shadow-md transition group">
          <!-- 이미지 -->
          <div class="relative bg-gray-100 overflow-hidden" style="aspect-ratio:4/3">
            <img v-if="recipe.image_url" :src="recipe.image_url" :alt="recipe.title_ko || recipe.title"
              class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
              @error="recipe.image_url = null" />
            <div v-else class="w-full h-full flex items-center justify-center text-5xl bg-gradient-to-br from-orange-100 to-amber-50">🍽️</div>
            <!-- 난이도 배지 -->
            <span :class="difficultyClass(recipe.difficulty)" class="absolute top-2 right-2 text-xs px-2 py-0.5 rounded-full font-medium">
              {{ recipe.difficulty }}
            </span>
          </div>
          <div class="p-3">
            <h3 class="font-semibold text-gray-800 text-sm leading-snug line-clamp-2 mb-2">{{ recipe.title_ko || recipe.title }}</h3>
            <div class="flex items-center justify-between text-xs text-gray-400">
              <span>⏱ {{ recipe.cook_time }}</span>
              <span>❤️ {{ recipe.like_count || 0 }}</span>
            </div>
          </div>
        </div>
        <div v-if="recipes.length === 0" class="col-span-4 text-center py-16 text-gray-400">
          <div class="text-4xl mb-3">🍽️</div>
          <div>레시피가 없습니다</div>
        </div>
      </div>

      <!-- 페이지네이션 -->
      <div v-if="totalPages > 1" class="flex justify-center gap-1 mt-6">
        <button v-for="p in Math.min(totalPages, 10)" :key="p" @click="page = p; loadRecipes()"
          :class="['px-3 py-1.5 rounded-lg text-sm font-medium transition',
 page === p ? 'bg-orange-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">
          {{ p }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const categories = ref([])
const recipes = ref([])
const loading = ref(false)
const activeCategory = ref('')
const search = ref('')
const difficulty = ref('')
const page = ref(1)
const totalPages = ref(1)

function catTabClass(key) {
  return activeCategory.value === key ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
}
function difficultyClass(d) {
  if (d === '쉬움') return 'bg-green-100 text-green-700'
  if (d === '어려움') return 'bg-red-100 text-red-700'
  return 'bg-yellow-100 text-yellow-700'
}

async function loadCategories() {
  try {
    const { data } = await axios.get('/api/recipes/categories')
    categories.value = data
  } catch {}
}

async function loadRecipes() {
  loading.value = true
  try {
    const params = { page: page.value }
    if (activeCategory.value) params.category = activeCategory.value
    if (search.value) params.search = search.value
    if (difficulty.value) params.difficulty = difficulty.value
    const { data } = await axios.get('/api/recipes', { params })
    recipes.value = data.data || data
    totalPages.value = data.last_page || 1
  } catch { recipes.value = [] }
  finally { loading.value = false }
}

onMounted(async () => {
  await loadCategories()
  await loadRecipes()
})
</script>
