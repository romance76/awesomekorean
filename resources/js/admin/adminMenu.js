// 관리자 메뉴 정의 — 단 하나의 원본(single source of truth).
// PC 사이드바·상단 서브탭과 휴대폰 하단 허브(AdminMobileMenu)가 모두 이 파일에서 메뉴를 가져온다.
// 새 관리자 화면을 추가할 때는 이 배열에 한 줄만 더하면 사이드바·휴대폰 허브·권한 필터에 자동 반영된다.
//
// 메뉴 개편 1단계(할 일 #82): 기존 5개 PC 그룹을 7개로 재구성했지만, 주소(path)는 하나도
// 바꾸지 않았고 어떤 관리자 화면도 지우지 않았다 — 분류(그룹)만 바뀐다.
// 휴대폰 하단 탭·허브(/admin/menu/:group)는 지금 쓰는 그룹 키(member/board/ad/system)를
// legacyGroup 으로 그대로 유지해 기존 북마크·동작이 전혀 바뀌지 않는다. 휴대폰 하단 탭
// 자체를 다시 꾸미는 일(홈/돈/신고·문의/콘텐츠/더보기)은 별도 할 일 #84 에서 한다.
//
// pcGroup: PC 사이드바·검색에 쓰는 새 7개 그룹 키
// legacyGroup: 휴대폰 허브(/admin/menu/:group)·하단 탭에 쓰는 기존 4개 그룹 키(member/board/ad/system)
// minTier: 이 화면을 보려면 필요한 최소 등급(2=관리자, 3=최고관리자) — 화면 숨김 판단에만 쓰고
//          실제 접근 차단은 서버 AdminTier 가 함(여기서는 UI 노출만 제어)
// mobile: 휴대폰용으로 새로 만든 화면이면 true(확대 없이 그대로), 아니면 legacy-zoom 으로 보정
// hold: 보류 중인 기능(게임·안심서비스) — 지우지 않고 그대로 두되 표시만

export const PC_GROUPS = [
  { key: 'home', label: '대시보드', to: '/admin', icon: 'chart-bar', chip: 'bg-amber-50 text-amber-600' },
  { key: 'member', label: '회원', to: '/admin/members', icon: 'users', chip: 'bg-blue-50 text-blue-600' },
  { key: 'content', label: '콘텐츠', to: '/admin/community', icon: 'list', chip: 'bg-emerald-50 text-emerald-600' },
  { key: 'money', label: '광고·매출', to: '/admin/ad-center', icon: 'megaphone', chip: 'bg-violet-50 text-violet-600' },
  { key: 'promo', label: '홍보·이벤트', to: '/admin/sweepstakes', icon: 'gift', chip: 'bg-pink-50 text-pink-600' },
  { key: 'support', label: '신고·문의', to: '/admin/security', icon: 'flag', chip: 'bg-red-50 text-red-600' },
  { key: 'system', label: '시스템', to: '/admin/settings', icon: 'settings', chip: 'bg-gray-100 text-ink-light' },
]

// 휴대폰 하단 탭 4그룹(홈 제외) — 기존 동작과 100% 동일하게 유지
export const LEGACY_MOBILE_GROUPS = {
  member: '회원', board: '게시판', ad: '광고', system: '시스템',
}

