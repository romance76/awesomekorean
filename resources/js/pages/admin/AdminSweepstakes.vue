<template>
<!-- ───────── 휴대폰 화면 ───────── -->
<div v-if="isMobile" class="alv-m space-y-3 pb-4">
  <p class="text-[13px] text-ink-muted leading-relaxed px-0.5">경품 추첨 이벤트는 "이벤트" 등록 화면에서 "경품 추첨(Sweepstakes) 이벤트로 등록"을 체크해 만들어요. 당첨자는 서버에서 안전한 난수로 <b>1회만</b> 뽑고, 결과는 되돌릴 수 없어요.</p>
  <div v-if="!isSuperAdmin" class="bg-white border border-gray-100 rounded-2xl py-12 text-center text-ink-muted text-[15px] px-4">경품 추첨 관리는 사이트 최고관리자만 접근할 수 있어요</div>
  <template v-else>
    <RouterLink to="/events/create" class="flex items-center justify-center min-h-[52px] rounded-2xl bg-amber-500 text-white text-[16px] font-bold">+ 이벤트로 새 경품 추첨 등록</RouterLink>
    <SweepstakesBoard :items="items" :loading="loading" @participants="mParticipants" @design="openDesign" @winner="askWinner" @delete="askDelete" @reload="load" />
  </template>

  <Teleport to="body">
    <div v-if="mSheet" class="alv-m fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="closeSheet">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2 max-h-[90vh] overflow-y-auto" role="dialog" aria-modal="true" :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>
        <div v-if="mSheet.mode === 'participants'">
          <div class="text-[17px] font-bold text-ink mb-2 break-words">참가 현황 — {{ mSheet.item.title }}</div>
          <div v-if="winnerList.length" class="mb-3 bg-amber-50 border border-amber-100 rounded-xl p-3">
            <div class="text-[13px] font-bold text-amber-800 mb-1">당첨자 {{ winnerList.length }}명</div>
            <ol class="space-y-1"><li v-for="w in winnerList" :key="w.rank" class="text-[15px] flex items-center gap-2"><b class="w-9 text-amber-700">{{ w.rank }}등</b><span class="font-semibold text-ink truncate">{{ w.name }}</span></li></ol>
          </div>
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

  <SweepstakesBoard :items="items" :loading="loading" @participants="viewParticipants" @design="openDesign" @winner="confirmSelectWinner" @delete="remove" @reload="load" />

  <!-- 참가현황 모달 -->
  <div v-if="showParticipants" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="showParticipants=false">
    <div class="absolute inset-0 bg-black/40"></div>
    <div class="relative bg-white rounded-2xl p-6 w-full max-w-lg max-h-[80vh] overflow-y-auto">
      <div class="flex items-start justify-between gap-3 mb-4">
        <h2 class="font-bold text-lg min-w-0 break-words">참가 현황 — {{ activeItem?.title }}</h2>
        <button @click="showParticipants=false" aria-label="닫기" title="닫기" class="w-8 h-8 -mt-1 -mr-2 rounded-full flex items-center justify-center text-ink-muted hover:bg-gray-100 hover:text-ink flex-shrink-0 transition-colors"><AppIcon name="x" :size="18" /></button>
      </div>
      <div v-if="winnerList.length" class="mb-4 bg-amber-50 border border-amber-100 rounded-xl p-3">
        <div class="text-xs font-bold text-amber-800 mb-1.5">당첨자 {{ winnerList.length }}명</div>
        <ol class="space-y-1"><li v-for="w in winnerList" :key="w.rank" class="text-sm flex items-center gap-2"><b class="w-8 text-amber-700">{{ w.rank }}등</b><span class="font-semibold text-ink">{{ w.name }}</span><span v-if="w.prize" class="text-ink-faint text-xs">· {{ w.prize }}</span></li></ol>
      </div>
      <div class="divide-y divide-gray-50">
        <div v-for="p in participants" :key="p.id" class="py-2.5 flex items-center justify-between text-sm">
          <span>{{ p.user?.nickname || p.user?.name || ('User #' + p.user_id) }}</span>
          <span class="font-bold text-amber-600">{{ p.entries_count }} Entry</span>
        </div>
        <div v-if="!participants.length" class="py-8 text-center text-ink-muted text-sm">참가자가 없습니다</div>
      </div>
      <button @click="showParticipants=false" class="mt-4 w-full py-2.5 rounded-xl bg-gray-100 text-ink text-sm font-bold hover:bg-gray-200 transition-colors">닫기</button>
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
      <div v-if="DRAW_STYLES.length > 1" class="text-xs font-bold text-ink-muted mb-2">추첨 화면(게임)</div>
      <div v-if="DRAW_STYLES.length > 1" class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-5">
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
import SweepstakesBoard from '../../components/admin/SweepstakesBoard.vue'
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
// 응모 기간 중이면 서버가 early_draw 로 한 번 막는다 — 확인 후 early=true 로 다시 보냄
async function postSelectWinner(id) {
  try {
    return await axios.post(`/api/admin/sweepstakes/${id}/select-winner`)
  } catch (e) {
    if (e.response?.status === 409 && e.response?.data?.code === 'early_draw') {
      if (!confirm(e.response.data.message + '\n\n그래도 지금 추첨할까요?')) { const c = new Error('cancelled'); c.cancelled = true; throw c }
      return await axios.post(`/api/admin/sweepstakes/${id}/select-winner`, { early: true })
    }
    throw e
  }
}
async function doWinner() {
  if (busy.value) return
  busy.value = true
  const item = mSheet.value.item
  try {
    const { data } = await postSelectWinner(item.id)
    mSheet.value = null
    say(winnerSummary(data.data).replace(/\n/g, ' · '))
    await load()
  } catch (e) { if (!e.cancelled) say(e.response?.data?.message || '당첨자 선정에 실패했어요', true) }
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
  { value: 'lottery3d', emoji: '🎱', label: '3D 추첨기(공 뽑기)', desc: '3D 추첨기에서 공이 섞이다 당첨 번호가 나와요. 색·배경·로고를 꾸밀 수 있어요.' },
]
const design = ref(null)   // { item, draw_style, theme, saving, error }
const stageRef = ref(null)
const previewKey = ref(0)
function openDesign(item) {
  const theme = item.theme && typeof item.theme === 'object' && !Array.isArray(item.theme) ? { ...item.theme } : {}
  design.value = { item, draw_style: 'lottery3d', theme, saving: false, error: '' }
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
const winnerList = ref([])
const activeItem = ref(null)

// ───── 다중 당첨자 ─────
function winnerCountOf(item) { return Math.max(1, Number(item?.winner_count) || 1) }
// 서버가 내려주는 winners 가 없어도(구버전 응답) 깨지지 않게 이름/등수를 정리
function normWinners(list) {
  if (!Array.isArray(list)) return []
  return list.map((w, i) => ({
    rank: Number(w.rank) || i + 1,
    prize: w.prize_name || '',
    name: w.display_name || w.nickname || w.user?.nickname || w.user?.name || w.name || ('User #' + (w.user_id ?? '?')),
  })).sort((a, b) => a.rank - b.rank)
}
function itemWinners(item) { return normWinners(item?.winners) }
function winnerSummary(d) {
  const list = normWinners(d?.winners)
  if (list.length > 1) return '당첨자\n' + list.map(w => w.rank + '등: ' + w.name).join('\n')
  const one = list[0]?.name || d?.winner?.nickname || d?.winner?.name || ('#' + d?.winner_user_id)
  return '당첨자: ' + one
}

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
  winnerList.value = itemWinners(item)
  try {
    const { data } = await axios.get(`/api/admin/sweepstakes/${item.id}/participants`)
    participants.value = data.data?.data || data.data?.participants || (Array.isArray(data.data) ? data.data : [])
    const w = data.winners || data.data?.winners || data.meta?.winners
    if (Array.isArray(w) && w.length) winnerList.value = normWinners(w)
  } catch {
    participants.value = []
  }
}

async function confirmSelectWinner(item) {
  if (!confirm(`"${item.title}" 당첨자를 지금 선정하시겠습니까?\n\n이 작업은 서버에서 1회만 실행되며 절대 되돌릴 수 없습니다.`)) return
  try {
    const { data } = await postSelectWinner(item.id)
    alert(winnerSummary(data.data))
    await load()
  } catch (e) {
    if (!e.cancelled) alert(e.response?.data?.message || '당첨자 선정 실패')
  }
}

onMounted(() => { if (isSuperAdmin.value) load(); else loading.value = false })
</script>
<style>
/* 휴대폰 관리자: 입력창 글자가 16px 보다 작으면 iOS 가 화면을 확대해 버림 */
.alv-m input, .alv-m textarea, .alv-m select { font-size: 16px; }
</style>
