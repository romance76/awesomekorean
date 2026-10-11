// 알림을 눌렀을 때 이동할 곳 — NavBar 알림 패널과 알림 페이지(Notifications.vue)가 같은 규칙을 쓴다.
// 관리자에게 가는 알림은 data.url 에 /admin/... 주소(필요하면 ?tab=…&status=… 포함)가 들어 있어서
// 일반 회원 화면이 아니라 해당 관리자 처리 화면이 바로 열린다.

// 사이트 안 주소만 허용 (다른 사이트·javascript: 같은 주소는 무시)
function safeInternal(url) {
  if (typeof url !== 'string') return ''
  const u = url.trim()
  if (!u) return ''
  if (/^\/(?!\/)/.test(u)) return u   // /admin/security?tab=reports&status=pending
  try {
    const x = new URL(u, window.location.origin)
    if (x.origin === window.location.origin) return x.pathname + x.search + x.hash
  } catch {}
  return ''
}

// 예전에 만들어져 data.url 이 없는 관리자 알림도 맞는 화면으로 보내기 위한 종류별 주소
const ADMIN_TYPE_ROUTES = {
  report_submitted: '/admin/security?tab=reports&status=pending',
  banner_submitted: '/admin/banners?status=pending',
  flyer_submitted: '/admin/flyers?status=pending',
  claim_submitted: '/admin/claims?status=pending',
  event_proof_submitted: '/admin/rewards?tab=events',
  prize_delivery_overdue: '/admin/sweepstakes?tab=pending',
}

// 알림 타입별 이동 경로 결정 (DB 스키마: 알림은 url 컬럼 없음, data JSON 로 추가 정보)
export function resolveNotifRoute(n) {
  const d = n.data || {}
  // 명시적 URL (일부 알림은 data.url 로 지정)
  const explicit = safeInternal(d.url)
  if (explicit) return explicit
  // 관리자 처리 알림 (예전 알림 포함)
  if (ADMIN_TYPE_ROUTES[n.type]) return ADMIN_TYPE_ROUTES[n.type]
  // 타입별 매핑
  if (n.type === 'message') return '/dashboard?tab=messages'
  if (n.type === 'friend_request') return '/friends'
  if (n.type === 'elder_call_missed') return '/elder/guardian'
  if (n.type === 'elder_checkin_missed') {
    return (d.elder_user_id || d.ward_id) ? '/elder/guardian' : '/elder/checkin'
  }
  if (n.type?.startsWith('elder_')) return '/elder'
  if (n.type === 'market_reservation_expired' || n.type === 'reservation_expired') {
    return d.item_id ? `/market/${d.item_id}` : '/market'
  }
  if (n.type === 'comment' && d.post_id) return `/community/post/${d.post_id}`
  if (n.type === 'system') return '/dashboard'
  // 폴백: 대시보드
  return '/dashboard'
}
