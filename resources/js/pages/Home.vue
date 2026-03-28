<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <!-- 히어로 -->
    <div class="bg-gradient-to-br from-blue-700 via-blue-600 to-indigo-600 text-white">
      <div class="max-w-[1200px] mx-auto px-4 py-12 text-center">
        <h1 class="text-3xl md:text-4xl font-black tracking-tight">🇰🇷 SomeKorean</h1>
        <p class="mt-2 text-blue-100 text-lg">미국 한인 올인원 커뮤니티 플랫폼</p>
        <div v-if="!auth.isLoggedIn" class="mt-6 flex flex-col sm:flex-row gap-3 justify-center">
          <router-link to="/auth/register"
            class="bg-white text-blue-700 font-bold px-6 py-3 rounded-xl hover:bg-blue-50 transition">
            지금 시작하기
          </router-link>
          <router-link to="/auth/login"
            class="border border-white/50 text-white font-medium px-6 py-3 rounded-xl hover:bg-white/10 transition">
            로그인
          </router-link>
        </div>
        <div v-else class="mt-6">
          <p class="text-blue-100">안녕하세요, <span class="font-bold text-white">{{ auth.user?.name }}</span>님! 👋</p>
          <div class="mt-3 inline-flex items-center gap-2 bg-white/20 rounded-full px-4 py-2">
            <span class="text-yellow-300">⭐</span>
            <span class="text-sm font-semibold">{{ (auth.user?.points_total ?? 0).toLocaleString() }}P</span>
            <span class="text-blue-200 text-xs">·</span>
            <span class="text-sm text-blue-100">{{ auth.user?.level }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- 통계 -->
    <div class="bg-white border-b">
      <div class="max-w-[1200px] mx-auto px-4 py-4 grid grid-cols-4 gap-4 text-center">
        <div><div class="text-xl font-black text-blue-600">{{ stats.users }}</div><div class="text-xs text-gray-500">회원</div></div>
        <div><div class="text-xl font-black text-green-600">{{ stats.posts }}</div><div class="text-xs text-gray-500">게시글</div></div>
        <div><div class="text-xl font-black text-purple-600">{{ stats.businesses }}</div><div class="text-xs text-gray-500">업소</div></div>
        <div><div class="text-xl font-black text-orange-600">{{ stats.jobs }}</div><div class="text-xs text-gray-500">구인구직</div></div>
      </div>
    </div>

    <div class="max-w-[1200px] mx-auto px-4 py-6 space-y-6">

      <!-- 메인 기능 그리드 -->
      <div>
        <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-3">주요 서비스</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
          <router-link v-for="feat in features" :key="feat.path" :to="feat.path"
            class="bg-white rounded-2xl p-4 flex items-center gap-3 shadow-sm hover:shadow-md transition group">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl flex-shrink-0"
              :style="{ backgroundColor: feat.bg }">{{ feat.icon }}</div>
            <div class="min-w-0">
              <div class="font-semibold text-gray-800 group-hover:text-blue-600 transition text-sm">{{ feat.name }}</div>
              <div class="text-xs text-gray-400 line-clamp-1">{{ feat.desc }}</div>
            </div>
          </router-link>
        </div>
      </div>

      <!-- 최신 게시글 -->
      <div>
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wider">최신 게시글</h2>
          <router-link to="/community" class="text-blue-600 text-sm">더보기 →</router-link>
        </div>
        <div v-if="loadingPosts" class="text-center py-6 text-gray-400">불러오는 중...</div>
        <div v-else class="space-y-2">
          <router-link v-for="post in posts" :key="post.id" :to="`/community/post/${post.id}`"
            class="bg-white rounded-xl p-3 flex items-center gap-3 shadow-sm hover:shadow transition block">
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium text-gray-800 truncate">{{ post.title }}</div>
              <div class="flex items-center gap-2 mt-0.5">
                <span class="text-xs text-gray-400">{{ post.user?.name }}</span>
                <span class="text-gray-300 text-xs">·</span>
                <span class="text-xs text-gray-400">👁 {{ post.view_count }}</span>
                <span class="text-gray-300 text-xs">·</span>
                <span class="text-xs text-gray-400">💬 {{ post.comment_count }}</span>
              </div>
            </div>
            <span v-if="post.like_count > 5" class="text-xs bg-red-50 text-red-500 px-1.5 py-0.5 rounded-full">🔥 인기</span>
          </router-link>
        </div>
      </div>

      <!-- 최신 구인구직 -->
      <div>
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wider">최신 구인구직</h2>
          <router-link to="/jobs" class="text-blue-600 text-sm">더보기 →</router-link>
        </div>
        <div v-if="loadingJobs" class="text-center py-4 text-gray-400">불러오는 중...</div>
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <router-link v-for="job in jobs" :key="job.id" :to="`/jobs/${job.id}`"
            class="bg-white rounded-xl p-3 shadow-sm hover:shadow transition block">
            <div class="flex items-center gap-2 mb-1">
              <span class="text-xs px-1.5 py-0.5 rounded-full font-medium"
                :class="jobTypeColor(job.job_type)">{{ jobTypeLabel(job.job_type) }}</span>
              <span class="text-xs text-gray-400">{{ job.region }}</span>
            </div>
            <div class="text-sm font-medium text-gray-800 truncate">{{ job.title }}</div>
            <div class="text-xs text-gray-500 mt-0.5">{{ job.company_name }} · {{ job.salary_range }}</div>
          </router-link>
        </div>
      </div>

      <!-- 중고장터 최신 -->
      <div>
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wider">중고장터 최신</h2>
          <router-link to="/market" class="text-blue-600 text-sm">더보기 →</router-link>
        </div>
        <div v-if="loadingMarket" class="text-center py-4 text-gray-400">불러오는 중...</div>
        <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-2">
          <router-link v-for="item in market" :key="item.id" :to="`/market/${item.id}`"
            class="bg-white rounded-xl p-3 shadow-sm hover:shadow transition block">
            <div class="text-lg mb-1">{{ categoryEmoji(item.category) }}</div>
            <div class="text-sm font-medium text-gray-800 truncate">{{ item.title }}</div>
            <div class="text-blue-600 font-bold text-sm mt-0.5">${{ Number(item.price).toLocaleString() }}</div>
            <div class="text-xs text-gray-400">{{ item.region }}</div>
          </router-link>
        </div>
      </div>

      <!-- 이벤트 -->
      <div>
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wider">다가오는 이벤트</h2>
          <router-link to="/events" class="text-blue-600 text-sm">더보기 →</router-link>
        </div>
        <div v-if="loadingEvents" class="text-center py-4 text-gray-400">불러오는 중...</div>
        <div v-else class="space-y-2">
          <router-link v-for="ev in events" :key="ev.id" :to="`/events/${ev.id}`"
            class="bg-white rounded-xl p-3 flex gap-3 shadow-sm hover:shadow transition block">
            <div class="w-12 h-12 bg-blue-100 rounded-xl flex flex-col items-center justify-center flex-shrink-0">
              <div class="text-xs text-blue-600 font-bold">{{ formatEventDate(ev.event_date).month }}</div>
              <div class="text-lg font-black text-blue-700">{{ formatEventDate(ev.event_date).day }}</div>
            </div>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium text-gray-800 truncate">{{ ev.title }}</div>
              <div class="text-xs text-gray-500 mt-0.5">📍 {{ ev.location }} · {{ ev.region }}</div>
              <div class="text-xs text-gray-400 mt-0.5">👥 {{ ev.attendee_count || 0 }}/{{ ev.max_attendees }}명</div>
            </div>
          </router-link>
        </div>
      </div>

      <!-- 앱 다운로드 유도 (PWA) -->
      <div class="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-2xl p-6 text-white text-center">
        <div class="text-3xl mb-2">📱</div>
        <h3 class="font-bold text-lg">앱으로 더 편리하게!</h3>
        <p class="text-blue-100 text-sm mt-1">홈화면에 추가하면 앱처럼 사용할 수 있어요</p>
        <p class="text-xs text-blue-200 mt-2">Safari → 공유 → 홈 화면에 추가 (iOS)<br>Chrome → 메뉴 → 앱 설치 (Android/PC)</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import axios from 'axios'

const auth = useAuthStore()

const posts   = ref([])
const jobs    = ref([])
const market  = ref([])
const events  = ref([])

const loadingPosts  = ref(false)
const loadingJobs   = ref(false)
const loadingMarket = ref(false)
const loadingEvents = ref(false)

const stats = ref({ users: '52+', posts: '50+', businesses: '50+', jobs: '51+' })

const features = [
  { path:'/community',   icon:'💬', name:'커뮤니티',    desc:'한인 게시판 & 정보공유',    bg:'#dbeafe' },
  { path:'/jobs',        icon:'💼', name:'구인구직',    desc:'한인 채용공고 & 이력서',    bg:'#dcfce7' },
  { path:'/market',      icon:'🛍️', name:'중고장터',    desc:'한인 중고거래 마켓',        bg:'#fef9c3' },
  { path:'/directory',   icon:'🏪', name:'업소록',      desc:'한인 업체 검색 & 리뷰',     bg:'#f3e8ff' },
  { path:'/ride',        icon:'🚗', name:'알바 라이드', desc:'한인 카풀 & 라이드셰어',    bg:'#fee2e2' },
  { path:'/match',       icon:'💝', name:'매칭',        desc:'나이별 한인 매칭 서비스',   bg:'#fce7f3' },
  { path:'/chat',        icon:'💬', name:'실시간 채팅', desc:'지역별 · 테마별 단톡방',   bg:'#e0f2fe' },
  { path:'/games',       icon:'🀄', name:'게임 & 포인트','desc':'고스톱 · 포커 · 퀴즈',  bg:'#fef3c7' },
  { path:'/events',      icon:'🎉', name:'이벤트',      desc:'한인 모임 & 행사',          bg:'#d1fae5' },
  { path:'/elder',       icon:'💙', name:'노인 안심',   desc:'체크인 & 보호자 알림',      bg:'#e0e7ff' },
  { path:'/games/quiz',  icon:'🧠', name:'일일 퀴즈',   desc:'매일 20P 적립',             bg:'#fce7f3' },
  { path:'/news',        icon:'📰', name:'한인 뉴스',   desc:'이민 · 경제 · 생활 뉴스',  bg:'#f0fdf4' },
]

function jobTypeLabel(type) {
  return { full_time:'풀타임', part_time:'파트타임', contract:'계약직', freelance:'프리랜서' }[type] ?? type
}
function jobTypeColor(type) {
  return { full_time:'bg-blue-100 text-blue-700', part_time:'bg-green-100 text-green-700',
           contract:'bg-orange-100 text-orange-700', freelance:'bg-purple-100 text-purple-700' }[type] ?? 'bg-gray-100 text-gray-600'
}
function categoryEmoji(cat) {
  return { Electronics:'📱', Furniture:'🛋️', Vehicles:'🚗', Appliances:'🏠', Clothing:'👕',
           Sports:'⚽', Baby:'👶', Books:'📚', Food:'🍱', Retail:'🛒' }[cat] ?? '📦'
}
function formatEventDate(dt) {
  if (!dt) return { month: '?', day: '?' }
  const d = new Date(dt)
  return { month: d.toLocaleDateString('ko-KR',{month:'short'}), day: d.getDate() }
}

async function loadAll() {
  loadingPosts.value  = true
  loadingJobs.value   = true
  loadingMarket.value = true
  loadingEvents.value = true

  const [postsRes, jobsRes, marketRes, eventsRes] = await Promise.allSettled([
    axios.get('/api/posts?per_page=5'),
    axios.get('/api/jobs?per_page=6'),
    axios.get('/api/market?per_page=6'),
    axios.get('/api/events?per_page=3'),
  ])

  if (postsRes.status === 'fulfilled')  posts.value  = postsRes.value.data?.data?.slice(0, 5)  ?? []
  if (jobsRes.status === 'fulfilled')   jobs.value   = jobsRes.value.data?.data?.slice(0, 6)   ?? []
  if (marketRes.status === 'fulfilled') market.value = marketRes.value.data?.data?.slice(0, 6) ?? []
  if (eventsRes.status === 'fulfilled') events.value = eventsRes.value.data?.data?.slice(0, 3) ?? []

  loadingPosts.value  = false
  loadingJobs.value   = false
  loadingMarket.value = false
  loadingEvents.value = false
}

onMounted(loadAll)
</script>
