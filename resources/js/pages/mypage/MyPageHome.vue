<template>
  <!-- /mypage (홈) — Phase 2-C Post UX: 카드 대시보드 -->
  <div class="space-y-4">
    <!-- 환영 메시지 + 프로필 완성도 -->
    <div v-if="data" class="bg-gradient-to-br from-amber-400 via-orange-400 to-pink-400 rounded-2xl p-5 text-white shadow-lg">
      <div class="flex items-center gap-4 mb-4">
        <img :src="data.user.avatar || '/images/default-avatar.png'" @error="$event.target.src='/images/default-avatar.png'" class="w-16 h-16 rounded-full border-2 border-white bg-white" />
        <div class="flex-1">
          <p class="text-sm opacity-90">안녕하세요 👋</p>
          <h2 class="text-2xl font-bold">{{ data.user.nickname || data.user.name }}</h2>
          <p class="text-xs opacity-80">{{ data.user.email }}</p>
        </div>
      </div>

      <!-- 프로필 완성도 -->
      <div v-if="data.profile_completion.percentage < 100" class="bg-white/20 backdrop-blur rounded-xl p-3">
        <div class="flex items-center justify-between mb-2">
          <p class="text-sm font-semibold">🎯 프로필 완성도</p>
          <p class="text-lg font-bold">{{ data.profile_completion.percentage }}%</p>
        </div>
        <div class="w-full bg-white/30 rounded-full h-2 mb-2 overflow-hidden">
          <div class="bg-white h-2 rounded-full transition-all" :style="{ width: data.profile_completion.percentage + '%' }"></div>
        </div>
        <div class="flex flex-wrap gap-1.5">
          <router-link
            v-for="s in data.profile_completion.suggestions.slice(0, 3)" :key="s.key"
            :to="profileLink(s.key)"
            class="text-xs bg-white/30 hover:bg-white/50 px-2 py-0.5 rounded-full"
          >+ {{ s.label }}</router-link>
        </div>
      </div>
      <div v-else class="bg-white/20 rounded-xl p-3 text-center">
        <p class="text-sm">✨ 프로필 완성! 모든 기능을 마음껏 이용하세요.</p>
      </div>
    </div>

    <!-- 주요 지표 카드 4개 -->
    <div v-if="data" class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      <router-link to="/mypage/points" class="bg-white rounded-xl shadow-sm p-4 hover:shadow-md transition group">
        <div class="flex items-center justify-between mb-1">
          <span class="text-2xl">💰</span>
          <span class="text-xs text-amber-500 group-hover:translate-x-1 transition">→</span>
        </div>
        <p class="text-xs text-gray-500">포인트</p>
        <p class="text-xl font-bold">{{ (data.user.points || 0).toLocaleString() }}</p>
      </router-link>

      <router-link to="/mypage/notifications" class="bg-white rounded-xl shadow-sm p-4 hover:shadow-md transition group relative">
        <div class="flex items-center justify-between mb-1">
          <span class="text-2xl">🔔</span>
          <span class="text-xs text-amber-500 group-hover:translate-x-1 transition">→</span>
        </div>
        <p class="text-xs text-gray-500">안 읽은 알림</p>
        <p class="text-xl font-bold">{{ data.unread.notifications }}</p>
        <span v-if="data.unread.notifications > 0" class="absolute top-2 right-2 bg-red-500 w-2 h-2 rounded-full"></span>
      </router-link>

      <router-link to="/mypage/messages" class="bg-white rounded-xl shadow-sm p-4 hover:shadow-md transition group relative">
        <div class="flex items-center justify-between mb-1">
          <span class="text-2xl">✉️</span>
          <span class="text-xs text-amber-500 group-hover:translate-x-1 transition">→</span>
        </div>
        <p class="text-xs text-gray-500">안 읽은 쪽지</p>
        <p class="text-xl font-bold">{{ data.unread.messages }}</p>
        <span v-if="data.unread.messages > 0" class="absolute top-2 right-2 bg-red-500 w-2 h-2 rounded-full"></span>
      </router-link>

      <router-link to="/mypage/friends" class="bg-white rounded-xl shadow-sm p-4 hover:shadow-md transition group relative">
        <div class="flex items-center justify-between mb-1">
          <span class="text-2xl">👫</span>
          <span class="text-xs text-amber-500 group-hover:translate-x-1 transition">→</span>
        </div>
        <p class="text-xs text-gray-500">친구</p>
        <p class="text-xl font-bold">{{ data.counts.friends }}</p>
        <span v-if="data.unread.friend_requests > 0" class="absolute top-2 right-2 bg-red-500 text-white text-xs rounded-full px-1.5 font-bold">{{ data.unread.friend_requests }}</span>
      </router-link>
    </div>

    <!-- 빠른 작업 바 -->
    <div class="bg-white rounded-xl shadow-sm p-4">
      <p class="text-xs text-gray-500 font-semibold mb-3">⚡ 빠른 작업</p>
      <div class="grid grid-cols-4 sm:grid-cols-6 gap-3">
        <router-link v-for="qa in quickActions" :key="qa.to" :to="qa.to"
          class="flex flex-col items-center gap-1 p-3 rounded-lg hover:bg-amber-50 transition">
          <span class="text-2xl">{{ qa.icon }}</span>
          <span class="text-xs text-gray-600 text-center">{{ qa.label }}</span>
        </router-link>
      </div>
    </div>

    <div v-if="data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <!-- 이번 달 활동 -->
      <div class="bg-white rounded-xl shadow-sm p-4">
        <h3 class="font-semibold mb-3">📅 이번 달 활동</h3>
        <div class="grid grid-cols-2 gap-3">
          <div class="bg-amber-50 rounded-lg p-3 text-center">
            <p class="text-xs text-gray-500">쓴 글</p>
            <p class="text-2xl font-bold text-amber-600">{{ data.this_month.posts_this_month }}</p>
          </div>
          <div class="bg-amber-50 rounded-lg p-3 text-center">
            <p class="text-xs text-gray-500">댓글</p>
            <p class="text-2xl font-bold text-amber-600">{{ data.this_month.comments_this_month }}</p>
          </div>
          <div class="bg-green-50 rounded-lg p-3 text-center">
            <p class="text-xs text-gray-500">포인트 획득</p>
            <p class="text-lg font-bold text-green-600">+{{ data.this_month.points_earned_this_month.toLocaleString() }}</p>
          </div>
          <div class="bg-red-50 rounded-lg p-3 text-center">
            <p class="text-xs text-gray-500">포인트 사용</p>
            <p class="text-lg font-bold text-red-500">-{{ data.this_month.points_spent_this_month.toLocaleString() }}</p>
          </div>
        </div>
      </div>

      <!-- 내 콘텐츠 -->
      <div class="bg-white rounded-xl shadow-sm p-4">
        <h3 class="font-semibold mb-3">📦 내 콘텐츠</h3>
        <div class="space-y-2">
          <router-link to="/mypage/posts" class="flex items-center justify-between p-2 rounded hover:bg-amber-50">
            <span class="text-sm">📝 게시글</span>
            <span class="text-sm font-bold">{{ data.counts.posts }}</span>
          </router-link>
          <router-link to="/mypage/market" class="flex items-center justify-between p-2 rounded hover:bg-amber-50">
            <span class="text-sm">🛒 내 장터</span>
            <span class="text-sm font-bold">{{ data.counts.market }}</span>
          </router-link>
          <router-link to="/mypage/realestate" class="flex items-center justify-between p-2 rounded hover:bg-amber-50">
            <span class="text-sm">🏠 부동산 매물</span>
            <span class="text-sm font-bold">{{ data.counts.realestate }}</span>
          </router-link>
          <router-link to="/mypage/jobs" class="flex items-center justify-between p-2 rounded hover:bg-amber-50">
            <span class="text-sm">💼 구인 공고</span>
            <span class="text-sm font-bold">{{ data.counts.jobs }}</span>
          </router-link>
          <router-link to="/mypage/bookmarks" class="flex items-center justify-between p-2 rounded hover:bg-amber-50">
            <span class="text-sm">🔖 북마크</span>
            <span class="text-sm font-bold">{{ data.counts.bookmarks }}</span>
          </router-link>
        </div>
      </div>
    </div>

    <!-- 최근 활동 피드 -->
    <div v-if="data?.recent_activity?.length" class="bg-white rounded-xl shadow-sm p-4">
      <h3 class="font-semibold mb-3">🕐 최근 활동</h3>
      <ul class="divide-y">
        <li v-for="(a, i) in data.recent_activity.slice(0, 5)" :key="i" class="py-2 flex items-center gap-3">
          <span class="text-xl">{{ actionIcon(a.event_type) }}</span>
          <div class="flex-1 min-w-0">
            <p class="text-sm">{{ actionLabel(a.event_type) }}</p>
            <p class="text-xs text-gray-500 truncate">{{ parseMeta(a.meta)?.title || '' }}</p>
          </div>
          <span class="text-xs text-gray-400 whitespace-nowrap">{{ fmtRelative(a.occurred_at) }}</span>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const data = ref(null)

