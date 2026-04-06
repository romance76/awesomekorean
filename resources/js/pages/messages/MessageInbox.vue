<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-3xl mx-auto px-4 py-5">
    <h1 class="text-xl font-black text-gray-800 mb-4">✉️ 쪽지</h1>
    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="!messages.length" class="text-center py-12 text-gray-400">받은 쪽지가 없습니다</div>
    <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div v-for="msg in messages" :key="msg.id" class="px-4 py-3 border-b last:border-0"
        :class="msg.is_read ? '' : 'bg-amber-50'">
        <div class="flex items-center gap-2 mb-1">
          <span class="text-sm font-semibold text-gray-800">{{ msg.sender?.name }}</span>
          <span class="text-[10px] text-gray-400">{{ formatDate(msg.created_at) }}</span>
          <span v-if="!msg.is_read" class="w-2 h-2 bg-amber-500 rounded-full"></span>
        </div>
        <div class="text-sm text-gray-600">{{ msg.content }}</div>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
const messages = ref([])
const loading = ref(true)
function formatDate(dt) {
  if (!dt) return ''
  const h = Math.floor((Date.now() - new Date(dt).getTime()) / 3600000)
  if (h < 1) return '방금'
  if (h < 24) return h + '시간 전'
  return Math.floor(h/24) + '일 전'
}
onMounted(async () => {
  try { const { data } = await axios.get('/api/messages'); messages.value = data.data?.data || data.data || [] } catch {}
  loading.value = false
})
</script>
