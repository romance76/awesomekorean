<template>
  <nav class="bg-white border-b border-gray-200 sticky top-0 z-50">
    <!-- Row 1: Logo + Search + Auth -->
    <div class="max-w-7xl mx-auto px-4 flex items-center h-14">
      <!-- Logo -->
      <RouterLink to="/" class="flex items-center gap-2 flex-shrink-0">
        <div class="w-8 h-8 bg-amber-400 rounded-lg flex items-center justify-center text-sm font-black text-amber-900">SK</div>
        <div class="hidden sm:block">
          <div class="font-black text-gray-900 text-sm leading-none">SomeKorean</div>
          <div class="text-[10px] text-amber-600 font-medium">미국 한인 커뮤니티</div>
        </div>
      </RouterLink>

      <!-- Search -->
      <div class="flex-1 max-w-lg mx-4">
        <form @submit.prevent="goSearch" class="flex border-2 border-amber-400 rounded-lg overflow-hidden">
          <input v-model="searchQ" type="text" placeholder="검색어를 입력하세요"
            class="flex-1 px-3 py-1.5 text-sm outline-none" />
          <button type="submit" class="bg-amber-400 px-4 text-amber-900 hover:bg-amber-500 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          </button>
        </form>
      </div>

      <!-- Auth -->
      <div class="flex items-center gap-2 flex-shrink-0">
        <template v-if="auth.isLoggedIn">
          <RouterLink to="/notifications" class="relative p-1.5 text-gray-500 hover:text-amber-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            <span v-if="unreadCount>0" class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[9px] w-4 h-4 rounded-full flex items-center justify-center font-bold">{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
          </RouterLink>
          <!-- 프로필 드롭다운 -->
          <div class="relative">
            <button @click="showDropdown=!showDropdown" class="w-8 h-8 rounded-full bg-amber-500 text-white flex items-center justify-center text-sm font-bold focus:ring-2 focus:ring-amber-300">
              {{ (auth.user?.name || '?')[0] }}
            </button>
            <div v-if="showDropdown" class="absolute right-0 top-10 bg-white border border-gray-200 rounded-xl shadow-lg py-2 w-48 z-50" @click="showDropdown=false">
              <div class="px-4 py-2 border-b">
                <div class="text-sm font-bold text-gray-800 truncate">{{ auth.user?.name }}</div>
                <div class="text-[10px] text-gray-400 truncate">{{ auth.user?.email }}</div>
                <div class="text-[10px] text-amber-600 font-semibold mt-0.5">{{ auth.user?.points || 0 }}P</div>
              </div>
              <RouterLink to="/dashboard" class="block px-4 py-2 text-sm text-gray-600 hover:bg-amber-50 hover:text-amber-700">👤 마이페이지</RouterLink>
              <RouterLink to="/profile/edit" class="block px-4 py-2 text-sm text-gray-600 hover:bg-amber-50 hover:text-amber-700">✏️ 프로필 수정</RouterLink>
              <RouterLink to="/points" class="block px-4 py-2 text-sm text-gray-600 hover:bg-amber-50 hover:text-amber-700">💰 포인트</RouterLink>
              <RouterLink to="/bookmarks" class="block px-4 py-2 text-sm text-gray-600 hover:bg-amber-50 hover:text-amber-700">🔖 북마크</RouterLink>
              <RouterLink to="/messages" class="block px-4 py-2 text-sm text-gray-600 hover:bg-amber-50 hover:text-amber-700">✉️ 쪽지</RouterLink>
              <RouterLink to="/friends" class="block px-4 py-2 text-sm text-gray-600 hover:bg-amber-50 hover:text-amber-700">👫 친구</RouterLink>
              <div v-if="auth.isAdmin">
                <div class="border-t my-1"></div>
                <RouterLink to="/admin" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50">🔧 관리자</RouterLink>
              </div>
              <div class="border-t my-1"></div>
              <button @click="handleLogout" class="w-full text-left px-4 py-2 text-sm text-gray-500 hover:bg-gray-50 hover:text-red-500">🚪 로그아웃</button>
            </div>
          </div>
        </template>
        <template v-else>
          <RouterLink to="/login" class="text-sm text-gray-600 hover:text-amber-700 px-2 py-1 hidden sm:block">로그인</RouterLink>
          <RouterLink to="/register" class="text-sm bg-amber-400 text-amber-900 font-bold px-4 py-1.5 rounded-lg hover:bg-amber-500">회원가입</RouterLink>
        </template>
        <button @click="langStore.toggle()" class="text-xs font-bold px-2 py-1 rounded border border-gray-200 ml-1">
          {{ langStore.locale === 'ko' ? 'EN' : '한' }}
        </button>
      </div>
    </div>

    <!-- Row 2: Navigation tabs (네이버 카페 스타일 탭) -->
    <div class="border-t border-gray-100 hidden md:block">
      <div class="max-w-7xl mx-auto px-4 flex items-center h-10 overflow-x-auto scrollbar-hide gap-0">
        <RouterLink v-for="item in navItems" :key="item.to" :to="item.to"
          class="text-xs font-semibold px-3 py-2.5 border-b-2 whitespace-nowrap transition"
          :class="isActive(item.to) ? 'border-amber-500 text-amber-700' : 'border-transparent text-gray-500 hover:text-amber-600 hover:border-amber-300'">
          {{ item.label }}
        </RouterLink>
      </div>
    </div>
  </nav>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useLangStore } from '../stores/lang'

