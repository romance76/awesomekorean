<template>
  <div class="max-w-[1200px] mx-auto px-4 py-6 pb-20">
    <!-- 검색창 -->
    <div class="relative mb-6">
      <input
        v-model="query"
        @keyup.enter="doSearch"
        type="text"
        placeholder="게시글, 구인구직, 업소록, 장터 검색..."
        class="w-full border border-gray-200 rounded-xl px-4 py-3 pr-12 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
        autofocus
      />
      <button @click="doSearch" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-blue-500">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
      </button>
    </div>

    <div v-if="loading" class="text-center py-16 text-gray-400">검색 중...</div>

    <div v-else-if="searched && !hasResults" class="text-center py-16">
      <div class="text-5xl mb-3">🔍</div>
      <p class="text-gray-500">"{{ query }}" 검색 결과가 없습니다.</p>
    </div>

    <div v-else-if="hasResults" class="space-y-6">

      <!-- 게시글 -->
      <section v-if="results.posts?.length">
        <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-3 flex items-center gap-2">
          <span>💬</span> 게시글 ({{ results.posts.length }})
        </h2>
        <div class="space-y-2">
          <RouterLink v-for="p in results.posts" :key="'p'+p.id" :to="`/community/post/${p.id}`"
            class="bg-white rounded-xl p-3 flex gap-3 shadow-sm hover:shadow transition block">
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium text-gray-800 truncate" v-html="highlight(p.title)"></div>
              <div class="text-xs text-gray-400 mt-0.5 line-clamp-1" v-html="highlight(p.content?.slice(0,80))"></div>
              <div class="text-xs text-gray-300 mt-1">{{ p.user?.name }} · {{ p.board?.name }}</div>
            </div>
          </RouterLink>
        </div>
      </section>

      <!-- 구인구직 -->
      <section v-if="results.jobs?.length">
        <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-3 flex items-center gap-2">
          <span>💼</span> 구인구직 ({{ results.jobs.length }})
        </h2>
        <div class="grid sm:grid-cols-2 gap-2">
          <RouterLink v-for="j in results.jobs" :key="'j'+j.id" :to="`/jobs/${j.id}`"
            class="bg-white rounded-xl p-3 shadow-sm hover:shadow transition block">
            <div class="text-xs text-blue-600 font-medium mb-0.5">{{ j.company_name }}</div>
            <div class="text-sm font-semibold text-gray-800 truncate" v-html="highlight(j.title)"></div>
            <div class="text-xs text-gray-400 mt-1">{{ j.region }} · {{ j.salary_range }}</div>
          </RouterLink>
        </div>
      </section>

      <!-- 업소록 -->
      <section v-if="results.businesses?.length">
        <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-3 flex items-center gap-2">
          <span>🏪</span> 업소록 ({{ results.businesses.length }})
        </h2>
        <div class="grid sm:grid-cols-2 gap-2">
          <RouterLink v-for="b in results.businesses" :key="'b'+b.id" :to="`/directory/${b.id}`"
            class="bg-white rounded-xl p-3 shadow-sm hover:shadow transition block">
            <div class="text-sm font-semibold text-gray-800 truncate" v-html="highlight(b.name)"></div>
            <div class="text-xs text-gray-400 mt-1">{{ b.category }} · {{ b.region }}</div>
            <div class="flex items-center gap-1 mt-1">
              <span class="text-yellow-400 text-xs">★</span>
              <span class="text-xs text-gray-500">{{ Number(b.rating_avg || 0).toFixed(1) }}</span>
            </div>
          </RouterLink>
        </div>
      </section>

      <!-- 중고장터 -->
      <section v-if="results.market?.length">
        <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-3 flex items-center gap-2">
          <span>🛍️</span> 중고장터 ({{ results.market.length }})
        </h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
          <RouterLink v-for="m in results.market" :key="'m'+m.id" :to="`/market/${m.id}`"
            class="bg-white rounded-xl p-3 shadow-sm hover:shadow transition block">
            <div class="text-sm font-medium text-gray-800 truncate" v-html="highlight(m.title)"></div>
            <div class="text-blue-600 font-bold text-sm mt-1">${{ Number(m.price).toLocaleString() }}</div>
            <div class="text-xs text-gray-400">{{ m.region }}</div>
          </RouterLink>
        </div>
      </section>

    </div>

    <div v-else class="text-center py-16">
      <div class="text-5xl mb-3">🔍</div>
      <p class="text-gray-400 text-sm">키워드를 입력하고 검색하세요</p>
      <div class="flex flex-wrap gap-2 justify-center mt-4">
        <button v-for="tag in popularTags" :key="tag" @click="quickSearch(tag)"
          class="text-xs bg-blue-50 text-blue-600 px-3 py-1 rounded-full hover:bg-blue-100">
          {{ tag }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'

const route = useRoute()

const query   = ref(route.query.q ?? '')
const results = ref({})
const loading = ref(false)
const searched = ref(false)

const popularTags = ['한인', '영어', '부동산', 'IT', '레스토랑', '청소', '이민', '세금', '보험', '자동차']

const hasResults = computed(() =>
  (results.value.posts?.length || results.value.jobs?.length ||
   results.value.businesses?.length || results.value.market?.length)
)

function highlight(text) {
  if (!text || !query.value) return text ?? ''
  const esc = query.value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  return text.replace(new RegExp(`(${esc})`, 'gi'), '<mark class="bg-yellow-200 rounded px-0.5">$1</mark>')
}

async function doSearch() {
  if (!query.value.trim()) return
  loading.value = true
  searched.value = false
  try {
    const { data } = await axios.get('/api/search', { params: { q: query.value } })
    results.value = data.result
  } catch {
    results.value = {}
  } finally {
    loading.value = false
    searched.value = true
  }
}

function quickSearch(tag) {
  query.value = tag
  doSearch()
}

if (query.value) doSearch()
</script>
