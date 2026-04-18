<template>
  <!-- 관리자 레이아웃 v2 (Phase 2-C 묶음 4 스캐폴드).
       11개 카테고리 그룹 네비 + 역할 기반 메뉴 필터링 + 브레드크럼.
       기존 /admin 은 유지, /admin/v2 경로로 점진 도입. -->
  <div :class="['min-h-screen transition-colors', darkMode ? 'bg-gray-900 text-gray-100' : 'bg-gray-50 text-gray-800']">
    <!-- 헤더 -->
    <header :class="['border-b sticky top-0 z-30 shadow-sm transition-colors', darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white']">
      <div class="flex items-center justify-between px-4 py-3">
        <div class="flex items-center gap-3">
          <button @click="sidebarOpen=!sidebarOpen" :class="['lg:hidden p-2 rounded', darkMode ? 'hover:bg-gray-700' : 'hover:bg-gray-100']">
            <span>☰</span>
          </button>
          <router-link to="/admin/v2" class="font-bold text-lg">
            <span class="text-amber-500">⚙️</span> 관리자 v2
          </router-link>
        </div>
        <div class="flex items-center gap-3 relative">
          <div class="relative">
            <input
              v-model="globalSearch"
              @input="onSearchInput"
              @focus="showResults = true"
              @blur="setTimeout(() => showResults = false, 150)"
              placeholder="전체 검색 (유저·글·결제·업소)"
              :class="['hidden md:block px-3 py-1.5 border rounded-lg text-sm w-72', darkMode ? 'bg-gray-700 border-gray-600 text-gray-100' : '']"
            />
            <!-- 검색 결과 드롭다운 -->
            <div v-if="showResults && globalSearch.length >= 2" :class="['absolute top-full mt-1 right-0 w-96 rounded-lg shadow-lg z-50 overflow-hidden border', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white']">
              <div v-if="searching" class="p-4 text-center text-sm text-gray-400">검색 중...</div>
              <div v-else-if="!hasResults" class="p-4 text-center text-sm text-gray-400">결과 없음</div>
              <div v-else class="max-h-96 overflow-y-auto">
                <template v-for="(items, category) in searchResults" :key="category">
                  <div class="px-3 py-1 text-xs font-semibold text-gray-500 bg-gray-50 border-b sticky top-0" :class="darkMode ? 'bg-gray-700 text-gray-300' : ''">
                    {{ categoryLabel(category) }} ({{ items.length }})
                  </div>
                  <router-link
                    v-for="item in items" :key="category + '-' + item.id"
                    :to="item.url"
                    @click="showResults = false; globalSearch = ''"
                    class="block px-3 py-2 hover:bg-amber-50 border-b text-sm transition-colors"
                    :class="darkMode ? 'hover:bg-gray-700 border-gray-600' : ''"
                  >
                    <div class="flex items-center gap-2">
                      <img v-if="item.image" :src="item.image" @error="$event.target.src='/images/default-avatar.png'" class="w-6 h-6 rounded-full" />
                      <div class="flex-1 min-w-0">
                        <p class="font-semibold truncate">{{ item.title }}</p>
                        <p class="text-xs text-gray-500 truncate">{{ item.meta }}</p>
                      </div>
                    </div>
                  </router-link>
                </template>
              </div>
            </div>
          </div>
          <button @click="toggleDarkMode" :class="['p-2 rounded transition-colors', darkMode ? 'hover:bg-gray-700' : 'hover:bg-gray-100']" :title="darkMode ? '라이트 모드' : '다크 모드'">
            {{ darkMode ? '☀️' : '🌙' }}
          </button>
          <!-- 실시간 알림 뱃지 (Phase 2-C Post) -->
          <div class="relative">
            <button @click="showAlerts = !showAlerts" :class="['relative p-2 rounded', darkMode ? 'hover:bg-gray-700' : 'hover:bg-gray-100']" title="실시간 관리자 알림">
              🔔
              <span v-if="alerts.length" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold">{{ alerts.length }}</span>
            </button>
            <div v-if="showAlerts" :class="['absolute right-0 top-full mt-1 w-80 rounded-lg shadow-lg border z-50 max-h-96 overflow-y-auto', darkMode ? 'bg-gray-800 border-gray-600' : 'bg-white']">
              <div class="p-2 border-b flex items-center justify-between">
                <span class="text-xs font-semibold">실시간 알림 ({{ alerts.length }})</span>
                <button @click="alerts = []" class="text-xs text-amber-600 hover:text-amber-800">모두 지우기</button>
              </div>
              <div v-if="!alerts.length" class="p-6 text-center text-xs text-gray-400">알림 없음</div>
              <router-link
                v-for="(a, i) in alerts" :key="i" :to="a.link || '#'"
                @click="alerts.splice(i, 1); showAlerts = false"
                :class="['block p-3 border-b text-xs hover:bg-amber-50 transition-colors', darkMode ? 'hover:bg-gray-700 border-gray-600' : '']"
              >
                <p class="font-semibold">{{ a.title }}</p>
                <p class="text-gray-500 truncate">{{ a.message }}</p>
                <p class="text-gray-400 mt-0.5">{{ fmtTime(a.occurred_at) }}</p>
              </router-link>
            </div>
          </div>
          <router-link to="/mypage/profile" :class="['flex items-center gap-2 rounded px-2 py-1', darkMode ? 'hover:bg-gray-700' : 'hover:bg-gray-100']">
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
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../../stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const sidebarOpen = ref(false)
const globalSearch = ref('')
const darkMode = ref(localStorage.getItem('admin_dark_mode') === '1')
function toggleDarkMode() {
  darkMode.value = !darkMode.value
  localStorage.setItem('admin_dark_mode', darkMode.value ? '1' : '0')
}

