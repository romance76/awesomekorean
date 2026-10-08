<template>
<!-- 휴대폰 화면: 하단 5탭 + 메뉴 카드. 오른쪽 위 버튼으로 이전(PC) 화면과 바꿀 수 있어요 -->
<div v-if="isMobileLayout" class="min-h-screen bg-[#F3F4F6] flex flex-col">
  <header class="fixed top-0 inset-x-0 z-40 bg-white border-b border-gray-100 flex items-center gap-1 px-2"
    :style="{ paddingTop: 'env(safe-area-inset-top, 0px)', height: 'calc(56px + env(safe-area-inset-top, 0px))' }">
    <button v-if="mBack" @click="router.push(mBack)" class="w-11 h-11 grid place-items-center rounded-xl text-ink active:bg-gray-100" aria-label="뒤로"><AppIcon name="chevron-left" :size="24" /></button>
    <span v-else class="w-2"></span>
    <h1 class="flex-1 min-w-0 text-[18px] font-bold text-ink truncate">{{ mTitle }}</h1>
    <button @click="setView('classic')" class="min-h-[44px] px-3 flex items-center gap-1.5 rounded-xl text-[13px] font-bold text-ink-light active:bg-gray-100" title="이전(PC) 화면으로 보기">
      <AppIcon name="monitor" :size="18" /><span>PC 화면</span>
    </button>
  </header>

  <main class="flex-1" :style="{ paddingTop: 'calc(56px + env(safe-area-inset-top, 0px))', paddingBottom: 'calc(66px + env(safe-area-inset-bottom, 0px))' }">
    <div class="p-3.5" :class="mLegacy ? 'admin-legacy-zoom' : ''">
      <AdminMobileHome v-if="route.path === '/admin'" />
      <router-view v-else />
    </div>
  </main>

  <nav class="fixed bottom-0 inset-x-0 z-40 bg-white border-t border-gray-100 grid grid-cols-5" :style="{ paddingBottom: 'env(safe-area-inset-bottom, 0px)' }" aria-label="관리자 메뉴">
    <RouterLink v-for="t in mTabs" :key="t.to" :to="t.to"
      class="flex flex-col items-center justify-center gap-0.5 min-h-[58px] text-[12px]"
      :class="t.active ? 'text-amber-600 font-bold' : 'text-ink-muted font-medium'" :aria-current="t.active ? 'page' : undefined">
      <AppIcon :name="t.icon" :size="23" /><span>{{ t.label }}</span>
    </RouterLink>
  </nav>
</div>

<div v-else class="min-h-screen bg-[#F7F8FA] flex">
  <!-- 좁은 화면(휴대폰/태블릿)에서 이전 화면을 보고 있을 때: 휴대폰 화면으로 돌아가는 버튼 -->
  <button v-if="winW < 1024" @click="setView('mobile')"
    class="fixed right-3 z-[60] min-h-[44px] px-4 rounded-full bg-amber-500 text-white text-sm font-bold shadow-lg flex items-center gap-1.5"
    :style="{ bottom: 'calc(12px + env(safe-area-inset-bottom, 0px))' }">
    <AppIcon name="phone" :size="16" />휴대폰 화면
  </button>
  <!-- 사이드바 -->
  <aside class="w-52 bg-white border-r border-gray-100 hidden lg:flex flex-col h-screen sticky top-0">
    <div class="p-4 border-b border-gray-100">
      <div class="flex items-center gap-2">
        <img v-if="siteStore.logoUrl && !logoError" :src="siteStore.logoUrl" alt="AwesomeKorean" class="h-7 w-auto max-w-[140px] object-contain" @error="logoError = true" />
        <template v-else>
          <span class="icon-chip w-8 h-8 bg-amber-50 text-amber-600"><AppIcon name="settings" :size="16" /></span>
          <span class="font-bold text-ink text-sm">AwesomeKorean</span>
        </template>
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
      <button @click="handleLogout" class="flex items-center gap-1.5 text-xs text-ink-muted hover:text-red-600 transition-colors py-1 w-full"><AppIcon name="log-out" :size="14" /> 로그아웃</button>
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
      <div class="p-3 border-t border-gray-100">
        <button @click="handleLogout" class="flex items-center gap-1.5 text-xs text-ink-muted hover:text-red-600 transition-colors py-1 w-full"><AppIcon name="log-out" :size="14" /> 로그아웃</button>
      </div>
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
        <NewFeatureBadge v-if="tab.isNew" :desc="tab.newDesc || tab.label" />
      </RouterLink>
    </div>
    <div class="p-4 lg:p-6">
      <router-view />
    </div>
  </main>
