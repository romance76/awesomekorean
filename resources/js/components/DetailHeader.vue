<template>
  <!-- 모바일 상세 페이지 상단 헤더 — 둥근 뒤로가기 버튼 + 제목 (PC 는 lg:hidden 으로 숨김).
       custom-back 을 주면 router.back 대신 @back 이벤트를 낸다(목록 안에서 상세만 닫는 화면용). -->
  <div class="lg:hidden sticky top-14 z-30 bg-white/95 backdrop-blur border-b border-gray-100 flex items-center gap-2.5 px-3 py-2 -mx-4 mb-3">
    <button @click="goBack" aria-label="뒤로가기" title="뒤로가기"
      class="w-9 h-9 rounded-full bg-gray-100 text-ink hover:bg-gray-200 active:bg-gray-200 active:scale-95 transition flex items-center justify-center flex-shrink-0">
      <AppIcon name="chevron-left" :size="21" :stroke-width="2.4" />
    </button>
    <div class="flex-1 min-w-0">
      <div v-if="title" class="text-[15px] font-bold text-ink truncate">{{ title }}</div>
      <div v-else-if="$slots.title" class="text-[15px] font-bold text-ink truncate">
        <slot name="title"></slot>
      </div>
    </div>
    <slot name="actions"></slot>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'
import AppIcon from './AppIcon.vue'

const props = defineProps({
  title: { type: String, default: '' },
  fallback: { type: String, default: '/' },    // history 없을 때 이동할 경로
  customBack: { type: Boolean, default: false }, // true 면 뒤로가기 대신 'back' 이벤트만 낸다
})
const emit = defineEmits(['back'])

const router = useRouter()

function goBack() {
  if (props.customBack) { emit('back'); return }
  // history 가 있으면 브라우저 뒤로, 없으면 fallback
  if (window.history.length > 1) {
    router.back()
  } else {
    router.push(props.fallback)
  }
}
</script>
