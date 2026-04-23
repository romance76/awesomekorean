<template>
<div class="min-h-screen bg-gray-50">
  <!-- 샘플 비교 헤더 -->
  <div class="bg-indigo-600 text-white text-xs font-bold px-4 py-2 flex items-center justify-between">
    <span>💬 샘플 C — 소셜피드형 (Reddit/Threads 스타일, 단일 타임라인)</span>
    <div class="flex gap-2">
      <RouterLink to="/home-sample/portal" class="underline">← 포털형</RouterLink>
      <RouterLink to="/home-sample/magazine" class="underline">← 매거진형</RouterLink>
      <RouterLink to="/" class="bg-indigo-900 px-2 py-0.5 rounded">원본 홈</RouterLink>
    </div>
  </div>

  <div class="max-w-6xl mx-auto px-4 py-5 grid grid-cols-12 gap-5">
    <!-- 좌측 고정 네비 -->
    <aside class="col-span-12 lg:col-span-3 hidden lg:block">
      <div class="sticky top-20 space-y-2">
        <!-- 검색 -->
        <form @submit.prevent="goSearch" class="bg-white rounded-xl border px-3 py-2 flex items-center gap-2">
          <span>🔍</span>
          <input v-model="searchQ" placeholder="검색" class="flex-1 text-sm outline-none bg-transparent" />
        </form>
        <!-- 피드 필터 -->
        <div class="bg-white rounded-xl border p-2">
          <button v-for="f in feedFilters" :key="f.key" @click="activeFilter = f.key"
            class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-bold transition"
            :class="activeFilter === f.key ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-50'">
            <span class="text-base">{{ f.icon }}</span>
            <span>{{ f.label }}</span>
            <span v-if="f.count" class="ml-auto text-[10px] bg-gray-100 px-1.5 rounded">{{ f.count }}</span>
          </button>
        </div>
        <!-- 커뮤니티 바로가기 -->
        <div class="bg-white rounded-xl border p-2">
          <div class="px-2 py-1 text-[10px] font-black text-gray-400">내가 팔로우하는 게시판</div>
          <button v-for="b in boards" :key="b.slug" @click="router.push(`/community/${b.slug}`)"
            class="w-full flex items-center gap-2 px-2 py-1.5 rounded-lg text-xs text-gray-600 hover:bg-gray-50">
            <span class="w-5 h-5 rounded-full text-[10px] font-bold flex items-center justify-center" :style="{ background: b.color + '33', color: b.color }">#</span>
            <span>{{ b.name }}</span>
          </button>
        </div>
      </div>
    </aside>

    <!-- 중앙 피드 -->
    <main class="col-span-12 lg:col-span-6 space-y-3">
      <!-- 포스트 작성 트리거 -->
      <div v-if="auth.isLoggedIn" class="bg-white rounded-xl border p-3 flex items-center gap-3">
        <div class="w-9 h-9 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold">
          {{ (auth.user?.name || '?')[0] }}
        </div>
        <RouterLink to="/community/write" class="flex-1 bg-gray-50 hover:bg-gray-100 rounded-full px-4 py-2 text-sm text-gray-500">무슨 생각을 하고 계신가요?</RouterLink>
        <RouterLink to="/community/write" class="p-2 rounded-full hover:bg-gray-100">📷</RouterLink>
      </div>
      <div v-else class="bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-xl p-4 flex items-center justify-between">
        <div>
          <div class="text-sm font-bold">AwesomeKorean 에 참여하세요</div>
          <div class="text-[11px] opacity-80">글쓰기, 쪽지, 친구 기능 모두 무료</div>
        </div>
        <RouterLink to="/register" class="bg-white text-indigo-600 font-bold px-4 py-1.5 rounded-full text-sm">시작하기</RouterLink>
      </div>

      <!-- 피드 아이템 -->
      <article v-for="item in filteredFeed" :key="item.id"
        class="bg-white rounded-xl border p-4 hover:border-indigo-200 transition cursor-pointer"
        @click="router.push(item.to)">
        <!-- 헤더 -->
        <div class="flex items-center gap-2 mb-2">
          <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold" :style="{ background: item.color }">
            {{ item.typeEmoji }}
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-xs font-bold text-gray-800 flex items-center gap-2">
              <span>{{ item.author || '익명' }}</span>
              <span class="text-[10px] font-normal text-white px-1.5 py-0.5 rounded" :style="{ background: item.color }">{{ item.typeLabel }}</span>
            </div>
            <div class="text-[10px] text-gray-400">{{ item.subtitle }} · {{ item.date }}</div>
          </div>
        </div>
        <!-- 본문 -->
        <div class="text-sm font-bold text-gray-900 mb-1.5 leading-snug">{{ item.title }}</div>
        <div v-if="item.content" class="text-xs text-gray-600 mb-2 line-clamp-2">{{ item.content }}</div>
        <!-- 이미지 -->
        <div v-if="item.image" class="mt-2 rounded-lg overflow-hidden border">
          <img :src="item.image" class="w-full max-h-80 object-cover" @error="($event.target.style.display='none')" />
        </div>
        <!-- 하단 액션바 -->
        <div class="flex items-center gap-5 mt-3 pt-3 border-t border-gray-50 text-xs text-gray-500">
          <span class="flex items-center gap-1 hover:text-red-500 cursor-pointer">❤️ {{ item.likes || 0 }}</span>
          <span class="flex items-center gap-1 hover:text-blue-500 cursor-pointer">💬 {{ item.comments || 0 }}</span>
          <span class="flex items-center gap-1 hover:text-green-500 cursor-pointer">🔗 공유</span>
          <span v-if="item.price" class="ml-auto font-black text-green-600">${{ item.price }}</span>
        </div>
      </article>

      <div v-if="!filteredFeed.length" class="text-center py-16 text-sm text-gray-400">피드가 없습니다</div>
    </main>

    <!-- 우측 트렌딩 -->
    <aside class="col-span-12 lg:col-span-3 hidden lg:block">
      <div class="sticky top-20 space-y-3">
        <div class="bg-white rounded-xl border p-3">
          <div class="text-xs font-black text-gray-800 mb-2">🔥 지금 뜨는 태그</div>
          <div class="flex flex-wrap gap-1">
            <span v-for="(t, i) in ['이민','영주권','맛집','구인','중고차','부동산','세금','학교','한의원']" :key="t"
              @click="router.push({path:'/search',query:{q:t}})"
              class="px-2 py-0.5 rounded-full text-[10px] font-bold cursor-pointer"
              :class="i < 3 ? 'bg-red-100 text-red-600' : 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100'">
              #{{ t }}<span v-if="i<3" class="ml-1">🔥</span>
            </span>
          </div>
        </div>
        <div class="bg-white rounded-xl border p-3">
          <div class="text-xs font-black text-gray-800 mb-2">👋 친구 추천</div>
          <div class="space-y-2">
            <div v-for="n in 3" :key="n" class="flex items-center gap-2">
              <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-400 to-purple-500 text-white text-xs font-bold flex items-center justify-center">{{ ['K','L','M'][n-1] }}</div>
              <div class="flex-1 min-w-0">
                <div class="text-xs font-bold truncate">{{ ['Kim ATL','Lee LA','Min NY'][n-1] }}</div>
                <div class="text-[10px] text-gray-400">활동 많음</div>
              </div>
              <button class="text-[10px] font-bold text-indigo-600 border border-indigo-200 px-2 py-0.5 rounded-full">팔로우</button>
            </div>
          </div>
        </div>
        <div class="bg-gradient-to-br from-indigo-500 to-purple-600 text-white rounded-xl p-3">
          <div class="text-xs font-black mb-1">📢 광고 슬롯</div>
          <div class="text-[11px] opacity-90">월간 경매로 광고를 노출하세요</div>
          <RouterLink to="/dashboard?tab=ads" class="mt-2 inline-block bg-white text-indigo-600 font-bold text-[11px] px-3 py-1 rounded-full">광고 신청</RouterLink>
        </div>
      </div>
    </aside>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'

