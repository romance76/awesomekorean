<template>
<div class="min-h-screen bg-white">
  <!-- 샘플 비교 헤더 -->
  <div class="bg-pink-500 text-white text-xs font-bold px-4 py-2 flex items-center justify-between">
    <span>🖼️ 샘플 B — 매거진형 (Pinterest/인스타 스타일, 이미지 중심)</span>
    <div class="flex gap-2">
      <RouterLink to="/home-sample/portal" class="underline">← 포털형</RouterLink>
      <RouterLink to="/home-sample/feed" class="underline">→ 소셜피드형</RouterLink>
      <RouterLink to="/" class="bg-pink-900 text-pink-100 px-2 py-0.5 rounded">원본 홈</RouterLink>
    </div>
  </div>

  <!-- 큰 히어로 -->
  <section class="relative bg-gradient-to-br from-rose-400 via-orange-400 to-amber-400 overflow-hidden">
    <div class="max-w-6xl mx-auto px-6 py-16 text-center text-white">
      <div class="text-sm font-bold opacity-90 mb-2 tracking-widest">AWESOME KOREAN</div>
      <h1 class="text-4xl md:text-6xl font-black mb-3">일상을 빛나게,<br/>한인의 모든 것</h1>
      <p class="text-sm md:text-base opacity-90 mb-6">커뮤니티 · 장터 · 업소 · 이벤트 · 레시피 — 한 곳에서</p>
      <form @submit.prevent="goSearch" class="max-w-md mx-auto flex bg-white/95 backdrop-blur rounded-full shadow-xl overflow-hidden">
        <input v-model="searchQ" placeholder="무엇을 찾으세요?" class="flex-1 px-5 py-3 text-sm text-gray-800 outline-none" />
        <button class="bg-rose-500 hover:bg-rose-600 text-white px-5 text-sm font-bold">검색</button>
      </form>
    </div>
    <!-- 장식 -->
    <div class="absolute -top-20 -right-20 w-80 h-80 bg-white/10 rounded-full blur-3xl"></div>
    <div class="absolute -bottom-10 -left-10 w-60 h-60 bg-white/10 rounded-full blur-3xl"></div>
  </section>

  <!-- 카테고리 컬러 블록 -->
  <section class="max-w-6xl mx-auto px-4 -mt-8 relative z-10 mb-10">
    <div class="grid grid-cols-3 md:grid-cols-6 gap-2">
      <RouterLink v-for="cat in categories" :key="cat.to" :to="cat.to"
        class="flex flex-col items-center justify-center py-5 rounded-2xl text-white shadow-lg hover:scale-105 transition-transform"
        :style="{ background: cat.bg }">
        <span class="text-3xl mb-1">{{ cat.icon }}</span>
        <span class="text-[11px] font-black">{{ cat.name }}</span>
      </RouterLink>
    </div>
  </section>

  <!-- Masonry 피드 -->
  <section class="max-w-6xl mx-auto px-4 pb-12">
    <div class="flex items-center justify-between mb-5">
      <h2 class="text-xl font-black text-gray-800">✨ 오늘의 한인 이야기</h2>
      <div class="flex gap-1 text-[11px]">
        <button v-for="f in filters" :key="f.key" @click="activeFilter = f.key"
          class="px-3 py-1 rounded-full font-bold transition"
          :class="activeFilter === f.key ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'">
          {{ f.label }}
        </button>
      </div>
    </div>

    <!-- Masonry (CSS columns) -->
    <div class="columns-2 md:columns-3 lg:columns-4 gap-3 space-y-3">
      <!-- 높이 다양한 카드 -->
      <RouterLink v-for="card in feedCards" :key="card.id" :to="card.to"
        class="block break-inside-avoid bg-white rounded-2xl overflow-hidden shadow-sm border border-gray-100 hover:shadow-lg transition group">
        <div v-if="card.image" class="relative">
          <img :src="card.image" :style="{ aspectRatio: card.ratio }" class="w-full object-cover" @error="($event.target.style.display='none')" />
          <span class="absolute top-2 left-2 text-[9px] font-black px-2 py-0.5 rounded-full text-white" :style="{ background: card.color }">{{ card.badge }}</span>
        </div>
        <div v-else class="px-4 py-5" :style="{ background: card.color + '22' }">
          <span class="text-[9px] font-black px-2 py-0.5 rounded-full text-white" :style="{ background: card.color }">{{ card.badge }}</span>
          <div class="text-lg font-black mt-2" :style="{ color: card.color }">{{ card.emoji }}</div>
        </div>
        <div class="p-3">
          <div class="text-xs font-bold text-gray-800 leading-tight line-clamp-2">{{ card.title }}</div>
          <div v-if="card.sub" class="text-[10px] text-gray-400 mt-1 line-clamp-1">{{ card.sub }}</div>
          <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-50">
            <span class="text-[10px] text-gray-400">{{ card.author || '익명' }}</span>
            <div class="flex gap-2 text-[10px] text-gray-400">
              <span v-if="card.likes">❤️ {{ card.likes }}</span>
              <span v-if="card.comments">💬 {{ card.comments }}</span>
            </div>
          </div>
        </div>
      </RouterLink>
    </div>
  </section>
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const searchQ = ref('')
const posts = ref([])
const jobs = ref([])
const market = ref([])
const activeFilter = ref('all')

