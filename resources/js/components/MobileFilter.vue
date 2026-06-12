<template>
<Teleport to="body">
  <!-- 오버레이 -->
  <Transition name="fade">
    <div v-if="modelValue" class="fixed inset-0 bg-black/40 z-[100]" @click="$emit('update:modelValue', false)"></div>
  </Transition>
  <!-- 패널 (위에서 아래로) -->
  <Transition name="slide-down">
    <div v-if="modelValue" class="fixed top-0 left-0 right-0 z-[101] bg-white rounded-b-2xl shadow-2xl max-h-[85vh] overflow-y-auto safe-top">
      <div class="flex justify-center pb-1 pt-3"><div class="w-10 h-1 bg-gray-300 rounded-full"></div></div>
      <div class="px-5 pb-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-base font-bold text-ink flex items-center gap-2">
            <span class="icon-chip w-7 h-7 bg-amber-50 text-amber-600"><AppIcon name="filter" :size="14" /></span>필터 설정
          </h2>
          <button @click="$emit('update:modelValue', false)" class="text-ink-muted hover:text-ink transition-colors"><AppIcon name="x" :size="20" /></button>
        </div>
        <slot />
        <button @click="$emit('apply'); $emit('update:modelValue', false)"
          class="btn-primary w-full py-3 mt-4">
          적용하기
        </button>
        <button @click="$emit('reset')"
          class="w-full text-ink-muted hover:text-ink text-xs font-semibold py-2 mt-1 transition-colors">
          필터 초기화
        </button>
      </div>
    </div>
  </Transition>
</Teleport>
</template>

<script setup>
import AppIcon from './AppIcon.vue'

defineProps({ modelValue: Boolean })
defineEmits(['update:modelValue', 'apply', 'reset'])
</script>

<style scoped>
.safe-top { padding-top: env(safe-area-inset-top, 0px); }
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.slide-down-enter-active, .slide-down-leave-active { transition: transform 0.3s ease; }
.slide-down-enter-from, .slide-down-leave-to { transform: translateY(-100%); }
</style>
