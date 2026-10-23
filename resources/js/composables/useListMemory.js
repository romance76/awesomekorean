// 글 상세를 별도 주소(/market/123 …)로 여는 목록(장터·부동산·구인구직·레시피·동호회·공동구매)의 "뒤로가기 시 같은 목록" 처리.
// 상세에서 뒤로 오면 목록 화면이 새로 만들어져서 고른 분류·검색어·쪽 번호가 처음 상태로 돌아가 버린다.
// → 고른 값을 sessionStorage 에 적어 두고, "뒤로/앞으로로 돌아온 경우에만"(기록에 forward 가 남아 있음) 되살린다.
//   메뉴를 새로 눌러 들어온 경우는 평소처럼 처음 상태.
// 사용: const listMem = useListMemory('market', { activeCat, search, page })
//        onMounted 안의 첫 목록 불러오기를  loadPage(listMem.restore())  로 바꾼다(되살린 쪽 번호를 돌려줌, 없으면 1).
import { watch, onBeforeUnmount } from 'vue'

export function useListMemory(key, refs) {
  const K = 'listMem:' + key
  const names = Object.keys(refs)
  // 뒤로·앞으로 돌아온 경우의 이전 기록은 만들어지는 즉시 읽어 둔다(아래 save() 가 덮어쓰기 전에)
  let prev = null
  try { if (window.history.state?.forward) prev = JSON.parse(sessionStorage.getItem(K) || 'null') } catch { prev = null }

  function save() {
    try {
      const d = {}
      names.forEach(n => { d[n] = refs[n].value })
      sessionStorage.setItem(K, JSON.stringify(d))
    } catch { /* 저장이 막혀도 목록은 그대로 동작 */ }
  }
  // 처음 상태(레시피의 무작위 seed 등)도 기록해 둬야, 아무것도 안 바꾸고 글만 보고 와도 같은 모습으로 돌아온다
  if (!prev) save()
  watch(names.map(n => () => refs[n].value), save)
  onBeforeUnmount(save)

  function restore() {
    try {
      const d = prev
      if (!d) return 1
      names.forEach(n => { if (n !== 'page' && n in d) refs[n].value = d[n] })
      return Number(d.page) > 0 ? Number(d.page) : 1
    } catch { return 1 }
  }

  return { restore }
}
