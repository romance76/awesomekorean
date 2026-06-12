<template>
<div class="min-h-screen bg-[#F7F8FA] flex">
  <!-- 사이드바 -->
  <aside class="w-52 bg-white border-r border-gray-100 hidden lg:flex flex-col h-screen sticky top-0">
    <div class="p-4 border-b border-gray-100">
      <div class="flex items-center gap-2">
        <span class="icon-chip w-8 h-8 bg-amber-50 text-amber-600"><AppIcon name="settings" :size="16" /></span>
        <span class="font-bold text-ink text-sm">AwesomeKorean</span>
      </div>
      <div v-if="auth.user" class="flex items-center gap-2 mt-3">
        <div class="w-8 h-8 bg-amber-400 rounded-full flex items-center justify-center text-white text-xs font-bold">{{ (auth.user.name||'?')[0] }}</div>
        <div>
          <div class="text-xs font-semibold text-ink">{{ auth.user.name }}</div>
          <div class="text-[11px] text-amber-600 font-bold">Admin</div>
        </div>
      </div>
    </div>
    <nav class="flex-1 overflow-y-auto py-2">
      <RouterLink v-for="item in mainMenu" :key="item.to" :to="item.to"
        class="flex items-center gap-2.5 px-4 py-2 text-sm transition-colors"
        :class="isMainActive(item) ? 'bg-amber-50 text-amber-700 font-bold border-r-2 border-amber-500' : 'text-ink-light hover:bg-amber-50/50'">
        <span class="icon-chip w-7 h-7" :class="item.chip"><AppIcon :name="item.icon" :size="15" /></span>
        <span>{{ item.label }}</span>
      </RouterLink>
    </nav>
    <div class="p-3 border-t border-gray-100">
      <RouterLink to="/" class="flex items-center gap-1.5 text-xs text-ink-muted hover:text-amber-600 transition-colors py-1"><AppIcon name="home" :size="14" /> 사이트로 돌아가기</RouterLink>
    </div>
  </aside>

  <!-- 모바일 메뉴 -->
  <div class="lg:hidden fixed top-0 left-0 right-0 bg-white border-b border-gray-100 z-40 px-4 py-3 flex items-center justify-between">
    <div class="flex items-center gap-2">
      <span class="icon-chip w-7 h-7 bg-amber-50 text-amber-600"><AppIcon name="settings" :size="14" /></span>
      <span class="font-bold text-ink text-sm">관리자</span>
    </div>
    <button @click="mobileMenu=!mobileMenu" class="text-ink-light p-1 transition-colors hover:text-amber-600"><AppIcon name="menu" :size="20" /></button>
  </div>
  <div v-if="mobileMenu" class="lg:hidden fixed inset-0 z-50 bg-black/30" @click.self="mobileMenu=false">
    <div class="absolute left-0 top-0 h-full w-56 bg-white shadow-xl overflow-y-auto">
      <div class="p-4 border-b border-gray-100 flex justify-between items-center">
        <span class="flex items-center gap-2 font-bold text-ink text-sm"><span class="icon-chip w-7 h-7 bg-amber-50 text-amber-600"><AppIcon name="settings" :size="14" /></span>관리자</span>
        <button @click="mobileMenu=false" class="text-ink-muted transition-colors hover:text-ink"><AppIcon name="x" :size="18" /></button>
      </div>
      <nav class="py-2">
        <RouterLink v-for="item in mainMenu" :key="item.to" :to="item.to" @click="mobileMenu=false"
          class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-ink-light hover:bg-amber-50 transition-colors">
          <span class="icon-chip w-7 h-7" :class="item.chip"><AppIcon :name="item.icon" :size="15" /></span>
          <span>{{ item.label }}</span>
        </RouterLink>
      </nav>
    </div>
  </div>

  <!-- 메인 -->
  <main class="flex-1 lg:mt-0 mt-14">
    <!-- 서브 탭 (콘텐츠/서비스/시스템일 때) -->
    <div v-if="currentSubTabs.length" class="bg-white border-b border-gray-100 px-4 py-0 flex gap-0 overflow-x-auto scrollbar-hide">
      <RouterLink v-for="tab in currentSubTabs" :key="tab.to" :to="tab.to"
        class="flex items-center gap-1.5 px-3 py-3 text-xs font-bold border-b-2 transition-colors whitespace-nowrap"
        :class="$route.path === tab.to ? 'border-amber-500 text-amber-700' : 'border-transparent text-ink-muted hover:text-ink-light'">
        <AppIcon :name="tab.icon" :size="14" />
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
import AppIcon from '../../components/AppIcon.vue'

