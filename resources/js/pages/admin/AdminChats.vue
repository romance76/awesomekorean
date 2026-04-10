<template>
<div>
  <h1 class="text-xl font-black text-gray-800 mb-4">💬 채팅 관리</h1>

  <div class="flex gap-4 items-start">
    <!-- ─── 왼쪽: 방 목록 ─── -->
    <div class="w-2/5 flex-shrink-0">
      <div class="bg-white rounded-xl shadow-sm border p-3 mb-3 flex gap-2">
        <input v-model="search" @keyup.enter="loadRooms()" placeholder="방 이름 검색..."
          class="flex-1 border rounded px-3 py-1.5 text-sm" />
        <button @click="loadRooms()" class="bg-gray-100 text-gray-700 px-3 py-1.5 rounded text-xs font-bold">검색</button>
        <button @click="showCreate=true" class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded text-xs">+ 방 개설</button>
      </div>

      <div v-if="roomsLoading" class="text-center py-8 text-gray-400">로딩중...</div>
      <div v-else-if="!rooms.length" class="text-center py-8 text-gray-400">방 없음</div>
      <div v-else class="bg-white rounded-xl shadow-sm border overflow-hidden max-h-[75vh] overflow-y-auto">
        <div v-for="r in rooms" :key="r.id"
          @click="openRoom(r)"
          class="border-b last:border-0 px-3 py-3 cursor-pointer hover:bg-amber-50/30 transition"
          :class="activeRoom?.id === r.id ? 'bg-amber-50 border-l-4 border-l-amber-500' : ''">
          <div class="flex items-start justify-between gap-2">
            <div class="flex-1 min-w-0">
              <div class="font-semibold text-gray-800 text-sm truncate">{{ r.name || '(이름 없음)' }}</div>
              <div class="text-[10px] text-gray-400 mt-0.5 flex gap-2">
                <span class="inline-block px-1.5 py-0.5 rounded bg-gray-100">{{ r.type }}</span>
                <span>👥 {{ r.users_count || 0 }}</span>
              </div>
              <div v-if="r.messages?.[0]" class="text-[11px] text-gray-500 mt-1 truncate">
                {{ r.messages[0].user?.nickname || r.messages[0].user?.name || '?' }}: {{ r.messages[0].content }}
              </div>
            </div>
            <button @click.stop="deleteRoom(r)" class="text-red-400 hover:text-red-600 text-sm flex-shrink-0">🗑</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ─── 오른쪽: 채팅창 상세 ─── -->
    <div class="flex-1 min-w-0">
      <!-- 방 선택 전 빈 상태 -->
      <div v-if="!activeRoom" class="bg-white rounded-xl shadow-sm border p-10 text-center text-gray-400">
        <div class="text-4xl mb-2">💬</div>
        <div>왼쪽에서 채팅방을 선택하세요</div>
      </div>

      <!-- 채팅창 -->
      <div v-else class="bg-white rounded-xl shadow-sm border">
        <!-- 헤더 -->
        <div class="px-4 py-3 border-b bg-amber-50 flex items-center justify-between">
          <div>
            <div class="font-black text-amber-800">💬 {{ activeRoom.name || '(이름 없음)' }}</div>
            <div class="text-[10px] text-amber-700">
              ID: {{ activeRoom.id }} · {{ activeRoom.type }} · 멤버: {{ roomDetail?.members?.length || 0 }}명
            </div>
          </div>
          <button @click="activeRoom = null; roomDetail = null" class="text-amber-700 text-xl leading-none">✕</button>
        </div>

        <!-- 탭 바 -->
        <div class="flex border-b overflow-x-auto">
          <button v-for="t in tabs" :key="t.key" @click="tab = t.key"
            class="flex items-center gap-1 px-4 py-2.5 text-xs font-bold border-b-2 whitespace-nowrap transition"
            :class="tab === t.key ? 'border-amber-500 text-amber-700' : 'border-transparent text-gray-400 hover:text-gray-600'">
            <span>{{ t.icon }}</span>
            <span>{{ t.label }}</span>
            <span v-if="tabCount(t.key) > 0" class="ml-1 text-[9px] px-1.5 py-0.5 rounded-full bg-red-100 text-red-700 font-bold">
              {{ tabCount(t.key) }}
            </span>
          </button>
        </div>

        <div v-if="detailLoading" class="p-10 text-center text-gray-400">로딩중...</div>
        <div v-else-if="roomDetail">
          <!-- 💬 메시지 탭 -->
          <div v-if="tab === 'messages'" class="flex flex-col">
          <div class="p-3 max-h-[55vh] overflow-y-auto">
            <div v-if="!roomDetail.messages?.length" class="text-center py-8 text-gray-400">메시지 없음</div>
            <div v-else class="space-y-1.5">
              <div v-for="m in [...roomDetail.messages].reverse()" :key="m.id"
                class="group flex items-start gap-2 p-2 rounded hover:bg-gray-50"
                :class="m.type === 'system' ? 'bg-amber-50' : ''">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <button @click="openUserAction(m.user, m)"
                      class="text-xs font-bold text-blue-700 hover:underline">
                      {{ m.user?.nickname || m.user?.name || '?' }}
                    </button>
                    <span v-if="(m.user_report_count || 0) > 0"
                      class="text-[9px] px-1.5 py-0.5 rounded-full bg-red-100 text-red-700 font-bold">
                      신고 {{ m.user_report_count }}
                    </span>
                    <span class="text-[9px] text-gray-400">{{ fmt(m.created_at) }}</span>
                    <span v-if="m.type === 'system'" class="text-[9px] px-1 py-0.5 bg-amber-200 text-amber-800 rounded font-bold">SYSTEM</span>
                  </div>
                  <div class="text-xs text-gray-800 mt-0.5 break-words">{{ m.content }}</div>
                </div>
                <button @click="deleteMessage(m)" class="opacity-0 group-hover:opacity-100 text-red-300 hover:text-red-500 text-xs flex-shrink-0">🗑</button>
              </div>
            </div>
          </div>
          <!-- 관리자 메시지 입력창 -->
          <div class="border-t px-3 py-2 flex gap-2 items-center bg-red-50/50">
            <span class="text-[10px] bg-red-500 text-white px-2 py-1 rounded-full font-bold flex-shrink-0">👑 관리자</span>
            <input v-model="adminMsg" @keyup.enter="sendAdminMessage" placeholder="관리자로 메시지 전송..."
              class="flex-1 border rounded-full px-3 py-1.5 text-xs outline-none focus:ring-2 focus:ring-red-300" />
            <button @click="sendAdminMessage" :disabled="!adminMsg.trim() || sendingMsg"
              class="bg-red-500 text-white font-bold px-3 py-1.5 rounded-full text-xs disabled:opacity-50">
              {{ sendingMsg ? '...' : '전송' }}
            </button>
          </div>
          </div>

          <!-- 📢 공지 탭 -->
          <div v-else-if="tab === 'announce'" class="p-4">
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">
              <div class="text-xs font-bold text-amber-700 mb-2">📢 새 공지 작성</div>
              <div class="mb-2">
                <div class="text-[10px] font-bold text-amber-800 mb-1">⏰ 공지 유지 기간</div>
                <div class="flex flex-wrap gap-1">
                  <button v-for="opt in durationOptions" :key="opt.value"
                    @click="selectedDuration = opt.value"
                    :class="selectedDuration === opt.value ? 'bg-amber-400 text-amber-900' : 'bg-white border text-gray-600 hover:bg-amber-100'"
                    class="px-2.5 py-1 rounded text-[10px] font-bold transition">{{ opt.label }}</button>
                </div>
                <input v-if="selectedDuration === 'custom'" v-model="customExpires" type="datetime-local"
                  class="w-full border rounded px-2 py-1 text-xs mt-2" />
              </div>
              <textarea v-model="announceText" rows="3" placeholder="공지 내용을 입력하세요..."
                class="w-full border rounded px-3 py-2 text-sm resize-none"></textarea>
              <div class="flex justify-end mt-2">
                <button @click="sendAnnounce" :disabled="!announceText.trim() || announcing"
                  class="bg-amber-400 text-amber-900 font-bold px-5 py-2 rounded text-xs disabled:opacity-50">
                  {{ announcing ? '전송중...' : '공지 발송' }}
                </button>
              </div>
            </div>
            <div class="text-xs font-bold text-gray-700 mb-2">📜 과거 공지 ({{ roomDetail.announcements?.length || 0 }})</div>
            <div v-if="!roomDetail.announcements?.length" class="text-center py-6 text-gray-400 text-sm">공지 없음</div>
            <div v-else class="space-y-2 max-h-[45vh] overflow-y-auto">
              <div v-for="a in roomDetail.announcements" :key="a.id" class="border rounded p-2 bg-amber-50/50">
                <div class="flex items-center gap-2 mb-1">
                  <span class="text-[10px] font-bold text-amber-700">{{ a.user?.nickname || a.user?.name }}</span>
                  <span class="text-[9px] text-gray-400">{{ fmt(a.created_at) }}</span>
                </div>
                <div class="text-xs text-gray-800 break-words">{{ a.content }}</div>
              </div>
            </div>
          </div>

          <!-- 🚨 신고 탭 -->
          <div v-else-if="tab === 'reports'" class="p-3 max-h-[65vh] overflow-y-auto">
            <div v-if="!roomDetail.reports?.length" class="text-center py-8 text-gray-400">신고 없음</div>
            <div v-else class="space-y-2">
              <div v-for="r in roomDetail.reports" :key="r.id" class="border rounded-lg p-3"
                :class="r.status === 'resolved' ? 'bg-gray-50' : 'bg-red-50 border-red-200'">
                <div class="flex items-start justify-between gap-2">
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap mb-1">
                      <span class="text-[10px] px-1.5 py-0.5 rounded-full font-bold"
                        :class="r.status === 'resolved' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
                        {{ r.status === 'resolved' ? '✅ 해결됨' : '🚨 미해결' }}
                      </span>
                      <span class="text-[10px] text-gray-500">{{ fmt(r.created_at) }}</span>
                    </div>
                    <div class="text-xs">
                      <span class="text-gray-500">신고자:</span>
                      <span class="font-bold ml-1">{{ r.reporter_name }}</span>
                      <span class="text-gray-400 ml-1 text-[10px]">({{ r.reporter_email }})</span>
                    </div>
                    <div class="text-xs mt-0.5">
                      <span class="text-gray-500">대상:</span>
                      <button @click="openUserAction({id: r.target_user_id, name: r.target_name, email: r.target_email})"
                        class="font-bold ml-1 text-blue-700 hover:underline">{{ r.target_name }}</button>
                    </div>
                    <div class="text-xs mt-0.5">
                      <span class="text-gray-500">이유:</span>
                      <span class="ml-1">{{ r.reason }}</span>
                    </div>
                    <div v-if="r.report_content" class="text-xs mt-0.5">
                      <span class="text-gray-500">상세:</span>
                      <span class="ml-1">{{ r.report_content }}</span>
                    </div>
                    <div class="mt-2 p-2 bg-white rounded border text-xs text-gray-700">
                      <span class="text-[10px] text-gray-400">메시지 내용:</span>
                      <div class="mt-0.5">{{ r.message_content || '(삭제됨)' }}</div>
                    </div>
                  </div>
                  <button v-if="r.status !== 'resolved'" @click="resolveReport(r)"
                    class="text-[10px] bg-green-500 text-white font-bold px-2 py-1 rounded hover:bg-green-600 flex-shrink-0">
                    ✓ 해결
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- 🚫 차단 탭 (이 방만) -->
          <div v-else-if="tab === 'bans'" class="p-3 max-h-[65vh] overflow-y-auto">
            <div class="text-xs font-bold text-gray-700 mb-2">이 방 차단 목록 ({{ roomDetail.bans?.length || 0 }})</div>
            <div v-if="!roomDetail.bans?.length" class="text-center py-8 text-gray-400">차단된 유저 없음</div>
            <div v-else class="space-y-2">
              <div v-for="u in roomDetail.bans" :key="u.id"
                class="flex items-center justify-between gap-2 p-3 border border-red-200 bg-red-50 rounded-lg">
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-bold">{{ u.nickname || u.name }}</div>
                  <div class="text-[10px] text-gray-500">{{ u.email }}</div>
                  <div v-if="u.reason" class="text-[10px] text-red-600 mt-0.5">사유: {{ u.reason }}</div>
                  <div class="text-[9px] text-gray-400 mt-0.5">{{ fmt(u.banned_at) }}</div>
                </div>
                <button @click="unbanMember(u)" class="text-[10px] bg-green-100 text-green-700 px-3 py-1.5 rounded font-bold hover:bg-green-200 flex-shrink-0">
                  🔓 해제
                </button>
              </div>
            </div>

            <!-- 멤버 리스트 -->
            <div class="text-xs font-bold text-gray-700 mb-2 mt-4 border-t pt-3">👥 현재 멤버 ({{ roomDetail.members?.length || 0 }})</div>
            <div v-if="!roomDetail.members?.length" class="text-center py-4 text-gray-400 text-sm">멤버 없음</div>
            <div v-else class="space-y-1">
              <div v-for="u in roomDetail.members" :key="u.id"
                class="flex items-center justify-between gap-2 p-2 border rounded">
                <button @click="openUserAction(u)" class="flex-1 min-w-0 text-left hover:bg-gray-50 rounded px-1">
                  <div class="text-xs font-semibold text-blue-700">{{ u.nickname || u.name }}</div>
                  <div class="text-[10px] text-gray-400">{{ u.email }}</div>
                </button>
                <span v-if="u.is_banned" class="text-[9px] px-1.5 py-0.5 rounded-full bg-red-100 text-red-700 font-bold">영구제명</span>
              </div>
            </div>
          </div>

          <!-- ⛔ 영구제명 탭 -->
          <div v-else-if="tab === 'permaban'" class="p-3 max-h-[65vh] overflow-y-auto">
            <div class="text-xs font-bold text-gray-700 mb-2">사이트 전체 영구제명 ({{ roomDetail.permanent_banned?.length || 0 }})</div>
            <div v-if="!roomDetail.permanent_banned?.length" class="text-center py-8 text-gray-400">영구제명 유저 없음</div>
            <div v-else class="space-y-2">
              <div v-for="u in roomDetail.permanent_banned" :key="u.id"
                class="flex items-center justify-between gap-2 p-3 border border-red-300 bg-red-50 rounded-lg">
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-bold">⛔ {{ u.nickname || u.name }}</div>
                  <div class="text-[10px] text-gray-500">{{ u.email }}</div>
                  <div v-if="u.ban_reason" class="text-[10px] text-red-700 mt-0.5 font-semibold">사유: {{ u.ban_reason }}</div>
                  <div class="text-[9px] text-gray-400 mt-0.5">제명: {{ fmt(u.updated_at) }}</div>
                </div>
                <button @click="unpermaban(u)" class="text-[10px] bg-green-100 text-green-700 px-3 py-1.5 rounded font-bold hover:bg-green-200 flex-shrink-0">
                  🔓 해제
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ─── 유저 액션 모달 ─── -->
  <div v-if="userActionModal" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" @click.self="userActionModal = null">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden">
      <div class="bg-gradient-to-r from-amber-50 to-orange-50 px-5 py-4 border-b">
        <div class="flex items-center gap-3">
          <div class="w-12 h-12 rounded-full bg-amber-400 flex items-center justify-center text-white font-black text-lg">
            {{ (userActionModal.nickname || userActionModal.name || '?')[0] }}
          </div>
          <div class="flex-1 min-w-0">
            <div class="font-black text-gray-800">{{ userActionModal.nickname || userActionModal.name }}</div>
            <div class="text-[11px] text-gray-500">{{ userActionModal.email }}</div>
            <div class="flex items-center gap-2 mt-1">
              <span v-if="userReportCount > 0" class="text-[9px] px-1.5 py-0.5 rounded-full bg-red-100 text-red-700 font-bold">
                🚨 신고 {{ userReportCount }}건
              </span>
              <span v-if="userActionModal.is_banned" class="text-[9px] px-1.5 py-0.5 rounded-full bg-red-200 text-red-800 font-bold">
                영구제명 상태
              </span>
            </div>
          </div>
        </div>
      </div>
      <div class="p-5 space-y-2">
        <div class="text-xs font-bold text-gray-600 mb-2">액션 선택</div>
        <button @click="kickFromRoom" class="w-full px-4 py-2.5 bg-yellow-100 text-yellow-800 font-bold rounded-lg hover:bg-yellow-200 text-sm text-left">
          👋 이 방에서 강퇴 <span class="text-[10px] font-normal text-yellow-600">(재입장 가능)</span>
        </button>
        <button @click="banFromRoom" class="w-full px-4 py-2.5 bg-orange-100 text-orange-800 font-bold rounded-lg hover:bg-orange-200 text-sm text-left">
          🚫 이 방 차단 <span class="text-[10px] font-normal text-orange-600">(재입장 불가)</span>
        </button>
        <button @click="permaBanUser" class="w-full px-4 py-2.5 bg-red-500 text-white font-bold rounded-lg hover:bg-red-600 text-sm text-left">
          ⛔ 영구제명 <span class="text-[10px] font-normal text-red-100">(사이트 전체 차단)</span>
        </button>
      </div>
      <div class="bg-gray-50 px-5 py-3 flex justify-end">
        <button @click="userActionModal = null" class="text-gray-500 text-xs font-bold">취소</button>
      </div>
    </div>
  </div>

  <!-- ─── 방 개설 모달 ─── -->
  <div v-if="showCreate" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showCreate=false">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-5">
      <h3 class="font-black text-gray-800 mb-3">💬 새 채팅방 개설</h3>
      <input v-model="newRoomName" placeholder="방 이름" class="w-full border rounded-lg px-3 py-2 text-sm mb-3" />
      <select v-model="newRoomType" class="w-full border rounded-lg px-3 py-2 text-sm mb-3">
        <option value="public">공개 채팅 (모든 유저 접근)</option>
        <option value="group">그룹 채팅 (초대 전용)</option>
      </select>
      <div class="flex gap-2 justify-end">
        <button @click="showCreate=false" class="px-4 py-2 text-gray-500 text-sm">취소</button>
        <button @click="createRoom" :disabled="!newRoomName.trim()" class="bg-amber-400 text-amber-900 font-bold px-5 py-2 rounded-lg text-sm disabled:opacity-50">개설</button>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const rooms = ref([])
