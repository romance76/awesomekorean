<template>
<div class="min-h-screen">
  <div class="page-main px-4 py-5">
    <RouterLink to="/groupbuy" class="inline-flex items-center gap-1 text-sm text-ink-muted hover:text-ink mb-4">
      <span aria-hidden="true">&larr;</span> 공동구매로 돌아가기
    </RouterLink>
    <GroupBuyGuide />
  </div>
</div>
</template>
<script setup>
import GroupBuyGuide from '../../components/groupbuy/GroupBuyGuide.vue'
</script>
