<template>
<!-- ───────── 휴대폰 화면 ───────── -->
<div v-if="isMobile" class="alv-m space-y-3 pb-4">
  <p class="text-[13px] text-ink-muted leading-relaxed px-0.5">경품 추첨 이벤트는 "이벤트" 등록 화면에서 "경품 추첨(Sweepstakes) 이벤트로 등록"을 체크해 만들어요. 당첨자는 서버에서 안전한 난수로 <b>1회만</b> 뽑고, 결과는 되돌릴 수 없어요.</p>
  <div v-if="!isSuperAdmin" class="bg-white border border-gray-100 rounded-2xl py-12 text-center text-ink-muted text-[15px] px-4">경품 추첨 관리는 사이트 최고관리자만 접근할 수 있어요</div>
  <template v-else>
    <RouterLink to="/events/create" class="flex items-center justify-center min-h-[52px] rounded-2xl bg-amber-500 text-white text-[16px] font-bold">+ 이벤트로 새 경품 추첨 등록</RouterLink>
    <div v-if="loading" class="text-center py-10 text-ink-muted text-[15px]">불러오는 중...</div>
    <div v-else class="space-y-2.5">
      <div v-for="item in items" :key="item.id" class="bg-white border border-gray-100 rounded-2xl p-3.5">
        <div class="flex items-center gap-2 flex-wrap"><span class="text-[12px] font-bold px-2.5 py-1 rounded-full" :class="statusBadge(item.status)">{{ statusLabel(item.status) }}</span></div>
        <div class="text-[16px] font-bold text-ink mt-1 break-words">{{ item.title }}</div>
        <div class="text-[14px] text-ink-muted">🎁 {{ item.prize_name }} <span v-if="item.prize_value">(${{ item.prize_value }})</span></div>
        <div class="text-[13px] text-ink-faint mt-1 leading-relaxed">{{ formatDate(item.start_at) }} ~ {{ formatDate(item.end_at) }}<br />전체 Entry {{ item.total_entries }} · 참가자 {{ item.unique_participants ?? '-' }}<span v-if="item.winner_user_id"> · 당첨자 ID {{ item.winner_user_id }}</span></div>
        <div class="grid grid-cols-2 gap-2 mt-3">
          <button @click="mParticipants(item)" class="min-h-[48px] rounded-xl bg-gray-100 text-ink text-[15px] font-bold">참가 현황</button>
          <RouterLink v-if="item.event_id" :to="`/events/${item.event_id}`" class="flex items-center justify-center min-h-[48px] rounded-xl bg-gray-100 text-ink text-[15px] font-bold">이벤트 보기</RouterLink>
          <RouterLink v-if="item.event_id && item.status !== 'winner_selected'" :to="`/events/${item.event_id}/edit`" class="flex items-center justify-center min-h-[48px] rounded-xl bg-gray-100 text-ink text-[15px] font-bold">수정</RouterLink>
          <button v-if="item.status !== 'winner_selected'" @click="askWinner(item)" class="min-h-[48px] rounded-xl bg-violet-600 text-white text-[15px] font-bold">당첨자 선정</button>
          <button @click="askDelete(item)" :disabled="item.total_entries > 0" class="min-h-[48px] rounded-xl bg-red-50 text-red-600 text-[15px] font-bold disabled:opacity-30">삭제</button>
        </div>
      </div>
      <div v-if="!items.length" class="bg-white border border-gray-100 rounded-2xl py-12 text-center text-ink-muted text-[15px]">등록된 경품 추첨 이벤트가 없어요</div>
    </div>
  </template>

  <Teleport to="body">
    <div v-if="mSheet" class="alv-m fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="closeSheet">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2 max-h-[90vh] overflow-y-auto" role="dialog" aria-modal="true" :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>
        <div v-if="mSheet.mode === 'participants'">
          <div class="text-[17px] font-bold text-ink mb-2 break-words">참가 현황 — {{ mSheet.item.title }}</div>
          <div class="divide-y divide-gray-50">
            <div v-for="p in participants" :key="p.id" class="py-3 flex items-center justify-between text-[15px]"><span class="min-w-0 truncate">{{ p.user?.nickname || p.user?.name || ('User #' + p.user_id) }}</span><b class="shrink-0 text-amber-600 tabular-nums">{{ p.entries_count }} Entry</b></div>
            <div v-if="!participants.length" class="py-8 text-center text-ink-muted text-[15px]">참가자가 없어요</div>
          </div>
          <button @click="closeSheet" class="mt-3 w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">닫기</button>
        </div>
        <div v-else-if="mSheet.mode === 'winner'" class="space-y-3">
          <div class="text-[17px] font-bold text-ink">당첨자를 지금 선정할까요?</div>
          <p class="text-[15px] text-ink-light leading-relaxed break-words">‘{{ mSheet.item.title }}’<br /><b class="text-red-600">서버에서 1회만 실행되고 절대 되돌릴 수 없어요.</b></p>
          <button @click="doWinner" :disabled="busy" class="w-full min-h-[52px] rounded-xl bg-violet-600 text-white text-[16px] font-bold disabled:opacity-50">{{ busy ? '추첨 중...' : '당첨자 선정하기' }}</button>
          <button @click="closeSheet" :disabled="busy" class="w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">취소</button>
        </div>
        <div v-else-if="mSheet.mode === 'delete'" class="space-y-3">
          <div class="text-[17px] font-bold text-ink">삭제할까요?</div>
          <p class="text-[15px] text-ink-light break-words">‘{{ mSheet.item.title }}’</p>
          <button @click="doDelete" :disabled="busy" class="w-full min-h-[52px] rounded-xl bg-red-500 text-white text-[16px] font-bold disabled:opacity-50">{{ busy ? '처리 중...' : '삭제하기' }}</button>
          <button @click="closeSheet" :disabled="busy" class="w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">취소</button>
        </div>
      </div>
    </div>
    <div v-if="toast" class="alv-m fixed left-1/2 -translate-x-1/2 z-[80] max-w-[90vw] px-4 py-3 rounded-xl text-[15px] font-bold text-white shadow-lg" :class="toast.error ? 'bg-red-600' : 'bg-ink'" :style="{ top: 'calc(70px + env(safe-area-inset-top, 0px))' }" role="status">{{ toast.text }}</div>
  </Teleport>
