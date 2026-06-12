<template>
<div v-if="show && user" class="fixed inset-0 z-50" @click.self="$emit('close')">
  <div class="absolute inset-0"></div>
  <!-- 팝업 컨테이너 -->
  <div class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-white rounded-2xl shadow-xl overflow-hidden animate-in transition-all duration-200" :class="view === 'message' ? 'w-80' : 'w-64'">

    <!-- 헤더 -->
    <div class="bg-gradient-to-r from-[#FF8A53] to-[#F2570F] px-3 py-2 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-full bg-white/25 flex items-center justify-center text-sm font-bold text-white">{{ (user.name || '?')[0] }}</div>
        <div>
          <div class="text-sm font-bold text-white leading-tight">{{ user.name }}</div>
          <div class="text-[11px] text-white/80">{{ user.city ? user.city + ', ' + user.state : '' }}</div>
        </div>
      </div>
      <button @click="closeAll" class="text-white/70 hover:text-white transition-colors"><AppIcon name="x" :size="16" /></button>
    </div>

    <!-- 메인 뷰: 기본 버튼들 -->
    <div v-if="view === 'main'" class="p-2 flex flex-col gap-1.5">
      <div v-if="!isMe" class="flex gap-1.5">
        <button @click="view = 'message'" class="flex-1 bg-blue-500 text-white text-xs font-bold py-1.5 rounded-lg hover:bg-blue-600 transition-colors inline-flex items-center justify-center gap-1"><AppIcon name="mail" :size="12" /> 쪽지</button>
        <!-- 친구 아닌 경우 -->
        <button v-if="user.allow_friend_request && !isFriend && !isPending" @click="view = 'confirm'" class="flex-1 bg-amber-400 text-white text-xs font-bold py-1.5 rounded-lg hover:bg-amber-500 transition-colors inline-flex items-center justify-center gap-1"><AppIcon name="user-plus" :size="12" /> 친구</button>
        <!-- 대기중 (내가 보낸 요청) -->
        <button v-else-if="isPending && isSender" @click="view = 'pending'" class="flex-1 bg-gray-100 text-ink-muted text-xs font-bold py-1.5 rounded-lg hover:bg-gray-200 transition-colors inline-flex items-center justify-center gap-1"><AppIcon name="clock" :size="12" /> 대기중</button>
        <!-- 대기중 (상대가 보낸 요청) -->
        <button v-else-if="isPending && !isSender" @click="view = 'received'" class="flex-1 bg-orange-100 text-orange-600 text-xs font-bold py-1.5 rounded-lg hover:bg-orange-200 transition-colors inline-flex items-center justify-center gap-1"><AppIcon name="mail" :size="12" /> 요청받음</button>
        <!-- 이미 친구 -->
        <div v-else-if="isFriend" class="flex-1 text-center text-xs text-emerald-600 py-1.5 font-bold inline-flex items-center justify-center gap-1"><AppIcon name="check" :size="12" /> 친구</div>
        <!-- 친구요청 차단 -->
        <div v-else-if="!user.allow_friend_request" class="flex-1 flex items-center justify-center text-ink-faint py-1.5"><AppIcon name="lock" :size="12" /></div>
      </div>
      <template v-if="isFriend">
        <RouterLink :to="`/profile/${user.id}`" @click="$emit('close')" class="flex items-center justify-center gap-1 text-center text-ink-light text-xs font-semibold py-1.5 rounded-lg bg-gray-50 hover:bg-gray-100 transition-colors"><AppIcon name="user" :size="12" /> 프로필</RouterLink>
      </template>
    </div>

    <!-- 친구요청 확인 뷰 -->
    <div v-else-if="view === 'confirm'" class="p-3">
      <p class="text-sm font-bold text-ink text-center mb-2">친구 요청을 하시겠습니까?</p>
      <div class="bg-amber-50 rounded-xl p-2 mb-3 text-[11px] text-amber-700 leading-relaxed">
        <p>• 요청 후 <b>24시간이 지나야</b> 취소할 수 있습니다.</p>
        <p>• 상대방이 <b>7일 이내</b> 수락하지 않으면 자동으로 만료됩니다.</p>
      </div>
      <div class="flex gap-1.5">
        <button @click="view = 'main'" class="flex-1 bg-gray-100 text-ink-light text-xs font-bold py-1.5 rounded-lg hover:bg-gray-200 transition-colors">취소</button>
        <button @click="addFriend" :disabled="adding" class="flex-1 bg-amber-400 text-white text-xs font-bold py-1.5 rounded-lg hover:bg-amber-500 disabled:opacity-50 transition-colors">
          {{ adding ? '전송중...' : '요청 보내기' }}
        </button>
      </div>
    </div>

    <!-- 대기중 상세 뷰 (취소 가능 여부) -->
    <div v-else-if="view === 'pending'" class="p-3">
      <p class="text-sm font-bold text-ink text-center mb-2 flex items-center justify-center gap-1"><AppIcon name="clock" :size="14" /> 친구 요청 대기중</p>
      <div class="bg-gray-50 rounded-xl p-2 mb-3 text-[11px] text-ink-light leading-relaxed">
        <p v-if="!canCancel">• 요청 후 24시간이 지나야 취소할 수 있습니다.</p>
        <p v-if="!canCancel && cancelTimeLeft">• 남은 시간: <b>{{ cancelTimeLeft }}</b></p>
        <p v-if="canCancel">• 지금 취소할 수 있습니다.</p>
        <p>• 7일 이내 수락하지 않으면 자동 만료됩니다.</p>
      </div>
      <div class="flex gap-1.5">
        <button @click="view = 'main'" class="flex-1 bg-gray-100 text-ink-light text-xs font-bold py-1.5 rounded-lg hover:bg-gray-200 transition-colors">돌아가기</button>
        <button v-if="canCancel" @click="cancelFriend" :disabled="cancelling" class="flex-1 bg-red-500 text-white text-xs font-bold py-1.5 rounded-lg hover:bg-red-600 disabled:opacity-50 transition-colors">
          {{ cancelling ? '취소중...' : '요청 취소' }}
        </button>
        <div v-else class="flex-1 bg-gray-200 text-ink-faint text-xs font-bold py-1.5 rounded-lg text-center cursor-not-allowed">취소 불가</div>
      </div>
    </div>

    <!-- 받은 요청 수락/거절 뷰 -->
    <div v-else-if="view === 'received'" class="p-3">
      <p class="text-sm font-bold text-ink text-center mb-2 flex items-center justify-center gap-1"><AppIcon name="mail" :size="14" /> 친구 요청을 받았습니다</p>
      <div class="bg-orange-50 rounded-xl p-2 mb-3 text-[11px] text-orange-700 leading-relaxed">
        <p><b>{{ user.name }}</b>님이 친구 요청을 보냈습니다.</p>
        <p>수락하면 서로 친구가 됩니다.</p>
      </div>
      <div class="flex gap-1.5">
        <button @click="view = 'main'" class="flex-1 bg-gray-100 text-ink-light text-xs font-bold py-1.5 rounded-lg hover:bg-gray-200 transition-colors">돌아가기</button>
        <button @click="acceptFriend" :disabled="accepting" class="flex-1 bg-emerald-500 text-white text-xs font-bold py-1.5 rounded-lg hover:bg-emerald-600 disabled:opacity-50 transition-colors inline-flex items-center justify-center gap-1">
          <AppIcon v-if="!accepting" name="check" :size="12" />{{ accepting ? '수락중...' : '수락' }}
        </button>
      </div>
      <div v-if="acceptMsg" class="mt-2 text-center text-[11px] text-emerald-600 font-bold animate-fade">{{ acceptMsg }}</div>
    </div>

    <!-- 쪽지 보내기 뷰 -->
    <div v-else-if="view === 'message'" class="p-3">
      <template v-if="!msgSent">
        <p class="text-xs font-bold text-ink-light mb-2 flex items-center gap-1"><AppIcon name="mail" :size="13" /> {{ user.name }}님에게 쪽지</p>
        <textarea v-model="msgContent" rows="5" maxlength="500" placeholder="쪽지 내용을 입력하세요..." class="input-soft"></textarea>
        <div class="flex justify-between items-center mt-1.5">
          <span class="text-xs text-ink-faint">{{ msgContent.length }}/500</span>
          <div class="flex gap-1.5">
            <button @click="view = 'main'; msgContent = ''" class="bg-gray-100 text-ink-light text-xs font-bold px-3 py-1.5 rounded-lg hover:bg-gray-200 transition-colors">취소</button>
            <button @click="sendMsg" :disabled="sending || !msgContent.trim()" class="bg-blue-500 text-white text-xs font-bold px-3 py-1.5 rounded-lg hover:bg-blue-600 disabled:opacity-50 transition-colors">
              {{ sending ? '전송중...' : '보내기' }}
            </button>
          </div>
        </div>
      </template>
      <!-- 전송 완료 → 보낸 내용 표시 -->
      <template v-else>
        <p class="text-xs font-bold text-emerald-600 mb-2 flex items-center gap-1"><AppIcon name="check" :size="13" /> 쪽지를 보냈습니다</p>
        <div class="bg-blue-50 rounded-xl p-2.5 text-sm text-ink-light whitespace-pre-wrap leading-relaxed max-h-32 overflow-y-auto">{{ sentContent }}</div>
        <div class="flex gap-1.5 mt-2">
          <button @click="msgSent = false; msgContent = ''" class="flex-1 bg-blue-500 text-white text-xs font-bold py-1.5 rounded-lg hover:bg-blue-600 transition-colors">새 쪽지 쓰기</button>
          <button @click="msgSent = false; msgContent = ''; sentContent = ''; closeAll()" class="flex-1 bg-gray-100 text-ink-light text-xs font-bold py-1.5 rounded-lg hover:bg-gray-200 transition-colors">닫기</button>
        </div>
      </template>
    </div>

  </div>
