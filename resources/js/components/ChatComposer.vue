<template>
<!--
  공용 메시지 입력창 — 채팅방·1:1 채팅·쪽지·채팅 팝업이 같은 모양을 쓴다.
  둥근 큰 상자: 위에는 글(쓸수록 위로 늘어남), 아래 왼쪽은 부가 버튼(이모티콘·첨부 등, left 슬롯), 아래 오른쪽은 보내기.
  Enter 전송 / Shift+Enter 줄바꿈 / 한글 조합 중 Enter 도 한 번에 전송(useEnterSend).

  입력칸은 <textarea> 대신 contenteditable 을 쓴다: 아이폰은 textarea/input 에 키보드 위 "∧ ∨ ✓ 자동 완성" 막대를
  붙이는데, contenteditable 에서는 그 막대가 붙지 않아 입력창이 키보드에 더 가깝게 붙는다.
-->
<div class="rounded-3xl border transition-colors px-3.5 pt-2.5 pb-2"
  :class="[dark ? 'bg-gray-700 border-gray-600 focus-within:border-green-500' : 'bg-white border-gray-200 focus-within:border-amber-300 shadow-sm',
           disabled ? 'opacity-60' : '']">
  <div ref="el" role="textbox" aria-multiline="true" :aria-label="placeholder" :aria-disabled="disabled"
    :contenteditable="disabled ? 'false' : editMode" :data-placeholder="placeholder" :data-dark="dark ? '1' : '0'"
    enterkeyhint="send" autocapitalize="sentences" spellcheck="false"
    class="chat-ce block w-full outline-none leading-6 text-[16px] overflow-y-auto"
    :class="dark ? 'text-white' : 'text-ink'"
    :style="{ maxHeight: maxHeight + 'px', minHeight: '24px' }"
    @input="onInput" @keydown="enter.onKeydown" @compositionend="onCompEnd" @paste="onPaste"></div>
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
import { ref, computed, watch, onMounted } from 'vue'
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
// plaintext-only 를 지원하지 않는 브라우저는 true (붙여넣기는 아래 onPaste 에서 항상 글자만 넣는다)
const editMode = typeof CSS !== 'undefined' && typeof document !== 'undefined'
  && (() => { try { const d = document.createElement('div'); d.contentEditable = 'plaintext-only'; return d.contentEditable === 'plaintext-only' } catch { return false } })()
  ? 'plaintext-only' : 'true'

const ready = computed(() => !props.disabled && !props.sending && (props.canSend !== null ? props.canSend : !!props.modelValue.trim()))

function readText() {
  const t = el.value
  if (!t) return ''
  // innerText 는 줄바꿈을 \n 으로 돌려준다. 비었을 때 브라우저가 남기는 <br> 로 생기는 개행은 제거
  return (t.innerText || '').replace(/ /g, ' ').replace(/\n$/, '')
}
function setText(v) {
  const t = el.value
  if (!t) return
  t.textContent = v
  if (document.activeElement === t) placeCaretAtEnd(t)
}
function placeCaretAtEnd(t) {
  try {
    const r = document.createRange(); r.selectNodeContents(t); r.collapse(false)
    const s = window.getSelection(); s.removeAllRanges(); s.addRange(r)
  } catch {}
}

function onInput() {
  const t = el.value
  let v = readText()
  if (v.length > props.maxlength) {
    v = v.slice(0, props.maxlength)
    setText(v)
  }
  if (!v.trim() && t && t.innerHTML !== '') t.innerHTML = ''   // 비우면 placeholder 가 다시 보이도록
  emit('update:modelValue', v)
}
function onPaste(e) {
  // 서식 없이 글자만 붙여넣기
  e.preventDefault()
  const text = (e.clipboardData || window.clipboardData)?.getData('text') || ''
  if (!text) return
  const room = props.maxlength - readText().length
  if (room <= 0) return
  document.execCommand('insertText', false, text.slice(0, room))
}
function doSend() { if (ready.value) emit('send') }

const enter = useEnterSend(() => doSend())
// 한글 조합이 끝난 직후 최종 글자를 모델에 반영한 뒤 (필요하면) 전송
function onCompEnd() { onInput(); enter.onCompositionend() }

// 부모가 값을 바꿨을 때(전송 후 비우기 등) 입력칸에 반영
watch(() => props.modelValue, (v) => {
  if (v !== readText()) setText(v || '')
})
onMounted(() => { if (props.modelValue) setText(props.modelValue) })

defineExpose({
  focus: (opts) => el.value?.focus?.(opts),
  el,
})
</script>

<style scoped>
.chat-ce { white-space: pre-wrap; overflow-wrap: anywhere; -webkit-user-select: text; user-select: text; }
.chat-ce:empty::before { content: attr(data-placeholder); color: #a8a29e; pointer-events: none; }
.chat-ce[data-dark="1"]:empty::before { color: #9ca3af; }
</style>
