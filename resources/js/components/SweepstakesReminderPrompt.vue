<template>
  <!-- 추첨 시작 5분 전 알림: 출석체크 안내처럼 화면 하단에 떠 있다가 닫으면 사라짐 -->
  <Transition name="srp">
    <div v-if="item" class="fixed left-3 z-40 max-w-[calc(100vw-1.5rem)] rounded-2xl bg-white shadow-lg border border-amber-200 px-3.5 py-2.5"
      :class="checkinVisible ? 'bottom-40 md:bottom-6' : 'bottom-24 md:bottom-6'">
      <div class="flex items-start gap-2.5">
        <div class="text-lg leading-none mt-0.5">⏰</div>
        <div class="min-w-0">
          <div class="text-[13px] font-bold text-ink">곧 추첨! <span class="text-amber-600">{{ item.prize_name }}</span></div>
          <div class="text-xs text-ink-muted mt-0.5">추첨이 <b class="text-amber-600 tabular-nums">{{ countdown }}</b> 뒤 시작돼요</div>
          <div class="flex gap-1.5 mt-2">
            <button @click="goEvent" class="rounded-full bg-gradient-to-r from-amber-400 to-amber-500 text-white text-xs font-bold px-3.5 py-1.5">이벤트 보러가기</button>
            <button @click="dismiss" class="rounded-full border border-line text-ink-muted text-xs font-bold px-3 py-1.5 hover:bg-gray-50">닫기</button>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>
<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { checkinVisible } from '../utils/floatPrompts'

const auth = useAuthStore()
const router = useRouter()
const item = ref(null)
const left = ref(0)
let endAtMs = 0
let pollTimer = null
let tickTimer = null

const countdown = computed(() => {
  const s = Math.max(0, left.value)
  const m = Math.floor(s / 60)
  const sec = String(s % 60).padStart(2, '0')
  return `${m}분 ${sec}초`
})

function tick() {
  if (!item.value) return
  left.value = Math.max(0, Math.floor((endAtMs - Date.now()) / 1000))
  if (left.value <= 0) item.value = null
}

async function refresh() {
  if (!auth.isLoggedIn || !auth.user?.id || document.hidden) return
  try {
    const { data } = await axios.get('/api/me/sweepstakes-reminders/due')
    const list = data?.data || []
    if (!list.length) { item.value = null; return }
    const first = list[0]
    endAtMs = first.end_at ? new Date(first.end_at).getTime() : Date.now() + (first.seconds_left || 0) * 1000
    item.value = first
    tick()
  } catch { /* 조용히 무시 */ }
}

function goEvent() {
  if (!item.value) return
  const id = item.value.event_id
  dismiss()
  if (id) router.push(`/events/${id}`)
}

async function dismiss() {
  const sid = item.value?.sweepstakes_id
  item.value = null
  if (!sid) return
  try { await axios.post(`/api/me/sweepstakes-reminders/${sid}/dismiss`) } catch {}
}

function onVisible() { if (!document.hidden) refresh() }

onMounted(() => {
  document.addEventListener('visibilitychange', onVisible)
  pollTimer = setInterval(refresh, 30000)
  tickTimer = setInterval(tick, 1000)
})
onBeforeUnmount(() => {
  document.removeEventListener('visibilitychange', onVisible)
  if (pollTimer) clearInterval(pollTimer)
  if (tickTimer) clearInterval(tickTimer)
})
watch(() => auth.user?.id, (id) => { if (id) refresh(); else item.value = null }, { immediate: true })
</script>
<style scoped>
.srp-enter-active, .srp-leave-active { transition: opacity .25s, transform .25s; }
.srp-enter-from, .srp-leave-to { opacity: 0; transform: translateY(8px); }
</style>
