<template>
<svg viewBox="0 0 200 150" class="game-thumb-svg" preserveAspectRatio="xMidYMid slice" role="img" :aria-label="slug">
  <defs>
    <linearGradient id="gt-card" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#fb7185"/><stop offset="100%" stop-color="#e11d48"/>
    </linearGradient>
    <linearGradient id="gt-brain" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#a78bfa"/><stop offset="100%" stop-color="#7c3aed"/>
    </linearGradient>
    <linearGradient id="gt-arcade" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#60a5fa"/><stop offset="100%" stop-color="#2563eb"/>
    </linearGradient>
    <linearGradient id="gt-word" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#34d399"/><stop offset="100%" stop-color="#059669"/>
    </linearGradient>
    <linearGradient id="gt-education" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#fbbf24"/><stop offset="100%" stop-color="#d97706"/>
    </linearGradient>
  </defs>
  <rect width="200" height="150" :fill="`url(#gt-${category})`" />
  <g v-html="scene" />
</svg>
</template>

<script setup>
// 게임 카드 썸네일 — 실제 스크린샷 대신 사용하는 게임별 오리지널 플랫 일러스트.
// 이모지가 "장난같다"는 피드백에 따라 각 게임 컨셉을 간단한 도형으로 표현.
import { computed } from 'vue'

const props = defineProps({ slug: { type: String, required: true } })

const CATEGORY = {
  memory: 'brain', '2048': 'brain', omok: 'brain', puzzle: 'brain', bingo: 'brain',
  speedcalc: 'brain', seniormemory: 'brain', stroop: 'brain',
  snake: 'arcade', towerdefense: 'arcade', slots: 'arcade', stocksim: 'arcade',
  wordle: 'word', wordchain: 'word', wordblank: 'word', spelling: 'word', typing: 'word', wordcard: 'word',
  hangul: 'education', counting: 'education', colors: 'education', shapes: 'education',
  satwords: 'education', proverb: 'education', flag: 'education', uslife: 'education',
  animals: 'education', idiom: 'education',
}
const category = computed(() => CATEGORY[props.slug] || 'brain')

const W = 'fill="#fff" opacity=".92"'
const W2 = 'fill="#fff" opacity=".65"'

