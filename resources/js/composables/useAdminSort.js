import { ref, computed } from 'vue'

/**
 * 관리자 표 정렬 상태.
 * - 기본: 번호(id) 내림차순 = 가장 마지막 번호가 맨 위
 * - 다른 열 머리글을 처음 누르면 오름차순 → 다시 누르면 내림차순 → 한 번 더 누르면 기본으로 복귀
 * - 번호 열(기본 키)은 오름/내림만 번갈아
 * onChange: 정렬이 바뀌면 호출(보통 1페이지부터 다시 불러오기)
 */
export function useAdminSort(onChange = () => {}, defaultKey = 'id', defaultDir = 'desc') {
  const sortKey = ref(defaultKey)
  const sortDir = ref(defaultDir)

  function toggleSort(k) {
    if (sortKey.value === k) {
      if (k === defaultKey) sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
      else if (sortDir.value === 'asc') sortDir.value = 'desc'
      else { sortKey.value = defaultKey; sortDir.value = defaultDir }   // 세 번째 누름: 기본 정렬로
    } else {
      sortKey.value = k
      sortDir.value = 'asc'
    }
    onChange()
  }

  // API 요청에 붙일 값 (기본 정렬이면 아예 보내지 않음)
  const sortParams = computed(() =>
    (sortKey.value === defaultKey && sortDir.value === defaultDir) ? {} : { sort: sortKey.value, dir: sortDir.value }
  )

  return { sortKey, sortDir, toggleSort, sortParams }
}
