export const RESUME_CATEGORIES = [
  { value: 'restaurant', label: '요식업' }, { value: 'it', label: 'IT' }, { value: 'beauty', label: '미용' },
  { value: 'driving', label: '운전' }, { value: 'retail', label: '판매' }, { value: 'office', label: '사무직' },
  { value: 'construction', label: '건설' }, { value: 'medical', label: '의료' }, { value: 'education', label: '교육' }, { value: 'etc', label: '기타' },
]
export const RESUME_TYPES = [
  { value: 'full', label: '풀타임' }, { value: 'part', label: '파트타임' }, { value: 'contract', label: '계약직' },
]
export function fmtBytes(n) {
  if (!n) return ''
  return n >= 1048576 ? (n / 1048576).toFixed(1) + 'MB' : Math.max(1, Math.round(n / 1024)) + 'KB'
}
