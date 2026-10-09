<template>
  <div class="space-y-5">
    <!-- 프리셋 -->
    <div>
      <div class="text-xs font-bold text-ink-muted mb-2">추천 테마 (한 번에 적용)</div>
      <div class="flex flex-wrap gap-2">
        <button v-for="p in PRESETS" :key="p.key" type="button" @click="applyPreset(p)"
          class="flex items-center gap-2 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:border-amber-400 text-xs font-bold text-ink">
          <span class="w-8 h-5 rounded-md border border-gray-200" :style="{ background: `linear-gradient(${p.theme.bg_top}, ${p.theme.bg_bottom})` }"></span>
          <span class="w-3 h-3 rounded-full" :style="{ background: p.theme.accent }"></span>
          {{ p.label }}
        </button>
      </div>
    </div>

    <!-- 색상 -->
    <div>
      <div class="text-xs font-bold text-ink-muted mb-2">색상</div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div v-for="f in COLOR_FIELDS" :key="f.key">
          <label class="input-label">{{ f.label }}</label>
          <div class="flex items-center gap-2">
            <input type="color" :value="colorValue(f.key)" @input="setColor(f.key, $event.target.value)"
              class="w-11 h-10 rounded-lg border border-gray-200 bg-white p-0.5 cursor-pointer shrink-0" :aria-label="f.label" />
            <input type="text" :value="colorValue(f.key)" @change="setColorText(f.key, $event.target.value)" maxlength="7"
              class="input-soft px-3 font-mono" placeholder="#RRGGBB" />
          </div>
        </div>
      </div>
    </div>

    <!-- 공 색 -->
    <div>
      <div class="flex items-center justify-between mb-2">
        <div class="text-xs font-bold text-ink-muted">공 색상 ({{ balls.length }}/8)</div>
        <button type="button" @click="resetBalls" class="text-xs text-ink-faint underline">기본값으로</button>
      </div>
      <div class="flex flex-wrap gap-2 items-center">
        <div v-for="(c, i) in balls" :key="i" class="relative">
          <input type="color" :value="c" @input="setBall(i, $event.target.value)"
            class="w-11 h-11 rounded-full border border-gray-200 bg-white p-0.5 cursor-pointer" :aria-label="`공 색 ${i + 1}`" />
          <button type="button" v-if="balls.length > 1" @click="removeBall(i)"
            class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-gray-700 text-white text-[11px] leading-none flex items-center justify-center" aria-label="이 색 삭제">×</button>
        </div>
        <button type="button" v-if="balls.length < 8" @click="addBall"
          class="w-11 h-11 rounded-full border-2 border-dashed border-gray-300 text-gray-400 text-xl flex items-center justify-center hover:border-amber-400 hover:text-amber-500" aria-label="공 색 추가">+</button>
      </div>
    </div>

    <!-- 이미지 -->
    <div>
      <div class="text-xs font-bold text-ink-muted mb-2">이미지</div>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div v-for="u in UPLOADERS" :key="u.kind" class="border border-gray-100 rounded-xl p-3 bg-white">
          <div class="text-xs font-bold text-ink mb-0.5">{{ u.label }}</div>
          <div class="text-[11px] text-ink-faint mb-2 leading-snug">{{ u.hint }}</div>
          <div class="h-24 rounded-lg border border-gray-100 flex items-center justify-center overflow-hidden"
            :style="{ background: u.kind === 'logo' ? '#334155' : '#f8fafc' }">
            <img v-if="imgUrl(u)" :src="imgUrl(u)" alt="" class="max-h-full max-w-full object-contain" />
            <span v-else class="text-[11px] text-gray-400">{{ u.empty }}</span>
          </div>
          <div class="flex items-center gap-2 mt-2">
            <label class="btn-secondary !px-3 !py-1.5 text-xs cursor-pointer" :class="{ 'opacity-50 pointer-events-none': uploading === u.kind }">
              {{ uploading === u.kind ? '올리는 중...' : '이미지 선택' }}
              <input type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="onFile(u, $event)" />
            </label>
            <button type="button" v-if="theme[u.field]" @click="setField(u.field, '')" class="text-xs text-red-500">제거</button>
          </div>
        </div>
      </div>
      <label class="flex items-center gap-2 mt-3 text-sm text-ink cursor-pointer">
        <input type="checkbox" class="w-4 h-4 accent-amber-500" :checked="theme.show_logo !== false" @change="setField('show_logo', $event.target.checked)" />
        화면에 로고 표시 <span class="text-xs text-ink-faint">(올린 로고가 없으면 사이트 기본 로고)</span>
      </label>
      <p v-if="uploadError" class="text-xs text-red-500 mt-2">{{ uploadError }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
})
const emit = defineEmits(['update:modelValue'])

// 에디터 표시용 기본값 (실제 기본값은 useLotteryTheme.js — 로드되면 그 값을 사용)
const FALLBACK = {
  bg_top: '#0f1b3d', bg_bottom: '#050a1a', accent: '#f5b83d', floor: '#1b2a52', glass_tint: '#bfe3ff',
  ball_colors: ['#ef4444', '#f59e0b', '#22c55e', '#3b82f6', '#a855f7', '#ec4899'],
}
const defaults = ref({ ...FALLBACK })
onMounted(async () => {
  try {
    const m = await import('../../composables/useLotteryTheme.js')
    if (m.DEFAULT_LOTTERY_THEME) defaults.value = { ...FALLBACK, ...m.DEFAULT_LOTTERY_THEME }
  } catch { /* 파일이 아직 없어도 에디터는 동작 */ }
})