</div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useAuthStore } from '../stores/auth'
import axios from 'axios'
import AppIcon from './AppIcon.vue'

const props = defineProps({ show: Boolean, userId: [Number, String] })
const emit = defineEmits(['close'])
const auth = useAuthStore()

const user = ref(null)
const isFriend = ref(false)
const isPending = ref(false)
const isSender = ref(false)
const canCancel = ref(false)
const pendingId = ref(null)
const pendingCreatedAt = ref(null)
const adding = ref(false)
const cancelling = ref(false)
const view = ref('main') // main | confirm | pending | received | message

// 수락
const accepting = ref(false)
const acceptMsg = ref('')

// 쪽지
const msgContent = ref('')
const sending = ref(false)
const msgSent = ref(false)
const sentContent = ref('')

const isMe = computed(() => auth.user?.id == props.userId)

const cancelTimeLeft = computed(() => {
  if (!pendingCreatedAt.value) return ''
  const created = new Date(pendingCreatedAt.value)
  const canAt = new Date(created.getTime() + 24 * 60 * 60 * 1000)
  const diff = canAt - Date.now()
  if (diff <= 0) return ''
  const h = Math.floor(diff / 3600000)
  const m = Math.floor((diff % 3600000) / 60000)
  return `${h}시간 ${m}분`
})

