<template>
  <button @click.stop.prevent="$emit('toggle')"
    :class="ribbon ? 'absolute right-3 z-10 transition hover:scale-110' : 'transition hover:scale-110'"
    :style="ribbon ? 'top: -4px;' : ''"
    :title="active ? '북마크 해제' : '북마크'">
    <svg viewBox="0 0 24 24" :class="[sizeClass, active ? 'fill-red-500 text-red-500 drop-shadow' : 'fill-none text-gray-300 hover:text-red-400']"
      stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M19 21l-7-4-7 4V5a2 2 0 012-2h10a2 2 0 012 2z"/>
    </svg>
  </button>
</template>
<script setup>
import { computed } from 'vue'
const props = defineProps({
  active: { type: Boolean, default: false },
  size: { type: String, default: 'md' }, // sm, md, lg
  ribbon: { type: Boolean, default: false }, // 카드 보더 상단에 걸치는 리본 스타일
})
defineEmits(['toggle'])
const sizeClass = computed(() => {
  if (props.ribbon) return 'w-6 h-6'
  return ({ sm: 'w-4 h-4', md: 'w-5 h-5', lg: 'w-6 h-6' }[props.size] || 'w-5 h-5')
})
</script>
<script>
export default { name: 'BookmarkToggle' }
</script>
