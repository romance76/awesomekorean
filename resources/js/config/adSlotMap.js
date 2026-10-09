/**
 * 광고 자리(페이지 × 위치) 단일 기준표 — 실제 <AdSlot page="..." position="..."> 사용처에서 도출.
 *
 *  - left  : 왼쪽 사이드바(카테고리 아래) 광고 자리
 *  - right : 오른쪽 사이드바 광고 자리
 *  - 여기 없는 페이지/위치는 "실제로 화면에 존재하지 않는 자리" → 광고 신청에서 제공하지 않는다.
 *  - 페이지 레이아웃에 <AdSlot> 을 추가/삭제하면 이 표도 같이 고칠 것
 *    (서버 쪽 동일 표: app/Support/AdSlotMap.php)
 */
export const AD_SLOT_MAP = {
  home:       ['left', 'right'],
  community:  ['left', 'right'],
  qa:         ['left', 'right'],
  news:       ['left', 'right'],
  recipes:    ['left', 'right'],
  groupbuy:   ['left', 'right'],
  market:     ['left', 'right'],
  jobs:       ['left', 'right'],
  realestate: ['left', 'right'],
  directory:  ['left', 'right'],
  clubs:      ['left', 'right'],
  // 이벤트 페이지는 오른쪽 열을 없애고 콘텐츠를 넓게 씀 → 왼쪽 자리만 존재
  events:     ['left'],
}

export function adPositionsOf(page) {
  return AD_SLOT_MAP[page] || []
}

export function adSlotExists(page, position) {
  return adPositionsOf(page).includes(position)
}
