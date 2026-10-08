import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from 'axios'
import router from '../router'

/**
 * "광고 위치 확인하기" 안내 모드.
 * 켜면 현재 페이지의 광고 자리(프리미엄/스탠다드/텍스트 인라인)가 점선 박스와 가격으로 표시된다.
 */
export const useAdGuideStore = defineStore('adGuide', () => {
  const on = ref(false)
  // AdApply.vue 의 기본값과 같은 구성 — 서버 설정(slot_min_prices)이 오면 덮어쓴다
  const prices = ref({
    left_premium: 8000, left_standard: 5000,
    right_premium: 10000, right_standard: 7000,
    'inline-text_text': 1000,
  })
  const discountPct = ref(0)
  let loaded = false

  async function loadPrices() {
    if (loaded) return
    loaded = true
    try {
      const { data } = await axios.get('/api/ad-settings/public')
      if (data.data?.slot_min_prices) prices.value = { ...prices.value, ...data.data.slot_min_prices }
    } catch {}
    try {
      const { data } = await axios.get('/api/pricing-promotions/active')
      discountPct.value = data?.data?.ad?.discount_pct || 0
    } catch {}
  }

  function priceOf(key) {
    const base = prices.value[key] || 0
    return discountPct.value ? Math.round(base * (100 - discountPct.value) / 100) : base
  }

  function onKey(e) { if (e.key === 'Escape') close() }
  function open() { on.value = true; loadPrices(); window.addEventListener('keydown', onKey) }
  function close() { on.value = false; window.removeEventListener('keydown', onKey) }

  // 다른 페이지로 이동하면 안내 모드 해제
  router.afterEach(() => { if (on.value) close() })
  function toggle() { on.value ? close() : open() }

  return { on, prices, discountPct, priceOf, open, close, toggle }
})