const auth = useAuthStore()
const langStore = useLangStore()
const router = useRouter()
const route = useRoute()
const searchQ = ref('')
const showDropdown = ref(false)
const unreadCount = ref(0)

// 알림 뱃지 로드
async function loadUnread() {
  if (!auth.isLoggedIn) return
  try {
    const { data } = await axios.get('/api/notifications')
    const list = data.data?.data || data.data || []
    unreadCount.value = list.filter(n => !n.is_read).length
  } catch {}
}

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}

// 드롭다운 외부 클릭 닫기
if (typeof window !== 'undefined') {
  window.addEventListener('click', (e) => {
    if (showDropdown.value && !e.target.closest('.relative')) showDropdown.value = false
  })
}

onMounted(() => loadUnread())

const navItems = computed(() => {
  const ko = langStore.locale === 'ko'
  return [
    { to: '/', label: ko ? '홈' : 'Home' },
    { to: '/community', label: ko ? '커뮤니티' : 'Community' },
    { to: '/qa', label: 'Q&A' },
    { to: '/jobs', label: ko ? '구인구직' : 'Jobs' },
    { to: '/market', label: ko ? '중고장터' : 'Market' },
    { to: '/directory', label: ko ? '업소록' : 'Directory' },
    { to: '/realestate', label: ko ? '부동산' : 'Real Estate' },
    { to: '/events', label: ko ? '이벤트' : 'Events' },
    { to: '/news', label: ko ? '뉴스' : 'News' },
    { to: '/recipes', label: ko ? '레시피' : 'Recipes' },
    { to: '/clubs', label: ko ? '동호회' : 'Clubs' },
    { to: '/games', label: ko ? '게임' : 'Games' },
    { to: '/shorts', label: ko ? '숏츠' : 'Shorts' },
    { to: '/music', label: ko ? '음악듣기' : 'Music' },
    { to: '/groupbuy', label: ko ? '공동구매' : 'Group Buy' },
    { to: '/chat', label: ko ? '채팅' : 'Chat' },
    { to: '/friends', label: ko ? '친구' : 'Friends' },
  ]
})

function isActive(path) {
  if (path === '/') return route.path === '/'
  return route.path.startsWith(path)
}

function goSearch() {
  if (searchQ.value.trim()) {
    router.push({ path: '/search', query: { q: searchQ.value.trim() } })
    searchQ.value = ''
  }
}
</script>
