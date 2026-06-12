<template>
<div v-if="lastPage > 1" class="flex justify-center items-center gap-1 mt-4 flex-wrap">
  <button @click="$emit('page', 1)" :disabled="page <= 1"
    class="pg-btn" :class="page <= 1 ? 'text-gray-300' : 'text-ink-muted hover:bg-gray-100'">«</button>
  <button @click="$emit('page', page - 1)" :disabled="page <= 1"
    class="pg-btn" :class="page <= 1 ? 'text-gray-300' : 'text-ink-muted hover:bg-gray-100'">‹</button>

  <button v-for="pg in visiblePages" :key="pg" @click="$emit('page', pg)"
    class="pg-btn" :class="pg === page ? 'bg-amber-400 text-white font-bold' : 'text-ink-muted hover:bg-gray-100'">
    {{ pg }}
  </button>

  <button @click="$emit('page', page + 1)" :disabled="page >= lastPage"
    class="pg-btn" :class="page >= lastPage ? 'text-gray-300' : 'text-ink-muted hover:bg-gray-100'">›</button>
  <button @click="$emit('page', lastPage)" :disabled="page >= lastPage"
    class="pg-btn" :class="page >= lastPage ? 'text-gray-300' : 'text-ink-muted hover:bg-gray-100'">»</button>
</div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  page: { type: Number, required: true },
  lastPage: { type: Number, required: true },
})

defineEmits(['page'])

const visiblePages = computed(() => {
  const total = props.lastPage
  const cur = props.page
  const maxShow = window.innerWidth < 640 ? 5 : 7
  const half = Math.floor(maxShow / 2)

  let start = Math.max(1, cur - half)
  let end = Math.min(total, start + maxShow - 1)
  if (end - start + 1 < maxShow) start = Math.max(1, end - maxShow + 1)

  const pages = []
  for (let i = start; i <= end; i++) pages.push(i)
  return pages
})
</script>

<style scoped>
.pg-btn {
  min-width: 28px;
  height: 28px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  font-size: 12px;
  transition: all 0.15s;
  cursor: pointer;
}
.pg-btn:disabled { cursor: default; }
@media (min-width: 640px) {
  .pg-btn { min-width: 32px; height: 32px; font-size: 13px; }
}
</style>
