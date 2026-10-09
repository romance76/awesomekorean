<template>
<!--
  프로필 사진 + 회원 등급 링.
  size = 링 이미지 한 변(전체 크기). 링 구멍이 이미지의 약 60% 라서, 사진 지름은 링이 있을 때 size×0.62
  (구멍보다 조금 크게 해서 링 띠 아래로 살짝 겹침), 링이 없을 때는 size×0.74(링의 바깥 지름과 비슷한 존재감).
  링 이미지의 왕관/월계수는 가장자리까지 나오므로 부모에 overflow-hidden 이 없어야 한다.
-->
<span class="relative inline-block flex-shrink-0 align-middle" :style="{ width: size + 'px', height: size + 'px' }">
  <span class="absolute rounded-full overflow-hidden flex items-center justify-center text-white font-bold select-none"
    :class="showImg ? 'bg-gray-100' : 'bg-amber-400'"
    :style="{ width: d + 'px', height: d + 'px', left: (size - d) / 2 + 'px', top: (size - d) / 2 + 'px' }">
    <img v-if="showImg" :src="src" alt="" class="w-full h-full object-cover" loading="lazy" decoding="async" @error="failed = true" />
    <span v-else :style="{ fontSize: Math.round(d * 0.42) + 'px', lineHeight: 1 }">{{ initial }}</span>
  </span>
  <img v-if="ringSrc" :src="ringSrc" alt="" aria-hidden="true" class="absolute inset-0 w-full h-full pointer-events-none select-none" decoding="async" />
</span>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  user: { type: Object, default: null },      // avatar, name/nickname, grade_level 을 가진 사용자 객체
  size: { type: Number, default: 64 },
  level: { type: Number, default: null },     // 명시하면 user.grade_level 대신 사용
  ring: { type: Boolean, default: true },
})

const failed = ref(false)

// 등급 번호: 명시값 → user.grade_level → user.grade.level. 없으면 링을 그리지 않는다(컬럼을 못 받은 목록 등).
const lvl = computed(() => {
  const v = props.level ?? props.user?.grade_level ?? props.user?.grade?.level ?? null
  const n = Number(v)
  return Number.isInteger(n) && n >= 1 && n <= 15 ? n : null
})
// 36px 미만에서는 링이 뭉개지므로 링을 생략
const hasRing = computed(() => props.ring && lvl.value !== null && props.size >= 36)
const d = computed(() => Math.round(props.size * (hasRing.value ? 0.62 : 0.74)))

const ringSrc = computed(() => {
  if (!hasRing.value) return ''
  const nn = String(lvl.value).padStart(2, '0')
  // 256px 사본이면 충분(작은 화면 + 레티나까지). 아주 크게 쓸 때만 원본
  return props.size > 200 ? `/images/grades/level_${nn}.png` : `/images/grades/level_${nn}_s.png`
})

// DB 값은 보통 /storage/avatars/... 형태. 주소 형태가 섞여 있어도 안전하게 정규화
const src = computed(() => {
  const a = props.user?.avatar
  if (!a) return ''
  if (/^(https?:)?\/\//.test(a) || a.startsWith('/') || a.startsWith('data:')) return a
  return '/storage/' + a
})
const showImg = computed(() => !!src.value && !failed.value)
const initial = computed(() => (props.user?.nickname || props.user?.name || '?').trim().slice(0, 1) || '?')

watch(src, () => { failed.value = false })
</script>
