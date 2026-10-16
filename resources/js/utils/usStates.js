// 미국 주 인접 관계 — 서버 App\Support\UsStates 와 같은 파일(database/data/us_state_adjacency.json)을 읽는다.
import adjacency from '../../../database/data/us_state_adjacency.json'

export function normalizeState(s) {
  return String(s || '').trim().toUpperCase()
}

export function isUsState(s) {
  return Object.prototype.hasOwnProperty.call(adjacency, normalizeState(s))
}

// 작성자 주 + 인접 주 (자기 주가 맨 앞). 모르는 주면 빈 배열
export function stateRegion(s) {
  const st = normalizeState(s)
  if (!adjacency[st]) return []
  return [st, ...adjacency[st]]
}
