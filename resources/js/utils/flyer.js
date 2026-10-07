// NEW 전단 광고 공용 — 시간 표기 / 종류 / 주 목록

export const KINDS = [
  { value: 'open', label: '🎉 신장개업' },
  { value: 'closing', label: '📦 폐업정리' },
  { value: 'sale', label: '🏷️ 세일·행사' },
  { value: 'etc', label: '📢 기타 소식' },
]

export function kindLabel(v) {
  return KINDS.find(k => k.value === v)?.label || '📢 소식'
}

export const US_STATES = [
  ['AL', 'Alabama'], ['AK', 'Alaska'], ['AZ', 'Arizona'], ['AR', 'Arkansas'], ['CA', 'California'],
  ['CO', 'Colorado'], ['CT', 'Connecticut'], ['DE', 'Delaware'], ['DC', 'Washington DC'], ['FL', 'Florida'],
  ['GA', 'Georgia'], ['HI', 'Hawaii'], ['ID', 'Idaho'], ['IL', 'Illinois'], ['IN', 'Indiana'],
  ['IA', 'Iowa'], ['KS', 'Kansas'], ['KY', 'Kentucky'], ['LA', 'Louisiana'], ['ME', 'Maine'],
  ['MD', 'Maryland'], ['MA', 'Massachusetts'], ['MI', 'Michigan'], ['MN', 'Minnesota'], ['MS', 'Mississippi'],
  ['MO', 'Missouri'], ['MT', 'Montana'], ['NE', 'Nebraska'], ['NV', 'Nevada'], ['NH', 'New Hampshire'],
  ['NJ', 'New Jersey'], ['NM', 'New Mexico'], ['NY', 'New York'], ['NC', 'North Carolina'], ['ND', 'North Dakota'],
  ['OH', 'Ohio'], ['OK', 'Oklahoma'], ['OR', 'Oregon'], ['PA', 'Pennsylvania'], ['RI', 'Rhode Island'],
  ['SC', 'South Carolina'], ['SD', 'South Dakota'], ['TN', 'Tennessee'], ['TX', 'Texas'], ['UT', 'Utah'],
  ['VT', 'Vermont'], ['VA', 'Virginia'], ['WA', 'Washington'], ['WV', 'West Virginia'], ['WI', 'Wisconsin'],
  ['WY', 'Wyoming'],
].map(([code, name]) => ({ code, name }))

export function stateName(code) {
  return US_STATES.find(s => s.code === String(code || '').toUpperCase())?.name || code
}

/** 0(·24) → 자정, 12 → 정오, 13 → 오후 1시 */
export function fmtHour(h) {
  const hh = ((Number(h) % 24) + 24) % 24
  if (hh === 0) return '자정'
  if (hh === 12) return '정오'
  return `${hh < 12 ? '오전' : '오후'} ${hh % 12}시`
}

/** [18,19,20,22] → ['오후 6시~오후 9시', '오후 10시~오후 11시'] (연속 구간으로 묶음, 끝은 그 시간이 끝나는 정각) */
export function hourRanges(hours) {
  const hs = [...new Set((hours || []).map(Number))].sort((a, b) => a - b)
  if (hs.length === 24) return ['24시간 종일']
  const out = []
  let i = 0
  while (i < hs.length) {
    let j = i
    while (j + 1 < hs.length && hs[j + 1] === hs[j] + 1) j++
    out.push(`${fmtHour(hs[i])}~${fmtHour(hs[j] + 1)}`)
    i = j + 1
  }
  return out
}

/** '2026-10-08' → '10월 8일 (수)' (타임존 영향 없이 날짜 문자열만 사용) */
export function fmtDay(dateStr) {
  if (!dateStr) return ''
  const [y, m, d] = String(dateStr).slice(0, 10).split('-').map(Number)
  const dow = ['일', '월', '화', '수', '목', '금', '토'][new Date(Date.UTC(y, m - 1, d)).getUTCDay()]
  return `${m}월 ${d}일 (${dow})`
}

export function tzLabel(tz) {
  return {
    'America/New_York': '동부(ET)', 'America/Chicago': '중부(CT)', 'America/Denver': '산악(MT)',
    'America/Phoenix': '애리조나(MST)', 'America/Los_Angeles': '서부(PT)',
    'America/Anchorage': '알래스카', 'Pacific/Honolulu': '하와이',
  }[tz] || tz
}

export const STATUS_LABEL = {
  pending: { text: '승인 대기', cls: 'bg-amber-50 text-amber-700 border-amber-200' },
  approved: { text: '게시 중', cls: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
  rejected: { text: '반려', cls: 'bg-red-50 text-red-600 border-red-200' },
  cancelled: { text: '취소', cls: 'bg-gray-100 text-gray-500 border-gray-200' },
}
