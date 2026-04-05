<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-xl font-black text-gray-800">❓ Q&A</h1>
    </div>
    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="!items.length" class="text-center py-12 text-gray-400">등록된 글이 없습니다</div>
    <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <RouterLink v-for="item in items" :key="item.id" :to="'/qa/' + item.id"
        class="block px-4 py-3 border-b border-gray-50 hover:bg-amber-50/50 transition">
        <div class="text-sm font-medium text-gray-800">{{ item.title }}</div>
        <div class="text-xs text-gray-400 mt-1">{{ item.user?.name || item.company || '' }} · {{ item.view_count || 0 }}조회</div>
      </RouterLink>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
const items = ref([])
const loading = ref(true)
onMounted(async () => {
  try {
    const { data } = await axios.get('/api/qa')
    items.value = data.data?.data || []
  } catch {}
  loading.value = false
})
</script>