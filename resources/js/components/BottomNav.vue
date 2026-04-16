<template>
  <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50 md:hidden"
       style="padding-bottom: var(--sab)">
    <!-- 스와이프 가능한 탭 영역 -->
    <div ref="scrollEl" class="flex items-center h-13 overflow-x-auto scrollbar-hide scroll-smooth"
         @touchstart="onTouchStart" @touchend="onTouchEnd">
      <RouterLink v-for="item in favMenus" :key="item.path" :to="item.path"
        class="flex flex-col items-center justify-center gap-0.5 flex-shrink-0 transition-colors"
        :style="tabStyle"
        :class="isActive(item.path) ? 'text-amber-600' : 'text-gray-400'">
        <span class="text-lg leading-none">{{ item.icon }}</span>
        <span class="text-[10px] font-medium leading-none">{{ item.label }}</span>
      </RouterLink>
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
import { useRoute } from 'vue-router'
import { useNavFavoritesStore } from '../stores/navFavorites'

const route = useRoute()
const navStore = useNavFavoritesStore()
const scrollEl = ref(null)
const currentPage = ref(0)

const favMenus = computed(() => navStore.favoriteMenus)

// 탭 너비: 5개 이하면 균등 분배, 6개 이상이면 고정 75px
const tabStyle = computed(() => {
  const count = favMenus.value.length
  if (count <= 5) {
    return { width: (100 / count) + '%', minWidth: '60px' }
  }
  return { width: '75px', minWidth: '75px' }
})

function isActive(path) {
  if (path === '/') return route.path === '/'
  return route.path.startsWith(path)
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
