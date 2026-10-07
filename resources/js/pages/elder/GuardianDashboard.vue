<template>
<div class="min-h-screen">
  <div class="page-main px-4 py-5">
    <PageHeader title="보호자 대시보드" icon="users" fallback="/elder" />

    <div v-if="loading" class="text-center py-12 text-ink-muted">로딩중...</div>
    <div v-else-if="!wards.length" class="py-16 text-center">
      <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="users" :size="28" :stroke-width="1.5" /></div>
      <div class="text-ink-muted font-semibold text-sm">등록된 피보호자가 없습니다</div>
      <div class="text-xs text-ink-faint mt-1">피보호자가 보호자로 등록하면 여기에 표시됩니다</div>
    </div>
    <div v-else class="space-y-3">
      <div v-for="ward in wards" :key="ward.id" class="card p-4">
        <div class="flex items-center gap-3">
          <div class="icon-chip w-12 h-12 bg-blue-50 text-blue-600 rounded-full"><AppIcon name="user" :size="20" /></div>
          <div class="flex-1">
            <div class="text-sm font-bold text-ink">{{ ward.user?.name || '피보호자' }}</div>
            <div class="text-xs text-ink-muted">체크인 간격: {{ ward.checkin_interval }}시간</div>
          </div>
          <div :class="ward.status === 'ok' ? 'text-emerald-500' : ward.status === 'sos' ? 'text-red-500' : 'text-gray-400'">
            <AppIcon :name="ward.status === 'ok' ? 'check' : ward.status === 'sos' ? 'alert-circle' : 'clock'" :size="22" />
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import AppIcon from '../../components/AppIcon.vue'
import PageHeader from '../../components/PageHeader.vue'
import axios from 'axios'
const wards = ref([])
const loading = ref(true)
onMounted(async () => {
  try { const { data } = await axios.get('/api/elder/guardian/wards'); wards.value = data.data || [] } catch {}
  loading.value = false
})
</script>
