<template>
  <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50 md:hidden">
    <div class="flex items-center justify-around h-14">
      <RouterLink v-for="item in items" :key="item.to" :to="item.to"
        class="flex flex-col items-center gap-0.5 text-[10px] font-medium transition"
        :class="isActive(item.to) ? 'text-amber-600' : 'text-gray-400'">
        <span class="text-lg">{{ item.icon }}</span>
        <span>{{ item.label }}</span>
      </RouterLink>
    </div>
  </div>
</template>

<script setup>
import { useRoute } from 'vue-router'
import { useLangStore } from '../stores/lang'
import { computed } from 'vue'

const route = useRoute()
const langStore = useLangStore()

const items = computed(() => {
  const ko = langStore.locale === 'ko'
  return [
    { to: '/', icon: '🏠', label: ko ? '홈' : 'Home' },
    { to: '/community', icon: '💬', label: ko ? '커뮤니티' : 'Community' },
    { to: '/games', icon: '🎮', label: ko ? '게임' : 'Games' },
    { to: '/chat', icon: '💭', label: ko ? '채팅' : 'Chat' },
    { to: '/dashboard', icon: '👤', label: ko ? 'MY' : 'My' },
  ]
})

function isActive(path) {
  if (path === '/') return route.path === '/'
  return route.path.startsWith(path)
}
</script>
