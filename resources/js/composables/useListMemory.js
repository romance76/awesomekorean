// 글 상세를 별도 주소(/market/123 …)로 여는 목록(장터·부동산·구인구직·레시피·동호회·공동구매)의 "뒤로가기 시 같은 목록" 처리.
// 상세에서 뒤로 오면 목록 화면이 새로 만들어져서 고른 분류·검색어·쪽 번호가 처음 상태로 돌아가 버린다.
// → 고른 값을 sessionStorage 에 적어 두고, "뒤로/앞으로로 돌아온 경우에만"(기록에 forward 가 남아 있음) 되살린다.
//   메뉴를 새로 눌러 들어온 경우는 평소처럼 처음 상태.
// 사용: const listMem = useListMemory('market', { activeCat, search, page })
//        onMounted 안의 첫 목록 불러오기를  loadPage(listMem.restore())  로 바꾼다(되살린 쪽 번호를 돌려줌, 없으면 1).
import { watch } from 'vue'

export function useListMemory(key, refs) {
  const K = 'listMem:' + key
  const names = Object.keys(refs)

  watch(names.map(n => () => refs[n].value), () => {
    try {
      const d = {}
      names.forEach(n => { d[n] = refs[n].value })
      sessionStorage.setItem(K, JSON.stringify(d))
    } catch { /* 저장이 막혀도 목록은 그대로 동작 */ }
  })

  function restore() {
    try {
      if (!window.history.state?.forward) return 1
      const d = JSON.parse(sessionStorage.getItem(K) || 'null')
      if (!d) return 1
      names.forEach(n => { if (n !== 'page' && n in d) refs[n].value = d[n] })
      return Number(d.page) > 0 ? Number(d.page) : 1
    } catch { return 1 }
  }

  return { restore }
}