const roomsLoading = ref(true)
const search = ref('')

const activeRoom = ref(null)
const roomDetail = ref(null)
const detailLoading = ref(false)

const tabs = [
  { key: 'messages', icon: '💬', label: '메시지' },
  { key: 'announce', icon: '📢', label: '공지' },
  { key: 'reports', icon: '🚨', label: '신고' },
  { key: 'bans', icon: '🚫', label: '차단' },
  { key: 'permaban', icon: '⛔', label: '영구제명' },
]
const tab = ref('messages')

const announceText = ref('')
const announcing = ref(false)
const selectedDuration = ref('1h')
const customExpires = ref('')

const durationOptions = [
  { value: '10m', label: '10분' },
  { value: '30m', label: '30분' },
  { value: '1h', label: '1시간' },
  { value: '6h', label: '6시간' },
  { value: '12h', label: '12시간' },
  { value: '1d', label: '하루' },
  { value: 'custom', label: '날짜 선택' },
]

const adminMsg = ref('')
const sendingMsg = ref(false)

const showCreate = ref(false)
const newRoomName = ref('')
const newRoomType = ref('public')

const userActionModal = ref(null)

const userReportCount = computed(() => {
  if (!userActionModal.value || !roomDetail.value?.user_report_counts) return 0
  return roomDetail.value.user_report_counts[userActionModal.value.id] || 0
})

