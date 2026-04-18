<template>
  <!-- 유저 대시보드 v2 레이아웃 (Phase 2-C 묶음 3 스캐폴드) -->
  <div class="max-w-7xl mx-auto px-4 py-6">
    <!-- 상단 프로필 요약 카드 -->
    <section class="bg-gradient-to-r from-amber-400 to-orange-400 rounded-xl p-5 text-white mb-5 shadow-sm">
      <div class="flex items-center gap-4">
        <img
          :src="auth.user?.avatar || '/images/default-avatar.png'"
          class="w-16 h-16 rounded-full border-2 border-white/60 object-cover bg-white"
          @error="$event.target.src='/images/default-avatar.png'"
        />
        <div class="flex-1">
          <h2 class="text-xl font-bold">{{ auth.user?.nickname || auth.user?.name || '회원' }}</h2>
          <p class="text-sm opacity-90">{{ auth.user?.email }}</p>
        </div>
        <div class="text-right">
          <p class="text-xs opacity-80">포인트</p>
          <p class="text-2xl font-bold">{{ (auth.user?.points || 0).toLocaleString() }}</p>
        </div>
      </div>
    </section>

    <div class="grid grid-cols-12 gap-5">
      <!-- 좌측 네비 (데스크톱) -->
      <aside class="hidden lg:block col-span-3 bg-white rounded-xl shadow-sm p-3 h-fit sticky top-20">
        <nav class="space-y-1">
          <template v-for="group in navGroups" :key="group.label">
            <p class="text-xs text-gray-400 px-3 py-2 uppercase tracking-wider">{{ group.label }}</p>
            <router-link
              v-for="item in group.items"
              :key="item.to"
              :to="item.to"
              class="block px-3 py-2 rounded-lg text-sm hover:bg-amber-50 transition"
              active-class="bg-amber-100 text-amber-900 font-semibold"
            >
              <span class="mr-2">{{ item.icon }}</span>{{ item.label }}
            </router-link>
          </template>
        </nav>
      </aside>

      <!-- 메인 영역 -->
      <main class="col-span-12 lg:col-span-9">
        <!-- 모바일 그룹 아코디언 네비 (가로 스크롤보다 보기 쉬움) -->
        <div class="lg:hidden mb-3 bg-white rounded-xl shadow-sm p-2">
          <details v-for="group in navGroups" :key="group.label" class="group/nav">
            <summary class="flex items-center justify-between px-3 py-2 cursor-pointer rounded-lg hover:bg-amber-50">
              <span class="font-semibold text-sm">{{ group.label }}</span>
              <span class="text-xs text-gray-400 group-open/nav:rotate-180 transition">▼</span>
            </summary>
            <div class="grid grid-cols-3 gap-1 p-2">
              <router-link
                v-for="item in group.items"
                :key="item.to"
                :to="item.to"
                class="flex flex-col items-center gap-1 p-2 rounded-lg hover:bg-amber-50 text-center"
                active-class="bg-amber-100 text-amber-900 font-semibold"
              >
                <span class="text-lg">{{ item.icon }}</span>
                <span class="text-xs">{{ item.label }}</span>
              </router-link>
            </div>
          </details>
        </div>
        <router-view v-slot="{ Component }">
          <keep-alive>
            <component :is="Component" />
          </keep-alive>
        </router-view>
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useAuthStore } from '../../stores/auth'

const auth = useAuthStore()

// 4그룹 20+ 페이지 매핑 (다음 단계에서 각 개별 페이지 구현)
const navGroups = [
  {
    label: '내 콘텐츠',
    items: [
      { to: '/mypage/posts',      icon: '📝', label: '내 글' },
      { to: '/mypage/comments',   icon: '💬', label: '내 댓글' },
      { to: '/mypage/market',     icon: '🛒', label: '내 장터' },
      { to: '/mypage/realestate', icon: '🏠', label: '내 부동산' },
      { to: '/mypage/jobs',       icon: '💼', label: '내 구인' },
      { to: '/mypage/groupbuy',   icon: '🤝', label: '공동구매' },
      { to: '/mypage/clubs',      icon: '👥', label: '동호회' },
      { to: '/mypage/events',     icon: '🎉', label: '이벤트' },
      { to: '/mypage/business',   icon: '🏪', label: '내 업소' },
      { to: '/mypage/resume',     icon: '📄', label: '이력서' },
    ],
  },
  {
    label: '내 활동',
    items: [
      { to: '/mypage/bookmarks',    icon: '🔖', label: '북마크' },
      { to: '/mypage/friends',      icon: '👫', label: '친구' },
      { to: '/mypage/messages',     icon: '✉️', label: '쪽지' },
      { to: '/mypage/chats',        icon: '💭', label: '채팅' },
      { to: '/mypage/calls',        icon: '📞', label: '통화내역' },
      { to: '/mypage/notifications',icon: '🔔', label: '알림' },
    ],
  },
  {
    label: '내 자산',
    items: [
      { to: '/mypage/points',   icon: '💰', label: '포인트' },
      { to: '/mypage/payments', icon: '💳', label: '결제 이력' },
      { to: '/mypage/ads',      icon: '📢', label: '광고 신청' },
    ],
  },
  {
    label: '내 설정',
    items: [
      { to: '/mypage/profile',              icon: '👤', label: '프로필' },
      { to: '/mypage/security',             icon: '🔒', label: '보안·세션' },
      { to: '/mypage/notification-settings',icon: '🔔', label: '알림 설정' },
      { to: '/mypage/privacy',              icon: '🛡️', label: '개인정보' },
      { to: '/mypage/elder',                icon: '💙', label: '안심서비스' },
    ],
  },
]

const flatItems = computed(() => navGroups.flatMap(g => g.items))
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>
