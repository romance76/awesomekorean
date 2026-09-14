<template>
<svg viewBox="0 0 200 150" class="game-thumb-svg" preserveAspectRatio="xMidYMid slice" role="img" :aria-label="slug">
  <defs>
    <linearGradient :id="`gt-${slug}`" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" :stop-color="colors[0]"/><stop offset="100%" :stop-color="colors[1]"/>
    </linearGradient>
  </defs>
  <rect width="200" height="150" :fill="`url(#gt-${slug})`" />
  <g v-html="scene" />
</svg>
</template>

<script setup>
// 게임 카드 썸네일 — 게임별 고유 색상 + 상세 일러스트로 구성된 오리지널 아트.
import { computed } from 'vue'

const props = defineProps({ slug: { type: String, required: true } })

// 게임별 고유 듀오톤 그라데이션 (카테고리 공유 대신 게임마다 다른 색으로 단조로움 방지)
const GRADIENTS = {
  memory: ['#fda4af', '#e11d48'],
  '2048': ['#93c5fd', '#1d4ed8'],
  omok: ['#c4b5fd', '#6d28d9'],
  puzzle: ['#67e8f9', '#0e7490'],
  bingo: ['#f0abfc', '#a21caf'],
  speedcalc: ['#7dd3fc', '#0369a1'],
  seniormemory: ['#f9a8d4', '#be185d'],
  stroop: ['#a5b4fc', '#4338ca'],
  snake: ['#86efac', '#15803d'],
  towerdefense: ['#94a3b8', '#334155'],
  slots: ['#fdba74', '#c2410c'],
  stocksim: ['#34d399', '#059669'],
  wordle: ['#bef264', '#4d7c0f'],
  wordchain: ['#d8b4fe', '#7e22ce'],
  wordblank: ['#5eead4', '#0f766e'],
  spelling: ['#fca5a5', '#b91c1c'],
  typing: ['#818cf8', '#3730a3'],
  wordcard: ['#fde68a', '#b45309'],
  hangul: ['#fbbf24', '#92400e'],
  counting: ['#67e8f9', '#0e7490'],
  colors: ['#f5d0fe', '#a21caf'],
  shapes: ['#99f6e4', '#0d9488'],
  satwords: ['#fed7aa', '#9a3412'],
  proverb: ['#fde68a', '#78350f'],
  flag: ['#93c5fd', '#1e40af'],
  uslife: ['#86efac', '#166534'],
  animals: ['#fdba74', '#9a3412'],
  idiom: ['#e9d5ff', '#6b21a8'],
}
const colors = computed(() => GRADIENTS[props.slug] || ['#c4b5fd', '#6d28d9'])

const W = 'fill="#fff" opacity=".92"'
const W2 = 'fill="#fff" opacity=".65"'