function fmt(v) {
  if (!v) return '-'
  try { return new Date(v).toLocaleString('ko-KR', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }) }
  catch { return v }
}

function tabCount(key) {
  if (!roomDetail.value) return 0
  if (key === 'announce') return roomDetail.value.announcements?.length || 0
  if (key === 'reports') return (roomDetail.value.reports || []).filter(r => r.status !== 'resolved').length
  if (key === 'bans') return roomDetail.value.bans?.length || 0
  if (key === 'permaban') return roomDetail.value.permanent_banned?.length || 0
  return 0
}

async function loadRooms() {
  roomsLoading.value = true
  try {
    const { data } = await axios.get('/api/admin/chat/rooms', { params: { search: search.value } })
    rooms.value = data.data?.data || []
  } catch {}
  roomsLoading.value = false
}

async function openRoom(r) {
  activeRoom.value = r
  tab.value = 'messages'
  await loadRoomDetail()
}

async function loadRoomDetail() {
  if (!activeRoom.value) return
  detailLoading.value = true
  try {
    const { data } = await axios.get(`/api/admin/chat/rooms/${activeRoom.value.id}`)
    roomDetail.value = data.data || null
  } catch {}
  detailLoading.value = false
}

async function createRoom() {
  try {
    await axios.post('/api/admin/chat/rooms', { name: newRoomName.value, type: newRoomType.value })
    showCreate.value = false
    newRoomName.value = ''
    loadRooms()
  } catch (e) { alert(e.response?.data?.message || '실패') }
}

