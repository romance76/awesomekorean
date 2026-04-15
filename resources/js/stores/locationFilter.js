/**
 * 페이지(섹션)별 위치 필터 상태 저장소.
 *
 * 동작:
 *  - 사용자가 /jobs 에서 "전국" 으로 바꾸면 그 선택은 /jobs 내 페이지 이동(list↔detail)에서 유지됨
 *  - 다른 섹션(/market, /realestate 등)으로 이동하면 해당 섹션의 상태는 초기화되어
 *    다음에 들어올 때 사용자의 기본 위치로 돌아감
 *
 * 섹션 경로 매핑은 getSection() 에서. 라우터 afterEach 훅이 '섹션 전환' 시
 * 이전 섹션의 필터 상태를 지워줌 (main.js 참고).
 *
 * 상태 형태:
 *   { jobs: { cityIdx: '-1', radius: '0' }, market: {...}, ... }
 *
 * cityIdx 규칙:
 *   '-2' = 내 위치 (기본)
 *   '-1' = 전국
 *   '0' 이상 = koreanCities 인덱스
 */
import { defineStore } from 'pinia'

// URL 경로에서 섹션 키 추출. 새 섹션 추가 시 여기에만 등록하면 됨.
export function getSection(path) {
  if (!path) return null
  if (path.startsWith('/jobs')) return 'jobs'
  if (path.startsWith('/market')) return 'market'
  if (path.startsWith('/realestate')) return 'realestate'
  if (path.startsWith('/directory')) return 'directory'
  if (path.startsWith('/community')) return 'community'
  if (path.startsWith('/events')) return 'events'
  if (path.startsWith('/groupbuy')) return 'groupbuy'
  if (path.startsWith('/qa')) return 'qa'
  if (path.startsWith('/recipes')) return 'recipes'
  if (path.startsWith('/shopping')) return 'shopping'
  return null
}

export const useLocationFilterStore = defineStore('locationFilter', {
  state: () => ({
    // { [sectionKey]: { cityIdx: string, radius: string } | null }
    filters: {},
  }),
  actions: {
    get(section) {
      return section && this.filters[section] ? this.filters[section] : null
    },
    set(section, { cityIdx, radius }) {
      if (!section) return
      this.filters[section] = { cityIdx: String(cityIdx), radius: String(radius) }
    },
    clear(section) {
      if (section) delete this.filters[section]
    },
    // 라우터 afterEach 에서 호출: from 섹션과 to 섹션이 다르면 from 을 초기화
    onRouteChange(toPath, fromPath) {
      const toSec = getSection(toPath)
      const fromSec = getSection(fromPath)
      if (fromSec && toSec !== fromSec) {
        this.clear(fromSec)
      }
    },
  },
})
