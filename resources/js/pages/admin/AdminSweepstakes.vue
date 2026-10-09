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
          <button v-if="item.status !== 'winner_selected'" @click="openDesign(item)" class="min-h-[48px] rounded-xl bg-amber-50 text-amber-700 text-[15px] font-bold">추첨 화면 꾸미기</button>
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
          <button v-if="item.status !== 'winner_selected'" @click="openDesign(item)" class="btn-secondary !px-3 !py-1.5 text-xs">추첨 화면 꾸미기</button>
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

<!-- ───────── 추첨 화면 꾸미기 (PC·휴대폰 공용) ───────── -->
<Teleport to="body">
  <div v-if="design" class="alv-m fixed inset-0 z-[75] flex items-center justify-center sm:p-4" @click.self="closeDesign">
    <div class="absolute inset-0 bg-black/45" @click="closeDesign"></div>
    <div class="relative bg-white w-full h-full sm:h-auto sm:max-h-[92vh] sm:max-w-5xl sm:rounded-2xl overflow-y-auto p-4 sm:p-6" role="dialog" aria-modal="true">
      <div class="flex items-start justify-between gap-3 mb-4">
        <div class="min-w-0">
          <h2 class="font-bold text-lg text-ink">추첨 화면 꾸미기</h2>
          <p class="text-xs text-ink-muted truncate">{{ design.item.title }} · 🎁 {{ design.item.prize_name }}</p>
        </div>
        <button type="button" @click="closeDesign" class="text-ink-muted text-2xl leading-none px-2" aria-label="닫기">×</button>
      </div>

      <!-- 추첨 게임 선택 -->
      <div class="text-xs font-bold text-ink-muted mb-2">추첨 화면(게임)</div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-5">
        <label v-for="d in DRAW_STYLES" :key="d.value" class="flex items-start gap-3 p-3 rounded-xl border-2 cursor-pointer"
          :class="design.draw_style === d.value ? 'border-amber-500 bg-amber-50' : 'border-gray-100 bg-white'">
          <input type="radio" name="draw_style" :value="d.value" v-model="design.draw_style" class="mt-1 accent-amber-500" />
          <span>
            <span class="block text-sm font-bold text-ink">{{ d.emoji }} {{ d.label }}</span>
            <span class="block text-xs text-ink-muted mt-0.5">{{ d.desc }}</span>
          </span>
        </label>
      </div>

      <div v-if="design.draw_style === 'lottery3d'" class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <SweepstakesThemeEditor v-model="design.theme" />
        <div>
          <div class="lg:sticky lg:top-0">
            <div class="text-xs font-bold text-ink-muted mb-2">미리보기 (응모권 40장 중 17번 당첨 예시)</div>
            <div class="rounded-xl overflow-hidden border border-gray-100 bg-gray-50">
              <LotteryStage3D :key="previewKey" ref="stageRef" :theme="design.theme" :prize-name="design.item.prize_name"
                :ticket-count="40" :winning-ticket="17" winner-name="미리보기" :autoplay="false" :compact="true" />
            </div>
            <div class="flex gap-2 mt-2">
              <button type="button" @click="stageRef?.play?.()" class="btn-primary !px-4 !py-2 text-sm">추첨 연출 재생</button>
              <button type="button" @click="stageRef?.reset?.()" class="btn-secondary !px-4 !py-2 text-sm">처음으로</button>
            </div>
            <p class="text-[11px] text-ink-faint mt-2">미리보기는 연출만 보여 줘요. 실제 당첨자는 서버가 정하고, 이 화면은 그 결과를 다시 보여 줄 뿐이에요.</p>
          </div>
        </div>
      </div>
      <p v-else class="text-sm text-ink-muted bg-gray-50 rounded-xl p-3">2D 룰렛 휠은 기본 화면 그대로 사용돼요. 색·배경·로고 꾸미기는 3D 추첨기에서만 가능해요.</p>

      <p v-if="design.error" class="text-sm text-red-500 mt-3">{{ design.error }}</p>
      <div class="flex justify-end gap-2 mt-5">
        <button type="button" @click="closeDesign" class="btn-secondary !px-5 !py-2.5 text-sm">취소</button>
        <button type="button" @click="saveDesign" :disabled="design.saving" class="btn-primary !px-5 !py-2.5 text-sm disabled:opacity-50">{{ design.saving ? '저장 중...' : '저장' }}</button>
      </div>
    </div>
  </div>
</Teleport>
<Teleport to="body">
  <div v-if="toast && !isMobile" class="fixed left-1/2 -translate-x-1/2 top-6 z-[80] px-4 py-3 rounded-xl text-sm font-bold text-white shadow-lg" :class="toast.error ? 'bg-red-600' : 'bg-ink'" role="status">{{ toast.text }}</div>
</Teleport>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, inject, defineAsyncComponent } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
import { useAuthStore } from '../../stores/auth'
import SweepstakesThemeEditor from '../../components/admin/SweepstakesThemeEditor.vue'
// 3D 추첨기는 무거워서 꾸미기 창을 열 때만 불러옴
const LotteryStage3D = defineAsyncComponent(() => import('../../components/LotteryStage3D.vue'))

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

// ───── 추첨 화면(게임) 선택 + 테마 꾸미기 ─────
// 새 게임을 추가하려면 이 배열에 한 줄만 추가하면 됩니다 (value 는 백엔드 draw_style 값과 동일해야 함).
const DRAW_STYLES = [
  { value: 'wheel', emoji: '🎡', label: '2D 룰렛 휠', desc: '기본 룰렛이 돌아가며 당첨자를 보여 줘요.' },
  { value: 'lottery3d', emoji: '🎱', label: '3D 추첨기(공 뽑기)', desc: '3D 추첨기에서 공이 섞이다 당첨 번호가 나와요. 색·배경·로고를 꾸밀 수 있어요.' },
]
const design = ref(null)   // { item, draw_style, theme, saving, error }
const stageRef = ref(null)
const previewKey = ref(0)
function openDesign(item) {
  const theme = item.theme && typeof item.theme === 'object' && !Array.isArray(item.theme) ? { ...item.theme } : {}
  design.value = { item, draw_style: item.draw_style || 'wheel', theme, saving: false, error: '' }
}
function closeDesign() { if (!design.value?.saving) design.value = null }
async function saveDesign() {
  const d = design.value
  if (!d || d.saving) return
  d.saving = true; d.error = ''
  try {
    await axios.put(`/api/admin/sweepstakes/${d.item.id}`, { draw_style: d.draw_style, theme: d.theme })
    design.value = null
    say('추첨 화면을 저장했어요')
    await load()
  } catch (e) {
    d.error = e.response?.data?.message || '저장에 실패했어요'
    d.saving = false
  }
}
watch(() => !!design.value, locked => { document.body.style.overflow = locked ? 'hidden' : '' })

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
