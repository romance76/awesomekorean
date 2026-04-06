<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-xl font-black text-gray-800">🛒 중고장터</h1>
      <div class="flex items-center gap-2">
        <!-- 위치 필터 -->
        <div class="flex items-center gap-1 bg-white border border-gray-200 rounded-lg px-3 py-1.5 text-xs">
          <span class="text-amber-600">📍</span>
          <span class="text-gray-600 font-semibold">{{ locationText }}</span>
          <select v-model="radius" @change="loadPage()" class="border-0 bg-transparent text-xs text-gray-500 outline-none ml-1 pr-1">
            <option value="10">10mi</option>
            <option value="30">30mi</option>
            <option value="50">50mi</option>
            <option value="100">100mi</option>
            <option value="0">전국</option>
          </select>
        </div>
      </div>
    </div>

    <!-- 검색 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 mb-4">
      <form @submit.prevent="loadPage()" class="flex gap-2">
        <input v-model="search" type="text" placeholder="검색어 입력..." class="flex-1 border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 outline-none" />
        <button type="submit" class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-lg text-sm hover:bg-amber-500">검색</button>
      </form>
    </div>

    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="!items.length" class="text-center py-12">
      <div class="text-4xl mb-3">🛒</div>
      <div class="text-gray-400">등록된 내용이 없습니다</div>
      <div v-if="radius !== '0'" class="text-xs text-gray-400 mt-1">거리를 늘려보거나 '전국'으로 검색해보세요</div>
    </div>
    <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <RouterLink v-for="item in items" :key="item.id" :to="'/market/' + item.id"
        class="block px-4 py-3 border-b border-gray-50 hover:bg-amber-50/50 transition">
        <div class="flex items-center justify-between">
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium text-gray-800 truncate">{{ item.title || item.name }}</div>
            <div class="text-xs text-gray-400 mt-0.5">
              {{ item.user?.name || item.company || item.organizer || '' }}
              <span v-if="item.city"> · {{ item.city }}, {{ item.state }}</span>
              <span v-if="item.distance !== undefined"> · {{ item.distance.toFixed(1) }}mi</span>
              <span v-if="item.view_count"> · {{ item.view_count }}조회</span>
            </div>
          </div>
          <div v-if="item.price !== undefined && item.price !== null" class="text-amber-600 font-bold text-sm ml-3">
            ${{ Number(item.price).toLocaleString() }}
          </div>
          <div v-if="item.salary_min" class="text-amber-600 font-bold text-xs ml-3">
            ${{ item.salary_min }}~${{ item.salary_max }}/{{ item.salary_type }}
          </div>
        </div>
      </RouterLink>
    </div>

    <!-- 페이지네이션 -->
    <div v-if="lastPage > 1" class="flex justify-center gap-1.5 mt-4">
      <button v-for="pg in Math.min(lastPage, 10)" :key="pg" @click="loadPage(pg)"
        class="px-3 py-1 rounded text-sm" :class="pg===page?'bg-amber-400 text-amber-900 font-bold':'bg-white text-gray-600 border hover:bg-amber-50'">{{ pg }}</button>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useLocation } from '../../composables/useLocation'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'

const auth = useAuthStore()
const { locationQuery, displayText, init: initLocation } = useLocation()

const items = ref([])
const loading = ref(true)
const page = ref(1)
const lastPage = ref(1)
const search = ref('')
const radius = ref('30')

const locationText = ref('위치 로딩중...')

async function loadPage(p = 1) {
  loading.value = true
  page.value = p

  const params = { page: p, per_page: 20 }
  if (search.value) params.search = search.value

  // 위치 기반 필터
  if (radius.value !== '0') {
    const loc = locationQuery.value
    if (loc.lat && loc.lng) {
      params.lat = loc.lat
      params.lng = loc.lng
      params.radius = parseInt(radius.value)
    }
  }

  try {
    const { data } = await axios.get('/api/market', { params })
    items.value = data.data?.data || []
    lastPage.value = data.data?.last_page || 1
  } catch {}
  loading.value = false
}

onMounted(async () => {
  await initLocation()
  locationText.value = displayText.value || '전국'
  loadPage()
})
</script>