const SCENES = {
  memory: `<circle cx="150" cy="30" r="46" fill="#fff" opacity=".12"/><circle cx="20" cy="115" r="30" fill="#fff" opacity=".10"/>
    <g transform="translate(38,28) rotate(-8)"><rect width="50" height="66" rx="9" fill="#fff"/><rect x="6" y="6" width="38" height="54" rx="5" fill="#fecdd3"/>
      <path d="M14 34 l7 8 15-18" stroke="#e11d48" stroke-width="6" fill="none" stroke-linecap="round" stroke-linejoin="round"/></g>
    <g transform="translate(112,22) rotate(7)"><rect width="50" height="66" rx="9" fill="#fff" opacity=".85"/><rect x="6" y="6" width="38" height="54" rx="5" fill="#fecdd3" opacity=".7"/>
      <path d="M14 34 l7 8 15-18" stroke="#e11d48" stroke-width="6" fill="none" stroke-linecap="round" stroke-linejoin="round" opacity=".55"/></g>
    <circle cx="30" cy="120" r="4" fill="#fff" opacity=".7"/><circle cx="170" cy="120" r="5" fill="#fff" opacity=".5"/>`,
  '2048': `<circle cx="10" cy="10" r="40" fill="#fff" opacity=".1"/>
    <rect x="34" y="26" width="52" height="44" rx="8" fill="#fff"/><rect x="114" y="26" width="52" height="44" rx="8" fill="#bfdbfe"/>
    <rect x="34" y="80" width="52" height="44" rx="8" fill="#bfdbfe"/><rect x="114" y="80" width="52" height="44" rx="8" fill="#fff"/>
    <text x="60" y="56" text-anchor="middle" font-size="24" font-weight="900" fill="#1d4ed8">2</text>
    <text x="140" y="56" text-anchor="middle" font-size="24" font-weight="900" fill="#1e3a8a">4</text>
    <text x="60" y="110" text-anchor="middle" font-size="24" font-weight="900" fill="#1e3a8a">8</text>
    <text x="140" y="110" text-anchor="middle" font-size="24" font-weight="900" fill="#1d4ed8">2</text>`,
  omok: `<circle cx="170" cy="20" r="50" fill="#fff" opacity=".1"/>
    <rect x="34" y="22" width="132" height="106" rx="12" fill="#fff" opacity=".22"/>
    <g stroke="#fff" stroke-width="2.5" opacity=".55">
      <line x1="54" y1="38" x2="54" y2="112"/><line x1="84" y1="38" x2="84" y2="112"/>
      <line x1="114" y1="38" x2="114" y2="112"/><line x1="146" y1="38" x2="146" y2="112"/>
      <line x1="40" y1="54" x2="160" y2="54"/><line x1="40" y1="80" x2="160" y2="80"/><line x1="40" y1="106" x2="160" y2="106"/>
    </g>
    <circle cx="84" cy="54" r="11" fill="#1c1917"/><circle cx="114" cy="80" r="11" fill="#fff"/>
    <circle cx="54" cy="80" r="11" fill="#1c1917"/><circle cx="114" cy="106" r="11" fill="#fff"/>
    <circle cx="146" cy="54" r="11" fill="#1c1917"/>`,
  puzzle: `<circle cx="20" cy="125" r="42" fill="#fff" opacity=".1"/>
    <path d="M50 40h35v14a10 10 0 0 0 20 0V40h35v35h-14a10 10 0 0 0 0 20h14v35H85V116a10 10 0 0 0-20 0v14H50V95h14a10 10 0 0 0 0-20H50z" fill="#fff"/>
    <path d="M50 40h35v14a10 10 0 0 0 20 0V40h35v35h-14a10 10 0 0 0 0 20h14v35H85V116a10 10 0 0 0-20 0v14H50V95h14a10 10 0 0 0 0-20H50z" fill="none" stroke="#0e7490" stroke-width="3" opacity=".25"/>
    <circle cx="170" cy="30" r="6" fill="#fff" opacity=".6"/>`,
  bingo: `<circle cx="170" cy="120" r="44" fill="#fff" opacity=".1"/>
    <rect x="42" y="28" width="116" height="94" rx="12" fill="#fff" opacity=".92"/>
    <line x1="42" y1="59" x2="158" y2="59" stroke="#a21caf" stroke-width="3" opacity=".35"/>
    <line x1="42" y1="90" x2="158" y2="90" stroke="#a21caf" stroke-width="3" opacity=".35"/>
    <line x1="80" y1="28" x2="80" y2="122" stroke="#a21caf" stroke-width="3" opacity=".35"/>
    <line x1="120" y1="28" x2="120" y2="122" stroke="#a21caf" stroke-width="3" opacity=".35"/>
    <circle cx="61" cy="43.5" r="10" fill="#fbbf24"/><circle cx="139" cy="74.5" r="10" fill="#fbbf24"/>
    <circle cx="100" cy="106" r="10" fill="#fbbf24"/><circle cx="139" cy="43.5" r="10" fill="#fbbf24" opacity=".55"/>`,
  speedcalc: `<circle cx="30" cy="30" r="40" fill="#fff" opacity=".1"/>
    <rect x="40" y="45" width="120" height="60" rx="14" fill="#fff" opacity=".9"/>
    <text x="65" y="88" text-anchor="middle" font-size="34" font-weight="900" fill="#0369a1">7</text>
    <text x="100" y="86" text-anchor="middle" font-size="28" font-weight="900" fill="#0891b2" opacity=".7">+</text>
    <text x="135" y="88" text-anchor="middle" font-size="34" font-weight="900" fill="#0369a1">5</text>
    <circle cx="165" cy="40" r="5" fill="#fff" opacity=".6"/>`,
  seniormemory: `<circle cx="160" cy="30" r="40" fill="#fff" opacity=".1"/>
    <rect x="35" y="35" width="58" height="78" rx="12" fill="#fff"/><rect x="107" y="35" width="58" height="78" rx="12" fill="#fff" opacity=".65"/>
    <circle cx="64" cy="74" r="17" fill="#be185d"/><circle cx="136" cy="74" r="17" fill="#be185d" opacity=".5"/>`,
  stroop: `<circle cx="20" cy="20" r="40" fill="#fff" opacity=".1"/>
    <rect x="40" y="40" width="120" height="70" rx="12" fill="#fff" opacity=".92"/>
    <text x="100" y="68" text-anchor="middle" font-size="22" font-weight="900" fill="#dc2626">파랑</text>
    <text x="100" y="98" text-anchor="middle" font-size="22" font-weight="900" fill="#eab308">빨강</text>`,
  snake: `<circle cx="20" cy="120" r="46" fill="#fff" opacity=".12"/>
    <path d="M32 108 h22 v-24 h22 v-24 h24 v24 h22 v24 h22" stroke="#fff" stroke-width="18" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
    <circle cx="32" cy="108" r="11" fill="#052e16"/><circle cx="26" cy="104" r="2.2" fill="#fff"/>
    <circle cx="150" cy="34" r="9" fill="#fde047"/><circle cx="150" cy="34" r="9" fill="none" stroke="#fff" stroke-width="2" opacity=".6"/>`,
  towerdefense: `<circle cx="160" cy="110" r="50" fill="#fff" opacity=".12"/>
    <rect x="30" y="112" width="140" height="10" rx="3" fill="#0c4a6e" opacity=".2"/>
    <rect x="78" y="58" width="44" height="56" rx="4" fill="#fff"/>
    <path d="M78 58 v-10 h44 v10 M84 48 v-8 M100 48 v-10 M116 48 v-8" stroke="#fff" stroke-width="6" fill="none" stroke-linejoin="round"/>
    <rect x="92" y="78" width="16" height="20" rx="2" fill="#334155"/><circle cx="100" cy="70" r="4" fill="#334155"/>
    <path d="M60 112 l10-22 M140 112 l-10-22" stroke="#fff" stroke-width="6" stroke-linecap="round" opacity=".7"/>
    <circle cx="46" cy="96" r="5" fill="#fde68a"/><circle cx="154" cy="90" r="4" fill="#fde68a"/>`,
  slots: `<circle cx="170" cy="130" r="40" fill="#fff" opacity=".12"/>
    <rect x="36" y="30" width="128" height="90" rx="14" fill="#fff" opacity=".9"/>
    <rect x="48" y="42" width="30" height="60" rx="7" fill="#ffedd5"/><rect x="85" y="42" width="30" height="60" rx="7" fill="#ffedd5"/><rect x="122" y="42" width="30" height="60" rx="7" fill="#ffedd5"/>
    <text x="63" y="86" text-anchor="middle" font-size="28" font-weight="900" fill="#ea580c">7</text>
    <text x="100" y="84" text-anchor="middle" font-size="24" fill="#facc15">★</text>
    <text x="137" y="86" text-anchor="middle" font-size="28" font-weight="900" fill="#ea580c">7</text>
    <circle cx="36" cy="52" r="6" fill="#fff"/><circle cx="164" cy="100" r="5" fill="#fff" opacity=".7"/>`,
  stocksim: `<circle cx="170" cy="30" r="40" fill="#fff" opacity=".1"/>
    <polyline points="35,110 70,85 100,98 130,55 165,42" stroke="#fff" stroke-width="7" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
    <polygon points="165,42 150,46 161,55" fill="#fff"/>
    <circle cx="60" cy="100" r="4" fill="#fff" opacity=".7"/><circle cx="120" cy="65" r="4" fill="#fff" opacity=".7"/>
    <circle cx="40" cy="35" r="14" fill="#fde047"/><text x="40" y="40" text-anchor="middle" font-size="14" font-weight="900" fill="#78350f">$</text>`,
  wordle: `<circle cx="30" cy="20" r="40" fill="#fff" opacity=".1"/>
    <rect x="44" y="30" width="28" height="28" rx="5" fill="#fff"/><rect x="78" y="30" width="28" height="28" rx="5" fill="#fde047"/><rect x="112" y="30" width="28" height="28" rx="5" fill="#fff" opacity=".55"/>
    <rect x="44" y="64" width="28" height="28" rx="5" fill="#fff" opacity=".55"/><rect x="78" y="64" width="28" height="28" rx="5" fill="#fff"/><rect x="112" y="64" width="28" height="28" rx="5" fill="#fde047"/><rect x="146" y="64" width="28" height="28" rx="5" fill="#fff" opacity=".55"/>
    <rect x="44" y="98" width="28" height="28" rx="5" fill="#fff" opacity=".55"/><rect x="78" y="98" width="28" height="28" rx="5" fill="#fff" opacity=".55"/><rect x="112" y="98" width="28" height="28" rx="5" fill="#fff" opacity=".55"/><rect x="146" y="98" width="28" height="28" rx="5" fill="#fff"/>
    <text x="160" y="86" text-anchor="middle" font-size="18" font-weight="900" fill="#4d7c0f">A</text>`,
  wordchain: `<circle cx="30" cy="120" r="40" fill="#fff" opacity=".1"/>
    <circle cx="62" cy="75" r="24" fill="none" stroke="#fff" stroke-width="9"/><circle cx="100" cy="75" r="24" fill="none" stroke="#fff" stroke-width="9" opacity=".85"/><circle cx="138" cy="75" r="24" fill="none" stroke="#fff" stroke-width="9" opacity=".6"/>
    <text x="62" y="82" text-anchor="middle" font-size="16" font-weight="900" fill="#fff">사</text>
    <text x="100" y="82" text-anchor="middle" font-size="16" font-weight="900" fill="#fff" opacity=".85">과</text>
    <text x="138" y="82" text-anchor="middle" font-size="16" font-weight="900" fill="#fff" opacity=".6">일</text>`,
  wordblank: `<circle cx="170" cy="120" r="42" fill="#fff" opacity=".1"/>
    <rect x="35" y="45" width="130" height="16" rx="6" fill="#fff" opacity=".9"/>
    <rect x="35" y="70" width="60" height="16" rx="6" fill="#fff" opacity=".55"/>
    <rect x="100" y="68" width="46" height="20" rx="5" fill="none" stroke="#fff" stroke-width="3" stroke-dasharray="6 5"/>
    <rect x="35" y="98" width="36" height="22" rx="6" fill="#fde047"/><rect x="78" y="98" width="36" height="22" rx="6" fill="#fff" opacity=".7"/>`,
  spelling: `<circle cx="30" cy="30" r="40" fill="#fff" opacity=".1"/>
    <rect x="45" y="35" width="80" height="90" rx="6" fill="#fff" opacity=".9"/>
    <line x1="58" y1="55" x2="112" y2="55" stroke="#b91c1c" stroke-width="4" opacity=".3"/><line x1="58" y1="70" x2="112" y2="70" stroke="#b91c1c" stroke-width="4" opacity=".3"/>
    <path d="M95 105 L145 55 158 68 108 118 Z" fill="#fff"/><path d="M145 55 L158 42 171 55 158 68Z" fill="#fde047"/><path d="M95 105 L88 122 105 115Z" fill="#fde047"/>`,
  typing: `<circle cx="170" cy="20" r="40" fill="#fff" opacity=".1"/>
    <rect x="32" y="52" width="136" height="58" rx="10" fill="#fff" opacity=".92"/>
    <rect x="42" y="61" width="15" height="15" rx="3" fill="#3730a3" opacity=".4"/><rect x="61" y="61" width="15" height="15" rx="3" fill="#3730a3" opacity=".4"/>
    <rect x="80" y="61" width="15" height="15" rx="3" fill="#3730a3" opacity=".4"/><rect x="99" y="61" width="15" height="15" rx="3" fill="#3730a3" opacity=".4"/>
    <rect x="118" y="61" width="15" height="15" rx="3" fill="#3730a3" opacity=".4"/><rect x="137" y="61" width="15" height="15" rx="3" fill="#3730a3" opacity=".4"/>
    <rect x="52" y="84" width="96" height="15" rx="6" fill="#3730a3" opacity=".55"/>`,
  wordcard: `<circle cx="20" cy="120" r="40" fill="#fff" opacity=".1"/>
    <rect x="48" y="38" width="66" height="82" rx="10" fill="#fff" opacity=".6"/><rect x="86" y="48" width="66" height="82" rx="10" fill="#fff"/>
    <rect x="100" y="62" width="38" height="26" rx="4" fill="#b45309" opacity=".35"/><line x1="100" y1="100" x2="138" y2="100" stroke="#b45309" stroke-width="4" opacity=".5"/>`,
  hangul: `<circle cx="150" cy="120" r="44" fill="#fff" opacity=".12"/><circle cx="100" cy="75" r="52" fill="#fff" opacity=".18"/>
    <text x="100" y="98" text-anchor="middle" font-size="62" font-weight="900" fill="#fff">가</text>
    <circle cx="34" cy="30" r="5" fill="#fff" opacity=".7"/><circle cx="168" cy="34" r="4" fill="#fff" opacity=".6"/>`,
  counting: `<circle cx="170" cy="120" r="40" fill="#fff" opacity=".1"/>
    <circle cx="58" cy="75" r="26" fill="#fff"/><circle cx="100" cy="75" r="26" fill="#fff" opacity=".75"/><circle cx="142" cy="75" r="26" fill="#fff"/>
    <text x="58" y="84" text-anchor="middle" font-size="24" font-weight="900" fill="#0891b2">1</text>
    <text x="100" y="84" text-anchor="middle" font-size="24" font-weight="900" fill="#0891b2">2</text>
    <text x="142" y="84" text-anchor="middle" font-size="24" font-weight="900" fill="#0891b2">3</text>`,
  colors: `<circle cx="20" cy="20" r="40" fill="#fff" opacity=".1"/>
    <circle cx="66" cy="58" r="24" fill="#ef4444"/><circle cx="112" cy="50" r="24" fill="#3b82f6"/><circle cx="142" cy="92" r="24" fill="#facc15"/><circle cx="80" cy="100" r="24" fill="#22c55e"/>
    <circle cx="66" cy="58" r="24" fill="none" stroke="#fff" stroke-width="3" opacity=".5"/><circle cx="112" cy="50" r="24" fill="none" stroke="#fff" stroke-width="3" opacity=".5"/>`,
  shapes: `<circle cx="30" cy="30" r="40" fill="#fff" opacity=".1"/>
    <circle cx="55" cy="80" r="24" fill="#fff"/><rect x="90" y="56" width="46" height="46" rx="6" fill="#fff" opacity=".8"/><polygon points="162,54 182,98 142,98" fill="#fff" opacity=".92"/>`,
  satwords: `<circle cx="170" cy="30" r="40" fill="#fff" opacity=".1"/>
    <rect x="55" y="30" width="90" height="94" rx="8" fill="#fff"/>
    <line x1="70" y1="52" x2="130" y2="52" stroke="#9a3412" stroke-width="4" opacity=".35"/><line x1="70" y1="68" x2="130" y2="68" stroke="#9a3412" stroke-width="4" opacity=".35"/><line x1="70" y1="84" x2="112" y2="84" stroke="#9a3412" stroke-width="4" opacity=".35"/>
    <rect x="70" y="98" width="30" height="10" rx="3" fill="#fde047"/>`,
  proverb: `<circle cx="20" cy="120" r="40" fill="#fff" opacity=".1"/>
    <rect x="55" y="35" width="90" height="82" rx="4" fill="#fff"/>
    <circle cx="55" cy="45" r="10" fill="#fff"/><circle cx="55" cy="107" r="10" fill="#fff"/><circle cx="145" cy="45" r="10" fill="#fff"/><circle cx="145" cy="107" r="10" fill="#fff"/>
    <path d="M70 62 q30 -10 60 0" stroke="#78350f" stroke-width="4" fill="none" stroke-linecap="round" opacity=".4"/>
    <path d="M70 82 q30 10 60 0" stroke="#78350f" stroke-width="4" fill="none" stroke-linecap="round" opacity=".4"/>
    <path d="M70 100 q30 -6 60 0" stroke="#78350f" stroke-width="4" fill="none" stroke-linecap="round" opacity=".4"/>`,
  flag: `<circle cx="170" cy="120" r="40" fill="#fff" opacity=".1"/>
    <line x1="60" y1="28" x2="60" y2="124" stroke="#fff" stroke-width="7" stroke-linecap="round"/>
    <path d="M60 36 h78 l-16 22 16 22 h-78 Z" fill="#fff" opacity=".92"/><circle cx="60" cy="124" r="6" fill="#fff" opacity=".7"/>`,
  uslife: `<circle cx="170" cy="30" r="40" fill="#fff" opacity=".1"/>
    <path d="M100 28c-26 0-45 19-45 43 0 32 45 59 45 59s45-27 45-59c0-24-19-43-45-43z" fill="#fff"/><circle cx="100" cy="71" r="16" fill="#166534"/>`,
  animals: `<circle cx="20" cy="30" r="40" fill="#fff" opacity=".1"/>
    <circle cx="100" cy="82" r="36" fill="#fff"/><polygon points="70,55 58,22 88,48" fill="#fff"/><polygon points="130,55 142,22 112,48" fill="#fff"/>
    <circle cx="86" cy="80" r="5" fill="#9a3412"/><circle cx="114" cy="80" r="5" fill="#9a3412"/>
    <path d="M88 96q12 10 24 0" stroke="#9a3412" stroke-width="3.5" fill="none" stroke-linecap="round"/>`,
  idiom: `<circle cx="170" cy="120" r="42" fill="#fff" opacity=".1"/>
    <path d="M40 40h120a8 8 0 0 1 8 8v50a8 8 0 0 1-8 8H90l-20 20v-20H40a8 8 0 0 1-8-8V48a8 8 0 0 1 8-8z" fill="#fff" opacity=".92"/>
    <path d="M55 62 q35 -10 70 0" stroke="#6b21a8" stroke-width="4" fill="none" stroke-linecap="round" opacity=".4"/>
    <path d="M55 82 q35 10 55 0" stroke="#6b21a8" stroke-width="4" fill="none" stroke-linecap="round" opacity=".4"/>`,
}

const scene = computed(() => SCENES[props.slug] || `<circle cx="100" cy="75" r="30" ${W}/>`)
</script>

<style scoped>
.game-thumb-svg { width: 100%; height: 100%; display: block; }
</style>