const router = useRouter()
const auth = useAuthStore()
const searchQ = ref('')
const posts = ref([])
const jobs = ref([])
const market = ref([])
const activeFilter = ref('all')

const feedFilters = [
  { key: 'all', icon: '🏠', label: '홈' },
  { key: 'community', icon: '💬', label: '커뮤니티' },
  { key: 'market', icon: '🛒', label: '장터' },
  { key: 'jobs', icon: '💼', label: '구인' },
  { key: 'popular', icon: '🔥', label: '인기' },
]

const boards = [
  { slug: 'free', name: '자유게시판', color: '#f59e0b' },
  { slug: 'food', name: '맛집후기', color: '#ef4444' },
  { slug: 'immigration', name: '이민생활', color: '#10b981' },
  { slug: 'tips', name: '생활꿀팁', color: '#3b82f6' },
  { slug: 'education', name: '자녀교육', color: '#8b5cf6' },
]

function fmtDate(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  const diff = Math.floor((Date.now() - d.getTime()) / 1000)
  if (diff < 60) return '방금'
  if (diff < 3600) return Math.floor(diff/60) + '분'
  if (diff < 86400) return Math.floor(diff/3600) + '시간'
  return Math.floor(diff/86400) + '일'
}

const feed = computed(() => {
  const items = []
  posts.value.forEach(p => items.push({
    id: 'p' + p.id, type: 'community',
    to: `/community/${p.board?.slug || 'free'}/${p.id}`,
    typeEmoji: '💬', typeLabel: p.board?.name || '커뮤니티',
    color: '#f59e0b',
    author: p.user?.name, subtitle: p.board?.name || '커뮤니티',
    date: fmtDate(p.created_at),
    title: p.title, content: p.content?.replace(/<[^>]+>/g,'').slice(0, 200),
    image: p.thumbnail || null,
    likes: p.likes_count, comments: p.comments_count,
  }))
  market.value.forEach(m => items.push({
    id: 'm' + m.id, type: 'market',
    to: `/market/${m.id}`,
    typeEmoji: '🛒', typeLabel: '장터',
    color: '#3b82f6',
    author: m.user?.name, subtitle: m.category || '중고',
    date: fmtDate(m.created_at),
    title: m.title, content: m.description?.slice(0, 200),
    image: m.images?.[0] || m.image || null,
    price: m.price,
    likes: m.likes_count, comments: m.comments_count,
  }))
  jobs.value.forEach(j => items.push({
    id: 'j' + j.id, type: 'jobs',
    to: `/jobs/${j.id}`,
    typeEmoji: '💼', typeLabel: '구인',
    color: '#10b981',
    author: j.company || j.user?.name, subtitle: j.location || '',
    date: fmtDate(j.created_at),
    title: j.title, content: (j.description || '').slice(0, 200),
    image: null,
  }))
  // 시간순
  return items.sort((a, b) => (b.id > a.id ? 1 : -1))
})

const filteredFeed = computed(() => {
  if (activeFilter.value === 'all') return feed.value
  if (activeFilter.value === 'popular') return [...feed.value].sort((a,b) => (b.likes || 0) - (a.likes || 0))
  return feed.value.filter(i => i.type === activeFilter.value)
})

function goSearch() { if (searchQ.value.trim()) router.push({ path: '/search', query: { q: searchQ.value.trim() } }) }

onMounted(async () => {
  const [p, j, m] = await Promise.allSettled([
    axios.get('/api/posts?per_page=15'),
    axios.get('/api/jobs?per_page=6'),
    axios.get('/api/market?per_page=10'),
  ])
  if (p.status === 'fulfilled') posts.value = p.value.data?.data?.data || []
  if (j.status === 'fulfilled') jobs.value = j.value.data?.data?.data || []
  if (m.status === 'fulfilled') market.value = m.value.data?.data?.data || []
})
</script>
