<!-- 내 잔액 한 줄: 포인트 · Entry · 카드 결제(이번 달·누적·승인 대기). 결제 화면(상위노출·광고 신청)에서 "지금 내가 얼마 갖고 있는지"를 같이 보여 준다.
     props: need = 이번 결제에 드는 포인트(포인트 결제일 때, 모자라면 빨간 안내), pay = 'points' | 'card' (지금 고른 결제 방법을 강조) -->
<template>
<div class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-[12px] leading-relaxed" aria-label="내 잔액">
  <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
    <span :class="pay === 'points' ? 'font-bold text-violet-700' : 'text-ink-muted'">내 포인트 <b class="tabular-nums text-ink">{{ auth.isUnlimited ? '∞' : points.toLocaleString() + 'P' }}</b></span>
    <span class="text-ink-muted">내 Entry <b class="tabular-nums text-ink">{{ auth.isUnlimited ? '∞' : entries.toLocaleString() }}</b></span>
    <span :class="pay === 'card' ? 'font-bold text-violet-700' : 'text-ink-muted'">카드 결제 이번 달 <b class="tabular-nums text-ink">${{ money(sum.paid_month) }}</b> · 누적 <b class="tabular-nums text-ink">${{ money(sum.paid_total) }}</b><template v-if="sum.held > 0"> · 승인 대기 <b class="tabular-nums text-ink">${{ money(sum.held) }}</b></template></span>
  </div>
  <p v-if="!auth.isUnlimited && pay === 'points' && need > 0 && points < need" class="mt-1 text-red-600 font-bold">포인트가 {{ (need - points).toLocaleString() }}P 모자라요. 카드 결제를 고르거나, 글·댓글 활동으로 모아 주세요.</p>
  <p v-else-if="!auth.isUnlimited && pay === 'points' && need > 0" class="mt-1 text-emerald-700">결제 후 포인트 {{ (points - need).toLocaleString() }}P 남아요.</p>
</div>
</template>
<script setup>
import { computed, reactive, onMounted } from 'vue'
import axios from 'axios'
import { useAuthStore } from '../stores/auth'

defineProps({ need: { type: Number, default: 0 }, pay: { type: String, default: '' } })
const auth = useAuthStore()
const points = computed(() => Number(auth.user?.points || 0))
const entries = computed(() => Number(auth.user?.entries || 0))
const sum = reactive({ paid_total: 0, paid_month: 0, held: 0 })
const money = (v) => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

// 같은 화면에서 여러 번 그려도 서버를 매번 부르지 않게 1분 기억
let cache = null
onMounted(async () => {
  if (cache && Date.now() - cache.at < 60000) { Object.assign(sum, cache.data); return }
  try {
    const { data } = await axios.get('/api/payments/history', { params: { per_page: 1 } })
    if (data.summary) { Object.assign(sum, data.summary); cache = { at: Date.now(), data: { ...data.summary } } }
  } catch {}
})
</script>
