import { ref, computed, watch, onUnmounted } from 'vue'

/**
 * "화면 전체를 쓰는 창(채팅·쪽지 등)이 열려 있음" 신호.
 * 음악 미니 플레이어가 이 신호를 보고, 입력창을 가리지 않도록 아주 얇은 띠로 줄어든다.
 *
 * 사용: useImmersive(() => 이 창이 모바일 전체화면으로 열려 있을 때 true)
 */
const count = ref(0)
export const immersiveActive = computed(() => count.value > 0)

export function useImmersive(isActive) {
  let on = false
  function sync() {
    const a = !!isActive()
    if (a && !on) { count.value++; on = true }
    else if (!a && on) { count.value--; on = false }
  }
  watch(() => !!isActive(), sync, { immediate: true })
  onUnmounted(() => { if (on) { count.value--; on = false } })
}
