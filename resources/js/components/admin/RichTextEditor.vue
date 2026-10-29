<template>
<div class="rte border border-gray-200 rounded-xl bg-white overflow-hidden">
  <div class="flex flex-wrap items-center gap-1 px-2 py-1.5 border-b border-gray-100 bg-gray-50">
    <button v-for="b in basics" :key="b.cmd" type="button" class="rte-btn" :class="b.cls" :title="b.title" @mousedown.prevent="exec(b.cmd, b.arg)" v-html="b.label"></button>
    <span class="rte-sep"></span>
    <button type="button" class="rte-btn font-bold" title="큰 제목" @mousedown.prevent="exec('formatBlock', 'h2')">제목</button>
    <button type="button" class="rte-btn font-bold text-[12px]" title="작은 제목" @mousedown.prevent="exec('formatBlock', 'h3')">소제목</button>
    <button type="button" class="rte-btn" title="일반 글" @mousedown.prevent="exec('formatBlock', 'p')">본문</button>
    <button type="button" class="rte-btn" title="인용(강조 박스)" @mousedown.prevent="exec('formatBlock', 'blockquote')">❝</button>
    <span class="rte-sep"></span>
    <button v-for="c in colors" :key="c.v" type="button" class="rte-color" :style="{ background: c.v }" :title="c.t" @mousedown.prevent="exec('foreColor', c.v)"></button>
    <span class="rte-sep"></span>
    <button type="button" class="rte-btn" title="글머리표 목록" @mousedown.prevent="exec('insertUnorderedList')">• 목록</button>
    <button type="button" class="rte-btn" title="번호 목록" @mousedown.prevent="exec('insertOrderedList')">1. 목록</button>
    <button type="button" class="rte-btn" title="왼쪽 정렬" @mousedown.prevent="exec('justifyLeft')">⇤</button>
    <button type="button" class="rte-btn" title="가운데 정렬" @mousedown.prevent="exec('justifyCenter')">↔</button>
    <span class="rte-sep"></span>
    <button type="button" class="rte-btn" title="링크 넣기" @mousedown.prevent="addLink">🔗 링크</button>
    <button type="button" class="rte-btn" title="서식 지우기" @mousedown.prevent="exec('removeFormat')">지우기</button>
  </div>
  <div ref="el" class="rte-area" contenteditable="true" :data-placeholder="placeholder" @input="onInput" @blur="onInput" @paste="onPaste"></div>
</div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'

const props = defineProps({ modelValue: { type: String, default: '' }, placeholder: { type: String, default: '여기에 글을 쓰세요' } })
const emit = defineEmits(['update:modelValue'])
const el = ref(null)
let last = null   // 내가 마지막으로 내보낸 값 — 같은 값이 되돌아오면 다시 채우지 않아 커서가 튀지 않는다

const basics = [
  { cmd: 'bold', label: '<b>B</b>', title: '굵게', cls: '' },
  { cmd: 'italic', label: '<i>I</i>', title: '기울임', cls: '' },
  { cmd: 'underline', label: '<u>U</u>', title: '밑줄', cls: '' },
]
const colors = [
  { v: '#111111', t: '검정' }, { v: '#FC226B', t: '핑크(브랜드)' }, { v: '#666666', t: '회색' },
  { v: '#2563eb', t: '파랑' }, { v: '#16a34a', t: '초록' }, { v: '#d97706', t: '주황' },
]

function exec(cmd, arg = null) {
  el.value.focus()
  try { document.execCommand('styleWithCSS', false, true) } catch {}
  document.execCommand(cmd, false, arg)
  onInput()
}
function addLink() {
  const url = window.prompt('연결할 주소(https:// 로 시작)를 입력하세요')
  if (!url) return
  if (!/^(https?:\/\/|mailto:)/i.test(url)) { window.alert('https:// 또는 http:// 로 시작하는 주소만 넣을 수 있어요.'); return }
  exec('createLink', url)
}
function onInput() {
  const html = el.value.innerHTML === '<br>' ? '' : el.value.innerHTML
  last = html
  emit('update:modelValue', html)
}
// 붙여 넣을 때는 서식 없이 글자만(워드·웹에서 온 지저분한 서식 방지)
function onPaste(e) {
  e.preventDefault()
  const text = (e.clipboardData || window.clipboardData).getData('text/plain')
  document.execCommand('insertText', false, text)
}

onMounted(() => { el.value.innerHTML = props.modelValue || ''; last = props.modelValue || '' })
watch(() => props.modelValue, (v) => {
  if ((v || '') !== last && el.value) { el.value.innerHTML = v || ''; last = v || '' }
})
</script>

<style scoped>
.rte-btn { min-width: 30px; height: 30px; padding: 0 8px; border-radius: 8px; font-size: 13px; color: #374151; background: #fff; border: 1px solid #e5e7eb; }
.rte-btn:hover { background: #fff7e6; border-color: #fcd9a0; }
.rte-sep { width: 1px; height: 20px; background: #e5e7eb; margin: 0 4px; }
.rte-color { width: 20px; height: 20px; border-radius: 999px; border: 2px solid #fff; box-shadow: 0 0 0 1px #d1d5db; }
.rte-area { min-height: 220px; max-height: 420px; overflow-y: auto; padding: 14px 16px; font-size: 15px; line-height: 1.8; color: #3a3a3a; outline: none; }
.rte-area:empty::before { content: attr(data-placeholder); color: #9ca3af; }
.rte-area :deep(h2) { font-size: 21px; font-weight: 800; margin: 14px 0 8px; color: #111; }
.rte-area :deep(h3) { font-size: 17px; font-weight: 800; margin: 12px 0 6px; color: #111; }
.rte-area :deep(p) { margin: 0 0 12px; }
.rte-area :deep(ul) { list-style: disc; padding-left: 22px; margin: 0 0 12px; }
.rte-area :deep(ol) { list-style: decimal; padding-left: 22px; margin: 0 0 12px; }
.rte-area :deep(a) { color: #FC226B; font-weight: 700; text-decoration: underline; }
.rte-area :deep(blockquote) { border-left: 4px solid #FC226B; background: #fff5f8; padding: 8px 14px; margin: 0 0 12px; color: #555; }
</style>
