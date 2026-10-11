// 관리자 전용 확인 시트 — 일반 useModal(사이트 전체 공용, 중앙 모달 한 종류)과 달리
// 위험도(kind)에 따라 색이 달라지는 확인창. ConfirmSheet.vue 가 이 상태를 그린다.
// 사용법: const { confirmAction } = useAdminConfirm()
//        const ok = await confirmAction({ title: '차단 풀기', message: '1.2.3.4 차단을 풀까요?', kind: 'danger' })
//        if (!ok) return
import { ref } from 'vue'

const visible = ref(false)
const title = ref('')
const message = ref('')
const kind = ref('normal') // normal(회색) | money(초록, 포인트·환불 등 돈 관련) | danger(빨강, 삭제·차단해제 등 되돌리기 어려운 동작)
const confirmLabel = ref('확인')
const cancelLabel = ref('취소')
let resolveFn = null

export function useAdminConfirm() {
  function confirmAction({ title: t = '', message: m = '', kind: k = 'normal', confirmLabel: cl = '확인', cancelLabel: kl = '취소' } = {}) {
    return new Promise(resolve => {
      title.value = t
      message.value = m
      kind.value = k
      confirmLabel.value = cl
      cancelLabel.value = kl
      visible.value = true
      resolveFn = resolve
    })
  }
  function onOk() { visible.value = false; resolveFn?.(true) }
  function onCancel() { visible.value = false; resolveFn?.(false) }
  return { visible, title, message, kind, confirmLabel, cancelLabel, confirmAction, onOk, onCancel }
}
