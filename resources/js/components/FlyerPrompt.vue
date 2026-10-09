<template>
  <!-- NEW 전단 광고 바로가기: 하루 한 번, 출석체크 버튼과 동시에 뜨지 않게 잠깐 떠 있다가 사라진다.
       지금 방송 중인 전단(내 지역 → 없으면 전국)이 있을 때만 나온다. -->
  <Transition name="flp">
    <div v-if="show && ad" class="fixed right-3 bottom-24 md:bottom-6 z-40 flex items-center gap-1 rounded-full bg-white shadow-lg border border-amber-200 pl-1.5 pr-1 py-1 max-w-[calc(100vw-1.5rem)]">
      <button @click="open" class="flex items-center gap-2 min-w-0 rounded-full pr-2">
        <img :src="ad.image_url" :alt="ad.title" class="w-9 h-9 rounded-full object-cover bg-gray-100 flex-shrink-0" />
        <span class="min-w-0 text-left">
          <span class="block text-[10px] font-black text-amber-500 leading-tight">📢 지금 NEW 광고</span>
          <span class="block text-[13px] font-bold text-ink truncate max-w-[200px] leading-tight">{{ ad.title }}</span>
        </span>
      </button>
      <button @click="close" class="w-7 h-7 grid place-items-center text-ink-faint hover:text-ink rounded-full flex-shrink-0" title="닫기">
        <AppIcon name="x" :size="14" />
      </button>
    </div>
  </Transition>
</template>
<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from 'axios'
import { useAuthStore } from '../stores/auth'
import { checkinVisible } from '../utils/floatPrompts'
import { US_STATES } from '../utils/flyer'
import AppIcon from './AppIcon.vue'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const show = ref(false)
const ad = ref(null)
let timer = null
let hideTimer = null
let done = false   // 이 화면 세션에서 이미 한 번 처리함

const START_DELAY = 6000      // 페이지가 열리고 나서 기다리는 시간
const AFTER_CHECKIN = 4000    // 출석체크 버튼이 사라진 뒤 기다리는 시간
const VISIBLE_MS = 15000      // 자동으로 사라지기까지

function todayStr() {
  const d = new Date()
  return `${d.getFullYear()}-${d.getMonth() + 1}-${d.getDate()}`
}
const seenKey = () => `ak_flyer_prompt_${auth.user?.id || 'guest'}`
function seenToday() {
  try { return localStorage.getItem(seenKey()) === todayStr() } catch { return false }
}
function markSeen() {
  try { localStorage.setItem(seenKey(), todayStr()) } catch {}
}

function schedule(delay) {
  clearTimeout(timer)
  timer = setTimeout(tryShow, delay)
}

async function tryShow() {
  if (done || seenToday()) return
  // NEW 게시판/광고 상세를 보고 있거나 숨김 탭이면 지금은 건너뛰고 나중에 다시 시도
  if (route.path.startsWith('/new') || document.hidden) return schedule(30000)
  // 출석체크 버튼이 떠 있으면 그게 사라질 때까지 기다린다 (동시에 뜨지 않음)
  if (checkinVisible.value) return
  const myState = (auth.user?.state || '').toUpperCase()
  const hasState = US_STATES.some(s => s.code === myState)
  try {
    const { data } = await axios.get('/api/flyers', { params: { scope: hasState ? 'state' : 'national', state: hasState ? myState : undefined } })
    const f = data?.data?.featured
    if (!f?.image_url) return   // 지금 방송 중인 광고 없음 → 오늘 몫은 그대로 두고 다음 기회에
    if (checkinVisible.value) return
    ad.value = f
    show.value = true
    done = true
    markSeen()
    hideTimer = setTimeout(() => { show.value = false }, VISIBLE_MS)
  } catch {}
}

function open() {
  const id = ad.value?.id
  show.value = false
  if (id) router.push(`/new/${id}`)
}
function close() { show.value = false }

// 출석체크 버튼이 사라지면(출석/닫기) 잠시 뒤 광고 안내를 시도
watch(checkinVisible, v => { if (!v && !done) schedule(AFTER_CHECKIN) })
// 로그인 상태가 바뀌면 사용자별로 다시 판단
watch(() => auth.user?.id, () => { done = false; show.value = false; schedule(START_DELAY) })

onMounted(() => schedule(START_DELAY))
onBeforeUnmount(() => { clearTimeout(timer); clearTimeout(hideTimer) })
</script>
<style scoped>
.flp-enter-active, .flp-leave-active { transition: opacity .3s, transform .3s; }
.flp-enter-from, .flp-leave-to { opacity: 0; transform: translateY(8px); }
</style>
