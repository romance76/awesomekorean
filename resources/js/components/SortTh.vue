<template>
<!-- 눌러서 정렬하는 표 머리글. 클래스는 부모가 준 것(px-3 py-2 text-left …)을 그대로 쓴다 -->
<th :aria-sort="active ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none'" class="select-none whitespace-nowrap">
  <button type="button" @click="$emit('sort', k)"
    class="inline-flex items-center gap-1 transition-colors hover:text-ink focus-visible:outline-none"
    :class="active ? 'text-ink font-bold' : ''"
    :title="active ? (sortDir === 'asc' ? '오름차순 (누르면 내림차순)' : '내림차순 (누르면 오름차순)') : '눌러서 정렬'">
    <slot />
    <span class="text-[9px] leading-none" :class="active ? 'text-amber-500' : 'text-gray-300'" aria-hidden="true">{{ active ? (sortDir === 'asc' ? '▲' : '▼') : '↕' }}</span>
  </button>
</th>
</template>

<script setup>
import { computed } from 'vue'
const props = defineProps({
  k: { type: String, required: true },      // 이 열의 정렬 키(서버 허용 목록과 같은 이름)
  sortKey: { type: String, default: '' },   // 지금 정렬 중인 키
  sortDir: { type: String, default: 'desc' },
})
defineEmits(['sort'])
const active = computed(() => props.sortKey === props.k)
</script>