const auth = useAuthStore()
const route = useRoute()
const mobileMenu = ref(false)

// 왼쪽 메인 메뉴 (5개)
const mainMenu = [
  { to: '/admin', icon: 'chart-bar', chip: 'bg-amber-50 text-amber-600', label: '대시보드', group: 'main' },
  { to: '/admin/members', icon: 'users', chip: 'bg-blue-50 text-blue-600', label: '회원', group: 'member' },
  { to: '/admin/market', icon: 'list', chip: 'bg-emerald-50 text-emerald-600', label: '게시판', group: 'board' },
  { to: '/admin/banners', icon: 'megaphone', chip: 'bg-violet-50 text-violet-600', label: '광고관리', group: 'ad' },
  { to: '/admin/settings', icon: 'settings', chip: 'bg-gray-100 text-ink-light', label: '시스템', group: 'system' },
]

// 각 그룹의 서브 탭 (콘텐츠 + 서비스 통합)
const subTabs = {
  member: [
    { to: '/admin/members', icon: 'users', label: '회원관리' },
    { to: '/admin/friends', icon: 'heart-handshake', label: '친구' },
  ],
  board: [
    { to: '/admin/community', icon: 'message-circle', label: '커뮤니티' },
    { to: '/admin/market', icon: 'shopping-cart', label: '장터' },
    { to: '/admin/jobs', icon: 'briefcase', label: '구인구직' },
    { to: '/admin/realestate', icon: 'building', label: '부동산' },
    { to: '/admin/qa', icon: 'help-circle', label: 'Q&A' },
    { to: '/admin/events', icon: 'calendar', label: '이벤트' },
    { to: '/admin/clubs', icon: 'users', label: '동호회' },
    { to: '/admin/recipes', icon: 'utensils', label: '레시피' },
    { to: '/admin/news', icon: 'newspaper', label: '뉴스' },
    { to: '/admin/directory', icon: 'store', label: '업소록' },
    { to: '/admin/groupbuy', icon: 'shopping-bag', label: '공동구매' },
    { to: '/admin/music', icon: 'music', label: '음악' },
    { to: '/admin/shorts', icon: 'video', label: '숏츠' },
    { to: '/admin/shopping', icon: 'shopping-cart', label: '쇼핑' },
    { to: '/admin/games', icon: 'gamepad', label: '게임' },
    { to: '/admin/poker', icon: 'coins', label: '포커' },
    { to: '/admin/elder', icon: 'heart', label: '안심' },
    { to: '/admin/communication', icon: 'phone', label: '채팅·통화' },
    { to: '/admin/claims', icon: 'flag', label: '클레임' },
  ],
  ad: [
    { to: '/admin/ad-center', icon: 'sparkles', label: '광고 센터' },
    { to: '/admin/banners', icon: 'megaphone', label: '광고 목록' },
    { to: '/admin/payments', icon: 'wallet', label: '결제/오더' },
  ],
  system: [
    { to: '/admin/security', icon: 'lock', label: '보안/신고' },
    { to: '/admin/settings', icon: 'settings', label: '설정' },
    { to: '/admin/pricing', icon: 'coins', label: '가격/할인' },
    { to: '/admin/hero-banners', icon: 'image', label: '히어로 배너' },
    { to: '/admin/popup-banners', icon: 'message-square', label: '팝업 배너' },
    { to: '/admin/system', icon: 'monitor', label: '시스템' },
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
