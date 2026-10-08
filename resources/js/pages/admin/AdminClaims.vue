<template>
<!-- ───────── 휴대폰 화면 ───────── -->
<div v-if="isMobile" class="alv-m space-y-3 pb-4">
  <p class="text-[13px] text-ink-muted px-0.5">업주의 업소록 소유권 클레임을 승인/거절해요.</p>
  <div class="flex gap-2 overflow-x-auto scrollbar-hide" role="group" aria-label="클레임 상태">
    <button v-for="f in ['pending','approved','rejected','all']" :key="f" @click="filter = f" :aria-pressed="filter === f"
      class="shrink-0 min-h-[44px] px-4 rounded-full border text-[15px]" :class="filter === f ? 'bg-ink text-white border-ink font-bold' : 'bg-white text-ink border-gray-200 font-medium'">
      {{ {all:'전체',pending:'대기',approved:'승인',rejected:'거절'}[f] }} <span class="text-[13px]" :class="filter === f ? 'text-white/80' : 'text-ink-muted'">{{ f === 'all' ? stats.total : stats[f] || 0 }}</span></button>
    <button @click="loadClaims" class="shrink-0 min-h-[44px] px-4 rounded-full border border-gray-200 bg-white text-[15px] font-bold text-blue-600">새로고침</button>
  </div>
  <div v-if="loading" class="text-center py-10 text-ink-muted text-[15px]">불러오는 중...</div>
  <div v-else-if="!filteredClaims.length" class="text-center py-12 text-ink-muted text-[15px]">{{ filter === 'pending' ? '대기 중인 클레임이 없어요 👍' : '해당 상태의 클레임이 없어요' }}</div>
  <div v-else class="space-y-2.5">
    <div v-for="c in filteredClaims" :key="c.id" class="bg-white border border-gray-100 rounded-2xl p-3.5">
      <div class="flex items-center gap-2 flex-wrap">
        <span class="text-[12px] px-2.5 py-1 rounded-full font-bold" :class="c.status==='pending'?'bg-amber-100 text-amber-700':c.status==='approved'?'bg-green-100 text-green-700':'bg-red-100 text-red-700'">{{ {pending:'대기',approved:'승인',rejected:'거절'}[c.status] }}</span>
        <span class="text-[12px] text-ink-faint">{{ formatDate(c.created_at) }} · #{{ c.id }}</span>
      </div>
      <div class="text-[16px] font-bold text-ink mt-1 break-words">🏪 {{ c.business?.name }}</div>
      <div class="text-[13px] text-ink-muted">{{ c.business?.category }} · {{ c.business?.city }}</div>
      <div class="text-[14px] text-ink-light mt-1.5 break-words"><b>{{ c.user?.name }}</b> <span class="text-ink-muted">({{ c.user?.email }})</span></div>
      <a v-if="c.user?.phone" :href="`tel:${c.user.phone}`" class="inline-flex items-center min-h-[40px] text-[14px] font-bold text-blue-600">📞 {{ c.user.phone }}</a>
      <div v-if="c.notes" class="text-[14px] text-ink-light mt-1 bg-gray-50 rounded-xl px-3 py-2 break-words">💬 {{ c.notes }}</div>
      <a v-if="c.document_url" :href="c.document_url" target="_blank" rel="noopener noreferrer" class="mt-2 flex items-center justify-center min-h-[46px] rounded-xl bg-amber-50 text-amber-700 text-[15px] font-bold">📎 증빙서류 보기</a>
      <div class="grid grid-cols-2 gap-2 mt-3" v-if="c.status === 'pending'">
        <button @click="ask('approve', c)" class="min-h-[50px] rounded-xl bg-emerald-500 text-white text-[16px] font-bold">승인</button>
        <button @click="ask('reject', c)" class="min-h-[50px] rounded-xl bg-red-50 text-red-600 text-[16px] font-bold">거절</button>
      </div>
      <button v-else-if="c.status === 'approved'" @click="ask('revoke', c)" class="mt-3 w-full min-h-[48px] rounded-xl bg-gray-100 text-ink text-[15px] font-bold">승인 취소</button>
      <button v-else @click="ask('approve', c)" class="mt-3 w-full min-h-[48px] rounded-xl bg-emerald-500 text-white text-[15px] font-bold">재승인</button>
    </div>
  </div>

  <Teleport to="body">
    <div v-if="sheet" class="alv-m fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="closeSheet">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2 max-h-[90vh] overflow-y-auto" role="dialog" aria-modal="true" :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>
        <div class="text-[17px] font-bold text-ink mb-1">{{ {approve:'클레임을 승인할까요?', reject:'클레임을 거절할까요?', revoke:'승인을 취소할까요?'}[sheet.kind] }}</div>
        <p class="text-[15px] text-ink-light mb-3 break-words">{{ sheet.c.business?.name }} — {{ sheet.c.user?.name }}</p>
        <template v-if="sheet.kind === 'reject'">
          <textarea v-model="reason" rows="3" maxlength="200" placeholder="거절 사유 (업주에게 전달돼요)" aria-label="거절 사유" class="w-full rounded-xl border border-gray-200 px-3 py-3 mb-3"></textarea>
        </template>
        <button @click="doSheet" :disabled="busy || (sheet.kind === 'reject' && !reason.trim())" class="w-full min-h-[52px] rounded-xl text-white text-[16px] font-bold disabled:opacity-40" :class="sheet.kind === 'approve' ? 'bg-emerald-500' : 'bg-red-500'">{{ busy ? '처리 중...' : {approve:'승인하기', reject:'거절하기', revoke:'승인 취소하기'}[sheet.kind] }}</button>
        <button @click="closeSheet" :disabled="busy" class="mt-2 w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">취소</button>
      </div>
    </div>
    <div v-if="toast" class="alv-m fixed left-1/2 -translate-x-1/2 z-[80] max-w-[90vw] px-4 py-3 rounded-xl text-[15px] font-bold text-white shadow-lg" :class="toast.error ? 'bg-red-600' : 'bg-ink'" :style="{ top: 'calc(70px + env(safe-area-inset-top, 0px))' }" role="status">{{ toast.text }}</div>
  </Teleport>
