<template>
  <div class="lottery-stage rounded-2xl overflow-hidden border border-white/10 shadow-lg" :style="rootStyle">
    <!-- 무대 -->
    <div ref="stageEl" class="stage relative w-full" :class="compact ? 'stage-compact' : 'stage-normal'" :style="stageStyle">
      <canvas ref="canvasEl" class="absolute inset-0 w-full h-full block" aria-label="3D 추첨기"></canvas>
      <!-- 배경 이미지 레이어 (선택): 캔버스 위에 화면 합성(어떤 URL 이든 CORS 무관) -->
      <div v-if="t.background_image_url" class="bg-layer absolute inset-0 bg-cover bg-center pointer-events-none" :style="{ backgroundImage: `url('${t.background_image_url}')` }"></div>

      <template v-if="!hideChrome">
        <!-- 로고 -->
        <div v-if="t.show_logo && t.logo_url" class="glass-chip absolute top-2.5 left-3 px-2.5 py-1.5 rounded-xl pointer-events-none">
          <img :src="t.logo_url" alt="" class="h-6 sm:h-8 w-auto max-w-[9rem] object-contain" @error="$event.target.parentElement.style.display = 'none'" />
        </div>

        <!-- 상태 뱃지 -->
        <div class="glass-chip absolute top-2.5 right-3 px-3 py-1.5 rounded-full text-[11px] sm:text-xs font-bold text-white flex items-center gap-1.5" :style="{ borderColor: t.accent }">
          <span class="w-1.5 h-1.5 rounded-full" :class="{ 'pulse-dot': phase !== 'idle' && phase !== 'reveal' }" :style="{ background: t.accent, boxShadow: `0 0 8px ${t.accent}` }"></span>
          {{ statusLabel }}
        </div>

        <!-- 당첨 번호 발표 -->
        <transition name="pop">
          <div v-if="phase === 'reveal'" class="reveal-card absolute left-1/2 bottom-3 text-center text-white" :style="{ borderColor: t.accent, boxShadow: `0 0 34px ${accentSoft}, inset 0 1px 0 rgba(255,255,255,.3)` }">
            <div class="flex items-center justify-center gap-1 sm:gap-2">
              <svg class="laurel" viewBox="0 0 40 80" aria-hidden="true">
                <path d="M30 76 C 10 62 6 30 20 4" fill="none" :stroke="t.accent" stroke-width="1.6" stroke-linecap="round" />
                <ellipse v-for="(l, i) in laurel" :key="i" :cx="l.x" :cy="l.y" rx="6.5" ry="2.6" :transform="`rotate(${l.r} ${l.x} ${l.y})`" :fill="t.accent" opacity="0.92" />
              </svg>
              <div>
                <div class="text-[10px] tracking-[0.3em] opacity-80 font-bold whitespace-nowrap">WINNING TICKET</div>
                <div class="win-number text-4xl sm:text-5xl font-black leading-none" :style="{ color: t.accent, textShadow: `0 0 18px ${accentSoft}, 0 2px 0 rgba(0,0,0,.5)` }">{{ winnerNumberText }}</div>
                <div v-if="winnerName" class="text-sm font-bold mt-1">{{ winnerName }} 님</div>
              </div>
              <svg class="laurel" style="transform: scaleX(-1)" viewBox="0 0 40 80" aria-hidden="true">
                <path d="M30 76 C 10 62 6 30 20 4" fill="none" :stroke="t.accent" stroke-width="1.6" stroke-linecap="round" />
                <ellipse v-for="(l, i) in laurel" :key="i" :cx="l.x" :cy="l.y" rx="6.5" ry="2.6" :transform="`rotate(${l.r} ${l.x} ${l.y})`" :fill="t.accent" opacity="0.92" />
              </svg>
            </div>
          </div>
        </transition>
      </template>

      <!-- WebGL 불가 / 로딩 -->
      <div v-if="glError" class="absolute inset-0 flex items-center justify-center text-center px-6 text-sm text-white/90">
        <div>
          <div class="text-3xl mb-2">🎰</div>
          {{ glError }}
          <div v-if="winningTicket != null" class="mt-3 font-black text-xl" :style="{ color: t.accent }">당첨 번호 {{ winnerNumberText }}<span v-if="winnerName"> · {{ winnerName }} 님</span></div>
        </div>
      </div>
      <div v-else-if="loading" class="absolute inset-0 flex items-center justify-center text-xs text-white/70">3D 추첨기를 불러오는 중…</div>
    </div>

    <!-- 하단 정보 영역 -->
    <div v-if="!hideChrome" class="info-bar p-3 sm:p-4 flex flex-col sm:flex-row gap-3 sm:items-center" :style="{ background: `linear-gradient(180deg, ${t.bg_bottom}, #04060f)` }">
      <!-- 경품 액자 -->
      <div class="flex items-center gap-3 flex-1 min-w-0">
        <div class="shrink-0 w-16 h-16 rounded-xl overflow-hidden flex items-center justify-center bg-white/5" :style="{ border: `2px solid ${t.accent}`, boxShadow: `0 0 16px ${accentSoft}, inset 0 0 10px rgba(255,255,255,.12)` }">
          <img v-if="prizeImg && !prizeImgFailed" :src="prizeImg" alt="" class="w-full h-full object-cover" @error="prizeImgFailed = true" />
          <span v-else class="text-3xl">🎁</span>
        </div>
        <div class="min-w-0">
          <div class="text-[11px] font-bold tracking-widest" :style="{ color: t.accent }">PRIZE · 경품</div>
          <div class="text-base font-black text-white truncate">{{ prizeName || '경품' }}</div>
          <div class="text-[11px] text-white/60">총 {{ ticketCount.toLocaleString() }}장의 응모권 · {{ statusLabel }}</div>
        </div>
      </div>

      <!-- 단계 + 버튼 -->
      <div class="flex items-center gap-3 sm:justify-end">
        <div class="flex items-center gap-1.5" aria-hidden="true">
          <span v-for="(s, i) in STEPS" :key="s" class="w-2 h-2 rounded-full transition-all" :style="{ background: stepIndex >= i && phase !== 'idle' ? t.accent : 'rgba(255,255,255,.22)', boxShadow: stepIndex >= i && phase !== 'idle' ? `0 0 8px ${t.accent}` : 'none' }"></span>
        </div>
        <button type="button" @click="play()" :disabled="!canPlay" class="gold-btn px-5 py-2.5 rounded-xl text-sm font-black disabled:opacity-40 disabled:cursor-not-allowed" :style="{ background: `linear-gradient(180deg, ${lightAccent}, ${t.accent})`, color: btnText, boxShadow: `0 4px 18px ${accentSoft}, inset 0 1px 0 rgba(255,255,255,.55)` }">
          {{ played ? '추첨 영상 다시보기' : '추첨 영상 보기' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { mergeTheme, hexToInt, hexToRgba, lightenHex, readableOn } from '../composables/useLotteryTheme'

const props = defineProps({
  theme: { type: Object, default: () => ({}) },
  prizeName: { type: String, default: '' },
  prizeImage: { type: String, default: '' },
  ticketCount: { type: Number, default: 0 },
  winningTicket: { type: Number, default: null },
  winnerName: { type: String, default: '' },
  autoplay: { type: Boolean, default: false },
  compact: { type: Boolean, default: false },
  // true 이면 자체 HTML 오버레이(로고/뱃지/당첨카드/하단 정보바)를 모두 숨기고 3D 캔버스만 표시
  hideChrome: { type: Boolean, default: false },
})
const emit = defineEmits(['finished', 'phase'])

const STEPS = ['mix', 'select', 'eject', 'reveal']

const t = computed(() => mergeTheme(props.theme))
const themeKey = computed(() => JSON.stringify(t.value))
const accentSoft = computed(() => hexToRgba(t.value.accent, 0.3))
const lightAccent = computed(() => lightenHex(t.value.accent, 0.35))
const btnText = computed(() => readableOn(t.value.accent))
const rootStyle = computed(() => ({ background: t.value.bg_bottom }))
const stageStyle = computed(() => ({ background: `linear-gradient(180deg, ${t.value.bg_top}, ${t.value.bg_bottom})` }))
const prizeImg = computed(() => t.value.prize_image_url || props.prizeImage || '')
const prizeImgFailed = ref(false)
watch(prizeImg, () => { prizeImgFailed.value = false })

// 월계관 잎 (베지어 곡선 위에 양쪽으로 배치)
const laurel = (() => {
  const P = [[30, 76], [10, 62], [6, 30], [20, 4]]
  const bez = (u) => {
    const a = (1 - u) ** 3, b = 3 * u * (1 - u) ** 2, c = 3 * u * u * (1 - u), d = u ** 3
    return [a * P[0][0] + b * P[1][0] + c * P[2][0] + d * P[3][0], a * P[0][1] + b * P[1][1] + c * P[2][1] + d * P[3][1]]
  }
  const out = []
  for (let i = 0; i < 7; i++) {
    const u = 0.12 + i * 0.13
    const [x, y] = bez(u), [x2, y2] = bez(u + 0.02)
    const ang = (Math.atan2(y2 - y, x2 - x) * 180) / Math.PI
    out.push({ x: x - 5, y: y - 1, r: ang - 35 })
    out.push({ x: x + 5, y: y + 1, r: ang + 35 })
  }
  return out
})()

const stageEl = ref(null)
const canvasEl = ref(null)
const phase = ref('idle') // idle | mix | select | eject | reveal
const played = ref(false)
const loading = ref(true)
const glError = ref('')
const stepIndex = computed(() => STEPS.indexOf(phase.value))
watch(phase, (p) => emit('phase', p))

const hasResult = computed(() => props.winningTicket != null && Number(props.winningTicket) >= 1)
const canPlay = computed(() => hasResult.value && !loading.value && !glError.value && (phase.value === 'idle' || phase.value === 'reveal'))
const winnerNumberText = computed(() => (hasResult.value ? String(props.winningTicket).padStart(3, '0') : '---'))
const statusLabel = computed(() => {
  if (glError.value) return '3D 사용 불가'
  switch (phase.value) {
    case 'mix': return '공을 섞는 중…'
    case 'select': return '당첨 공 선택 중…'
    case 'eject': return '당첨 공이 나오는 중…'
    case 'reveal': return '🎉 당첨 번호 발표'
    default: return hasResult.value ? '추첨 결과 확정' : '추첨 대기 중'
  }
})

const reduced = typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
const DUR = reduced ? { mix: 2.0, select: 0.8, eject: 2.0 } : { mix: 4.8, select: 1.8, eject: 4.0 }

// ── 장면 상수 ──
const GX = -1.3, GY = 0.8, GR = 2.4          // 유리구 중심/반지름
const CX = 4.05, CZ = 0.3                    // 수집 챔버 위치
const FLOOR_Y = -2.4
const CH_FLOOR = -2.2                        // 챔버 바닥 윗면
const SCENE_CX = 0.65, SCENE_CY = 0.55       // 카메라 기준 중심

// ── three.js 내부 상태 (반응형 아님) ──
let THREE = null
let X = {}                 // 동적 import 된 확장 모듈
let renderer = null, scene = null, camera = null
let composer = null, renderPass = null, bloomPass = null
let envTex = null
let useComposer = false, useReflector = false
let tubePath = null
let balls = [], carrier = null
let ballHalo = null, winLight = null, accentLight = null
let reflector = null
let bokeh = [], sparkle = null
let texCache = new Map(), ownTextures = []
let raf = 0, inView = true, tabVisible = true, unmounted = false
let ro = null, io = null
let ph = 'idle', phStart = 0, playTicket = null, buildToken = 0
let lastNow = 0, simClock = 0
let aspect = 1.6
let camDist = 14, camBlend = 0
let dropState = null, restedAt = 0, revealAt = 0
let emitAcc = 0
let perfEma = 16, perfFrames = 0
let BR = 0.33

const PIPE_A = { x: -0.15, y: 1.95, z: 0 }
const FINAL_R = 0.56

function mulberry32(a) {
  return () => { a |= 0; a = (a + 0x6d2b79f5) | 0; let x = Math.imul(a ^ (a >>> 15), 1 | a); x = (x + Math.imul(x ^ (x >>> 7), 61 | x)) ^ x; return ((x ^ (x >>> 14)) >>> 0) / 4294967296 }
}
const cosRand = mulberry32(777)

function ballCount() { return Math.max(12, Math.min(props.ticketCount || 0, 34)) }
const smooth = (x) => { x = Math.max(0, Math.min(1, x)); return x * x * (3 - 2 * x) }
const clamp01 = (x) => Math.max(0, Math.min(1, x))

// ── 텍스처 ──
function shade(hex, f) {
  const n = parseInt(hex.slice(1), 16)
  const c = (v) => Math.max(0, Math.min(255, Math.round(v * f)))
  return `rgb(${c((n >> 16) & 255)},${c((n >> 8) & 255)},${c(n & 255)})`
}

// 구 UV: u=0.25 → +z(정면), u=0.75 → -z. 양면에 흰 원판 + 번호
function numberTexture(n, color) {
  const key = `${n}|${color}`
  if (texCache.has(key)) return texCache.get(key)
  const hi = !props.compact
  const W = hi ? 512 : 256, H = W / 2
  const c = document.createElement('canvas')
  c.width = W; c.height = H
  const ctx = c.getContext('2d')
  const g = ctx.createLinearGradient(0, 0, 0, H)
  g.addColorStop(0, shade(color, 0.72)); g.addColorStop(0.35, color); g.addColorStop(0.65, color); g.addColorStop(1, shade(color, 0.72))
  ctx.fillStyle = g; ctx.fillRect(0, 0, W, H)
  const s = String(n)
  const dr = H * 0.25
  for (const cx of [W * 0.25, W * 0.75]) {
    const cy = H / 2
    ctx.beginPath(); ctx.arc(cx, cy, dr + H * 0.018, 0, Math.PI * 2); ctx.fillStyle = shade(color, 0.55); ctx.fill()
    const rg = ctx.createRadialGradient(cx - dr * 0.3, cy - dr * 0.35, dr * 0.1, cx, cy, dr)
    rg.addColorStop(0, '#ffffff'); rg.addColorStop(0.75, '#f6f7fb'); rg.addColorStop(1, '#d9dde8')
    ctx.beginPath(); ctx.arc(cx, cy, dr, 0, Math.PI * 2); ctx.fillStyle = rg; ctx.fill()
    const size = Math.round(dr * (s.length <= 2 ? 1.12 : s.length === 3 ? 0.88 : s.length === 4 ? 0.7 : 0.56))
    ctx.fillStyle = '#12172b'
    ctx.font = `900 ${size}px "Arial Black", Arial, sans-serif`
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle'
    ctx.fillText(s, cx, cy + size * 0.04)
  }
  const tex = new THREE.CanvasTexture(c)
  tex.colorSpace = THREE.SRGBColorSpace
  tex.anisotropy = Math.min(8, renderer?.capabilities.getMaxAnisotropy?.() || 1)
  texCache.set(key, tex); ownTextures.push(tex)
  return tex
}

function radialTexture(stops, size = 128) {
  const c = document.createElement('canvas'); c.width = c.height = size
  const ctx = c.getContext('2d')
  const g = ctx.createRadialGradient(size / 2, size / 2, 0, size / 2, size / 2, size / 2)
  stops.forEach(([o, col]) => g.addColorStop(o, col))
  ctx.fillStyle = g; ctx.fillRect(0, 0, size, size)
  const tex = new THREE.CanvasTexture(c); tex.colorSpace = THREE.SRGBColorSpace
  ownTextures.push(tex)
  return tex
}

function starTexture() {
  const c = document.createElement('canvas'); c.width = c.height = 64
  const ctx = c.getContext('2d')
  const g = ctx.createRadialGradient(32, 32, 0, 32, 32, 32)
  g.addColorStop(0, 'rgba(255,255,255,1)'); g.addColorStop(0.25, 'rgba(255,240,200,.55)'); g.addColorStop(1, 'rgba(255,255,255,0)')
  ctx.fillStyle = g; ctx.fillRect(0, 0, 64, 64)
  ctx.strokeStyle = 'rgba(255,255,255,.85)'; ctx.lineWidth = 1.5
  ctx.beginPath(); ctx.moveTo(32, 2); ctx.lineTo(32, 62); ctx.moveTo(2, 32); ctx.lineTo(62, 32); ctx.stroke()
  const tex = new THREE.CanvasTexture(c); tex.colorSpace = THREE.SRGBColorSpace
  ownTextures.push(tex)
  return tex
}

function makeBgTexture(th) {
  const W = 320, H = Math.max(96, Math.min(480, Math.round(W / aspect)))
  const c = document.createElement('canvas'); c.width = W; c.height = H
  const ctx = c.getContext('2d')
  const g = ctx.createLinearGradient(0, 0, 0, H)
  g.addColorStop(0, th.bg_top); g.addColorStop(1, th.bg_bottom)
  ctx.fillStyle = g; ctx.fillRect(0, 0, W, H)
  const glow = (x, y, r, col) => {
    const rg = ctx.createRadialGradient(x, y, 0, x, y, r)
    rg.addColorStop(0, col); rg.addColorStop(1, 'rgba(0,0,0,0)')
    ctx.fillStyle = rg; ctx.fillRect(0, 0, W, H)
  }
  glow(W * 0.42, H * 0.46, W * 0.5, hexToRgba(th.accent, 0.2))
  glow(W * 0.92, H * 0.18, W * 0.45, 'rgba(90,120,255,.28)')
  glow(W * 0.05, H * 0.2, W * 0.4, 'rgba(160,90,255,.2)')
  const vg = ctx.createRadialGradient(W / 2, H * 0.5, H * 0.3, W / 2, H * 0.5, W * 0.7)
  vg.addColorStop(0, 'rgba(0,0,0,0)'); vg.addColorStop(1, 'rgba(0,0,0,.45)')
  ctx.fillStyle = vg; ctx.fillRect(0, 0, W, H)
  const tex = new THREE.CanvasTexture(c); tex.colorSpace = THREE.SRGBColorSpace
  return tex
}

function disposeScene() {
  buildToken++
  if (reflector) { try { reflector.dispose?.() } catch (e) { /* ignore */ } reflector = null }
  if (scene) {
    if (scene.background?.dispose) scene.background.dispose()
    scene.traverse((o) => {
      if (o.geometry) o.geometry.dispose()
      if (o.material) (Array.isArray(o.material) ? o.material : [o.material]).forEach((m) => m.dispose())
    })
  }
  ownTextures.forEach((x) => x.dispose())
  ownTextures = []; texCache = new Map()
  balls = []; carrier = null; ballHalo = null; winLight = null; accentLight = null; scene = null
  bokeh = []; sparkle = null; dropState = null
}

function buildScene() {
  disposeScene()
  const th = t.value
  const accentI = hexToInt(th.accent)
  scene = new THREE.Scene()
  scene.background = makeBgTexture(th)
  if (envTex) { scene.environment = envTex; scene.environmentIntensity = 1.05 }
  scene.add(new THREE.HemisphereLight(0xbfd8ff, 0x20163a, envTex ? 0.35 : 2.2))
  const key = new THREE.DirectionalLight(0xffffff, envTex ? 1.6 : 2.4); key.position.set(-5, 9, 7); scene.add(key)
  const pl = (c, p, x, y, z, d = 22) => { const l = new THREE.PointLight(c, p, d, 2); l.position.set(x, y, z); scene.add(l); return l }
  pl(0x5f8bff, 90, -7, 3, -2); pl(0xffa94d, 80, 8, 2.5, -3)
  accentLight = pl(accentI, 30, GX, -1.4, 4.5, 14)
  winLight = pl(accentI, 0, CX, -1, CZ + 2.2, 10)

  // 재질
  const gold = new THREE.MeshPhysicalMaterial({ color: 0xffc24a, metalness: 1, roughness: 0.17, clearcoat: 0.5, clearcoatRoughness: 0.1, envMapIntensity: 1.4, side: THREE.DoubleSide })
  const black = new THREE.MeshPhysicalMaterial({ color: 0x07090f, metalness: 0.45, roughness: 0.14, clearcoat: 1, clearcoatRoughness: 0.04, envMapIntensity: 1.1, side: THREE.DoubleSide })
  const tint = new THREE.Color(th.glass_tint)
  // 유리: 어두운 배경 위에 반사만 가산 (투명 + 흐려지지 않는 반사)
  const glass = new THREE.MeshPhysicalMaterial({
    color: tint.clone().multiplyScalar(0.012), metalness: 0, roughness: 0.02, ior: 1.5, specularIntensity: 1,
    clearcoat: 0, envMapIntensity: envTex ? 0.32 : 1,
    transparent: true, blending: THREE.CustomBlending, blendSrc: THREE.OneFactor, blendDst: THREE.OneFactor, blendEquation: THREE.AddEquation,
    side: THREE.DoubleSide, depthWrite: false,
  })
  const add = (geo, m, x = 0, y = 0, z = 0) => { const o = new THREE.Mesh(geo, m); o.position.set(x, y, z); scene.add(o); return o }
  const lathe = (pts, m, x, y, z, seg = 96) => add(new THREE.LatheGeometry(pts.map((p) => new THREE.Vector2(p[0], p[1])), seg), m, x, y, z)
  const flatTorus = (r, tk, m, x, y, z, seg = 128) => { const o = add(new THREE.TorusGeometry(r, tk, 16, seg), m, x, y, z); o.rotation.x = Math.PI / 2; return o }

  // ── 바닥 ──
  const floorR = 13
  useReflector = useReflector && !!X.Reflector
  if (useReflector) {
    reflector = new X.Reflector(new THREE.CircleGeometry(floorR * 0.62, 64), { textureWidth: 512, textureHeight: 512, color: 0x4a536b, clipBias: 0.003 })
    reflector.rotation.x = -Math.PI / 2; reflector.position.set(SCENE_CX, FLOOR_Y, 0)
    scene.add(reflector)
  }
  const fade = radialTexture([[0, '#fff'], [0.55, '#fff'], [0.9, '#444'], [1, '#000']], 128)
  fade.colorSpace = THREE.NoColorSpace
  const floorMat = new THREE.MeshPhysicalMaterial({ color: hexToInt(th.floor), metalness: useReflector ? 0.2 : 0.8, roughness: useReflector ? 0.5 : 0.22, clearcoat: 0.3, clearcoatRoughness: 0.2, envMapIntensity: 0.18, transparent: true, opacity: useReflector ? 0.86 : 0.96, alphaMap: fade, depthWrite: !useReflector })
  const floor = add(new THREE.CircleGeometry(floorR, 64), floorMat, SCENE_CX, FLOOR_Y + 0.004, 0)
  floor.rotation.x = -Math.PI / 2

  // 바닥 동심원 링 (HDR 색 → 블룸)
  const ringMat = (r, g, b) => new THREE.MeshBasicMaterial({ color: new THREE.Color(r, g, b), toneMapped: true })
  const accentC = new THREE.Color(th.accent)
  flatTorus(3.75, 0.022, ringMat(accentC.r * 2.2, accentC.g * 2.2, accentC.b * 2.2), GX, FLOOR_Y + 0.012, 0)
  flatTorus(4.9, 0.018, ringMat(0.2, 0.45, 1.7), GX, FLOOR_Y + 0.012, 0)
  flatTorus(6.3, 0.014, ringMat(accentC.r * 1.1, accentC.g * 1.1, accentC.b * 1.1), GX, FLOOR_Y + 0.012, 0)
  flatTorus(1.75, 0.02, ringMat(accentC.r * 2.2, accentC.g * 2.2, accentC.b * 2.2), CX, FLOOR_Y + 0.012, CZ)
  flatTorus(2.4, 0.014, ringMat(0.2, 0.45, 1.7), CX, FLOOR_Y + 0.012, CZ)
  // 바닥에 번지는 링 광원
  const glowTex = radialTexture([[0, 'rgba(255,255,255,0)'], [0.5, 'rgba(255,255,255,0)'], [0.63, 'rgba(255,255,255,.85)'], [0.8, 'rgba(255,255,255,.12)'], [1, 'rgba(255,255,255,0)']], 256)
  const glowPlane = add(new THREE.PlaneGeometry(10.5, 10.5), new THREE.MeshBasicMaterial({ map: glowTex, color: accentI, transparent: true, opacity: 0.4, blending: THREE.AdditiveBlending, depthWrite: false }), GX, FLOOR_Y + 0.02, 0)
  glowPlane.rotation.x = -Math.PI / 2

  // ── 본체 받침 (선반 프로파일) ──
  const phiTop = Math.asin((-1.12 - GY) / GR)
  const dish = []
  for (let i = 0; i <= 10; i++) { const a = phiTop + ((-Math.PI / 2 - phiTop) * i) / 10; dish.push([Math.max(0.001, (GR - 0.03) * Math.cos(a)), GY + (GR - 0.03) * Math.sin(a)]) }
  const baseProfile = [[0.001, -2.4], [3.0, -2.4], [3.12, -2.36], [3.17, -2.28], [3.1, -2.18], [2.72, -2.05], [2.38, -1.9], [2.12, -1.6], [1.96, -1.2], ...dish]
  lathe(baseProfile, black, GX, 0, 0)
  flatTorus(3.12, 0.065, gold, GX, -2.22)               // 하단 금 링
  flatTorus(2.55, 0.05, gold, GX, -1.93)
  // 상단 금 칼라 (유리구를 감싸는 베벨 링)
  lathe([[1.44, -1.17], [1.84, -1.17], [1.93, -1.1], [1.93, -1.0], [1.84, -0.93], [1.5, -0.93], [1.44, -1.0]], gold, GX, 0, 0)
  // LED 링
  const led = new THREE.MeshStandardMaterial({ color: 0x111111, emissive: accentI, emissiveIntensity: 3.2, roughness: 0.4 })
  flatTorus(2.62, 0.045, led, GX, -1.97)
  flatTorus(3.17, 0.03, led, GX, -2.3)

  // ── 유리구 ──
  add(new THREE.SphereGeometry(GR, 64, 48), glass, GX, GY, 0).renderOrder = 5
  // 구 윗부분 금 캡
  lathe([[0.001, GY + GR + 0.05], [0.34, GY + GR + 0.03], [0.46, GY + GR - 0.08], [0.4, GY + GR - 0.2], [0.001, GY + GR - 0.2]], gold, GX, 0, 0, 48)

  // ── 파이프 ──
  tubePath = new THREE.CatmullRomCurve3([
    new THREE.Vector3(PIPE_A.x, PIPE_A.y, 0), new THREE.Vector3(0.85, 2.5, 0.05), new THREE.Vector3(2.1, 2.95, 0.1),
    new THREE.Vector3(3.4, 2.65, 0.2), new THREE.Vector3(3.98, 1.75, CZ), new THREE.Vector3(CX, 0.85, CZ), new THREE.Vector3(CX, 0.12, CZ),
  ], false, 'centripetal')
  add(new THREE.TubeGeometry(tubePath, 120, 0.48, 28, false), glass, 0, 0, 0).renderOrder = 5
  // 구 표면과 만나는 지점의 금속 칼라
  let tc = 0.1
  for (let u = 0; u <= 1; u += 0.01) { const p = tubePath.getPointAt(u); if (Math.hypot(p.x - GX, p.y - GY, p.z) >= GR + 0.02) { tc = u; break } }
  const cp = tubePath.getPointAt(tc), ct = tubePath.getTangentAt(tc)
  const collar = lathe([[0.44, -0.2], [0.64, -0.2], [0.72, -0.13], [0.72, 0.13], [0.64, 0.2], [0.44, 0.2]], gold, cp.x, cp.y, cp.z, 48)
  collar.quaternion.setFromUnitVectors(new THREE.Vector3(0, 1, 0), ct)
  const mouth = new THREE.Mesh(new THREE.TorusGeometry(0.5, 0.05, 12, 48), gold)
  mouth.position.set(PIPE_A.x, PIPE_A.y, 0); mouth.quaternion.setFromUnitVectors(new THREE.Vector3(0, 0, 1), tubePath.getTangentAt(0)); scene.add(mouth)

  // ── 수집 챔버 ──
  lathe([[0.001, -2.4], [1.2, -2.4], [1.28, -2.35], [1.28, -2.26], [1.12, -2.2], [0.001, -2.2]], black, CX, 0, CZ, 64)
  flatTorus(1.05, 0.05, gold, CX, -2.2, CZ, 64)
  add(new THREE.CylinderGeometry(1.0, 1.0, 2.7, 64, 1, true), glass, CX, -0.85, CZ).renderOrder = 5
  lathe([[0.5, 0.12], [1.0, 0.12], [1.13, 0.22], [1.13, 0.42], [1.0, 0.52], [0.5, 0.52]], gold, CX, 0, CZ, 64)

  // ── 공 ──
  const N = ballCount()
  BR = N > 28 ? 0.33 : 0.36
  const total = Math.max(props.ticketCount || 0, N)
  const step = Math.max(1, Math.floor(total / N))
  const geo = new THREE.SphereGeometry(BR, 40, 28)
  const rnd = mulberry32(20240607)
  for (let i = 0; i < N; i++) {
    let number = Math.min(total, i * step + 1)
    if (hasResult.value && number === Number(props.winningTicket)) number = (number % total) + 1
    const color = th.ball_colors[i % th.ball_colors.length]
    const m = new THREE.MeshPhysicalMaterial({ map: numberTexture(number, color), color: 0xffffff, roughness: 0.28, metalness: 0, clearcoat: 1, clearcoatRoughness: 0.04, envMapIntensity: 1.2, emissive: accentI, emissiveIntensity: 0 })
    const b = new THREE.Mesh(geo, m)
    const a = i * 2.399963, rad = Math.sqrt((i + 0.5) / N) * (GR - BR - 0.3)
    b.position.set(GX + Math.cos(a) * rad, GY - 0.5 + (rnd() - 0.5) * 1.6, Math.sin(a) * rad)
    b.quaternion.setFromEuler(new THREE.Euler(rnd() * 6, rnd() * 6, rnd() * 6))
    b.userData = { seed: rnd() * 90, v: new THREE.Vector3((rnd() - 0.5) * 2, 0, (rnd() - 0.5) * 2), mode: 'pool', origMap: m.map }
    scene.add(b); balls.push(b)
  }
  prewarm()

  // 후광 / 반짝이 / 보케
  const halo = new THREE.Sprite(new THREE.SpriteMaterial({ map: radialTexture([[0, 'rgba(255,255,255,.9)'], [0.35, 'rgba(255,255,255,.3)'], [1, 'rgba(255,255,255,0)']]), color: accentI, blending: THREE.AdditiveBlending, transparent: true, depthWrite: false, opacity: 0, fog: false }))
  halo.visible = false; halo.renderOrder = 7; scene.add(halo); ballHalo = halo
  buildSparkle()
  buildBokeh(th)

  // 진행 중이던 결과 화면 복원
  if (ph === 'reveal' && playTicket != null) {
    carrier = balls[0]
    applyWinnerLook(carrier)
    carrier.userData.mode = 'rest'
    carrier.scale.setScalar(FINAL_R / BR)
    carrier.position.set(CX, CH_FLOOR + FINAL_R, CZ)
    carrier.quaternion.identity()
    revealAt = performance.now() / 1000
  }
  if (renderPass) renderPass.scene = scene
}

function buildSparkle() {
  const n = 90
  const geo = new THREE.BufferGeometry()
  geo.setAttribute('position', new THREE.BufferAttribute(new Float32Array(n * 3), 3))
  geo.setAttribute('color', new THREE.BufferAttribute(new Float32Array(n * 3), 3))
  const m = new THREE.PointsMaterial({ size: 0.34, map: starTexture(), vertexColors: true, transparent: true, blending: THREE.AdditiveBlending, depthWrite: false, sizeAttenuation: true, fog: false })
  const pts = new THREE.Points(geo, m); pts.frustumCulled = false; pts.renderOrder = 8
  scene.add(pts)
  sparkle = { pts, n, vel: new Float32Array(n * 3), life: new Float32Array(n), max: new Float32Array(n), next: 0 }
}
function emitSparkles(count, ox, oy, oz) {
  if (!sparkle) return
  const pos = sparkle.pts.geometry.attributes.position.array
  for (let k = 0; k < count; k++) {
    const i = sparkle.next; sparkle.next = (i + 1) % sparkle.n
    const a = cosRand() * Math.PI * 2, e = (cosRand() - 0.35) * Math.PI, sp = 0.8 + cosRand() * 2.2
    pos[i * 3] = ox + Math.cos(a) * 0.4; pos[i * 3 + 1] = oy + Math.sin(e) * 0.4; pos[i * 3 + 2] = oz + Math.sin(a) * 0.4
    sparkle.vel[i * 3] = Math.cos(a) * Math.cos(e) * sp; sparkle.vel[i * 3 + 1] = Math.sin(e) * sp + 0.8; sparkle.vel[i * 3 + 2] = Math.sin(a) * Math.cos(e) * sp
    sparkle.max[i] = sparkle.life[i] = 1.2 + cosRand() * 1.4
  }
}
function updateSparkle(dt) {
  if (!sparkle) return
  const pos = sparkle.pts.geometry.attributes.position.array
  const col = sparkle.pts.geometry.attributes.color.array
  const ac = new THREE.Color(t.value.accent)
  for (let i = 0; i < sparkle.n; i++) {
    if (sparkle.life[i] <= 0) { col[i * 3] = col[i * 3 + 1] = col[i * 3 + 2] = 0; continue }
    sparkle.life[i] -= dt
    sparkle.vel[i * 3 + 1] -= 1.6 * dt
    pos[i * 3] += sparkle.vel[i * 3] * dt; pos[i * 3 + 1] += sparkle.vel[i * 3 + 1] * dt; pos[i * 3 + 2] += sparkle.vel[i * 3 + 2] * dt
    const f = Math.max(0, sparkle.life[i] / sparkle.max[i])
    const tw = 0.6 + 0.4 * Math.sin(simClock * 18 + i)
    const w = f * tw
    col[i * 3] = Math.min(1.6, (0.55 + ac.r * 0.8) * w * 1.4); col[i * 3 + 1] = Math.min(1.6, (0.55 + ac.g * 0.8) * w * 1.4); col[i * 3 + 2] = Math.min(1.6, (0.55 + ac.b * 0.8) * w * 1.4)
  }
  sparkle.pts.geometry.attributes.position.needsUpdate = true
  sparkle.pts.geometry.attributes.color.needsUpdate = true
}

function buildBokeh(th) {
  const tex = radialTexture([[0, 'rgba(255,255,255,.55)'], [0.7, 'rgba(255,255,255,.4)'], [0.88, 'rgba(255,255,255,.75)'], [1, 'rgba(255,255,255,0)']], 128)
  const cols = [0xffcf6a, 0xffb347, 0x5b8cff, 0x7a6bff, 0xb07cff, hexToInt(th.accent)]
  const mats = cols.map((c) => new THREE.SpriteMaterial({ map: tex, color: c, transparent: true, opacity: 0.3, blending: THREE.AdditiveBlending, depthWrite: false, fog: false }))
  const rnd = mulberry32(4242)
  const n = props.compact ? 22 : 40
  for (let i = 0; i < n; i++) {
    const s = new THREE.Sprite(mats[i % mats.length])
    const sz = 0.9 + rnd() * 3.4
    s.scale.set(sz, sz, 1)
    s.position.set(-16 + rnd() * 34, -1.5 + rnd() * 9, -10 - rnd() * 8)
    s.material = mats[i % mats.length]
    s.userData = { bx: s.position.x, by: s.position.y, ph: rnd() * 6.28, sp: 0.1 + rnd() * 0.25, amp: 0.2 + rnd() * 0.6 }
    scene.add(s); bokeh.push(s)
  }
}

// ── 시뮬레이션 ──
const _q = typeof window !== 'undefined' ? null : null
let tq = null, ta = null
function stepBalls(dt, tt, par) {
  const n = balls.length
  for (let i = 0; i < n; i++) {
    const b = balls[i], u = b.userData
    if (u.mode !== 'pool') continue
    const p = b.position, v = u.v, s = u.seed
    const dx = p.x - GX, dz = p.z
    const rxz = Math.hypot(dx, dz) + 1e-4
    v.y -= par.grav * dt
    const tang = par.swirl * Math.min(rxz, 1.5) / 1.5
    v.x += (-dz / rxz) * tang * dt; v.z += (dx / rxz) * tang * dt
    if (rxz < 1.45) v.y += par.blow * (1 - rxz / 1.45) * dt * (p.y < GY + 1.0 ? 1 : 0.35)
    v.x += Math.sin(tt * 2.7 + s) * par.wob * dt; v.y += Math.sin(tt * 3.4 + s * 1.3) * par.wob * 0.6 * dt; v.z += Math.cos(tt * 3.1 + s * 0.7) * par.wob * dt
    const damp = Math.exp(-par.damp * dt); v.multiplyScalar(damp)
    const sp = v.length(); if (sp > 9) v.multiplyScalar(9 / sp)
    p.x += v.x * dt; p.y += v.y * dt; p.z += v.z * dt
    // 구 내부 구속
    const cx = p.x - GX, cy = p.y - GY, cz = p.z
    const d = Math.hypot(cx, cy, cz), lim = GR - BR - 0.035
    if (d > lim) {
      const nx = cx / d, ny = cy / d, nz = cz / d
      p.set(GX + nx * lim, GY + ny * lim, nz * lim)
      const vn = v.x * nx + v.y * ny + v.z * nz
      if (vn > 0) { v.x -= 1.55 * vn * nx; v.y -= 1.55 * vn * ny; v.z -= 1.55 * vn * nz }
    }
  }
  // 공끼리 겹침 해소
  const md = BR * 2
  for (let i = 0; i < n; i++) {
    const a = balls[i]; if (a.userData.mode !== 'pool') continue
    for (let j = i + 1; j < n; j++) {
      const b = balls[j]; if (b.userData.mode !== 'pool') continue
      const dx = b.position.x - a.position.x, dy = b.position.y - a.position.y, dz = b.position.z - a.position.z
      const d2 = dx * dx + dy * dy + dz * dz
      if (d2 >= md * md || d2 < 1e-8) continue
      const d = Math.sqrt(d2), ov = (md - d) * 0.5, nx = dx / d, ny = dy / d, nz = dz / d
      a.position.x -= nx * ov; a.position.y -= ny * ov; a.position.z -= nz * ov
      b.position.x += nx * ov; b.position.y += ny * ov; b.position.z += nz * ov
      const va = a.userData.v, vb = b.userData.v
      const vn = (vb.x - va.x) * nx + (vb.y - va.y) * ny + (vb.z - va.z) * nz
      if (vn < 0) { const im = -vn * 0.6; va.x -= nx * im; va.y -= ny * im; va.z -= nz * im; vb.x += nx * im; vb.y += ny * im; vb.z += nz * im }
    }
  }
  // 굴러가는 회전
  for (let i = 0; i < n; i++) {
    const b = balls[i], u = b.userData
    if (u.mode !== 'pool') continue
    const v = u.v, sp = Math.hypot(v.x, v.z) + Math.abs(v.y) * 0.5
    if (sp < 1e-3) continue
    ta.set(v.z, 0.25 * Math.sin(u.seed), -v.x).normalize()
    tq.setFromAxisAngle(ta, (sp * dt) / BR * 0.9)
    b.quaternion.premultiply(tq)
  }
}

function prewarm() {
  tq = new THREE.Quaternion(); ta = new THREE.Vector3()
  const par = PAR.idleResult
  for (let i = 0; i < 90; i++) stepBalls(1 / 60, i / 60, par)
}

const PAR = {
  idleWait: { grav: 9, swirl: 3.5, blow: 19, wob: 1.2, damp: 0.9 },
  idleResult: { grav: 9, swirl: 3, blow: 17, wob: 0.8, damp: 1.0 },
  mix: { grav: 9, swirl: 12, blow: 42, wob: 2.4, damp: 0.7 },
  select: { grav: 9, swirl: 6, blow: 24, wob: 1.2, damp: 0.9 },
  after: { grav: 9, swirl: 2.5, blow: 14, wob: 0.8, damp: 1.0 },
}
function lerpPar(a, b, k) {
  const o = {}
  for (const key in a) o[key] = a[key] + (b[key] - a[key]) * k
  return o
}

function applyWinnerLook(ball) {
  const m = ball.material
  m.map = numberTexture(playTicket, t.value.accent)
  m.emissive.set(t.value.accent)
  m.emissiveIntensity = 0.04
  m.needsUpdate = true
}
function restoreCarrier() {
  if (!carrier) return
  const u = carrier.userData
  carrier.material.map = u.origMap
  carrier.material.emissiveIntensity = 0
  carrier.material.needsUpdate = true
  carrier.scale.setScalar(1)
  u.mode = 'pool'; u.v.set(0, 2, 0)
  carrier.position.set(GX + (cosRand() - 0.5), GY - 1.2, (cosRand() - 0.5))
  carrier = null
  if (ballHalo) ballHalo.visible = false
}

function setPhase(p) {
  ph = p
  phStart = performance.now()
  phase.value = p
}

function resize() {
  if (!renderer || !stageEl.value) return
  const w = Math.max(1, stageEl.value.clientWidth), h = Math.max(1, stageEl.value.clientHeight)
  renderer.setSize(w, h, false)
  composer?.setSize(w, h)
  const na = w / h
  const aspectChanged = Math.abs(na - aspect) > 0.15
  aspect = na
  camera.aspect = na
  const margin = props.compact ? 0.04 : 0.1
  const tanH = Math.tan((camera.fov * Math.PI) / 360)
  const halfH = 3.5 * (1 + margin), halfW = 4.6 * (1 + margin)
  camDist = Math.max(halfH / tanH, halfW / (tanH * na)) + 1.6
  camera.updateProjectionMatrix()
  if (aspectChanged && scene) { scene.background?.dispose?.(); scene.background = makeBgTexture(t.value) }
}

function updateCamera(tt) {
  const slow = reduced ? 0 : 1
  const ang = Math.sin(tt * 0.13) * 0.09 * slow
  const dist = camDist * (1 + Math.sin(tt * 0.09) * 0.015 * slow - camBlend * 0.04)
  const tx = SCENE_CX + camBlend * 0.5, ty = SCENE_CY + camBlend * 0.1
  camera.position.set(tx + Math.sin(ang) * dist, ty + 1.15 + Math.sin(tt * 0.11) * 0.08 * slow, Math.cos(ang) * dist)
  camera.lookAt(tx, ty - 0.1, 0)
}

const _col = { v: null }
function frame(now) {
  raf = 0
  if (unmounted || !renderer || !scene) return
  let dt = lastNow ? Math.min((now - lastNow) / 1000, 1 / 20) : 1 / 60
  lastNow = now
  simClock += dt
  const tt = simClock
  const elapsed = (now - phStart) / 1000

  // 단계 전이
  if (ph === 'mix' && elapsed > DUR.mix) {
    setPhase('select')
    let best = null, bd = 1e9
    for (const b of balls) { if (b.userData.mode !== 'pool') continue; const d = Math.hypot(b.position.x - PIPE_A.x, b.position.y - PIPE_A.y, b.position.z); if (d < bd) { bd = d; best = b } }
    carrier = best
    applyWinnerLook(carrier)
    carrier.userData.mode = 'rise'
    carrier.userData.from = carrier.position.clone()
  } else if (ph === 'select' && elapsed > DUR.select) {
    setPhase('eject')
    carrier.userData.mode = 'pipe'
  }

  // 풀 시뮬레이션 파라미터
  let par
  if (ph === 'mix') par = lerpPar(PAR.select, PAR.mix, smooth(elapsed / 0.8))
  else if (ph === 'select') par = lerpPar(PAR.mix, PAR.select, smooth(elapsed / DUR.select))
  else if (ph === 'eject' || ph === 'reveal') par = PAR.after
  else par = hasResult.value ? PAR.idleResult : PAR.idleWait
  const sub = 2
  for (let i = 0; i < sub; i++) stepBalls(dt / sub, tt + i * 0.001, par)

  // 당첨 공 연출
  const cu = carrier?.userData
  let haloK = 0
  if (carrier && cu.mode === 'rise') {
    const k = smooth(elapsed / DUR.select)
    const f = cu.from
    carrier.position.set(
      f.x + (PIPE_A.x - f.x) * k + Math.sin(tt * 9) * (1 - k) * 0.12,
      f.y + (PIPE_A.y - f.y) * k + Math.sin(k * Math.PI) * 0.35,
      f.z + (PIPE_A.z - f.z) * k)
    carrier.rotation.y += dt * 6; carrier.rotation.x += dt * 3
    carrier.scale.setScalar(1 + 0.12 * k)
    carrier.material.emissiveIntensity = 0.06 + 0.12 * Math.abs(Math.sin(elapsed * 7))
    haloK = 0.5 + 0.5 * k
  } else if (carrier && cu.mode === 'pipe') {
    const pd = DUR.eject * 0.64
    const k = clamp01(elapsed / pd)
    const e = smooth(k) * 0.6 + k * 0.4
    carrier.position.copy(tubePath.getPointAt(e))
    carrier.rotation.x += dt * 4; carrier.rotation.z += dt * 2.5
    carrier.scale.setScalar(1.12)
    carrier.material.emissiveIntensity = 0.15
    haloK = 0.7
    if (k >= 1) { cu.mode = 'drop'; dropState = { vy: -0.4, t: 0, bounces: 0 } }
  } else if (carrier && cu.mode === 'drop') {
    const ds = dropState
    ds.t += dt
    const sc = 1.12 + (FINAL_R / BR - 1.12) * smooth(ds.t / 0.7)
    carrier.scale.setScalar(sc)
    ds.vy -= 17 * dt
    carrier.position.y += ds.vy * dt
    carrier.position.x = CX; carrier.position.z = CZ
    carrier.rotation.x += dt * 2; carrier.rotation.z += dt * 1.5
    const restY = CH_FLOOR + BR * sc
    if (carrier.position.y <= restY) {
      carrier.position.y = restY
      if (ds.bounces < 2 && Math.abs(ds.vy) > 1.2) { ds.vy = -ds.vy * 0.34; ds.bounces++ } else { ds.vy = 0; cu.mode = 'settle'; dropState = null; restedAt = tt }
    }
    haloK = 0.9
  } else if (carrier && cu.mode === 'settle') {
    // 정면으로 회전하며 안착
    const k = smooth((tt - restedAt) / 0.7)
    carrier.quaternion.slerp(new THREE.Quaternion(), 0.12 + 0.2 * k)
    carrier.position.y = CH_FLOOR + FINAL_R
    carrier.scale.setScalar(FINAL_R / BR)
    haloK = 1
    if (tt - restedAt > 0.75) {
      cu.mode = 'rest'
      setPhase('reveal'); played.value = true
      revealAt = tt
      emitSparkles(60, CX, CH_FLOOR + FINAL_R, CZ + 0.4)
      emit('finished')
    }
  } else if (carrier && cu.mode === 'rest') {
    carrier.rotation.set(0, Math.sin(tt * 0.8) * 0.28, 0)
    haloK = 1
  }
  if (ph === 'reveal' && carrier) {
    const age = tt - revealAt
    emitAcc += dt
    if (age < 8 && emitAcc > 0.22) { emitAcc = 0; emitSparkles(2, CX, CH_FLOOR + FINAL_R, CZ + 0.4) }
    carrier.material.emissiveIntensity = 0.02
  }

  // 후광/조명
  if (ballHalo) {
    ballHalo.visible = !!carrier && haloK > 0
    if (ballHalo.visible) {
      ballHalo.position.copy(carrier.position)
      const rest = cu.mode === 'rest' || cu.mode === 'settle'
      const s = rest ? 3.0 + 0.25 * Math.sin(tt * 3) : 1.9
      ballHalo.scale.set(s, s, 1)
      ballHalo.material.opacity = (rest ? 0.14 : 0.35) * haloK
    }
  }
  const revealed = ph === 'reveal'
  winLight.intensity += ((revealed ? 10 : 0) - winLight.intensity) * Math.min(1, dt * 4)
  accentLight.intensity = ph === 'select' ? 30 + 30 * Math.abs(Math.sin(elapsed * 6)) : 30
  camBlend += ((revealed ? 1 : 0) - camBlend) * Math.min(1, dt * 1.6)

  // 보케 + 반짝이
  for (const s of bokeh) {
    const u = s.userData
    s.position.x = u.bx + Math.sin(tt * u.sp + u.ph) * u.amp * 2
    s.position.y = u.by + Math.cos(tt * u.sp * 0.8 + u.ph) * u.amp
  }
  updateSparkle(dt)
  updateCamera(tt)

  if (reflector) reflector.visible = useReflector
  if (useComposer && composer) composer.render(dt)
  else renderer.render(scene, camera)

  // 성능 감시: 느리면 후처리/반사 해제
  if (inView && perfFrames++ > 40) {
    perfEma = perfEma * 0.95 + (dt * 1000) * 0.05
    if (perfEma > 34 && (useComposer || useReflector) && perfFrames > 140) {
      useComposer = false; useReflector = false
      if (reflector) reflector.visible = false
      perfFrames = 0; perfEma = 16
    }
  }
  schedule()
}

function schedule() {
  if (raf || unmounted || !renderer) return
  if (!inView || !tabVisible) return
  raf = requestAnimationFrame(frame)
}

function updateVisibility() { if (inView && tabVisible) { lastNow = 0; schedule() } }
function onVisibility() { tabVisible = !document.hidden; updateVisibility() }

async function play() {
  if (!canPlay.value) return
  playTicket = Number(props.winningTicket)
  restoreCarrier()
  played.value = false
  setPhase('mix')
  schedule()
}

function reset() {
  if (ph === 'mix' || ph === 'select' || ph === 'eject') return
  restoreCarrier()
  played.value = false
  setPhase('idle')
  schedule()
}

async function init() {
  try {
    THREE = await import('three')
  } catch (e) {
    loading.value = false
    glError.value = '3D 추첨기를 불러오지 못했습니다.'
    return
  }
  if (unmounted) return
  // 선택적 확장 모듈 (실패하면 기본 렌더로 대체)
  try {
    const [env, ec, rp, ub, op, rf] = await Promise.all([
      import('three/examples/jsm/environments/RoomEnvironment.js'),
      import('three/examples/jsm/postprocessing/EffectComposer.js'),
      import('three/examples/jsm/postprocessing/RenderPass.js'),
      import('three/examples/jsm/postprocessing/UnrealBloomPass.js'),
      import('three/examples/jsm/postprocessing/OutputPass.js'),
      import('three/examples/jsm/objects/Reflector.js'),
    ])
    X = { RoomEnvironment: env.RoomEnvironment, EffectComposer: ec.EffectComposer, RenderPass: rp.RenderPass, UnrealBloomPass: ub.UnrealBloomPass, OutputPass: op.OutputPass, Reflector: rf.Reflector }
  } catch (e) { X = {} }
  if (unmounted) return
  try {
    renderer = new THREE.WebGLRenderer({ canvas: canvasEl.value, antialias: true, alpha: true, powerPreference: 'high-performance' })
  } catch (e) {
    loading.value = false
    glError.value = '이 기기에서는 3D 추첨 화면을 표시할 수 없습니다.'
    return
  }
  const caps = renderer.capabilities
  const coarse = !!window.matchMedia?.('(pointer: coarse)').matches
  const cores = navigator.hardwareConcurrency || 4, mem = navigator.deviceMemory || 4
  const strong = !(coarse && (cores < 6 || mem < 4))
  const gl2 = !!caps.isWebGL2
  useComposer = !!X.EffectComposer && !props.compact && !reduced && gl2 && caps.maxTextureSize >= 4096 && !(coarse && (cores < 4 || mem < 3))
  useReflector = !!X.Reflector && !props.compact && !reduced && gl2 && strong && caps.maxTextureSize >= 8192
  const pr = Math.min(window.devicePixelRatio || 1, strong ? 2 : 1.5)
  renderer.setPixelRatio(pr)
  renderer.setClearColor(0x000000, 1)
  renderer.outputColorSpace = THREE.SRGBColorSpace
  renderer.toneMapping = THREE.ACESFilmicToneMapping
  renderer.toneMappingExposure = 1.05
  camera = new THREE.PerspectiveCamera(30, 1, 0.1, 120)

  if (X.RoomEnvironment) {
    try {
      const pm = new THREE.PMREMGenerator(renderer)
      const room = new X.RoomEnvironment()
      envTex = pm.fromScene(room, 0.04).texture
      room.traverse?.((o) => { o.geometry?.dispose?.(); o.material?.dispose?.() })
      pm.dispose()
    } catch (e) { envTex = null }
  }

  const w0 = Math.max(1, stageEl.value.clientWidth), h0 = Math.max(1, stageEl.value.clientHeight)
  aspect = w0 / h0
  buildScene()
  if (useComposer) {
    try {
      const rt = new THREE.WebGLRenderTarget(w0 * pr, h0 * pr, { type: THREE.HalfFloatType, samples: strong ? 4 : 2 })
      composer = new X.EffectComposer(renderer, rt)
      composer.setPixelRatio(pr)
      renderPass = new X.RenderPass(scene, camera)
      bloomPass = new X.UnrealBloomPass(new THREE.Vector2(w0, h0), 0.42, 0.6, 0.92)
      composer.addPass(renderPass); composer.addPass(bloomPass); composer.addPass(new X.OutputPass())
    } catch (e) { composer = null; useComposer = false }
  }
  resize()
  loading.value = false
  ro = new ResizeObserver(() => { resize(); schedule() })
  ro.observe(stageEl.value)
  if ('IntersectionObserver' in window) {
    io = new IntersectionObserver((es) => { inView = es[0]?.isIntersecting !== false; updateVisibility() }, { threshold: 0.05 })
    io.observe(stageEl.value)
  }
  document.addEventListener('visibilitychange', onVisibility)
  phStart = performance.now()
  schedule()
  if (props.autoplay && hasResult.value) play()
}

onMounted(init)

watch([themeKey, () => ballCount(), () => props.ticketCount], () => {
  if (!renderer || !THREE) return
  if (ph === 'mix' || ph === 'select' || ph === 'eject') return
  buildScene()
  schedule()
})
watch(() => props.winningTicket, (v, old) => {
  if (old == null && v != null && props.autoplay && renderer) play()
})

onBeforeUnmount(() => {
  unmounted = true
  if (raf) cancelAnimationFrame(raf)
  raf = 0
  document.removeEventListener('visibilitychange', onVisibility)
  ro?.disconnect(); io?.disconnect()
  disposeScene()
  try { bloomPass?.dispose?.(); composer?.dispose?.() } catch (e) { /* ignore */ }
  composer = null; bloomPass = null; renderPass = null
  envTex?.dispose?.(); envTex = null
  if (renderer) { renderer.dispose(); renderer.forceContextLoss?.(); renderer = null }
})

defineExpose({ play, reset })
</script>

<style scoped>
.stage { overflow: hidden; touch-action: pan-y; }
.stage-normal { height: 400px; }
.stage-compact { height: 280px; }
@media (min-width: 640px) {
  .stage-normal { height: 500px; }
  .stage-compact { height: 320px; }
}
.bg-layer { opacity: 0.35; mix-blend-mode: screen; }
.glass-chip {
  background: linear-gradient(180deg, rgba(255,255,255,.12), rgba(255,255,255,.03)), rgba(8, 12, 28, .6);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid rgba(255,255,255,.16);
  box-shadow: inset 0 1px 0 rgba(255,255,255,.25), 0 6px 22px rgba(0,0,0,.4);
}
.pulse-dot { animation: lsPulse 1s ease-in-out infinite; }
@keyframes lsPulse { 50% { opacity: .35; transform: scale(.7); } }
.reveal-card {
  transform: translateX(-50%);
  padding: 8px 14px 10px;
  border-radius: 20px;
  border: 1px solid;
  background: linear-gradient(180deg, rgba(255,255,255,.14), rgba(255,255,255,.02)), rgba(7, 11, 26, .74);
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
}
.laurel { width: 30px; height: 60px; filter: drop-shadow(0 0 6px rgba(255,200,60,.5)); }
@media (min-width: 640px) { .laurel { width: 38px; height: 76px; } }
.win-number { letter-spacing: 0.06em; }
.gold-btn { transition: transform .15s, filter .15s; border: 1px solid rgba(255,255,255,.25); }
.gold-btn:not(:disabled):hover { filter: brightness(1.08); transform: translateY(-1px); }
.gold-btn:not(:disabled):active { transform: translateY(1px); }
.info-bar { border-top: 1px solid rgba(255,255,255,.08); }
.pop-enter-active { transition: all 0.5s cubic-bezier(0.2, 1.2, 0.4, 1); }
.pop-enter-from { opacity: 0; transform: translate(-50%, 18px) scale(0.85); }
</style>
