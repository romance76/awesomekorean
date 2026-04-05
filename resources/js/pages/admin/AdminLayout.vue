<template>
<div class="min-h-screen bg-gray-100 flex">
  <aside class="w-56 bg-white border-r hidden lg:block overflow-y-auto h-screen sticky top-0">
    <div class="p-4 border-b"><div class="font-black text-amber-700">⚙️ 관리자</div></div>
    <nav class="py-2">
      <RouterLink v-for="item in menuItems" :key="item.to" :to="item.to"
        class="block px-4 py-2 text-sm text-gray-600 hover:bg-amber-50 hover:text-amber-700">{{ item.label }}</RouterLink>
    </nav>
  </aside>
  <main class="flex-1 p-6"><router-view /></main>
</div>
</template>
<script setup>
const menuItems = [
  { to: '/admin', label: '대시보드' },
  { to: '/admin/members', label: '회원관리' },
  { to: '/admin/content', label: '콘텐츠' },
  { to: '/admin/boards', label: '게시판' },
  { to: '/admin/news', label: '뉴스' },
  { to: '/admin/jobs', label: '구인구직' },
  { to: '/admin/market', label: '장터' },
  { to: '/admin/realestate', label: '부동산' },
  { to: '/admin/clubs', label: '동호회' },
  { to: '/admin/qa', label: 'Q&A' },
  { to: '/admin/recipes', label: '레시피' },
  { to: '/admin/events', label: '이벤트' },
  { to: '/admin/directory', label: '업소록' },
  { to: '/admin/games', label: '게임' },
  { to: '/admin/music', label: '음악' },
  { to: '/admin/shorts', label: '숏츠' },
  { to: '/admin/shopping', label: '쇼핑' },
  { to: '/admin/groupbuy', label: '공동구매' },
  { to: '/admin/elder', label: '안심서비스' },
  { to: '/admin/chats', label: '채팅' },
  { to: '/admin/banners', label: '배너' },
  { to: '/admin/payments', label: '결제' },
  { to: '/admin/security', label: '보안' },
  { to: '/admin/settings', label: '설정' },
  { to: '/admin/system', label: '시스템' },
]
</script>