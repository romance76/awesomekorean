// 목록 화면 안에서 글 상세를 펼쳐 보는 메뉴(커뮤니티·Q&A·뉴스·업소록·장터·부동산·이벤트 …)의 뒤로가기 처리.
// 상세는 주소가 바뀌지 않아서, 그냥 두면 뒤로가기가 "목록"이 아니라 메뉴 밖(이전 화면)으로 나가 버린다.
// → 상세를 열 때 같은 주소의 기록을 하나 더 쌓고(표시 inlineDetail), 뒤로가기가 오면 그 상세만 닫는다.
//   화면의 '목록' 버튼 등으로 상세를 닫으면 쌓아 둔 기록도 함께 거둬서 기록이 늘어나지 않게 한다.
import { watch, onBeforeUnmount } from 'vue'

export function useInlineDetailBack(activeItem, close) {
  const marked = () => !!window.history.state?.inlineDetail

  watch(() => !!activeItem.value, (open) => {
    if (open) {
      if (!marked()) window.history.pushState({ ...window.history.state, inlineDetail: 1 }, '')
    } else if (marked()) {
      window.history.back()
    }
  })

  // 사용자가 뒤로가기를 눌러 표시 없는 기록으로 돌아왔는데 상세가 열려 있으면 상세만 닫는다
  const onPop = () => { if (activeItem.value && !marked()) close() }
  window.addEventListener('popstate', onPop)
  onBeforeUnmount(() => window.removeEventListener('popstate', onPop))
}
