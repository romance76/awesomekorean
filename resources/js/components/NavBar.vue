<template>
  <nav class="bg-white/95 backdrop-blur-sm border-b border-gray-100 sticky top-0 z-50" style="padding-top: env(safe-area-inset-top, 0px)">
    <!-- Row 1: 햄버거(모바일) + Logo + Search + Auth -->
    <div class="max-w-7xl mx-auto px-3 flex items-center h-12 gap-2">
      <!-- 햄버거 메뉴 (모바일) -->
      <button @click="mobileMenu = !mobileMenu" class="md:hidden p-2.5 -ml-1 text-ink-light hover:text-amber-500 transition-colors flex-shrink-0">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path v-if="!mobileMenu" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
          <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>

      <!-- Logo -->
      <RouterLink to="/" class="flex items-center flex-shrink-0" aria-label="AwesomeKorean">
        <img src="/images/logo.png" alt="AwesomeKorean" class="h-8 w-auto" style="max-height:32px" />
      </RouterLink>

      <!-- Search (데스크톱만 — 모바일은 햄버거 메뉴 안에) -->
      <div class="flex-1 mx-2 min-w-0 hidden md:block">
        <form @submit.prevent="goSearch" class="relative max-w-lg mx-auto">
          <button type="submit" aria-label="검색" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-faint hover:text-amber-500 transition-colors">
            <AppIcon name="search" :size="16" />
          </button>
          <input v-model="searchQ" type="text" placeholder="궁금한 것을 검색해 보세요 — 영주권, 맛집, 중고차…"
            class="w-full bg-surface border-[1.5px] border-line rounded-full pl-10 pr-4 py-2 text-sm text-ink outline-none transition-all duration-150 placeholder:text-ink-faint focus:bg-white focus:border-amber-400 focus:ring-4 focus:ring-amber-400/10" />
        </form>
      </div>
      <!-- 모바일: 로고 옆 빈 공간 채우기 -->
      <div class="flex-1 md:hidden"></div>

      <!-- Auth -->
      <div class="flex items-center gap-1.5 flex-shrink-0">
        <template v-if="auth.isLoggedIn">
          <div class="relative notif-bell">
            <button @click="toggleNotifs" class="relative p-2 text-ink-light hover:text-amber-500 transition-colors">
              <AppIcon name="bell" :size="20" />
              <span v-if="unreadCount>0" class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[10px] w-4 h-4 rounded-full flex items-center justify-center font-bold">{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
            </button>
            <!-- 알림 드롭다운 -->
            <div v-if="showNotifs" class="absolute right-0 top-10 bg-white rounded-2xl shadow-lift z-50 overflow-hidden" style="width: min(320px, calc(100vw - 2rem));">
              <div class="px-4 py-3 flex items-center justify-between border-b border-gray-50">
                <span class="text-sm font-bold text-ink">알림</span>
                <button v-if="notifList.some(n=>!n.read_at)" @click="markAllRead" class="text-xs text-amber-600 hover:text-amber-700 font-semibold transition-colors">전체 읽음</button>
              </div>
              <div class="max-h-80 overflow-y-auto">
                <div v-if="!notifList.length" class="px-4 py-10 text-center">
                  <div class="icon-chip w-11 h-11 bg-gray-100 text-gray-300 mx-auto mb-2"><AppIcon name="bell" :size="22" :stroke-width="1.5" /></div>
                  <p class="text-sm text-ink-muted">알림이 없습니다</p>
                </div>
                <div v-for="n in notifList" :key="n.id" @click="clickNotif(n)"
                  class="px-4 py-2.5 border-b border-gray-50 last:border-0 cursor-pointer hover:bg-amber-50/40 transition-colors"
                  :class="n.read_at ? '' : 'bg-amber-50/60'">
                  <div class="flex items-start gap-2">
                    <span v-if="!n.read_at" class="w-2 h-2 bg-amber-400 rounded-full flex-shrink-0 mt-1.5"></span>
                    <span v-else class="w-2 h-2 flex-shrink-0"></span>
                    <div class="min-w-0 flex-1">
                      <div class="text-xs font-semibold text-ink truncate">{{ n.title }}</div>
                      <div class="text-xs text-ink-muted truncate">{{ n.content }}</div>
                      <div class="text-[11px] text-ink-faint mt-0.5">{{ formatNotifDate(n.created_at) }}</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="relative">
            <button @click="showDropdown=!showDropdown" class="w-8 h-8 rounded-full bg-amber-400 text-white flex items-center justify-center text-xs font-bold overflow-hidden relative ring-2 ring-amber-100 hover:ring-amber-200 transition-all">
              <img v-if="auth.user?.avatar" :src="auth.user.avatar" alt="avatar" class="absolute inset-0 w-full h-full object-cover" @error="(e)=>{ e.target.style.display='none' }" />
              <span v-else>{{ (auth.user?.name || '?')[0] }}</span>
            </button>
            <div v-if="showDropdown" class="absolute right-0 top-10 bg-white rounded-2xl shadow-lift py-2 w-52 z-50" @click="showDropdown=false">
              <div class="px-4 py-2.5 border-b border-gray-50">
                <div class="text-sm font-bold text-ink truncate">{{ auth.user?.name }}</div>
                <div class="text-xs text-ink-faint truncate">{{ auth.user?.email }}</div>
                <div class="text-xs text-amber-600 font-bold mt-1 flex items-center gap-1"><AppIcon name="coins" :size="13" />{{ auth.user?.points || 0 }}P</div>
              </div>
              <RouterLink to="/dashboard" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-ink-light hover:bg-amber-50/60 hover:text-ink transition-colors"><AppIcon name="user" :size="16" class="text-ink-muted" />마이페이지</RouterLink>
              <RouterLink to="/dashboard?tab=messages" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-ink-light hover:bg-amber-50/60 hover:text-ink transition-colors"><AppIcon name="mail" :size="16" class="text-ink-muted" />쪽지</RouterLink>
              <RouterLink to="/friends" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-ink-light hover:bg-amber-50/60 hover:text-ink transition-colors"><AppIcon name="heart-handshake" :size="16" class="text-ink-muted" />친구</RouterLink>
              <div v-if="auth.isAdmin">
                <div class="border-t border-gray-50 my-1"></div>
                <RouterLink to="/admin" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-red-500 hover:bg-red-50 transition-colors"><AppIcon name="shield" :size="16" />관리자</RouterLink>
              </div>
              <div class="border-t border-gray-50 my-1"></div>
              <button @click="handleLogout" class="w-full flex items-center gap-2.5 text-left px-4 py-2.5 text-sm text-ink-muted hover:bg-gray-50 transition-colors"><AppIcon name="log-out" :size="16" />로그아웃</button>
            </div>
          </div>
        </template>
        <template v-else>
          <RouterLink to="/login" class="text-[13px] font-semibold text-ink-light hover:text-ink hover:bg-surface px-3 py-1.5 rounded-full transition-colors">로그인</RouterLink>
          <RouterLink to="/register" class="text-[13px] text-white font-bold px-4 py-1.5 rounded-full transition-all shadow-btn hover:-translate-y-px" style="background-image:linear-gradient(135deg,#FF7A30,#FF4D12)">시작하기</RouterLink>
        </template>
        <button @click="togglePageLang()" translate="no" class="notranslate text-[11px] font-bold px-2.5 py-1.5 rounded-full text-ink-muted bg-surface hover:bg-line transition-colors" :title="isTranslatedEn ? '한국어로 돌아가기' : 'Translate to English'">
          <span translate="no" class="notranslate">{{ isTranslatedEn ? '한' : 'EN' }}</span>
        </button>
      </div>
    </div>

    <!-- Row 2: 데스크톱 메뉴 -->
    <div class="border-t border-gray-50 hidden md:block">
      <div class="max-w-7xl mx-auto px-4 flex justify-center items-center h-10 overflow-x-auto scrollbar-hide">
        <RouterLink v-for="item in visibleMenus" :key="item.path" :to="item.path"
          class="text-[13px] font-semibold px-3 py-2.5 border-b-2 whitespace-nowrap transition-colors duration-150"
          :class="isActive(item.path) ? 'border-amber-400 text-amber-600' : 'border-transparent text-ink-light hover:text-ink'">
          {{ item.label }}
        </RouterLink>
      </div>
    </div>

    <!-- 모바일 메뉴 (슬라이드) -->
    <Teleport to="body">
      <Transition name="menu-fade">
        <div v-if="mobileMenu" class="fixed inset-0 bg-black/40 z-[999]" @click="mobileMenu=false"></div>
      </Transition>
      <Transition name="menu-slide">
        <div v-if="mobileMenu" class="fixed top-0 left-0 bottom-0 w-[85vw] max-w-sm bg-white z-[1000] shadow-2xl overflow-y-auto"
             style="padding-top: var(--sat); padding-bottom: var(--sab)">
          <!-- 헤더 -->
          <div class="flex items-center justify-between px-4 py-3 border-b border-gray-50">
            <span class="text-sm font-bold text-ink">전체 메뉴</span>
            <button @click="mobileMenu=false" class="p-1 text-ink-faint hover:text-ink transition-colors">
              <AppIcon name="x" :size="20" />
            </button>
          </div>
          <!-- 검색 -->
          <div class="px-3 py-2.5 border-b border-gray-50">
            <form @submit.prevent="goSearch(); mobileMenu=false" class="relative">
              <button type="submit" aria-label="검색" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-faint">
                <AppIcon name="search" :size="16" />
              </button>
              <input v-model="searchQ" type="text" placeholder="궁금한 것을 검색해 보세요"
                class="w-full bg-surface border-[1.5px] border-line rounded-full pl-10 pr-4 py-3 text-sm text-ink outline-none placeholder:text-ink-faint focus:bg-white focus:border-amber-400 focus:ring-4 focus:ring-amber-400/10 transition-all" />
            </form>
          </div>
          <!-- 메뉴 목록 -->
          <div class="py-2">
            <div v-for="item in visibleMenus" :key="item.path"
              class="flex items-center transition-colors"
              :class="isActive(item.path) ? 'bg-amber-50/70' : 'hover:bg-gray-50'">
              <RouterLink :to="item.path" @click="mobileMenu=false"
                class="flex items-center gap-3 flex-1 min-w-0 px-4 py-2 text-sm"
                :class="isActive(item.path) ? 'text-amber-700 font-bold' : 'text-ink-light'">
                <span class="icon-chip w-8 h-8" :class="menuChipColor(item.key)"><AppIcon :name="menuIcon(item.key)" :size="16" /></span>
                <span>{{ item.label }}</span>
              </RouterLink>
              <button @click.stop="navFavStore.toggleFavorite(item.key)"
                class="px-3 py-2.5 flex-shrink-0 transition-colors"
                :class="navFavStore.isFavorite(item.key) ? 'text-amber-400' : 'text-gray-200 hover:text-amber-300'"
                :title="navFavStore.isFavorite(item.key) ? '하단바에서 제거' : '하단바에 추가'">
                <AppIcon name="star" :size="18" :filled="navFavStore.isFavorite(item.key)" />
              </button>
            </div>
          </div>
          <!-- 즐겨찾기 안내 -->
          <div class="px-4 py-2.5 border-t border-gray-50 text-[11px] text-ink-faint flex items-center gap-1">
            <AppIcon name="star" :size="12" /> 눌러서 하단 메뉴에 추가/제거 (최대 {{ navFavStore.MAX_FAVORITES }}개)
          </div>
        </div>
      </Transition>
    </Teleport>
  </nav>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useLangStore } from '../stores/lang'
