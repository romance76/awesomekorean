<template>
<Teleport to="body">
  <div class="fixed inset-0 z-[1100] bg-black/60 flex items-center justify-center p-4" @click.self="$emit('cancel')">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[340px] p-4">
      <h3 class="font-bold text-ink text-base mb-0.5">프로필 사진 맞추기</h3>
      <p class="text-xs text-ink-muted mb-3">사진을 끌어서 위치를 옮기고, 두 손가락이나 아래 막대로 크기를 맞춰 주세요.</p>

      <!-- 편집 영역: 원 안에 들어가는 부분만 프로필 사진이 돼요 -->
      <div ref="box" class="relative mx-auto select-none overflow-hidden rounded-2xl bg-gray-900"
        :style="{ width: V + 'px', height: V + 'px', touchAction: 'none', cursor: 'grab' }"
        @pointerdown="onDown" @pointermove="onMove" @pointerup="onUp" @pointercancel="onUp" @wheel.prevent="onWheel">
        <img v-if="url" :src="url" alt="" draggable="false" @load="onLoad"
          class="absolute max-w-none pointer-events-none"
          :style="imgStyle" />
        <!-- 원형 가이드: 바깥을 어둡게 -->
        <div class="absolute inset-0 pointer-events-none"
          :style="{ background: 'radial-gradient(circle at center, transparent ' + (V * 0.5 - 1) + 'px, rgba(0,0,0,.55) ' + (V * 0.5) + 'px)' }"></div>
        <div class="absolute rounded-full border-2 border-white/80 pointer-events-none"
          :style="{ left: 0, top: 0, width: V + 'px', height: V + 'px' }"></div>
      </div>

      <div class="flex items-center gap-2 mt-3 px-1">
        <AppIcon name="image" :size="14" class="text-ink-faint" />
        <input type="range" min="1" max="4" step="0.01" v-model.number="zoom" @input="clamp" class="flex-1 accent-[#FC226B]" aria-label="크기" />
        <AppIcon name="image" :size="20" class="text-ink-faint" />
      </div>

      <div class="flex gap-2 mt-4">
        <button type="button" @click="$emit('cancel')" class="flex-1 min-h-[44px] rounded-xl border border-gray-200 text-sm font-bold text-ink-light active:bg-gray-50">취소</button>
        <button type="button" @click="confirm" :disabled="!ready || busy" class="flex-1 min-h-[44px] rounded-xl btn-primary justify-center text-sm font-bold disabled:opacity-50">{{ busy ? '처리 중...' : '적용' }}</button>
      </div>
    </div>
  </div>
</Teleport>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import AppIcon from './AppIcon.vue'

const props = defineProps({ file: { type: File, required: true } })
const emit = defineEmits(['confirm', 'cancel'])

const V = 280                 // 편집 영역 한 변(px)
const OUT = 512               // 결과 사진 한 변(px)
const url = ref('')
const imgW = ref(0)
const imgH = ref(0)
const zoom = ref(1)           // 1 = 짧은 변이 영역에 딱 맞음(cover)
const ox = ref(0)             // 사진 중심의 이동량(영역 중심 기준, px)
const oy = ref(0)
const ready = ref(false)
const busy = ref(false)
const box = ref(null)

const base = computed(() => (imgW.value && imgH.value) ? V / Math.min(imgW.value, imgH.value) : 1)
const scale = computed(() => base.value * zoom.value)

const imgStyle = computed(() => ({
  left: '50%', top: '50%',
  width: imgW.value + 'px', height: imgH.value + 'px',
  transform: `translate(-50%, -50%) translate(${ox.value}px, ${oy.value}px) scale(${scale.value})`,
  transformOrigin: 'center center',
}))

// 사진이 항상 영역을 덮도록(빈틈 없이) 이동 범위를 제한
function clamp() {
  const maxX = Math.max(0, (imgW.value * scale.value - V) / 2)
  const maxY = Math.max(0, (imgH.value * scale.value - V) / 2)
  ox.value = Math.max(-maxX, Math.min(maxX, ox.value))
  oy.value = Math.max(-maxY, Math.min(maxY, oy.value))
}

function onLoad(e) {
  imgW.value = e.target.naturalWidth
  imgH.value = e.target.naturalHeight
  zoom.value = 1; ox.value = 0; oy.value = 0
  ready.value = true
}

// ── 끌기(한 손가락/마우스) + 확대(두 손가락) ──
const pointers = new Map()
let lastDist = 0
function onDown(e) {
  box.value?.setPointerCapture?.(e.pointerId)
  pointers.set(e.pointerId, { x: e.clientX, y: e.clientY })
  if (pointers.size === 2) lastDist = dist()
}
function onMove(e) {
  const p = pointers.get(e.pointerId)
  if (!p) return
  const dx = e.clientX - p.x, dy = e.clientY - p.y
  p.x = e.clientX; p.y = e.clientY
  if (pointers.size === 1) {
    ox.value += dx; oy.value += dy
    clamp()
  } else if (pointers.size === 2) {
    const d = dist()
    if (lastDist > 0) { zoom.value = Math.max(1, Math.min(4, zoom.value * (d / lastDist))); clamp() }
    lastDist = d
  }
}
function onUp(e) {
  pointers.delete(e.pointerId)
  if (pointers.size < 2) lastDist = 0
}
function dist() {
  const [a, b] = [...pointers.values()]
  return Math.hypot(a.x - b.x, a.y - b.y)
}
function onWheel(e) {
  zoom.value = Math.max(1, Math.min(4, zoom.value * (e.deltaY < 0 ? 1.08 : 1 / 1.08)))
  clamp()
}

// ── 적용: 원 안에 보이는 부분을 정사각형으로 잘라 JPEG 로 ──
function confirm() {
  if (!ready.value || busy.value) return
  busy.value = true
  const img = new Image()
  img.onload = () => {
    const s = scale.value
    const cx = imgW.value / 2 - ox.value / s      // 영역 중심에 해당하는 사진 좌표
    const cy = imgH.value / 2 - oy.value / s
    const size = V / s                            // 영역이 덮는 사진 한 변
    const canvas = document.createElement('canvas')
    canvas.width = OUT; canvas.height = OUT
    const ctx = canvas.getContext('2d')
    ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, OUT, OUT)   // 투명 PNG 대비
    ctx.imageSmoothingQuality = 'high'
    ctx.drawImage(img, cx - size / 2, cy - size / 2, size, size, 0, 0, OUT, OUT)
    canvas.toBlob(blob => {
      busy.value = false
      if (blob) emit('confirm', blob)
      else emit('cancel')
    }, 'image/jpeg', 0.92)
  }
  img.onerror = () => { busy.value = false; emit('cancel') }
  img.src = url.value
}

onMounted(() => { url.value = URL.createObjectURL(props.file) })
onUnmounted(() => { if (url.value) URL.revokeObjectURL(url.value) })
</script>
