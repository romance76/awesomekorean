<template>
<div class="min-h-screen">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-4">
      <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="heart-handshake" :size="20" /></span>
      친구
    </h1>

    <div class="grid grid-cols-12 gap-4">
      <!-- 왼쪽: 필터 -->
      <div class="col-span-12 lg:col-span-3 space-y-3">
        <!-- 상태 탭 -->
        <div class="card overflow-hidden">
          <div class="px-4 py-3 border-b border-gray-50 font-bold text-sm text-ink flex items-center gap-1.5"><AppIcon name="list" :size="14" class="text-amber-600" />상태</div>
          <button v-for="t in statusTabs" :key="t.key" @click="statusFilter=t.key; loadFriends()"
            class="w-full text-left px-4 py-2.5 text-sm transition-colors flex items-center justify-between"
            :class="statusFilter===t.key?'bg-amber-50 text-amber-700 font-bold':'text-ink-light hover:bg-amber-50/50'">
            <span>{{ t.icon }} {{ t.label }}</span>
            <span class="text-[11px] text-ink-faint">{{ getCounts(t.key) }}</span>
          </button>
        </div>
        <!-- 섹션 필터 -->
        <div class="card overflow-hidden">
          <div class="px-4 py-3 border-b border-gray-50 font-bold text-sm text-ink flex items-center gap-1.5"><AppIcon name="tag" :size="14" class="text-amber-600" />만난 곳</div>
          <button @click="sourceFilter=''; loadFriends()" class="w-full text-left px-4 py-2 text-xs transition-colors"
            :class="!sourceFilter?'bg-amber-50 text-amber-700 font-bold':'text-ink-light hover:bg-amber-50/50'">전체</button>
          <button v-for="s in sources" :key="s.key" @click="sourceFilter=s.key; loadFriends()"
            class="w-full text-left px-4 py-2 text-xs transition-colors"
            :class="sourceFilter===s.key?'bg-amber-50 text-amber-700 font-bold':'text-ink-light hover:bg-amber-50/50'">{{ s.icon }} {{ s.label }}</button>
        </div>
        <!-- 그룹 채팅방 -->
        <div class="card overflow-hidden">
          <div class="px-4 py-3 border-b border-gray-50 font-bold text-sm text-ink flex items-center justify-between">
            <span class="flex items-center gap-1.5"><AppIcon name="message-circle" :size="14" class="text-amber-600" />채팅방</span>
            <button @click="showGroupModal=true" class="text-amber-600 text-xs font-semibold hover:text-amber-800 transition-colors flex items-center gap-0.5"><AppIcon name="plus" :size="12" />만들기</button>
          </div>
          <div v-if="!chatRooms.length" class="px-4 py-3 text-xs text-ink-muted">채팅방이 없습니다</div>
          <RouterLink v-for="room in chatRooms" :key="room.id" :to="`/chat/${room.id}`"
            class="block px-4 py-2.5 text-sm hover:bg-amber-50 transition-colors border-b border-gray-50 last:border-0">
            <div class="font-medium text-ink truncate">{{ room.name }}</div>
            <div class="text-[11px] text-ink-muted truncate">{{ room.last_message || '메시지 없음' }}</div>
          </RouterLink>
        </div>
      </div>

      <!-- 메인: 친구 목록 -->
      <div class="col-span-12 lg:col-span-9">
        <div v-if="loading" class="text-center py-12 text-ink-muted">로딩중...</div>

        <!-- 소개 페이지 (친구가 하나도 없을 때) -->
        <div v-else-if="!allFriends.length" class="space-y-6">
          <div class="card p-8 text-center">
            <div class="icon-chip w-16 h-16 bg-amber-50 text-amber-600 mx-auto mb-4"><AppIcon name="heart-handshake" :size="32" :stroke-width="1.5" /></div>
            <h2 class="text-2xl font-bold text-ink mb-2">AwesomeKorean 친구</h2>
            <p class="text-ink-muted text-sm max-w-md mx-auto">미국에서 만난 한인 친구들과 더 가까워지세요. 커뮤니티, 구인구직, 중고장터 등 다양한 곳에서 만난 인연을 이어갈 수 있습니다.</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="card p-5 text-center">
              <div class="icon-chip w-14 h-14 bg-green-50 text-green-600 mx-auto mb-3"><AppIcon name="eye" :size="26" :stroke-width="1.5" /></div>
              <h3 class="font-bold text-ink text-sm mb-1">실시간 온라인 확인</h3>
              <p class="text-xs text-ink-muted">친구가 온라인인지 자리비움인지 실시간으로 확인하고 바로 연락하세요</p>
            </div>
            <div class="card p-5 text-center">
              <div class="icon-chip w-14 h-14 bg-blue-50 text-blue-600 mx-auto mb-3"><AppIcon name="message-circle" :size="26" :stroke-width="1.5" /></div>
              <h3 class="font-bold text-ink text-sm mb-1">1:1 & 그룹 채팅</h3>
              <p class="text-xs text-ink-muted">친구와 프라이빗 채팅, 여러 친구를 모아 그룹 채팅방도 만들 수 있어요</p>
            </div>
            <div class="card p-5 text-center">
              <div class="icon-chip w-14 h-14 bg-amber-50 text-amber-600 mx-auto mb-3"><AppIcon name="tag" :size="26" :stroke-width="1.5" /></div>
              <h3 class="font-bold text-ink text-sm mb-1">만난 곳 기억하기</h3>
              <p class="text-xs text-ink-muted">커뮤니티, 구인구직, 장터 등 어디서 만났는지 자동으로 기록됩니다</p>
            </div>
            <div class="card p-5 text-center">
              <div class="icon-chip w-14 h-14 bg-purple-50 text-purple-600 mx-auto mb-3"><AppIcon name="mail" :size="26" :stroke-width="1.5" /></div>
              <h3 class="font-bold text-ink text-sm mb-1">쪽지 & 소통</h3>
              <p class="text-xs text-ink-muted">친구에게 쪽지를 보내거나 프로필을 확인할 수 있어요</p>
            </div>
            <div class="card p-5 text-center">
              <div class="icon-chip w-14 h-14 bg-red-50 text-red-500 mx-auto mb-3"><AppIcon name="shield" :size="26" :stroke-width="1.5" /></div>
              <h3 class="font-bold text-ink text-sm mb-1">차단 & 관리</h3>
              <p class="text-xs text-ink-muted">원치 않는 사람은 차단하고, 친구 목록을 자유롭게 관리하세요</p>
            </div>
            <div class="card p-5 text-center">
              <div class="icon-chip w-14 h-14 bg-cyan-50 text-cyan-600 mx-auto mb-3"><AppIcon name="globe" :size="26" :stroke-width="1.5" /></div>
              <h3 class="font-bold text-ink text-sm mb-1">한인 네트워크</h3>
              <p class="text-xs text-ink-muted">LA, NY, Atlanta 등 미국 각지의 한인들과 네트워크를 넓혀보세요</p>
            </div>
          </div>

          <div class="card p-6 text-center">
            <h3 class="font-bold text-ink mb-2">첫 친구를 만들어보세요!</h3>
            <p class="text-xs text-ink-muted mb-4">게시글에서 마음에 드는 사람의 프로필을 방문하고 👫 친구 추가를 눌러보세요</p>
            <div class="flex justify-center gap-3">
              <RouterLink to="/community" class="btn-primary text-sm px-5 py-2.5 flex items-center gap-1.5"><AppIcon name="message-circle" :size="15" />커뮤니티 둘러보기</RouterLink>
              <RouterLink to="/clubs" class="btn-secondary text-sm px-5 py-2.5 flex items-center gap-1.5"><AppIcon name="users" :size="15" />동호회 찾기</RouterLink>
            </div>
          </div>
        </div>

        <!-- 필터 결과 없음 -->
        <div v-else-if="!filteredFriends.length" class="py-16 text-center">
          <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="users" :size="28" :stroke-width="1.5" /></div>
          <p class="text-sm text-ink-muted">{{ statusFilter==='pending'?'대기중인 요청이 없습니다': sourceFilter ? '이 섹션의 친구가 없습니다' : '친구가 없습니다' }}</p>
        </div>
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <div v-for="f in filteredFriends" :key="f.id"
            class="card card-hover p-4">
            <!-- 프로필 헤더 -->
            <div class="flex items-center gap-3 mb-3">
              <div class="relative">
                <div class="w-12 h-12 bg-amber-100 rounded-full flex items-center justify-center text-lg font-bold text-amber-700">{{ (f.friend?.name||'?')[0] }}</div>
                <div class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 rounded-full border-2 border-white"
                  :class="{'bg-green-500':f.online_status==='online','bg-yellow-400':f.online_status==='away','bg-gray-300':f.online_status==='offline'}"></div>
              </div>
              <div class="flex-1 min-w-0">
                <RouterLink :to="`/profile/${f.friend?.id}`" class="text-sm font-bold text-ink hover:text-amber-700 truncate block transition-colors">{{ f.friend?.name }}</RouterLink>
                <div class="text-[11px] text-ink-muted">{{ f.friend?.city ? f.friend.city+', '+f.friend.state : '' }}</div>
                <div class="flex items-center gap-1 mt-0.5">
                  <span v-if="f.source" class="text-[11px] bg-blue-50 text-blue-600 px-1.5 py-0.5 rounded-full">{{ sourceLabel(f.source) }}</span>
                  <span class="text-[11px]" :class="{'text-green-500':f.online_status==='online','text-yellow-500':f.online_status==='away','text-ink-faint':f.online_status==='offline'}">
                    {{ {online:'온라인',away:'자리비움',offline:'오프라인'}[f.online_status] }}
                  </span>
                </div>
              </div>
            </div>

            <!-- 액션 버튼 -->
            <div class="flex gap-1.5">
              <template v-if="f.status==='accepted'">
                <button @click="openChat(f.friend?.id)" class="flex-1 text-xs bg-amber-50 text-amber-700 py-1.5 rounded-lg font-bold hover:bg-amber-100 transition-colors flex items-center justify-center gap-1"><AppIcon name="message-circle" :size="12" />채팅</button>
                <button @click="startCall(f.friend)" class="flex-1 text-xs bg-green-50 text-green-700 py-1.5 rounded-lg font-bold hover:bg-green-100 transition-colors flex items-center justify-center gap-1"><AppIcon name="phone" :size="12" />전화</button>
                <button @click="sendMessageTo(f.friend)" class="flex-1 text-xs bg-blue-50 text-blue-700 py-1.5 rounded-lg font-bold hover:bg-blue-100 transition-colors flex items-center justify-center gap-1"><AppIcon name="mail" :size="12" />쪽지</button>
                <button @click="removeFriend(f.id)" class="text-ink-faint px-2 py-1.5 hover:text-red-500 transition-colors"><AppIcon name="x" :size="13" /></button>
              </template>
              <template v-else-if="f.status==='pending' && !f.is_sender">
                <button @click="acceptRequest(f.id)" class="flex-1 text-xs bg-green-50 text-green-700 py-1.5 rounded-lg font-bold hover:bg-green-100 transition-colors flex items-center justify-center gap-1"><AppIcon name="check" :size="12" />수락</button>
                <button @click="removeFriend(f.id)" class="flex-1 text-xs bg-red-50 text-red-600 py-1.5 rounded-lg font-bold hover:bg-red-100 transition-colors flex items-center justify-center gap-1"><AppIcon name="x" :size="12" />거절</button>
              </template>
              <template v-else-if="f.status==='pending' && f.is_sender">
                <span class="flex-1 text-xs text-ink-muted py-1.5 text-center">요청 대기중...</span>
                <button @click="removeFriend(f.id)" class="text-xs text-red-400 px-2 py-1.5 hover:text-red-600 transition-colors">취소</button>
              </template>
              <template v-else-if="f.status==='blocked'">
                <button @click="removeFriend(f.id)" class="flex-1 text-xs bg-gray-100 text-ink-light py-1.5 rounded-lg font-bold hover:bg-gray-200 transition-colors">차단 해제</button>
              </template>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 쪽지 보내기 모달 -->
    <div v-if="msgTarget" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" @click.self="msgTarget=null">
      <div class="bg-white rounded-2xl shadow-lift w-full max-w-md overflow-hidden">
        <div class="px-5 py-3 flex items-center justify-between border-b border-gray-100">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center text-sm font-bold text-blue-600">{{ (msgTarget.name || '?')[0] }}</div>
            <span class="text-ink font-bold text-sm">{{ msgTarget.name }}님에게 쪽지</span>
          </div>
          <button @click="msgTarget=null; msgText=''" class="text-ink-muted hover:text-ink transition-colors"><AppIcon name="x" :size="18" /></button>
        </div>
        <div class="p-5">
          <template v-if="!msgDone">
            <textarea v-model="msgText" rows="5" maxlength="500" placeholder="쪽지 내용을 입력하세요..."
              class="input-soft w-full p-3 text-sm resize-none"></textarea>
            <div class="flex justify-between items-center mt-3">
              <span class="text-xs text-ink-muted">{{ msgText.length }}/500</span>
              <div class="flex gap-2">
                <button @click="msgTarget=null; msgText=''" class="btn-secondary text-sm px-4 py-2">취소</button>
                <button @click="doSendMsg" :disabled="msgSending || !msgText.trim()" class="btn-primary text-sm px-4 py-2 disabled:opacity-50">
                  {{ msgSending ? '전송중...' : '보내기' }}
                </button>
              </div>
            </div>
          </template>
          <template v-else>
            <p class="text-sm font-bold text-green-600 mb-2 flex items-center gap-1.5"><AppIcon name="check" :size="15" />쪽지를 보냈습니다</p>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-ink-light whitespace-pre-wrap max-h-32 overflow-y-auto">{{ msgSentContent }}</div>
            <div class="flex gap-2 mt-3">
              <button @click="msgDone=false; msgText=''" class="btn-primary text-sm flex-1 py-2">새 쪽지 쓰기</button>
              <button @click="msgTarget=null; msgDone=false; msgText=''" class="btn-secondary text-sm flex-1 py-2">닫기</button>
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- 그룹 채팅방 생성 모달 -->
    <div v-if="showGroupModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" @click.self="showGroupModal=false">
      <div class="bg-white rounded-2xl p-5 w-full max-w-md shadow-lift">
        <h3 class="font-bold text-ink mb-3 flex items-center gap-2"><span class="icon-chip w-7 h-7 bg-amber-50 text-amber-600"><AppIcon name="message-circle" :size="14" /></span>그룹 채팅방 만들기</h3>
        <input v-model="groupName" type="text" placeholder="채팅방 이름" class="input-soft w-full px-3 py-2 text-sm mb-3" />
        <div class="text-xs text-ink-muted mb-2">친구를 선택하세요:</div>
        <div class="max-h-48 overflow-y-auto space-y-1 mb-3">
          <label v-for="f in acceptedFriends" :key="f.friend?.id" class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-amber-50 cursor-pointer transition-colors">
            <input type="checkbox" :value="f.friend?.id" v-model="selectedFriends" class="rounded accent-amber-500" />
            <div class="w-6 h-6 bg-amber-100 rounded-full flex items-center justify-center text-[11px] font-bold text-amber-700">{{ (f.friend?.name||'?')[0] }}</div>
            <span class="text-sm text-ink-light">{{ f.friend?.name }}</span>
            <span class="w-2 h-2 rounded-full ml-auto" :class="{'bg-green-500':f.online_status==='online','bg-yellow-400':f.online_status==='away','bg-gray-300':f.online_status==='offline'}"></span>
          </label>
        </div>
        <div class="flex gap-2">
          <button @click="createGroupChat" :disabled="!groupName.trim()||!selectedFriends.length" class="btn-primary text-sm flex-1 px-4 py-2 disabled:opacity-50">만들기 ({{ selectedFriends.length }}명)</button>
          <button @click="showGroupModal=false" class="btn-ghost px-4 py-2">취소</button>
        </div>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const router = useRouter()
