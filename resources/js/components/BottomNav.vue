<template>
  <nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50 md:hidden">
    <div class="grid grid-cols-5 h-16">
      <RouterLink to="/" class="bottom-nav-item" :class="{ active: $route.path === '/' }">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
        </svg>
        <span>홈</span>
      </RouterLink>

      <RouterLink to="/community" class="bottom-nav-item" :class="{ active: $route.path.startsWith('/community') }">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
        </svg>
        <span>커뮤니티</span>
      </RouterLink>

      <RouterLink to="/chat" class="bottom-nav-item" :class="{ active: $route.path.startsWith('/chat') }">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
        </svg>
        <span>채팅</span>
      </RouterLink>

      <RouterLink to="/shorts" class="bottom-nav-item" :class="{ active: $route.path.startsWith('/shorts') }">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span>숏츠</span>
      </RouterLink>

      <RouterLink v-if="auth.isLoggedIn" to="/notifications" class="bottom-nav-item relative"
        :class="{ active: $route.path.startsWith('/notifications') }">
        <div class="relative">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
          </svg>
          <span v-if="unreadCount > 0"
            class="absolute -top-1 -right-1 w-3.5 h-3.5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center leading-none" style="font-size:9px">
            {{ unreadCount > 9 ? '9+' : unreadCount }}
          </span>
        </div>
        <span>알림</span>
      </RouterLink>
      <RouterLink v-else to="/auth/login" class="bottom-nav-item">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
        </svg>
        <span>로그인</span>
      </RouterLink>
    </div>
  </nav>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import axios from 'axios'

const auth = useAuthStore()
const unreadCount = ref(0)

onMounted(async () => {
  if (auth.isLoggedIn) {
    try {
      const { data } = await axios.get('/api/notifications/unread')
      unreadCount.value = data.count
    } catch { }
  }
})
</script>

<style scoped>
.bottom-nav-item {
  @apply flex flex-col items-center justify-center gap-0.5 text-gray-400 text-xs py-2 transition;
}
.bottom-nav-item.active,
.bottom-nav-item.router-link-active {
  @apply text-blue-600;
}
</style>