async function deleteRoom(r) {
  if (!confirm(`"${r.name || '방 #' + r.id}"을(를) 삭제하시겠습니까?`)) return
  try {
    await axios.delete(`/api/admin/chat/rooms/${r.id}`)
    if (activeRoom.value?.id === r.id) { activeRoom.value = null; roomDetail.value = null }
    loadRooms()
  } catch (e) { alert(e.response?.data?.message || '실패') }
}

async function sendAnnounce() {
  if (!announceText.value.trim()) return
  if (selectedDuration.value === 'custom' && !customExpires.value) {
    alert('날짜를 선택해주세요')
    return
  }
  announcing.value = true
  try {
    const payload = { content: announceText.value, duration: selectedDuration.value }
    if (selectedDuration.value === 'custom') payload.expires_at = customExpires.value
    const { data } = await axios.post(`/api/admin/chat/rooms/${activeRoom.value.id}/announce`, payload)
    announceText.value = ''
    customExpires.value = ''
    alert(data.message || '공지가 발송되었습니다')
    await loadRoomDetail()
  } catch (e) { alert(e.response?.data?.message || '실패') }
  announcing.value = false
}

async function sendAdminMessage() {
  if (!adminMsg.value.trim() || !activeRoom.value) return
  sendingMsg.value = true
  try {
    await axios.post(`/api/chat/rooms/${activeRoom.value.id}/messages`, { content: adminMsg.value })
    adminMsg.value = ''
    await loadRoomDetail()
  } catch (e) { alert(e.response?.data?.message || '전송 실패') }
  sendingMsg.value = false
}

