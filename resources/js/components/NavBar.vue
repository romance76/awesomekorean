<template>
  <nav class="bg-white border-b border-gray-200 sticky top-0 z-50" style="padding-top: env(safe-area-inset-top, 0px)">
    <!-- Row 1: 햄버거(모바일) + Logo + Search + Auth -->
    <div class="max-w-7xl mx-auto px-3 flex items-center h-12 gap-2">
      <!-- 햄버거 메뉴 (모바일) -->
      <button @click="mobileMenu = !mobileMenu" class="md:hidden p-2.5 -ml-1 text-gray-500 hover:text-amber-600 flex-shrink-0">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path v-if="!mobileMenu" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
          <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>

      <!-- Logo -->
      <RouterLink to="/" class="flex items-center flex-shrink-0">
        <img src="/images/logo_00.jpg" alt="SomeKorean" class="h-8 rounded-lg object-contain" @error="e => e.target.outerHTML='<div class=\'w-7 h-7 bg-amber-400 rounded-lg flex items-center justify-center text-xs font-black text-amber-900\'>SK</div>'" />
      </RouterLink>

      <!-- Search (데스크톱만 — 모바일은 햄버거 메뉴 안에) -->
      <div class="flex-1 mx-2 min-w-0 hidden md:block">
        <form @submit.prevent="goSearch" class="flex border border-amber-400 rounded-lg overflow-hidden max-w-lg mx-auto">
          <input v-model="searchQ" type="text" placeholder="검색어를 입력하세요"
            class="flex-1 px-3 py-1.5 text-sm outline-none min-w-0" />
          <button type="submit" class="bg-amber-400 px-3 text-amber-900 hover:bg-amber-500 transition flex-shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          </button>
        </form>
      </div>
      <!-- 모바일: 로고 옆 빈 공간 채우기 -->
      <div class="flex-1 md:hidden"></div>

      <!-- Auth -->
      <div class="flex items-center gap-1.5 flex-shrink-0">
        <template v-if="auth.isLoggedIn">
          <div class="relative notif-bell">
            <button @click="toggleNotifs" class="relative p-2 text-gray-500 hover:text-amber-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
              <span v-if="unreadCount>0" class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[9px] w-4 h-4 rounded-full flex items-center justify-center font-bold">{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
            </button>
            <!-- 알림 드롭다운 -->
            <div v-if="showNotifs" class="absolute right-0 top-10 bg-white border border-gray-200 rounded-xl shadow-xl z-50 overflow-hidden" style="width: min(320px, calc(100vw - 2rem));">
              <div class="px-4 py-2.5 border-b flex items-center justify-between bg-amber-50">
                <span class="text-sm font-bold text-amber-900">🔔 알림</span>
                <button v-if="notifList.some(n=>!n.read_at)" @click="markAllRead" class="text-[10px] text-amber-600 hover:text-amber-800 font-bold">전체 읽음</button>
              </div>
              <div class="max-h-80 overflow-y-auto">
                <div v-if="!notifList.length" class="px-4 py-8 text-center text-gray-400 text-sm">알림이 없습니다</div>
                <div v-for="n in notifList" :key="n.id" @click="clickNotif(n)"
                  class="px-4 py-2.5 border-b last:border-0 cursor-pointer hover:bg-amber-50/50 transition"
                  :class="n.read_at ? '' : 'bg-amber-50'">
                  <div class="flex items-start gap-2">
                    <span v-if="!n.read_at" class="w-2 h-2 bg-amber-500 rounded-full flex-shrink-0 mt-1.5"></span>
                    <span v-else class="w-2 h-2 flex-shrink-0"></span>
                    <div class="min-w-0 flex-1">
                      <div class="text-xs font-bold text-gray-800 truncate">{{ n.title }}</div>
                      <div class="text-[11px] text-gray-500 truncate">{{ n.content }}</div>
                      <div class="text-[10px] text-gray-400 mt-0.5">{{ formatNotifDate(n.created_at) }}</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="relative">
            <button @click="showDropdown=!showDropdown" class="w-8 h-8 rounded-full bg-amber-500 text-white flex items-center justify-center text-xs font-bold">
              {{ (auth.user?.name || '?')[0] }}
            </button>
            <div v-if="showDropdown" class="absolute right-0 top-9 bg-white border border-gray-200 rounded-xl shadow-lg py-2 w-48 z-50" @click="showDropdown=false">
              <div class="px-4 py-2 border-b">
                <div class="text-sm font-bold text-gray-800 truncate">{{ auth.user?.name }}</div>
                <div class="text-[10px] text-gray-400 truncate">{{ auth.user?.email }}</div>
                <div class="text-[10px] text-amber-600 font-semibold mt-0.5">{{ auth.user?.points || 0 }}P</div>
              </div>
              <RouterLink to="/dashboard" class="block px-4 py-2 text-sm text-gray-600 hover:bg-amber-50">👤 마이페이지</RouterLink>
              <RouterLink to="/dashboard?tab=messages" class="block px-4 py-2 text-sm text-gray-600 hover:bg-amber-50">✉️ 쪽지</RouterLink>
              <RouterLink to="/friends" class="block px-4 py-2 text-sm text-gray-600 hover:bg-amber-50">👫 친구</RouterLink>
              <div v-if="auth.isAdmin">
                <div class="border-t my-1"></div>
                <RouterLink to="/admin" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50">🔧 관리자</RouterLink>
              </div>
              <div class="border-t my-1"></div>
              <button @click="handleLogout" class="w-full text-left px-4 py-2 text-sm text-gray-500 hover:bg-gray-50">🚪 로그아웃</button>
            </div>
          </div>
        </template>
        <template v-else>
          <RouterLink to="/login" class="text-xs text-gray-600 hover:text-amber-700 px-1.5 py-1 hidden sm:block">로그인</RouterLink>
          <RouterLink to="/register" class="text-xs bg-amber-400 text-amber-900 font-bold px-3 py-1 rounded-lg hover:bg-amber-500">가입</RouterLink>
        </template>
        <button @click="langStore.toggle()" class="text-[11px] font-bold px-2 py-1 rounded border border-gray-200">
          {{ langStore.locale === 'ko' ? 'EN' : '한' }}
        </button>
      </div>
    </div>

    <!-- Row 2: 데스크톱 메뉴 -->
    <div class="border-t border-gray-100 hidden md:block">
      <div class="max-w-7xl mx-auto px-4 flex justify-center items-center h-10 overflow-x-auto scrollbar-hide">
        <RouterLink v-for="item in visibleMenus" :key="item.path" :to="item.path"
          class="text-xs font-semibold px-3 py-2.5 border-b-2 whitespace-nowrap transition"
          :class="isActive(item.path) ? 'border-amber-500 text-amber-700' : 'border-transparent text-gray-500 hover:text-amber-600 hover:border-amber-300'">
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
          <div class="flex items-center justify-between px-4 py-3 border-b">
            <span class="text-sm font-bold text-amber-700">전체 메뉴</span>
            <button @click="mobileMenu=false" class="p-1 text-gray-400 hover:text-gray-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <!-- 검색 -->
          <div class="px-3 py-2 border-b">
            <form @submit.prevent="goSearch(); mobileMenu=false" class="flex border border-amber-400 rounded-lg overflow-hidden">
              <input v-model="searchQ" type="text" placeholder="검색어를 입력하세요"
                class="flex-1 px-3 py-2 text-sm outline-none" />
              <button type="submit" class="bg-amber-400 px-3 text-amber-900">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
              </button>
            </form>
          </div>
          <!-- 메뉴 목록 -->
          <div class="py-2">
            <div v-for="item in visibleMenus" :key="item.path"
              class="flex items-center transition"
              :class="isActive(item.path) ? 'bg-amber-50' : 'hover:bg-gray-50'">
              <RouterLink :to="item.path" @click="mobileMenu=false"
                class="flex items-center gap-3 flex-1 min-w-0 px-4 py-2.5 text-sm"
                :class="isActive(item.path) ? 'text-amber-700 font-bold' : 'text-gray-600'">
                <span class="text-base">{{ item.icon || '📄' }}</span>
                <span>{{ item.label }}</span>
              </RouterLink>
              <button @click.stop="navFavStore.toggleFavorite(item.key)"
                class="px-3 py-2.5 text-sm flex-shrink-0 transition"
                :class="navFavStore.isFavorite(item.key) ? 'text-amber-400' : 'text-gray-300 hover:text-amber-300'"
                :title="navFavStore.isFavorite(item.key) ? '하단바에서 제거' : '하단바에 추가'">
                {{ navFavStore.isFavorite(item.key) ? '⭐' : '☆' }}
              </button>
            </div>
          </div>
          <!-- 즐겨찾기 안내 -->
          <div class="px-4 py-2 border-t text-[10px] text-gray-400">
            ⭐ 눌러서 하단 메뉴에 추가/제거 (최대 {{ navFavStore.MAX_FAVORITES }}개)
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

const auth = useAuthStore()
const langStore = useLangStore()
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
  // 쪽지 알림이면 대시보드 쪽지 탭으로
  if (n.type === 'message') {
    router.push('/dashboard?tab=messages')
  } else if (n.data?.url) {
    router.push(n.data.url)
  }
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
