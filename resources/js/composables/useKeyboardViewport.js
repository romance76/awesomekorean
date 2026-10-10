import { ref, watch, onMounted, onUnmounted } from 'vue'

/**
 * 모바일 전체화면 채팅(position:fixed; inset:0)용 — 키보드가 올라올 때 입력창이 키보드 바로 위에 붙게 한다.
 *
 * iOS Safari 는 키보드가 올라와도 fixed 요소의 높이를 줄이지 않고 "페이지 전체를 위로 밀어" 입력칸을 보여준다.
 * 그러면 입력창이 위로 떠서 키보드와 입력창 사이가 크게 벌어진다.
 * → 키보드가 열려 있는 동안만 컨테이너를 "실제로 보이는 영역(visualViewport)"의 위치·높이로 맞추고
 *   뒤쪽 페이지가 같이 밀리지 않게 스크롤을 잠근다. 키보드가 없을 때는 아무것도 바꾸지 않는다(기존 inset:0).
 *
 * @param active  () => boolean — 이 화면이 모바일 전체화면으로 열려 있을 때 true
 * @returns { style: 컨테이너에 붙일 인라인 style 문자열(키보드 열림 때만 값 있음), keyboardOpen }
 */
export function useKeyboardViewport(active) {
  const style = ref('')
  const keyboardOpen = ref(false)
  let locked = false
  let prevBodyOverflow = ''

  function sync() {
    const v = typeof window !== 'undefined' ? window.visualViewport : null
    if (!v || !active()) { style.value = ''; keyboardOpen.value = false; return }
    const open = window.innerHeight - v.height > 120   // 키보드(+입력 보조 막대)가 올라온 상태
    keyboardOpen.value = open
    if (open) {
      style.value = `top:${Math.round(v.offsetTop)}px;height:${Math.round(v.height)}px;bottom:auto;`
      // 페이지 자체가 위로 밀려 있으면 되돌린다
      if (window.scrollY !== 0) window.scrollTo(0, 0)
    } else {
      style.value = ''
    }
  }

  function lock(on) {
    if (on === locked) return
    locked = on
    if (on) { prevBodyOverflow = document.body.style.overflow; document.body.style.overflow = 'hidden' }
    else { document.body.style.overflow = prevBodyOverflow }
  }

  watch(() => active(), (a) => { lock(!!a); sync() }, { immediate: true })

  onMounted(() => {
    const v = window.visualViewport
    if (!v) return
    v.addEventListener('resize', sync)
    v.addEventListener('scroll', sync)
    window.addEventListener('focusin', onFocus)
    sync()
  })
  onUnmounted(() => {
    const v = window.visualViewport
    if (v) { v.removeEventListener('resize', sync); v.removeEventListener('scroll', sync) }
    window.removeEventListener('focusin', onFocus)
    lock(false)
  })
  // 입력칸을 누른 직후에는 키보드 애니메이션이 끝난 뒤 한 번 더 맞춘다
  function onFocus() { setTimeout(sync, 50); setTimeout(sync, 300) }

  return { style, keyboardOpen }
}
