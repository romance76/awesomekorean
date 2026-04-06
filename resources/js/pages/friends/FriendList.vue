<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-3xl mx-auto px-4 py-5">
    <h1 class="text-xl font-black text-gray-800 mb-4">👫 친구</h1>
    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="!friends.length" class="text-center py-12 text-gray-400">아직 친구가 없습니다</div>
    <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div v-for="f in friends" :key="f.id" class="px-4 py-3 border-b last:border-0 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center text-sm font-bold text-amber-700">
            {{ (f.friend?.name || '?')[0] }}
          </div>
          <div>
            <div class="text-sm font-semibold text-gray-800">{{ f.friend?.name }}</div>
            <div class="text-xs text-gray-400">{{ f.friend?.city ? f.friend.city + ', ' + f.friend.state : '' }}</div>
          </div>
        </div>
        <div class="flex gap-2">
          <RouterLink :to="`/chat`" class="text-xs bg-amber-100 text-amber-700 px-3 py-1 rounded-lg hover:bg-amber-200">💬 채팅</RouterLink>
          <button @click="removeFriend(f.id)" class="text-xs text-red-400 hover:text-red-600">삭제</button>
        </div>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
const friends = ref([])
const loading = ref(true)
async function removeFriend(id) {
  if (!confirm('친구를 삭제하시겠습니까?')) return
  try { await axios.delete(`/api/friends/${id}`); friends.value = friends.value.filter(f => f.id !== id) } catch {}
}
onMounted(async () => {
  try { const { data } = await axios.get('/api/friends'); friends.value = data.data || [] } catch {}
  loading.value = false
})
</script>
