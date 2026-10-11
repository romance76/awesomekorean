<!-- 관리자 공용 확인 시트 (할 일 #83) — PC·휴대폰 어디서나 터치 44px 이상.
     위험도(kind)에 따라 머리글 색이 달라진다: normal(회색/기본) · money(초록, 포인트·환불 등) · danger(빨강, 삭제·차단해제 등).
     AdminLayout.vue 에 한 번만 심어 두면 useAdminConfirm().confirmAction() 으로 어디서든 쓸 수 있다. -->
<template>
<Teleport to="body">
  <Transition name="ac-fade">
    <div v-if="visible" class="fixed inset-0 z-[110] flex items-end sm:items-center justify-center" @click.self="onCancel">
      <div class="absolute inset-0 bg-black/40"></div>
      <div class="relative bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl w-full sm:max-w-sm overflow-hidden"
        :style="{ paddingBottom: 'env(safe-area-inset-bottom, 0px)' }">
        <div class="px-5 py-3.5 flex items-center gap-2" :class="headerClass">
          <AppIcon :name="icon" :size="18" />
          <div class="text-[15px] font-bold">{{ title || defaultTitle }}</div>
        </div>
        <div class="px-5 py-4">
          <p class="text-[14px] text-ink-light whitespace-pre-line leading-relaxed">{{ message }}</p>
        </div>
        <div class="px-5 pb-5 flex gap-2 justify-end">
          <button @click="onCancel" class="min-h-[44px] px-5 rounded-full bg-white border border-gray-200 text-ink-light font-semibold text-[14px]">{{ cancelLabel }}</button>
          <button @click="onOk" class="min-h-[44px] px-5 rounded-full text-white font-bold text-[14px]" :class="okClass">{{ confirmLabel }}</button>
        </div>
      </div>
    </div>
  </Transition>
</Teleport>
</template>
<script setup>
import { computed } from 'vue'
import AppIcon from '../AppIcon.vue'
import { useAdminConfirm } from '../../composables/useAdminConfirm'

const { visible, title, message, kind, confirmLabel, cancelLabel, onOk, onCancel } = useAdminConfirm()

const defaultTitle = computed(() => ({ normal: '확인', money: '확인', danger: '정말 하시겠어요?' }[kind.value] || '확인'))
const headerClass = computed(() => ({
  normal: 'bg-gray-50 text-ink',
  money: 'bg-emerald-50 text-emerald-700',
  danger: 'bg-red-50 text-red-700',
}[kind.value] || 'bg-gray-50 text-ink'))
const icon = computed(() => ({ normal: 'help-circle', money: 'wallet', danger: 'alert-circle' }[kind.value] || 'help-circle'))
const okClass = computed(() => ({
  normal: 'bg-amber-500',
  money: 'bg-emerald-500',
  danger: 'bg-red-500',
}[kind.value] || 'bg-amber-500'))
</script>
<style scoped>
.ac-fade-enter-active, .ac-fade-leave-active { transition: opacity .15s; }
.ac-fade-enter-from, .ac-fade-leave-to { opacity: 0; }
</style>
