<template>
  <Teleport to="body">
    <div v-if="modelValue" class="fixed inset-0 z-[10000] flex flex-col bg-black/60" role="dialog" aria-modal="true" aria-label="이력서 미리보기" @click.self="close">
      <!-- 위 막대 -->
      <div class="flex items-center gap-2 px-3 sm:px-5 py-2.5 bg-white border-b border-gray-100 shrink-0">
        <div class="min-w-0 flex-1">
          <div class="text-sm font-bold text-ink truncate">{{ resume?.title || '이력서' }}</div>
          <div class="text-[11px] text-ink-faint truncate">{{ subtitle }}</div>
        </div>
        <button type="button" @click="printNow" :disabled="loading || !!error" class="btn-primary !px-3 !py-1.5 !text-xs disabled:opacity-50">
          <AppIcon name="printer" :size="14" />인쇄
        </button>
        <button v-if="isFile && blobUrl" type="button" @click="openNewTab" class="btn-secondary !px-3 !py-1.5 !text-xs hidden sm:inline-flex">새 창</button>
        <button type="button" @click="close" class="w-8 h-8 rounded-full hover:bg-gray-100 text-ink-light text-xl leading-none" aria-label="닫기">&times;</button>
      </div>

      <!-- 종이 -->
      <div ref="bodyEl" class="flex-1 overflow-auto px-2 sm:px-6 py-4 bg-gray-200/90" @click.self="close">
        <div v-if="loading" class="text-center text-sm text-ink-muted py-16">불러오는 중…</div>
        <div v-else-if="error" class="text-center text-sm text-red-600 py-16">{{ error }}</div>

        <!-- 직접 쓴 이력서: 레터지 한 장 모양 -->
        <div v-else-if="!isFile" class="mx-auto shadow-xl bg-white" :style="{ width: 8.5 * 96 * scale + 'px' }">
          <div :style="{ zoom: scale }"><ResumePaper ref="paperRef" :data="resume" /></div>
        </div>

        <!-- 올린 이미지 -->
        <div v-else-if="isImage" class="mx-auto shadow-xl bg-white flex items-center justify-center" :style="{ width: 8.5 * 96 * scale + 'px', height: 11 * 96 * scale + 'px' }">
          <img :src="blobUrl" alt="이력서" class="max-w-full max-h-full object-contain" />
        </div>

        <!-- 올린 PDF -->
        <div v-else class="mx-auto shadow-xl bg-white" :style="{ width: 'min(100%, ' + 8.5 * 96 + 'px)', height: 'calc(100vh - 9rem)' }">
          <iframe ref="pdfEl" :src="blobUrl" title="이력서 PDF" class="w-full h-full border-0"></iframe>
        </div>
        <p v-if="isFile && !isImage && blobUrl" class="text-center text-[11px] text-ink-muted mt-2">여러 쪽이 한 장만 보이면 위의 '새 창'으로 열어서 보세요.</p>
      </div>
    </div>
    <component :is="'style'">{{ paperCss }}</component>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, onUnmounted, nextTick } from 'vue'
import axios from 'axios'
import AppIcon from '../AppIcon.vue'
import ResumePaper from './ResumePaper.vue'
import paperCss from './resumePaper.css?raw'
import { fmtBytes } from './resumeOptions'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  resume: { type: Object, default: null },     // 직접 쓴 이력서 내용 또는 { kind:'uploaded', file_mime, file_name, ... }
  fileUrl: { type: String, default: '' },      // 올린 파일을 받아 올 주소 (로그인 확인이 필요해 axios 로 받는다)
})
const emit = defineEmits(['update:modelValue'])

const loading = ref(false)
const error = ref('')
const blobUrl = ref('')
const paperRef = ref(null)
const pdfEl = ref(null)
const bodyEl = ref(null)
const scale = ref(1)

const isFile = computed(() => props.resume?.kind === 'uploaded')
const isImage = computed(() => /^image\//.test(props.resume?.file_mime || ''))
const subtitle = computed(() => isFile.value ? [props.resume?.file_name, fmtBytes(props.resume?.file_size)].filter(Boolean).join(' · ') : '직접 쓴 이력서 · 레터지(8.5 × 11 in)')

function fit() {
  const w = (bodyEl.value?.clientWidth || window.innerWidth) - 16
  scale.value = Math.max(0.3, Math.min(1, w / (8.5 * 96)))
}
function revoke() { if (blobUrl.value) { URL.revokeObjectURL(blobUrl.value); blobUrl.value = '' } }
function close() { emit('update:modelValue', false) }

watch(() => [props.modelValue, props.resume, props.fileUrl], async ([open]) => {
  revoke(); error.value = ''
  if (!open) { window.removeEventListener('resize', fit); return }
  await nextTick(); fit(); window.addEventListener('resize', fit)
  if (isFile.value && props.fileUrl) {
    loading.value = true
    try {
      const { data } = await axios.get(props.fileUrl, { responseType: 'blob' })
      blobUrl.value = URL.createObjectURL(data)
    } catch (e) { error.value = e.response?.status === 404 ? '파일을 찾을 수 없어요.' : '파일을 불러오지 못했어요. 잠시 후 다시 시도해 주세요.' }
    loading.value = false
  }
})
onUnmounted(() => { revoke(); window.removeEventListener('resize', fit) })

function openNewTab() { if (blobUrl.value) window.open(blobUrl.value, '_blank') }

// 인쇄: 화면 그대로가 아니라, 레터지 크기(여백 0.6in)로 따로 만든 숨은 창에서 인쇄한다 (사이트 메뉴 등이 같이 찍히지 않게)
function printHtml(bodyHtml, extraCss = '', margin = '0.6in') {
  const f = document.createElement('iframe')
  f.setAttribute('aria-hidden', 'true')
  f.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;'
  document.body.appendChild(f)
  const doc = f.contentWindow.document
  doc.open()
  doc.write(`<!doctype html><html><head><meta charset="utf-8"><title>${(props.resume?.title || '이력서').replace(/</g, '')}</title><style>
    @page { size: letter; margin: ${margin}; }
    html, body { margin: 0; padding: 0; background: #fff; }
    ${paperCss}
    .rp-page { width: auto; min-height: 0; padding: 0; }
    ${extraCss}
  </style></head><body>${bodyHtml}</body></html>`)
  doc.close()
  const go = () => { try { f.contentWindow.focus(); f.contentWindow.print() } catch { window.print() } setTimeout(() => f.remove(), 60000) }
  if (f.contentWindow.document.readyState === 'complete') setTimeout(go, 150); else f.onload = () => setTimeout(go, 150)
}

function printNow() {
  if (!isFile.value) {
    const el = paperRef.value?.pageEl
    if (el) printHtml(el.outerHTML)
    return
  }
  if (isImage.value) {
    printHtml(`<img src="${blobUrl.value}" style="display:block;max-width:100%;max-height:9.8in;margin:0 auto;object-fit:contain">`, '', '0.5in')
    return
  }
  try { pdfEl.value.contentWindow.focus(); pdfEl.value.contentWindow.print() } catch { openNewTab() }   // 막히면 새 창에서 인쇄
}
</script>
