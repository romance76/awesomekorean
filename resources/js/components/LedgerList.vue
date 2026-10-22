<template>
<!-- 포인트·Entry 내역: 한 줄에 [아이콘] 내용(길면 줄바꿈) + 날짜 / 오른쪽에 증감. 가로로 밀리거나 글이 잘리지 않게 -->
<div class="rounded-2xl border border-gray-100 overflow-hidden">
  <ul class="max-h-[26rem] overflow-y-auto overflow-x-hidden divide-y divide-gray-100 bg-white">
    <li v-for="h in items" :key="h.id" class="flex items-center gap-3 px-3.5 py-3">
      <span class="shrink-0 w-9 h-9 rounded-xl grid place-items-center text-[17px]" :class="h.amount > 0 ? 'bg-emerald-50' : 'bg-red-50'" aria-hidden="true">{{ icon(textOf(h)) }}</span>
      <div class="min-w-0 flex-1">
        <div class="text-[14px] font-semibold text-ink leading-snug break-words">{{ textOf(h) }}</div>
        <div class="text-[12px] text-ink-muted mt-0.5">{{ fmt(h.created_at) }}</div>
      </div>
      <div class="shrink-0 text-right">
        <span class="inline-block min-w-[3.25rem] text-center text-[14px] font-extrabold tabular-nums px-2.5 py-1 rounded-full"
          :class="h.amount > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600'">{{ h.amount > 0 ? '+' : '' }}{{ Number(h.amount).toLocaleString() }}{{ unit }}</span>
        <div v-if="h.balance_after != null" class="text-[11px] text-ink-faint mt-0.5 tabular-nums">잔액 {{ Number(h.balance_after).toLocaleString() }}{{ unit }}</div>
      </div>
    </li>
  </ul>
</div>
</template>

<script setup>
const props = defineProps({
  items: { type: Array, default: () => [] },
  unit: { type: String, default: 'P' },                       // 'P' 또는 'E'
  field: { type: String, default: 'description' },            // 내용이 들어 있는 칸 (포인트는 reason, Entry 는 description)
  fmt: { type: Function, default: (d) => String(d || '').slice(5, 16).replace('T', ' ') },
})
const textOf = (h) => String(h[props.field] ?? h.description ?? h.reason ?? '')

// 내용에 들어 있는 낱말로 알아보기 쉬운 그림을 고른다
function icon(t) {
  if (/출석/.test(t)) return '📅'
  if (/가입|보너스/.test(t)) return '🎁'
  if (/리뷰|후기/.test(t)) return '🛍️'
  if (/공동구매/.test(t)) return '🤝'
  if (/응모|사용|차감|결제/.test(t)) return '🎟️'
  if (/댓글/.test(t)) return '💬'
  if (/글|게시/.test(t)) return '✏️'
  if (/좋아요/.test(t)) return '❤️'
  if (/환불/.test(t)) return '↩️'
  return '⭐'
}
</script>
