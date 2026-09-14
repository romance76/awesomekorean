// 게임 로비 카드 일러스트 테마 데이터
// stops: 배경 라디얼 그라데이션 [밝은색, 중간색, 어두운색]
// pattern: 배경 다이아몬드 패턴 선 색
// text: 타이틀 글자색 / shadow: 타이틀 입체 엠보싱용 그림자 3단계
// illustration: 카드 좌하단에 배치되는 SVG 원본 마크업 (400x300 좌표계)

export const CARD_ART = {
  memory: {
    stops: ['#fb7185', '#e11d48', '#9f1239'], pattern: '#ffffff', text: '#ffffff',
    shadow: ['#9f1239', '#881337', '#6b0f2b'],
    illustration: `
      <g transform="translate(16,58) rotate(-16)">
        <rect x="2" y="2" width="96" height="132" rx="10" fill="#fff" stroke="#fecdd3" stroke-width="2"/>
        <text x="14" y="30" font-family="Poppins" font-weight="800" font-size="20" fill="#e11d48">A</text>
        <text x="50" y="90" font-size="46" fill="#fecdd3" text-anchor="middle">&#9829;</text>
      </g>
      <g transform="translate(96,34) rotate(10)">
        <rect x="2" y="2" width="96" height="132" rx="10" fill="#fff" stroke="#fecdd3" stroke-width="2"/>
        <text x="14" y="30" font-family="Poppins" font-weight="800" font-size="20" fill="#e11d48">A</text>
        <text x="50" y="90" font-size="46" fill="#fecdd3" text-anchor="middle">&#9829;</text>
      </g>
      <g transform="translate(158,64) rotate(-8)">
        <circle r="26" fill="#16a34a"/>
        <polyline points="-9,0 -2,8 11,-9" fill="none" stroke="#fff" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"/>
      </g>`,
  },
  '2048': {
    stops: ['#60a5fa', '#2563eb', '#1e3a8a'], pattern: '#ffffff', text: '#ffffff',
    shadow: ['#1d4ed8', '#1e40af', '#1e3a8a'],
    illustration: `
      <g transform="translate(24,56) rotate(-8)">
        <rect x="0" y="56" width="80" height="80" rx="12" fill="#3b82f6" stroke="#1d4ed8" stroke-width="2"/>
        <text x="40" y="106" text-anchor="middle" font-family="Black Han Sans" font-size="36" fill="#fff">2</text>
        <rect x="66" y="20" width="88" height="88" rx="12" fill="#f0f9ff" stroke="#93c5fd" stroke-width="2"/>
        <text x="110" y="76" text-anchor="middle" font-family="Black Han Sans" font-size="38" fill="#1d4ed8">4</text>
        <rect x="110" y="78" width="84" height="84" rx="12" fill="#f59e0b" stroke="#b45309" stroke-width="2"/>
        <text x="152" y="132" text-anchor="middle" font-family="Black Han Sans" font-size="24" fill="#78350f">2048</text>
      </g>`,
  },
  omok: {
    stops: ['#475569', '#1e293b', '#0f172a'], pattern: '#fbbf24', text: '#fde68a',
    shadow: ['#b45309', '#92400e', '#78350f'],
    illustration: `
      <g transform="translate(14,74) rotate(-4)">
        <rect x="2" y="2" width="186" height="146" rx="6" fill="#d0995e" stroke="#7c4a1e" stroke-width="3"/>
        <g stroke="#7c4a1e" stroke-width="1.6" opacity="0.65">
          <line x1="26" y1="20" x2="26" y2="130"/><line x1="58" y1="20" x2="58" y2="130"/><line x1="90" y1="20" x2="90" y2="130"/>
          <line x1="122" y1="20" x2="122" y2="130"/><line x1="154" y1="20" x2="154" y2="130"/>
          <line x1="16" y1="30" x2="174" y2="30"/><line x1="16" y1="62" x2="174" y2="62"/><line x1="16" y1="94" x2="174" y2="94"/><line x1="16" y1="126" x2="174" y2="126"/>
        </g>
        <circle cx="90" cy="62" r="12" fill="#0f0f0f"/>
        <circle cx="122" cy="94" r="12" fill="#f8fafc" stroke="#94a3b8" stroke-width="1.5"/>
        <circle cx="58" cy="94" r="12" fill="#0f0f0f"/>
        <circle cx="122" cy="30" r="12" fill="#f8fafc" stroke="#94a3b8" stroke-width="1.5"/>
        <circle cx="154" cy="62" r="12" fill="#0f0f0f"/>
      </g>`,
  },
  puzzle: {
    stops: ['#22d3ee', '#0e7490', '#164e63'], pattern: '#ffffff', text: '#ffffff',
    shadow: ['#0e7490', '#155e75', '#164e63'],
    illustration: `
      <g transform="translate(30,64) rotate(-6)">
        <path d="M0 20 h60 a14 14 0 0 1 0 20 v0 a14 14 0 0 1 0 20 h-60 a14 14 0 0 0 -20 0 v-60 a14 14 0 0 0 20 0 Z" fill="#fbbf24" stroke="#b45309" stroke-width="2"/>
        <path d="M60 20 h60 v60 h-60 a14 14 0 0 0 0 -20 a14 14 0 0 0 0 -20 Z" fill="#34d399" stroke="#047857" stroke-width="2" transform="translate(4,0)"/>
        <path d="M0 80 h60 a14 14 0 0 1 20 0 v60 h-80 a14 14 0 0 0 0 -20 a14 14 0 0 0 0 -20 Z" fill="#f472b6" stroke="#be185d" stroke-width="2"/>
        <circle cx="60" cy="80" r="7" fill="#fff" opacity="0.8"/>
      </g>`,
  },
  bingo: {
    stops: ['#e879f9', '#a21caf', '#701a75'], pattern: '#ffffff', text: '#fce7f3',
    shadow: ['#a21caf', '#86198f', '#701a75'],
    illustration: `
      <g transform="translate(22,54) rotate(-5)">
        <rect x="0" y="0" width="170" height="150" rx="10" fill="#fff" stroke="#f5d0fe" stroke-width="3"/>
        <g stroke="#e9d5ff" stroke-width="2">
          <line x1="34" y1="0" x2="34" y2="150"/><line x1="68" y1="0" x2="68" y2="150"/><line x1="102" y1="0" x2="102" y2="150"/><line x1="136" y1="0" x2="136" y2="150"/>
          <line x1="0" y1="30" x2="170" y2="30"/><line x1="0" y1="60" x2="170" y2="60"/><line x1="0" y1="90" x2="170" y2="90"/><line x1="0" y1="120" x2="170" y2="120"/>
        </g>
        <circle cx="51" cy="45" r="13" fill="#ec4899" opacity="0.85"/>
        <circle cx="119" cy="45" r="13" fill="#ec4899" opacity="0.85"/>
        <circle cx="85" cy="75" r="13" fill="#a21caf"/>
        <circle cx="17" cy="105" r="13" fill="#ec4899" opacity="0.85"/>
        <circle cx="153" cy="105" r="13" fill="#ec4899" opacity="0.85"/>
      </g>`,
  },
  speedcalc: {
    stops: ['#38bdf8', '#0369a1', '#0c4a6e'], pattern: '#ffffff', text: '#ffffff',
    shadow: ['#0369a1', '#075985', '#0c4a6e'],
    illustration: `
      <g font-family="Poppins" font-weight="800">
        <g transform="translate(40,70) rotate(-10)"><circle r="34" fill="#fbbf24"/><text text-anchor="middle" dy="14" font-size="40" fill="#78350f">+</text></g>
        <g transform="translate(110,50) rotate(8)"><circle r="30" fill="#f472b6"/><text text-anchor="middle" dy="12" font-size="34" fill="#831843">&#215;</text></g>
        <g transform="translate(70,130) rotate(6)"><circle r="26" fill="#34d399"/><text text-anchor="middle" dy="10" font-size="28" fill="#064e3b">&#8722;</text></g>
        <g transform="translate(150,110) rotate(-6)"><circle r="22" fill="#fff"/><text text-anchor="middle" dy="8" font-size="24" fill="#0369a1">&#247;</text></g>
      </g>`,
  },
  seniormemory: {
    stops: ['#f472b6', '#be185d', '#831843'], pattern: '#ffffff', text: '#ffffff',
    shadow: ['#be185d', '#9d174d', '#831843'],
    illustration: `
      <g transform="translate(45,70)">
        <path d="M0 30 C-40 -10 -70 40 0 90 C70 40 40 -10 0 30 Z" fill="#fff" opacity="0.95" transform="translate(50,0) scale(1.1)"/>
        <circle cx="120" cy="45" r="30" fill="#fde047"/>
        <circle cx="112" cy="38" r="4" fill="#78350f"/><circle cx="128" cy="38" r="4" fill="#78350f"/>
        <path d="M108 54 q12 10 24 0" stroke="#78350f" stroke-width="3" fill="none" stroke-linecap="round"/>
        <circle cx="150" cy="10" r="6" fill="#fff" opacity="0.8"/>
        <circle cx="30" cy="100" r="5" fill="#fff" opacity="0.7"/>
      </g>`,
  },
  stroop: {
    stops: ['#818cf8', '#4338ca', '#312e81'], pattern: '#ffffff', text: '#ffffff',
    shadow: ['#4338ca', '#3730a3', '#312e81'],
    illustration: `
      <g font-family="Black Han Sans" font-size="28">
        <g transform="translate(24,60) rotate(-8)"><rect width="110" height="46" rx="10" fill="#fff"/><text x="55" y="32" text-anchor="middle" fill="#2563eb">빨강</text></g>
        <g transform="translate(60,110) rotate(5)"><rect width="110" height="46" rx="10" fill="#fff"/><text x="55" y="32" text-anchor="middle" fill="#ea580c">파랑</text></g>
        <g transform="translate(20,158) rotate(-3)"><rect width="110" height="46" rx="10" fill="#fff"/><text x="55" y="32" text-anchor="middle" fill="#7c3aed">노랑</text></g>
      </g>`,
  },
  snake: {
    stops: ['#4ade80', '#15803d', '#14532d'], pattern: '#ffffff', text: '#ffffff',
    shadow: ['#15803d', '#166534', '#14532d'],
    illustration: `
      <g>
        <g fill="#22c55e" stroke="#14532d" stroke-width="2">
          <rect x="20" y="150" width="30" height="30" rx="7"/>
          <rect x="46" y="150" width="30" height="30" rx="7"/>
          <rect x="72" y="130" width="30" height="30" rx="7"/>
          <rect x="72" y="104" width="30" height="30" rx="7"/>
          <rect x="98" y="84" width="30" height="30" rx="7"/>
        </g>
        <circle cx="128" cy="84" r="6" fill="#052e14"/>
        <circle cx="170" cy="90" r="14" fill="#ef4444" stroke="#7f1d1d" stroke-width="2"/>
        <path d="M170 76 q6 -10 12 -4" stroke="#15803d" stroke-width="3" fill="none" stroke-linecap="round"/>
      </g>`,
  },
  towerdefense: {
    stops: ['#2dd4bf', '#0f766e', '#042f2e'], pattern: '#ffffff', text: '#f0fdfa',
    shadow: ['#0f766e', '#115e59', '#134e4a'],
    illustration: `
      <g transform="translate(24,74)">
        <rect x="30" y="66" width="110" height="12" rx="3" fill="#134e4a" opacity="0.5"/>
        <rect x="55" y="-2" width="60" height="76" rx="4" fill="#e2e8f0"/>
        <rect x="30" y="18" width="22" height="46" rx="3" fill="#f1f5f9"/>
        <rect x="118" y="18" width="22" height="46" rx="3" fill="#f1f5f9"/>
        <rect x="72" y="34" width="26" height="34" rx="2" fill="#0f766e"/>
        <circle cx="85" cy="20" r="6" fill="#0f766e"/>
        <path d="M85 -28 v-20" stroke="#fff" stroke-width="3"/>
        <path d="M85 -48 l26 10 -26 10 Z" fill="#f97316"/>
      </g>`,
  },
  slots: {
    stops: ['#fb923c', '#c2410c', '#7c2d12'], pattern: '#ffffff', text: '#fff7ed',
    shadow: ['#c2410c', '#9a3412', '#7c2d12'],
    illustration: `
      <g transform="translate(20,54)">
        <rect x="0" y="0" width="170" height="110" rx="14" fill="#7c2d12" stroke="#fbbf24" stroke-width="3"/>
        <rect x="14" y="16" width="44" height="78" rx="8" fill="#fff"/>
        <rect x="63" y="16" width="44" height="78" rx="8" fill="#fff"/>
        <rect x="112" y="16" width="44" height="78" rx="8" fill="#fff"/>
        <text x="36" y="68" text-anchor="middle" font-size="40">&#127820;</text>
        <text x="85" y="68" text-anchor="middle" font-size="36" font-family="Black Han Sans" fill="#dc2626">7</text>
        <text x="134" y="68" text-anchor="middle" font-size="38">&#127826;</text>
        <circle cx="190" cy="30" r="10" fill="#fbbf24"/>
        <rect x="185" y="28" width="10" height="50" rx="5" fill="#fbbf24"/>
      </g>`,
  },
  stocksim: {
    stops: ['#34d399', '#059669', '#064e3b'], pattern: '#ffffff', text: '#ffffff',
    shadow: ['#059669', '#047857', '#064e3b'],
    illustration: `
      <g transform="translate(30,60)">
        <rect x="0" y="90" width="24" height="40" rx="3" fill="#a7f3d0"/>
        <rect x="34" y="66" width="24" height="64" rx="3" fill="#6ee7b7"/>
        <rect x="68" y="40" width="24" height="90" rx="3" fill="#34d399"/>
        <rect x="102" y="10" width="24" height="120" rx="3" fill="#fff"/>
        <polyline points="4,90 46,66 80,40 114,10" fill="none" stroke="#fbbf24" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M100 10 l18 0 0 18" fill="none" stroke="#fbbf24" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
      </g>`,
  },
  wordle: {
    stops: ['#84cc16', '#4d7c0f', '#365314'], pattern: '#ffffff', text: '#ffffff',
    shadow: ['#4d7c0f', '#3f6212', '#365314'],
    illustration: `
      <g transform="translate(18,90) rotate(-4)" font-family="Black Han Sans" font-size="26">
        <rect x="0" y="0" width="40" height="40" rx="6" fill="#22c55e"/><text x="20" y="28" text-anchor="middle" fill="#fff">K</text>
        <rect x="46" y="0" width="40" height="40" rx="6" fill="#eab308"/><text x="66" y="28" text-anchor="middle" fill="#fff">O</text>
        <rect x="92" y="0" width="40" height="40" rx="6" fill="#57534e"/><text x="112" y="28" text-anchor="middle" fill="#fff">R</text>
        <rect x="138" y="0" width="40" height="40" rx="6" fill="#22c55e"/><text x="158" y="28" text-anchor="middle" fill="#fff">E</text>
        <rect x="46" y="48" width="40" height="40" rx="6" fill="#eab308"/><text x="66" y="76" text-anchor="middle" fill="#fff">A</text>
      </g>`,
  },
  wordchain: {
    stops: ['#c084fc', '#7e22ce', '#4c1d95'], pattern: '#ffffff', text: '#ffffff',
    shadow: ['#7e22ce', '#6b21a8', '#4c1d95'],
    illustration: `
      <g font-family="Black Han Sans" font-size="24">
        <g transform="translate(14,60) rotate(-6)"><path d="M0 20 a20 20 0 1 1 0 1 M0 21 l-10 16 12 -8 Z" fill="#fff"/><rect x="0" y="0" width="90" height="42" rx="18" fill="#fff"/><text x="45" y="28" text-anchor="middle" fill="#7e22ce">사과</text></g>
        <g transform="translate(96,116) rotate(4)"><rect x="0" y="0" width="90" height="42" rx="18" fill="#fff"/><text x="45" y="28" text-anchor="middle" fill="#7e22ce">과일</text></g>
        <circle cx="90" cy="95" r="9" fill="none" stroke="#fde68a" stroke-width="5"/>
        <circle cx="105" cy="105" r="9" fill="none" stroke="#fde68a" stroke-width="5"/>
      </g>`,
  },
  wordblank: {
    stops: ['#5eead4', '#0f766e', '#134e4a'], pattern: '#ffffff', text: '#ffffff',
    shadow: ['#0f766e', '#115e59', '#134e4a'],
    illustration: `
      <g transform="translate(22,54) rotate(-3)">
        <rect x="0" y="0" width="160" height="130" rx="10" fill="#fff"/>
        <rect x="0" y="0" width="18" height="130" rx="4" fill="#fca5a5"/>
        <line x1="40" y1="30" x2="140" y2="30" stroke="#5eead4" stroke-width="5" stroke-linecap="round"/>
        <line x1="40" y1="55" x2="100" y2="55" stroke="#5eead4" stroke-width="5" stroke-linecap="round" stroke-dasharray="6 8"/>
        <line x1="40" y1="80" x2="130" y2="80" stroke="#5eead4" stroke-width="5" stroke-linecap="round"/>
        <line x1="40" y1="105" x2="90" y2="105" stroke="#5eead4" stroke-width="5" stroke-linecap="round"/>
        <g transform="translate(150,100) rotate(45)"><rect x="-6" y="-30" width="12" height="50" rx="3" fill="#fbbf24"/><path d="M-6 20 L0 34 L6 20 Z" fill="#78350f"/></g>
      </g>`,
  },
  spelling: {
    stops: ['#f87171', '#b91c1c', '#7f1d1d'], pattern: '#ffffff', text: '#ffffff',
    shadow: ['#b91c1c', '#991b1b', '#7f1d1d'],
    illustration: `
      <g transform="translate(20,56) rotate(-3)" font-family="Poppins" font-weight="700">
        <rect x="0" y="0" width="170" height="130" rx="10" fill="#fff"/>
        <text x="16" y="42" font-size="24" fill="#dc2626" text-decoration="line-through">어의없다</text>
        <line x1="10" y1="34" x2="150" y2="34" stroke="#dc2626" stroke-width="3"/>
        <text x="16" y="82" font-size="24" fill="#16a34a">어이없다</text>
        <g transform="translate(140,72)"><circle r="16" fill="#16a34a"/><polyline points="-6,0 -1,6 8,-7" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/></g>
      </g>`,
  },
  typing: {
    stops: ['#818cf8', '#3730a3', '#1e1b4b'], pattern: '#ffffff', text: '#ffffff',
    shadow: ['#3730a3', '#312e81', '#1e1b4b'],
    illustration: `
      <g transform="translate(18,90) rotate(-2)" font-family="Poppins" font-weight="800" font-size="16">
        <g fill="#fff"><rect x="0" y="0" width="34" height="34" rx="7"/><rect x="40" y="0" width="34" height="34" rx="7"/><rect x="80" y="0" width="34" height="34" rx="7"/><rect x="120" y="0" width="34" height="34" rx="7"/><rect x="160" y="0" width="34" height="34" rx="7"/></g>
        <text x="17" y="23" text-anchor="middle" fill="#3730a3">K</text><text x="57" y="23" text-anchor="middle" fill="#3730a3">O</text><text x="97" y="23" text-anchor="middle" fill="#3730a3">R</text><text x="137" y="23" text-anchor="middle" fill="#3730a3">E</text><text x="177" y="23" text-anchor="middle" fill="#3730a3">A</text>
        <rect x="20" y="48" width="150" height="16" rx="8" fill="#fff" opacity="0.85"/>
        <rect x="160" y="46" width="3" height="20" fill="#3730a3"/>
      </g>`,
  },
  wordcard: {
    stops: ['#fbbf24', '#b45309', '#78350f'], pattern: '#ffffff', text: '#fffbeb',
    shadow: ['#b45309', '#92400e', '#78350f'],
    illustration: `
      <g>
        <rect x="30" y="110" width="90" height="60" rx="8" fill="#fff" opacity="0.6" transform="rotate(10 30 110)"/>
        <rect x="24" y="90" width="90" height="60" rx="8" fill="#fff" opacity="0.85" transform="rotate(-4 24 90)"/>
        <rect x="24" y="66" width="94" height="66" rx="8" fill="#fff"/>
        <text x="71" y="112" text-anchor="middle" font-family="Black Han Sans" font-size="34" fill="#b45309">A</text>
      </g>`,
  },
  hangul: {
    stops: ['#f59e0b', '#b91c1c', '#450a0a'], pattern: '#fde68a', text: '#fef3c7',
    shadow: ['#b91c1c', '#7f1d1d', '#450a0a'],
    illustration: `
      <g transform="translate(22,66) rotate(-6)" font-family="Black Han Sans" font-size="34">
        <rect x="0" y="30" width="70" height="70" rx="12" fill="#fff"/><text x="35" y="80" text-anchor="middle" fill="#b91c1c">&#44032;</text>
        <rect x="60" y="0" width="76" height="76" rx="12" fill="#fef3c7"/><text x="98" y="52" text-anchor="middle" fill="#b91c1c">&#45208;</text>
        <rect x="106" y="46" width="70" height="70" rx="12" fill="#fff"/><text x="141" y="96" text-anchor="middle" fill="#b91c1c">&#45796;</text>
      </g>`,
  },
  counting: {
    stops: ['#67e8f9', '#0e7490', '#155e75'], pattern: '#ffffff', text: '#ffffff',
    shadow: ['#0e7490', '#155e75', '#134e4a'],
    illustration: `
      <g transform="translate(22,64) rotate(-5)">
        <rect x="0" y="30" width="56" height="56" rx="10" fill="#fff"/><circle cx="28" cy="58" r="7" fill="#0e7490"/>
        <rect x="60" y="6" width="60" height="60" rx="10" fill="#fef3c7"/><circle cx="76" cy="22" r="6" fill="#0e7490"/><circle cx="104" cy="50" r="6" fill="#0e7490"/>
        <rect x="128" y="42" width="60" height="60" rx="10" fill="#fff"/><circle cx="143" cy="57" r="6" fill="#0e7490"/><circle cx="158" cy="72" r="6" fill="#0e7490"/><circle cx="173" cy="57" r="6" fill="#0e7490"/>
      </g>`,
  },
  colors: {
    stops: ['#f0abfc', '#a21caf', '#701a75'], pattern: '#ffffff', text: '#ffffff',
    shadow: ['#a21caf', '#86198f', '#701a75'],
    illustration: `
      <g transform="translate(30,110)">
        <circle cx="0" cy="0" r="24" fill="#ef4444"/>
        <circle cx="38" cy="-18" r="24" fill="#f97316"/>
        <circle cx="76" cy="-26" r="24" fill="#eab308"/>
        <circle cx="114" cy="-18" r="24" fill="#22c55e"/>
        <circle cx="150" cy="0" r="24" fill="#3b82f6"/>
        <g transform="translate(90,30) rotate(20)"><path d="M-8 0 q8 -20 20 -14 q6 10 -8 20 q-14 6 -12 -6 Z" fill="#fff"/><rect x="8" y="6" width="6" height="26" rx="3" fill="#78350f" transform="rotate(30 8 6)"/></g>
      </g>`,
  },
  shapes: {
    stops: ['#2dd4bf', '#0d9488', '#115e59'], pattern: '#ffffff', text: '#ffffff',
    shadow: ['#0d9488', '#0f766e', '#115e59'],
    illustration: `
      <g>
        <circle cx="45" cy="90" r="34" fill="#fbbf24"/>
        <path d="M120 60 L150 118 L90 118 Z" fill="#f472b6"/>
        <rect x="100" y="40" width="56" height="56" rx="8" fill="#60a5fa" transform="rotate(12 128 68)"/>
        <path d="M55 150 l8 -22 8 22 -22 -14 h28 Z" fill="#fff"/>
      </g>`,
  },
  satwords: {
    stops: ['#fdba74', '#9a3412', '#431407'], pattern: '#ffffff', text: '#fff7ed',
    shadow: ['#9a3412', '#7c2d12', '#431407'],
    illustration: `
      <g transform="translate(20,72)">
        <path d="M0 10 Q45 -12 90 10 V96 Q45 74 0 96 Z" fill="#fff"/>
        <path d="M90 10 Q135 -12 180 10 V96 Q135 74 90 96 Z" fill="#f3f4f6"/>
        <line x1="90" y1="10" x2="90" y2="96" stroke="#d6d3d1" stroke-width="2"/>
        <g stroke="#9a3412" stroke-width="2.5" opacity="0.5">
          <line x1="16" y1="30" x2="72" y2="24"/><line x1="16" y1="46" x2="72" y2="40"/><line x1="16" y1="62" x2="60" y2="57"/>
          <line x1="106" y1="24" x2="162" y2="30"/><line x1="108" y1="40" x2="162" y2="46"/><line x1="118" y1="57" x2="162" y2="62"/>
        </g>
        <path d="M60 -20 l30 -12 30 12 -30 12 Z" fill="#1e293b"/>
        <rect x="88" y="-8" width="4" height="16" fill="#1e293b"/>
      </g>`,
  },
  proverb: {
    stops: ['#d6b98c', '#78350f', '#2b1a0e'], pattern: '#292524', text: '#fef3c7',
    shadow: ['#78350f', '#451a03', '#2b1a0e'],
    illustration: `
      <g transform="translate(18,48)">
        <rect x="0" y="10" width="150" height="140" rx="6" fill="#fdf6e3" stroke="#a8895f" stroke-width="3"/>
        <rect x="10" y="20" width="130" height="120" rx="3" fill="none" stroke="#c9a876" stroke-width="1.5"/>
        <path d="M26 52 Q75 40 118 62 Q80 78 40 92 Q85 88 122 108" stroke="#1c1917" stroke-width="7" fill="none" stroke-linecap="round"/>
        <circle cx="26" cy="52" r="4" fill="#1c1917"/>
        <g transform="translate(150,-6) rotate(35)"><rect x="-7" y="-6" width="14" height="82" rx="5" fill="#92400e" stroke="#451a03" stroke-width="2"/><path d="M-12 -6 q12 -26 24 0 q-3 16 -24 0 Z" fill="#0c0a09"/></g>
      </g>`,
  },
  flag: {
    stops: ['#60a5fa', '#1e40af', '#0f1e4d'], pattern: '#ffffff', text: '#ffffff',
    shadow: ['#1e40af', '#1e3a8a', '#0f1e4d'],
    illustration: `
      <g transform="translate(70,90)">
        <circle r="56" fill="#dbeafe" stroke="#fff" stroke-width="3"/>
        <ellipse rx="56" ry="20" fill="none" stroke="#93c5fd" stroke-width="2"/>
        <ellipse rx="24" ry="56" fill="none" stroke="#93c5fd" stroke-width="2"/>
        <line x1="-56" y1="0" x2="56" y2="0" stroke="#93c5fd" stroke-width="2"/>
        <g transform="translate(70,-30) rotate(-10)"><rect width="3" height="46" fill="#fff"/><path d="M3 0 h30 l-8 10 8 10 h-30 Z" fill="#ef4444"/></g>
        <g transform="translate(-30,-56) rotate(8)"><rect width="3" height="40" fill="#fff"/><path d="M3 0 h26 l-7 9 7 9 h-26 Z" fill="#facc15"/></g>
      </g>`,
  },
  uslife: {
    stops: ['#f87171', '#1e3a8a', '#0b1120'], pattern: '#ffffff', text: '#ffffff',
    shadow: ['#1e3a8a', '#172554', '#0b1120'],
    illustration: `
      <g transform="translate(30,50)">
        <path d="M0 0 h140 v90 q-70 34 -70 34 q-70 -34 -70 -34 Z" fill="#fff"/>
        <g fill="#dc2626"><rect y="0" width="140" height="13"/><rect y="26" width="140" height="13"/><rect y="52" width="140" height="13"/><rect y="78" width="140" height="13"/></g>
        <rect width="60" height="52" fill="#1e3a8a"/>
        <g fill="#fff"><circle cx="12" cy="10" r="3"/><circle cx="30" cy="10" r="3"/><circle cx="48" cy="10" r="3"/><circle cx="21" cy="24" r="3"/><circle cx="39" cy="24" r="3"/><circle cx="12" cy="38" r="3"/><circle cx="30" cy="38" r="3"/><circle cx="48" cy="38" r="3"/></g>
      </g>`,
  },
  animals: {
    stops: ['#fcd34d', '#9a3412', '#431407'], pattern: '#ffffff', text: '#fff7ed',
    shadow: ['#9a3412', '#7c2d12', '#431407'],
    illustration: `
      <g transform="translate(80,140) scale(1.35)">
        <path d="M-55 -20 L-25 -60 L-10 -15 Z" fill="#f97316" stroke="#9a3412" stroke-width="2"/>
        <path d="M55 -20 L25 -60 L10 -15 Z" fill="#f97316" stroke="#9a3412" stroke-width="2"/>
        <path d="M-30 -35 L-15 -50 L-6 -20 Z" fill="#fff7ed"/>
        <path d="M30 -35 L15 -50 L6 -20 Z" fill="#fff7ed"/>
        <ellipse rx="55" ry="46" fill="#fdba74" stroke="#9a3412" stroke-width="2"/>
        <path d="M-30 10 Q0 30 30 10 L18 30 Q0 40 -18 30 Z" fill="#fff7ed"/>
        <circle cx="-20" cy="-6" r="6.5" fill="#1c1917"/><circle cx="20" cy="-6" r="6.5" fill="#1c1917"/>
        <circle cx="-22" cy="-8" r="2" fill="#fff"/><circle cx="18" cy="-8" r="2" fill="#fff"/>
        <path d="M0 8 l-9 11 h18 Z" fill="#1c1917"/>
        <path d="M-3 20 q3 5 6 0" stroke="#1c1917" stroke-width="2.5" fill="none" stroke-linecap="round"/>
      </g>`,
  },
  idiom: {
    stops: ['#d6d3d1', '#292524', '#0c0a09'], pattern: '#a8a29e', text: '#fbbf24',
    shadow: ['#292524', '#1c1917', '#0c0a09'],
    illustration: `
      <g transform="translate(24,40)">
        <rect x="0" y="0" width="80" height="150" rx="4" fill="#fdf6e3" stroke="#a8a29e" stroke-width="2"/>
        <path d="M40 16 C18 34 20 56 40 66 C60 56 62 34 40 16 Z" fill="#1c1917"/>
        <path d="M40 66 C24 78 26 98 40 108 C54 98 56 78 40 66 Z" fill="#1c1917" opacity="0.88"/>
        <path d="M40 108 C30 116 31 130 40 138 C49 130 50 116 40 108 Z" fill="#1c1917" opacity="0.7"/>
        <rect x="90" y="18" width="80" height="150" rx="4" fill="#fdf6e3" stroke="#a8a29e" stroke-width="2" transform="rotate(4 130 93)"/>
        <g transform="rotate(4 130 93)">
          <line x1="115" y1="40" x2="145" y2="150" stroke="#1c1917" stroke-width="10" stroke-linecap="round"/>
          <line x1="100" y1="70" x2="160" y2="70" stroke="#1c1917" stroke-width="8" stroke-linecap="round"/>
        </g>
        <circle cx="14" cy="14" r="6" fill="#dc2626"/>
      </g>`,
  },
  casino: {
    stops: ['#fbbf24', '#7f1d1d', '#1a0505'], pattern: '#fbbf24', text: '#fef3c7',
    shadow: ['#7f1d1d', '#450a0a', '#1a0505'],
    illustration: `
      <g transform="translate(90,100)">
        <circle r="62" fill="#0c0a09" stroke="#fbbf24" stroke-width="4"/>
        <g>
          <path d="M0 0 L0 -62 A62 62 0 0 1 44 -44 Z" fill="#dc2626"/>
          <path d="M0 0 L44 -44 A62 62 0 0 1 62 0 Z" fill="#171717"/>
          <path d="M0 0 L62 0 A62 62 0 0 1 44 44 Z" fill="#dc2626"/>
          <path d="M0 0 L44 44 A62 62 0 0 1 0 62 Z" fill="#171717"/>
          <path d="M0 0 L0 62 A62 62 0 0 1 -44 44 Z" fill="#dc2626"/>
          <path d="M0 0 L-44 44 A62 62 0 0 1 -62 0 Z" fill="#171717"/>
          <path d="M0 0 L-62 0 A62 62 0 0 1 -44 -44 Z" fill="#dc2626"/>
          <path d="M0 0 L-44 -44 A62 62 0 0 1 0 -62 Z" fill="#171717"/>
        </g>
        <circle r="16" fill="#fbbf24"/>
        <circle cx="30" cy="-46" r="7" fill="#fff"/>
        <g transform="translate(-90,40)">
          <circle cy="0" r="16" fill="#fff" stroke="#dc2626" stroke-width="3"/>
          <circle cy="-10" r="16" fill="#3b82f6" stroke="#fff" stroke-width="3"/>
          <circle cy="-20" r="16" fill="#dc2626" stroke="#fff" stroke-width="3"/>
        </g>
      </g>`,
  },
}

export const DEFAULT_ART = {
  stops: ['#a8a29e', '#57534e', '#292524'], pattern: '#ffffff', text: '#ffffff',
  shadow: ['#57534e', '#44403c', '#292524'],
  illustration: '',
}