watch(() => [props.userId, props.show], async ([id, s]) => {
  if (!id || !s) return
  // 뷰 리셋
  view.value = 'main'
  msgContent.value = ''
  msgSent.value = false
  user.value = null; isFriend.value = false; isPending.value = false; isSender.value = false; canCancel.value = false; pendingId.value = null; pendingCreatedAt.value = null

  try { const { data } = await axios.get(`/api/users/${id}`); user.value = data.data } catch { return }

  if (auth.isLoggedIn && !isMe.value) {
    try {
      const { data } = await axios.get('/api/friends')
      const list = data.data || []
      const match = list.find(f => f.friend?.id == id)
      if (match) {
        isFriend.value = match.status === 'accepted'
        isPending.value = match.status === 'pending'
        isSender.value = match.is_sender
        canCancel.value = match.can_cancel
        pendingId.value = match.id
        pendingCreatedAt.value = match.created_at
      }
    } catch {}
  }
}, { immediate: true })

async function addFriend() {
  adding.value = true
  try {
    const { data } = await axios.post(`/api/friends/request/${user.value.id}`, { source: 'community' })
    if (data.auto_accepted) {
      // 상대방이 이미 요청 보냈으면 자동으로 친구 됨
      isFriend.value = true
      isPending.value = false
    } else {
      isPending.value = true
      isSender.value = true
      canCancel.value = false
      pendingId.value = data.data?.id
      pendingCreatedAt.value = new Date().toISOString()
    }
    view.value = 'main'
  } catch (e) {
    alert(e.response?.data?.message || '요청 실패')
  }
  adding.value = false
}

async function cancelFriend() {
  if (!pendingId.value) return
  cancelling.value = true
  try {
    await axios.delete(`/api/friends/cancel/${pendingId.value}`)
    isPending.value = false
    isSender.value = false
    canCancel.value = false
    pendingId.value = null
    view.value = 'main'
  } catch (e) {
    alert(e.response?.data?.message || '취소 실패')
  }
  cancelling.value = false
}

async function acceptFriend() {
  if (!pendingId.value) return
  accepting.value = true
  try {
    await axios.post(`/api/friends/accept/${pendingId.value}`)
    isFriend.value = true
    isPending.value = false
    acceptMsg.value = '✅ 친구가 되었습니다!'
    setTimeout(() => { acceptMsg.value = ''; view.value = 'main' }, 1500)
  } catch (e) {
    alert(e.response?.data?.message || '수락 실패')
  }
  accepting.value = false
}

async function sendMsg() {
  if (!msgContent.value.trim()) return
  sending.value = true
  try {
    await axios.post('/api/messages', { receiver_id: user.value.id, content: msgContent.value.trim() })
    sentContent.value = msgContent.value.trim()
    msgSent.value = true
    msgContent.value = ''
  } catch (e) {
    alert(e.response?.data?.message || '전송 실패')
  }
  sending.value = false
}

function closeAll() {
  view.value = 'main'
  msgContent.value = ''
  emit('close')
}
</script>

<style scoped>
.animate-in { animation: popIn 0.15s ease-out; }
@keyframes popIn { from { transform: translate(-50%,-50%) scale(0.9); opacity: 0; } to { transform: translate(-50%,-50%) scale(1); opacity: 1; } }
.animate-fade { animation: fadeIn 0.3s ease-out; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
</style>