const allFriends = ref([])
const chatRooms = ref([])
const loading = ref(true)
const statusFilter = ref('')
const sourceFilter = ref('')
const showGroupModal = ref(false)
const groupName = ref('')
const selectedFriends = ref([])

// 쪽지 모달
const msgTarget = ref(null)
const msgText = ref('')
const msgSending = ref(false)
const msgDone = ref(false)
const msgSentContent = ref('')

const statusTabs = [
  { key: '', icon: '👫', label: '전체' },
  { key: 'accepted', icon: '✅', label: '친구' },
  { key: 'pending', icon: '⏳', label: '요청' },
  { key: 'blocked', icon: '🚫', label: '차단' },
]

const sources = [
  { key: 'community', icon: '💬', label: '커뮤니티' },
  { key: 'jobs', icon: '💼', label: '구인구직' },
  { key: 'market', icon: '🛒', label: '중고장터' },
  { key: 'realestate', icon: '🏠', label: '부동산' },
  { key: 'directory', icon: '🏪', label: '업소록' },
  { key: 'clubs', icon: '👥', label: '동호회' },
  { key: 'events', icon: '🎉', label: '이벤트' },
  { key: 'qa', icon: '❓', label: 'Q&A' },
]

function sourceLabel(key) {
  const s = sources.find(s => s.key === key)
  return s ? s.label : key
}

