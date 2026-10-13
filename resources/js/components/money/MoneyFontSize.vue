<template>
<!-- 글씨 크기 선택: 보통 / 크게 / 아주 크게. 고른 크기는 이 기기에 기억해 다음 글에도 그대로 적용 -->
<div class="inline-flex items-center gap-1.5" role="group" aria-label="글씨 크기">
  <span class="text-[12px] font-bold text-ink-muted mr-0.5">글씨</span>
  <button v-for="o in OPTIONS" :key="o.v" type="button" @click="moneyFont.set(o.v)"
    class="rounded-lg border px-2.5 py-1 font-extrabold leading-none transition"
    :class="[o.cls, moneyFont.level.value === o.v ? 'bg-emerald-600 border-emerald-600 text-white' : 'bg-white border-gray-200 text-ink hover:bg-gray-50']"
    :aria-pressed="moneyFont.level.value === o.v">{{ o.label }}</button>
</div>
</template>

<script setup>
import { moneyFont } from './moneyFont'
const OPTIONS = [
  { v: 0, label: '가', cls: 'text-[13px]' },
  { v: 1, label: '가', cls: 'text-[17px]' },
  { v: 2, label: '가', cls: 'text-[22px]' },
]
</script>
