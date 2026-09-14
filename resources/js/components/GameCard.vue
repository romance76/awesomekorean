<template>
  <svg viewBox="0 0 400 300" preserveAspectRatio="xMidYMid slice" class="gc" xmlns="http://www.w3.org/2000/svg">
    <defs>
      <radialGradient :id="`gc-bg-${slug}`" cx="30%" cy="0%" r="120%">
        <stop offset="0%" :stop-color="art.stops[0]" />
        <stop offset="55%" :stop-color="art.stops[1]" />
        <stop offset="100%" :stop-color="art.stops[2]" />
      </radialGradient>
      <pattern :id="`gc-pat-${slug}`" width="44" height="44" patternUnits="userSpaceOnUse" patternTransform="rotate(15)">
        <path d="M22 0 L44 22 L22 44 L0 22 Z" fill="none" :stroke="art.pattern" stroke-width="1.5" />
      </pattern>
      <filter :id="`gc-ds-${slug}`" x="-60%" y="-60%" width="220%" height="220%">
        <feDropShadow dx="0" dy="6" stdDeviation="5" flood-color="#000" flood-opacity="0.4" />
      </filter>
      <radialGradient :id="`gc-vig-${slug}`" cx="50%" cy="50%" r="75%">
        <stop offset="60%" stop-color="#000" stop-opacity="0" />
        <stop offset="100%" stop-color="#000" stop-opacity="0.35" />
      </radialGradient>
    </defs>

    <rect width="400" height="300" :fill="`url(#gc-bg-${slug})`" />
    <rect width="400" height="300" :fill="`url(#gc-pat-${slug})`" opacity="0.12" />
    <circle cx="340" cy="-10" r="130" fill="#ffffff" opacity="0.10" />
    <rect width="400" height="300" :fill="`url(#gc-vig-${slug})`" />

    <g :filter="`url(#gc-ds-${slug})`" v-html="art.illustration"></g>

    <foreignObject x="12" y="10" width="220" height="28">
      <div xmlns="http://www.w3.org/1999/xhtml" class="gc-badge">
        <span class="gc-badge-dot">A</span>AwesomeKorean
      </div>
    </foreignObject>

    <foreignObject x="0" y="6" width="386" height="150">
      <div xmlns="http://www.w3.org/1999/xhtml" class="gc-title" :style="titleStyle">
        <span v-for="(line, i) in titleLines" :key="i">{{ line }}</span>
      </div>
    </foreignObject>

    <foreignObject x="0" y="254" width="400" height="36">
      <div xmlns="http://www.w3.org/1999/xhtml" class="gc-subtitle">{{ subtitle }}</div>
    </foreignObject>
  </svg>
</template>

<script setup>
import { computed } from 'vue'
import { CARD_ART, DEFAULT_ART } from '../data/gameCardArt'

const props = defineProps({
  slug: { type: String, required: true },
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
})

const art = computed(() => CARD_ART[props.slug] || DEFAULT_ART)
const titleLines = computed(() => (props.title || '').split(' '))

const titleFontSize = computed(() => {
  const len = (props.title || '').replace(/\s/g, '').length
  if (len <= 2) return 58
  if (len <= 4) return 40
  if (len <= 6) return 30
  return 24
})

const titleStyle = computed(() => {
  const [s1, s2, s3] = art.value.shadow
  return {
    color: art.value.text,
    fontSize: `${titleFontSize.value}px`,
    textShadow: `2px 3px 0 ${s1}, 4px 6px 0 ${s2}, 6px 9px 0 ${s3}, 6px 14px 16px rgba(0,0,0,0.5)`,
  }
})
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Gothic+A1:wght@800;900&family=Poppins:wght@600;700;800;900&display=swap');

.gc { display: block; width: 100%; height: 100%; }

.gc-badge {
  display: inline-flex; align-items: center; gap: 5px;
  background: rgba(255,255,255,0.95); border-radius: 999px; padding: 3px 10px 3px 4px;
  font-family: 'Poppins', -apple-system, sans-serif; font-size: 10px; font-weight: 800; color: #1c1917;
  box-shadow: 0 3px 8px rgba(0,0,0,0.25); width: fit-content; white-space: nowrap;
}
.gc-badge-dot {
  width: 15px; height: 15px; border-radius: 50%; flex-shrink: 0;
  background: linear-gradient(135deg,#fb923c,#e11d48);
  display: inline-flex; align-items: center; justify-content: center;
  color: #fff; font-size: 8px; font-weight: 900;
}

.gc-title {
  height: 100%; display: flex; flex-direction: column; align-items: flex-end; justify-content: flex-start;
  font-family: 'Gothic A1', sans-serif; font-weight: 800; line-height: 1.12; text-align: right;
  padding-right: 16px; word-break: keep-all;
}
.gc-title span { display: block; }

.gc-subtitle {
  font-family: 'Poppins', -apple-system, sans-serif; font-size: 12px; font-weight: 800;
  color: rgba(255,255,255,0.9); text-align: center; text-shadow: 0 2px 4px rgba(0,0,0,0.4);
}
</style>