const COLOR_FIELDS = [
  { key: 'bg_top', label: '배경 위쪽' },
  { key: 'bg_bottom', label: '배경 아래쪽' },
  { key: 'accent', label: '포인트 색 (글자·장식)' },
  { key: 'floor', label: '바닥' },
  { key: 'glass_tint', label: '유리 색' },
]

// 새 추첨 화면을 추가하려면 상위(AdminSweepstakes)의 DRAW_STYLES 배열만 확장하면 됩니다.
const PRESETS = [
  { key: 'navy', label: '네이비·골드', theme: { bg_top: '#0f1b3d', bg_bottom: '#050a1a', accent: '#f5b83d', floor: '#1b2a52', glass_tint: '#bfe3ff', ball_colors: ['#ef4444', '#f59e0b', '#22c55e', '#3b82f6', '#a855f7', '#ec4899'] } },
  { key: 'awesome', label: '어썸코리안 핑크·오렌지', theme: { bg_top: '#ff7a59', bg_bottom: '#c2185b', accent: '#fff3c4', floor: '#8e1b4a', glass_tint: '#ffe0ec', ball_colors: ['#ffffff', '#ffd54f', '#ff8a65', '#f06292', '#ba68c8', '#4dd0e1'] } },
  { key: 'mint', label: '민트 스튜디오', theme: { bg_top: '#c9f4ea', bg_bottom: '#6fcfc0', accent: '#0f766e', floor: '#a7e3d8', glass_tint: '#e6fffa', ball_colors: ['#0f766e', '#f59e0b', '#ef4444', '#3b82f6', '#8b5cf6', '#ffffff'] } },
  { key: 'xmas', label: '크리스마스', theme: { bg_top: '#14532d', bg_bottom: '#052e16', accent: '#fde68a', floor: '#7f1d1d', glass_tint: '#d1fae5', ball_colors: ['#dc2626', '#16a34a', '#ffffff', '#fbbf24', '#dc2626', '#16a34a'] } },
  { key: 'white', label: '심플 화이트', theme: { bg_top: '#ffffff', bg_bottom: '#e5e7eb', accent: '#374151', floor: '#d1d5db', glass_tint: '#f1f5f9', ball_colors: ['#ef4444', '#f59e0b', '#22c55e', '#3b82f6', '#a855f7', '#6b7280'] } },
]

const UPLOADERS = [
  { kind: 'prize', field: 'prize_image_url', label: '상품 이미지', hint: '800×800 png/jpg, 1MB 이하', empty: '없음' },
  { kind: 'background', field: 'background_image_url', label: '배경 이미지', hint: '1920×1080 jpg, 1.5MB 이하', empty: '없음 (색 배경)' },
  { kind: 'logo', field: 'logo_url', label: '로고', hint: '투명 png, 가로 512px 정도', empty: '사이트 기본 로고' },
]

const theme = computed(() => props.modelValue || {})
const balls = computed(() => (Array.isArray(theme.value.ball_colors) && theme.value.ball_colors.length ? theme.value.ball_colors : defaults.value.ball_colors))

function update(patch) { emit('update:modelValue', { ...theme.value, ...patch }) }
function colorValue(k) { return theme.value[k] || defaults.value[k] || '#000000' }
function setColor(k, v) { update({ [k]: v }) }
function setColorText(k, v) {
  let s = String(v).trim()
  if (s && s[0] !== '#') s = '#' + s
  if (/^#[0-9a-fA-F]{6}$/.test(s)) update({ [k]: s.toLowerCase() })
}
function setField(k, v) { update({ [k]: v }) }
function applyPreset(p) { update({ ...p.theme, ball_colors: [...p.theme.ball_colors] }) }

function setBall(i, v) { const a = [...balls.value]; a[i] = v; update({ ball_colors: a }) }
function addBall() { if (balls.value.length < 8) update({ ball_colors: [...balls.value, '#888888'] }) }
function removeBall(i) { if (balls.value.length > 1) update({ ball_colors: balls.value.filter((_, idx) => idx !== i) }) }
function resetBalls() { update({ ball_colors: [...defaults.value.ball_colors] }) }

const uploading = ref('')
const uploadError = ref('')
function imgUrl(u) { return theme.value[u.field] || '' }
async function onFile(u, e) {
  const file = e.target.files?.[0]
  e.target.value = ''
  if (!file) return
  const limit = u.kind === 'background' ? 1.5 : 1
  if (file.size > limit * 1024 * 1024 * 3) { uploadError.value = `파일이 너무 커요. ${limit}MB 이하로 줄여 주세요.`; return }
  uploading.value = u.kind; uploadError.value = ''
  try {
    const fd = new FormData()
    fd.append('image', file)
    fd.append('kind', u.kind)
    const { data } = await axios.post('/api/admin/sweepstakes/upload-image', fd)
    if (data?.url) update({ [u.field]: data.url })
    else uploadError.value = '업로드 결과를 받지 못했어요'
  } catch (err) {
    uploadError.value = err.response?.data?.message || '이미지 업로드에 실패했어요'
  } finally { uploading.value = '' }
}
</script>
