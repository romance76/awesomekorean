<template>
  <div v-if="!hideOnChatRoom && !pageKeyboardOpen" class="fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-sm border-t border-gray-100 z-50 md:hidden"
       style="padding-bottom: var(--sab)">
    <!-- 스와이프 가능한 탭 영역 -->
    <div ref="scrollEl" class="flex items-center h-13 overflow-x-auto scrollbar-hide scroll-smooth"
         @touchstart="onTouchStart" @touchend="onTouchEnd">
      <component :is="item.external ? 'a' : RouterLink" v-for="item in favMenus" :key="item.groupKey || item.path"
        v-bind="item.external ? { href: item.path } : { to: item.path }"
        class="flex flex-col items-center justify-center gap-1 flex-shrink-0 transition-colors"
        :style="tabStyle"
        :class="itemActive(item) ? 'text-ink' : 'text-ink-light'">
        <!-- 메뉴별 색 칩 (사이드 메뉴와 같은 색) — 활성은 진하게 + 테두리 -->
        <span class="icon-chip w-8 h-7 transition-shadow" :class="[menuChipColor(item.key), itemActive(item) ? 'ring-2 ring-black/15' : '']">
          <AppIcon :name="menuIcon(item.key)" :size="18" :stroke-width="itemActive(item) ? 2.4 : 2" />
        </span>
        <span class="text-[10px] leading-none whitespace-nowrap" :class="itemActive(item) ? 'font-bold' : 'font-semibold'" :style="item.color ? { color: item.color } : null">{{ item.label }}</span>
      </component>
    </div>
    <!-- 스와이프 인디케이터 (6개 이상일 때) -->
    <div v-if="favMenus.length > 5" class="flex justify-center gap-1 pb-0.5">
      <div v-for="i in Math.ceil(favMenus.length / 5)" :key="i"
        class="w-1 h-1 rounded-full transition-colors"
        :class="currentPage === i - 1 ? 'bg-amber-400' : 'bg-gray-200'"></div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { useNavFavoritesStore } from '../stores/navFavorites'
import AppIcon from './AppIcon.vue'
import { menuIcon, menuChipColor } from '../utils/menuIcons'
import { pageKeyboardOpen } from '../composables/useKeyboardViewport'

const route = useRoute()
const navStore = useNavFavoritesStore()
const scrollEl = ref(null)
const currentPage = ref(0)

const favMenus = computed(() => navStore.favoriteMenus)

// 채팅방 입장 (/chat/:id) 시에는 하단 네비 숨김 — 대기실(/chat) 에서는 정상 표시
const hideOnChatRoom = computed(() => {
  const p = route.path || ''
  return /^\/chat\/\d+/.test(p)
})

// 탭 너비: 5개 이하면 균등 분배, 6개 이상이면 고정 75px
const tabStyle = computed(() => {
  const count = favMenus.value.length
  if (count <= 5) {
    return { width: (100 / count) + '%', minWidth: '60px' }
  }
  return { width: '75px', minWidth: '75px' }
})

function itemActive(item) {
  return item.matchPaths ? item.matchPaths.some(isActive) : isActive(item.path)
}

function isActive(path) {
  if (path === '/') return route.path === '/'
  return route.path === path || route.path.startsWith(path.replace(/\/$/, '') + '/')
}

// 스와이프 트래킹 (인디케이터용)
let touchStartX = 0
function onTouchStart(e) { touchStartX = e.touches[0].clientX }
function onTouchEnd() {
  if (!scrollEl.value) return
  const scrollLeft = scrollEl.value.scrollLeft
  const pageWidth = scrollEl.value.clientWidth
  currentPage.value = Math.round(scrollLeft / pageWidth)
}
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
.h-13 { height: 52px; }
</style>
