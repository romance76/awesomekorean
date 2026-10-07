import DOMPurify from 'dompurify'

// 링크는 새 창 + noopener 로, 위험한 태그/속성(script, iframe, on*, style, javascript: …)은 모두 제거한다.
DOMPurify.addHook('afterSanitizeAttributes', (node) => {
  if (node.tagName === 'A' && node.getAttribute('href')) {
    node.setAttribute('rel', 'noopener noreferrer nofollow ugc')
    node.setAttribute('target', '_blank')
  }
})

/** v-html 로 보여줄 HTML 은 반드시 이 함수를 거친다 (회원/외부/관리자 입력 모두) */
export function sanitizeHtml(html) {
  return DOMPurify.sanitize(String(html ?? ''), {
    USE_PROFILES: { html: true },
    FORBID_TAGS: ['style', 'form', 'input', 'button', 'textarea', 'select', 'option', 'iframe', 'object', 'embed', 'svg', 'math', 'audio', 'video', 'source', 'link', 'meta'],
    FORBID_ATTR: ['style', 'srcset'],
    ALLOW_DATA_ATTR: false,
  })
}
