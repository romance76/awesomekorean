<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-3xl mx-auto px-4 py-5">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-xl font-black text-gray-800">👫 친구</h1>
      <div class="flex gap-2">
        <button @click="tab='friends'" class="text-xs font-bold px-3 py-1.5 rounded-lg" :class="tab==='friends'?'bg-amber-400 text-amber-900':'bg-white text-gray-600 border'">친구 {{ friends.length }}</button>
        <button @click="tab='requests'" class="text-xs font-bold px-3 py-1.5 rounded-lg relative" :class="tab==='requests'?'bg-amber-400 text-amber-900':'bg-white text-gray-600 border'">
          요청 {{ pendingRequests.length }}
          <span v-if="pendingRequests.length" class="absolute -top-1 -right-1 bg-red-500 text-white text-[8px] w-3.5 h-3.5 rounded-full flex items-center justify-center">{{ pendingRequests.length }}</span>
        </button>
        <button @click="tab='blocked'" class="text-xs font-bold px-3 py-1.5 rounded-lg" :class="tab==='blocked'?'bg-amber-400 text-amber-900':'bg-white text-gray-600 border'">차단</button>
      </div>
    </div>

    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>

    <!-- 친구 목록 -->
    <div v-else-if="tab==='friends'">
      <div v-if="!friends.length" class="text-center py-12 text-gray-400">아직 친구가 없습니다</div>
      <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div v-for="f in friends" :key="f.id" class="px-4 py-3 border-b last:border-0 flex items-center justify-between">
          <RouterLink :to="`/profile/${f.friend?.id}`" class="flex items-center gap-3 hover:opacity-80">
            <div class="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center text-sm font-bold text-amber-700">{{ (f.friend?.name || '?')[0] }}</div>
            <div>
              <div class="text-sm font-semibold text-gray-800">{{ f.friend?.name }}</div>
              <div class="text-xs text-gray-400">{{ f.friend?.city ? f.friend.city + ', ' + f.friend.state : '' }}</div>
            </div>
          </RouterLink>
          <div class="flex gap-2">
            <RouterLink to="/messages" class="text-xs bg-amber-100 text-amber-700 px-3 py-1 rounded-lg hover:bg-amber-200">💬 쪽지</RouterLink>
            <button @click="blockFriend(f.friend?.id)" class="text-xs text-gray-400 hover:text-red-500">차단</button>
            <button @click="removeFriend(f.id)" class="text-xs text-red-400 hover:text-red-600">삭제</button>
          </div>
        </div>
      </div>
    </div>

    <!-- 친구 요청 -->
    <div v-else-if="tab==='requests'">
      <div v-if="!pendingRequests.length" class="text-center py-12 text-gray-400">대기중인 요청이 없습니다</div>
      <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div v-for="r in pendingRequests" :key="r.id" class="px-4 py-3 border-b last:border-0 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center text-sm font-bold text-blue-700">{{ (r.user?.name || '?')[0] }}</div>
            <div>
              <div class="text-sm font-semibold text-gray-800">{{ r.user?.name }}</div>
              <div class="text-xs text-gray-400">친구 요청</div>
            </div>
          </div>
          <div class="flex gap-2">
            <button @click="acceptRequest(r.id)" class="text-xs bg-amber-400 text-amber-900 font-bold px-3 py-1 rounded-lg hover:bg-amber-500">수락</button>
            <button @click="removeFriend(r.id)" class="text-xs text-red-400 hover:text-red-600">거절</button>
          </div>
        </div>
      </div>
    </div>

    <!-- 차단 목록 -->
    <div v-else-if="tab==='blocked'">
      <div v-if="!blockedUsers.length" class="text-center py-12 text-gray-400">차단한 사용자가 없습니다</div>
      <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div v-for="b in blockedUsers" :key="b.id" class="px-4 py-3 border-b last:border-0 flex items-center justify-between">
          <div class="text-sm text-gray-800">{{ b.friend?.name || b.user?.name }}</div>
          <button @click="removeFriend(b.id)" class="text-xs text-amber-600 hover:text-amber-800">차단 해제</button>
        </div>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
const allFriends = ref([])
const loading = ref(true)
const tab = ref('friends')

const friends = computed(() => allFriends.value.filter(f => f.status === 'accepted'))
const pendingRequests = computed(() => allFriends.value.filter(f => f.status === 'pending'))
const blockedUsers = computed(() => allFriends.value.filter(f => f.status === 'blocked'))

async function removeFriend(id) {
  if (!confirm('정말 삭제하시겠습니까?')) return
  try { await axios.delete(`/api/friends/${id}`); allFriends.value = allFriends.value.filter(f => f.id !== id) } catch {}
}

async function acceptRequest(id) {
  try {
    await axios.post(`/api/friends/accept/${id}`)
    const r = allFriends.value.find(f => f.id === id)
    if (r) r.status = 'accepted'
  } catch {}
}

async function blockFriend(userId) {
  if (!confirm('이 사용자를 차단하시겠습니까?')) return
  try {
    await axios.post(`/api/friends/block/${userId}`)
    await loadFriends()
  } catch {}
}

async function loadFriends() {
  try { const { data } = await axios.get('/api/friends'); allFriends.value = data.data || [] } catch {}
}

onMounted(async () => {
  await loadFriends()
  loading.value = false
})
</script>
