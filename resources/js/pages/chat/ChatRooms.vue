<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-3xl mx-auto px-4 py-5">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-xl font-black text-gray-800">💬 채팅</h1>
      <button @click="showCreate = true" class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-lg text-sm hover:bg-amber-500">+ 새 채팅</button>
    </div>

    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="!rooms.length" class="text-center py-12 text-gray-400">채팅방이 없습니다. 새 채팅을 시작해보세요!</div>
    <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <RouterLink v-for="room in rooms" :key="room.id" :to="`/chat/${room.id}`"
        class="block px-4 py-3 border-b last:border-0 hover:bg-amber-50/50 transition">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center text-lg flex-shrink-0">💬</div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-semibold text-gray-800">{{ room.name || '채팅방 #' + room.id }}</div>
            <div class="text-xs text-gray-400 truncate">{{ room.messages?.[0]?.content || '메시지가 없습니다' }}</div>
          </div>
          <span class="text-[10px] text-gray-400">{{ room.type === 'dm' ? 'DM' : '그룹' }}</span>
        </div>
      </RouterLink>
    </div>

    <!-- 새 채팅 모달 -->
    <div v-if="showCreate" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" @click.self="showCreate = false">
      <div class="bg-white rounded-xl p-5 w-full max-w-sm shadow-xl">
        <h3 class="font-bold text-gray-800 mb-3">새 채팅방</h3>
        <input v-model="newRoomName" type="text" placeholder="채팅방 이름" class="w-full border rounded-lg px-3 py-2 text-sm mb-3 focus:ring-2 focus:ring-amber-400 outline-none" />
        <div class="flex gap-2">
          <button @click="createRoom" class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-lg text-sm flex-1 hover:bg-amber-500">만들기</button>
          <button @click="showCreate = false" class="text-gray-500 px-4 py-2">취소</button>
        </div>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
const router = useRouter()
const rooms = ref([])
const loading = ref(true)
const showCreate = ref(false)
const newRoomName = ref('')
async function createRoom() {
  if (!newRoomName.value.trim()) return
  try {
    const { data } = await axios.post('/api/chat/rooms', { name: newRoomName.value, type: 'group' })
    router.push(`/chat/${data.data.id}`)
  } catch {}
}
onMounted(async () => {
  try { const { data } = await axios.get('/api/chat/rooms'); rooms.value = data.data || [] } catch {}
  loading.value = false
})
</script>