</div>

<!-- ───────── PC 화면 ───────── -->
<div v-else>
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-2">
    <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="gift" :size="20" /></span>
    경품 추첨(Sweepstakes) 관리
  </h1>
  <p class="text-sm text-ink-muted mb-6">경품 추첨 이벤트는 "이벤트" 페이지의 등록 화면에서 "경품 추첨(Sweepstakes) 이벤트로 등록"을 체크해 생성합니다. 생성된 이벤트는 사이트의 "이벤트" 목록에 노출되며, 당첨자 선정은 서버에서 암호학적으로 안전한 난수로 1회만 수행되고 결과는 되돌릴 수 없습니다.</p>

  <div v-if="!isSuperAdmin" class="card py-16 text-center text-ink-muted text-sm">경품 추첨 관리는 사이트 최고관리자만 접근할 수 있습니다</div>
  <template v-else>
  <RouterLink to="/events/create" class="btn-primary !px-5 !py-2.5 mb-5 inline-flex items-center gap-1.5"><AppIcon name="plus" :size="14" />이벤트로 새 경품 추첨 등록</RouterLink>

  <div v-if="loading" class="text-center py-12 text-ink-muted">로딩중...</div>
  <div v-else class="space-y-3">
    <div v-for="item in items" :key="item.id" class="card p-4">
      <div class="flex items-start justify-between gap-3 flex-wrap">
        <div>
          <div class="flex items-center gap-2">
            <span class="text-xs font-bold px-2 py-0.5 rounded-full" :class="statusBadge(item.status)">{{ statusLabel(item.status) }}</span>
            <span class="font-bold text-ink">{{ item.title }}</span>
          </div>
          <div class="text-sm text-ink-muted mt-1">🎁 {{ item.prize_name }} <span v-if="item.prize_value">(${{ item.prize_value }})</span></div>
          <div class="text-xs text-ink-faint mt-1">
            {{ formatDate(item.start_at) }} ~ {{ formatDate(item.end_at) }} ·
            Total Entries: {{ item.total_entries }} · 참가자: {{ item.unique_participants ?? '-' }}
            <span v-if="item.winner_user_id"> · 당첨자 ID: {{ item.winner_user_id }}</span>
          </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
          <button @click="viewParticipants(item)" class="btn-secondary !px-3 !py-1.5 text-xs">참가현황</button>
          <RouterLink v-if="item.event_id" :to="`/events/${item.event_id}`" class="btn-secondary !px-3 !py-1.5 text-xs">이벤트 보기</RouterLink>
          <RouterLink v-if="item.event_id && item.status !== 'winner_selected'" :to="`/events/${item.event_id}/edit`" class="btn-secondary !px-3 !py-1.5 text-xs">수정</RouterLink>
          <button
            v-if="item.status !== 'winner_selected'"
            @click="confirmSelectWinner(item)"
            class="btn-primary !px-3 !py-1.5 text-xs !bg-violet-600 hover:!bg-violet-700"
          >당첨자 선정</button>
          <button @click="remove(item)" :disabled="item.total_entries > 0" class="text-xs text-red-500 disabled:opacity-30 disabled:cursor-not-allowed">삭제</button>
        </div>
      </div>
    </div>
    <div v-if="!items.length" class="card py-16 text-center text-ink-muted text-sm">등록된 경품 추첨 이벤트가 없습니다</div>
  </div>

  <!-- 참가현황 모달 -->
  <div v-if="showParticipants" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="showParticipants=false">
    <div class="absolute inset-0 bg-black/40"></div>
    <div class="relative bg-white rounded-2xl p-6 w-full max-w-lg max-h-[80vh] overflow-y-auto">
      <h2 class="font-bold text-lg mb-4">참가 현황 — {{ activeItem?.title }}</h2>
      <div class="divide-y divide-gray-50">
        <div v-for="p in participants" :key="p.id" class="py-2.5 flex items-center justify-between text-sm">
          <span>{{ p.user?.nickname || p.user?.name || ('User #' + p.user_id) }}</span>
          <span class="font-bold text-amber-600">{{ p.entries_count }} Entry</span>
        </div>
        <div v-if="!participants.length" class="py-8 text-center text-ink-muted text-sm">참가자가 없습니다</div>
      </div>
    </div>
  </div>
  </template>
