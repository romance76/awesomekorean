<template>
<div class="min-h-screen bg-gray-100 flex">
  <!-- 사이드바 -->
  <aside class="w-60 bg-white border-r hidden lg:flex flex-col h-screen sticky top-0">
    <!-- 헤더 -->
    <div class="p-4 border-b bg-amber-50">
      <div class="font-black text-amber-800 text-sm">⚙️ SomeKorean 관리자</div>
      <div v-if="auth.user" class="flex items-center gap-2 mt-2">
        <div class="w-8 h-8 bg-amber-400 rounded-full flex items-center justify-center text-white text-xs font-bold">{{ (auth.user.name||'?')[0] }}</div>
        <div>
          <div class="text-xs font-semibold text-gray-800">{{ auth.user.name }}</div>
          <div class="text-[10px] text-amber-600 font-bold">Super Admin</div>
        </div>
      </div>
    </div>

    <!-- 메뉴 -->
    <nav class="flex-1 overflow-y-auto py-2">
      <div v-for="group in menuGroups" :key="group.title" class="mb-2">
        <div class="px-4 py-1.5 text-[10px] text-gray-400 font-bold uppercase tracking-wider">{{ group.title }}</div>
        <RouterLink v-for="item in group.items" :key="item.to" :to="item.to"
          class="flex items-center gap-2 px-4 py-2 text-sm transition"
          :class="$route.path === item.to ? 'bg-amber-50 text-amber-700 font-bold border-r-2 border-amber-500' : 'text-gray-600 hover:bg-amber-50/50 hover:text-amber-700'">
          <span class="text-xs">{{ item.icon }}</span>
          <span>{{ item.label }}</span>
        </RouterLink>
      </div>
    </nav>

    <!-- 하단 -->
    <div class="p-3 border-t">
      <RouterLink to="/" class="flex items-center gap-2 text-xs text-gray-500 hover:text-amber-700 py-1">🏠 사이트로 돌아가기</RouterLink>
    </div>
  </aside>

  <!-- 모바일 메뉴 버튼 -->
  <div class="lg:hidden fixed top-0 left-0 right-0 bg-white border-b z-40 px-4 py-3 flex items-center justify-between">
    <div class="font-black text-amber-800 text-sm">⚙️ 관리자</div>
    <button @click="mobileMenu=!mobileMenu" class="text-gray-600">☰</button>
  </div>

  <!-- 모바일 사이드바 -->
  <div v-if="mobileMenu" class="lg:hidden fixed inset-0 z-50" @click.self="mobileMenu=false">
    <div class="absolute left-0 top-0 h-full w-64 bg-white shadow-xl overflow-y-auto">
      <div class="p-4 border-b flex justify-between items-center">
        <span class="font-black text-amber-800 text-sm">⚙️ 관리자</span>
        <button @click="mobileMenu=false" class="text-gray-400">✕</button>
      </div>
      <nav class="py-2">
        <RouterLink v-for="item in allItems" :key="item.to" :to="item.to" @click="mobileMenu=false"
          class="block px-4 py-2 text-sm text-gray-600 hover:bg-amber-50">{{ item.icon }} {{ item.label }}</RouterLink>
      </nav>
    </div>
  </div>

  <!-- 메인 콘텐츠 -->
  <main class="flex-1 p-4 lg:p-6 lg:mt-0 mt-14">
    <router-view />
  </main>
</div>
</template>
<script setup>
import { ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'

const auth = useAuthStore()
const route = useRoute()
const mobileMenu = ref(false)

const menuGroups = [
  {
    title: '대시보드',
    items: [
      { to: '/admin', icon: '📊', label: '대시보드' },
    ]
  },
  {
    title: '회원',
    items: [
      { to: '/admin/members', icon: '👥', label: '회원관리' },
      { to: '/admin/friends', icon: '👫', label: '친구' },
    ]
  },
  {
    title: '콘텐츠',
    items: [
      { to: '/admin/content', icon: '📝', label: '게시글' },
      { to: '/admin/boards', icon: '📋', label: '게시판' },
      { to: '/admin/news', icon: '📰', label: '뉴스' },
      { to: '/admin/jobs', icon: '💼', label: '구인구직' },
      { to: '/admin/market', icon: '🛒', label: '장터' },
      { to: '/admin/realestate', icon: '🏠', label: '부동산' },
      { to: '/admin/clubs', icon: '👥', label: '동호회' },
      { to: '/admin/qa', icon: '❓', label: 'Q&A' },
      { to: '/admin/recipes', icon: '🍳', label: '레시피' },
      { to: '/admin/events', icon: '🎉', label: '이벤트' },
    ]
  },
  {
    title: '서비스',
    items: [
      { to: '/admin/directory', icon: '🏪', label: '업소록' },
      { to: '/admin/games', icon: '🎮', label: '게임' },
      { to: '/admin/music', icon: '🎵', label: '음악' },
      { to: '/admin/shorts', icon: '📱', label: '숏츠' },
      { to: '/admin/shopping', icon: '🛍️', label: '쇼핑' },
      { to: '/admin/groupbuy', icon: '🤝', label: '공동구매' },
      { to: '/admin/elder', icon: '💙', label: '안심서비스' },
      { to: '/admin/chats', icon: '💬', label: '채팅' },
    ]
  },
  {
    title: '시스템',
    items: [
      { to: '/admin/banners', icon: '🖼️', label: '배너' },
      { to: '/admin/payments', icon: '💳', label: '결제' },
      { to: '/admin/security', icon: '🔒', label: '보안' },
      { to: '/admin/settings', icon: '⚙️', label: '설정' },
      { to: '/admin/system', icon: '🖥️', label: '시스템' },
    ]
  },
]

const allItems = computed(() => menuGroups.flatMap(g => g.items))
</script>
