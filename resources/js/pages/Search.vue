<template>
<div class="min-h-screen">
  <div class="max-w-4xl mx-auto px-4 py-5">
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-4">
      <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="search" :size="20" /></span>
      검색
    </h1>
    <div class="card p-4 mb-4">
      <form @submit.prevent="search" class="flex gap-2">
        <input v-model="query" type="text" placeholder="검색어를 입력하세요..." autofocus class="input-soft flex-1 w-auto" />
        <button type="submit" class="btn-primary px-6">검색</button>
      </form>
    </div>

    <div v-if="loading" class="text-center py-8 text-ink-faint">검색 중...</div>
    <div v-else-if="searched">
      <template v-for="(items, type) in results" :key="type">
        <div v-if="items.length" class="card overflow-hidden divide-y divide-gray-50 mb-3">
          <div class="px-4 py-2.5 bg-amber-50 font-bold text-sm text-amber-700">{{ typeLabels[type] }} ({{ items.length }})</div>
          <RouterLink v-for="item in items" :key="item.id" :to="typeLinks[type] + item.id"
            class="list-row">
            <div class="text-sm font-medium text-ink">{{ item.title || item.name }}</div>
            <div class="text-xs text-ink-faint mt-0.5">{{ item.company || item.category || item.city || '' }}</div>
          </RouterLink>
        </div>
      </template>
      <div v-if="totalResults === 0" class="py-16 text-center">
        <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="search" :size="28" :stroke-width="1.5" /></div>
        <p class="text-sm text-ink-muted">검색 결과가 없습니다</p>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import AppIcon from '../components/AppIcon.vue'
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