import AppIcon from './AppIcon.vue'
import { menuIcon, menuChipColor } from '../utils/menuIcons'

const auth = useAuthStore()
const langStore = useLangStore()

// Google Translate 위젯 연동: googtrans 쿠키 토글 + reload
const isTranslatedEn = ref(typeof document !== 'undefined' && document.cookie.includes('googtrans=/ko/en'))
function togglePageLang() {
  const host = location.hostname
  // www. 를 뗀 루트 도메인 (예: awesomekorean.com)
  const root = host.replace(/^www\./, '')
  const domains = ['', host, '.' + host, '.' + root]
  // 기존 googtrans 쿠키 모두 제거
  for (const d of domains) {
    document.cookie = 'googtrans=; path=/; max-age=0' + (d ? `; domain=${d}` : '')
  }
  if (!isTranslatedEn.value) {
    // 한국어 → 영어
    document.cookie = 'googtrans=/ko/en; path=/'
    document.cookie = 'googtrans=/ko/en; path=/; domain=.' + root
    langStore.setLang('en')
  } else {
    // 영어 → 한국어
    langStore.setLang('ko')
  }
  location.reload()
}
import { useNavFavoritesStore } from '../stores/navFavorites'
const navFavStore = useNavFavoritesStore()
const router = useRouter()
const route = useRoute()
const searchQ = ref('')
const showDropdown = ref(false)
const mobileMenu = ref(false)
const unreadCount = ref(0)
const showNotifs = ref(false)
const notifList = ref([])
import { useSiteStore } from '../stores/site'
const siteStore = useSiteStore()

