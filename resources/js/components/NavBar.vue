<template>
  <nav class="bg-white shadow-sm sticky top-0 z-50">
    <!-- Row 1: Logo + Search + User Actions -->
    <div class="max-w-[1200px] mx-auto px-4 flex items-center h-12 gap-3">
      <RouterLink to="/" class="flex-shrink-0">
        <img src="/images/logo.jpg" alt="SomeKorean" class="h-8" />
      </RouterLink>

      <!-- Search -->
      <form @submit.prevent="goSearch" class="flex-1 max-w-xs hidden md:flex">
        <div class="relative w-full">
          <input v-model="searchQ" type="text" placeholder="검색어를 입력하세요..."
            class="w-full border border-gray-200 rounded-lg px-3 py-1.5 pr-8 text-xs focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
          <button type="submit" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-blue-500">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
          </button>
        </div>
      </form>

      <!-- Right Side -->
      <div class="ml-auto flex items-center gap-1.5 flex-shrink-0">
        <template v-if="auth.isLoggedIn">
          <!-- 알림 -->
          <RouterLink to="/notifications" class="relative p-1.5 text-gray-500 hover:text-blue-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            <span v-if="unreadNotifs > 0"
              class="absolute top-0 right-0 w-4 h-4 bg-red-500 text-white text-[10px] rounded-full flex items-center justify-center leading-none font-bold">
              {{ unreadNotifs > 9 ? '9+' : unreadNotifs }}
            </span>
          </RouterLink>
          <!-- 메시지 -->
          <RouterLink to="/messages" class="p-1.5 text-gray-500 hover:text-blue-600 hidden sm:block">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
            </svg>
          </RouterLink>
          <!-- 포인트 -->
          <RouterLink to="/points" class="hidden md:flex items-center gap-0.5 text-xs text-yellow-600 font-bold bg-yellow-50 px-2.5 py-1 rounded-lg hover:bg-yellow-100 transition">
            ⭐ {{ (auth.user?.points_total ?? 0).toLocaleString() }}P
          </RouterLink>
          <!-- 아바타 → 대쉬보드 -->
          <RouterLink to="/dashboard"
            class="w-8 h-8 rounded-full overflow-hidden bg-blue-500 text-white flex items-center justify-center text-sm font-bold hover:ring-2 hover:ring-blue-300 flex-shrink-0 transition">
            <img v-if="auth.user?.avatar" :src="auth.user.avatar" class="w-full h-full object-cover" />
            <span v-else>{{ (auth.user?.name || auth.user?.username || '?')[0] }}</span>
          </RouterLink>
          <button @click="logout" class="text-xs text-gray-400 hover:text-red-400 hidden lg:block ml-1">로그아웃</button>
        </template>
        <template v-else>
          <RouterLink to="/auth/login"    class="text-sm text-gray-600 hover:text-blue-600 px-3 py-1">로그인</RouterLink>
          <RouterLink to="/auth/register" class="text-sm bg-blue-600 text-white px-3 py-1.5 rounded-lg hover:bg-blue-700">회원가입</RouterLink>
        </template>
      </div>
    </div>

    <!-- Row 2: Navigation Links -->
    <div class="border-t border-gray-100 bg-gray-50/80">
      <div class="max-w-[1200px] mx-auto px-4 flex items-center h-9 overflow-x-auto scrollbar-hide gap-0.5">
        <RouterLink to="/community" class="nav-link">지식인</RouterLink>
        <RouterLink to="/clubs"     class="nav-link">동호회</RouterLink>
        <RouterLink to="/jobs"      class="nav-link">구인구직</RouterLink>
        <RouterLink to="/market"    class="nav-link">중고장터</RouterLink>
        <RouterLink to="/realestate" class="nav-link">부동산</RouterLink>
        <RouterLink to="/directory" class="nav-link">업소록</RouterLink>
        <RouterLink to="/chat"      class="nav-link">채팅</RouterLink>
        <RouterLink to="/friends"   class="nav-link">친구</RouterLink>
        <RouterLink to="/match"     class="nav-link">매칭</RouterLink>
        <RouterLink to="/games"     class="nav-link">게임</RouterLink>
        <RouterLink to="/events"    class="nav-link">이벤트</RouterLink>
        <RouterLink to="/ride"      class="nav-link">알바라이드</RouterLink>
        <RouterLink to="/elder"     class="nav-link">안심서비스</RouterLink>
        <RouterLink to="/news"      class="nav-link">뉴스</RouterLink>
      </div>
    </div>
  </nav>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import { useRouter } from 'vue-router'
import axios from 'axios'

const auth   = useAuthStore()
const router = useRouter()
const unreadNotifs = ref(0)
const searchQ = ref('')

async function logout() {
  await auth.logout()
  router.push('/')
}

function goSearch() {
  if (!searchQ.value.trim()) return
  router.push({ name: 'search', query: { q: searchQ.value } })
  searchQ.value = ''
}

onMounted(async () => {
  if (auth.isLoggedIn) {
    try {
      const { data } = await axios.get('/api/notifications/unread')
      unreadNotifs.value = data.count
    } catch { }
  }
})
</script>

<style scoped>
.nav-link {
  @apply text-xs text-gray-600 hover:text-blue-600 px-2.5 py-1.5 rounded-md hover:bg-blue-50 transition font-semibold whitespace-nowrap;
}
.router-link-active.nav-link {
  @apply text-blue-600 bg-blue-50;
}
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>
