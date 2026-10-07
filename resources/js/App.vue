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
    <!-- 글로벌 미니 프로필 팝업 -->
    <UserPopup :show="showUserPopup" :user-id="popupUserId" @close="showUserPopup=false" />
    <SiteModal />

    <CommHub v-if="auth.isLoggedIn && auth.user" ref="commHub" />
    <MiniPlayer />
    <GlobalChatPopup v-if="auth.isLoggedIn" />
    <PopupBanner v-if="showNav" />
    <CheckinPrompt v-if="showNav && auth.isLoggedIn" />

    <main>
      <router-view v-slot="{ Component }" :key="route.fullPath">
        <keep-alive :include="['Home']">
          <component :is="Component" :key="route.fullPath" />
        </keep-alive>
      </router-view>
    </main>

    <!-- 푸터 (데스크탑) — 본문과 구분되도록 어두운 남색(slate) 배경으로.
         기존엔 bg-surface(연한 살구색)라 본문 카드들과 거의 구분이 안 됐음. -->
    <footer v-if="showNav" class="bg-slate-800 hidden md:block mt-6">
      <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="grid gap-4 mb-6" :style="{ gridTemplateColumns: `repeat(${footerCfg.columns.length + 1}, minmax(0, 1fr))` }">
          <div>
            <img v-if="siteStore.logoDarkUrl" :src="siteStore.logoDarkUrl" alt="AwesomeKorean" class="h-7 w-auto mb-2" />
            <div v-else class="text-amber-400 font-black text-sm mb-2">AwesomeKorean</div>
            <div v-if="footerCfg.tagline" class="text-xs text-gray-400">{{ footerCfg.tagline }}</div>
          </div>
          <div v-for="(col, ci) in footerCfg.columns" :key="ci">
            <div class="text-xs font-bold text-white mb-2.5">{{ col.title }}</div>
            <template v-for="(link, li) in col.links" :key="li">
              <RouterLink v-if="link.url && link.url.startsWith('/')" :to="link.url" class="block text-xs text-gray-400 hover:text-amber-400 transition-colors mb-1.5">{{ link.label }}</RouterLink>
              <a v-else-if="link.url" :href="link.url" target="_blank" rel="noopener noreferrer" class="block text-xs text-gray-400 hover:text-amber-400 transition-colors mb-1.5">{{ link.label }}</a>
            </template>
          </div>
        </div>
        <div class="border-t border-white/10 pt-4 flex items-center justify-between gap-4 text-xs text-gray-500">
          <span>{{ footerCfg.copyright }}</span>
          <span v-if="footerSns.length" class="flex items-center gap-3">
            <a v-for="s in footerSns" :key="s.label" :href="s.url" target="_blank" rel="noopener noreferrer" class="hover:text-amber-400 transition-colors">{{ s.label }}</a>
          </span>
        </div>
        <p v-if="footerCfg.additional_text" class="text-xs text-gray-600 mt-3 whitespace-pre-line">{{ footerCfg.additional_text }}</p>
      </div>
    </footer>

    <BottomNav v-if="showNav" />
    <div v-if="showNav" class="h-14 md:hidden"></div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
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
import PopupBanner from './components/PopupBanner.vue'
import CheckinPrompt from './components/CheckinPrompt.vue'
import { DEFAULT_FOOTER, readSavedFooter } from './utils/footerDefaults'

import { useBookmarkStore } from './stores/bookmarks'

const route = useRoute()
const siteStore = useSiteStore()
const auth = useAuthStore()
const bookmarkStore = useBookmarkStore()
const commHub = ref(null)

// 앱 초기화: settings + 북마크 로드
siteStore.load()

// 푸터: 관리자 "푸터 편집"에서 저장한 값이 있으면 그걸, 없으면 기본 푸터
const footerCfg = computed(() => {
  const saved = readSavedFooter(siteStore.settings?.footer_config)
  return { ...DEFAULT_FOOTER, ...(saved || {}), columns: (saved?.columns || DEFAULT_FOOTER.columns) }
})
const footerSns = computed(() => {
  const s = footerCfg.value.sns || {}
  return [['facebook', 'Facebook'], ['instagram', 'Instagram'], ['twitter', 'Twitter/X'], ['youtube', 'YouTube'], ['kakao', 'KakaoTalk']]
    .filter(([k]) => /^https?:\/\//i.test(s[k] || '')).map(([k, label]) => ({ label, url: s[k] }))
})
if (auth.isLoggedIn) bookmarkStore.loadAll()

// 방문자 집계: 브라우저마다 임의 ID 하나를 만들어 두고 2분마다 "아직 보고 있어요" 신호를 보낸다.
// 서버는 오늘(미국 동부 날짜) 같은 ID 를 한 번만 세므로, 사이트를 오가는 사람도 하루 1명으로 집계된다.
let visitTimer = null
function visitorId() {
  try {
    let v = localStorage.getItem('ak_vid')
    if (!v) { v = (crypto.randomUUID?.() || Math.random().toString(36).slice(2) + Date.now().toString(36)); localStorage.setItem('ak_vid', v) }
    return v
  } catch { return 'anon-' + Math.random().toString(36).slice(2, 12) }
}
function sendVisit() {
  if (document.visibilityState === 'hidden') return
  axios.post('/api/site/ping', { vid: visitorId() }).catch(() => {})
}
onMounted(() => { sendVisit(); visitTimer = setInterval(sendVisit, 120000); document.addEventListener('visibilitychange', sendVisit) })
onUnmounted(() => { clearInterval(visitTimer); document.removeEventListener('visibilitychange', sendVisit) })

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
