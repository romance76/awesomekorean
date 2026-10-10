<template>
<!--
  공용 메시지 입력창 — 채팅방·1:1 채팅·쪽지·채팅 팝업이 같은 모양을 쓴다.
  둥근 큰 상자: 위에는 글(쓸수록 위로 늘어남), 아래 왼쪽은 부가 버튼(이모티콘·첨부 등, left 슬롯), 아래 오른쪽은 보내기.
  Enter 전송 / Shift+Enter 줄바꿈 / 한글 조합 중 Enter 도 한 번에 전송(useEnterSend).
-->
<div class="rounded-3xl border transition-colors px-3.5 pt-2.5 pb-2"
  :class="[dark ? 'bg-gray-700 border-gray-600 focus-within:border-green-500' : 'bg-white border-gray-200 focus-within:border-amber-300 shadow-sm',
           disabled ? 'opacity-60' : '']">
  <textarea ref="el" :value="modelValue" rows="1" :maxlength="maxlength" :placeholder="placeholder" :disabled="disabled"
    name="chat-message" enterkeyhint="send" autocomplete="off" autocapitalize="sentences"
    class="block w-full resize-none bg-transparent border-0 outline-none p-0 leading-6 text-[16px] disabled:cursor-not-allowed"
    :class="dark ? 'text-white placeholder-gray-400' : 'text-ink placeholder:text-ink-faint'"
    :style="{ maxHeight: maxHeight + 'px' }"
    @input="onInput" @keydown="enter.onKeydown" @compositionend="enter.onCompositionend"></textarea>
  <div class="flex items-center justify-between mt-1.5">
    <div class="flex items-center gap-1 min-w-0"><slot name="left" /></div>
    <button type="button" @mousedown.prevent @click="doSend" :disabled="!ready"
      class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 transition-colors disabled:cursor-not-allowed"
      :class="dark ? 'bg-green-600 text-white hover:bg-green-500 disabled:bg-gray-600' : 'bg-amber-500 text-white hover:bg-amber-600 disabled:opacity-35'"
      :title="sending ? '전송 중...' : '전송'" aria-label="전송">
      <span v-if="sending" class="text-xs">...</span>
      <AppIcon v-else name="send" :size="17" />
    </button>
  </div>
</div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted } from 'vue'
import AppIcon from './AppIcon.vue'
import { useEnterSend } from '../composables/useEnterSend'

const props = defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: '메시지 입력...' },
  disabled: { type: Boolean, default: false },
  maxlength: { type: Number, default: 2000 },
  maxHeight: { type: Number, default: 160 },     // 글이 길어질 때 입력창이 늘어나는 최대 높이(px)
  dark: { type: Boolean, default: false },
  sending: { type: Boolean, default: false },
  canSend: { type: Boolean, default: null },      // 첨부 파일만 있어도 보낼 수 있을 때 true 로 넘김
})
const emit = defineEmits(['update:modelValue', 'send'])

const el = ref(null)
const ready = computed(() => !props.disabled && !props.sending && (props.canSend !== null ? props.canSend : !!props.modelValue.trim()))

function resize() {
  const t = el.value
  if (!t) return
  t.style.height = 'auto'
  t.style.height = Math.min(t.scrollHeight, props.maxHeight) + 'px'
  t.style.overflowY = t.scrollHeight > props.maxHeight ? 'auto' : 'hidden'
}
function onInput(e) { emit('update:modelValue', e.target.value); resize() }
function doSend() { if (ready.value) emit('send') }

const enter = useEnterSend(() => doSend())

watch(() => props.modelValue, () => nextTick(resize))
onMounted(resize)

defineExpose({
  focus: (opts) => el.value?.focus?.(opts),
  el,
})
</script>
