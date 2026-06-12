<template>
<div class="min-h-screen">
  <div class="max-w-3xl mx-auto px-4 py-5">
    <button @click="$router.back()" class="text-sm text-ink-muted hover:text-amber-600 transition-colors mb-3 inline-flex items-center gap-1"><AppIcon name="arrow-left" :size="14" />안심서비스</button>
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-4">
      <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="list" :size="20" /></span>
      체크인 기록
    </h1>

    <div v-if="loading" class="text-center py-12 text-ink-muted">로딩중...</div>
    <div v-else-if="!logs.length" class="py-16 text-center">
      <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="clock" :size="28" :stroke-width="1.5" /></div>
      <p class="text-sm text-ink-muted">체크인 기록이 없습니다</p>
    </div>
    <div v-else class="card overflow-hidden divide-y divide-gray-50">
      <div v-for="log in logs" :key="log.id" class="px-4 py-3 flex items-center gap-3">
        <div class="icon-chip w-10 h-10 rounded-full flex-shrink-0"
          :class="log.status === 'ok' ? 'bg-emerald-50 text-emerald-600' : log.status === 'sos' ? 'bg-red-50 text-red-500' : 'bg-gray-100 text-gray-400'">
          <AppIcon :name="log.status === 'ok' ? 'check' : log.status === 'sos' ? 'alert-circle' : 'clock'" :size="18" />
        </div>
        <div class="flex-1">
          <div class="text-sm font-semibold" :class="log.status === 'ok' ? 'text-emerald-600' : log.status === 'sos' ? 'text-red-600' : 'text-ink-muted'">
            {{ { ok: '정상 체크인', sos: 'SOS 긴급', missed: '미체크인' }[log.status] }}
          </div>
          <div class="text-xs text-ink-muted">{{ formatDate(log.checked_in_at) }}</div>
        </div>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import AppIcon from '../../components/AppIcon.vue'
import axios from 'axios'
const logs = ref([])
const loading = ref(true)
function formatDate(dt) { return dt ? new Date(dt).toLocaleString('ko-KR') : '' }
onMounted(async () => {
  try { const { data } = await axios.get('/api/elder/checkin-history'); logs.value = data.data?.data || data.data || [] } catch {}
  loading.value = false
})
</script>
