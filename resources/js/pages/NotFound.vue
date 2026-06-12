<template>
  <div class="min-h-screen flex items-center justify-center">
    <div class="text-center">
      <div class="icon-chip w-16 h-16 bg-gray-100 text-gray-300 mx-auto mb-4"><AppIcon name="search" :size="32" :stroke-width="1.5" /></div>
      <h1 class="text-2xl font-bold text-ink mb-2">페이지를 찾을 수 없습니다</h1>
      <p class="text-ink-muted mb-6">요청하신 페이지가 존재하지 않습니다</p>
      <RouterLink to="/" class="btn-primary">홈으로</RouterLink>
    </div>
  </div>
</template>
<script setup>
import AppIcon from '../components/AppIcon.vue'
</script>