async function deleteMessage(m) {
  if (!confirm('이 메시지를 삭제할까요?')) return
  try {
    await axios.delete(`/api/admin/chat/rooms/${activeRoom.value.id}/messages/${m.id}`)
    await loadRoomDetail()
  } catch (e) { alert(e.response?.data?.message || '실패') }
}

async function resolveReport(r) {
  if (!confirm('이 신고를 해결 처리할까요?')) return
  try {
    await axios.post(`/api/admin/chat/rooms/${activeRoom.value.id}/reports/${r.id}/resolve`)
    await loadRoomDetail()
  } catch (e) { alert(e.response?.data?.message || '실패') }
}

async function unbanMember(u) {
  if (!confirm(`${u.nickname || u.name} 님의 방 차단을 해제할까요?`)) return
  try {
    await axios.delete(`/api/admin/chat/rooms/${activeRoom.value.id}/ban/${u.id}`)
    await loadRoomDetail()
  } catch (e) { alert(e.response?.data?.message || '실패') }
}

async function unpermaban(u) {
  if (!confirm(`${u.nickname || u.name} 님의 영구제명을 해제할까요?`)) return
  try {
    await axios.post(`/api/admin/users/${u.id}/unban`)
    await loadRoomDetail()
  } catch (e) { alert(e.response?.data?.message || '실패') }
}