// ⚠️ Issue #18: 실제 노출 메뉴는 DB site_settings.menu_config 가 오버라이드한다.
// - 관리자 UI:    /admin/site-settings → "메뉴 구성" 탭
// - DB 조회:      SELECT value FROM site_settings WHERE key='menu_config';
// - 이 배열은 DB 값이 없을 때의 폴백일 뿐이며, 운영 상태와 다를 수 있음.
const defaultMenus = [
  { key: 'home', label: '홈', label_en: 'Home', icon: '🏠', path: '/', enabled: true },
  { key: 'community', label: '커뮤니티', label_en: 'Community', icon: '💬', path: '/community', enabled: true },
  { key: 'qa', label: 'Q&A', label_en: 'Q&A', icon: '❓', path: '/qa', enabled: true },
  { key: 'jobs', label: '구인구직', label_en: 'Jobs', icon: '💼', path: '/jobs', enabled: true },
  { key: 'market', label: '중고장터', label_en: 'Market', icon: '🛒', path: '/market', enabled: true },
  { key: 'directory', label: '업소록', label_en: 'Directory', icon: '📋', path: '/directory', enabled: true },
  { key: 'realestate', label: '부동산', label_en: 'Real Estate', icon: '🏠', path: '/realestate', enabled: true },
  { key: 'events', label: '이벤트', label_en: 'Events', icon: '🎉', path: '/events', enabled: true },
  { key: 'news', label: '뉴스', label_en: 'News', icon: '📰', path: '/news', enabled: true },
  { key: 'recipes', label: '레시피', label_en: 'Recipes', icon: '🍳', path: '/recipes', enabled: true },
  { key: 'clubs', label: '동호회', label_en: 'Clubs', icon: '👥', path: '/clubs', enabled: true },
  { key: 'games', label: '게임', label_en: 'Games', icon: '🎮', path: '/games', enabled: true },
  { key: 'shorts', label: '숏츠', label_en: 'Shorts', icon: '📱', path: '/shorts', enabled: true },
  { key: 'music', label: '음악듣기', label_en: 'Music', icon: '🎵', path: '/music', enabled: true },
  { key: 'groupbuy', label: '공동구매', label_en: 'Group Buy', icon: '🤝', path: '/groupbuy', enabled: true },
  { key: 'chat', label: '채팅', label_en: 'Chat', icon: '💭', path: '/chat', enabled: true, login_required: true },
  { key: 'friends', label: '친구', label_en: 'Friends', icon: '👫', path: '/friends', enabled: true, login_required: true },
  { key: 'elder', label: '안심서비스', label_en: 'Elder Care', icon: '💙', path: '/elder', enabled: true },
  { key: 'comms', label: '안심 커뮤', label_en: 'Comms', icon: '📞', path: '/comms', enabled: true },
  { key: 'shopping', label: '쇼핑', label_en: 'Shopping', icon: '🛍️', path: '/shopping', enabled: false },
]

