<template>
  <!-- 관리자 레이아웃 v2 (Phase 2-C 묶음 4 스캐폴드).
       11개 카테고리 그룹 네비 + 역할 기반 메뉴 필터링 + 브레드크럼.
       기존 /admin 은 유지, /admin/v2 경로로 점진 도입. -->
  <div class="min-h-screen bg-gray-50">
    <!-- 헤더 -->
    <header class="bg-white border-b sticky top-0 z-30 shadow-sm">
      <div class="flex items-center justify-between px-4 py-3">
        <div class="flex items-center gap-3">
          <button @click="sidebarOpen=!sidebarOpen" class="lg:hidden p-2 hover:bg-gray-100 rounded">
            <span>☰</span>
          </button>
          <router-link to="/admin/v2" class="font-bold text-lg">
            <span class="text-amber-500">⚙️</span> 관리자 v2
          </router-link>
        </div>
        <div class="flex items-center gap-3">
          <input v-model="globalSearch" placeholder="전체 검색 (Ctrl+K)" class="hidden md:block px-3 py-1.5 border rounded-lg text-sm w-64" />
          <button class="relative p-2 hover:bg-gray-100 rounded" title="알림">🔔</button>
          <router-link to="/mypage/profile" class="flex items-center gap-2 hover:bg-gray-100 rounded px-2 py-1">
            <img :src="auth.user?.avatar || '/images/default-avatar.png'" class="w-7 h-7 rounded-full" @error="$event.target.src='/images/default-avatar.png'" />
            <span class="text-sm hidden md:inline">{{ auth.user?.nickname || auth.user?.name }}</span>
          </router-link>
          <button @click="logout" class="text-sm text-gray-500 hover:text-red-500 px-2">로그아웃</button>
        </div>
      </div>
    </header>

    <div class="flex">
      <!-- 사이드바 -->
      <aside :class="['bg-white border-r min-h-screen transition-all', sidebarOpen ? 'w-64' : 'hidden lg:block lg:w-64']">
        <nav class="p-3 space-y-1">
          <template v-for="group in visibleGroups" :key="group.key">
            <details class="group" :open="group.defaultOpen">
              <summary class="flex items-center gap-2 px-3 py-2 rounded-lg cursor-pointer hover:bg-gray-50">
                <span>{{ group.icon }}</span>
                <span class="font-semibold text-sm flex-1">{{ group.label }}</span>
                <span class="text-xs text-gray-400">{{ group.items.length }}</span>
              </summary>
              <div class="ml-2 mt-1 space-y-0.5">
                <router-link
                  v-for="item in group.items"
                  :key="item.to"
                  :to="item.to"
                  class="block px-3 py-1.5 rounded text-sm text-gray-700 hover:bg-amber-50"
                  active-class="bg-amber-100 text-amber-900 font-semibold"
                >
                  {{ item.label }}
                </router-link>
              </div>
            </details>
          </template>
        </nav>
      </aside>

      <!-- 메인 -->
      <main class="flex-1 p-5">
        <!-- 브레드크럼 -->
        <nav class="text-xs text-gray-500 mb-3">
          <router-link to="/admin/v2" class="hover:text-amber-600">홈</router-link>
          <span class="mx-1">›</span>
          <span>{{ currentGroupLabel }}</span>
          <span v-if="currentPageLabel">
            <span class="mx-1">›</span>
            <span class="text-gray-700">{{ currentPageLabel }}</span>
          </span>
        </nav>
        <router-view v-slot="{ Component }">
          <component :is="Component" v-if="Component" />
          <div v-else class="bg-white rounded-xl shadow-sm p-8 text-center text-gray-500">
            <p class="text-2xl mb-2">🚧</p>
            <p>Phase 2-C 묶음 4 리팩토링 대상 페이지입니다.</p>
            <p class="text-xs mt-2">기존 /admin 경로에서 사용 가능.</p>
          </div>
        </router-view>
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../../stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const sidebarOpen = ref(false)
const globalSearch = ref('')

