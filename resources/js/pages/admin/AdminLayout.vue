<template>
<!-- 휴대폰 화면: 하단 5탭 + 메뉴 카드. 오른쪽 위 버튼으로 이전(PC) 화면과 바꿀 수 있어요 -->
<div v-if="isMobileLayout" class="admin-shell min-h-screen bg-[#F3F4F6] flex flex-col">
  <header class="fixed top-0 inset-x-0 z-40 bg-white border-b border-gray-100 flex items-center gap-1 px-2"
    :style="{ paddingTop: 'env(safe-area-inset-top, 0px)', height: 'calc(56px + env(safe-area-inset-top, 0px))' }">
    <button v-if="mBack" @click="router.push(mBack)" class="w-11 h-11 grid place-items-center rounded-xl text-ink active:bg-gray-100" aria-label="뒤로"><AppIcon name="chevron-left" :size="24" /></button>
    <span v-else class="w-2"></span>
    <h1 class="flex-1 min-w-0 text-[18px] font-bold text-ink truncate">{{ mTitle }}</h1>
    <!-- 알림: 신고·광고 신청 같은 관리자 알림을 누르면 해당 관리자 처리 화면이 바로 열려요 -->
    <RouterLink to="/notifications" class="relative w-11 h-11 shrink-0 grid place-items-center rounded-xl text-ink active:bg-gray-100" :aria-label="unreadNotifs > 0 ? `알림 ${unreadNotifs}건 안 읽음` : '알림'">
      <AppIcon name="bell" :size="22" />
      <span v-if="unreadNotifs > 0" class="absolute top-1 right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[11px] font-bold grid place-items-center tabular-nums">{{ unreadNotifs > 9 ? '9+' : unreadNotifs }}</span>
    </RouterLink>
    <button @click="setView('classic')" class="min-h-[44px] px-3 flex items-center gap-1.5 rounded-xl text-[13px] font-bold text-ink-light active:bg-gray-100" title="이전(PC) 화면으로 보기">
      <AppIcon name="monitor" :size="18" /><span>PC 화면</span>
    </button>
  </header>

  <main class="flex-1" :style="{ paddingTop: 'calc(56px + env(safe-area-inset-top, 0px))', paddingBottom: 'calc(66px + env(safe-area-inset-bottom, 0px))' }">
    <!-- 같은 그룹의 다른 화면으로 바로 이동하는 칩 줄 (PC 의 상단 서브탭과 같은 목록) — 허브·홈에서는 숨김 -->
    <nav v-if="mGroupChips.length > 1" class="sticky z-30 bg-[#F3F4F6] border-b border-gray-200/70 flex gap-2 overflow-x-auto scrollbar-hide px-3.5 py-2" :style="{ top: 'calc(56px + env(safe-area-inset-top, 0px))' }" aria-label="같은 메뉴 안의 화면">
      <RouterLink v-for="c in mGroupChips" :key="c.to" :to="c.to"
        class="shrink-0 min-h-[40px] px-3.5 rounded-full border text-[14px] flex items-center whitespace-nowrap"
        :class="c.active ? 'bg-ink text-white border-ink font-bold' : 'bg-white text-ink border-gray-200 font-medium'" :aria-current="c.active ? 'page' : undefined">{{ c.label }}</RouterLink>
    </nav>
    <div class="p-3.5" :class="mLegacy ? 'admin-legacy-zoom' : ''">
      <AdminMobileHome v-if="route.path === '/admin'" />
      <router-view v-else />
    </div>
    <!-- 맨 아래: 일반(사용자) 화면으로 전환 -->
    <div class="px-3.5 pb-5">
      <RouterLink to="/" class="w-full min-h-[48px] rounded-xl bg-white border border-gray-200 text-ink font-bold text-[14px] flex items-center justify-center gap-2 active:bg-gray-100">
        <AppIcon name="home" :size="18" />일반 화면으로 전환
      </RouterLink>
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

