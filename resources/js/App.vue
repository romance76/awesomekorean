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
        <div class="grid grid-cols-4 gap-4 mb-6">
          <div>
            <img v-if="siteStore.logoDarkUrl" :src="siteStore.logoDarkUrl" alt="AwesomeKorean" class="h-7 w-auto mb-2" />
            <div v-else class="text-amber-400 font-black text-sm mb-2">AwesomeKorean</div>
            <div class="text-xs text-gray-400">미국 한인 No.1 커뮤니티</div>
          </div>
          <div>
            <div class="text-xs font-bold text-white mb-2.5">서비스</div>
            <RouterLink to="/community" class="block text-xs text-gray-400 hover:text-amber-400 transition-colors mb-1.5">커뮤니티</RouterLink>
            <RouterLink to="/jobs" class="block text-xs text-gray-400 hover:text-amber-400 transition-colors mb-1.5">구인구직</RouterLink>
            <RouterLink to="/market" class="block text-xs text-gray-400 hover:text-amber-400 transition-colors mb-1.5">중고장터</RouterLink>
            <RouterLink to="/directory" class="block text-xs text-gray-400 hover:text-amber-400 transition-colors mb-1.5">업소록</RouterLink>
          </div>
          <div>
            <div class="text-xs font-bold text-white mb-2.5">콘텐츠</div>
            <RouterLink to="/news" class="block text-xs text-gray-400 hover:text-amber-400 transition-colors mb-1.5">뉴스</RouterLink>
            <RouterLink to="/recipes" class="block text-xs text-gray-400 hover:text-amber-400 transition-colors mb-1.5">레시피</RouterLink>
            <RouterLink to="/games" class="block text-xs text-gray-400 hover:text-amber-400 transition-colors mb-1.5">게임</RouterLink>
            <RouterLink to="/music" class="block text-xs text-gray-400 hover:text-amber-400 transition-colors mb-1.5">음악</RouterLink>
          </div>
          <div>
            <div class="text-xs font-bold text-white mb-2.5">안내</div>
            <RouterLink to="/about" class="block text-xs text-gray-400 hover:text-amber-400 transition-colors mb-1.5">소개</RouterLink>
            <RouterLink to="/terms" class="block text-xs text-gray-400 hover:text-amber-400 transition-colors mb-1.5">이용약관</RouterLink>
            <RouterLink to="/privacy" class="block text-xs text-gray-400 hover:text-amber-400 transition-colors mb-1.5">개인정보처리방침</RouterLink>
          </div>
        </div>
        <div class="border-t border-white/10 pt-4 text-xs text-center text-gray-500">&copy; 2026 AwesomeKorean. All rights reserved.</div>
      </div>
    </footer>

    <BottomNav v-if="showNav" />
    <div v-if="showNav" class="h-14 md:hidden"></div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
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

import { useBookmarkStore } from './stores/bookmarks'

const route = useRoute()
const siteStore = useSiteStore()
const auth = useAuthStore()
const bookmarkStore = useBookmarkStore()
const commHub = ref(null)

// 앱 초기화: settings + 북마크 로드
siteStore.load()
if (auth.isLoggedIn) bookmarkStore.loadAll()

// 출석체크 자동화: 로그인한 상태로 사이트를 여는 순간 하루 1번 자동 출석 (마이페이지에서 누르지 않아도 됨).
// 이미 오늘 했으면 서버가 400 을 주므로 조용히 무시. 같은 날 반복 호출을 막으려고 브라우저에 날짜만 기억.
async function autoCheckin() {
  if (!auth.isLoggedIn || !auth.user?.id) return
  const d = new Date()
  const today = `${d.getFullYear()}-${d.getMonth() + 1}-${d.getDate()}`
  const key = `ak_checkin_${auth.user.id}`
  try { if (localStorage.getItem(key) === today) return } catch {}
  try {
    const { data } = await axios.post('/api/entries/checkin')
    const r = data?.data
    if (r?.entry_awarded) siteStore.toast('🎉 출석 완료! Entry 1개를 받았어요', 'success', 4000)
    else if (r) siteStore.toast(`✅ 오늘 출석 완료 (${r.progress}/${r.required})`, 'success')
  } catch {}   // 400(이미 출석) · 인증 필요 등은 조용히 넘김
  try { localStorage.setItem(key, today) } catch {}
}
watch(() => auth.user?.id, () => { autoCheckin() }, { immediate: true })

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
