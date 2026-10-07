<template>
  <!-- 출석체크 바로가기: 오늘 아직 출석 안 했을 때만 화면 한쪽에 떠 있고, 한 번 눌러야 출석된다(자동 출석 아님).
       ✕ 로 닫으면 오늘은 다시 안 뜸. -->
  <Transition name="ckp">
    <div v-if="show" class="fixed right-3 bottom-24 md:bottom-6 z-40 flex items-center gap-1 rounded-full bg-white shadow-lg border border-amber-200 pl-1.5 pr-1 py-1">
      <button @click="doCheckin" :disabled="busy"
        class="inline-flex items-center gap-1.5 rounded-full bg-gradient-to-r from-amber-400 to-orange-500 text-white text-[13px] font-bold px-3.5 py-2 disabled:opacity-60">
        <AppIcon name="calendar" :size="15" />{{ busy ? '처리 중...' : `출석체크${required ? ` (${progress}/${required})` : ''}` }}
      </button>
      <button @click="dismiss" class="w-7 h-7 grid place-items-center text-ink-faint hover:text-ink rounded-full" title="오늘은 닫기">
        <AppIcon name="x" :size="14" />
      </button>
    </div>
  </Transition>
</template>
<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import { useAuthStore } from '../stores/auth'
import { useSiteStore } from '../stores/site'
import AppIcon from './AppIcon.vue'

const auth = useAuthStore()
const site = useSiteStore()
const show = ref(false)
const busy = ref(false)
const progress = ref(0)
const required = ref(0)

function todayStr() {
  const d = new Date()
  return `${d.getFullYear()}-${d.getMonth() + 1}-${d.getDate()}`
}
const dismissKey = () => `ak_checkin_dismiss_${auth.user?.id}`
function dismissedToday() {
  try { return localStorage.getItem(dismissKey()) === todayStr() } catch { return false }
}

async function refresh() {
  if (!auth.isLoggedIn || !auth.user?.id) { show.value = false; return }
  if (dismissedToday()) { show.value = false; return }
  try {
    const { data } = await axios.get('/api/entries/balance')
    const d = data?.data
    progress.value = d?.checkin_progress ?? 0
    required.value = d?.checkin_required ?? 0
    show.value = !d?.checked_in_today
  } catch { show.value = false }
}

async function doCheckin() {
  busy.value = true
  try {
    const { data } = await axios.post('/api/entries/checkin')
    const r = data?.data
    if (r?.entry_awarded) site.toast('🎉 출석 완료! Entry 1개를 받았어요', 'success', 4000)
    else site.toast(`✅ 오늘 출석 완료 (${r?.progress ?? progress.value + 1}/${r?.required ?? required.value})`, 'success')
    show.value = false
  } catch (e) {
    // 이미 오늘 출석한 경우(400)도 버튼은 치운다
    if (e.response?.status === 400) show.value = false
    else site.toast(e.response?.data?.message || '출석체크 실패', 'error')
  }
  busy.value = false
}

function dismiss() {
  try { localStorage.setItem(dismissKey(), todayStr()) } catch {}
  show.value = false
}

// 날짜가 바뀐 뒤 탭으로 돌아왔을 때도 다시 확인 (24시간 켜 둔 화면 대응)
function onVisible() { if (!document.hidden) refresh() }
onMounted(() => document.addEventListener('visibilitychange', onVisible))
onBeforeUnmount(() => document.removeEventListener('visibilitychange', onVisible))
watch(() => auth.user?.id, refresh, { immediate: true })
</script>
<style scoped>
.ckp-enter-active, .ckp-leave-active { transition: opacity .25s, transform .25s; }
.ckp-enter-from, .ckp-leave-to { opacity: 0; transform: translateY(8px); }
</style>
