<template>
  <div class="min-h-screen bg-gray-50 pb-16">
    <!-- Header -->
    <div class="max-w-[1200px] mx-auto px-4 pt-4">
      <div class="bg-gradient-to-r from-blue-600 to-blue-500 text-white px-6 py-6 rounded-2xl">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-xl font-black">👥 내 친구</h1>
            <p class="text-blue-100 text-sm mt-0.5">{{ friends.length }}명의 친구</p>
          </div>
          <span class="bg-white/20 text-white text-sm font-bold px-3 py-1.5 rounded-xl">
            요청 {{ pendingRequests.length }}개
          </span>
        </div>
      </div>
    </div>

    <div class="max-w-[1200px] mx-auto px-4 py-5 space-y-4">

      <!-- 친구 검색 -->
      <div class="bg-white rounded-2xl shadow-sm p-4">
        <h2 class="font-bold text-gray-800 text-sm mb-3">🔍 친구 찾기</h2>
        <div class="flex gap-2">
          <input v-model="searchQuery" type="text" placeholder="이름 또는 @아이디로 검색..."
            class="flex-1 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400"
            @input="searchUsers" />
          <button @click="searchUsers" class="bg-blue-600 text-white px-4 py-2.5 rounded-xl text-sm font-bold hover:bg-blue-700">
            검색
          </button>
        </div>

        <!-- 검색 결과 -->
        <div v-if="searchResults.length > 0" class="mt-3 space-y-2">
          <div v-for="user in searchResults" :key="user.id"
            class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 border border-gray-100">
            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center font-bold text-blue-600 flex-shrink-0 overflow-hidden">
              <img v-if="user.avatar" :src="user.avatar" class="w-full h-full object-cover" />
              <span v-else>{{ (user.name || '?')[0] }}</span>
            </div>
            <div class="flex-1 min-w-0">
              <p class="font-semibold text-gray-800 text-sm">{{ user.name }}</p>
              <p class="text-gray-400 text-xs">@{{ user.username }} · {{ user.region || '지역 미등록' }}</p>
            </div>
            <button @click="sendRequest(user.id)"
              :disabled="user.requestSent || user.isFriend"
              class="text-xs px-3 py-1.5 rounded-lg font-bold flex-shrink-0 transition"
              :class="user.isFriend ? 'bg-gray-100 text-gray-400 cursor-default'
                : user.requestSent ? 'bg-green-100 text-green-600 cursor-default'
                : 'bg-blue-600 text-white hover:bg-blue-700'">
              {{ user.isFriend ? '이미 친구' : user.requestSent ? '✓ 요청됨' : '+ 친구 추가' }}
            </button>
          </div>
        </div>
        <div v-else-if="searchQuery && !searching" class="mt-3 text-center text-gray-400 text-sm py-4">
          검색 결과가 없습니다
        </div>
      </div>

      <!-- 받은 친구 요청 -->
      <div v-if="pendingRequests.length > 0" class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <div class="px-5 py-3 bg-orange-50 flex items-center gap-2">
          <span class="text-lg">📨</span>
          <h2 class="font-bold text-orange-800 text-sm">받은 친구 요청 ({{ pendingRequests.length }})</h2>
        </div>
        <div class="divide-y divide-gray-50">
          <div v-for="req in pendingRequests" :key="req.id" class="flex items-center gap-3 px-5 py-3.5">
            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center font-bold text-blue-600 flex-shrink-0 overflow-hidden">
              <img v-if="req.requester?.avatar" :src="req.requester.avatar" class="w-full h-full object-cover" />
              <span v-else>{{ (req.requester?.name || '?')[0] }}</span>
            </div>
            <div class="flex-1 min-w-0">
              <p class="font-semibold text-gray-800 text-sm">{{ req.requester?.name }}</p>
              <p class="text-gray-400 text-xs">@{{ req.requester?.username }}</p>
            </div>
            <div class="flex gap-2 flex-shrink-0">
              <button @click="acceptRequest(req.requester_id)"
                class="bg-blue-600 text-white text-xs px-3 py-1.5 rounded-lg font-bold hover:bg-blue-700">
                수락
              </button>
              <button @click="rejectRequest(req.requester_id)"
                class="bg-gray-100 text-gray-500 text-xs px-3 py-1.5 rounded-lg font-bold hover:bg-gray-200">
                거절
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- 내 친구 목록 -->
      <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <div class="px-5 py-3 bg-blue-50 flex items-center gap-2">
          <span class="text-lg">👥</span>
          <h2 class="font-bold text-blue-800 text-sm">내 친구 ({{ friends.length }}명)</h2>
        </div>
        <div v-if="loading" class="text-center py-8 text-gray-400 text-sm">불러오는 중...</div>
        <div v-else-if="friends.length === 0" class="text-center py-12">
          <p class="text-4xl mb-3">👋</p>
          <p class="text-gray-600 font-semibold mb-1">아직 친구가 없어요</p>
          <p class="text-gray-400 text-sm">위에서 친구를 검색해서 추가해보세요!</p>
        </div>
        <div v-else class="divide-y divide-gray-50">
          <div v-for="friend in friends" :key="friend.id" class="flex items-center gap-3 px-5 py-3.5">
            <div class="relative flex-shrink-0">
              <div class="w-11 h-11 rounded-full bg-blue-100 flex items-center justify-center font-bold text-blue-600 overflow-hidden">
                <img v-if="friend.avatar" :src="friend.avatar" class="w-full h-full object-cover" />
                <span v-else>{{ (friend.name || '?')[0] }}</span>
              </div>
              <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-400 border-2 border-white rounded-full"></span>
            </div>
            <div class="flex-1 min-w-0">
              <p class="font-semibold text-gray-800 text-sm">{{ friend.name }}</p>
              <p class="text-gray-400 text-xs">@{{ friend.username }} · {{ friend.region || '지역 미등록' }}</p>
              <p class="text-xs text-gray-500">{{ friend.level || '씨앗' }} · ⭐ {{ (friend.points_total || 0).toLocaleString() }}P</p>
            </div>
            <div class="flex gap-1.5 flex-shrink-0">
              <!-- 메시지 -->
              <RouterLink :to="`/messages?to=${friend.id}`"
                class="w-8 h-8 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-100 transition text-sm" title="메시지">
                💬
              </RouterLink>
              <!-- 전화/비디오 -->
              <button @click="startCall(friend, 'voice')"
                class="w-8 h-8 rounded-full bg-green-50 text-green-600 flex items-center justify-center hover:bg-green-100 transition text-sm" title="음성통화">
                📞
              </button>
              <button @click="startCall(friend, 'video')"
                class="w-8 h-8 rounded-full bg-purple-50 text-purple-600 flex items-center justify-center hover:bg-purple-100 transition text-sm" title="화상통화">
                📹
              </button>
              <!-- 더보기 -->
              <button @click="removeFriend(friend.id)"
                class="w-8 h-8 rounded-full bg-red-50 text-red-400 flex items-center justify-center hover:bg-red-100 transition text-sm" title="친구 삭제">
                ✕
              </button>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- 통화 모달 -->
    <Transition name="fade">
      <div v-if="callModal" class="fixed inset-0 bg-black/80 flex items-center justify-center z-50 px-4">
        <div class="bg-white rounded-3xl p-8 max-w-sm w-full text-center shadow-2xl">
          <div class="w-20 h-20 mx-auto rounded-full bg-blue-100 overflow-hidden flex items-center justify-center text-3xl font-bold text-blue-600 mb-4">
            <img v-if="callModal.friend.avatar" :src="callModal.friend.avatar" class="w-full h-full object-cover" />
            <span v-else>{{ (callModal.friend.name || '?')[0] }}</span>
          </div>
          <h2 class="text-xl font-black text-gray-800 mb-1">{{ callModal.friend.name }}</h2>
          <p class="text-gray-400 text-sm mb-2">{{ callModal.type === 'voice' ? '📞 음성통화' : '📹 화상통화' }}</p>
          <div class="flex gap-2 my-4">
            <div class="w-2 h-2 bg-blue-400 rounded-full animate-bounce" style="animation-delay:0s"></div>
            <div class="w-2 h-2 bg-blue-400 rounded-full animate-bounce" style="animation-delay:0.15s"></div>
            <div class="w-2 h-2 bg-blue-400 rounded-full animate-bounce" style="animation-delay:0.3s"></div>
          </div>
          <p class="text-gray-500 text-sm mb-6">연결 중... 🔔 상대방을 호출하고 있어요</p>
          <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-3 mb-5 text-left">
            <p class="text-yellow-800 text-xs font-bold">🚧 서비스 준비 중</p>
            <p class="text-yellow-700 text-xs mt-1">WebRTC 음성/화상통화 기능은 현재 개발 중입니다. 빠른 시일 내 출시 예정!</p>
          </div>
          <button @click="callModal = null" class="w-full bg-red-500 text-white font-bold py-3 rounded-2xl hover:bg-red-600">
            ✕ 통화 종료
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const friends         = ref([])
const pendingRequests = ref([])
const searchQuery     = ref('')
const searchResults   = ref([])
const loading         = ref(true)
const searching       = ref(false)
const callModal       = ref(null)

