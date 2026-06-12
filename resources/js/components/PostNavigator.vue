<template>
  <!-- 이전글 / 목록 / 다음글 네비 (모든 Detail 하단 공통) -->
  <div class="mt-4 flex items-center justify-between card overflow-hidden text-sm">
    <RouterLink v-if="prevId" :to="detailPath(prevId)"
      class="flex-1 px-4 py-3 hover:bg-amber-50/60 transition-colors text-left text-ink-light truncate border-r border-gray-50">
      <span class="text-ink-muted text-xs">← 이전글</span>
      <div v-if="prevTitle" class="text-xs text-ink-light truncate mt-0.5">{{ prevTitle }}</div>
    </RouterLink>
    <div v-else class="flex-1 px-4 py-3 text-left text-ink-faint border-r border-gray-50 text-xs">← 이전글 없음</div>

    <RouterLink :to="listPath"
      class="px-4 py-3 hover:bg-amber-50/60 transition-colors text-center text-ink font-bold border-r border-gray-50 flex-shrink-0">
      목록
    </RouterLink>

    <RouterLink v-if="nextId" :to="detailPath(nextId)"
      class="flex-1 px-4 py-3 hover:bg-amber-50/60 transition-colors text-right text-ink-light truncate">
      <span class="text-ink-muted text-xs">다음글 →</span>
      <div v-if="nextTitle" class="text-xs text-ink-light truncate mt-0.5">{{ nextTitle }}</div>
    </RouterLink>
    <div v-else class="flex-1 px-4 py-3 text-right text-ink-faint text-xs">다음글 없음 →</div>
  </div>
</template>

<script setup>
const props = defineProps({
  prevId:    { type: [Number, String, null], default: null },
  prevTitle: { type: String, default: '' },
  nextId:    { type: [Number, String, null], default: null },
  nextTitle: { type: String, default: '' },
  listPath:  { type: String, required: true },      // 예: /market, /realestate, /jobs
  detailBase: { type: String, required: true },    // 예: /market/, /realestate/, /jobs/
})

function detailPath(id) {
  return props.detailBase + id
}
</script>
