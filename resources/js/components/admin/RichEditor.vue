<template>
  <!-- Quill 기반 WYSIWYG 에디터 (Phase 2-C Post) -->
  <div class="border rounded-lg overflow-hidden">
    <div ref="editor" :style="{ minHeight: height + 'px' }"></div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, onBeforeUnmount } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  height: { type: Number, default: 200 },
  placeholder: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue'])

const editor = ref(null)
let quill = null
let updating = false

onMounted(async () => {
  const Quill = (await import('quill')).default
  await import('quill/dist/quill.snow.css')

  quill = new Quill(editor.value, {
    theme: 'snow',
    placeholder: props.placeholder,
    modules: {
      toolbar: [
        [{ header: [1, 2, 3, false] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ color: [] }, { background: [] }],
        [{ list: 'ordered' }, { list: 'bullet' }],
        [{ align: [] }],
        ['blockquote', 'code-block'],
        ['link', 'image'],
        ['clean'],
      ],
    },
  })

  // 초기값
  if (props.modelValue) {
    quill.root.innerHTML = props.modelValue
  }

  quill.on('text-change', () => {
    if (updating) return
    const html = quill.root.innerHTML
    emit('update:modelValue', html === '<p><br></p>' ? '' : html)
  })
})

watch(() => props.modelValue, (val) => {
  if (!quill) return
  if (val === quill.root.innerHTML) return
  updating = true
  quill.root.innerHTML = val || ''
  updating = false
})

onBeforeUnmount(() => {
  if (quill) { quill = null }
})
</script>

<style>
/* Quill snow 테마 오버라이드: 최소한 조정만 */
.ql-toolbar { border-top-left-radius: 8px; border-top-right-radius: 8px; }
.ql-container { border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; font-family: inherit; font-size: 14px; }
</style>