export const ADMIN_ITEMS = [
  // ── 회원 ──
  { to: '/admin/members', icon: 'users', label: '회원관리', pcGroup: 'member', legacyGroup: 'member', mobile: true },
  { to: '/admin/friends', icon: 'heart-handshake', label: '친구', pcGroup: 'member', legacyGroup: 'member', mobile: true },

  // ── 콘텐츠 ──
  { to: '/admin/community', icon: 'message-circle', label: '커뮤니티', pcGroup: 'content', legacyGroup: 'board', mobile: true },
  { to: '/admin/market', icon: 'shopping-cart', label: '장터', pcGroup: 'content', legacyGroup: 'board', mobile: true },
  { to: '/admin/jobs', icon: 'briefcase', label: '구인구직', pcGroup: 'content', legacyGroup: 'board', mobile: true },
  { to: '/admin/realestate', icon: 'building', label: '부동산', pcGroup: 'content', legacyGroup: 'board', mobile: true },
  { to: '/admin/qa', icon: 'help-circle', label: 'Q&A', pcGroup: 'content', legacyGroup: 'board', mobile: true },
  { to: '/admin/events', icon: 'calendar', label: '이벤트', pcGroup: 'content', legacyGroup: 'board', mobile: true },
  { to: '/admin/clubs', icon: 'users', label: '동호회', pcGroup: 'content', legacyGroup: 'board', mobile: true },
  { to: '/admin/recipes', icon: 'utensils', label: '레시피', pcGroup: 'content', legacyGroup: 'board', mobile: true },
  { to: '/admin/news', icon: 'newspaper', label: '뉴스', pcGroup: 'content', legacyGroup: 'board', mobile: true },
  { to: '/admin/info', icon: 'book-open', label: '정보', pcGroup: 'content', legacyGroup: 'board', mobile: true },
  { to: '/admin/money', icon: 'coins', label: 'MONEY', pcGroup: 'content', legacyGroup: 'board', mobile: true },
  { to: '/admin/directory', icon: 'store', label: '업소록', pcGroup: 'content', legacyGroup: 'board', mobile: true },
  { to: '/admin/groupbuy', icon: 'shopping-bag', label: '공동구매', pcGroup: 'content', legacyGroup: 'board', mobile: true },
  { to: '/admin/music', icon: 'music', label: '음악', pcGroup: 'content', legacyGroup: 'board', mobile: true },
  { to: '/admin/shorts', icon: 'video', label: '숏츠', pcGroup: 'content', legacyGroup: 'board', mobile: true },
  // 쇼핑 기능은 도입하지 않기로 확정(공동구매·중고장터로 대체) — 지우지 않고 메뉴에서만 숨김
  { to: '/admin/shopping', icon: 'shopping-cart', label: '쇼핑', pcGroup: 'content', legacyGroup: 'board', mobile: true, hold: true, hideFromTabs: true },
  { to: '/admin/chats', icon: 'message-square', label: '채팅', pcGroup: 'content', legacyGroup: 'board', mobile: true },
  { to: '/admin/games', icon: 'gamepad', label: '게임', pcGroup: 'content', legacyGroup: 'board', mobile: true, hold: true, hideFromTabs: true },
  // 포커는 독립 메뉴가 아니라 게임(/admin/games) 안의 하위 화면 — 상단 탭 목록에서는 제외하지만
  // 현재 그룹(subTabs) 판정에는 계속 쓰여야 하므로 목록 자체에는 남겨 둔다(hideFromTabs).
  { to: '/admin/poker', icon: 'coins', label: '포커', pcGroup: 'content', legacyGroup: 'board', mobile: true, hold: true, hideFromTabs: true },
  { to: '/admin/elder', icon: 'heart', label: '안심', pcGroup: 'content', legacyGroup: 'board', mobile: true, hold: true, hideFromTabs: true },
  { to: '/admin/communication', icon: 'phone', label: '채팅·통화', pcGroup: 'content', legacyGroup: 'board', mobile: true },

  // ── 광고·매출 ──
  { to: '/admin/ad-center', icon: 'sparkles', label: '광고 센터', pcGroup: 'money', legacyGroup: 'ad', mobile: true },
  { to: '/admin/banners', icon: 'megaphone', label: '광고 목록', pcGroup: 'money', legacyGroup: 'ad', mobile: true },
  { to: '/admin/flyers', icon: 'megaphone', label: 'NEW 전단 광고', pcGroup: 'money', legacyGroup: 'ad', mobile: true },
  { to: '/admin/pricing', icon: 'coins', label: '가격/할인', pcGroup: 'money', legacyGroup: 'ad', mobile: true },
  // 매출/결제 현황은 할 일 #90 에서 결제/오더 화면의 탭으로 통합됨 — 메뉴 항목은 하나로 합침(주소는 /admin/revenue → 리다이렉트로 유지)
  { to: '/admin/payments', icon: 'wallet', label: '결제/오더 · 매출 · 인보이스', pcGroup: 'money', legacyGroup: 'ad', mobile: true },
  // 돈과 연결된 지급을 한 곳에: 포인트·Entry 직접 지급 · 실제 받은 구매 금액 기록 · 보상 승인(예전 '신고·문의 › 보상 승인') · 지급 기록
  { to: '/admin/grants', icon: 'gift', label: '지급·보상 센터', pcGroup: 'money', legacyGroup: 'ad', mobile: true,
    isNew: true, newDesc: '회원을 찾아 포인트·Entry 지급, 실제로 받은 구매 금액 기록(인보이스 자동 발행), 보상 승인을 한 곳에서 합니다.' },

  // ── 홍보·이벤트 ──
  { to: '/admin/open-event', icon: 'gift', label: '오픈 이벤트', pcGroup: 'promo', legacyGroup: 'ad', mobile: true, minTier: 3,
    isNew: true, newDesc: '소프트 오픈 기간에 포인트 2배 · 채팅방 무료 · 광고 할인 등을 한 번에 켜는 화면입니다.' },
  { to: '/admin/social', icon: 'megaphone', label: 'SNS 게시 (인스타·스레드)', pcGroup: 'promo', legacyGroup: 'system', mobile: true, minTier: 3 },
  { to: '/admin/sweepstakes', icon: 'gift', label: '경품 추첨 관리', pcGroup: 'promo', legacyGroup: 'system', mobile: true, minTier: 3 },
  { to: '/admin/sweepstakes-rules', icon: 'book-open', label: '경품 추첨 공식 규정', pcGroup: 'promo', legacyGroup: 'system', mobile: true, minTier: 3 },
  { to: '/admin/hero-banners', icon: 'image', label: '히어로 배너', pcGroup: 'promo', legacyGroup: 'system', mobile: true },
  { to: '/admin/popup-banners', icon: 'message-square', label: '팝업 배너', pcGroup: 'promo', legacyGroup: 'system', mobile: true },

  // ── 신고·문의 ──
  { to: '/admin/security', icon: 'lock', label: '보안/신고', pcGroup: 'support', legacyGroup: 'system', mobile: true },
  { to: '/admin/claims', icon: 'flag', label: '클레임', pcGroup: 'support', legacyGroup: 'board', mobile: true },
  // (보상 승인은 '지급·보상 센터'로 합침 — 이 주소는 그쪽 탭으로 자동 이동, 메뉴에서는 숨김)
  { to: '/admin/rewards', icon: 'gift', label: '보상 승인', pcGroup: 'support', legacyGroup: 'board', mobile: true, hideFromTabs: true,
    isNew: true, newDesc: '이벤트 완료인증 · 레시피 인기보상을 관리자가 확인 후 지급하는 화면입니다.' },

  // ── 시스템 ──
  { to: '/admin/accounts', icon: 'mail', label: '계정·이메일 현황', pcGroup: 'system', legacyGroup: 'system', mobile: true },
  { to: '/admin/todos', icon: 'list', label: '할 일 목록', pcGroup: 'system', legacyGroup: 'system', mobile: true, minTier: 3 },
  { to: '/admin/audit-log', icon: 'shield', label: '감사 로그', pcGroup: 'system', legacyGroup: 'system', mobile: true, minTier: 3 },
  { to: '/admin/analytics', icon: 'chart-bar', label: '방문 분석', pcGroup: 'system', legacyGroup: 'system', mobile: true, minTier: 3 },
  { to: '/admin/settings', icon: 'settings', label: '설정', pcGroup: 'system', legacyGroup: 'system', mobile: true },
  { to: '/admin/entry-settings', icon: 'ticket', label: 'Entry 설정', pcGroup: 'system', legacyGroup: 'system', mobile: true },
  { to: '/admin/system', icon: 'monitor', label: '시스템', pcGroup: 'system', legacyGroup: 'system', mobile: true },
]

// 그룹(pcGroup)별 화면 목록 — PC 상단 서브탭, 검색에 사용
export function itemsByPcGroup(group) {
  return ADMIN_ITEMS.filter(i => i.pcGroup === group)
}

// 그룹(legacyGroup)별 화면 목록 — #82 당시 휴대폰 허브가 쓰던 옛 4그룹 매핑.
// #84 부터 휴대폰도 PC 와 같은 7그룹(pcGroup, itemsByPcGroup)을 쓰므로 AdminLayout.vue 에서는
// 더 쓰지 않지만, 각 항목의 legacyGroup 값 자체는 과거 분류 기록으로 그대로 둔다.
export function itemsByLegacyGroup(group) {
  return ADMIN_ITEMS.filter(i => i.legacyGroup === group)
}

// 모든 화면 라벨+주소로 찾기 — 메뉴 검색(향후 #83 공용 부품에서 사용 예정)
export function searchItems(keyword) {
  const k = String(keyword || '').trim().toLowerCase()
  if (!k) return []
  return ADMIN_ITEMS.filter(i => i.label.toLowerCase().includes(k))
}
