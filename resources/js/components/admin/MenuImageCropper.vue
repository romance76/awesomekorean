<template>
<Teleport to="body">
  <div class="fixed inset-0 z-[1100] bg-black/60 flex items-center justify-center p-4" @click.self="$emit('cancel')">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[380px] p-4">
      <h3 class="font-bold text-ink text-base mb-0.5">메뉴 이미지 맞추기</h3>
      <p class="text-xs text-ink-muted mb-3">이미지를 끌어서 위치를 옮기고, 아래 막대(또는 마우스 휠·두 손가락)로 크기를 맞춰 주세요. 네모 안에 보이는 부분이 메뉴에 나와요.</p>

      <div class="flex gap-1.5 mb-2" role="group" aria-label="네모 비율">
        <button v-for="r in RATIOS" :key="r.v" type="button" @click="setRatio(r.v)"
          class="px-3 py-1 rounded-lg border text-xs font-bold" :class="ratio === r.v ? 'border-pink-400 bg-pink-50 text-pink-600' : 'border-gray-200 text-ink-light'">{{ r.l }}</button>
      </div>

      <!-- 편집 영역: 체크무늬 = 투명한 부분 -->
      <div ref="box" class="relative mx-auto select-none overflow-hidden rounded-xl border border-gray-300"
        :style="{ width: W + 'px', height: H + 'px', touchAction: 'none', cursor: 'grab', backgroundImage: 'linear-gradient(45deg,#eee 25%,transparent 25%,transparent 75%,#eee 75%),linear-gradient(45deg,#eee 25%,#fff 25%,#fff 75%,#eee 75%)', backgroundSize: '16px 16px', backgroundPosition: '0 0,8px 8px' }"
        @pointerdown="onDown" @pointermove="onMove" @pointerup="onUp" @pointercancel="onUp" @wheel.prevent="onWheel">
        <img v-if="url" :src="url" alt="" draggable="false" @load="onLoad" class="absolute max-w-none pointer-events-none" :style="imgStyle" />
      </div>

      <div class="flex items-center gap-2 mt-3 px-1">
        <span class="text-[11px] text-ink-faint">작게</span>
        <input type="range" min="0.3" max="4" step="0.01" v-model.number="zoom" @input="clamp" class="flex-1 accent-[#FC226B]" aria-label="크기" />
        <span class="text-[11px] text-ink-faint">크게</span>
        <button type="button" @click="reset" class="text-[11px] underline text-ink-muted">처음대로</button>
      </div>

      <div class="flex gap-2 mt-4">
        <button type="button" @click="$emit('cancel')" class="flex-1 min-h-[44px] rounded-xl border border-gray-200 text-sm font-bold text-ink-light active:bg-gray-50">취소</button>
        <button type="button" @click="confirm" :disabled="!ready || busy" class="flex-1 min-h-[44px] rounded-xl btn-primary justify-center text-sm font-bold disabled:opacity-50">{{ busy ? '처리 중…' : '이대로 올리기' }}</button>
      </div>
    </div>
  </div>
</Teleport>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({ file: { type: File, required: true } })
const emit = defineEmits(['confirm', 'cancel'])

// 메뉴에 나오는 이미지는 가로로 긴 모양이 많아서 비율을 고를 수 있게
const RATIOS = [{ v: 1, l: '정사각 1:1' }, { v: 2, l: '가로 2:1' }, { v: 3, l: '가로 3:1' }, { v: 4, l: '가로 4:1' }]
const W = 320                         // 편집 영역 가로(px)
const OUT_W = 800                     // 결과 이미지 가로(px) — 서버가 높이 96px 이하로 다시 줄여요
const ratio = ref(3)
const H = computed(() => Math.round(W / ratio.value))
const url = ref('')
const imgW = ref(0); const imgH = ref(0)
const zoom = ref(1)                   // 1 = 이미지 전체가 네모 안에 들어옴
const ox = ref(0); const oy = ref(0)  // 이미지 중심 이동량(네모 중심 기준, px)
const ready = ref(false); const busy = ref(false)
const box = ref(null)

const base = computed(() => (imgW.value && imgH.value) ? Math.min(W / imgW.value, H.value / imgH.value) : 1)
const scale = computed(() => base.value * zoom.value)
const imgStyle = computed(() => ({
  left: '50%', top: '50%', width: imgW.value + 'px', height: imgH.value + 'px',
  transform: `translate(-50%, -50%) translate(${ox.value}px, ${oy.value}px) scale(${scale.value})`, transformOrigin: 'center center',
}))

// 이미지가 네모 밖으로 완전히 사라지지 않게만 제한
function clamp() {
  const maxX = W / 2 + imgW.value * scale.value / 2 - 24
  const maxY = H.value / 2 + imgH.value * scale.value / 2 - 24
  ox.value = Math.max(-maxX, Math.min(maxX, ox.value))
  oy.value = Math.max(-maxY, Math.min(maxY, oy.value))
}
function reset() { zoom.value = 1; ox.value = 0; oy.value = 0 }
function setRatio(v) { ratio.value = v; reset() }
function onLoad(e) { imgW.value = e.target.naturalWidth; imgH.value = e.target.naturalHeight; reset(); ready.value = true }

const pointers = new Map()
let lastDist = 0
function onDown(e) { box.value?.setPointerCapture?.(e.pointerId); pointers.set(e.pointerId, { x: e.clientX, y: e.clientY }); if (pointers.size === 2) lastDist = dist() }
function onMove(e) {
  const p = pointers.get(e.pointerId); if (!p) return
  const dx = e.clientX - p.x, dy = e.clientY - p.y
  p.x = e.clientX; p.y = e.clientY
  if (pointers.size === 1) { ox.value += dx; oy.value += dy; clamp() }
  else if (pointers.size === 2) { const d = dist(); if (lastDist > 0) { zoom.value = Math.max(0.3, Math.min(4, zoom.value * (d / lastDist))); clamp() } lastDist = d }
}
function onUp(e) { pointers.delete(e.pointerId); if (pointers.size < 2) lastDist = 0 }
function dist() { const [a, b] = [...pointers.values()]; return Math.hypot(a.x - b.x, a.y - b.y) }
function onWheel(e) { zoom.value = Math.max(0.3, Math.min(4, zoom.value * (e.deltaY < 0 ? 1.08 : 1 / 1.08))); clamp() }

// 적용: 네모 안에 보이는 그대로 투명 PNG 로 (빈 곳은 투명)
function confirm() {
  if (!ready.value || busy.value) return
  busy.value = true
  const img = new Image()
  img.onload = () => {
    const k = OUT_W / W                                   // 화면 → 결과 배율
    const canvas = document.createElement('canvas')
    canvas.width = OUT_W; canvas.height = Math.round(H.value * k)
    const ctx = canvas.getContext('2d')
    ctx.imageSmoothingQuality = 'high'
    const dw = imgW.value * scale.value * k, dh = imgH.value * scale.value * k
    const cx = canvas.width / 2 + ox.value * k, cy = canvas.height / 2 + oy.value * k
    ctx.drawImage(img, cx - dw / 2, cy - dh / 2, dw, dh)
    canvas.toBlob(blob => { busy.value = false; blob ? emit('confirm', blob) : emit('cancel') }, 'image/png')
  }
  img.onerror = () => { busy.value = false; emit('cancel') }
  img.src = url.value
}

onMounted(() => { url.value = URL.createObjectURL(props.file) })
onUnmounted(() => { if (url.value) URL.revokeObjectURL(url.value) })
</script>
