<template>
  <div>
    <div ref="cardEl" class="border border-gray-200 rounded-xl px-3 py-3 bg-white"></div>
    <p v-if="error" class="text-xs text-red-500 mt-2">{{ error }}</p>
    <p v-if="loadError" class="text-xs text-red-500 mt-2">{{ loadError }}</p>
    <button type="button" @click="confirm" :disabled="busy || !ready"
      class="btn-primary w-full py-3 rounded-xl text-sm mt-3 disabled:opacity-50">
      {{ busy ? '확인 중...' : buttonLabel }}
    </button>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'

// 달러 직접 결제용 카드 입력 — "카드 보류(승인 후 청구)" PaymentIntent 의 client_secret 으로 카드를 확인한다.
// 보류가 잡히면(requires_capture) 'authorized' 를 알리고, 실제 청구/반려 처리는 서버가 한다.
const props = defineProps({
  clientSecret: { type: String, required: true },
  buttonLabel: { type: String, default: '카드 확인하고 신청하기' },
})
const emit = defineEmits(['authorized'])

const cardEl = ref(null)
const ready = ref(false)
const busy = ref(false)
const error = ref('')
const loadError = ref('')
let stripe = null
let card = null

async function loadStripeJs() {
  if (window.Stripe) return
  await new Promise((resolve, reject) => {
    const s = document.createElement('script')
    s.src = 'https://js.stripe.com/v3/'
    s.onload = resolve
    s.onerror = () => reject(new Error('Stripe 결제 모듈을 불러오지 못했어요. 네트워크를 확인해주세요.'))
    document.head.appendChild(s)
  })
}

onMounted(async () => {
  try {
    const key = document.querySelector('meta[name="stripe-key"]')?.content
    if (!key) { loadError.value = '카드 결제 설정이 아직 준비되지 않았어요. 관리자에게 문의해주세요.'; return }
    await loadStripeJs()
    stripe = window.Stripe(key)
    card = stripe.elements().create('card', { style: { base: { fontSize: '15px', color: '#1f2937' } } })
    card.mount(cardEl.value)
    ready.value = true
  } catch (e) {
    loadError.value = e.message || '결제 모듈을 불러오지 못했어요.'
  }
})
onBeforeUnmount(() => { try { card?.destroy() } catch {} })

async function confirm() {
  if (!stripe || !card) return
  busy.value = true
  error.value = ''
  const { error: err, paymentIntent } = await stripe.confirmCardPayment(props.clientSecret, { payment_method: { card } })
  if (err) { error.value = err.message || '카드를 확인하지 못했어요.'; busy.value = false; return }
  // manual capture 라서 정상이면 requires_capture (바로 청구되지 않고 보류만 잡힌 상태)
  if (paymentIntent.status === 'requires_capture' || paymentIntent.status === 'succeeded') emit('authorized', paymentIntent.id)
  else error.value = '카드 확인이 끝나지 않았어요 (상태: ' + paymentIntent.status + ')'
  busy.value = false
}
</script>