</div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, inject } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
import { useAuthStore } from '../../stores/auth'

const auth = useAuthStore()
const isSuperAdmin = computed(() => auth.user?.role === 'super_admin')

// 관리자 휴대폰 화면이면 카드 + 아래에서 올라오는 시트로 보여 줌 (AdminLayout 이 알려 줌)
const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)

// 휴대폰: 당첨자 선정·삭제는 브라우저 확인창 대신 아래에서 올라오는 시트로 확인
const mSheet = ref(null)   // { mode: 'participants' | 'winner' | 'delete', item }
const busy = ref(false)
const toast = ref(null)
let toastTimer = null
function say(text, error = false) { toast.value = { text, error }; clearTimeout(toastTimer); toastTimer = setTimeout(() => { toast.value = null }, 3500) }
function closeSheet() { if (!busy.value) mSheet.value = null }
async function mParticipants(item) { await viewParticipants(item); showParticipants.value = false; mSheet.value = { mode: 'participants', item } }
function askWinner(item) { mSheet.value = { mode: 'winner', item } }
function askDelete(item) { mSheet.value = { mode: 'delete', item } }
async function doWinner() {
  if (busy.value) return
  busy.value = true
  const item = mSheet.value.item
  try {
    const { data } = await axios.post(`/api/admin/sweepstakes/${item.id}/select-winner`)
    mSheet.value = null
    say(`당첨자: ${data.data?.winner?.nickname || data.data?.winner?.name || '#' + data.data?.winner_user_id}`)
    await load()
  } catch (e) { say(e.response?.data?.message || '당첨자 선정에 실패했어요', true) }
  finally { busy.value = false }
}
async function doDelete() {
  if (busy.value) return
  busy.value = true
  const item = mSheet.value.item
  try { await axios.delete(`/api/admin/sweepstakes/${item.id}`); mSheet.value = null; say('삭제했어요'); await load() }
  catch (e) { say(e.response?.data?.message || '삭제에 실패했어요', true) }
  finally { busy.value = false }
}
watch(() => isMobile.value && !!mSheet.value, locked => { document.body.style.overflow = locked ? 'hidden' : '' })
onBeforeUnmount(() => { document.body.style.overflow = ''; clearTimeout(toastTimer) })

const items = ref([])
const loading = ref(true)

const showParticipants = ref(false)
const participants = ref([])
const activeItem = ref(null)

function statusLabel(status) {
  return { draft: '준비중', active: '진행중', ended: '마감', winner_selected: '당첨자 발표', cancelled: '취소됨' }[status] || status
}
function statusBadge(status) {
  return {
    draft: 'bg-gray-100 text-gray-600',
    active: 'bg-green-100 text-green-700',
    ended: 'bg-amber-100 text-amber-700',
    winner_selected: 'bg-violet-100 text-violet-700',
    cancelled: 'bg-red-100 text-red-700',
  }[status] || 'bg-gray-100 text-gray-600'
}
function formatDate(dt) {
  if (!dt) return ''
  return new Date(dt).toLocaleString('ko-KR', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/admin/sweepstakes')
    items.value = data.data?.data || data.data || []
  } catch {}
  loading.value = false
}

async function remove(item) {
  if (!confirm(`"${item.title}"을(를) 삭제하시겠습니까?`)) return
  try {
    await axios.delete(`/api/admin/sweepstakes/${item.id}`)
    await load()
  } catch (e) {
    alert(e.response?.data?.message || '삭제 실패')
  }
}

async function viewParticipants(item) {
  activeItem.value = item
  showParticipants.value = true
  try {
    const { data } = await axios.get(`/api/admin/sweepstakes/${item.id}/participants`)
    participants.value = data.data?.data || data.data || []
  } catch {
    participants.value = []
  }
}

async function confirmSelectWinner(item) {
  if (!confirm(`"${item.title}" 당첨자를 지금 선정하시겠습니까?\n\n이 작업은 서버에서 1회만 실행되며 절대 되돌릴 수 없습니다.`)) return
  try {
    const { data } = await axios.post(`/api/admin/sweepstakes/${item.id}/select-winner`)
    alert(`당첨자: ${data.data?.winner?.nickname || data.data?.winner?.name || '#' + data.data?.winner_user_id}`)
    await load()
  } catch (e) {
    alert(e.response?.data?.message || '당첨자 선정 실패')
  }
}

onMounted(() => { if (isSuperAdmin.value) load(); else loading.value = false })
</script>
<style>
/* 휴대폰 관리자: 입력창 글자가 16px 보다 작으면 iOS 가 화면을 확대해 버림 */
.alv-m input, .alv-m textarea, .alv-m select { font-size: 16px; }
</style>
