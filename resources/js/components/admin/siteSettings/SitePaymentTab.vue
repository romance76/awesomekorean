<template>
  <div>
  <div class="card p-6 mb-4">
    <div class="flex items-center justify-between mb-5">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
          <span class="text-lg font-bold text-indigo-600">S</span>
        </div>
        <div>
          <h2 class="text-base font-semibold text-ink">Stripe API 키 설정</h2>
          <p class="text-xs text-ink-faint">결제 처리를 위한 Stripe API 키 관리</p>
        </div>
      </div>
      <span :class="[
        payment.stripe_publishable_key ? 'badge-green' : 'badge-red'
      ]">
        {{ payment.stripe_publishable_key ? 'API 키 설정됨' : '미설정' }}
      </span>
    </div>

    <!-- 테스트/라이브 모드 토글 -->
    <div class="flex items-center gap-4 mb-5 p-3 bg-gray-50 rounded-xl">
      <span class="text-sm text-ink-light font-medium">결제 모드:</span>
      <label class="relative inline-flex items-center cursor-pointer">
        <input type="checkbox" v-model="payment.stripe_test_mode" class="sr-only peer" />
        <div class="w-11 h-6 bg-green-500 peer-checked:bg-yellow-400 rounded-full transition-colors after:content-[''] after:absolute after:top-0.5 after:left-[2px] peer-checked:after:translate-x-full after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
      </label>
      <span :class="[
        payment.stripe_test_mode ? 'badge-primary' : 'badge-green'
      ]">
        {{ payment.stripe_test_mode ? '테스트 모드' : '라이브 (실결제)' }}
      </span>
    </div>

    <!-- Stripe API 키 입력 -->
    <div class="space-y-4 mb-5">
      <div>
        <label class="input-label">Publishable Key</label>
        <input
          v-model="payment.stripe_publishable_key"
          type="text"
          class="input-field font-mono text-sm"
          :placeholder="payment.stripe_test_mode ? 'pk_test_...' : 'pk_live_...'"
        />
        <p class="text-xs text-ink-faint mt-1">프론트엔드에서 사용되는 공개 키입니다</p>
      </div>
      <div>
        <label class="input-label">Secret Key</label>
        <div class="relative">
          <input
            v-model="payment.stripe_secret_key"
            :type="showStripeSecret ? 'text' : 'password'"
            class="input-field font-mono text-sm pr-20"
            :placeholder="payment.stripe_test_mode ? 'sk_test_...' : 'sk_live_...'"
          />
          <button
            type="button"
            @click="showStripeSecret = !showStripeSecret"
            class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-ink-muted hover:text-ink px-2 py-1 rounded transition-colors"
          >
            {{ showStripeSecret ? '숨기기' : '보기' }}
          </button>
        </div>
        <p class="text-xs text-ink-faint mt-1">서버에서 사용되는 비밀 키입니다 (절대 노출하지 마세요)</p>
      </div>
      <div>
        <label class="input-label">Webhook Secret <span class="text-ink-faint">(선택)</span></label>
        <input
          v-model="payment.stripe_webhook_secret"
          type="password"
          class="input-field font-mono text-sm"
          placeholder="whsec_..."
        />
        <p class="text-xs text-ink-faint mt-1">Webhook 이벤트 검증용 (설정 시 보안 강화)</p>
      </div>
    </div>

    <div class="flex justify-end">
      <button @click="saveStripeKeys" :disabled="savingStripe" class="btn-primary">
        <span v-if="savingStripe" class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></span>
        Stripe 키 저장
      </button>
    </div>
  </div>

  <!-- 결제 일반 설정 -->
  <div class="card p-6 mb-4">
    <div class="flex items-center justify-between mb-5">
      <h2 class="text-base font-semibold text-ink">결제 시스템 연결</h2>
      <span :class="[
        payment.stripe_connected ? 'badge-green' : 'badge-red'
      ]">
        {{ payment.stripe_connected ? '연결됨' : '미연결' }}
      </span>
    </div>

    <!-- Stripe 연동 상태 -->
    <div class="border border-gray-100 rounded-xl p-4 mb-5">
      <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
            <span class="text-lg font-bold text-indigo-600">S</span>
          </div>
          <div>
            <p class="text-sm font-semibold text-ink">Stripe</p>
            <p class="text-xs text-ink-faint">결제 처리 서비스</p>
          </div>
        </div>
        <a href="/admin/payments" class="inline-flex items-center gap-1 px-4 py-2 bg-indigo-500 hover:bg-indigo-600 text-white text-sm font-medium rounded-lg transition-colors">
          {{ payment.stripe_connected ? 'Stripe 설정 변경' : 'Stripe 연동하기' }}
          <span class="text-xs">&rarr;</span>
        </a>
      </div>

      <!-- 결제 모드 -->
      <div class="flex items-center gap-4 mt-3 pt-3 border-t border-gray-100">
        <span class="text-sm text-ink-light">결제 모드:</span>
        <span :class="[
          payment.mode === 'live' ? 'badge-green' : 'badge-primary'
        ]">
          {{ payment.mode === 'live' ? '라이브 (실결제)' : '테스트 모드' }}
        </span>
      </div>
    </div>

    <!-- 결제 수단 현황 -->
    <div class="mb-5">
      <h3 class="text-sm font-semibold text-ink-muted mb-3">결제 수단 현황</h3>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="flex items-center justify-between border border-gray-100 rounded-xl p-3">
          <div class="flex items-center gap-2">
            <span class="text-lg">&#128179;</span>
            <span class="text-sm text-ink-light">카드 결제</span>
          </div>
          <span :class="[payment.card_enabled ? 'badge-green' : 'badge-gray']">
            {{ payment.card_enabled ? '활성' : '비활성' }}
          </span>
        </div>
        <div class="flex items-center justify-between border border-gray-100 rounded-xl p-3">
          <div class="flex items-center gap-2">
            <span class="text-lg">&#127822;</span>
            <span class="text-sm text-ink-light">Apple Pay</span>
          </div>
          <span :class="[payment.apple_pay_enabled ? 'badge-green' : 'badge-gray']">
            {{ payment.apple_pay_enabled ? '활성' : '비활성' }}
          </span>
        </div>
        <div class="flex items-center justify-between border border-gray-100 rounded-xl p-3">
          <div class="flex items-center gap-2">
            <span class="text-lg">G</span>
            <span class="text-sm text-ink-light">Google Pay</span>
          </div>
          <span :class="[payment.google_pay_enabled ? 'badge-green' : 'badge-gray']">
            {{ payment.google_pay_enabled ? '활성' : '비활성' }}
          </span>
        </div>
      </div>
    </div>

    <!-- 최소 결제금액 / 통화 설정 -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
      <div>
        <label class="input-label">최소 결제금액</label>
        <div class="flex items-center gap-2">
          <input v-model.number="payment.min_amount" type="number" min="0" step="0.01" class="input-field w-32" />
          <span class="text-sm text-ink-muted">{{ payment.currency }}</span>
        </div>
      </div>
      <div>
        <label class="input-label">기본 통화</label>
        <select v-model="payment.currency" class="input-field">
          <option value="USD">USD (미국 달러)</option>
          <option value="KRW">KRW (한국 원)</option>
          <option value="EUR">EUR (유로)</option>
          <option value="JPY">JPY (일본 엔)</option>
        </select>
      </div>
    </div>

    <!-- 이번달 결제 요약 -->
    <div class="bg-gray-50 rounded-xl p-4">
      <h3 class="text-sm font-semibold text-ink-muted mb-3">이번달 결제 요약</h3>
      <div class="grid grid-cols-2 gap-4">
        <div class="bg-white rounded-xl p-3 border border-gray-100">
          <p class="text-xs text-ink-muted mb-1">총 결제 금액</p>
          <p class="text-lg font-bold text-ink">
            {{ payment.currency === 'USD' ? '$' : '' }}{{ payment.this_month_total.toLocaleString() }}
            <span v-if="payment.currency !== 'USD'" class="text-sm font-normal text-ink-muted">{{ payment.currency }}</span>
          </p>
        </div>
        <div class="bg-white rounded-xl p-3 border border-gray-100">
          <p class="text-xs text-ink-muted mb-1">결제 성공률</p>
          <p class="text-lg font-bold" :class="payment.this_month_success_rate >= 90 ? 'text-green-600' : payment.this_month_success_rate >= 70 ? 'text-yellow-600' : 'text-red-600'">
            {{ payment.this_month_success_rate }}%
          </p>
        </div>
      </div>
    </div>

    <div class="mt-4 flex justify-end">
      <button @click="savePayment" :disabled="saving" class="btn-primary">
        <span v-if="saving" class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></span>
        결제 설정 저장
      </button>
    </div>
  </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

// 결제 탭(Stripe 키 + 결제 설정) — 값(payment)과 저장 API 호출은 SiteSettings.vue 가 맡고, 여기서는 화면만 그린다.
const props = defineProps({
  payment: { type: Object, required: true },
  saving: { type: Boolean, default: false },
  savingStripe: { type: Boolean, default: false },
})
const emit = defineEmits(['save-stripe', 'save-payment'])
const payment = props.payment
const showStripeSecret = ref(false)
const saveStripeKeys = () => emit('save-stripe')
const savePayment = () => emit('save-payment')
</script>

<style scoped>
/* 사이트 설정 화면과 동일한 소프트 입력 스타일 (SiteSettings.vue 와 같은 값) */
.input-field {
  @apply w-full bg-[#F4F6F8] border-[1.5px] border-transparent rounded-xl px-3 py-2 text-sm text-ink placeholder:text-ink-faint outline-none transition-all duration-150 focus:bg-white focus:border-amber-400 focus:ring-4 focus:ring-amber-400/10;
}
</style>
