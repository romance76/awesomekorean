<template>
<div>
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-4">
    <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="message-circle" :size="20" /></span>
    채팅 관리
  </h1>

  <div class="flex gap-4 items-start">
    <!-- ─── 왼쪽: 방 목록 ─── -->
    <div class="w-2/5 flex-shrink-0">
      <div class="card p-3 mb-3 flex gap-2">
        <input v-model="search" @keyup.enter="loadRooms()" placeholder="방 이름 검색..."
          class="input-soft flex-1 !w-auto !px-3 !py-1.5 !text-sm" />
        <button @click="loadRooms()" class="btn-secondary !px-3 !py-1.5 !text-xs flex-shrink-0">검색</button>
        <button @click="showCreate=true" class="btn-primary !px-3 !py-1.5 !text-xs flex-shrink-0"><AppIcon name="plus" :size="13" /> 방 개설</button>
      </div>

      <div v-if="roomsLoading" class="text-center py-8 text-ink-muted">로딩중...</div>
      <div v-else-if="!rooms.length" class="py-16 text-center">
        <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="message-circle" :size="28" :stroke-width="1.5" /></div>
        <p class="text-sm text-ink-muted">방 없음</p>
      </div>
      <div v-else class="card overflow-hidden divide-y divide-gray-50 max-h-[75vh] overflow-y-auto">
        <div v-for="r in rooms" :key="r.id"
          @click="openRoom(r)"
          class="px-3 py-3 cursor-pointer hover:bg-amber-50/40 transition-colors"
          :class="activeRoom?.id === r.id ? 'bg-amber-50 border-l-4 border-l-amber-500' : ''">
          <div class="flex items-start justify-between gap-2">
            <div class="flex-1 min-w-0">
              <div class="font-semibold text-ink text-sm truncate">{{ r.name || '(이름 없음)' }}</div>
              <div class="text-xs text-ink-muted mt-0.5 flex items-center gap-2">
                <span class="badge-gray">{{ r.type }}</span>
                <span class="inline-flex items-center gap-0.5"><AppIcon name="users" :size="11" /> {{ r.users_count || 0 }}</span>
              </div>
              <div v-if="r.messages?.[0]" class="text-[11px] text-ink-muted mt-1 truncate">
                {{ r.messages[0].user?.nickname || r.messages[0].user?.name || '?' }}: {{ r.messages[0].content }}
              </div>
            </div>
            <button @click.stop="deleteRoom(r)" class="text-red-400 hover:text-red-600 flex-shrink-0 transition-colors"><AppIcon name="trash" :size="14" /></button>
          </div>
        </div>
      </div>
    </div>

    <!-- ─── 오른쪽: 채팅창 상세 ─── -->
    <div class="flex-1 min-w-0">
      <!-- 방 선택 전 빈 상태 -->
      <div v-if="!activeRoom" class="card p-10 text-center">
        <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="message-circle" :size="28" :stroke-width="1.5" /></div>
        <p class="text-sm text-ink-muted">왼쪽에서 채팅방을 선택하세요</p>
      </div>

      <!-- 채팅창 -->
      <div v-else class="card overflow-hidden">
        <!-- 헤더 -->
        <div class="px-4 py-3 border-b border-gray-50 bg-amber-50 flex items-center justify-between">
          <div>
            <div class="flex items-center gap-1.5 font-bold text-amber-800"><AppIcon name="message-circle" :size="16" /> {{ activeRoom.name || '(이름 없음)' }}</div>
            <div class="text-[11px] text-amber-700">
              ID: {{ activeRoom.id }} · {{ activeRoom.type }} · 멤버: {{ roomDetail?.members?.length || 0 }}명
            </div>
          </div>
          <button @click="activeRoom = null; roomDetail = null" class="text-amber-700 hover:text-amber-900 transition-colors"><AppIcon name="x" :size="18" /></button>
        </div>

        <!-- 탭 바 -->
        <div class="flex border-b border-gray-50 overflow-x-auto">
          <button v-for="t in tabs" :key="t.key" @click="tab = t.key"
            class="flex items-center gap-1 px-4 py-2.5 text-xs font-bold border-b-2 whitespace-nowrap transition-colors"
            :class="tab === t.key ? 'border-amber-500 text-amber-700' : 'border-transparent text-ink-muted hover:text-ink-light'">
            <AppIcon :name="t.icon" :size="13" />
            <span>{{ t.label }}</span>
            <span v-if="tabCount(t.key) > 0" class="ml-1 text-[11px] px-1.5 py-0.5 rounded-full bg-red-100 text-red-700 font-bold">
              {{ tabCount(t.key) }}
            </span>
          </button>
        </div>

        <div v-if="detailLoading" class="p-10 text-center text-ink-muted">로딩중...</div>
        <div v-else-if="roomDetail">
          <!-- 💬 메시지 탭 -->
          <div v-if="tab === 'messages'" class="flex flex-col">
          <div class="p-3 max-h-[55vh] overflow-y-auto">
            <div v-if="!roomDetail.messages?.length" class="text-center py-8 text-ink-muted">메시지 없음</div>
            <div v-else class="space-y-1.5">
              <div v-for="m in [...roomDetail.messages].reverse()" :key="m.id"
                class="group flex items-start gap-2 p-2 rounded-lg hover:bg-gray-50 transition-colors"
                :class="m.type === 'system' ? 'bg-amber-50' : ''">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <button @click="openUserAction(m.user, m)"
                      class="text-xs font-bold text-blue-700 hover:underline">
                      {{ m.user?.nickname || m.user?.name || '?' }}
                    </button>
                    <span v-if="(m.user_report_count || 0) > 0"
                      class="text-[11px] px-1.5 py-0.5 rounded-full bg-red-100 text-red-700 font-bold">
                      신고 {{ m.user_report_count }}
                    </span>
                    <span class="text-[11px] text-ink-faint">{{ fmt(m.created_at) }}</span>
                    <span v-if="m.type === 'system'" class="text-[11px] px-1 py-0.5 bg-amber-200 text-amber-800 rounded font-bold">SYSTEM</span>
                  </div>
                  <div class="text-xs text-ink mt-0.5 break-words">{{ m.content }}</div>
                </div>
                <button @click="deleteMessage(m)" class="opacity-0 group-hover:opacity-100 text-red-300 hover:text-red-500 flex-shrink-0 transition-colors"><AppIcon name="trash" :size="13" /></button>
              </div>
            </div>
          </div>
          <!-- 관리자 메시지 입력창 -->
          <div class="border-t border-gray-50 px-3 py-2 flex gap-2 items-center bg-red-50/50">
            <span class="inline-flex items-center gap-1 text-[11px] bg-red-500 text-white px-2 py-1 rounded-full font-bold flex-shrink-0"><AppIcon name="shield" :size="11" /> 관리자</span>
            <input v-model="adminMsg" @keyup.enter="sendAdminMessage" placeholder="관리자로 메시지 전송..."
              class="input-soft flex-1 !w-auto !rounded-full !px-3 !py-1.5 !text-xs" />
            <button @click="sendAdminMessage" :disabled="!adminMsg.trim() || sendingMsg"
              class="bg-red-500 hover:bg-red-600 text-white font-bold px-3 py-1.5 rounded-full text-xs disabled:opacity-50 transition-colors">
              {{ sendingMsg ? '...' : '전송' }}
            </button>
          </div>
          </div>

          <!-- 📢 공지 탭 -->
          <div v-else-if="tab === 'announce'" class="p-4">
            <div class="bg-amber-50 rounded-xl p-3 mb-4">
              <div class="flex items-center gap-1 text-xs font-bold text-amber-700 mb-2"><AppIcon name="megaphone" :size="13" /> 새 공지 작성</div>
              <div class="mb-2">
                <div class="flex items-center gap-1 text-xs font-bold text-amber-800 mb-1"><AppIcon name="clock" :size="12" /> 공지 유지 기간</div>
                <div class="flex flex-wrap gap-1">
                  <button v-for="opt in durationOptions" :key="opt.value"
                    @click="selectedDuration = opt.value"
                    :class="selectedDuration === opt.value ? 'bg-amber-400 text-white shadow-btn' : 'bg-white text-ink-light hover:bg-amber-100'"
                    class="px-2.5 py-1 rounded-lg text-xs font-bold transition-colors">{{ opt.label }}</button>
                </div>
                <input v-if="selectedDuration === 'custom'" v-model="customExpires" type="datetime-local"
                  class="input-soft !px-2 !py-1 !text-xs mt-2" />
              </div>
              <textarea v-model="announceText" rows="3" placeholder="공지 내용을 입력하세요..."
                class="input-soft"></textarea>
              <div class="flex justify-end mt-2">
                <button @click="sendAnnounce" :disabled="!announceText.trim() || announcing"
                  class="btn-primary !px-5 !py-2 !text-xs">
                  {{ announcing ? '전송중...' : '공지 발송' }}
                </button>
              </div>
            </div>
            <div class="flex items-center gap-1 text-xs font-bold text-ink-light mb-2"><AppIcon name="list" :size="13" /> 과거 공지 ({{ roomDetail.announcements?.length || 0 }})</div>
            <div v-if="!roomDetail.announcements?.length" class="text-center py-6 text-ink-muted text-sm">공지 없음</div>
            <div v-else class="space-y-2 max-h-[45vh] overflow-y-auto">
              <div v-for="a in roomDetail.announcements" :key="a.id" class="rounded-xl p-2.5 bg-amber-50/60">
                <div class="flex items-center gap-2 mb-1">
                  <span class="text-xs font-bold text-amber-700">{{ a.user?.nickname || a.user?.name }}</span>
                  <span class="text-[11px] text-ink-faint">{{ fmt(a.created_at) }}</span>
                </div>
                <div class="text-xs text-ink break-words">{{ a.content }}</div>
              </div>
            </div>
          </div>

          <!-- 🚨 신고 탭 -->
          <div v-else-if="tab === 'reports'" class="p-3 max-h-[65vh] overflow-y-auto">
            <div v-if="!roomDetail.reports?.length" class="text-center py-8 text-ink-muted">신고 없음</div>
            <div v-else class="space-y-2">
              <div v-for="r in roomDetail.reports" :key="r.id" class="rounded-xl p-3"
                :class="r.status === 'resolved' ? 'bg-gray-50' : 'bg-red-50'">
                <div class="flex items-start justify-between gap-2">
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap mb-1">
                      <span class="text-xs px-1.5 py-0.5 rounded-full font-bold"
                        :class="r.status === 'resolved' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
                        {{ r.status === 'resolved' ? '해결됨' : '미해결' }}
                      </span>
                      <span class="text-[11px] text-ink-muted">{{ fmt(r.created_at) }}</span>
                    </div>
                    <div class="text-xs">
                      <span class="text-ink-muted">신고자:</span>
                      <span class="font-bold ml-1 text-ink">{{ r.reporter_name }}</span>
                      <span class="text-ink-faint ml-1 text-[11px]">({{ r.reporter_email }})</span>
                    </div>
                    <div class="text-xs mt-0.5">
                      <span class="text-ink-muted">대상:</span>
                      <button @click="openUserAction({id: r.target_user_id, name: r.target_name, email: r.target_email})"
                        class="font-bold ml-1 text-blue-700 hover:underline">{{ r.target_name }}</button>
                    </div>
                    <div class="text-xs mt-0.5">
                      <span class="text-ink-muted">이유:</span>
                      <span class="ml-1 text-ink-light">{{ r.reason }}</span>
                    </div>
                    <div v-if="r.report_content" class="text-xs mt-0.5">
                      <span class="text-ink-muted">상세:</span>
                      <span class="ml-1 text-ink-light">{{ r.report_content }}</span>
                    </div>
                    <div class="mt-2 p-2 bg-white rounded-lg text-xs text-ink-light">
                      <span class="text-[11px] text-ink-faint">메시지 내용:</span>
                      <div class="mt-0.5">{{ r.message_content || '(삭제됨)' }}</div>
                    </div>
                  </div>
                  <button v-if="r.status !== 'resolved'" @click="resolveReport(r)"
                    class="inline-flex items-center gap-1 text-xs bg-emerald-500 text-white font-bold px-2.5 py-1 rounded-lg hover:bg-emerald-600 flex-shrink-0 transition-colors">
                    <AppIcon name="check" :size="12" /> 해결
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- 🚫 차단 탭 (이 방만) -->
          <div v-else-if="tab === 'bans'" class="p-3 max-h-[65vh] overflow-y-auto">
            <div class="text-xs font-bold text-ink-light mb-2">이 방 차단 목록 ({{ roomDetail.bans?.length || 0 }})</div>
            <div v-if="!roomDetail.bans?.length" class="text-center py-8 text-ink-muted">차단된 유저 없음</div>
            <div v-else class="space-y-2">
              <div v-for="u in roomDetail.bans" :key="u.id"
                class="flex items-center justify-between gap-2 p-3 bg-red-50 rounded-xl">
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-bold text-ink">{{ u.nickname || u.name }}</div>
                  <div class="text-[11px] text-ink-muted">{{ u.email }}</div>
                  <div v-if="u.reason" class="text-[11px] text-red-600 mt-0.5">사유: {{ u.reason }}</div>
                  <div class="text-[11px] text-ink-faint mt-0.5">{{ fmt(u.banned_at) }}</div>
                </div>
                <button @click="unbanMember(u)" class="inline-flex items-center gap-1 text-xs bg-emerald-50 text-emerald-700 px-3 py-1.5 rounded-lg font-bold hover:bg-emerald-100 flex-shrink-0 transition-colors">
                  <AppIcon name="key" :size="12" /> 해제
                </button>
              </div>
            </div>

            <!-- 멤버 리스트 -->
            <div class="flex items-center gap-1 text-xs font-bold text-ink-light mb-2 mt-4 border-t border-gray-100 pt-3"><AppIcon name="users" :size="13" /> 현재 멤버 ({{ roomDetail.members?.length || 0 }})</div>
            <div v-if="!roomDetail.members?.length" class="text-center py-4 text-ink-muted text-sm">멤버 없음</div>
            <div v-else class="space-y-1">
              <div v-for="u in roomDetail.members" :key="u.id"
                class="flex items-center justify-between gap-2 p-2 border border-gray-100 rounded-lg">
                <button @click="openUserAction(u)" class="flex-1 min-w-0 text-left hover:bg-gray-50 rounded-lg px-1 transition-colors">
                  <div class="text-xs font-semibold text-blue-700">{{ u.nickname || u.name }}</div>
                  <div class="text-[11px] text-ink-faint">{{ u.email }}</div>
                </button>
                <span v-if="u.is_banned" class="text-[11px] px-1.5 py-0.5 rounded-full bg-red-100 text-red-700 font-bold">영구제명</span>
              </div>
            </div>
          </div>

          <!-- ⛔ 영구제명 탭 -->
          <div v-else-if="tab === 'permaban'" class="p-3 max-h-[65vh] overflow-y-auto">
            <div class="text-xs font-bold text-ink-light mb-2">사이트 전체 영구제명 ({{ roomDetail.permanent_banned?.length || 0 }})</div>
            <div v-if="!roomDetail.permanent_banned?.length" class="text-center py-8 text-ink-muted">영구제명 유저 없음</div>
            <div v-else class="space-y-2">
              <div v-for="u in roomDetail.permanent_banned" :key="u.id"
                class="flex items-center justify-between gap-2 p-3 bg-red-50 rounded-xl">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-1 text-sm font-bold text-ink"><AppIcon name="alert-circle" :size="14" class="text-red-500" /> {{ u.nickname || u.name }}</div>
                  <div class="text-[11px] text-ink-muted">{{ u.email }}</div>
                  <div v-if="u.ban_reason" class="text-[11px] text-red-700 mt-0.5 font-semibold">사유: {{ u.ban_reason }}</div>
                  <div class="text-[11px] text-ink-faint mt-0.5">제명: {{ fmt(u.updated_at) }}</div>
                </div>
                <button @click="unpermaban(u)" class="inline-flex items-center gap-1 text-xs bg-emerald-50 text-emerald-700 px-3 py-1.5 rounded-lg font-bold hover:bg-emerald-100 flex-shrink-0 transition-colors">
                  <AppIcon name="key" :size="12" /> 해제
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
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
      <div class="bg-amber-50 px-5 py-4 border-b border-gray-50">
        <div class="flex items-center gap-3">
          <div class="w-12 h-12 rounded-full bg-amber-400 flex items-center justify-center text-white font-black text-lg">
            {{ (userActionModal.nickname || userActionModal.name || '?')[0] }}
          </div>
          <div class="flex-1 min-w-0">
            <div class="font-bold text-ink">{{ userActionModal.nickname || userActionModal.name }}</div>
            <div class="text-[11px] text-ink-muted">{{ userActionModal.email }}</div>
            <div class="flex items-center gap-2 mt-1">
              <span v-if="userReportCount > 0" class="text-[11px] px-1.5 py-0.5 rounded-full bg-red-100 text-red-700 font-bold">
                신고 {{ userReportCount }}건
              </span>
              <span v-if="userActionModal.is_banned" class="text-[11px] px-1.5 py-0.5 rounded-full bg-red-200 text-red-800 font-bold">
                영구제명 상태
              </span>
            </div>
          </div>
        </div>
      </div>
      <div class="p-5 space-y-2">
        <div class="text-xs font-bold text-ink-light mb-2">액션 선택</div>
        <button @click="kickFromRoom" class="w-full flex items-center gap-2 px-4 py-2.5 bg-yellow-100 text-yellow-800 font-bold rounded-xl hover:bg-yellow-200 text-sm text-left transition-colors">
          <AppIcon name="log-out" :size="15" /> 이 방에서 강퇴 <span class="text-[11px] font-normal text-yellow-600">(재입장 가능)</span>
        </button>
        <button @click="banFromRoom" class="w-full flex items-center gap-2 px-4 py-2.5 bg-orange-100 text-orange-800 font-bold rounded-xl hover:bg-orange-200 text-sm text-left transition-colors">
          <AppIcon name="shield" :size="15" /> 이 방 차단 <span class="text-[11px] font-normal text-orange-600">(재입장 불가)</span>
        </button>
        <button @click="permaBanUser" class="w-full flex items-center gap-2 px-4 py-2.5 bg-red-500 text-white font-bold rounded-xl hover:bg-red-600 text-sm text-left transition-colors">
          <AppIcon name="alert-circle" :size="15" /> 영구제명 <span class="text-[11px] font-normal text-red-100">(사이트 전체 차단)</span>
        </button>
      </div>
      <div class="bg-gray-50 px-5 py-3 flex justify-end">
        <button @click="userActionModal = null" class="btn-ghost !px-4 !py-1.5 !text-xs">취소</button>
      </div>
    </div>
  </div>

  <!-- ─── 방 개설 모달 ─── -->
  <div v-if="showCreate" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showCreate=false">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-5">
      <h3 class="flex items-center gap-2 font-bold text-ink mb-3">
        <span class="icon-chip w-7 h-7 bg-amber-50 text-amber-600"><AppIcon name="message-circle" :size="14" /></span>새 채팅방 개설
      </h3>
      <input v-model="newRoomName" placeholder="방 이름" class="input-soft mb-3" />
      <select v-model="newRoomType" class="input-soft mb-3">
        <option value="public">공개 채팅 (모든 유저 접근)</option>
        <option value="group">그룹 채팅 (초대 전용)</option>
      </select>
      <div class="flex gap-2 justify-end">
        <button @click="showCreate=false" class="btn-ghost !px-4 !py-2">취소</button>
        <button @click="createRoom" :disabled="!newRoomName.trim()" class="btn-primary !px-5 !py-2">개설</button>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const rooms = ref([])
const roomsLoading = ref(true)
const search = ref('')

const activeRoom = ref(null)
const roomDetail = ref(null)
const detailLoading = ref(false)

const tabs = [
  { key: 'messages', icon: 'message-circle', label: '메시지' },
  { key: 'announce', icon: 'megaphone', label: '공지' },
  { key: 'reports', icon: 'alert-circle', label: '신고' },
  { key: 'bans', icon: 'shield', label: '차단' },
  { key: 'permaban', icon: 'lock', label: '영구제명' },
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
