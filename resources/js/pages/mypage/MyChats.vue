<template>
  <div class="bg-white rounded-xl shadow-sm p-4">
    <div class="flex items-center justify-between mb-3">
      <h3 class="font-bold">💭 내 채팅방</h3>
      <router-link to="/chat" class="text-xs text-amber-600 hover:text-amber-800">전체 채팅 →</router-link>
    </div>
    <div v-if="loading" class="p-6 text-center text-sm text-gray-400">로딩 중...</div>
    <div v-else-if="!items.length" class="p-10 text-center text-sm text-gray-500">
      <p class="text-3xl mb-2">💭</p><p>참여한 채팅방이 없습니다.</p>
    </div>
    <ul v-else class="divide-y">
      <li v-for="r in items" :key="r.id" class="py-3 flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center text-xl">💬</div>
        <div class="flex-1 min-w-0">
          <p class="font-semibold text-sm truncate">{{ r.name || r.title || r.room_name }}</p>
          <p class="text-xs text-gray-500 truncate">{{ r.last_message || r.latest_message?.content || '새 채팅방' }}</p>
        </div>
        <span v-if="r.unread_count" class="px-2 py-0.5 bg-red-500 text-white rounded-full text-xs">{{ r.unread_count }}</span>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
const items = ref([])
const loading = ref(true)
onMounted(async () => {
  try {
    const { data } = await axios.get('/api/chat/rooms').catch(() => axios.get('/api/friends/chat-rooms').catch(() => ({ data: { data: [] } })))
    items.value = data?.data?.data || data?.data || []
  } finally { loading.value = false }
})
</script>
