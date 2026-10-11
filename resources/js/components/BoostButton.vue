<template>
<!--
  5개 리소스 공통 상위노출 버튼 + 모달.
  props:
    resource: 'market' | 'realestate' | 'jobs' | 'businesses' | 'clubs'
    item: 원본 아이템 객체 (boosted_until / promotion_tier / promotion_expires_at / state / category)
    size: 'sm' | 'md'
  emits:
    updated: 결제/해제 후 재로드 필요 시 부모가 처리
-->
<div class="boost-wrap">
  <!-- 활성 중 배너 -->
  <div v-if="isActive" class="boost-active">
    <AppIcon name="sparkles" :size="14" /> 상위노출 중 · {{ remaining }}
  </div>
  <!-- 비활성: 버튼 -->
  <button v-else @click="showModal = true"
    class="boost-btn" :class="sizeClass">
    <AppIcon name="sparkles" :size="14" /> 상위노출
  </button>

  <!-- 모달 -->
  <Teleport to="body">
    <div v-if="showModal" class="boost-modal-overlay" @click.self="showModal = false">
      <div class="boost-modal">
        <h3 class="modal-title"><span class="icon-chip w-8 h-8 bg-violet-50 text-violet-600"><AppIcon name="sparkles" :size="16" /></span>상위노출</h3>

        <div class="location-info">
          <AppIcon name="map-pin" :size="13" /> 내 지역({{ item?.state || '?' }} + 인접 주) 사용자에게 우선 노출됩니다
        </div>

        <!-- 결제 방법: 포인트 또는 카드 (Kay 결정 2026-10-11 — 카드는 바로 청구, 환불 없음) -->
        <MyBalanceStrip v-if="!cardSecret" :need="payWith === 'points' ? (selected?.price || 0) : 0" :pay="payWith" style="margin-bottom:10px;" />
        <div v-if="!cardSecret" class="pay-tabs" role="group" aria-label="결제 방법">
          <button type="button" :class="{ on: payWith === 'points' }" @click="payWith = 'points'">포인트</button>
          <button type="button" :class="{ on: payWith === 'card' }" @click="payWith = 'card'">카드 결제</button>
        </div>

        <template v-if="!cardSecret">
          <div class="days-grid">
            <button v-for="opt in options" :key="opt.days"
              @click="selected = opt"
              class="day-btn"
              :class="{ active: selected?.days === opt.days }">
              <div class="day-num">{{ opt.days }}일</div>
              <div class="day-price">{{ priceText(opt.price) }}</div>
            </button>
          </div>

          <div class="total-box">
            <span class="total-label">총 결제</span>
            <span class="total-value">{{ priceText(selected?.price || 0) }}</span>
          </div>
          <p v-if="payWith === 'card'" class="card-note">카드로 결제하면 바로 청구되고 상위노출이 적용돼요. <b>카드 결제는 최소 {{ cardMinText }} 이상부터 가능하고, 환불되지 않아요.</b> 인보이스는 결제 내역에서 볼 수 있어요.</p>
          <div v-if="payWith === 'card' && cardBelow" class="min-box">
            <p>카드는 최소 {{ cardMinText }} 부터라 지금 {{ priceText(selected?.price || 0) }} 로는 결제할 수 없어요. 아래에서 골라 주세요.</p>
            <button v-if="needDays && needDays <= 90" type="button" class="min-btn main" @click="useDays(needDays)">{{ needDays }}일로 늘리기 · {{ money(needDays * pricePerDay) }}</button>
            <button type="button" class="min-btn" @click="payWith = 'points'">포인트로 결제하기 · {{ Number(selected?.price || 0).toLocaleString() }}P</button>
          </div>

          <div class="modal-actions">
            <button @click="showModal = false" class="btn-cancel">취소</button>
            <button @click="pay" class="btn-pay" :disabled="paying || !selected || (payWith === 'card' && cardBelow)">
              {{ paying ? '처리 중...' : (payWith === 'card' ? '카드로 결제하기' : '포인트로 결제하기') }}
            </button>
          </div>
        </template>

        <!-- 카드 확인 단계 -->
        <div v-else>
          <div class="total-box">
            <span class="total-label">{{ selected?.days }}일 · 카드 결제</span>
            <span class="total-value">{{ money(cardCents) }}</span>
          </div>
          <StripeCardForm :client-secret="cardSecret" button-label="카드 확인하고 결제·적용하기" @authorized="confirmCard" />
          <button @click="cancelCard" class="btn-cancel" style="width:100%;margin-top:8px;" :disabled="paying">뒤로</button>
        </div>

        <div v-if="errorMsg" class="err-msg">{{ errorMsg }}</div>
      </div>
    </div>
  </Teleport>
