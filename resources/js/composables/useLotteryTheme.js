// 3D 추첨 무대 테마: 기본값 + 병합 + 헬퍼
// 사이트 로고 기본 경로 (NavBar 의 siteStore.logoUrl 기본값과 동일)
export const DEFAULT_LOGO = '/images/logo.png'

export const DEFAULT_THEME = Object.freeze({
  bg_top: '#1b2a63',
  bg_bottom: '#070a1c',
  accent: '#FFC83D',
  floor: '#0b1022',
  glass_tint: '#cfeaff',
  // 채도 높은 광택 공 팔레트: 빨강/주황/노랑/초록/파랑/보라/흰/핑크
  ball_colors: ['#e53935', '#fb8c00', '#fdd835', '#2ea84f', '#1e7be8', '#8e3bc4', '#f4f4f6', '#ec407a'],
  logo_url: DEFAULT_LOGO,
  prize_image_url: '',
  background_image_url: '',
  show_logo: true,
})

const HEX_RE = /^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/

/** #rgb / #rrggbb 형식인지 검사 */
export function isHex(v) {
  return typeof v === 'string' && HEX_RE.test(v.trim())
}

/** 유효한 hex 면 #rrggbb 로 정규화, 아니면 fallback */
export function safeHex(v, fallback) {
  if (!isHex(v)) return fallback
  let s = v.trim().toLowerCase()
  if (s.length === 4) s = '#' + s[1] + s[1] + s[2] + s[2] + s[3] + s[3]
  return s
}

/** 허용 가능한 이미지 URL 인지 (http(s), 루트 상대경로, data:image) */
export function safeUrl(v) {
  if (typeof v !== 'string') return ''
  const s = v.trim()
  if (!s) return ''
  if (/^(https?:)?\/\//i.test(s) || s.startsWith('/') || /^data:image\//i.test(s)) return s
  return ''
}

/** 서버 테마(부분값 가능)와 기본값을 병합해 검증된 테마 반환 */
export function mergeTheme(theme) {
  const t = theme && typeof theme === 'object' ? theme : {}
  const d = DEFAULT_THEME
  const balls = Array.isArray(t.ball_colors)
    ? t.ball_colors.filter(isHex).map((c) => safeHex(c, '#ffffff'))
    : []
  return {
    bg_top: safeHex(t.bg_top, d.bg_top),
    bg_bottom: safeHex(t.bg_bottom, d.bg_bottom),
    accent: safeHex(t.accent, d.accent),
    floor: safeHex(t.floor, d.floor),
    glass_tint: safeHex(t.glass_tint, d.glass_tint),
    ball_colors: balls.length ? balls : [...d.ball_colors],
    logo_url: resolveLogo(t),
    prize_image_url: safeUrl(t.prize_image_url),
    background_image_url: safeUrl(t.background_image_url),
    show_logo: t.show_logo !== false,
  }
}

/** 로고 URL 결정: 테마 값이 유효하면 사용, 없으면 사이트 기본 로고 */
export function resolveLogo(theme) {
  return safeUrl(theme?.logo_url) || DEFAULT_LOGO
}

/** hex -> three.js 에서 쓰는 정수(0xRRGGBB) */
export function hexToInt(hex) {
  return parseInt(safeHex(hex, '#ffffff').slice(1), 16)
}

/** hex + 알파 -> rgba() 문자열 */
export function hexToRgba(hex, a = 1) {
  const n = hexToInt(hex)
  return `rgba(${(n >> 16) & 255},${(n >> 8) & 255},${n & 255},${a})`
}

/** hex 를 흰색 쪽으로 밝게 (amt 0~1) -> #rrggbb */
export function lightenHex(hex, amt = 0.3) {
  const n = hexToInt(hex)
  const f = (v) => Math.round(v + (255 - v) * amt).toString(16).padStart(2, '0')
  return '#' + f((n >> 16) & 255) + f((n >> 8) & 255) + f(n & 255)
}

/** 배경색 위에서 읽기 좋은 글자색 (어두운 남색 / 흰색) */
export function readableOn(hex) {
  const n = hexToInt(hex)
  const l = (0.299 * ((n >> 16) & 255) + 0.587 * ((n >> 8) & 255) + 0.114 * (n & 255)) / 255
  return l > 0.6 ? '#2a1a00' : '#ffffff'
}
