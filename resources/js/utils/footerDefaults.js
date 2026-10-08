// 사이트 푸터의 기본 내용 — 실제 사이트 푸터(App.vue)와 관리자 "푸터 편집"이 같은 값에서 시작하도록 한 곳에 둔다.
// 관리자가 저장한 설정(footer_config, version 2)이 있으면 그걸 쓰고, 없으면 이 기본값을 쓴다.
export const FOOTER_VERSION = 2

export const DEFAULT_FOOTER = {
  version: FOOTER_VERSION,
  tagline: '미국 한인 No.1 커뮤니티',
  columns: [
    { title: '서비스', links: [
      { label: '커뮤니티', url: '/community' }, { label: '구인구직', url: '/jobs' },
      { label: '중고장터', url: '/market' }, { label: '업소록', url: '/directory' },
    ] },
    { title: '콘텐츠', links: [
      { label: '뉴스', url: '/news' }, { label: '레시피', url: '/recipes' },
      { label: '게임', url: '/games' }, { label: '음악', url: '/music' },
    ] },
    { title: '안내', links: [
      { label: '소개', url: '/about' }, { label: '문의하기', url: '/contact' }, { label: '이용약관', url: '/terms' }, { label: '개인정보처리방침', url: '/privacy' },
    ] },
  ],
  copyright: '© 2026 AwesomeKorean. All rights reserved.',
  sns: { facebook: '', instagram: '', twitter: '', youtube: '', kakao: '' },
  additional_text: '',
}

// 저장된 값(문자열/객체)을 읽어서 쓸 수 있으면 돌려주고, 아니면 null (예전 방식으로 저장된 값은 무시)
export function readSavedFooter(raw) {
  try {
    const v = typeof raw === 'string' ? JSON.parse(raw) : raw
    if (v && v.version === FOOTER_VERSION && Array.isArray(v.columns)) return v
  } catch {}
  return null
}