</div>
</template>
<script setup>
import { ref, computed, provide, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useSiteStore } from '../../stores/site'
import AppIcon from '../../components/AppIcon.vue'
import NewFeatureBadge from '../../components/NewFeatureBadge.vue'
import AdminMobileHome from './AdminMobileHome.vue'

const auth = useAuthStore()
const siteStore = useSiteStore()
const route = useRoute()
const router = useRouter()
const mobileMenu = ref(false)
const logoError = ref(false)

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}

// 왼쪽 메인 메뉴 (5개)
const mainMenu = [
  { to: '/admin', icon: 'chart-bar', chip: 'bg-amber-50 text-amber-600', label: '대시보드', group: 'main' },
  { to: '/admin/members', icon: 'users', chip: 'bg-blue-50 text-blue-600', label: '회원', group: 'member' },
  { to: '/admin/market', icon: 'list', chip: 'bg-emerald-50 text-emerald-600', label: '게시판', group: 'board' },
  { to: '/admin/banners', icon: 'megaphone', chip: 'bg-violet-50 text-violet-600', label: '광고/가격관리', group: 'ad' },
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
    { to: '/admin/info', icon: 'book-open', label: '정보' },
    { to: '/admin/directory', icon: 'store', label: '업소록' },
    { to: '/admin/groupbuy', icon: 'shopping-bag', label: '공동구매' },
    { to: '/admin/music', icon: 'music', label: '음악' },
    { to: '/admin/shorts', icon: 'video', label: '숏츠' },
    { to: '/admin/shopping', icon: 'shopping-cart', label: '쇼핑' },
    { to: '/admin/chats', icon: 'message-square', label: '채팅' },
    { to: '/admin/games', icon: 'gamepad', label: '게임' },
    // 포커는 독립된 공개 메뉴가 아니라 게임(/games) 안의 하위 기능이라 별도
    // 상단 탭은 불필요 — AdminGames.vue에서 들어가는 하위 화면으로만 유지하고,
    // 상단 탭 목록(boardTabs)에서는 제외(hideFromTabs). 현재 그룹 판정에는 계속 사용.
    { to: '/admin/poker', icon: 'coins', label: '포커', hideFromTabs: true },
    { to: '/admin/elder', icon: 'heart', label: '안심' },
    { to: '/admin/communication', icon: 'phone', label: '채팅·통화' },
    { to: '/admin/claims', icon: 'flag', label: '클레임' },
    { to: '/admin/rewards', icon: 'gift', label: '보상 승인', isNew: true, newDesc: '이벤트 완료인증 · 레시피 인기보상을 관리자가 확인 후 지급하는 화면입니다.' },
  ],
  // '가격/할인'(포인트·광고 가격·할인 이벤트)은 원래 시스템 탭 아래 있었는데,
  // 그중 상당 부분이 광고 가격 설정이라 광고 관리 쪽에서만 따로 찾아야 해서
  // 불편하다는 피드백으로 광고관리 그룹으로 이동(광고/가격관리로 통합).
  ad: [
    { to: '/admin/ad-center', icon: 'sparkles', label: '광고 센터' },
    { to: '/admin/banners', icon: 'megaphone', label: '광고 목록' },
    { to: '/admin/flyers', icon: 'megaphone', label: 'NEW 전단 광고' },
    { to: '/admin/pricing', icon: 'coins', label: '가격/할인' },
    { to: '/admin/open-event', icon: 'gift', label: '오픈 이벤트', isNew: true, newDesc: '소프트 오픈 기간에 포인트 2배 · 채팅방 무료 · 광고 할인 등을 한 번에 켜는 화면입니다.' },
    { to: '/admin/revenue', icon: 'chart-bar', label: '매출/결제 현황' },
    { to: '/admin/payments', icon: 'wallet', label: '결제/오더' },
  ],
  system: [
    { to: '/admin/security', icon: 'lock', label: '보안/신고' },
    { to: '/admin/todos', icon: 'list', label: '할 일 목록' },
    { to: '/admin/analytics', icon: 'chart-bar', label: '방문 분석' },
    { to: '/admin/settings', icon: 'settings', label: '설정' },
    { to: '/admin/entry-settings', icon: 'ticket', label: 'Entry 설정' },
    { to: '/admin/sweepstakes', icon: 'gift', label: '경품 추첨 관리' },
    { to: '/admin/hero-banners', icon: 'image', label: '히어로 배너' },
    { to: '/admin/popup-banners', icon: 'message-square', label: '팝업 배너' },
    { to: '/admin/system', icon: 'monitor', label: '시스템' },
  ],
}

