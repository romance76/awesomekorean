// 목록 화면 안에서 글 상세를 펼쳐 보는 메뉴(커뮤니티·Q&A·뉴스·업소록·장터·부동산·이벤트 …)의 뒤로가기 처리.
// 상세는 주소가 바뀌지 않아서, 그냥 두면 뒤로가기가 "목록"이 아니라 메뉴 밖(이전 화면)으로 나가 버린다.
// → 상세를 열 때 같은 주소의 기록을 하나 더 쌓고(표시 inlineDetail), 뒤로가기가 오면 그 상세만 닫는다.
//   화면의 '목록' 버튼 등으로 상세를 닫으면 쌓아 둔 기록도 함께 거둬서 기록이 늘어나지 않게 한다.
//   목록에서 스크롤을 내려 글을 열었다면, 닫을 때 그 위치로 되돌린다(목록이 다시 그려지길 기다리며 몇 번 맞춤).
import { watch, onBeforeUnmount } from 'vue'

// keep: 글을 열 때 같이 바뀌어 버리는 목록 필터(예: 열린 글의 분류로 activeCat 이 바뀜)를 { 이름: ref } 로 넘기면, 닫을 때 원래 값으로 되돌린다.
export function useInlineDetailBack(activeItem, close, keep = {}) {
  const marked = () => !!window.history.state?.inlineDetail
  let listScrollY = 0
  let kept = {}
  let byBack = false   // 브라우저 뒤로가기로 닫는 중일 때만 필터·스크롤을 되돌린다(분류 버튼·'목록' 버튼으로 닫을 때는 그대로)

  function restoreScroll(y) {
    if (!(y > 0)) return
    let tries = 0
    const step = () => {
      window.scrollTo(0, y)
      if (Math.abs(window.scrollY - y) > 4 && tries++ < 25) setTimeout(step, 100)
    }
    setTimeout(step, 0)
  }

  watch(() => !!activeItem.value, (open) => {
    if (open) {
      listScrollY = window.scrollY
      kept = {}
      Object.keys(keep).forEach(k => { kept[k] = keep[k].value })
      if (!marked()) window.history.pushState({ ...window.history.state, inlineDetail: 1 }, '')
    } else {
      if (marked()) window.history.back()
      if (byBack) {
        Object.keys(kept).forEach(k => { keep[k].value = kept[k] })
        restoreScroll(listScrollY)
      }
    }
  }, { flush: 'sync' })   // 글을 여는 코드가 곧바로 맨 위로 스크롤하기 전에, 목록의 스크롤 위치를 먼저 기억해야 해서 즉시 실행

  // 상세를 펼친 채 다른 화면으로 갔다가 뒤로 오면, 쌓아 둔 기록(표시)이 남은 채로 목록이 새로 만들어진다.
  // 그대로 두면 "뒤로"를 한 번 더 눌러야 하므로, 상세가 닫힌 상태로 표시 기록에 서 있다면 바로 한 칸 물러서 정리한다.
  if (marked()) setTimeout(() => { if (!activeItem.value && marked()) window.history.back() }, 0)

  // 사용자가 뒤로가기를 눌러 표시 없는 기록으로 돌아왔는데 상세가 열려 있으면 상세만 닫는다
  const onPop = () => {
    if (activeItem.value && !marked()) { byBack = true; try { close() } finally { byBack = false } }
  }
  window.addEventListener('popstate', onPop)
  onBeforeUnmount(() => window.removeEventListener('popstate', onPop))
}
