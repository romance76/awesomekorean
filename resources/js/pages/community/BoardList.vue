<template>
  <div class="min-h-screen bg-gray-50 pb-16">
    <div class="max-w-[1200px] mx-auto px-4 pt-4">
      <!-- Header -->
      <div class="bg-gradient-to-r from-blue-600 to-blue-500 text-white px-6 py-5 rounded-2xl mb-5">
        <h1 class="text-xl font-black">💬 커뮤니티</h1>
        <p class="text-blue-100 text-sm mt-0.5">한인들과 정보를 나누고 소통해요</p>
      </div>

      <!-- 지식인 Q&A 배너 -->
      <router-link to="/community/qna"
        class="flex items-center gap-4 bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200 rounded-2xl px-5 py-4 mb-5 hover:shadow-md transition group">
        <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">🧠</div>
        <div class="flex-1 min-w-0">
          <div class="font-bold text-amber-800 group-hover:text-amber-900 text-sm">지식인 Q&A</div>
          <div class="text-xs text-amber-600 mt-0.5">궁금한 것을 물어보고 포인트로 답변 받아보세요</div>
        </div>
        <svg class="w-5 h-5 text-amber-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
      </router-link>

      <!-- Board Groups -->
      <div v-if="loading" class="text-center py-10 text-gray-400">불러오는 중...</div>
      <div v-else>
        <div v-for="(group, category) in grouped" :key="category" class="mb-5">
          <h2 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2 px-1">
            {{ categoryLabel(category) }}
          </h2>
          <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <router-link
              v-for="board in group" :key="board.id"
              :to="`/community/${board.slug}`"
              class="flex items-center justify-between px-5 py-4 border-b border-gray-50 last:border-0 hover:bg-blue-50/50 transition">
              <div class="flex items-center gap-3">
                <span class="text-xl w-8 text-center">{{ board.icon || '📋' }}</span>
                <div>
                  <p class="text-gray-800 font-semibold text-sm">{{ board.name }}</p>
                  <p class="text-gray-400 text-xs mt-0.5">{{ board.description }}</p>
                </div>
              </div>
              <svg class="w-4 h-4 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </router-link>
          </div>
        </div>
        <div v-if="!boards.length" class="text-center py-10 text-gray-400 text-sm">게시판이 없습니다.</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const boards  = ref([])
const loading = ref(true)

const grouped = computed(() => {
  const g = {}
  boards.value.forEach(b => {
    const cat = b.category || 'community'
    if (!g[cat]) g[cat] = []
    g[cat].push(b)
  })
  return g
})

function categoryLabel(cat) {
  return { community: '커뮤니티 게시판', local: '지역별 게시판', expert: '전문가 칼럼' }[cat] || cat
}

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/boards')
    boards.value = data
  } catch {}
  loading.value = false
})
</script>
