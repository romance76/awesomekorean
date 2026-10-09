<template>
<div>
  <div class="relative rounded-xl overflow-hidden border-2 border-dashed transition-colors"
    :class="[error ? 'border-red-300 bg-red-50' : 'border-gray-200 bg-[#F4F6F8]', disabled ? 'opacity-60' : '']"
    :style="{ aspectRatio: aspect, maxWidth: maxWidth }">
    <img v-if="preview" :src="preview" class="absolute inset-0 w-full h-full object-cover" alt="" />
    <label v-if="!preview" class="absolute inset-0 flex flex-col items-center justify-center text-ink-muted text-xs gap-1" :class="disabled ? '' : 'cursor-pointer'">
      <AppIcon name="camera" :size="compact ? 18 : 26" :stroke-width="1.5" />
      <span v-if="!compact" class="font-bold">{{ uploading ? '업로드 중...' : '이미지 선택' }}</span>
      <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" :disabled="disabled || uploading" @change="onFile" />
    </label>
    <div v-if="uploading" class="absolute inset-0 bg-white/70 grid place-items-center text-xs font-bold text-ink-muted">업로드 중...</div>
  </div>
  <div class="mt-1.5 flex items-center gap-2 flex-wrap">
    <label v-if="!disabled" class="btn-soft text-xs cursor-pointer">
      <AppIcon name="camera" :size="13" /> {{ preview ? '변경' : '업로드' }}
      <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" :disabled="uploading" @change="onFile" />
    </label>
    <button v-if="preview && !disabled && removable" type="button" class="text-xs text-red-500 font-bold px-2 py-1" @click="$emit('remove')">제거</button>
  </div>
  <p v-if="warn && !compact" class="text-[11px] text-amber-700 mt-1">{{ warn }}</p>
  <!-- 좁은 칸(등수별 상품)에서는 긴 문장 대신 짧은 표시만 (자세한 내용은 마우스를 올리면) -->
  <p v-else-if="warn" class="text-[10px] text-amber-700 mt-0.5 leading-tight" :title="warn">⚠ 비율·용량 확인</p>
  <p v-if="localError || error" class="text-xs text-red-500 mt-1">{{ localError || error }}</p>
</div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'
import AppIcon from '../AppIcon.vue'

// mode='pick'   : 파일을 고르면 'pick'(file) 이벤트만 보냄 (제출 시 FormData 로 전송)
// mode='upload' : 관리자 업로드 API 로 올리고 'uploaded'(url) 이벤트로 URL 전달
const props = defineProps({
  preview: { type: String, default: '' },
  aspect: { type: String, default: '1 / 1' },
  maxWidth: { type: String, default: '100%' },
  mode: { type: String, default: 'pick' },
  kind: { type: String, default: 'prize' },
  recommendRatio: { type: Number, default: 1 },   // 가로/세로
  maxMB: { type: Number, default: 5 },            // 강제 한도
  softMB: { type: Number, default: 1 },           // 권장 한도(경고)
  compact: { type: Boolean, default: false },
  removable: { type: Boolean, default: true },
  disabled: { type: Boolean, default: false },
  error: { type: String, default: '' },
})
const emit = defineEmits(['pick', 'uploaded', 'remove'])

const uploading = ref(false)
const localError = ref('')
const warn = ref('')

function readDims(file) {
  return new Promise(res => {
    const url = URL.createObjectURL(file)
    const img = new Image()
    img.onload = () => { res({ w: img.naturalWidth, h: img.naturalHeight }); URL.revokeObjectURL(url) }
    img.onerror = () => { res(null); URL.revokeObjectURL(url) }
    img.src = url
  })
}

async function onFile(e) {
  const file = e.target.files?.[0]
  e.target.value = ''
  if (!file) return
  localError.value = ''; warn.value = ''
  if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { localError.value = 'JPG, PNG, WebP 이미지만 올릴 수 있어요.'; return }
  if (file.size > props.maxMB * 1024 * 1024) { localError.value = `파일이 너무 커요. ${props.maxMB}MB 이하로 줄여 주세요.`; return }
  const msgs = []
  if (file.size > props.softMB * 1024 * 1024) msgs.push(`권장 용량(${props.softMB}MB)을 넘었어요. 로딩이 느릴 수 있어요.`)
  const d = await readDims(file)
  if (d && d.w && d.h) {
    const r = d.w / d.h
    // 정사각 상품 칸(compact)은 object-cover 로 가운데를 보여주므로 비율 허용 폭을 넓게
    if (Math.abs(r - props.recommendRatio) / props.recommendRatio > (props.compact ? 0.6 : 0.2)) msgs.push(`이미지 비율이 권장과 많이 달라요(현재 ${d.w}×${d.h}). 일부가 잘려 보일 수 있어요.`)
  }
  warn.value = msgs.join(' ')
  if (props.mode === 'pick') { emit('pick', file); return }
  uploading.value = true
  try {
    const fd = new FormData()
    fd.append('image', file)
    fd.append('kind', props.kind)
    const { data } = await axios.post('/api/admin/sweepstakes/upload-image', fd)
    if (data?.url) emit('uploaded', data.url)
    else localError.value = '업로드 결과를 받지 못했어요'
  } catch (err) {
    localError.value = err.response?.data?.message || '이미지 업로드에 실패했어요'
  } finally { uploading.value = false }
}
</script>