</div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import axios from 'axios'
import { useSiteStore } from '../stores/site'
import AppIcon from './AppIcon.vue'
import StripeCardForm from './StripeCardForm.vue'
import MyBalanceStrip from './MyBalanceStrip.vue'
import { useCardLimits } from '../composables/useCardLimits'

const props = defineProps({
  resource: { type: String, required: true },
  item: { type: Object, required: true },
  size: { type: String, default: 'md' },
})
const emit = defineEmits(['updated'])
const site = useSiteStore()

const showModal = ref(false)
const paying = ref(false)
const errorMsg = ref('')
// 하루 가격은 서버 설정(관리자 › 가격 센터)을 읽는다. 숫자는 포인트이자 센트(100 = $1.00)
const { minCents: cardMin, minText: cardMinText } = useCardLimits()
const TIER = 'state_plus'
const pricePerDay = ref(100)
// 기본 1·3·7·14·30일 + "N일로 늘리기"로 고른 값(카드 최소 금액을 딱 넘기는 날 수)
const customDays = ref(0)
const options = computed(() => {
  const set = new Set([1, 3, 7, 14, 30])
  if (customDays.value) set.add(customDays.value)
  return [...set].sort((a, b) => a - b).map(days => ({ days, price: pricePerDay.value * days }))
})
const selected = ref(null)
const payWith = ref('points')
const cardBelow = computed(() => Math.max(50, Number(selected.value?.price || 0)) < cardMin.value)
// 카드 최소 금액을 넘기려면 며칠이어야 하는지
const needDays = computed(() => pricePerDay.value > 0 ? Math.ceil(cardMin.value / pricePerDay.value) : 0)
function useDays(n) { customDays.value = n; selected.value = options.value.find(o => o.days === n) || selected.value }
const cardSecret = ref('')
const cardPaymentId = ref(null)
const cardCents = ref(0)
const money = (c) => '$' + (Number(c || 0) / 100).toFixed(2)
const priceText = (p) => payWith.value === 'card' ? money(Math.max(50, p)) : Number(p || 0).toLocaleString() + 'P'

watch(showModal, async (open) => {
  if (!open) { cardSecret.value = ''; cardPaymentId.value = null; customDays.value = 0; paying.value = false; return }
  errorMsg.value = ''
  try {
    const key = props.resource === 'businesses' ? 'business' : props.resource
    const { data } = await axios.get('/api/promotion/settings', { params: { resource: key } })
    const p = Number(data?.data?.price_per_day?.[TIER])
    if (p > 0) pricePerDay.value = p
  } catch {}
  selected.value = options.value.find(o => o.days === (selected.value?.days || 1)) || options.value[0]
})

// 활성 여부 — modern tier (state_plus 등) 또는 legacy boosted_until 둘 다 체크
const isActive = computed(() => {
  const it = props.item || {}
  if (it.promotion_tier && it.promotion_tier !== 'none' && it.promotion_expires_at
      && new Date(it.promotion_expires_at) > new Date()) return true
  if (it.boosted_until && new Date(it.boosted_until) > new Date()) return true
  return false
})
const expiresAt = computed(() => {
  const it = props.item || {}
  return it.promotion_expires_at || it.boosted_until
})
const remaining = computed(() => {
  if (!expiresAt.value) return ''
  const diff = new Date(expiresAt.value) - new Date()
  if (diff <= 0) return '만료됨'
  const h = Math.floor(diff / 3_600_000)
  const d = Math.floor(h / 24)
  const remH = h - d * 24
  if (d >= 1) return `${d}일 ${remH}시간 남음`
  const m = Math.floor(diff / 60_000) - h * 60
  return `${h}시간 ${m}분 남음`
})

