<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
      <h1 class="text-xl font-black text-gray-800">🍳 레시피</h1>
      <div class="flex items-center gap-2">
        <form @submit.prevent="loadRecipes()" class="flex gap-1">
          <input v-model="search" type="text" placeholder="검색..." class="border rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-amber-400 outline-none w-40" />
          <button type="submit" class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-amber-500">검색</button>
        </form>
        <RouterLink v-if="auth.isLoggedIn" to="/recipes/write" class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-lg text-sm hover:bg-amber-500">✏️ 등록</RouterLink>
      </div>
    </div>

    <div class="grid grid-cols-12 gap-4">
      <!-- 왼쪽: 카테고리 -->
      <div class="col-span-12 lg:col-span-2 hidden lg:block">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-20">
          <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">📋 카테고리</div>
          <button v-for="c in categories" :key="c.id||c" @click="activeCat=c.id||null; loadRecipes()"
            class="w-full text-left px-3 py-2 text-xs transition"
            :class="activeCat===(c.id||null) ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">{{ c.name || '전체' }}</button>
        </div>
      </div>

      <div class="col-span-12 lg:col-span-7">
        <div class="mb-2">
          <span class="font-bold text-amber-700 text-sm">{{ activeCat ? (categories.find(c => c.id === activeCat)?.name || activeCat) : '전체' }}</span>
          <span v-if="!activeCat" class="text-xs text-gray-400 ml-2">모든 레시피를 볼 수 있습니다</span>
        </div>
        <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
        <div v-else-if="!items.length" class="text-center py-12 text-gray-400">레시피가 없습니다</div>

        <!-- 카드형 -->
        <div v-else-if="viewMode==='card'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <RouterLink v-for="item in items" :key="item.id" :to="'/recipes/' + item.id"
            class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 hover:shadow-md hover:-translate-y-0.5 transition-all">
            <div class="flex items-center gap-3 mb-3">
              <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center text-2xl">🍳</div>
              <div class="flex-1 min-w-0">
                <div class="text-sm font-bold text-gray-800 truncate">{{ item.title }}</div>
                <div class="text-[10px] text-gray-400">{{ item.category?.name || '기타' }} · <UserName :userId="item.user?.id" :name="item.user?.name" className="text-[10px] text-gray-400 inline" /></div>
              </div>
            </div>
            <div class="text-xs text-gray-500 line-clamp-2 mb-2">{{ (item.content || '').slice(0, 80) }}</div>
            <div class="flex items-center justify-between text-[10px] text-gray-400">
              <div class="flex gap-2">
                <span v-if="item.cook_time">⏱ {{ item.cook_time }}분</span>
                <span v-if="item.difficulty">📊 {{ item.difficulty }}</span>
                <span v-if="item.servings">👥 {{ item.servings }}인분</span>
              </div>
              <span>👁 {{ item.view_count || 0 }}</span>
            </div>
          </RouterLink>
        </div>

        <!-- 리스트형 -->
        <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <RouterLink v-for="item in items" :key="item.id" :to="'/recipes/' + item.id"
            class="block px-4 py-3 border-b border-gray-50 hover:bg-amber-50/50 transition">
            <div class="text-sm font-medium text-gray-800">{{ item.title }}</div>
            <div class="text-xs text-gray-400 mt-1"><UserName :userId="item.user?.id" :name="item.user?.name" className="text-xs text-gray-400 inline" /> · {{ item.category?.name || '' }} · {{ item.view_count || 0 }}조회</div>
          </RouterLink>
        </div>

        <div v-if="lastPage > 1" class="flex justify-center gap-1.5 mt-4">
          <button v-for="pg in Math.min(lastPage, 10)" :key="pg" @click="loadRecipes(pg)"
            class="px-3 py-1 rounded text-sm" :class="pg===page?'bg-amber-400 text-amber-900 font-bold':'bg-white text-gray-600 border hover:bg-amber-50'">{{ pg }}</button>
        </div>
      </div>

      <!-- 오른쪽 위젯 -->
      <div class="col-span-12 lg:col-span-3 hidden lg:block">
        <SidebarWidgets api-url="/api/recipes" detail-path="/recipes/" :current-id="0"
          label="레시피" />
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { useRoute } from 'vue-router'
import { ref, onMounted } from 'vue'
import { useAuthStore } from '../../stores/auth'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import { useMenuConfig } from '../../composables/useMenuConfig'
import axios from 'axios'
const auth = useAuthStore()
const route = useRoute()
const items = ref([])
const categories = ref([{ name: '전체', id: null }])
const activeCat = ref(null)
const loading = ref(true)
const { loadConfig, getDefaultView } = useMenuConfig()
const viewMode = ref('list')
const search = ref('')
const page = ref(1)
const lastPage = ref(1)

async function loadRecipes(p = 1) {
  loading.value = true; page.value = p
  const params = { page: p, per_page: 20 }
  if (search.value) params.search = search.value
  if (activeCat.value) params.category_id = activeCat.value
  try {
    const { data } = await axios.get('/api/recipes', { params })
    items.value = data.data?.data || []
    lastPage.value = data.data?.last_page || 1
  } catch {}
  loading.value = false
}

onMounted(async () => {
  await loadConfig(); viewMode.value = getDefaultView('recipes')
  try { const { data } = await axios.get('/api/recipes/categories'); categories.value = [{ name: '전체', id: null }, ...(data.data || [])] } catch {}
  loadRecipes()
})
</script>