<div v-else class="admin-shell min-h-screen bg-[#F7F8FA] flex">
  <!-- 좁은 화면(휴대폰/태블릿)에서 이전 화면을 보고 있을 때: 휴대폰 화면으로 돌아가는 버튼 -->
  <button v-if="winW < 1024" @click="setView('mobile')"
    class="fixed right-3 z-[60] min-h-[44px] px-4 rounded-full bg-amber-500 text-white text-sm font-bold shadow-lg flex items-center gap-1.5"
    :style="{ bottom: 'calc(12px + env(safe-area-inset-bottom, 0px))' }">
    <AppIcon name="phone" :size="16" />휴대폰 화면
  </button>
  <!-- 사이드바 -->
  <aside class="w-60 bg-white border-r border-gray-100 hidden lg:flex flex-col h-screen sticky top-0">
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
    <!-- 그룹을 펼치면 관련된 화면이 소제목 아래 모여 보이는 사이드 메뉴 (현재 화면이 있는 그룹은 자동으로 펼쳐짐) -->
    <nav class="flex-1 overflow-y-auto py-2" aria-label="관리자 메뉴">
      <template v-for="g in sidebarTree" :key="g.key">
        <RouterLink v-if="!g.sections.length" :to="g.to"
          class="flex items-center gap-2.5 px-4 py-2 text-sm transition-colors"
          :class="isMainActive(g) ? 'bg-amber-50 text-amber-700 font-bold border-r-2 border-amber-500' : 'text-ink-light hover:bg-amber-50/50'">
          <span class="icon-chip w-7 h-7" :class="g.chip"><AppIcon :name="g.icon" :size="15" /></span>
          <span>{{ g.label }}</span>
        </RouterLink>
        <template v-else>
          <button type="button" @click="toggleGroup(g.key)" :aria-expanded="isOpen(g.key)"
            class="w-full flex items-center gap-2.5 px-4 py-2 text-sm transition-colors text-left"
            :class="isMainActive(g) ? 'text-amber-700 font-bold' : 'text-ink hover:bg-amber-50/50'">
            <span class="icon-chip w-7 h-7" :class="g.chip"><AppIcon :name="g.icon" :size="15" /></span>
            <span class="flex-1">{{ g.label }}</span>
            <AppIcon :name="isOpen(g.key) ? 'chevron-down' : 'chevron-right'" :size="14" class="text-ink-muted" />
          </button>
          <div v-show="isOpen(g.key)" class="pb-1">
            <template v-for="sec in g.sections" :key="sec.title">
              <div v-if="sec.title" class="mx-3 mt-2 mb-0.5 pl-3 pr-2 py-0.5 rounded bg-gray-100 text-[11px] font-bold text-ink-muted tracking-wide">{{ sec.title }}</div>
              <RouterLink v-for="it in sec.items" :key="it.to" :to="it.to"
                class="flex items-center gap-1.5 pl-[3.25rem] pr-3 py-1.5 text-[13px] transition-colors"
                :class="isItemActive(it) ? 'bg-amber-50 text-amber-700 font-bold border-r-2 border-amber-500' : 'text-ink-light hover:bg-amber-50/50'">
                <span>{{ it.label }}</span>
                <NewFeatureBadge v-if="it.isNew" :desc="it.newDesc || it.label" />
              </RouterLink>
            </template>
          </div>
        </template>
      </template>
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
    <div v-if="currentSubTabs.length" class="lg:hidden bg-white border-b border-gray-100 px-4 py-0 flex gap-0 overflow-x-auto scrollbar-hide">
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
<!-- 위험한 동작 앞 비밀번호 재확인 창 (PC·휴대폰 공용) -->
<ReauthSheet />
<!-- 관리자 공용 확인 시트 (할 일 #83) — useAdminConfirm().confirmAction() 으로 어디서든 호출 -->
<ConfirmSheet />
</template>
<script setup>
import { ref, computed, provide, watch, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import { useAuthStore } from '../../stores/auth'
import { useSiteStore } from '../../stores/site'
import AppIcon from '../../components/AppIcon.vue'
import NewFeatureBadge from '../../components/NewFeatureBadge.vue'
import AdminMobileHome from './AdminMobileHome.vue'
import ReauthSheet from '../../components/admin/ReauthSheet.vue'
import ConfirmSheet from '../../components/admin/ConfirmSheet.vue'
import { installReauthInterceptor } from '../../composables/useReauth'
import { PC_GROUPS, ADMIN_ITEMS, SECTION_ORDER, itemsByPcGroup } from '../../admin/adminMenu'

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

// 최고관리자만 볼 수 있는 화면 걸러내기(실제 접근 차단은 서버 AdminTier 가 함, 여기서는 메뉴 노출만)
function visibleFor(items) {
  if (auth.user?.role === 'super_admin') return items
  return items.filter(i => (i.minTier || 2) <= 2)
}

// 왼쪽 메인 메뉴 (PC 사이드바, 7개 그룹 — 메뉴 정의는 admin/adminMenu.js 하나에서 가져옴)
const mainMenu = PC_GROUPS.map(g => ({ to: g.to, icon: g.icon, chip: g.chip, label: g.label, group: g.key }))

// '가격/할인'(포인트·광고 가격·할인 이벤트)은 원래 시스템 탭 아래 있었는데,
// 그중 상당 부분이 광고 가격 설정이라 광고 관리 쪽에서만 따로 찾아야 해서
// 불편하다는 피드백으로 광고관리 그룹으로 이동(광고/가격관리로 통합) — adminMenu.js 의 money 그룹에 반영됨.

// 콘텐츠 탭 경로 → 시스템 "메뉴 관리"의 메뉴 key 매핑.
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

// 메뉴 설정 순서·켜짐 여부를 적용하는 공통 함수(콘텐츠 탭에만 적용, 관리자 전용 화면은 매핑이 없으므로 그대로 유지)
function applyMenuConfig(tabs) {
  const mc = siteStore.menuConfig
  if (!mc || !Array.isArray(mc) || !mc.length) return tabs
  const orderIndex = {}
  mc.forEach((m, i) => { orderIndex[m.key] = i })
  const enabledKeys = new Set(mc.filter(m => m.enabled !== false).map(m => m.key))
  const mapped = []
  const unmapped = []
  tabs.forEach(tab => {
    const key = boardMenuKeyMap[tab.to]
    if (!key) { unmapped.push(tab); return }
    if (!enabledKeys.has(key)) return
    mapped.push({ tab, order: orderIndex[key] })
  })
  mapped.sort((a, b) => a.order - b.order)
  return [...mapped.map(m => m.tab), ...unmapped]
}

// PC 상단 서브탭: 새 7그룹(pcGroup) 기준. 콘텐츠 그룹만 메뉴 설정 순서를 적용.
const currentPcGroup = computed(() => {
  const path = route.path
  const item = ADMIN_ITEMS.find(i => path === i.to || path.startsWith(i.to + '/'))
  return item ? item.pcGroup : 'home'
})
const currentSubTabs = computed(() => {
  const group = currentPcGroup.value
  let tabs = itemsByPcGroup(group).filter(t => !t.hideFromTabs)
  if (group === 'content') tabs = applyMenuConfig(tabs)
  return visibleFor(tabs)
})

// 휴대폰: 같은 그룹 안의 화면 칩 (허브·홈에서는 비움). 현재 화면이 어느 항목에 속하는지는 가장 긴 주소 일치로 판단.
const mGroupChips = computed(() => {
  const path = route.path
  if (path === '/admin' || path.startsWith('/admin/menu/')) return []
  return currentSubTabs.value.map(t => ({ to: t.to, label: t.label, active: path === t.to || path.startsWith(t.to + '/') }))
})

// 휴대폰 허브(/admin/menu/:group)·하단 탭용: 할 일 #84 부터는 PC 와 같은 7그룹(pcGroup)을 그대로 씀.
// '/admin' 이면 'home', 허브 화면(/admin/menu/:group)이면 그 그룹, 그 외엔 현재 화면이 속한 그룹.
const currentMobileGroup = computed(() => {
  const path = route.path
  if (path.startsWith('/admin/menu/')) return String(route.params.group || 'home')
  return currentPcGroup.value
})


// ── 휴대폰 화면 / 이전(PC) 화면 전환 ──
// 'mobile' = 휴대폰 화면, 'classic' = 이전 화면, 비어 있으면 자동(1024px 미만이면 휴대폰 화면)
const VIEW_KEY = 'ak_admin_view'
function readView() { try { const v = localStorage.getItem(VIEW_KEY); return v === 'mobile' || v === 'classic' ? v : '' } catch { return '' } }
const viewMode = ref(readView())
const winW = ref(typeof window !== 'undefined' ? window.innerWidth : 1280)
const onResize = () => { winW.value = window.innerWidth }
// 위험한 동작(키·결제 설정·회원 삭제 등)이 비밀번호 재확인(428)을 요구하면 비밀번호 창을 띄우고 같은 요청을 다시 보낸다
let ejectReauth = null

// 휴대폰 헤더의 알림 종 — 안 읽은 알림 수 (화면을 옮길 때 20초에 한 번만 다시 확인)
const unreadNotifs = ref(0)
let unreadAt = 0
async function loadUnreadNotifs() {
  if (Date.now() - unreadAt < 20000) return
  unreadAt = Date.now()
  try {
    const { data } = await axios.get('/api/notifications', { params: { unread_only: 1 } })
    unreadNotifs.value = Number(data.unread_count) || 0
  } catch {}
}
watch(() => route.path, () => { if (isMobileLayout.value) loadUnreadNotifs() })

onMounted(() => { window.addEventListener('resize', onResize); ejectReauth = installReauthInterceptor(); if (isMobileLayout.value) loadUnreadNotifs() })
onBeforeUnmount(() => { window.removeEventListener('resize', onResize); ejectReauth && ejectReauth() })
const isMobileLayout = computed(() => viewMode.value === 'mobile' || (viewMode.value !== 'classic' && winW.value < 1024))
function setView(v) { viewMode.value = v; try { localStorage.setItem(VIEW_KEY, v) } catch {} }

// 휴대폰 하단 탭은 5개뿐이라 7그룹을 다 담을 수 없음 — 자주 쓰는 4개(회원 제외)는 바로,
// 나머지(회원·홍보·이벤트·시스템)는 '더보기' 한 칸에 모아 그 안에서 고르게 함 (할 일 #84).
const MORE_GROUPS = ['member', 'promo', 'system']

// 메뉴 허브(AdminMobileMenu)가 그룹별 메뉴를 가져가는 통로. 'more' 는 실제 화면 그룹이 아니라
// MORE_GROUPS 로 가는 길목(타일) 목록을 보여 주는 가짜 그룹. 콘텐츠 그룹만 메뉴 설정 순서를 적용,
// 최고관리자 전용 화면은 일반 운영진에게 숨김.
function tabsFor(group) {
  if (group === 'more') {
    return PC_GROUPS.filter(g => MORE_GROUPS.includes(g.key))
      .map(g => ({ to: `/admin/menu/${g.key}`, icon: g.icon, label: g.label }))
  }
  let tabs = itemsByPcGroup(group).filter(t => !t.hideFromTabs)
  if (group === 'content') tabs = applyMenuConfig(tabs)
  return visibleFor(tabs)
}
provide('adminTabsFor', tabsFor)

// PC 사이드바 트리: 그룹 → 소제목(section) → 화면. 보이는 화면만(등급·메뉴 설정 반영)
const sidebarTree = computed(() => PC_GROUPS.map(g => {
  const items = g.key === 'home' ? [] : tabsFor(g.key)
  const order = SECTION_ORDER[g.key] || []
  const bySection = new Map()
  items.forEach(it => {
    const key = it.section || ''
    if (!bySection.has(key)) bySection.set(key, [])
    bySection.get(key).push(it)
  })
  const sections = [...order, ''].filter(k => bySection.has(k)).map(k => ({ title: k, items: bySection.get(k) }))
  return { key: g.key, group: g.key, to: g.to, label: g.label, icon: g.icon, chip: g.chip, sections }
}))
const NAV_OPEN_KEY = 'ak_admin_nav_open'
function readOpen() { try { const v = JSON.parse(localStorage.getItem(NAV_OPEN_KEY) || '[]'); return Array.isArray(v) ? v : [] } catch { return [] } }
const openGroups = ref(readOpen())
function isOpen(key) { return openGroups.value.includes(key) }
function saveOpen() { try { localStorage.setItem(NAV_OPEN_KEY, JSON.stringify(openGroups.value)) } catch {} }
function toggleGroup(key) {
  openGroups.value = isOpen(key) ? openGroups.value.filter(k => k !== key) : [...openGroups.value, key]
  saveOpen()
}
// 다른 그룹으로 이동하면 그 그룹을 펼쳐 둠
watch(currentPcGroup, (k) => { if (k && k !== 'home' && !isOpen(k)) { openGroups.value = [...openGroups.value, k]; saveOpen() } }, { immediate: true })
function isItemActive(it) { return route.path === it.to || route.path.startsWith(it.to + '/') }
provide('adminIsMobile', isMobileLayout)

const groupLabels = Object.fromEntries(PC_GROUPS.map(g => [g.key, g.label]))
groupLabels.more = '더보기'
const mTabs = computed(() => [
  { to: '/admin', icon: 'home', label: '홈', active: currentMobileGroup.value === 'home' },
  { to: '/admin/menu/money', icon: 'wallet', label: '돈', active: currentMobileGroup.value === 'money' },
  { to: '/admin/menu/support', icon: 'flag', label: '신고·문의', active: currentMobileGroup.value === 'support' },
  { to: '/admin/menu/content', icon: 'list', label: '콘텐츠', active: currentMobileGroup.value === 'content' },
  { to: '/admin/menu/more', icon: 'menu', label: '더보기', active: currentMobileGroup.value === 'more' || MORE_GROUPS.includes(currentMobileGroup.value) },
])
const isHub = computed(() => route.path.startsWith('/admin/menu/'))
const mTitle = computed(() => {
  const path = route.path
  if (path === '/admin') return '관리자'
  if (isHub.value) return groupLabels[route.params.group] || '관리자'
  if (path === '/admin/overview') return '종합 리포트'
  if (path === '/admin/calls') return '통화 로그'
  if (path === '/admin/members' && route.query.user) return '회원 상세'
  let best = null
  for (const t of ADMIN_ITEMS) {
    if ((path === t.to || path.startsWith(t.to + '/')) && (!best || t.to.length > best.to.length)) best = t
  }
  return best ? best.label : '관리자'
})
const mBack = computed(() => {
  if (route.path === '/admin') return ''
  if (route.path === '/admin/menu/more') return '/admin'
  // '더보기' 아래 하위 그룹(회원·홍보이벤트·시스템) 허브는 더보기로, 그 안의 화면은 자기 허브로 되돌아감
  if (isHub.value) return MORE_GROUPS.includes(String(route.params.group)) ? '/admin/menu/more' : '/admin'
  if (route.path === '/admin/members' && route.query.user) return '/admin/members'
  if (route.path === '/admin/calls') return '/admin/communication'
  if (route.path.startsWith('/admin/games/questions/')) return '/admin/games/settings/' + route.params.slug
  if (route.path.startsWith('/admin/games/')) return '/admin/games'
  return currentMobileGroup.value === 'home' ? '/admin' : `/admin/menu/${currentMobileGroup.value}`
})
// 아직 휴대폰용으로 다시 만들지 않은 화면은 글자를 조금 키워서 보여 줌
// (회원관리·게시판 관리처럼 이미 휴대폰용으로 만든 화면은 확대하지 않음)
const MOBILE_NATIVE = ['/admin/members', '/admin/community', '/admin/jobs', '/admin/market', '/admin/realestate', '/admin/clubs', '/admin/qa', '/admin/events', '/admin/directory', '/admin/friends', '/admin/todos', '/admin/payments', '/admin/security', '/admin/accounts', '/admin/banners', '/admin/flyers', '/admin/ad-center', '/admin/pricing', '/admin/revenue', '/admin/analytics', '/admin/overview', '/admin/open-event', '/admin/entry-settings', '/admin/system', '/admin/sweepstakes', '/admin/hero-banners', '/admin/popup-banners', '/admin/settings', '/admin/claims', '/admin/rewards', '/admin/grants', '/admin/info', '/admin/content', '/admin/boards', '/admin/news', '/admin/shorts', '/admin/recipes', '/admin/groupbuy', '/admin/music', '/admin/shopping', '/admin/games', '/admin/poker', '/admin/elder', '/admin/chats', '/admin/calls', '/admin/communication']
const mLegacy = computed(() => route.path !== '/admin' && !isHub.value && !MOBILE_NATIVE.some(n => route.path === n || (n === '/admin/games' && route.path.startsWith(n + '/'))))

function isMainActive(item) {
  if (item.to === '/admin' && item.group === 'home') return route.path === '/admin'
  return currentPcGroup.value === item.group
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
