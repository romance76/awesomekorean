<template>
<div class="min-h-screen bg-gray-100 flex">
  <!-- 사이드바 -->
  <aside class="w-48 bg-white border-r hidden lg:flex flex-col h-screen sticky top-0">
    <div class="p-3 border-b bg-amber-50">
      <div class="font-black text-amber-800 text-sm">⚙️ AwesomeKorean</div>
      <div v-if="auth.user" class="flex items-center gap-2 mt-2">
        <div class="w-7 h-7 bg-amber-400 rounded-full flex items-center justify-center text-white text-[10px] font-bold">{{ (auth.user.name||'?')[0] }}</div>
        <div>
          <div class="text-[10px] font-semibold text-gray-800">{{ auth.user.name }}</div>
          <div class="text-[9px] text-amber-600 font-bold">Admin</div>
        </div>
      </div>
    </div>
    <nav class="flex-1 overflow-y-auto py-2">
      <RouterLink v-for="item in mainMenu" :key="item.to" :to="item.to"
        class="flex items-center gap-2 px-4 py-2.5 text-sm transition"
        :class="isMainActive(item) ? 'bg-amber-50 text-amber-700 font-bold border-r-2 border-amber-500' : 'text-gray-600 hover:bg-amber-50/50'">
        <span>{{ item.icon }}</span>
        <span>{{ item.label }}</span>
      </RouterLink>
    </nav>
    <div class="p-3 border-t">
      <RouterLink to="/" class="flex items-center gap-2 text-[10px] text-gray-500 hover:text-amber-700 py-1">🏠 사이트로 돌아가기</RouterLink>
    </div>
  </aside>

  <!-- 모바일 메뉴 -->
  <div class="lg:hidden fixed top-0 left-0 right-0 bg-white border-b z-40 px-4 py-3 flex items-center justify-between">
    <div class="font-black text-amber-800 text-sm">⚙️ 관리자</div>
    <button @click="mobileMenu=!mobileMenu" class="text-gray-600">☰</button>
  </div>
  <div v-if="mobileMenu" class="lg:hidden fixed inset-0 z-50" @click.self="mobileMenu=false">
    <div class="absolute left-0 top-0 h-full w-56 bg-white shadow-xl overflow-y-auto">
      <div class="p-4 border-b flex justify-between items-center">
        <span class="font-black text-amber-800 text-sm">⚙️ 관리자</span>
        <button @click="mobileMenu=false" class="text-gray-400">✕</button>
      </div>
      <nav class="py-2">
        <RouterLink v-for="item in mainMenu" :key="item.to" :to="item.to" @click="mobileMenu=false"
          class="block px-4 py-2.5 text-sm text-gray-600 hover:bg-amber-50">{{ item.icon }} {{ item.label }}</RouterLink>
      </nav>
    </div>
  </div>

  <!-- 메인 -->
  <main class="flex-1 lg:mt-0 mt-14">
    <!-- 서브 탭 (콘텐츠/서비스/시스템일 때) -->
    <div v-if="currentSubTabs.length" class="bg-white border-b px-4 py-0 flex gap-0 overflow-x-auto scrollbar-hide">
      <RouterLink v-for="tab in currentSubTabs" :key="tab.to" :to="tab.to"
        class="flex items-center gap-1 px-3 py-3 text-xs font-bold border-b-2 transition whitespace-nowrap"
        :class="$route.path === tab.to ? 'border-amber-500 text-amber-700' : 'border-transparent text-gray-400 hover:text-gray-600'">
        <span>{{ tab.icon }}</span>
        <span>{{ tab.label }}</span>
      </RouterLink>
    </div>
    <div class="p-4 lg:p-6">
      <router-view />
    </div>
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

// 왼쪽 메인 메뉴 (5개)
const mainMenu = [
  { to: '/admin', icon: '📊', label: '대시보드', group: 'main' },
  { to: '/admin/members', icon: '👥', label: '회원', group: 'member' },
  { to: '/admin/market', icon: '📋', label: '게시판', group: 'board' },
  { to: '/admin/banners', icon: '📢', label: '광고관리', group: 'ad' },
  { to: '/admin/settings', icon: '⚙️', label: '시스템', group: 'system' },
]

// 각 그룹의 서브 탭 (콘텐츠 + 서비스 통합)
const subTabs = {
  member: [
    { to: '/admin/members', icon: '👥', label: '회원관리' },
    { to: '/admin/friends', icon: '👫', label: '친구' },
  ],
  board: [
    { to: '/admin/community', icon: '💬', label: '커뮤니티' },
    { to: '/admin/market', icon: '🛒', label: '장터' },
    { to: '/admin/jobs', icon: '💼', label: '구인구직' },
    { to: '/admin/realestate', icon: '🏠', label: '부동산' },
    { to: '/admin/qa', icon: '❓', label: 'Q&A' },
    { to: '/admin/events', icon: '🎉', label: '이벤트' },
    { to: '/admin/clubs', icon: '👥', label: '동호회' },
    { to: '/admin/recipes', icon: '🍳', label: '레시피' },
    { to: '/admin/news', icon: '📰', label: '뉴스' },
    { to: '/admin/directory', icon: '🏪', label: '업소록' },
    { to: '/admin/groupbuy', icon: '🛍', label: '공동구매' },
    { to: '/admin/music', icon: '🎵', label: '음악' },
    { to: '/admin/shorts', icon: '🎬', label: '숏츠' },
    { to: '/admin/shopping', icon: '🛒', label: '쇼핑' },
    { to: '/admin/games', icon: '🎮', label: '게임' },
    { to: '/admin/poker', icon: '♠️', label: '포커' },
    { to: '/admin/elder', icon: '💙', label: '안심' },
    { to: '/admin/communication', icon: '💬', label: '채팅·통화' },
    { to: '/admin/claims', icon: '📋', label: '클레임' },
  ],
  ad: [
    { to: '/admin/ad-center', icon: '🎯', label: '광고 센터' },
    { to: '/admin/banners', icon: '📢', label: '광고 목록' },
    { to: '/admin/payments', icon: '💳', label: '결제/오더' },
  ],
  system: [
    { to: '/admin/security', icon: '🔒', label: '보안/신고' },
    { to: '/admin/settings', icon: '⚙️', label: '설정' },
    { to: '/admin/pricing', icon: '💰', label: '가격/할인' },
    { to: '/admin/hero-banners', icon: '🎪', label: '히어로 배너' },
    { to: '/admin/popup-banners', icon: '💬', label: '팝업 배너' },
    { to: '/admin/system', icon: '🖥️', label: '시스템' },
  ],
}

// 현재 페이지가 어떤 그룹에 속하는지
const currentGroup = computed(() => {
  const path = route.path
  for (const [group, tabs] of Object.entries(subTabs)) {
    if (tabs.some(t => path === t.to || (t.to !== '/admin' && path.startsWith(t.to)))) return group
  }
  return 'main'
})

const currentSubTabs = computed(() => subTabs[currentGroup.value] || [])

function isMainActive(item) {
  if (item.to === '/admin' && item.group === 'main') return route.path === '/admin'
  return currentGroup.value === item.group
}
</script>
