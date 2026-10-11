<!-- 관리자 공용 저장 바 (할 일 #83) — 화면 하단에 고정, 바뀐 내용이 있을 때만 나타난다.
     PC·휴대폰 공용, 터치 44px 이상. 저장 중엔 버튼이 잠기고 "저장 중…" 으로 바뀐다.
     사용법: <SaveBar :dirty="dirty" :saving="saving" @save="onSave" @cancel="onCancel" /> -->
<template>
<Transition name="sb-slide">
  <div v-if="dirty" class="fixed bottom-0 inset-x-0 z-50 bg-white border-t border-gray-200 shadow-[0_-4px_12px_rgba(0,0,0,0.06)]"
    :style="{ paddingBottom: 'env(safe-area-inset-bottom, 0px)' }">
    <div class="max-w-5xl mx-auto px-4 py-3 flex items-center gap-3">
      <div class="flex-1 min-w-0 flex items-center gap-1.5 text-[13px] font-semibold text-amber-600">
        <AppIcon name="alert-circle" :size="15" />
        <span class="truncate">{{ message || '저장하지 않은 변경 사항이 있어요' }}</span>
      </div>
      <button v-if="!hideCancel" @click="$emit('cancel')" :disabled="saving"
        class="min-h-[44px] px-4 rounded-full bg-white border border-gray-200 text-ink-light font-semibold text-[14px] disabled:opacity-50">취소</button>
      <button @click="$emit('save')" :disabled="saving"
        class="min-h-[44px] px-6 rounded-full text-white font-bold text-[14px] disabled:opacity-60"
        style="background-image: linear-gradient(135deg, #FF8A4D, #FC226B);">
        {{ saving ? '저장 중…' : (saveLabel || '저장') }}
      </button>
    </div>
  </div>
</Transition>
</template>
<script setup>
import AppIcon from '../AppIcon.vue'
defineProps({
  dirty: { type: Boolean, default: false },
  saving: { type: Boolean, default: false },
  message: { type: String, default: '' },
  saveLabel: { type: String, default: '' },
  hideCancel: { type: Boolean, default: false },
})
defineEmits(['save', 'cancel'])
</script>
<style scoped>
.sb-slide-enter-active, .sb-slide-leave-active { transition: transform .18s ease, opacity .18s; }
.sb-slide-enter-from, .sb-slide-leave-to { transform: translateY(100%); opacity: 0; }
</style>
