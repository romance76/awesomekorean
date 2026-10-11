<!-- 상위노출 카드 결제 공용 창 — usePromoCheckout().payPromotionByCard() 가 연다. App.vue 에 한 번만 둔다. -->
<template>
<Teleport to="body">
  <div v-if="s.open" class="fixed inset-0 z-[10001] bg-black/55 flex items-end sm:items-center justify-center" @click.self="!s.busy && cancelPromoCard()">
    <div class="bg-white w-full sm:max-w-md rounded-t-2xl sm:rounded-2xl p-5" role="dialog" aria-modal="true"
      :style="{ paddingBottom: 'calc(20px + env(safe-area-inset-bottom, 0px))' }">
      <div class="text-[17px] font-bold text-ink mb-1">상위노출 카드 결제</div>
      <div class="flex items-center justify-between bg-violet-50 rounded-xl px-4 py-3 mb-3">
        <span class="text-sm font-bold text-violet-700">{{ s.days }}일 노출</span>
        <span class="text-xl font-black text-violet-700">${{ (s.cents / 100).toFixed(2) }}</span>
      </div>
      <p class="text-[13px] text-ink-muted mb-3 leading-relaxed">카드를 확인하면 <b>바로 청구</b>되고 상위노출이 적용돼요. <b>카드 결제는 환불되지 않아요.</b> 인보이스는 마이페이지 › 결제에서 볼 수 있어요.</p>
      <StripeCardForm :client-secret="s.secret" button-label="카드 확인하고 결제·적용하기" @authorized="confirmPromoCard" />
      <p v-if="s.busy" class="text-xs text-ink-muted mt-2">결제를 마무리하는 중...</p>
      <p v-if="s.error" class="text-xs text-red-500 mt-2">{{ s.error }}</p>
      <button @click="cancelPromoCard" :disabled="s.busy" class="w-full mt-2 py-2.5 rounded-xl bg-gray-100 text-ink text-sm font-bold disabled:opacity-50">취소 (청구 없음)</button>
    </div>
  </div>
</Teleport>
</template>
<script setup>
import StripeCardForm from './StripeCardForm.vue'
import { promoState as s, confirmPromoCard, cancelPromoCard } from '../composables/usePromoCheckout'
</script>
