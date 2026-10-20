// 이벤트 카테고리 이름(한글)과 외부 행사 표시용 도우미 — 이벤트 목록·상세 화면이 함께 쓴다.
const NAMES = {
  festival: '지역 축제·행사',
  concert: '콘서트·공연',
  sports: '스포츠',
  community: '모임·커뮤니티',
  education: '교육·체험',
  other: '기타',
  // 예전 카테고리(이미 올라간 글 호환)
  culture: '문화',
  networking: '네트워킹',
  food: '음식',
  music: '음악·공연',
  religion: '종교',
  business: '비즈니스',
}

/** 카테고리 코드 → 한글 이름 (영어 코드가 그대로 보이지 않게) */
export function catName(code) {
  if (!code) return '기타'
  return NAMES[code] || code
}

/** 행사 일정: "10월 25일(토) 오후 7:00" (withTime=false 면 날짜만) */
export function evWhen(dt, withTime = true) {
  if (!dt) return ''
  const d = new Date(dt)
  const date = d.toLocaleDateString('ko-KR', { month: 'long', day: 'numeric', weekday: 'short' })
  if (!withTime) return date   // 공식 피드 행사는 날짜만(시간은 본문에 현지 시간으로 안내)
  const time = d.toLocaleTimeString('ko-KR', { hour: 'numeric', minute: '2-digit' })
  return `${date} ${time}`
}

/** 외부 사이트를 새 탭으로 연다 (우리 사이트 화면은 그대로 유지) */
export function openExternal(url) {
  if (!url) return
  // 'noopener' 를 features 로 주면 성공해도 null 이 돌아와 실패와 구분되지 않아서, 열린 뒤에 opener 를 끊는다
  const w = window.open(url, '_blank')
  if (w) { try { w.opener = null } catch (e) { /* 무시 */ } }
  else window.location.assign(url) // 팝업이 막힌 환경에서는 같은 창으로라도 열기
}