</div>

<!-- ───────── PC 화면 ───────── -->
<div v-else>
  <div class="mb-4">
    <div class="text-xs text-ink-muted">관리자 › 서비스 › 업소 클레임</div>
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mt-1">
      <span class="icon-chip w-9 h-9 bg-pink-50 text-pink-600"><AppIcon name="store" :size="20" /></span>
      업소 클레임 관리
    </h1>
    <p class="text-xs text-ink-muted mt-0.5">업주의 업소록 소유권 클레임을 승인/거절합니다</p>
  </div>

  <!-- 통계 -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <div class="card p-3">
      <div class="text-xs text-ink-muted">전체</div>
      <div class="text-xl font-bold text-ink">{{ stats.total }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">대기</div>
      <div class="text-xl font-bold text-amber-600">{{ stats.pending }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">승인</div>
      <div class="text-xl font-bold text-green-600">{{ stats.approved }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">거절</div>
      <div class="text-xl font-bold text-red-600">{{ stats.rejected }}</div>
    </div>
  </div>

  <!-- 상태 필터 -->
  <div class="card p-3 mb-3 flex flex-wrap gap-2 items-center">
    <span class="text-xs text-ink-muted">상태:</span>
    <button v-for="f in ['all','pending','approved','rejected']" :key="f" @click="filter=f"
      class="text-xs px-3 py-1 rounded-full transition-colors"
      :class="filter===f ? 'bg-amber-400 text-white font-bold shadow-btn' : 'bg-gray-100 text-ink-muted hover:bg-gray-200'">
      {{ {all:'전체',pending:'대기',approved:'승인',rejected:'거절'}[f] }}
      <span class="ml-1 text-[11px]">({{ filter==='all' ? stats.total : stats[f] || 0 }})</span>
    </button>
    <button @click="loadClaims" class="ml-auto btn-secondary !px-3 !py-1 !text-xs"><AppIcon name="refresh" :size="12" /> 새로고침</button>
  </div>

  <!-- 리스트 -->
  <div v-if="loading" class="text-center py-12 text-ink-muted">로딩중...</div>
  <div v-else-if="!filteredClaims.length" class="card py-16 text-center">
    <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="store" :size="28" :stroke-width="1.5" /></div>
    <p class="text-sm text-ink-muted">{{ filter === 'pending' ? '대기 중인 클레임이 없습니다' : '해당 상태의 클레임이 없습니다' }}</p>
  </div>
  <div v-else class="space-y-3">
    <div v-for="c in filteredClaims" :key="c.id" class="card p-4">
      <div class="flex items-start justify-between gap-3">
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 mb-1 flex-wrap">
            <span class="text-xs px-2 py-0.5 rounded-full font-bold"
              :class="c.status==='pending'?'bg-amber-100 text-amber-700':c.status==='approved'?'bg-green-100 text-green-700':'bg-red-100 text-red-700'">
              {{ {pending:'대기',approved:'승인',rejected:'거절'}[c.status] }}
            </span>
            <span class="text-xs text-ink-faint">{{ formatDate(c.created_at) }}</span>
            <span class="text-xs text-ink-faint">#{{ c.id }}</span>
          </div>
          <div class="flex items-center gap-1 text-sm font-bold text-ink"><AppIcon name="store" :size="14" class="text-pink-500" /> {{ c.business?.name }}</div>
          <div class="text-xs text-ink-muted">{{ c.business?.category }} · {{ c.business?.city }}</div>
          <div class="flex items-center gap-1 text-xs text-ink-light mt-1"><AppIcon name="user" :size="12" /> <strong>{{ c.user?.name }}</strong> ({{ c.user?.email }}) {{ c.user?.phone ? '· ' + c.user.phone : '' }}</div>
          <div v-if="c.notes" class="flex items-center gap-1 text-xs text-ink-light mt-1"><AppIcon name="message-circle" :size="12" /> {{ c.notes }}</div>
          <div v-if="c.document_url" class="mt-2">
            <a :href="c.document_url" target="_blank" class="inline-flex items-center gap-1 text-xs text-amber-600 hover:underline"><AppIcon name="paperclip" :size="12" /> 증빙서류 보기</a>
          </div>
        </div>
        <div class="flex gap-2 flex-shrink-0">
          <template v-if="c.status==='pending'">
            <button @click="approve(c)" class="bg-green-500 text-white font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-green-600 transition-colors">승인</button>
            <button @click="reject(c)" class="bg-red-400 text-white font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-red-500 transition-colors">거절</button>
          </template>
          <button v-else-if="c.status==='approved'" @click="revoke(c)" class="bg-gray-400 text-white font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-gray-500 transition-colors">승인취소</button>
          <button v-else-if="c.status==='rejected'" @click="approve(c)" class="bg-green-500 text-white font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-green-600 transition-colors">재승인</button>
        </div>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, inject } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

// 관리자 휴대폰 화면이면 카드 + 아래에서 올라오는 시트로 보여 줌 (AdminLayout 이 알려 줌)
const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)
const toast = ref(null)
let toastTimer = null
function say(text, error = false) { toast.value = { text, error }; clearTimeout(toastTimer); toastTimer = setTimeout(() => { toast.value = null }, 3000) }
const busy = ref(false)
const sheet = ref(null)   // { kind: 'approve' | 'reject' | 'revoke', c }
const reason = ref('')
function ask(kind, c) { reason.value = ''; sheet.value = { kind, c } }
function closeSheet() { if (!busy.value) sheet.value = null }
async function doSheet() {
  if (busy.value || !sheet.value) return
  busy.value = true
  const { kind, c } = sheet.value
  try {
    if (kind === 'approve') { await axios.post(`/api/admin/claims/${c.id}/approve`); c.status = 'approved'; say('승인했어요') }
    else if (kind === 'reject') { await axios.post(`/api/admin/claims/${c.id}/reject`, { notes: reason.value.trim() }); c.status = 'rejected'; say('거절했어요') }
    else { await axios.post(`/api/admin/claims/${c.id}/reject`, { notes: '관리자 승인 취소' }); c.status = 'rejected'; say('승인을 취소했어요') }
    sheet.value = null
  } catch (e) { say(e.response?.data?.message || '처리하지 못했어요', true) }
  finally { busy.value = false }
}
watch(() => isMobile.value && !!sheet.value, locked => { document.body.style.overflow = locked ? 'hidden' : '' })
onBeforeUnmount(() => { document.body.style.overflow = ''; clearTimeout(toastTimer) })

const claims = ref([])
const loading = ref(true)
const filter = ref('pending')

const stats = computed(() => ({
  total: claims.value.length,
  pending: claims.value.filter(c => c.status === 'pending').length,
  approved: claims.value.filter(c => c.status === 'approved').length,
  rejected: claims.value.filter(c => c.status === 'rejected').length,
}))

const filteredClaims = computed(() =>
  filter.value === 'all' ? claims.value : claims.value.filter(c => c.status === filter.value)
)

function formatDate(dt) { return dt ? new Date(dt).toLocaleDateString('ko-KR') : '' }

async function loadClaims() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/admin/claims')
    claims.value = data.data?.data || data.data || []
  } catch {}
  loading.value = false
}

async function approve(c) {
  if (!confirm(`${c.business?.name} 클레임을 승인하시겠습니까?`)) return
  try { await axios.post(`/api/admin/claims/${c.id}/approve`); c.status = 'approved' }
  catch (e) { alert(e.response?.data?.message || '실패') }
}

async function revoke(c) {
  if (!confirm(`${c.business?.name} 승인을 취소하시겠습니까?`)) return
  try { await axios.post(`/api/admin/claims/${c.id}/reject`, { notes: '관리자 승인 취소' }); c.status = 'rejected' }
  catch (e) { alert(e.response?.data?.message || '실패') }
}

async function reject(c) {
  const reason = prompt('거절 사유를 입력하세요:')
  if (reason === null) return
  try { await axios.post(`/api/admin/claims/${c.id}/reject`, { notes: reason }); c.status = 'rejected' }
  catch (e) { alert(e.response?.data?.message || '실패') }
}

onMounted(loadClaims)
</script>

<style>
/* 휴대폰 관리자: 입력창 글자가 16px 보다 작으면 iOS 가 화면을 확대해 버림 */
.alv-m input, .alv-m textarea, .alv-m select { font-size: 16px; }
</style>
