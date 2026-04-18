<template>
  <div id="app">
    <Teleport to="body">
      <div class="fixed top-4 right-4 z-[9999] space-y-2">
        <TransitionGroup name="toast">
          <div v-for="toast in siteStore.toasts" :key="toast.id"
            class="flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg text-sm font-medium max-w-sm"
            :class="{
              'bg-green-500 text-white': toast.type === 'success',
              'bg-red-500 text-white': toast.type === 'error',
              'bg-amber-500 text-white': toast.type === 'warning',
              'bg-blue-500 text-white': toast.type === 'info',
            }">
            <span>{{ toast.message }}</span>
          </div>
        </TransitionGroup>
      </div>
    </Teleport>

    <NavBar v-if="showNav" />

    <!-- 사이트 공지 배너 (Phase 2-C Post) -->
    <div v-if="activeAnnouncement && !dismissedAnnouncementIds.includes(activeAnnouncement.id)"
         :class="['px-4 py-2 text-sm flex items-center justify-center gap-3 relative', levelClass(activeAnnouncement.level)]">
      <span class="font-semibold">{{ activeAnnouncement.title }}</span>
      <span>{{ activeAnnouncement.message }}</span>
      <a v-if="activeAnnouncement.link_url" :href="activeAnnouncement.link_url" class="underline font-semibold ml-2">
        {{ activeAnnouncement.link_label || '자세히 →' }}
      </a>
      <button v-if="activeAnnouncement.dismissible" @click="dismissAnnouncement(activeAnnouncement.id)" class="absolute right-4 hover:opacity-70">✕</button>
    </div>

    <!-- Impersonation 배너 (관리자 유저 임시 로그인 시) -->
    <div v-if="isImpersonating" class="bg-red-600 text-white px-4 py-2 text-sm flex items-center justify-center gap-3">
      ⚠️ <strong>Impersonation 모드</strong> — 유저 계정으로 임시 로그인 중
      <button @click="stopImpersonation" class="bg-white text-red-600 px-3 py-0.5 rounded font-semibold hover:bg-red-100">원래 계정으로 복귀</button>
    </div>

    <!-- 글로벌 미니 프로필 팝업 -->
    <UserPopup :show="showUserPopup" :user-id="popupUserId" @close="showUserPopup=false" />
    <SiteModal />

    <CommHub v-if="auth.isLoggedIn && auth.user" ref="commHub" />
    <MiniPlayer />
    <GlobalChatPopup v-if="auth.isLoggedIn" />

    <main>
      <router-view v-slot="{ Component }" :key="route.fullPath">
        <keep-alive :include="['Home']">
          <component :is="Component" :key="route.fullPath" />
        </keep-alive>
      </router-view>
    </main>

    <!-- 푸터 (데스크탑) — Phase 2-C 묶음 5: DB 기반 동적 구성 -->
    <footer v-if="showNav" class="bg-gray-800 text-gray-400 mt-8 hidden md:block">
      <div class="max-w-7xl mx-auto px-4 py-6">
        <div class="grid grid-cols-4 gap-4 mb-4">
          <div>
            <div class="text-amber-400 font-black text-sm mb-2">{{ siteStore.siteName }}</div>
            <div class="text-xs text-gray-500">{{ siteStore.getSetting('site_subtitle', '미국 한인 No.1 커뮤니티') }}</div>
          </div>
          <!-- DB 기반 섹션 (services/content/info) -->
          <template v-for="section in ['services','content','info']" :key="section">
            <div>
              <div class="text-xs font-bold text-gray-300 mb-2">{{ sectionLabel(section) }}</div>
              <template v-if="footerLinks[section]?.length">
                <RouterLink
                  v-for="link in footerLinks[section]"
                  :key="link.id"
                  :to="link.route_path"
                  class="block text-xs hover:text-amber-400 mb-1"
                >{{ link.label }}</RouterLink>
              </template>
              <template v-else>
                <!-- Fallback: DB 실패 시 최소 링크 -->
                <RouterLink v-if="section === 'services'" to="/community" class="block text-xs hover:text-amber-400 mb-1">커뮤니티</RouterLink>
                <RouterLink v-if="section === 'info'" to="/about" class="block text-xs hover:text-amber-400 mb-1">소개</RouterLink>
              </template>
            </div>
          </template>
        </div>
        <div class="border-t border-gray-700 pt-4 text-xs text-center text-gray-500">
          &copy; {{ new Date().getFullYear() }} {{ siteStore.siteName }}. All rights reserved.
        </div>
      </div>
    </footer>

    <BottomNav v-if="showNav" />
    <div v-if="showNav" class="h-14 md:hidden"></div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useRoute } from 'vue-router'
import { useSiteStore } from './stores/site'
import { useAuthStore } from './stores/auth'
import NavBar from './components/NavBar.vue'
import BottomNav from './components/BottomNav.vue'
import UserPopup from './components/UserPopup.vue'
import SiteModal from './components/SiteModal.vue'
import CommHub from './components/comms/CommHub.vue'
import MiniPlayer from './components/MiniPlayer.vue'
import GlobalChatPopup from './components/GlobalChatPopup.vue'