// 전역 검색 (Phase 2-C Post)
const searchResults = ref({})
const searching = ref(false)
const showResults = ref(false)
let searchDebounce = null
const hasResults = computed(() => Object.keys(searchResults.value).length > 0)
const categoryLabel = (c) => ({ users: '👤 회원', posts: '📝 게시글', payments: '💳 결제', businesses: '🏪 업소' }[c] || c)

// 실시간 Admin 알림 (Reverb channel: admin-alerts)
const alerts = ref([])
const showAlerts = ref(false)
const fmtTime = (s) => {
  try { return new Date(s).toLocaleTimeString('ko-KR') } catch { return '' }
}
onMounted(() => {
  try {
    window.Echo?.channel('admin-alerts').listen('.admin.alert', (e) => {
      alerts.value.unshift(e)
      if (alerts.value.length > 20) alerts.value.length = 20
      // 토스트 알림도 같이
      try {
        const msg = `[${e.severity === 'critical' ? '🚨' : e.severity === 'warning' ? '⚠️' : 'ℹ️'}] ${e.title}: ${e.message}`
        const store = (window.__siteStore__ ?? null)
        store?.toast?.(msg, e.severity === 'critical' ? 'error' : 'warning', 6000)
      } catch {}
    })
  } catch (err) {
    console.warn('[AdminLayoutV2] admin-alerts listener setup failed', err)
  }
})

async function onSearchInput() {
  if (searchDebounce) clearTimeout(searchDebounce)
  if (globalSearch.value.length < 2) { searchResults.value = {}; return }
  searching.value = true
  searchDebounce = setTimeout(async () => {
    try {
      const axios = (await import('axios')).default
      const { data } = await axios.get(`/api/admin/search?q=${encodeURIComponent(globalSearch.value)}`)
      searchResults.value = data.data || {}
    } catch { searchResults.value = {} }
    finally { searching.value = false }
  }, 250)
}

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
      { to: '/admin/v2/users/invitations', label: '👑 관리자 초대', perm: 'users.role.change' },
      { to: '/admin/v2/content',         label: '게시글',       perm: 'content.view' },
      { to: '/admin/v2/comments',        label: '댓글',         perm: 'comments.delete' },
      { to: '/admin/v2/boards',          label: '게시판',       perm: 'site.settings.edit' },
      { to: '/admin/v2/friends',         label: '친구 관리',    perm: 'users.view' },
      { to: '/admin/v2/qa',              label: 'Q&A',         perm: 'content.view' },
    ],
  },
  {
    key: 'communication', icon: '📣', label: '커뮤니케이션', perm: 'notifications.send',
    items: [
      { to: '/admin/v2/communication/broadcast', label: '📢 대량 알림/이메일', perm: 'notifications.send' },
      { to: '/admin/v2/communication/notices',   label: '📰 공지사항',           perm: 'notifications.send' },
      { to: '/admin/v2/communication/messages',  label: '💬 채팅 관리',          perm: 'content.moderate' },
      { to: '/admin/v2/communication/email-templates', label: '📧 이메일 템플릿',  perm: 'notifications.send' },
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
      { to: '/admin/v2/server/health',     label: '🩺 헬스 체크', perm: 'server.view' },
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
      { to: '/admin/v2/security/anomaly',    label: '⚠️ 이상 활동', perm: 'audit.view' },
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
