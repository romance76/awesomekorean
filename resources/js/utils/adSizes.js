/**
 * 사이드바 광고 이미지 규격 — 화면 표시(AdSlot)·안내(AdGuideBox)·신청(AdApply)이 모두 이 값을 쓴다.
 *
 * 이미지는 표시 영역 안에서 object-fit: cover 로 비율 10:7 로 잘려 보이고, 화면 폭이 좁으면 비례해서 작아진다.
 *  - 표시 크기(CSS px): 좌측 최대 약 193×135, 우측 최대 300×210 (PC 1280px 이상일 때, 더 좁으면 이보다 작게)
 *  - 업로드 권장: 표시 크기의 2배 (고해상도 화면에서도 선명) — 이보다 작아도 되지만 흐릿할 수 있음
 */
export const AD_SIZES = {
  left:  { label: '좌측 A', ratio: '200 / 140', w: 400, h: 280, minW: 200, minH: 140, display: '약 193×135' },
  right: { label: '우측 B', ratio: '300 / 210', w: 600, h: 420, minW: 300, minH: 210, display: '300×210' },
}

export const AD_RATIO_TEXT = '10:7'

export function adSize(position) {
  return AD_SIZES[position === 'left' ? 'left' : 'right']
}