const quickActions = [
  { to: '/community/write', icon: '✍️', label: '글 쓰기' },
  { to: '/market/write', icon: '🛒', label: '장터 등록' },
  { to: '/realestate/write', icon: '🏠', label: '매물 등록' },
  { to: '/jobs/write', icon: '💼', label: '구인 등록' },
  { to: '/mypage/profile', icon: '👤', label: '프로필' },
  { to: '/mypage/security', icon: '🔒', label: '보안' },
]

const profileLink = (key) => {
  if (key === 'email_verified') return '/mypage/security'
  return '/mypage/profile'
}

const actionIcon = (type) => ({
  'post.created': '📝', 'comment.created': '💬',
  'marketitem.created': '🛒', 'realestatelisting.created': '🏠',
  'job.created': '💼',
}[type] || '⚪')

const actionLabel = (type) => ({
  'post.created': '게시글 작성',
  'comment.created': '댓글 작성',
  'marketitem.created': '장터 물품 등록',
  'realestatelisting.created': '부동산 매물 등록',
  'job.created': '구인 공고 등록',
  'post.deleted': '게시글 삭제',
}[type] || type)

const fmtRelative = (s) => {
  if (!s) return ''
  const diff = Date.now() - new Date(s).getTime()
  const min = Math.floor(diff / 60000)
  if (min < 1) return '방금'
  if (min < 60) return `${min}분 전`
  const hr = Math.floor(min / 60)
  if (hr < 24) return `${hr}시간 전`
  const day = Math.floor(hr / 24)
  return `${day}일 전`
}

const parseMeta = (m) => {
  if (!m) return null
  try { return typeof m === 'string' ? JSON.parse(m) : m } catch { return null }
}

onMounted(async () => {
  try {
    const { data: res } = await axios.get('/api/mypage/home')
    data.value = res.data
  } catch {}
})
</script>
