<template>
<div v-if="show && user" class="fixed inset-0 z-50" @click.self="$emit('close')">
  <div class="absolute inset-0"></div>
  <!-- 작은 말풍선 팝업 -->
  <div class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-white rounded-xl shadow-xl border border-gray-200 w-56 overflow-hidden animate-in">
    <div class="bg-gradient-to-r from-amber-400 to-orange-400 px-3 py-2 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-full bg-white/30 flex items-center justify-center text-sm font-bold text-amber-900">{{ (user.name || '?')[0] }}</div>
        <div>
          <div class="text-sm font-bold text-amber-900 leading-tight">{{ user.name }}</div>
          <div class="text-[10px] text-amber-800/70">{{ user.city ? user.city + ', ' + user.state : '' }}</div>
        </div>
      </div>
      <button @click="$emit('close')" class="text-amber-900/50 hover:text-amber-900 text-sm">✕</button>
    </div>
    <div class="p-2 flex gap-1.5">
      <template v-if="!isMe">
        <button @click="sendMessage" class="flex-1 bg-blue-500 text-white text-[11px] font-bold py-1.5 rounded-lg hover:bg-blue-600">✉️ 쪽지</button>
        <button v-if="user.allow_friend_request && !isFriend && !isPending" @click="addFriend" :disabled="adding" class="flex-1 bg-amber-400 text-amber-900 text-[11px] font-bold py-1.5 rounded-lg hover:bg-amber-500 disabled:opacity-50">👫 친구</button>
        <div v-else-if="isPending" class="flex-1 text-center text-[10px] text-gray-400 py-1.5">⏳ 대기중</div>
        <div v-else-if="isFriend" class="flex-1 text-center text-[10px] text-green-600 py-1.5">✅ 친구</div>
        <div v-else-if="!user.allow_friend_request" class="flex-1 text-center text-[10px] text-gray-400 py-1.5">🔒</div>
      </template>
      <template v-if="isFriend">
        <RouterLink :to="`/profile/${user.id}`" @click="$emit('close')" class="flex-1 text-center border text-gray-600 text-[11px] font-semibold py-1.5 rounded-lg hover:bg-gray-50">👤 프로필</RouterLink>
      </template>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import axios from 'axios'

const props = defineProps({ show: Boolean, userId: [Number, String] })
const emit = defineEmits(['close'])
const auth = useAuthStore()
const router = useRouter()

const user = ref(null)
const isFriend = ref(false)
const isPending = ref(false)
const adding = ref(false)

const isMe = computed(() => auth.user?.id == props.userId)

watch(() => props.userId, async (id) => {
  if (!id || !props.show) return
  user.value = null; isFriend.value = false; isPending.value = false
  try { const { data } = await axios.get(`/api/users/${id}`); user.value = data.data } catch {}
  if (auth.isLoggedIn && !isMe.value) {
    try {
      const { data } = await axios.get('/api/friends')
      const list = data.data || []
      isFriend.value = list.some(f => f.friend?.id == id && f.status === 'accepted')
      isPending.value = list.some(f => f.friend?.id == id && f.status === 'pending')
    } catch {}
  }
}, { immediate: true })

async function addFriend() {
  adding.value = true
  try { await axios.post(`/api/friends/request/${user.value.id}`, { source: 'community' }); isPending.value = true } catch (e) { alert(e.response?.data?.message || '요청 실패') }
  adding.value = false
}

function sendMessage() { emit('close'); router.push('/messages') }
</script>

<style scoped>
.animate-in { animation: popIn 0.15s ease-out; }
@keyframes popIn { from { transform: translate(-50%,-50%) scale(0.9); opacity: 0; } to { transform: translate(-50%,-50%) scale(1); opacity: 1; } }
</style>