function getCounts(status) {
  if (!status) return allFriends.value.length
  return allFriends.value.filter(f => f.status === status).length
}

const filteredFriends = computed(() => {
  let list = allFriends.value
  if (statusFilter.value) list = list.filter(f => f.status === statusFilter.value)
  if (sourceFilter.value) list = list.filter(f => f.source === sourceFilter.value)
  return list
})

const acceptedFriends = computed(() => allFriends.value.filter(f => f.status === 'accepted'))

async function loadFriends() {
  try {
    const params = {}
    const { data } = await axios.get('/api/friends', { params })
    allFriends.value = data.data || []
  } catch {}
}

async function loadChatRooms() {
  try { const { data } = await axios.get('/api/friends/chat-rooms'); chatRooms.value = data.data || [] } catch {}
}

async function acceptRequest(id) {
  try { await axios.post(`/api/friends/accept/${id}`); await loadFriends() } catch {}
}

async function removeFriend(id) {
  if (!confirm('정말 삭제하시겠습니까?')) return
  try { await axios.delete(`/api/friends/${id}`); allFriends.value = allFriends.value.filter(f => f.id !== id) } catch {}
}

async function openChat(friendId) {
  // 안심 채팅 (CommHub 사용)
  if (window.openCommChat) {
    const friend = allFriends.value.find(f => f.friend?.id === friendId)?.friend
    if (friend) {
      window.openCommChat({
        id: friend.id,
        name: friend.nickname || friend.name,
        avatar: friend.avatar,
        online: false,
      }, null) // conversationId는 CommHub에서 자동 생성
    }
  } else {
    // 기존 채팅 폴백
    try {
      const { data } = await axios.post('/api/friends/private-chat', { friend_id: friendId })
      router.push(`/chat/${data.data.room_id}`)
    } catch {}
  }
}

