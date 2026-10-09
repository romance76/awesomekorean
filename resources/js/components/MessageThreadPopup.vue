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
            <span class="text-[10px] text-ink-faint">{{ timeLabel(m.created_at) }}</span>
            <div class="max-w-[75%] bg-amber-400 text-white text-sm rounded-2xl rounded-br-sm px-3 py-2 whitespace-pre-wrap break-words">{{ m.content }}</div>
          </template>
          <template v-else>
            <div class="max-w-[75%] bg-white border border-gray-100 text-ink text-sm rounded-2xl rounded-bl-sm px-3 py-2 whitespace-pre-wrap break-words">{{ m.content }}</div>
            <span class="text-[10px] text-ink-faint">{{ timeLabel(m.created_at) }}</span>
          </template>
        </div>
      </template>
    </div>

    <!-- 입력 -->
    <div class="p-2.5 border-t border-gray-100 flex items-end gap-2 flex-shrink-0 bg-white" :style="isMobile ? 'padding-bottom: calc(10px + env(safe-area-inset-bottom))' : ''">
      <textarea v-model="input" rows="2" maxlength="500" placeholder="답장 입력 (Enter 전송, Shift+Enter 줄바꿈)" class="input-soft flex-1 !text-sm" @keydown.enter="onEnter"></textarea>
      <button @click="send" :disabled="sending || !input.trim()" class="btn-primary !px-3"><AppIcon name="send" :size="14" /></button>
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

const auth = useAuthStore()
const router = useRouter()
const show = ref(false)
const partnerId = ref(null)
const partner = ref(null)
const messages = ref([])
const loading = ref(false)
const input = ref('')
const sending = ref(false)
const box = ref(null)
const winW = ref(typeof window !== 'undefined' ? window.innerWidth : 1200)
const isMobile = computed(() => winW.value < 640)
let timer = null

const onResize = () => { winW.value = window.innerWidth }
const toBottom = () => nextTick(() => { if (box.value) box.value.scrollTop = box.value.scrollHeight })

async function load(silent) {
  if (!partnerId.value) return
  if (!silent) loading.value = true
  try {
    const { data } = await axios.get('/api/messages/thread/' + partnerId.value)
    const prev = messages.value.length
    messages.value = data.data || []
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

function onEnter(e) { if (e.shiftKey || e.isComposing || e.keyCode === 229) return; e.preventDefault(); send() }
async function send() {
  const content = input.value.trim()
  if (!content || sending.value || !partnerId.value) return
  sending.value = true
  try {
    const { data } = await axios.post('/api/messages', { receiver_id: partnerId.value, content })
    messages.value.push(data.data || { id: Date.now(), sender_id: auth.user?.id, receiver_id: partnerId.value, content, created_at: new Date().toISOString() })
    input.value = ''; toBottom()
  } catch (e) { alert(e.response?.data?.message || '전송 실패') }
  sending.value = false
}

const dayKey = (dt) => { const d = new Date(dt); return d.getFullYear() + '-' + d.getMonth() + '-' + d.getDate() }
const isNewDay = (i) => i === 0 || dayKey(messages.value[i].created_at) !== dayKey(messages.value[i - 1].created_at)
function dayLabel(dt) {
  if (dayKey(dt) === dayKey(new Date())) return '오늘'
  const d = new Date(dt)
  return d.getFullYear() + '년 ' + (d.getMonth() + 1) + '월 ' + d.getDate() + '일 (' + ['일', '월', '화', '수', '목', '금', '토'][d.getDay()] + ')'
}
const timeLabel = (dt) => { const d = new Date(dt); return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0') }

// 열려 있는 동안 10초마다 새 쪽지 확인
watch(show, (v) => {
  clearInterval(timer)
  if (v) timer = setInterval(() => { if (document.visibilityState === 'visible') load(true) }, 10000)
})

onMounted(() => {
  window.addEventListener('resize', onResize)
  window.openMessageThread = open
})
onUnmounted(() => {
  window.removeEventListener('resize', onResize)
  clearInterval(timer)
  if (window.openMessageThread === open) delete window.openMessageThread
})
</script>
