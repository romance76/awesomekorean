// JWT 의 만료 시각(exp)을 브라우저에서 읽는다 (서명 검증이 아니라 "언제 끝나는지" 확인용; 진짜 검증은 서버가 한다)
export function tokenExpiresAtMs(token) {
  try {
    const part = String(token).split('.')[1]
    if (!part) return null
    const json = decodeURIComponent(escape(atob(part.replace(/-/g, '+').replace(/_/g, '/'))))
    const exp = JSON.parse(json).exp
    return exp ? exp * 1000 : null
  } catch { return null }
}