const sizeClass = computed(() => props.size === 'sm' ? 'btn-sm' : 'btn-md')

async function pay() {
  if (!selected.value) return
  paying.value = true
  errorMsg.value = ''
  try {
    // 모든 리소스 통일: state_plus tier (위치 기반) · 플랫 N일
    // 서버 HasPromotions 가 price_per_day 에 따라 차감 (point_settings)
    // 단순 UX를 위해 tier=state_plus 고정 (위치 기반 우선)
    const endpoint = endpointFor(props.resource)
    // 카드 결제 시작(보류 만들기)은 30초 안에 끝나야 함 — 연결이 끊겨 "처리 중..."에서 멈추지 않게 (청구는 이 단계에서 일어나지 않음)
    const { data } = await axios.post(`${endpoint}/${props.item.id}/promote`, {
      tier: TIER,
      days: selected.value.days,
      pay_with: payWith.value,
    }, { timeout: payWith.value === 'card' ? 30000 : 0 })   // 포인트는 서버가 바로 차감하므로 중간에 끊지 않음
    // 카드: 서버가 카드 보류를 만들고 돌려준 값으로 카드 확인 단계로 넘어감
    if (data?.data?.needs_card) {
      if (!showModal.value) { paying.value = false; return }   // 기다리는 동안 창을 닫았으면 이어가지 않음(청구 없음)
      cardSecret.value = data.data.client_secret
      cardPaymentId.value = data.data.payment_id
      cardCents.value = data.data.total_cents
      paying.value = false
      return
    }
    site.toast?.(data.message || '상위노출이 적용되었습니다', 'success')
    showModal.value = false
    emit('updated')
  } catch (e) {
    const msg = e.response?.data?.message
      || (e.code === 'ECONNABORTED' ? '연결이 느려서 처리하지 못했어요. 카드에는 청구되지 않았어요. 잠시 후 다시 시도해 주세요.' : '처리 실패')
    errorMsg.value = msg
    // 이미 활성 중이면 부모에게 알림 (아이템 리로드 유도)
    if (e.response?.data?.data?.already_active) {
      site.toast?.(msg, 'warning')
      showModal.value = false
      emit('updated')
    }
  }
  paying.value = false
}

async function confirmCard() {
  paying.value = true
  errorMsg.value = ''
  try {
    const { data } = await axios.post(`${endpointFor(props.resource)}/${props.item.id}/promote/confirm`, {
      payment_id: cardPaymentId.value, tier: TIER, days: selected.value.days,
    })
    site.toast?.(data.message || '상위노출이 적용되었습니다', 'success')
    showModal.value = false
    emit('updated')
  } catch (e) {
    errorMsg.value = e.response?.data?.message || '카드 결제를 마치지 못했어요'
    cardSecret.value = ''
  }
  paying.value = false
}
function cancelCard() { cardSecret.value = ''; cardPaymentId.value = null }

function endpointFor(r) {
  // URL 세그먼트 매핑
  return {
    market: '/api/market',
    realestate: '/api/realestate',
    jobs: '/api/jobs',
    businesses: '/api/businesses',
    clubs: '/api/clubs',
  }[r] || '/api/market'
}
</script>

<style scoped>
.boost-wrap { display: contents; }