const visibleMenus = computed(() => {
  const ko = langStore.locale === 'ko'
  const mc = siteStore.menuConfig
  if (mc && Array.isArray(mc)) {
    const defaultMap = {}
    defaultMenus.forEach(m => { defaultMap[m.key] = m })
    return mc
      .filter(m => m.enabled !== false)
      .filter(m => !m.admin_only || auth.isAdmin)
      .filter(m => !m.login_required || auth.isLoggedIn)
      .map(m => {
        const def = defaultMap[m.key] || {}
        return {
          ...def, ...m,
          path: m.path || def.path || `/${m.key}`,
          label: ko ? (m.label || def.label || m.key) : (m.label_en || def.label_en || m.label || def.label || m.key),
          icon: m.icon || def.icon || '📄',
        }
      })
      .filter(m => m.path)
  }
  return defaultMenus
    .filter(m => m.enabled !== false)
    .filter(m => !m.login_required || auth.isLoggedIn)
    .map(m => ({ ...m, label: ko ? m.label : (m.label_en || m.label) }))
})

// menuConfig는 siteStore.load()에서 자동으로 로드됨

async function loadUnread() {
  if (!auth.isLoggedIn) return
  try {
    const { data } = await axios.get('/api/notifications')
    unreadCount.value = data.unread_count || 0
    notifList.value = data.data?.data || data.data || []
  } catch {}
}