// ─── 유저 액션 모달 ───
function openUserAction(user, message) {
  if (!user) return
  userActionModal.value = user
}

async function kickFromRoom() {
  const u = userActionModal.value
  if (!u) return
  if (!confirm(`${u.nickname || u.name} 님을 이 방에서 강퇴합니다.\n(재입장 가능)`)) return
  try {
    await axios.delete(`/api/admin/chat/rooms/${activeRoom.value.id}/members/${u.id}`)
    userActionModal.value = null
    await loadRoomDetail()
  } catch (e) { alert(e.response?.data?.message || '실패') }
}

async function banFromRoom() {
  const u = userActionModal.value
  if (!u) return
  const reason = prompt(`${u.nickname || u.name} 님을 이 방에서 차단합니다.\n사유를 입력하세요(선택):`)
  if (reason === null) return
  try {
    await axios.post(`/api/admin/chat/rooms/${activeRoom.value.id}/ban/${u.id}`, { reason })
    userActionModal.value = null
    await loadRoomDetail()
  } catch (e) { alert(e.response?.data?.message || '실패') }
}

async function permaBanUser() {
  const u = userActionModal.value
  if (!u) return
  const reason = prompt(`⛔ ${u.nickname || u.name} 님을 사이트 전체에서 영구제명합니다.\n이 유저는 더 이상 로그인할 수 없게 됩니다.\n사유를 입력하세요:`)
  if (!reason) return
  if (!confirm(`정말로 ${u.nickname || u.name} 님을 영구제명하시겠습니까?`)) return
  try {
    await axios.post(`/api/admin/chat/users/${u.id}/permaban`, { reason })
    userActionModal.value = null
    await loadRoomDetail()
    alert('영구제명되었습니다.')
  } catch (e) { alert(e.response?.data?.message || '실패') }
}

onMounted(() => loadRooms())
</script>