.boost-btn {
  background: #8b5cf6;
  color: #fff; border: none; font-weight: 700; border-radius: 12px; cursor: pointer;
  display: inline-flex; align-items: center; justify-content: center; gap: 6px;
  transition: transform 0.15s, box-shadow 0.15s, background 0.15s;
}
.boost-btn:hover { background: #7c3aed; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(139, 92, 246, 0.3); }
.boost-btn.btn-md { padding: 10px 16px; font-size: 13px; width: 100%; }
.boost-btn.btn-sm { padding: 6px 12px; font-size: 12px; }

.boost-active {
  background: #f5f3ff;
  border: 1.5px solid #ddd6fe;
  color: #7c3aed;
  font-weight: 700;
  display: flex; align-items: center; justify-content: center; gap: 6px;
  border-radius: 12px;
  padding: 8px 14px;
  font-size: 12px;
}

.boost-modal-overlay {
  position: fixed; inset: 0; background: rgba(0, 0, 0, 0.55);
  z-index: 10000; display: flex; align-items: center; justify-content: center;
  padding: 16px;
}
.boost-modal {
  background: #fff; border-radius: 20px; padding: 22px;
  width: 100%; max-width: 380px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
}
.modal-title { font-size: 17px; font-weight: 700; color: #191F28; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
.location-info {
  background: #f5f3ff; color: #6d28d9; border-radius: 12px; padding: 10px 12px;
  font-size: 12px; font-weight: 600; margin-bottom: 14px;
  display: flex; align-items: flex-start; gap: 5px;
}

.days-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; margin-bottom: 14px; }
.day-btn {
  background: #f3f4f6; border: 1.5px solid transparent; border-radius: 12px;
  padding: 12px 8px; cursor: pointer; transition: all 0.15s; color: #4E5968;
}
.day-btn:hover { background: #ede9fe; }
.day-btn.active { background: #8b5cf6; border-color: #8b5cf6; color: #fff; }
.day-num { font-size: 15px; font-weight: 900; }
.day-price { font-size: 11px; margin-top: 4px; opacity: 0.85; }

.total-box {
  background: #f5f3ff;
  border-radius: 12px; padding: 12px 16px; margin-bottom: 14px;
  display: flex; justify-content: space-between; align-items: center;
}
.total-label { font-size: 13px; color: #7c3aed; font-weight: 700; }
.total-value { font-size: 20px; font-weight: 900; color: #7c3aed; }

.modal-actions { display: flex; gap: 8px; }
.btn-cancel, .btn-pay { flex: 1; padding: 12px; border-radius: 12px; font-weight: 700; font-size: 14px; border: none; cursor: pointer; transition: all 0.15s; }
.btn-cancel { background: #f3f4f6; color: #4E5968; }
.btn-cancel:hover:not(:disabled) { background: #e5e7eb; }
.btn-pay { background: #8b5cf6; color: #fff; }
.btn-pay:hover:not(:disabled) { background: #7c3aed; box-shadow: 0 4px 14px rgba(139, 92, 246, 0.35); }
.btn-pay:disabled { opacity: 0.5; cursor: not-allowed; }

.pay-tabs { display: grid; grid-template-columns: 1fr 1fr; gap: 4px; background: #f3f4f6; padding: 4px; border-radius: 12px; margin-bottom: 12px; }
.pay-tabs button { min-height: 40px; border: 0; border-radius: 9px; background: transparent; font-weight: 700; font-size: 14px; color: #4E5968; cursor: pointer; }
.pay-tabs button.on { background: #fff; color: #7c3aed; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
.card-note { font-size: 12px; color: #6b7280; line-height: 1.5; margin: -6px 0 12px; }
.min-box { margin: -4px 0 12px; padding: 10px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; }
.min-box p { margin: 0 0 8px; color: #dc2626; font-size: 12px; font-weight: 700; line-height: 1.5; }
.min-btn { display: block; width: 100%; min-height: 44px; margin-top: 6px; border-radius: 10px; border: 1.5px solid #c4b5fd; background: #fff; color: #6d28d9; font-size: 14px; font-weight: 700; cursor: pointer; }
.min-btn.main { background: #8b5cf6; border-color: #8b5cf6; color: #fff; }
.err-msg {
  margin-top: 10px; padding: 10px; background: #fef2f2; border: 1px solid #fecaca;
  border-radius: 10px; color: #dc2626; font-size: 12px; font-weight: 600; text-align: center;
}
</style>
