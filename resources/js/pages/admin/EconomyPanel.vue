<template>
<!-- 이코노미 한눈에: 포인트 · Entry · 매출 (슈퍼관리자 계정은 무제한이라 포인트·Entry 합계에서 뺀다) -->
<div class="bg-white border border-gray-100 rounded-2xl p-3.5 md:p-4 mb-3 md:mb-4">
  <div class="flex items-center gap-2 font-bold text-[15px] md:text-sm text-ink mb-3">
    <span class="icon-chip w-7 h-7 bg-amber-50 text-amber-600"><AppIcon name="coins" :size="14" /></span>이코노미
    <span v-if="report.economy_excluded_admins" class="ml-auto text-[11px] font-normal text-ink-faint">슈퍼관리자 {{ report.economy_excluded_admins }}명(무제한)은 포인트·Entry 합계에서 제외</span>
  </div>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
    <section v-for="s in sections" :key="s.key" class="rounded-xl border border-gray-100 p-3" :class="s.bg">
      <div class="flex items-center gap-1.5 text-[13px] font-bold text-ink mb-2">{{ s.icon }} {{ s.title }}</div>
      <div class="grid grid-cols-2 gap-x-3 gap-y-2">
        <div v-for="r in s.rows" :key="r.label">
          <div class="text-[11.5px] text-ink-muted">{{ r.label }}</div>
          <div class="text-[17px] font-bold tabular-nums leading-tight" :class="r.cls">{{ r.value }}</div>
        </div>
      </div>
    </section>
  </div>
</div>
</template>

<script setup>
import { computed } from 'vue'
import AppIcon from '../../components/AppIcon.vue'

const props = defineProps({ report: { type: Object, required: true } })

const n = v => Number(v || 0).toLocaleString()
const money = v => '$' + Number(v || 0).toLocaleString(undefined, { maximumFractionDigits: 2 })

const sections = computed(() => {
  const r = props.report
  const p = r.points || {}, e = r.entries || {}, m = r.payments || {}
  return [
    { key: 'points', icon: '🪙', title: '포인트', bg: 'bg-amber-50/40', rows: [
      { label: '누적 적립', value: '+' + n(p.total_issued), cls: 'text-green-600' },
      { label: '누적 소비', value: '-' + n(p.total_spent), cls: 'text-red-600' },
      { label: '오늘 적립', value: '+' + n(p.today_issued), cls: 'text-green-500' },
      { label: '오늘 소비', value: '-' + n(p.today_spent), cls: 'text-red-500' },
      { label: '회원 보유 합계', value: n(p.circulating) + 'P', cls: 'text-amber-600' },
    ] },
    { key: 'entries', icon: '🎟', title: 'Entry', bg: 'bg-violet-50/40', rows: [
      { label: '누적 지급', value: '+' + n(e.total_issued), cls: 'text-green-600' },
      { label: '누적 사용', value: '-' + n(e.total_spent), cls: 'text-red-600' },
      { label: '오늘 지급', value: '+' + n(e.today_issued), cls: 'text-green-500' },
      { label: '오늘 사용', value: '-' + n(e.today_spent), cls: 'text-red-500' },
      { label: '회원 보유 합계', value: n(e.circulating), cls: 'text-violet-600' },
    ] },
    { key: 'revenue', icon: '💵', title: '매출', bg: 'bg-blue-50/40', rows: [
      { label: '누적 매출', value: money(m.total_revenue), cls: 'text-blue-600' },
      { label: '이달 매출', value: money(m.month_revenue), cls: 'text-blue-500' },
      { label: '오늘 매출', value: money(m.today_revenue), cls: 'text-blue-500' },
      { label: '주문 (완료)', value: n(m.total_orders) + '건 (' + n(m.completed) + ')', cls: 'text-ink' },
      { label: '환불', value: n(m.refunded) + '건', cls: 'text-red-500' },
    ] },
  ]
})
</script>
