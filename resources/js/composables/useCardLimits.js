// 카드 결제 최소 금액 — 관리자 › 사이트 설정 › 결제/알림 설정 › "최소 결제금액"(기본 $5)을 서버에서 읽어 온다.
// 결제 화면이 "카드 결제는 최소 $5.00 부터" 안내와 사전 확인에 쓴다. (서버도 같은 기준으로 한 번 더 막는다)
import { ref, computed } from 'vue'
import axios from 'axios'

const minCents = ref(500)
let loaded = false
let pending = null

export function loadCardLimits() {
  if (loaded) return Promise.resolve()
  if (!pending) {
    pending = axios.get('/api/payments/card-limits')
      .then(({ data }) => { const v = Number(data?.data?.min_cents); if (v > 0) minCents.value = v; loaded = true })
      .catch(() => {})
      .finally(() => { pending = null })
  }
  return pending
}

export function useCardLimits() {
  loadCardLimits()
  const minText = computed(() => '$' + (minCents.value / 100).toFixed(2))
  return { minCents, minText, below: (cents) => Number(cents || 0) < minCents.value }
}