const filters = [
  { key: 'all', label: '전체' },
  { key: 'community', label: '커뮤니티' },
  { key: 'market', label: '장터' },
  { key: 'jobs', label: '구인' },
]

const categories = [
  { name: '커뮤니티', icon: '💬', to: '/community', bg: 'linear-gradient(135deg,#f59e0b,#f97316)' },
  { name: '중고장터', icon: '🛒', to: '/market', bg: 'linear-gradient(135deg,#3b82f6,#6366f1)' },
  { name: '업소록', icon: '🏪', to: '/directory', bg: 'linear-gradient(135deg,#10b981,#059669)' },
  { name: '부동산', icon: '🏠', to: '/realestate', bg: 'linear-gradient(135deg,#8b5cf6,#6366f1)' },
  { name: '레시피', icon: '🍳', to: '/recipes', bg: 'linear-gradient(135deg,#ef4444,#f97316)' },
  { name: '이벤트', icon: '🎉', to: '/events', bg: 'linear-gradient(135deg,#ec4899,#f43f5e)' },
]

// 혼합 피드 — 게시글 + 장터 + 구인
const feedCards = computed(() => {
  const cards = []
  // 장터는 이미지 중심
  market.value.forEach((m, i) => {
    cards.push({
      id: 'm' + m.id,
      to: `/market/${m.id}`,
      title: m.title,
      sub: `$${m.price || 0}`,
      image: m.images?.[0] || m.image || null,
      ratio: i % 3 === 0 ? '4/5' : i % 3 === 1 ? '3/4' : '1/1',
      badge: '장터',
      color: '#3b82f6',
      author: m.user?.name,
      likes: m.likes_count || 0,
      comments: m.comments_count || 0,
    })
  })
  // 게시글은 텍스트 카드
  posts.value.forEach(p => {
    cards.push({
      id: 'p' + p.id,
      to: `/community/${p.board?.slug || 'free'}/${p.id}`,
      title: p.title,
      sub: p.board?.name,
      image: p.thumbnail || null,
      ratio: '4/3',
      badge: p.board?.name || '커뮤니티',
      color: '#f59e0b',
      emoji: '💬',
      author: p.user?.name,
      likes: p.likes_count || 0,
      comments: p.comments_count || 0,
    })
  })
  // 구인은 강조 카드
  jobs.value.forEach(j => {
    cards.push({
      id: 'j' + j.id,
      to: `/jobs/${j.id}`,
      title: j.title,
      sub: j.company || j.wage || '협의',
      image: null,
      ratio: '5/4',
      badge: '구인',
      color: '#10b981',
      emoji: '💼',
      author: j.user?.name,
    })
  })
  // 섞어서 순서 다양화
  const shuffled = cards.sort(() => 0.4 - Math.random())
  if (activeFilter.value === 'all') return shuffled
  const map = { community: 'p', market: 'm', jobs: 'j' }
  const prefix = map[activeFilter.value]
  return shuffled.filter(c => c.id.startsWith(prefix))
})

function goSearch() { if (searchQ.value.trim()) router.push({ path: '/search', query: { q: searchQ.value.trim() } }) }

onMounted(async () => {
  const [p, j, m] = await Promise.allSettled([
    axios.get('/api/posts?per_page=12'),
    axios.get('/api/jobs?per_page=6'),
    axios.get('/api/market?per_page=12'),
  ])
  if (p.status === 'fulfilled') posts.value = p.value.data?.data?.data || []
  if (j.status === 'fulfilled') jobs.value = j.value.data?.data?.data || []
  if (m.status === 'fulfilled') market.value = m.value.data?.data?.data || []
})
</script>
