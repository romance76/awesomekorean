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
      <button @click="clearCache" :disabled="clearing" class="btn-primary px-4 py-2 disabled:opacity-50">{{ clearing ? '초기화중...' : '캐시 초기화' }}</button>
      <div v-if="msg" class="text-green-600 text-sm">{{ msg }}</div>
    </div>
    <div class="card p-4 space-y-2 lg:col-span-2">
      <div class="font-bold text-sm text-ink mb-2">콘텐츠 자동 수집</div>
      <div class="text-sm text-ink-muted">뉴스 · 쇼츠 · 음악 · 레시피 · 업소록을 버튼 하나로 순서대로 실행합니다. 하나가 실패해도 나머지는 계속 진행됩니다.</div>
      <button @click="syncAllContent" :disabled="syncing" class="btn-primary px-4 py-2 disabled:opacity-50">{{ syncing ? '수집 중... (최대 몇 분 소요)' : '전체 자동 수집 실행' }}</button>
      <div v-if="syncResults.length" class="mt-2 space-y-1.5">
        <div v-for="r in syncResults" :key="r.source" class="flex items-center gap-2 text-sm">
          <span class="w-14 shrink-0 font-semibold text-ink">{{ r.source }}</span>
          <span :class="r.success ? 'text-green-600' : 'text-red-500'">{{ r.success ? '✅' : '❌' }}</span>
          <span class="text-ink-muted truncate">{{ r.message }}</span>
        </div>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
const msg = ref('')
const clearing = ref(false)
const syncing = ref(false)
const syncResults = ref([])
async function clearCache() {
  clearing.value = true
  try {
    const { data } = await axios.post('/api/admin/system/clear-cache')
    msg.value = data.message || '캐시가 초기화되었습니다!'
  } catch (e) {
    msg.value = e.response?.data?.message || '캐시 초기화 실패'
  }
  clearing.value = false
  setTimeout(() => msg.value = '', 3000)
}
async function syncAllContent() {
  syncing.value = true
  syncResults.value = []
  try {
    const { data } = await axios.post('/api/admin/system/sync-all-content')
    syncResults.value = data.results || []
  } catch (e) {
    alert(e.response?.data?.message || '수집 실패')
  }
  syncing.value = false
}
</script>