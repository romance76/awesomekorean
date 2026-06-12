<template>
<div class="min-h-screen">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <div class="flex items-center justify-between mb-4">
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
        <span class="icon-chip w-9 h-9 bg-blue-50 text-blue-600"><AppIcon name="message-circle" :size="20" /></span>
        게시판
      </h1>
    </div>
    <div v-if="loading" class="text-center py-12 text-ink-muted">로딩중...</div>
    <div v-else-if="!items.length" class="py-16 text-center">
      <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="message-circle" :size="28" :stroke-width="1.5" /></div>
      <p class="text-sm text-ink-muted">등록된 글이 없습니다</p>
    </div>
    <div v-else class="card overflow-hidden divide-y divide-gray-50">
      <RouterLink v-for="item in items" :key="item.id" :to="'/community/free/' + item.id"
        class="list-row">
        <div class="text-sm font-semibold text-ink">{{ item.title }}</div>
        <div class="text-xs text-ink-muted mt-1">{{ item.user?.name || item.company || '' }} · {{ item.view_count || 0 }}회</div>
      </RouterLink>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import AppIcon from '../../components/AppIcon.vue'
import axios from 'axios'
const items = ref([])
const loading = ref(true)
onMounted(async () => {
  try {
    const { data } = await axios.get('/api/posts')
    items.value = data.data?.data || []
  } catch {}
  loading.value = false
})
</script>