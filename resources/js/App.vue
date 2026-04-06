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

    <main>
      <router-view v-slot="{ Component }">
        <keep-alive :include="['Home']">
          <component :is="Component" />
        </keep-alive>
      </router-view>
    </main>

    <!-- 푸터 (데스크탑) -->
    <footer v-if="showNav" class="bg-gray-800 text-gray-400 mt-8 hidden md:block">
      <div class="max-w-7xl mx-auto px-4 py-6">
        <div class="grid grid-cols-4 gap-4 mb-4">
          <div>
            <div class="text-amber-400 font-black text-sm mb-2">SomeKorean</div>
            <div class="text-xs text-gray-500">미국 한인 No.1 커뮤니티</div>
          </div>
          <div>
            <div class="text-xs font-bold text-gray-300 mb-2">서비스</div>
            <RouterLink to="/community" class="block text-xs hover:text-amber-400 mb-1">커뮤니티</RouterLink>
            <RouterLink to="/jobs" class="block text-xs hover:text-amber-400 mb-1">구인구직</RouterLink>
            <RouterLink to="/market" class="block text-xs hover:text-amber-400 mb-1">중고장터</RouterLink>
            <RouterLink to="/directory" class="block text-xs hover:text-amber-400 mb-1">업소록</RouterLink>
          </div>
          <div>
            <div class="text-xs font-bold text-gray-300 mb-2">콘텐츠</div>
            <RouterLink to="/news" class="block text-xs hover:text-amber-400 mb-1">뉴스</RouterLink>
            <RouterLink to="/recipes" class="block text-xs hover:text-amber-400 mb-1">레시피</RouterLink>
            <RouterLink to="/games" class="block text-xs hover:text-amber-400 mb-1">게임</RouterLink>
            <RouterLink to="/music" class="block text-xs hover:text-amber-400 mb-1">음악</RouterLink>
          </div>
          <div>
            <div class="text-xs font-bold text-gray-300 mb-2">안내</div>
            <RouterLink to="/about" class="block text-xs hover:text-amber-400 mb-1">소개</RouterLink>
            <RouterLink to="/terms" class="block text-xs hover:text-amber-400 mb-1">이용약관</RouterLink>
            <RouterLink to="/privacy" class="block text-xs hover:text-amber-400 mb-1">개인정보처리방침</RouterLink>
          </div>
        </div>
        <div class="border-t border-gray-700 pt-4 text-xs text-center text-gray-500">&copy; 2026 SomeKorean. All rights reserved.</div>
      </div>
    </footer>

    <BottomNav v-if="showNav" />
    <div v-if="showNav" class="h-14 md:hidden"></div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useSiteStore } from './stores/site'
import NavBar from './components/NavBar.vue'
import BottomNav from './components/BottomNav.vue'

const route = useRoute()
const siteStore = useSiteStore()

const showNav = computed(() => {
  const p = route.path
  return p !== '/login' && p !== '/register' && !p.startsWith('/admin')
})
</script>

<style>
.toast-enter-active { transition: all 0.3s ease; }
.toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from { transform: translateX(100%); opacity: 0; }
.toast-leave-to { transform: translateX(100%); opacity: 0; }
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>
