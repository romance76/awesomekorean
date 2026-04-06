<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-4xl mx-auto px-4 py-5">
    <h1 class="text-xl font-black text-gray-800 mb-4">🔍 검색</h1>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
      <form @submit.prevent="search" class="flex gap-2">
        <input v-model="query" type="text" placeholder="검색어를 입력하세요..." autofocus class="flex-1 border-2 border-amber-400 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
        <button type="submit" class="bg-amber-400 text-amber-900 font-bold px-6 py-2.5 rounded-lg hover:bg-amber-500">검색</button>
      </form>
    </div>

    <div v-if="loading" class="text-center py-8 text-gray-400">검색 중...</div>
    <div v-else-if="searched">
      <template v-for="(items, type) in results" :key="type">
        <div v-if="items.length" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-3">
          <div class="px-4 py-2.5 bg-amber-50 border-b font-bold text-sm text-amber-900">{{ typeLabels[type] }} ({{ items.length }})</div>
          <RouterLink v-for="item in items" :key="item.id" :to="typeLinks[type] + item.id"
            class="block px-4 py-2.5 border-b last:border-0 hover:bg-amber-50/50 transition">
            <div class="text-sm font-medium text-gray-800">{{ item.title || item.name }}</div>
            <div class="text-xs text-gray-400 mt-0.5">{{ item.company || item.category || item.city || '' }}</div>
          </RouterLink>
        </div>
      </template>
      <div v-if="totalResults === 0" class="text-center py-8 text-gray-400">검색 결과가 없습니다</div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
const route = useRoute()
const query = ref(route.query.q || '')
const results = ref({})
const loading = ref(false)
const searched = ref(false)
const typeLabels = { posts:'게시글', jobs:'구인구직', market:'중고장터', businesses:'업소록', events:'이벤트', qa:'Q&A', recipes:'레시피' }
const typeLinks = { posts:'/community/free/', jobs:'/jobs/', market:'/market/', businesses:'/directory/', events:'/events/', qa:'/qa/', recipes:'/recipes/' }
const totalResults = computed(() => Object.values(results.value).reduce((s, arr) => s + (arr?.length || 0), 0))

async function search() {
  if (!query.value.trim()) return
  loading.value = true; searched.value = false
  try {
    const { data } = await axios.get('/api/search', { params: { q: query.value } })
    results.value = data.data || {}
  } catch {}
  loading.value = false; searched.value = true
}

onMounted(() => { if (query.value) search() })
</script>
