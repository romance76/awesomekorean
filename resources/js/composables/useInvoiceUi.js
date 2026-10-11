// 관리자 인보이스 화면(목록 탭 · 상세 시트 · 결제/오더 칩)이 같이 쓰는 표시 도우미

/** 센트 → $12.34 */
export const usdCents = (c) => '$' + (Number(c || 0) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

/** 시각 → 미국 동부시간(ET) 한 줄 */
export const fmtET = (d) => {
  if (!d) return ''
  const t = new Date(d)
  if (isNaN(t.getTime())) return ''
  return t.toLocaleString('en-US', { timeZone: 'America/New_York', year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) + ' ET'
}

/** 인보이스 상태 칩 */
export const INVOICE_STATUS = {
  issued: { text: '발행됨', cls: 'bg-green-50 text-green-700 border-green-200' },
  void: { text: '무효', cls: 'bg-red-50 text-red-600 border-red-200' },
}

/** 메일 발송 상태 칩 (delivery 가 없으면 "안 보냄") */
export const MAIL_STATUS = {
  sent: { text: '발송됨', cls: 'bg-green-50 text-green-700 border-green-200' },
  failed: { text: '발송 실패', cls: 'bg-red-50 text-red-600 border-red-200' },
  queued: { text: '발송 대기', cls: 'bg-amber-50 text-amber-700 border-amber-200' },
  none: { text: '안 보냄', cls: 'bg-gray-100 text-gray-500 border-gray-200' },
}
export const mailChip = (delivery) => MAIL_STATUS[delivery?.status] || MAIL_STATUS.none

export const KIND_TEXT = { points: '포인트 구매', flyer: 'NEW 전면광고', event_request: '경품 이벤트 의뢰' }
