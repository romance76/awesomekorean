/**
 * Enter 로 전송하는 입력창용 공용 처리 (한글 IME 대응).
 *
 * 한글은 마지막 글자가 아직 조합 중일 때 Enter 를 누르면 keydown 이 isComposing=true(keyCode 229)로 온다.
 * 이를 그냥 무시하면 첫 Enter 는 글자만 확정하고 전송은 안 돼서 "엔터를 두 번 쳐야" 하는 문제가 생긴다.
 * → 조합 중 Enter 는 기억해 두었다가, 조합이 끝나는 순간(compositionend) 전송한다.
 *   (Safari 처럼 조합이 먼저 끝나고 keyCode 229 인 Enter 가 오는 경우는 바로 전송)
 *
 * 사용:
 *   const enter = useEnterSend(() => send())
 *   <textarea @keydown="enter.onKeydown" @compositionend="enter.onCompositionend" />
 *   인자가 필요하면 enter.onKeydown(e, parentId) 처럼 넘기면 submit(...args) 로 전달된다.
 */
export function useEnterSend(submit) {
  let pending = false
  let pendingArgs = []

  function onKeydown(e, ...args) {
    if (e.key !== 'Enter') { pending = false; return }
    if (e.shiftKey || e.ctrlKey || e.altKey || e.metaKey) return
    if (e.isComposing) {
      // Chrome/Firefox: 조합 중 Enter → 조합이 끝난 뒤 전송
      pending = true
      pendingArgs = args
      return
    }
    // Safari: 조합이 이미 끝났지만 keyCode 229 로 오는 Enter 도 정상 전송
    e.preventDefault()
    pending = false
    submit(...args)
  }

  function onCompositionend() {
    if (!pending) return
    const args = pendingArgs
    pending = false
    pendingArgs = []
    // v-model 이 최종 글자를 반영한 다음 전송
    setTimeout(() => submit(...args), 0)
  }

  return { onKeydown, onCompositionend }
}
