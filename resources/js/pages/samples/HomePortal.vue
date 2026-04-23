<template>
<div class="min-h-screen bg-gray-100">
  <!-- 샘플 비교 헤더 -->
  <div class="bg-amber-500 text-amber-900 text-xs font-bold px-4 py-2 flex items-center justify-between">
    <span>📰 샘플 A — 포털형 (네이버/다음 스타일, 정보 밀도 최대)</span>
    <div class="flex gap-2">
      <RouterLink to="/home-sample/magazine" class="underline">→ 매거진형</RouterLink>
      <RouterLink to="/home-sample/feed" class="underline">→ 소셜피드형</RouterLink>
      <RouterLink to="/" class="bg-amber-900 text-amber-100 px-2 py-0.5 rounded">원본 홈</RouterLink>
    </div>
  </div>

  <div class="max-w-7xl mx-auto px-3 py-3">
    <!-- 얇은 상단 알림바 -->
    <div class="bg-white rounded-lg border border-amber-200 px-3 py-1.5 flex items-center gap-3 text-xs mb-3 overflow-x-auto">
      <span class="font-black text-amber-600 flex-shrink-0">📢 공지</span>
      <span class="text-gray-600 whitespace-nowrap">{{ posts[0]?.title || '오늘의 핫 이슈를 확인하세요' }}</span>
      <span class="text-gray-300 flex-shrink-0">·</span>
      <span class="text-gray-500 whitespace-nowrap">🔥 이번주 인기: #이민 #맛집 #부동산 #장터</span>
    </div>

    <!-- 얇은 메뉴 아이콘 바 -->
    <div class="bg-white rounded-xl border border-gray-200 p-2 mb-3 grid grid-cols-6 sm:grid-cols-12 gap-1">
      <RouterLink v-for="svc in services" :key="svc.to" :to="svc.to"
        class="flex flex-col items-center py-2 rounded-lg hover:bg-amber-50 transition">
        <span class="text-xl">{{ svc.icon }}</span>
        <span class="text-[10px] font-bold text-gray-600 mt-0.5">{{ svc.name }}</span>
      </RouterLink>
    </div>

    <!-- 3-column 포털 레이아웃 -->
    <div class="grid grid-cols-12 gap-3">
      <!-- 좌측: 인기 카테고리 + 트렌딩 -->
      <aside class="col-span-12 lg:col-span-3 space-y-3">
        <div class="bg-white rounded-xl border p-3">
          <div class="text-xs font-black text-gray-800 mb-2 pb-2 border-b">🔥 인기 게시판</div>
          <RouterLink v-for="b in boards" :key="b.slug" :to="`/community/${b.slug}`"
            class="flex items-center justify-between py-1.5 text-xs text-gray-600 hover:text-amber-700 border-b last:border-0">
            <span>{{ b.name }}</span>
            <span class="text-[10px] text-amber-500 font-bold">HOT</span>
          </RouterLink>
        </div>
        <div class="bg-white rounded-xl border p-3">
          <div class="text-xs font-black text-gray-800 mb-2">🏷️ 트렌딩 태그</div>
          <div class="flex flex-wrap gap-1">
            <span v-for="t in ['이민','영주권','맛집','구인','중고차','부동산','세금','학교','병원','한의원','김치','미용실']" :key="t"
              @click="router.push({path:'/search',query:{q:t}})"
              class="bg-amber-50 text-amber-700 px-2 py-0.5 rounded text-[10px] font-bold cursor-pointer hover:bg-amber-100">#{{ t }}</span>
          </div>
        </div>
        <div class="bg-white rounded-xl border p-3">
          <div class="text-xs font-black text-gray-800 mb-2">👥 실시간 활동</div>
          <div class="text-[11px] space-y-1.5">
            <div class="flex items-center gap-2"><span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span><span class="text-gray-600">현재 접속 238명</span></div>
            <div class="flex items-center gap-2"><span class="text-gray-400">오늘 신규글</span><span class="font-bold text-amber-600 ml-auto">{{ posts.length * 12 }}</span></div>
            <div class="flex items-center gap-2"><span class="text-gray-400">신규 장터</span><span class="font-bold text-amber-600 ml-auto">{{ market.length * 3 }}</span></div>
          </div>
        </div>
      </aside>

      <!-- 중앙: 최신글 + 핫이슈 + 공지 멀티 박스 -->
      <main class="col-span-12 lg:col-span-6 space-y-3">
        <!-- 미니 히어로 (작게) -->
        <div class="bg-gradient-to-r from-amber-500 via-orange-500 to-red-500 rounded-xl p-4 text-white">
          <div class="text-xs opacity-80">미국 한인 No.1 커뮤니티</div>
          <div class="text-lg font-black">AwesomeKorean</div>
          <form @submit.prevent="goSearch" class="flex mt-2 bg-white rounded-lg overflow-hidden">
            <input v-model="searchQ" placeholder="업소·구인·장터 검색" class="flex-1 px-3 py-1.5 text-sm text-gray-800 outline-none" />
            <button class="bg-amber-600 px-3 text-white text-sm font-bold">🔍</button>
          </form>
        </div>

        <!-- 2x2 박스 그리드 -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <!-- 최신 게시글 -->
          <div class="bg-white rounded-xl border overflow-hidden">
            <div class="px-3 py-2 border-b bg-amber-50 flex items-center justify-between">
              <span class="text-xs font-black text-amber-900">📝 최신글</span>
              <RouterLink to="/community" class="text-[10px] text-amber-600">더보기 →</RouterLink>
            </div>
            <div class="divide-y">
              <RouterLink v-for="p in posts.slice(0,6)" :key="p.id" :to="`/community/${p.board?.slug}/${p.id}`"
                class="flex items-center justify-between px-3 py-1.5 hover:bg-amber-50 transition">
                <span class="text-[11px] text-gray-700 truncate flex-1">{{ p.title }}</span>
                <span class="text-[9px] text-gray-400 ml-2 flex-shrink-0">{{ p.comments_count || 0 }}</span>
              </RouterLink>
            </div>
          </div>
          <!-- 구인구직 -->
          <div class="bg-white rounded-xl border overflow-hidden">
            <div class="px-3 py-2 border-b bg-blue-50 flex items-center justify-between">
              <span class="text-xs font-black text-blue-900">💼 구인구직</span>
              <RouterLink to="/jobs" class="text-[10px] text-blue-600">더보기 →</RouterLink>
            </div>
            <div class="divide-y">
              <RouterLink v-for="j in jobs.slice(0,6)" :key="j.id" :to="`/jobs/${j.id}`"
                class="flex items-center justify-between px-3 py-1.5 hover:bg-blue-50 transition">
                <span class="text-[11px] text-gray-700 truncate flex-1">{{ j.title }}</span>
                <span class="text-[9px] text-blue-600 ml-2 flex-shrink-0 font-bold">{{ j.wage || '협의' }}</span>
              </RouterLink>
            </div>
          </div>
          <!-- 중고장터 -->
          <div class="bg-white rounded-xl border overflow-hidden">
            <div class="px-3 py-2 border-b bg-green-50 flex items-center justify-between">
              <span class="text-xs font-black text-green-900">🛒 중고장터</span>
              <RouterLink to="/market" class="text-[10px] text-green-600">더보기 →</RouterLink>
            </div>
            <div class="divide-y">
              <RouterLink v-for="m in market.slice(0,6)" :key="m.id" :to="`/market/${m.id}`"
                class="flex items-center justify-between px-3 py-1.5 hover:bg-green-50 transition">
                <span class="text-[11px] text-gray-700 truncate flex-1">{{ m.title }}</span>
                <span class="text-[9px] text-green-600 ml-2 flex-shrink-0 font-bold">${{ m.price || 0 }}</span>
              </RouterLink>
            </div>
          </div>
          <!-- 이벤트 -->
          <div class="bg-white rounded-xl border overflow-hidden">
            <div class="px-3 py-2 border-b bg-red-50 flex items-center justify-between">
              <span class="text-xs font-black text-red-900">🎉 이벤트</span>
              <RouterLink to="/events" class="text-[10px] text-red-600">더보기 →</RouterLink>
            </div>
            <div class="px-3 py-4 text-center text-[11px] text-gray-500">
              <div class="text-2xl mb-1">🎁</div>
              <div class="font-bold">진행중인 이벤트를 확인하세요</div>
            </div>
          </div>
        </div>
      </main>

      <!-- 우측: 날씨 + 광고 + 즐겨찾기 -->
      <aside class="col-span-12 lg:col-span-3 space-y-3">
        <div class="bg-gradient-to-br from-blue-400 to-blue-600 rounded-xl p-3 text-white">
          <div class="text-[10px] opacity-80">애틀랜타 · Today</div>
          <div class="flex items-baseline gap-2 mt-1"><span class="text-2xl font-black">72°F</span><span class="text-xs">맑음 ☀️</span></div>
          <div class="text-[10px] opacity-80 mt-1">내일 68° / 모레 75°</div>
        </div>
        <div class="bg-white rounded-xl border p-3">
          <div class="text-xs font-black text-gray-800 mb-2">💱 환율</div>
          <div class="space-y-1.5 text-[11px]">
            <div class="flex justify-between"><span class="text-gray-500">🇺🇸 USD</span><span class="font-bold">1,386원</span></div>
            <div class="flex justify-between"><span class="text-gray-500">🇰🇷 KRW</span><span class="font-bold">$0.00072</span></div>
          </div>
        </div>
        <AdSlot page="home" position="right" :maxSlots="2" />
        <div class="bg-white rounded-xl border p-3">
          <div class="text-xs font-black text-gray-800 mb-2">⭐ 즐겨찾기</div>
          <div class="grid grid-cols-3 gap-1.5">
            <RouterLink v-for="svc in services.slice(0,6)" :key="svc.to" :to="svc.to"
              class="flex flex-col items-center py-1.5 rounded-lg hover:bg-amber-50">
              <span class="text-base">{{ svc.icon }}</span>
              <span class="text-[9px] text-gray-500 mt-0.5">{{ svc.name }}</span>
            </RouterLink>
          </div>
        </div>
      </aside>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useSiteStore } from '../../stores/site'
