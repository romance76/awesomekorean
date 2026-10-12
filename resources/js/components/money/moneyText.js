// 머니 카페 글: 글쓰기 칸(일반 글자) ↔ 저장 형식(간단한 HTML 문단) — 서버가 한 번 더 정리(HtmlSanitizer)
const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')

export function textToHtml(text) {
  return String(text || '').trim().split(/\n{2,}/)
    .map(p => `<p>${esc(p).replace(/\n/g, '<br>')}</p>`).join('')
}

export function htmlToText(html) {
  const withBreaks = String(html || '').replace(/<\/p>\s*<p>/gi, '\n\n').replace(/<br\s*\/?>/gi, '\n')
  const el = document.createElement('div')
  el.innerHTML = withBreaks
  return (el.textContent || '').trim()
}

export const KIND_LABEL = { info: '정보', question: '질문', talk: '소통' }
export const KIND_CLASS = { info: 'bg-emerald-50 text-emerald-700', question: 'bg-amber-50 text-amber-700', talk: 'bg-sky-50 text-sky-700' }
export const DISCLAIMER = '여기 정보는 회원들의 경험과 일반 정보예요. 금융·세무·보험 결정은 꼭 전문가와 상담하세요.'