function formatNotifDate(dt) {
  if (!dt) return ''
  const d = new Date(dt), now = new Date()
  const m = d.getMonth() + 1, day = d.getDate()
  const hh = String(d.getHours()).padStart(2, '0'), mm = String(d.getMinutes()).padStart(2, '0')
  if (d.getFullYear() === now.getFullYear() && m === now.getMonth() + 1 && day === now.getDate()) return `오늘 ${hh}:${mm}`
  if (d.getFullYear() === now.getFullYear()) return `${m}/${day} ${hh}:${mm}`
  return `${d.getFullYear()}.${m}.${day} ${hh}:${mm}`
}

async function toggleNotifs() {
  showNotifs.value = !showNotifs.value
  if (showNotifs.value) await loadUnread()
}

async function markAllRead() {
  try {
    await axios.post('/api/notifications/read')
    notifList.value.forEach(n => n.read_at = new Date().toISOString())
    unreadCount.value = 0
  } catch {}
}

function clickNotif(n) {
  // 읽음 처리
  if (!n.read_at) {
    n.read_at = new Date().toISOString()
    unreadCount.value = Math.max(0, unreadCount.value - 1)
    axios.post(`/api/notifications/${n.id}/read`).catch(() => {})
  }
  showNotifs.value = false
  const dest = resolveNotifRoute(n)
  if (dest) router.push(dest)
}

// 알림 타입별 이동 경로 결정 (DB 스키마: 알림은 url 컬럼 없음, data JSON 로 추가 정보)
function resolveNotifRoute(n) {
  const d = n.data || {}
  // 명시적 URL (일부 알림은 data.url 로 지정)
  if (d.url) return d.url
  // 타입별 매핑
  if (n.type === 'message') return '/dashboard?tab=messages'
  if (n.type === 'friend_request') return '/friends'
  if (n.type === 'elder_call_missed') return '/elder/guardian'
  if (n.type === 'elder_checkin_missed') {
    return (d.elder_user_id || d.ward_id) ? '/elder/guardian' : '/elder/checkin'
  }
  if (n.type?.startsWith('elder_')) return '/elder'
  if (n.type === 'market_reservation_expired' || n.type === 'reservation_expired') {
    return d.item_id ? `/market/${d.item_id}` : '/market'
  }
  if (n.type === 'comment' && d.post_id) return `/community/post/${d.post_id}`
  if (n.type === 'system') return '/dashboard'
  // 폴백: 대시보드
  return '/dashboard'
}

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}

if (typeof window !== 'undefined') {
  window.addEventListener('click', (e) => {
    if (showDropdown.value && !e.target.closest('.relative')) showDropdown.value = false
    if (showNotifs.value && !e.target.closest('.notif-bell')) showNotifs.value = false
  })
}

function isActive(path) {
  if (path === '/') return route.path === '/'
  return route.path.startsWith(path)
}

function goSearch() {
  if (searchQ.value.trim()) {
    router.push({ path: '/search', query: { q: searchQ.value.trim() } })
    searchQ.value = ''
  }
}

let pollInterval = null
let echoChannel = null

onMounted(() => {
  loadUnread()
  siteStore.load() // settings + menuConfig 한번에
  if (auth.isLoggedIn) {
    // WebSocket 실시간 알림 수신
    if (window.Echo && auth.user?.id) {
      echoChannel = window.Echo.private(`user.${auth.user.id}`)
      echoChannel.listen('.notification.new', (e) => {
        unreadCount.value = e.unread_count || unreadCount.value + 1
        loadUnread() // 목록도 새로고침
      })
    }
    // WebSocket 실패 시 fallback: 120초 폴링
    pollInterval = setInterval(() => {
      loadUnread()
      axios.post('/api/heartbeat').catch(() => {})
    }, 120000)
  }
})

function onVisibility() {
  if (!document.hidden && auth.isLoggedIn) loadUnread()
}

onMounted(() => { document.addEventListener('visibilitychange', onVisibility) })

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval)
  if (echoChannel) echoChannel.stopListening('.notification.new')
  document.removeEventListener('visibilitychange', onVisibility)
})
</script>

<style scoped>
.menu-fade-enter-active, .menu-fade-leave-active { transition: opacity 0.2s; }
.menu-fade-enter-from, .menu-fade-leave-to { opacity: 0; }
.menu-slide-enter-active, .menu-slide-leave-active { transition: transform 0.25s ease; }
.menu-slide-enter-from, .menu-slide-leave-to { transform: translateX(-100%); }
</style>