import { useLangStore } from '../../stores/lang'
import AdSlot from '../../components/AdSlot.vue'
import axios from 'axios'

const router = useRouter()
const auth = useAuthStore()
const siteStore = useSiteStore()
const langStore = useLangStore()
const searchQ = ref('')
const posts = ref([])
const jobs = ref([])
const market = ref([])

const serviceDefaults = [
  { key: 'community', label: '커뮤니티', icon: '💬', path: '/community' },
  { key: 'jobs', label: '구인구직', icon: '💼', path: '/jobs' },
  { key: 'market', label: '중고장터', icon: '🛒', path: '/market' },
  { key: 'directory', label: '업소록', icon: '🏪', path: '/directory' },
  { key: 'realestate', label: '부동산', icon: '🏠', path: '/realestate' },
  { key: 'news', label: '뉴스', icon: '📰', path: '/news' },
  { key: 'events', label: '이벤트', icon: '🎉', path: '/events' },
  { key: 'qa', label: 'Q&A', icon: '❓', path: '/qa' },
  { key: 'clubs', label: '동호회', icon: '👥', path: '/clubs' },
  { key: 'recipes', label: '레시피', icon: '🍳', path: '/recipes' },
  { key: 'games', label: '게임', icon: '🎮', path: '/games' },
  { key: 'elder', label: '안심서비스', icon: '💙', path: '/elder' },
]

