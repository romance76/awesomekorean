<template>
  <!-- 모바일 상세 페이지 상단 헤더 — 뒤로가기 ← + 타이틀 통일 (PC 는 lg:hidden 으로 숨김) -->
  <div class="lg:hidden sticky top-14 z-30 bg-white border-b border-gray-100 flex items-center gap-2 px-3 py-2 -mx-4 mb-3">
    <button @click="goBack" class="text-gray-700 text-xl font-bold px-2 py-1 hover:text-amber-600" aria-label="뒤로가기">←</button>
    <div class="flex-1 min-w-0">
      <div v-if="title" class="text-sm font-bold text-gray-800 truncate">{{ title }}</div>
      <div v-else-if="$slots.title" class="text-sm font-bold text-gray-800 truncate">
        <slot name="title"></slot>
      </div>
    </div>
    <slot name="actions"></slot>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'

const props = defineProps({
  title: { type: String, default: '' },
  fallback: { type: String, default: '/' },    // history 없을 때 이동할 경로
})

const router = useRouter()

function goBack() {
  // history 가 있으면 브라우저 뒤로, 없으면 fallback
  if (window.history.length > 1) {
    router.back()
  } else {
    router.push(props.fallback)
  }
}
</script>
