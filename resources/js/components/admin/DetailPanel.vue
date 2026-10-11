<!-- 관리자 공용 상세 보기 틀 (할 일 #83) — PC 는 우측에서 밀려나오는 패널, 휴대폰은 하단 시트.
     표·카드 목록에서 "줄을 누르면 상세가 열리는" 모든 화면이 이 틀 하나를 같이 쓰도록 만든 것.
     사용법:
       <DetailPanel v-model="open" title="신고 #123">
         ...내용...
         <template #footer>버튼들</template>
       </DetailPanel> -->
<template>
<Teleport to="body">
  <Transition :name="isMobile ? 'dp-sheet' : 'dp-panel'">
    <div v-if="modelValue" class="fixed inset-0 z-[90] flex" :class="isMobile ? 'items-end' : 'items-stretch justify-end'" @click.self="close">
      <div class="absolute inset-0 bg-black/30"></div>
      <div class="relative bg-white flex flex-col shadow-2xl"
        :class="isMobile ? 'w-full max-h-[88vh] rounded-t-2xl' : 'w-full max-w-md h-screen'"
        :style="isMobile ? { paddingBottom: 'env(safe-area-inset-bottom, 0px)' } : {}">
        <div v-if="isMobile" class="flex justify-center pt-2 pb-1"><span class="w-10 h-1 rounded-full bg-gray-200"></span></div>
        <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-2 shrink-0">
          <div class="flex-1 min-w-0 text-[15px] font-bold text-ink truncate">{{ title }}</div>
          <button @click="close" class="w-9 h-9 grid place-items-center rounded-full text-ink-muted hover:bg-gray-100 active:bg-gray-100" aria-label="닫기"><AppIcon name="x" :size="18" /></button>
        </div>
        <div class="flex-1 overflow-y-auto px-4 py-4 text-[14px] leading-relaxed">
          <slot />
        </div>
        <div v-if="$slots.footer" class="px-4 py-3 border-t border-gray-100 shrink-0" :style="isMobile ? { paddingBottom: 'env(safe-area-inset-bottom, 8px)' } : {}">
          <slot name="footer" />
        </div>
      </div>
    </div>
  </Transition>
</Teleport>
</template>
<script setup>
import { inject } from 'vue'
import AppIcon from '../AppIcon.vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue'])
// AdminLayout.vue 가 provide 하는 '지금 휴대폰 레이아웃인지' 값 — 없으면(관리자 바깥) PC 패널로
const isMobile = inject('adminIsMobile', { value: false })
function close() { emit('update:modelValue', false) }
</script>
<style scoped>
.dp-panel-enter-active, .dp-panel-leave-active { transition: opacity .18s; }
.dp-panel-enter-active .relative, .dp-panel-leave-active .relative { transition: transform .22s cubic-bezier(.2,.7,.3,1); }
.dp-panel-enter-from, .dp-panel-leave-to { opacity: 0; }
.dp-panel-enter-from .relative, .dp-panel-leave-to .relative { transform: translateX(100%); }
.dp-sheet-enter-active, .dp-sheet-leave-active { transition: opacity .18s; }
.dp-sheet-enter-active .relative, .dp-sheet-leave-active .relative { transition: transform .22s cubic-bezier(.2,.7,.3,1); }
.dp-sheet-enter-from, .dp-sheet-leave-to { opacity: 0; }
.dp-sheet-enter-from .relative, .dp-sheet-leave-to .relative { transform: translateY(100%); }
</style>