const SCENES = {
  memory: `<rect x="40" y="35" width="45" height="60" rx="8" ${W}/><rect x="115" y="35" width="45" height="60" rx="8" ${W2}/>
    <path d="M55 65 l7 7 13-15" stroke="#7c3aed" stroke-width="5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M130 65 l7 7 13-15" stroke="#7c3aed" stroke-width="5" fill="none" stroke-linecap="round" stroke-linejoin="round" opacity=".5"/>`,
  '2048': `<rect x="35" y="30" width="50" height="42" rx="6" ${W}/><rect x="115" y="30" width="50" height="42" rx="6" ${W2}/>
    <rect x="35" y="78" width="50" height="42" rx="6" ${W2}/><rect x="115" y="78" width="50" height="42" rx="6" ${W}/>
    <text x="60" y="58" text-anchor="middle" font-size="20" font-weight="900" fill="#2563eb">2</text>
    <text x="140" y="58" text-anchor="middle" font-size="20" font-weight="900" fill="#7c3aed">4</text>
    <text x="60" y="106" text-anchor="middle" font-size="20" font-weight="900" fill="#7c3aed">8</text>
    <text x="140" y="106" text-anchor="middle" font-size="20" font-weight="900" fill="#2563eb">2</text>`,
  omok: `<rect x="35" y="25" width="130" height="100" rx="10" fill="#fff" opacity=".25"/>
    <line x1="55" y1="40" x2="55" y2="110" stroke="#fff" stroke-width="2" opacity=".6"/>
    <line x1="85" y1="40" x2="85" y2="110" stroke="#fff" stroke-width="2" opacity=".6"/>
    <line x1="115" y1="40" x2="115" y2="110" stroke="#fff" stroke-width="2" opacity=".6"/>
    <line x1="145" y1="40" x2="145" y2="110" stroke="#fff" stroke-width="2" opacity=".6"/>
    <line x1="40" y1="55" x2="160" y2="55" stroke="#fff" stroke-width="2" opacity=".6"/>
    <line x1="40" y1="80" x2="160" y2="80" stroke="#fff" stroke-width="2" opacity=".6"/>
    <line x1="40" y1="105" x2="160" y2="105" stroke="#fff" stroke-width="2" opacity=".6"/>
    <circle cx="85" cy="55" r="9" fill="#1c1917"/><circle cx="115" cy="80" r="9" fill="#fff"/>
    <circle cx="55" cy="80" r="9" fill="#1c1917"/><circle cx="115" cy="105" r="9" ${W}/>`,
  puzzle: `<path d="M50 40h35v14a10 10 0 0 0 20 0V40h35v35h-14a10 10 0 0 0 0 20h14v35H85V116a10 10 0 0 0-20 0v14H50V95h14a10 10 0 0 0 0-20H50z" ${W}/>`,
  bingo: `<rect x="42" y="30" width="116" height="90" rx="10" ${W2}/>
    <line x1="42" y1="60" x2="158" y2="60" stroke="#7c3aed" stroke-width="3"/>
    <line x1="42" y1="90" x2="158" y2="90" stroke="#7c3aed" stroke-width="3"/>
    <line x1="80" y1="30" x2="80" y2="120" stroke="#7c3aed" stroke-width="3"/>
    <line x1="120" y1="30" x2="120" y2="120" stroke="#7c3aed" stroke-width="3"/>
    <circle cx="61" cy="45" r="9" fill="#fbbf24"/><circle cx="139" cy="75" r="9" fill="#fbbf24"/>
    <circle cx="100" cy="105" r="9" fill="#fbbf24"/>`,
  speedcalc: `<text x="55" y="90" font-size="46" font-weight="900" ${W}>+</text>
    <text x="105" y="90" font-size="46" font-weight="900" fill="#fff" opacity=".5">=</text>
    <text x="148" y="90" font-size="40" font-weight="900" ${W}>7</text>`,
  seniormemory: `<rect x="35" y="40" width="55" height="70" rx="10" ${W}/><rect x="110" y="40" width="55" height="70" rx="10" ${W2}/>
    <circle cx="62.5" cy="75" r="14" fill="#e11d48"/><circle cx="137.5" cy="75" r="14" fill="#e11d48" opacity=".5"/>`,
  snake: `<path d="M40 100 h20 v-20 h20 v-20 h20 v20 h20 v20 h20" stroke="#fff" stroke-width="16" fill="none" stroke-linecap="round" stroke-linejoin="round" opacity=".9"/>
    <circle cx="150" cy="50" r="7" fill="#fbbf24"/>`,
  towerdefense: `<path d="M85 120V70h-8l13-18 13 18h-8v50z" ${W}/>
    <rect x="70" y="120" width="60" height="10" rx="3" ${W2}/>
    <rect x="60" y="55" width="8" height="14" ${W}/><rect x="122" y="55" width="8" height="14" ${W}/>`,
  slots: `<rect x="40" y="35" width="120" height="80" rx="12" fill="#fff" opacity=".2"/>
    <rect x="52" y="47" width="28" height="56" rx="6" ${W}/><rect x="86" y="47" width="28" height="56" rx="6" ${W}/><rect x="120" y="47" width="28" height="56" rx="6" ${W}/>
    <text x="66" y="88" text-anchor="middle" font-size="26" font-weight="900" fill="#ea580c">7</text>
    <text x="100" y="88" text-anchor="middle" font-size="24" fill="#ea580c">★</text>
    <text x="134" y="88" text-anchor="middle" font-size="26" font-weight="900" fill="#ea580c">7</text>`,
  stocksim: `<polyline points="40,105 75,80 105,95 140,50 165,60" stroke="#fff" stroke-width="6" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
    <circle cx="140" cy="50" r="6" fill="#fbbf24"/>`,
  wordle: `<g>
    <rect x="48" y="35" width="26" height="26" rx="4" fill="#10b981"/><rect x="80" y="35" width="26" height="26" rx="4" fill="#fbbf24"/>
    <rect x="112" y="35" width="26" height="26" rx="4" ${W2}/><rect x="144" y="35" width="0" height="26" rx="4" opacity="0"/>
    <rect x="48" y="67" width="26" height="26" rx="4" ${W2}/><rect x="80" y="67" width="26" height="26" rx="4" fill="#10b981"/>
    <rect x="112" y="67" width="26" height="26" rx="4" fill="#fbbf24"/><rect x="144" y="67" width="26" height="26" rx="4" ${W2}/>
    <rect x="48" y="99" width="26" height="26" rx="4" ${W2}/><rect x="80" y="99" width="26" height="26" rx="4" ${W2}/>
    <rect x="112" y="99" width="26" height="26" rx="4" ${W2}/><rect x="144" y="99" width="26" height="26" rx="4" fill="#10b981"/>
  </g>`,
  wordchain: `<circle cx="65" cy="75" r="22" fill="none" stroke="#fff" stroke-width="9"/>
    <circle cx="105" cy="75" r="22" fill="none" stroke="#fff" stroke-width="9" opacity=".85"/>
    <circle cx="145" cy="75" r="22" fill="none" stroke="#fff" stroke-width="9" opacity=".6"/>`,
  wordblank: `<rect x="35" y="55" width="130" height="12" rx="6" ${W2}/>
    <rect x="90" y="80" width="40" height="14" rx="4" fill="none" stroke="#fff" stroke-width="3" stroke-dasharray="6 5"/>
    <rect x="35" y="80" width="40" height="14" rx="4" ${W2}/><rect x="140" y="80" width="25" height="14" rx="4" ${W2}/>`,
  spelling: `<path d="M55 115 L120 50 135 65 70 130 Z" ${W}/><path d="M120 50 L135 35 150 50 135 65Z" fill="#fbbf24"/>
    <path d="M55 115 L45 125 55 128 60 118Z" fill="#fbbf24"/>`,
  typing: `<rect x="35" y="55" width="130" height="55" rx="10" ${W2}/>
    <rect x="45" y="65" width="14" height="14" rx="3" ${W}/><rect x="63" y="65" width="14" height="14" rx="3" ${W}/>
    <rect x="81" y="65" width="14" height="14" rx="3" ${W}/><rect x="99" y="65" width="14" height="14" rx="3" ${W}/>
    <rect x="117" y="65" width="14" height="14" rx="3" ${W}/><rect x="135" y="65" width="14" height="14" rx="3" ${W}/>
    <rect x="55" y="88" width="90" height="14" rx="6" ${W}/>`,
  hangul: `<text x="100" y="98" text-anchor="middle" font-size="60" font-weight="900" ${W} font-family="'Noto Sans KR',sans-serif">가</text>`,
  counting: `<circle cx="60" cy="75" r="24" ${W}/><circle cx="105" cy="75" r="24" ${W2}/><circle cx="150" cy="75" r="24" ${W}/>
    <text x="60" y="84" text-anchor="middle" font-size="24" font-weight="900" fill="#d97706">1</text>
    <text x="105" y="84" text-anchor="middle" font-size="24" font-weight="900" fill="#d97706">2</text>
    <text x="150" y="84" text-anchor="middle" font-size="24" font-weight="900" fill="#d97706">3</text>`,
  colors: `<circle cx="65" cy="60" r="22" fill="#ef4444"/><circle cx="110" cy="55" r="22" fill="#3b82f6"/>
    <circle cx="140" cy="90" r="22" fill="#fbbf24"/><circle cx="80" cy="100" r="22" fill="#10b981" opacity=".9"/>`,
  shapes: `<circle cx="55" cy="75" r="22" ${W}/><rect x="90" y="53" width="44" height="44" ${W2}/>
    <polygon points="160,50 178,95 142,95" ${W}/>`,
  satwords: `<rect x="55" y="30" width="90" height="90" rx="6" ${W}/>
    <line x1="70" y1="50" x2="130" y2="50" stroke="#d97706" stroke-width="4"/>
    <line x1="70" y1="65" x2="130" y2="65" stroke="#d97706" stroke-width="4"/>
    <line x1="70" y1="80" x2="110" y2="80" stroke="#d97706" stroke-width="4"/>`,
  proverb: `<rect x="55" y="35" width="90" height="80" rx="4" ${W}/>
    <circle cx="55" cy="45" r="10" ${W}/><circle cx="55" cy="105" r="10" ${W}/>
    <circle cx="145" cy="45" r="10" ${W}/><circle cx="145" cy="105" r="10" ${W}/>
    <line x1="70" y1="60" x2="130" y2="60" stroke="#d97706" stroke-width="4"/>
    <line x1="70" y1="78" x2="130" y2="78" stroke="#d97706" stroke-width="4"/>`,
  flag: `<line x1="55" y1="30" x2="55" y2="122" stroke="#fff" stroke-width="6" stroke-linecap="round"/>
    <path d="M55 38 h75 l-15 20 15 20 h-75 Z" fill="#fff" opacity=".9"/>`,
  uslife: `<path d="M100 30c-25 0-42 18-42 40 0 30 42 55 42 55s42-25 42-55c0-22-17-40-42-40z" ${W}/>
    <circle cx="100" cy="70" r="14" fill="#d97706"/>`,
  animals: `<circle cx="100" cy="80" r="34" ${W}/>
    <polygon points="72,55 62,25 88,50" ${W}/><polygon points="128,55 138,25 112,50" ${W}/>
    <circle cx="88" cy="78" r="4" fill="#78350f"/><circle cx="112" cy="78" r="4" fill="#78350f"/>
    <path d="M92 92q8 8 16 0" stroke="#78350f" stroke-width="3" fill="none" stroke-linecap="round"/>`,
  idiom: `<rect x="50" y="30" width="100" height="90" rx="4" ${W}/>
    <path d="M65 55 q35 -15 70 0" stroke="#d97706" stroke-width="4" fill="none" stroke-linecap="round"/>
    <path d="M65 75 q35 15 70 0" stroke="#d97706" stroke-width="4" fill="none" stroke-linecap="round"/>
    <path d="M65 95 q35 -8 70 0" stroke="#d97706" stroke-width="4" fill="none" stroke-linecap="round"/>`,
  wordcard: `<rect x="45" y="35" width="70" height="80" rx="8" ${W2}/><rect x="85" y="45" width="70" height="80" rx="8" ${W}/>
    <rect x="98" y="58" width="44" height="30" rx="4" fill="#059669" opacity=".5"/>
    <line x1="98" y1="100" x2="142" y2="100" stroke="#059669" stroke-width="4"/>`,
  stroop: `<text x="100" y="65" text-anchor="middle" font-size="26" font-weight="900" fill="#f43f5e">파랑</text>
    <text x="100" y="100" text-anchor="middle" font-size="26" font-weight="900" fill="#fbbf24">빨강</text>`,
}

const scene = computed(() => SCENES[props.slug] || `<circle cx="100" cy="75" r="30" ${W}/>`)
</script>

<style scoped>
.game-thumb-svg { width: 100%; height: 100%; display: block; }
</style>