function startCall(friend) {
  if (window.startCommCall && friend) {
    window.startCommCall({
      id: friend.id,
      name: friend.nickname || friend.name,
      avatar: friend.avatar,
    })
  }
}

function sendMessageTo(friend) {
  msgTarget.value = friend
  msgText.value = ''
  msgDone.value = false
}

async function doSendMsg() {
  if (!msgText.value.trim() || !msgTarget.value) return
  msgSending.value = true
  try {
    await axios.post('/api/messages', { receiver_id: msgTarget.value.id, content: msgText.value.trim() })
    msgSentContent.value = msgText.value.trim()
    msgDone.value = true
    msgText.value = ''
  } catch (e) {
    alert(e.response?.data?.message || '전송 실패')
  }
  msgSending.value = false
}

async function createGroupChat() {
  if (!groupName.value.trim() || !selectedFriends.value.length) return
  try {
    const { data } = await axios.post('/api/friends/group-chat', { name: groupName.value, friend_ids: selectedFriends.value })
    showGroupModal.value = false; groupName.value = ''; selectedFriends.value = []
    await loadChatRooms()
    router.push(`/chat/${data.data.id}`)
  } catch (e) { alert(e.response?.data?.message || '생성 실패') }
}

onMounted(async () => {
  await Promise.all([loadFriends(), loadChatRooms()])
  loading.value = false
})
</script>
