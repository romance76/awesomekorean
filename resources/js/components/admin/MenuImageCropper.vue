<template>
<Teleport to="body">
  <div class="fixed inset-0 z-[1100] bg-black/60 flex items-center justify-center p-4" @click.self="$emit('cancel')">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[1100px] max-h-[94vh] overflow-y-auto p-4">
      <h3 class="font-bold text-ink text-base mb-0.5">메뉴 이미지 맞추기</h3>
      <p class="text-xs text-ink-muted mb-3">이미지를 끌어서 위치를 옮기고, 아래 막대(또는 마우스 휠·두 손가락)로 크기를 맞춰 주세요. 네모 안에 보이는 부분이 메뉴에 나와요. <b>아래 "실제 메뉴 미리보기"에서 바로 확인</b>하세요.</p>
      <div class="mx-auto" style="max-width:380px">

      <div class="flex flex-wrap gap-1.5 mb-1" role="group" aria-label="네모 비율 (가로:세로)">
        <button v-for="r in RATIOS" :key="r.v" type="button" @click="setRatio(r.v)"
          class="px-2.5 py-1 rounded-lg border text-xs font-bold" :class="ratio === r.v ? 'border-pink-400 bg-pink-50 text-pink-600' : 'border-gray-200 text-ink-light'">{{ r.l }}</button>
      </div>
      <p class="text-[11px] text-ink-faint mb-2">숫자가 클수록 가로로 길쭉한 모양이에요 (가로:세로).</p>

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

      </div>

      <!-- 실제 메뉴 미리보기: 사이트 상단 메뉴(PC)와 같은 크기·같은 스타일로, 지금 맞추는 이미지를 그 자리에 끼워 보여 줌 -->
      <div class="mt-4">
        <div class="text-[11.5px] font-bold text-ink-muted mb-1">실제 메뉴 미리보기 (PC 상단 메뉴, 실제 크기)</div>
        <div class="border border-gray-200 rounded-xl bg-white overflow-x-auto">
          <div class="site-hdr-menu pointer-events-none select-none" style="min-width:max-content">
            <div v-for="g in previewGroups" :key="g.id" class="site-hdr-navitem">
              <span class="site-hdr-tab relative" :class="[g.mine ? 'site-hdr-tab-on' : 'site-hdr-tab-off', g.image ? '!h-auto' : '']">
                <img v-if="g.image" :src="g.image" :alt="g.label" class="h-12 w-auto max-w-[220px] object-contain block" />
                <span v-else :style="g.color ? { color: g.color } : null">{{ g.label }}</span><span v-if="g.hasSub" class="text-[9px] opacity-50">▼</span>
                <span v-if="g.mine" class="absolute -top-0.5 left-1/2 -translate-x-1/2 text-[9px] font-bold text-white bg-pink-500 rounded-full px-1.5 leading-4 whitespace-nowrap">지금 맞추는 중</span>
              </span>
            </div>
          </div>
        </div>
        <div class="mt-2 flex items-center gap-3 flex-wrap">
          <span class="text-[11.5px] font-bold text-ink-muted">휴대폰 메뉴에서는</span>
          <span class="inline-flex items-center gap-2 border border-gray-200 rounded-xl px-3 py-2 bg-white">
            <img v-if="myPreviewImg" :src="myPreviewImg" alt="" class="h-8 w-auto max-w-[96px] object-contain" />
            <span class="text-[13px] font-semibold text-ink">{{ myLabel }}</span>
          </span>
          <span class="text-[11px] text-ink-faint">PC는 높이 48px(가로 최대 220px), 휴대폰은 높이 32px(가로 최대 96px)로 나와요.</span>
        </div>
      </div>

      <div class="flex gap-2 mt-4 mx-auto" style="max-width:380px">
        <button type="button" @click="$emit('cancel')" class="flex-1 min-h-[44px] rounded-xl border border-gray-200 text-sm font-bold text-ink-light active:bg-gray-50">취소</button>
        <button type="button" @click="confirm" :disabled="!ready || busy" class="flex-1 min-h-[44px] rounded-xl btn-primary justify-center text-sm font-bold disabled:opacity-50">{{ busy ? '처리 중…' : '이대로 올리기' }}</button>
      </div>
    </div>
  </div>
</Teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'

// groups: 사이트 상단 메뉴 묶음 목록(미리보기용), groupId: 지금 이미지를 맞추는 묶음의 _id
const props = defineProps({ file: { type: File, required: true }, groups: { type: Array, default: () => [] }, groupId: { type: [String, Number], default: null } })
const emit = defineEmits(['confirm', 'cancel'])

// 메뉴에 나오는 이미지는 가로로 긴 모양이 많아서 비율을 고를 수 있게
// (메뉴에서는 높이 48px·가로 최대 220px 안에 들어가므로 4.5:1 보다 더 긴 모양은 줄어들어 보여요)
const RATIOS = [{ v: 1, l: '정사각 1:1' }, { v: 1.5, l: '1.5:1' }, { v: 2, l: '2:1' }, { v: 2.5, l: '2.5:1' }, { v: 3, l: '3:1' }, { v: 3.5, l: '3.5:1' }, { v: 4, l: '4:1' }, { v: 4.5, l: '4.5:1' }]
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
let srcEl = null
function onLoad(e) { srcEl = e.target; imgW.value = e.target.naturalWidth; imgH.value = e.target.naturalHeight; reset(); ready.value = true }

// ── 실제 메뉴 미리보기: 편집 중인 모습 그대로를 작은 그림으로 만들어 메뉴 줄에 끼워 넣는다 ──
const PREVIEW_W = 480
const myPreviewImg = ref('')
let raf = 0
function renderPreview() {
  if (!ready.value || !srcEl) return
  const k = PREVIEW_W / W
  const canvas = document.createElement('canvas')
  canvas.width = PREVIEW_W; canvas.height = Math.round(H.value * k)
  const ctx = canvas.getContext('2d')
  ctx.imageSmoothingQuality = 'high'
  const dw = imgW.value * scale.value * k, dh = imgH.value * scale.value * k
  ctx.drawImage(srcEl, canvas.width / 2 + ox.value * k - dw / 2, canvas.height / 2 + oy.value * k - dh / 2, dw, dh)
  myPreviewImg.value = canvas.toDataURL('image/png')
}
function schedulePreview() { cancelAnimationFrame(raf); raf = requestAnimationFrame(renderPreview) }
watch([zoom, ox, oy, ratio, ready], schedulePreview)

const myGroup = computed(() => props.groups.find(g => g._id === props.groupId) || null)
const myLabel = computed(() => myGroup.value?.label || '메뉴')
const previewGroups = computed(() => props.groups.filter(g => !g.hidden || g._id === props.groupId).map(g => {
  const mine = g._id === props.groupId
  return {
    id: g._id, mine, label: g.label, color: g.color || '',
    image: mine ? (myPreviewImg.value || g.image || '') : (g.image || ''),
    hasSub: g.type !== 'single' && (g.items || []).length > 0,
  }
}))

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
onUnmounted(() => { cancelAnimationFrame(raf); if (url.value) URL.revokeObjectURL(url.value) })
</script>
