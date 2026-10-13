// MONEY 카페 색 (money_cafes.theme) — 대문 그림 바탕·버튼·주제 카드 색. 관리자 화면의 색 고르기도 이 목록을 쓴다.
export const THEMES = {
  emerald: { label: '초록', from: '#e6f7ef', to: '#ffffff', art: '#d3f0e2', accent: '#008968', soft: '#ecf8f2', ink: '#0b5c45', line: '#cdeadb' },
  orange:  { label: '주황', from: '#fff1e6', to: '#ffffff', art: '#ffe0c7', accent: '#e8641b', soft: '#fff4ea', ink: '#9a3f0c', line: '#ffd9bd' },
  teal:    { label: '청록', from: '#e5f6f6', to: '#ffffff', art: '#cdeeee', accent: '#0f8b8d', soft: '#eaf7f7', ink: '#0b5d5f', line: '#c6e8e8' },
  sky:     { label: '하늘', from: '#e7f3fd', to: '#ffffff', art: '#d2e9fb', accent: '#1f7fd1', soft: '#eef6fe', ink: '#14558f', line: '#cfe4f7' },
  indigo:  { label: '남색', from: '#ecedfc', to: '#ffffff', art: '#dcdffb', accent: '#4c55d6', soft: '#f1f2fd', ink: '#2f3596', line: '#d8dbf7' },
  slate:   { label: '회청', from: '#edf1f5', to: '#ffffff', art: '#dde5ee', accent: '#46617f', soft: '#f2f5f8', ink: '#2c3e55', line: '#d8e0ea' },
  violet:  { label: '보라', from: '#f3ebfd', to: '#ffffff', art: '#e6d7fb', accent: '#7c3aed', soft: '#f6f0fe', ink: '#5420ad', line: '#e3d4fa' },
  blue:    { label: '파랑', from: '#e8f0fe', to: '#ffffff', art: '#d4e3fd', accent: '#2563eb', soft: '#eef4ff', ink: '#1d4aa8', line: '#d2e0fb' },
  amber:   { label: '노랑', from: '#fff7e0', to: '#ffffff', art: '#ffecb3', accent: '#c98a00', soft: '#fff9e8', ink: '#7a5300', line: '#fbe3a6' },
  rose:    { label: '노을', from: '#fff0ea', to: '#ffffff', art: '#ffdcd0', accent: '#e05a3a', soft: '#fff3ef', ink: '#93361f', line: '#fbd5c8' },
  yellow:  { label: '금색', from: '#fff8db', to: '#ffffff', art: '#ffeea8', accent: '#b88400', soft: '#fffae6', ink: '#6e4f00', line: '#f7e29a' },
  pink:    { label: '분홍', from: '#fff0f5', to: '#ffffff', art: '#ffd9e6', accent: '#e0145a', soft: '#fff3f7', ink: '#a00d40', line: '#ffcfe0' },
}
export const themeOf = (k) => THEMES[k] || THEMES.emerald

// art = "💼 💵 📱" → { main, a, b }
export function artOf(cafe) {
  const parts = String(cafe?.art || '').trim().split(/\s+/).filter(Boolean)
  return { main: parts[0] || cafe?.icon || '💰', a: parts[1] || '✨', b: parts[2] || '💡' }
}

// 카페 → 선 아이콘 (이모지 대신. AppIcon 이름)
const CAFE_ICONS = { earn: 'briefcase', save: 'wallet', grow: 'trending-up', home: 'home', insurance: 'shield', tax: 'file-text',
  cards: 'credit-card', stocks: 'chart-bar', kids: 'graduation-cap', retire: 'sun', relay: 'trophy', talk: 'message-circle' }
export const cafeIcon = (slug) => CAFE_ICONS[slug] || 'coins'