// 게시판 서브탭 경로 → 시스템 "메뉴 관리"의 메뉴 key 매핑.
// 매핑이 없는 항목(포커/클레임/보상 승인 등)은 공개 메뉴가 따로 없는
// 관리자 전용 화면이라 메뉴 설정과 무관하게 항상 노출한다.
const boardMenuKeyMap = {
  '/admin/community': 'community',
  '/admin/market': 'market',
  '/admin/jobs': 'jobs',
  '/admin/realestate': 'realestate',
  '/admin/qa': 'qa',
  '/admin/events': 'events',
  '/admin/clubs': 'clubs',
  '/admin/recipes': 'recipes',
  '/admin/news': 'news',
  '/admin/info': 'info',
  '/admin/directory': 'directory',
  '/admin/groupbuy': 'groupbuy',
  '/admin/music': 'music',
  '/admin/shorts': 'shorts',
  '/admin/shopping': 'shopping',
  '/admin/chats': 'chat',
  '/admin/games': 'games',
  '/admin/elder': 'elder',
  '/admin/communication': 'comms',
}

// 게시판 탭: 시스템 > 메뉴 관리에서 켜둔 메뉴만, 그 설정 순서대로 보여줌
// (관리자 전용 화면은 매핑이 없으므로 그대로 유지, 뒤에 붙임. hideFromTabs
// 항목은 상단 탭에선 완전히 제외 — currentGroup 판정에는 계속 쓰이므로
// subTabs.board 자체에서는 지우지 않음)
const boardTabs = computed(() => {
  const base = subTabs.board.filter(tab => !tab.hideFromTabs)
  const mc = siteStore.menuConfig
  if (!mc || !Array.isArray(mc) || !mc.length) return base
  const orderIndex = {}
  mc.forEach((m, i) => { orderIndex[m.key] = i })
  const enabledKeys = new Set(mc.filter(m => m.enabled !== false).map(m => m.key))
  const mapped = []
  const unmapped = []
  base.forEach(tab => {
    const key = boardMenuKeyMap[tab.to]
    if (!key) { unmapped.push(tab); return }
    if (!enabledKeys.has(key)) return
    mapped.push({ tab, order: orderIndex[key] })
  })
  mapped.sort((a, b) => a.order - b.order)
  return [...mapped.map(m => m.tab), ...unmapped]
})

// 현재 페이지가 어떤 그룹에 속하는지
const currentGroup = computed(() => {
  const path = route.path
  if (path.startsWith('/admin/menu/')) return String(route.params.group || 'main')
  for (const [group, tabs] of Object.entries(subTabs)) {
    if (tabs.some(t => path === t.to || (t.to !== '/admin' && path.startsWith(t.to)))) return group
  }
  return 'main'
})

const currentSubTabs = computed(() => {
  const tabs = currentGroup.value === 'board' ? boardTabs.value : (subTabs[currentGroup.value] || [])
  if (auth.user?.role === 'super_admin') return tabs
  return tabs.filter(t => t.to !== '/admin/sweepstakes')
})


// ── 휴대폰 화면 / 이전(PC) 화면 전환 ──
// 'mobile' = 휴대폰 화면, 'classic' = 이전 화면, 비어 있으면 자동(1024px 미만이면 휴대폰 화면)
const VIEW_KEY = 'ak_admin_view'
function readView() { try { const v = localStorage.getItem(VIEW_KEY); return v === 'mobile' || v === 'classic' ? v : '' } catch { return '' } }
const viewMode = ref(readView())
const winW = ref(typeof window !== 'undefined' ? window.innerWidth : 1280)
const onResize = () => { winW.value = window.innerWidth }
onMounted(() => window.addEventListener('resize', onResize))
onBeforeUnmount(() => window.removeEventListener('resize', onResize))
const isMobileLayout = computed(() => viewMode.value === 'mobile' || (viewMode.value !== 'classic' && winW.value < 1024))
function setView(v) { viewMode.value = v; try { localStorage.setItem(VIEW_KEY, v) } catch {} }

