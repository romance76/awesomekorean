// 상위노출 카드 결제 — 어디서든 한 줄로 부르는 공용 창 (Kay 결정 2026-10-11: 상위노출은 포인트 또는 카드, 카드는 바로 청구·환불 없음)
// 사용법: const { payPromotionByCard } = usePromoCheckout()
//        const r = await payPromotionByCard({ resource: 'market', id: 12, tier: 'state_plus', days: 7 })
//        r.ok === true 면 결제·적용 완료, false 면 취소·실패(r.message)
// 창(PromoCheckoutModal.vue)은 App.vue 에 한 번만 심어 둔다.
import { reactive } from 'vue'
import axios from 'axios'

export const promoEndpoint = (r) => ({
  jobs: '/api/jobs', market: '/api/market', realestate: '/api/realestate',
  business: '/api/businesses', businesses: '/api/businesses', clubs: '/api/clubs',
}[r] || '/api/market')

export const promoState = reactive({
  open: false, secret: '', cents: 0, days: 0, busy: false, error: '',
  _ctx: null, _resolve: null,
})

function finish(result) {
  const r = promoState._resolve
  Object.assign(promoState, { open: false, secret: '', busy: false, error: '', _ctx: null, _resolve: null })
  r && r(result)
}

export function usePromoCheckout() {
  async function payPromotionByCard({ resource, id, tier, days }) {
    const endpoint = promoEndpoint(resource)
    let data
    try {
      ({ data } = await axios.post(`${endpoint}/${id}/promote`, { tier, days, pay_with: 'card' }))
    } catch (e) {
      return { ok: false, message: e.response?.data?.message || '카드 결제를 시작하지 못했어요' }
    }
    if (!data?.data?.needs_card) return { ok: !!data?.success, message: data?.message }
    return new Promise((resolve) => {
      Object.assign(promoState, {
        open: true, secret: data.data.client_secret, cents: data.data.total_cents, days, busy: false, error: '',
        _ctx: { endpoint, id, tier, days, paymentId: data.data.payment_id }, _resolve: resolve,
      })
    })
  }
  return { payPromotionByCard }
}

// 창에서 카드 확인이 끝나면 → 서버가 청구·적용
export async function confirmPromoCard() {
  const c = promoState._ctx
  if (!c) return
  promoState.busy = true; promoState.error = ''
  try {
    const { data } = await axios.post(`${c.endpoint}/${c.id}/promote/confirm`, { payment_id: c.paymentId, tier: c.tier, days: c.days })
    finish({ ok: true, message: data?.message })
  } catch (e) {
    promoState.busy = false
    promoState.error = e.response?.data?.message || '카드 결제를 마치지 못했어요'
  }
}

export function cancelPromoCard() {
  finish({ ok: false, message: '카드 결제를 취소했어요 (청구 없음)' })
}
