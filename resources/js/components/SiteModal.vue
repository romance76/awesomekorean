<template>
<Teleport to="body">
  <Transition name="modal">
    <div v-if="visible" class="fixed inset-0 z-[100] flex items-center justify-center" @click.self="type !== 'alert' && onCancel()">
      <div class="absolute inset-0 bg-black/40"></div>
      <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 overflow-hidden animate-modal">
        <!-- 헤더 -->
        <div class="bg-gradient-to-r from-amber-400 to-orange-400 px-5 py-3">
          <div class="text-sm font-black text-amber-900">{{ title || defaultTitle }}</div>
        </div>
        <!-- 본문 -->
        <div class="px-5 py-4">
          <p class="text-sm text-gray-700 whitespace-pre-line leading-relaxed">{{ message }}</p>
          <input v-if="type === 'prompt'" v-model="inputValue" :placeholder="inputPlaceholder"
            class="w-full border-2 border-amber-200 rounded-lg px-3 py-2 text-sm mt-3 focus:border-amber-400 focus:ring-2 focus:ring-amber-200 outline-none"
            @keyup.enter="onOk" ref="promptInput" />
        </div>
        <!-- 버튼 -->
        <div class="px-5 pb-4 flex gap-2 justify-end">
          <button v-if="type !== 'alert'" @click="onCancel"
            class="px-5 py-2 rounded-lg text-sm font-bold text-gray-500 bg-gray-100 hover:bg-gray-200 transition">취소</button>
          <button @click="onOk"
            class="px-5 py-2 rounded-lg text-sm font-bold text-white bg-amber-500 hover:bg-amber-600 transition shadow-sm">확인</button>
        </div>
      </div>
    </div>
  </Transition>
</Teleport>
</template>

<script setup>
import { watch, nextTick, ref as vRef } from 'vue'
import { useModal } from '../composables/useModal'

const { visible, title, message, type, inputValue, inputPlaceholder, onOk, onCancel } = useModal()
const promptInput = vRef(null)

const defaultTitle = {
  alert: '알림',
  confirm: '확인',
  prompt: '입력',
}[type.value] || '알림'

watch(visible, (v) => {
  if (v && type.value === 'prompt') nextTick(() => promptInput.value?.focus())
})
</script>

<style scoped>
.animate-modal { animation: modalIn 0.2s ease-out; }
@keyframes modalIn { from { transform: scale(0.9) translateY(10px); opacity: 0; } to { transform: scale(1) translateY(0); opacity: 1; } }
.modal-enter-active { transition: opacity 0.2s; }
.modal-leave-active { transition: opacity 0.15s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