import { useBookmarkStore } from './stores/bookmarks'

const route = useRoute()
const siteStore = useSiteStore()
const auth = useAuthStore()
const bookmarkStore = useBookmarkStore()
const commHub = ref(null)

// 앱 초기화: settings + 북마크 로드
siteStore.load()
if (auth.isLoggedIn) bookmarkStore.loadAll()

// Footer 링크 DB 로드 (Phase 2-C 묶음 5)
const footerLinks = ref({})
const SECTION_LABELS = { services: '서비스', content: '콘텐츠', info: '안내', legal: '법적 고지' }
const sectionLabel = (k) => SECTION_LABELS[k] || k

// 공지 배너 (Phase 2-C Post)
const announcements = ref([])
const dismissedAnnouncementIds = ref(
  (() => { try { return JSON.parse(localStorage.getItem('dismissed_announcements') || '[]') } catch { return [] } })()
)
const activeAnnouncement = computed(() =>
  announcements.value.find(a => !dismissedAnnouncementIds.value.includes(a.id))
)
function dismissAnnouncement(id) {
  dismissedAnnouncementIds.value.push(id)
  localStorage.setItem('dismissed_announcements', JSON.stringify(dismissedAnnouncementIds.value))
}
function levelClass(level) {
  return {
    info:    'bg-blue-500 text-white',
    success: 'bg-green-500 text-white',
    warning: 'bg-amber-500 text-white',
    danger:  'bg-red-600 text-white',
  }[level] || 'bg-blue-500 text-white'
}

// Impersonation 감지 (Phase 2-C Post)
const isImpersonating = computed(() => {
  try {
    const tok = localStorage.getItem('sk_token') || sessionStorage.getItem('sk_token')
    if (!tok) return false
    const parts = tok.split('.')
    if (parts.length !== 3) return false
    const payload = JSON.parse(atob(parts[1].replace(/-/g, '+').replace(/_/g, '/')))
    return !!payload.impersonation
  } catch { return false }
})
async function stopImpersonation() {
  try {
    const { data } = await axios.post('/api/admin/stop-impersonation')
    if (data?.data?.token) {
      auth.setAuth?.(data.data.token, data.data.user)
      // setAuth 없으면 localStorage 에 직접 저장
      localStorage.setItem('sk_token', data.data.token)
      localStorage.setItem('sk_user', JSON.stringify(data.data.user))
      window.location.href = '/admin/v2'
    }
  } catch (e) {
    siteStore.toast('복귀 실패: ' + (e.response?.data?.message || e.message), 'error')
  }
}
onMounted(async () => {
  try {
    const { data } = await axios.get('/api/site/footer-links')
    footerLinks.value = data?.data || {}
  } catch {}
  // 공지 배너 로드
  try {
    const { data } = await axios.get('/api/site/announcements')
    announcements.value = data?.data || []
  } catch {}
  // 설정 변경 브로드캐스트 수신 시 Footer 도 재로드
  try {
    window.Echo?.channel('site-settings')
      .listen('.settings.updated', async (e) => {
        if (!e?.scope || ['company','site','footer','pages','general'].includes(e.scope)) {
          try {
            const { data } = await axios.get('/api/site/footer-links')
            footerLinks.value = data?.data || {}
          } catch {}
        }
      })
  } catch {}
})

// 글로벌: 어디서든 window.openCommChat(partner, convId) / window.startCommCall(partner) 호출 가능
if (typeof window !== 'undefined') {
  window.openCommChat = (partner, conversationId) => {
    commHub.value?.openChat(partner, conversationId)
  }
  window.startCommCall = async (partner) => {
    console.log('[App] startCommCall:', partner?.id, partner?.name)
    try {
      await commHub.value?.startCall(partner)
    } catch (err) {
      console.error('[App] startCommCall error:', err)
      alert('📞 ' + (err?.response?.data?.error || err?.message || '통화 연결 실패'))
    }
  }
}

// 글로벌 유저 팝업
const showUserPopup = ref(false)
const popupUserId = ref(null)

// 글로벌 이벤트: 어디서든 window.openUserPopup(userId) 호출 가능
if (typeof window !== 'undefined') {
  window.openUserPopup = (userId) => {
    if (!userId) return
    popupUserId.value = userId
    showUserPopup.value = true
  }
}

const showNav = computed(() => {
  const p = route.path
  return p !== '/login' && p !== '/register' && !p.startsWith('/admin') && p !== '/games/poker/play' && !p.startsWith('/games/poker/multi') && !p.startsWith('/games/poker/tournament/')
})
</script>

<style>
.toast-enter-active { transition: all 0.3s ease; }
.toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from { transform: translateX(100%); opacity: 0; }
.toast-leave-to { transform: translateX(100%); opacity: 0; }
.scrollbar-hide::-webkit-scrollbar { display: none; }

/* 마퀴 애니메이션 - hover시 긴 제목 스크롤 */
@keyframes marquee {
  0% { transform: translateX(0); }
  100% { transform: translateX(-50%); }
}
.group:hover .group-hover\:animate-marquee {
  animation: marquee 12s linear infinite;
  display: inline-block;
  padding-right: 2rem;
}
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>