// Load data
async function loadFriends() {
  loading.value = true
  try {
    const [friendsRes, pendingRes] = await Promise.allSettled([
      axios.get('/api/friends'),
      axios.get('/api/friends/pending'),
    ])
    if (friendsRes.status === 'fulfilled') friends.value = friendsRes.value.data
    if (pendingRes.status === 'fulfilled') pendingRequests.value = pendingRes.value.data
  } catch (e) {}
  loading.value = false
}

// Search
let searchTimer = null
function searchUsers() {
  clearTimeout(searchTimer)
  if (!searchQuery.value.trim()) { searchResults.value = []; return }
  searchTimer = setTimeout(async () => {
    searching.value = true
    try {
      const { data } = await axios.get('/api/friends/search', { params: { q: searchQuery.value } })
      const friendIds = new Set(friends.value.map(f => f.id))
      searchResults.value = data.map(u => ({
        ...u,
        isFriend: friendIds.has(u.id),
        requestSent: false,
      }))
    } catch (e) {} finally {
      searching.value = false
    }
  }, 400)
}

// Friend actions
async function sendRequest(userId) {
  try {
    await axios.post(`/api/friends/request/${userId}`)
    const user = searchResults.value.find(u => u.id === userId)
    if (user) user.requestSent = true
  } catch (e) {
    alert(e?.response?.data?.message || '요청 실패')
  }
}

async function acceptRequest(requesterId) {
  try {
    await axios.post(`/api/friends/accept/${requesterId}`)
    pendingRequests.value = pendingRequests.value.filter(r => r.requester_id !== requesterId)
    await loadFriends()
  } catch (e) {}
}

async function rejectRequest(requesterId) {
  try {
    await axios.post(`/api/friends/reject/${requesterId}`)
    pendingRequests.value = pendingRequests.value.filter(r => r.requester_id !== requesterId)
  } catch (e) {}
}

async function removeFriend(userId) {
  if (!confirm('친구를 삭제할까요?')) return
  try {
    await axios.delete(`/api/friends/${userId}`)
    friends.value = friends.value.filter(f => f.id !== userId)
  } catch (e) {}
}

function startCall(friend, type) {
  callModal.value = { friend, type }
}

onMounted(loadFriends)
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
