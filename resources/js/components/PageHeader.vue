<template>
  <!-- 목록이 아닌 모든 페이지(글쓰기·신청·가이드·설정…)의 공통 머리글: [뒤로가기] [아이콘] 제목 … [오른쪽 버튼] -->
  <div class="flex items-center gap-3 mb-4">
    <button v-if="back" type="button" @click="goBack" aria-label="뒤로가기"
      class="w-9 h-9 flex-shrink-0 flex items-center justify-center rounded-full bg-white shadow-card text-ink-muted hover:bg-gray-50 transition-colors">
      <AppIcon name="chevron-left" :size="20" />
    </button>
    <h1 class="flex items-center gap-2.5 min-w-0 text-xl font-bold text-ink">
      <span v-if="icon" class="icon-chip w-9 h-9 flex-shrink-0" :class="chip"><AppIcon :name="icon" :size="20" /></span>
      <span class="truncate">{{ title }}</span>
      <span v-if="subtitle" class="hidden sm:inline text-sm font-normal text-ink-faint truncate">— {{ subtitle }}</span>
    </h1>
    <div v-if="$slots.actions" class="ml-auto flex items-center gap-2 flex-shrink-0"><slot name="actions" /></div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'
import AppIcon from './AppIcon.vue'

const props = defineProps({
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  icon: { type: String, default: '' },
  chip: { type: String, default: 'bg-amber-50 text-amber-600' },   // 아이콘 칩 색 (배경 + 글자색)
  back: { type: Boolean, default: true },                           // 최상위 메뉴 페이지는 false
  to: { type: String, default: '' },                                // 지정하면 항상 이 경로로 이동
  fallback: { type: String, default: '/' },                         // 이전 기록이 없을 때 이동할 경로
})

const router = useRouter()

function goBack() {
  if (props.to) router.push(props.to)
  else if (window.history.length > 1) router.back()
  else router.push(props.fallback)
}
</script>