const services = computed(() => {
  const mc = siteStore.menuConfig
  if (mc && Array.isArray(mc)) {
    const defMap = {}
    serviceDefaults.forEach(m => { defMap[m.key] = m })
    return mc.filter(m => m.key !== 'home' && m.enabled !== false && (!m.admin_only || auth.isAdmin) && (!m.login_required || auth.isLoggedIn))
      .map(m => ({ to: m.path || `/${m.key}`, icon: m.icon || '📄', name: m.label || m.key }))
  }
  return serviceDefaults.map(m => ({ to: m.path, icon: m.icon, name: m.label }))
})

const boards = [
  { slug: 'free', name: '자유게시판' },{ slug: 'info', name: '정보공유' },
  { slug: 'tips', name: '생활꿀팁' },{ slug: 'food', name: '맛집후기' },
  { slug: 'immigration', name: '이민생활' },{ slug: 'health', name: '건강정보' },
  { slug: 'education', name: '자녀교육' },
]

function goSearch() { if (searchQ.value.trim()) router.push({ path: '/search', query: { q: searchQ.value.trim() } }) }

onMounted(async () => {
  siteStore.load()
  const [p, j, m] = await Promise.allSettled([
    axios.get('/api/posts?per_page=8'),
    axios.get('/api/jobs?per_page=8'),
    axios.get('/api/market?per_page=8'),
  ])
  if (p.status === 'fulfilled') posts.value = p.value.data?.data?.data?.slice(0, 8) || []
  if (j.status === 'fulfilled') jobs.value = j.value.data?.data?.data?.slice(0, 8) || []
  if (m.status === 'fulfilled') market.value = m.value.data?.data?.data?.slice(0, 8) || []
})
</script>
