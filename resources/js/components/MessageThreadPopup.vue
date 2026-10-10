<template>
<!--
  쪽지 대화 팝업 — 어느 페이지에서든 알림(새 쪽지)을 누르면 그 자리에서 채팅창처럼 열린다.
  window.openMessageThread(partnerId) 로 열고, 쪽지함(마이페이지)과 같은 /api/messages/thread API 를 쓴다.
-->
<Teleport to="body">
  <div v-if="show" class="fixed z-[9990] bg-white shadow-2xl flex flex-col overflow-hidden"
    :class="isMobile ? 'inset-0' : 'right-5 bottom-5 w-[380px] rounded-2xl border border-gray-100'"
    :style="isMobile ? '' : 'height: min(560px, calc(100vh - 7rem))'">
    <!-- 헤더 -->
    <div class="px-3 py-2.5 flex items-center gap-2 border-b border-gray-100 bg-blue-50 flex-shrink-0">
      <UserAvatar :user="partner" :size="44" />
      <div class="min-w-0 flex-1">
        <div class="text-sm font-bold text-ink truncate">{{ partner?.name || '쪽지' }}</div>
        <div class="text-[11px] text-ink-muted">쪽지 대화</div>
      </div>
      <button @click="goInbox" class="text-[11px] text-ink-muted hover:text-ink px-2 py-1 rounded-lg hover:bg-white/60" title="쪽지함에서 전체 보기">쪽지함</button>
      <button @click="close" class="w-8 h-8 rounded-full hover:bg-white/70 text-ink-muted flex items-center justify-center" title="닫기"><AppIcon name="x" :size="18" /></button>
    </div>

    <!-- 대화 -->
    <div ref="box" class="flex-1 min-h-0 overflow-y-auto bg-gray-50 p-3">
      <div v-if="loading" class="text-center text-sm text-ink-faint py-10">불러오는 중...</div>
      <div v-else-if="!messages.length" class="text-center text-sm text-ink-faint py-10">아직 주고받은 쪽지가 없습니다</div>
      <template v-for="(m, i) in messages" :key="m.id">
        <div v-if="isNewDay(i)" class="flex justify-center my-3"><span class="text-[11px] text-ink-muted bg-white border border-gray-100 rounded-full px-3 py-0.5">{{ dayLabel(m.created_at) }}</span></div>
        <div class="flex items-end gap-1.5 mb-1.5" :class="m.sender_id === auth.user?.id ? 'justify-end' : 'justify-start'">
          <template v-if="m.sender_id === auth.user?.id">
            <span v-if="m._failed" class="text-[10px] text-red-500 flex flex-col items-end leading-tight">
              <span>전송 실패</span>
              <span><button type="button" class="underline" @click="retry(partnerId, m.id)">재전송</button> · <button type="button" class="underline text-ink-faint" @click="discard(m.id)">삭제</button></span>
            </span>
            <span v-else-if="m._tmp" class="inline-block w-3 h-3 border-2 border-gray-300 border-t-transparent rounded-full animate-spin"></span>
            <span v-else class="text-[10px] text-ink-faint">{{ timeLabel(m.created_at) }}</span>
            <div class="max-w-[75%] bg-amber-400 text-white text-sm rounded-2xl rounded-br-sm px-3 py-2 whitespace-pre-wrap break-words" :class="m._failed ? 'ring-2 ring-red-400' : (m._tmp ? 'opacity-60' : '')">{{ m.content }}</div>
          </template>
          <template v-else>
            <div class="max-w-[75%] bg-white border border-gray-100 text-ink text-sm rounded-2xl rounded-bl-sm px-3 py-2 whitespace-pre-wrap break-words">{{ m.content }}</div>
            <span class="text-[10px] text-ink-faint">{{ timeLabel(m.created_at) }}</span>
          </template>
        </div>
      </template>
    </div>

    <!-- 입력 -->
    <div class="p-2.5 border-t border-gray-100 flex-shrink-0 bg-white" :style="isMobile ? 'padding-bottom: calc(10px + env(safe-area-inset-bottom))' : ''">
      <ChatComposer ref="inputEl" v-model="input" :maxlength="500" placeholder="답장 입력..." @send="send" />
    </div>
  </div>
</Teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { useAuthStore } from '../stores/auth'
import AppIcon from './AppIcon.vue'
import UserAvatar from './UserAvatar.vue'
import { useMessageSender } from '../composables/useMessageSender'
import ChatComposer from './ChatComposer.vue'

const auth = useAuthStore()
const router = useRouter()
const show = ref(false)
const partnerId = ref(null)
const partner = ref(null)
const messages = ref([])
const loading = ref(false)
const input = ref('')
const inputEl = ref(null)
const { send: sendOptimistic, retry, discard, merge } = useMessageSender(messages, () => auth.user?.id)
const box = ref(null)
const winW = ref(typeof window !== 'undefined' ? window.innerWidth : 1200)
const isMobile = computed(() => winW.value < 640)
let timer = null

const onResize = () => { winW.value = window.innerWidth }
const toBottom = () => nextTick(() => { if (box.value) box.value.scrollTop = box.value.scrollHeight })

async function load(silent) {
  if (!partnerId.value) return
  if (!silent) loading.value = true
  const startedAt = Date.now()
  const pid = partnerId.value
  try {
    const { data } = await axios.get('/api/messages/thread/' + pid)
    if (pid !== partnerId.value) return
    const prev = messages.value.length
    messages.value = merge(data.data || [], startedAt)
    if (data.partner) partner.value = data.partner
    if (!silent || messages.value.length > prev) toBottom()
  } catch {}
  loading.value = false
}

function open(id) {
  if (!id) return
  if (partnerId.value !== Number(id)) { messages.value = []; partner.value = null; input.value = '' }
  partnerId.value = Number(id)
  show.value = true
  load(false)
}
function close() { show.value = false; partnerId.value = null }
function goInbox() { close(); router.push('/dashboard?tab=messages') }

function send() {
  const content = input.value.trim()
  if (!content || !partnerId.value) return
  // 즉시 말풍선 표시 + 입력창 비우기 (전송은 뒤에서 순서대로, 입력창은 막지 않음)
  input.value = ''
  sendOptimistic(partnerId.value, content)
  toBottom()
  nextTick(() => inputEl.value?.focus?.({ preventScroll: true }))
}

const dayKey = (dt) => { const d = new Date(dt); return d.getFullYear() + '-' + d.getMonth() + '-' + d.getDate() }
const isNewDay = (i) => i === 0 || dayKey(messages.value[i].created_at) !== dayKey(messages.value[i - 1].created_at)
function dayLabel(dt) {
  if (dayKey(dt) === dayKey(new Date())) return '오늘'
  const d = new Date(dt)
  return d.getFullYear() + '년 ' + (d.getMonth() + 1) + '월 ' + d.getDate() + '일 (' + ['일', '월', '화', '수', '목', '금', '토'][d.getDay()] + ')'
}
const timeLabel = (dt) => { const d = new Date(dt); return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0') }

// 열려 있는 동안 10초마다 새 쪽지 확인 (실시간 알림이 안 오는 경우를 위한 보조)
// + 내 개인 채널로 '새 쪽지' 알림이 오면 바로 대화를 새로 불러온다.
let echoUserId = null
const onNewNotification = () => { if (show.value) load(true) }
function unlisten() {
  if (echoUserId && window.Echo) {
    try { window.Echo.private(`user.${echoUserId}`).stopListening('.notification.new', onNewNotification) } catch {}
  }
  echoUserId = null
}
watch(show, (v) => {
  clearInterval(timer)
  unlisten()
  if (v) {
    timer = setInterval(() => { if (document.visibilityState === 'visible') load(true) }, 10000)
    if (window.Echo && auth.user?.id) {
      try {
        echoUserId = auth.user.id
        window.Echo.private(`user.${echoUserId}`).listen('.notification.new', onNewNotification)
      } catch { echoUserId = null }
    }
  }
})

onMounted(() => {
  window.addEventListener('resize', onResize)
  window.openMessageThread = open
})
onUnmounted(() => {
  window.removeEventListener('resize', onResize)
  clearInterval(timer)
  unlisten()
  if (window.openMessageThread === open) delete window.openMessageThread
})
</script>