// 메뉴 허브(AdminMobileMenu)가 그룹별 메뉴를 가져가는 통로. 최고 관리자 전용 화면은 일반 운영진에게 숨김
const SUPER_ONLY = ['/admin/todos', '/admin/open-event', '/admin/analytics']
function tabsFor(group) {
  const tabs = group === 'board' ? boardTabs.value : (subTabs[group] || [])
  if (auth.user?.role === 'super_admin') return tabs
  return tabs.filter(t => t.to !== '/admin/sweepstakes' && !SUPER_ONLY.includes(t.to))
}
provide('adminTabsFor', tabsFor)
provide('adminIsMobile', isMobileLayout)

const groupLabels = { member: '회원', board: '게시판', ad: '광고', system: '시스템' }
const mTabs = computed(() => [
  { to: '/admin', icon: 'home', label: '홈', active: currentGroup.value === 'main' },
  { to: '/admin/menu/member', icon: 'users', label: '회원', active: currentGroup.value === 'member' },
  { to: '/admin/menu/board', icon: 'list', label: '게시판', active: currentGroup.value === 'board' },
  { to: '/admin/menu/ad', icon: 'megaphone', label: '광고', active: currentGroup.value === 'ad' },
  { to: '/admin/menu/system', icon: 'settings', label: '시스템', active: currentGroup.value === 'system' },
])
const isHub = computed(() => route.path.startsWith('/admin/menu/'))
const mTitle = computed(() => {
  const path = route.path
  if (path === '/admin') return '관리자'
  if (isHub.value) return groupLabels[route.params.group] || '관리자'
  if (path === '/admin/overview') return '종합 리포트'
  if (path === '/admin/members' && route.query.user) return '회원 상세'
  let best = null
  for (const tabs of Object.values(subTabs)) for (const t of tabs) {
    if ((path === t.to || path.startsWith(t.to + '/')) && (!best || t.to.length > best.to.length)) best = t
  }
  return best ? best.label : '관리자'
})
const mBack = computed(() => {
  if (route.path === '/admin') return ''
  if (isHub.value) return '/admin'
  if (route.path === '/admin/members' && route.query.user) return '/admin/members'
  return currentGroup.value === 'main' ? '/admin' : `/admin/menu/${currentGroup.value}`
})
// 아직 휴대폰용으로 다시 만들지 않은 화면은 글자를 조금 키워서 보여 줌
// (회원관리·게시판 관리처럼 이미 휴대폰용으로 만든 화면은 확대하지 않음)
const MOBILE_NATIVE = ['/admin/members', '/admin/community', '/admin/jobs', '/admin/market', '/admin/realestate', '/admin/clubs', '/admin/qa', '/admin/events', '/admin/directory', '/admin/friends', '/admin/todos', '/admin/payments', '/admin/security', '/admin/banners', '/admin/flyers', '/admin/ad-center', '/admin/pricing', '/admin/revenue', '/admin/analytics', '/admin/overview', '/admin/open-event', '/admin/entry-settings', '/admin/system', '/admin/sweepstakes', '/admin/hero-banners', '/admin/popup-banners', '/admin/settings', '/admin/claims', '/admin/rewards', '/admin/info']
const mLegacy = computed(() => route.path !== '/admin' && !isHub.value && !MOBILE_NATIVE.includes(route.path))

function isMainActive(item) {
  if (item.to === '/admin' && item.group === 'main') return route.path === '/admin'
  return currentGroup.value === item.group
}
</script>
<style>
/* 휴대폰 화면에서 아직 다시 만들지 않은 관리자 화면: 전체를 살짝 키워서 읽기 쉽게 */
.admin-legacy-zoom { zoom: 1.12; }
/* 아직 휴대폰용으로 새로 만들지 않은 화면을 위한 공통 보정: 입력칸 글자 16px(iOS 확대 방지), 표는 옆으로 밀어 보기, 모달은 화면 폭 안에 */
.admin-legacy-zoom input:not([type=checkbox]):not([type=radio]):not([type=range]):not([type=file]):not([type=color]),
.admin-legacy-zoom select,
.admin-legacy-zoom textarea { font-size: 16px; }
.admin-legacy-zoom table { display: block; max-width: 100%; overflow-x: auto; }
.admin-legacy-zoom .fixed.inset-0 > div { max-width: calc(100vw - 24px); }
.admin-legacy-zoom button:not([class*="w-"]):not([class*="h-"]) { min-height: 36px; }
</style>
