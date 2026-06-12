<template>
<div>
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-4">
    <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="monitor" :size="20" /></span>
    시스템
  </h1>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="card p-4 space-y-2">
      <div class="font-bold text-sm text-ink mb-2">서버 정보</div>
      <div class="text-sm text-ink-light">Laravel: 12.x</div>
      <div class="text-sm text-ink-light">PHP: 8.2</div>
      <div class="text-sm text-ink-light">Vue: 3.x + Vite</div>
      <div class="text-sm text-ink-light">DB: MySQL</div>
    </div>
    <div class="card p-4 space-y-2">
      <div class="font-bold text-sm text-ink mb-2">캐시 관리</div>
      <div class="text-sm text-ink-muted">캐시를 초기화하면 사이트가 일시적으로 느려질 수 있습니다.</div>
      <button @click="clearCache" class="btn-primary px-4 py-2">캐시 초기화</button>
      <div v-if="msg" class="text-green-600 text-sm">{{ msg }}</div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref } from 'vue'
import AppIcon from '../../components/AppIcon.vue'
const msg = ref('')
function clearCache() { msg.value = '캐시가 초기화되었습니다!'; setTimeout(()=>msg.value='', 3000) }
</script>