// 11 카테고리 구조 (관리자 상세 감사서 기반)
const groups = [
  {
    key: 'dashboard', icon: '📊', label: '대시보드', perm: null, defaultOpen: true,
    items: [
      { to: '/admin/v2/dashboard', label: '개요', perm: 'analytics.view' },
    ],
  },
  {
    key: 'users', icon: '👥', label: '회원 & 커뮤니티', perm: 'users.view',
    items: [
      { to: '/admin/v2/users',           label: '회원 관리',   perm: 'users.view' },
      { to: '/admin/v2/users/point-ops', label: '💰 포인트 운영', perm: 'points.adjust' },
      { to: '/admin/v2/content',         label: '게시글',       perm: 'content.view' },
      { to: '/admin/v2/comments',        label: '댓글',         perm: 'comments.delete' },
      { to: '/admin/v2/boards',          label: '게시판',       perm: 'site.settings.edit' },
      { to: '/admin/v2/friends',         label: '친구 관리',    perm: 'users.view' },
      { to: '/admin/v2/qa',              label: 'Q&A',         perm: 'content.view' },
    ],
  },
  {
    key: 'services', icon: '🏪', label: '서비스', perm: 'content.view',
    items: [
      { to: '/admin/v2/directory',   label: '업소록',      perm: 'content.view' },
      { to: '/admin/v2/claims',      label: '업소 클레임', perm: 'content.moderate' },
      { to: '/admin/v2/jobs',        label: '구인구직',    perm: 'content.view' },
      { to: '/admin/v2/market',      label: '중고장터',    perm: 'content.view' },
      { to: '/admin/v2/realestate',  label: '부동산',       perm: 'content.view' },
      { to: '/admin/v2/groupbuy',    label: '공동구매',    perm: 'content.view' },
      { to: '/admin/v2/shopping',    label: '쇼핑',         perm: 'content.view' },
    ],
  },
  {
    key: 'auto-content', icon: '🎉', label: '콘텐츠 자동화', perm: 'content.view',
    items: [
      { to: '/admin/v2/news',    label: '뉴스',   perm: 'content.view' },
      { to: '/admin/v2/recipes', label: '레시피', perm: 'content.view' },
      { to: '/admin/v2/music',   label: '음악',   perm: 'content.view' },
      { to: '/admin/v2/shorts',  label: '숏츠',   perm: 'content.view' },
      { to: '/admin/v2/events',  label: '이벤트', perm: 'content.view' },
      { to: '/admin/v2/clubs',   label: '동호회', perm: 'content.view' },
    ],
  },
  {
    key: 'revenue', icon: '💰', label: '수익 & 광고', perm: 'payments.view',
    items: [
      { to: '/admin/v2/banners',        label: '광고 배너',    perm: 'content.moderate' },
      { to: '/admin/v2/ad-settings',    label: '광고 설정',    perm: 'site.settings.edit' },
      { to: '/admin/v2/payments',       label: '결제·환불',   perm: 'payments.view' },
      { to: '/admin/v2/point-settings', label: '포인트 규칙', perm: 'points.rules.view' },
      { to: '/admin/v2/hero-banners',   label: '히어로 배너', perm: 'site.settings.edit' },
    ],
  },
  {
    key: 'specials', icon: '🎮', label: '특수 서비스', perm: 'content.view',
    items: [
      { to: '/admin/v2/poker',  label: '포커',        perm: 'content.view' },
      { to: '/admin/v2/games',  label: '게임',        perm: 'content.view' },
      { to: '/admin/v2/chats',  label: '채팅방',      perm: 'content.moderate' },
      { to: '/admin/v2/calls',  label: '통화내역',    perm: 'content.view' },
      { to: '/admin/v2/elder',  label: '안심서비스', perm: 'content.view' },
    ],
  },
  {
    key: 'site', icon: '⚙️', label: '사이트 설정', perm: 'site.settings.view',
    items: [
      { to: '/admin/v2/site/company', label: '회사 정보',   perm: 'site.settings.edit' },
      { to: '/admin/v2/site/pages',   label: '정적 페이지', perm: 'site.pages.edit' },
      { to: '/admin/v2/site/footer',  label: 'Footer',       perm: 'site.footer.edit' },
      { to: '/admin/v2/site/faq',     label: 'FAQ',          perm: 'site.faq.edit' },
      { to: '/admin/v2/site/seo',     label: 'SEO',          perm: 'site.settings.edit' },
    ],
  },
  {
    key: 'integrations', icon: '🔌', label: '외부 연동', perm: 'api.keys.view',
    items: [
      { to: '/admin/v2/integrations/api-keys',      label: 'API 키 관리',     perm: 'api.keys.view' },
      { to: '/admin/v2/integrations/stripe',        label: 'Stripe',           perm: 'integrations.manage' },
      { to: '/admin/v2/integrations/sentry',        label: 'Sentry',           perm: 'integrations.manage' },
      { to: '/admin/v2/integrations/digitalocean',  label: 'DigitalOcean',     perm: 'integrations.manage' },
      { to: '/admin/v2/integrations/firebase',      label: 'Firebase Push',    perm: 'integrations.manage' },
    ],
  },
  {
    key: 'server', icon: '🖥️', label: '서버 관리', perm: 'server.view',
    items: [
      { to: '/admin/v2/server/overview',   label: '개요',         perm: 'server.view' },
      { to: '/admin/v2/server/metrics',    label: 'Metrics',      perm: 'server.view' },
      { to: '/admin/v2/server/plan',       label: '플랜',         perm: 'server.manage' },
      { to: '/admin/v2/server/automation', label: '자동화',       perm: 'server.manage' },
      { to: '/admin/v2/server/snapshots',  label: 'Snapshots',    perm: 'server.snapshot' },
      { to: '/admin/v2/server/backup',     label: '백업',         perm: 'server.backup' },
    ],
  },
  {
    key: 'security', icon: '🔒', label: '보안', perm: 'audit.view',
    items: [
      { to: '/admin/v2/security/reports',    label: '신고',         perm: 'reports.view' },
      { to: '/admin/v2/security/ip-bans',    label: 'IP 차단',      perm: 'ipbans.view' },
      { to: '/admin/v2/security/login-logs', label: '로그인 기록', perm: 'users.login-history.view' },
      { to: '/admin/v2/security/audit',      label: '감사 로그',   perm: 'audit.view' },
    ],
  },
  {
    key: 'analytics', icon: '📈', label: '분석', perm: 'analytics.view',
    items: [
      { to: '/admin/v2/analytics/users',    label: '회원 분석',   perm: 'analytics.view' },
      { to: '/admin/v2/analytics/content',  label: '콘텐츠 분석', perm: 'analytics.view' },
      { to: '/admin/v2/analytics/revenue',  label: '매출',         perm: 'analytics.advanced' },
      { to: '/admin/v2/analytics/custom',   label: '커스텀 리포트', perm: 'analytics.advanced' },
    ],
  },
]

// 권한 기반 필터
const visibleGroups = computed(() =>
  groups
    .map(g => ({
      ...g,
      items: g.items.filter(i => !i.perm || auth.can(i.perm) || auth.hasRole('super_admin')),
    }))
    .filter(g => g.items.length > 0)
)

const currentGroupLabel = computed(() => {
  for (const g of groups) {
    if (g.items.some(i => route.path.startsWith(i.to))) return g.label
  }
  return '대시보드'
})
const currentPageLabel = computed(() => {
  for (const g of groups) {
    const item = g.items.find(i => route.path === i.to)
    if (item) return item.label
  }
  return ''
})

async function logout() {
  await auth.logout()
  router.push('/login')
}
</script>
