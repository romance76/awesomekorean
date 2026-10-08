<template>
<!-- ───────── 휴대폰 화면 ───────── -->
<div v-if="isMobile" class="alv-m space-y-3 pb-4">
  <p class="text-[13px] text-ink-muted leading-relaxed px-0.5">자동으로 지급되지 않는 보상을 확인한 뒤 직접 지급해요.</p>
  <div class="grid grid-cols-2 gap-1 bg-gray-200/70 rounded-2xl p-1" role="tablist" aria-label="보상 종류">
    <button @click="tab = 'events'" role="tab" :aria-selected="tab === 'events'" class="min-h-[46px] rounded-xl text-[14px] font-bold" :class="tab === 'events' ? 'bg-white text-ink shadow-sm' : 'text-ink-muted'">이벤트 완료인증 {{ eventProofs.length }}</button>
    <button @click="tab = 'recipes'" role="tab" :aria-selected="tab === 'recipes'" class="min-h-[46px] rounded-xl text-[14px] font-bold" :class="tab === 'recipes' ? 'bg-white text-ink shadow-sm' : 'text-ink-muted'">레시피 인기보상 {{ recipeCandidates.length }}</button>
  </div>
  <div v-if="loading" class="text-center py-10 text-ink-muted text-[15px]">불러오는 중...</div>

  <template v-else-if="tab === 'events'">
    <div v-if="!eventProofs.length" class="text-center py-12 text-ink-muted text-[15px]">대기 중인 제출이 없어요 👍</div>
    <div v-for="a in eventProofs" :key="a.id" class="bg-white border border-gray-100 rounded-2xl p-3.5">
      <div class="text-[16px] font-bold text-ink break-words">{{ a.user?.nickname || a.user?.name }}</div>
      <div class="text-[14px] text-ink-light break-words">{{ a.event?.title }}</div>
      <div class="text-[14px] mt-1">보상 <b class="text-amber-600 tabular-nums">{{ a.event?.reward_points }}P</b></div>
      <a :href="'/storage/' + a.proof_file" target="_blank" rel="noopener noreferrer" class="mt-2 flex items-center justify-center min-h-[46px] rounded-xl bg-blue-50 text-blue-700 text-[15px] font-bold">제출 파일 보기</a>
      <div class="grid grid-cols-2 gap-2 mt-3">
        <button @click="ask('approve', a)" :disabled="busy" class="min-h-[50px] rounded-xl bg-emerald-500 text-white text-[16px] font-bold disabled:opacity-50">승인 + 지급</button>
        <button @click="ask('reject', a)" :disabled="busy" class="min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold disabled:opacity-50">반려</button>
      </div>
    </div>
  </template>

  <template v-else>
    <div v-if="!recipeCandidates.length" class="text-center py-12 text-ink-muted text-[15px]">대상 레시피가 없어요</div>
    <div v-for="r in recipeCandidates" :key="r.id" class="bg-white border border-gray-100 rounded-2xl p-3.5">
      <div class="text-[16px] font-bold text-ink break-words">{{ r.title }}</div>
      <div class="text-[14px] text-ink-light">{{ r.user?.nickname || r.user?.name }} · 찜 {{ r.favorite_count }}개 · 댓글 {{ r.comments_count }}개</div>
      <div class="flex gap-2 mt-3">
        <input v-model.number="amounts[r.id]" type="number" inputmode="numeric" min="1" placeholder="지급할 포인트" :aria-label="r.title + ' 지급 포인트'" class="flex-1 min-w-0 min-h-[48px] rounded-xl border border-gray-200 px-3 text-right tabular-nums" />
        <button @click="ask('pay', r)" :disabled="busy || !amounts[r.id]" class="shrink-0 min-h-[48px] px-5 rounded-xl bg-amber-500 text-white text-[16px] font-bold disabled:opacity-40">지급</button>
      </div>
    </div>
  </template>

  <Teleport to="body">
    <div v-if="sheet" class="alv-m fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="closeSheet">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2 max-h-[90vh] overflow-y-auto" role="dialog" aria-modal="true" :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>
        <template v-if="sheet.kind === 'approve'">
          <div class="text-[17px] font-bold text-ink mb-1">포인트를 지급할까요?</div>
          <p class="text-[15px] text-ink-light mb-3 break-words"><b>{{ sheet.item.user?.nickname || sheet.item.user?.name }}</b>님에게 <b class="text-amber-600">{{ sheet.item.event?.reward_points }}P</b>를 지급해요.</p>
        </template>
        <template v-else-if="sheet.kind === 'pay'">
          <div class="text-[17px] font-bold text-ink mb-1">포인트를 지급할까요?</div>
          <p class="text-[15px] text-ink-light mb-3 break-words">“{{ sheet.item.title }}” 작성자에게 <b class="text-amber-600">{{ (amounts[sheet.item.id] || 0).toLocaleString() }}P</b>를 지급해요.</p>
        </template>
        <template v-else>
          <div class="text-[17px] font-bold text-ink mb-1">제출을 반려할까요?</div>
          <p class="text-[14px] text-ink-muted mb-2 break-words">{{ sheet.item.user?.nickname || sheet.item.user?.name }} — {{ sheet.item.event?.title }}</p>
          <textarea v-model="reason" rows="3" maxlength="200" placeholder="반려 사유 (선택)" aria-label="반려 사유" class="w-full rounded-xl border border-gray-200 px-3 py-3 mb-3"></textarea>
        </template>
        <button @click="doSheet" :disabled="busy" class="w-full min-h-[52px] rounded-xl text-white text-[16px] font-bold disabled:opacity-50" :class="sheet.kind === 'reject' ? 'bg-red-500' : 'bg-emerald-500'">{{ busy ? '처리 중...' : (sheet.kind === 'reject' ? '반려하기' : '지급하기') }}</button>
        <button @click="closeSheet" :disabled="busy" class="mt-2 w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">취소</button>
      </div>
    </div>
    <div v-if="toast" class="alv-m fixed left-1/2 -translate-x-1/2 z-[80] max-w-[90vw] px-4 py-3 rounded-xl text-[15px] font-bold text-white shadow-lg" :class="toast.error ? 'bg-red-600' : 'bg-ink'" :style="{ top: 'calc(70px + env(safe-area-inset-top, 0px))' }" role="status">{{ toast.text }}</div>
  </Teleport>
</div>

<!-- ───────── PC 화면 ───────── -->
<div v-else>
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-2">
    <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="gift" :size="20" /></span>
    보상 승인
    <NewFeatureBadge desc="이벤트 완료인증 · 레시피 인기보상은 자동 지급이 아니라 여기서 관리자가 직접 확인 후 지급합니다." />
  </h1>
  <p class="text-sm text-ink-muted mb-6">자동 지급되지 않는 보상들을 여기서 확인 후 직접 지급합니다.</p>

  <div class="flex gap-2 mb-4">
    <button @click="tab='events'" class="text-sm font-bold px-4 py-2 rounded-lg transition-colors" :class="tab==='events'?'bg-amber-400 text-white':'bg-gray-100 text-ink-light'">이벤트 완료인증 ({{ eventProofs.length }})</button>
    <button @click="tab='recipes'" class="text-sm font-bold px-4 py-2 rounded-lg transition-colors" :class="tab==='recipes'?'bg-amber-400 text-white':'bg-gray-100 text-ink-light'">레시피 인기보상 ({{ recipeCandidates.length }})</button>
  </div>

  <div v-if="loading" class="text-center py-12 text-ink-muted">로딩중...</div>

  <!-- 이벤트 완료인증 -->
  <div v-else-if="tab==='events'" class="card overflow-hidden divide-y divide-gray-50">
    <div v-if="!eventProofs.length" class="px-5 py-8 text-center text-sm text-ink-faint">대기중인 제출이 없습니다</div>
    <div v-for="a in eventProofs" :key="a.id" class="px-5 py-4 flex items-center gap-4">
      <div class="flex-1 min-w-0">
        <div class="text-sm font-bold text-ink">{{ a.user?.nickname || a.user?.name }} <span class="text-ink-faint font-normal">— {{ a.event?.title }}</span></div>
        <div class="text-xs text-ink-muted mt-0.5">보상 {{ a.event?.reward_points }}P · <a :href="'/storage/'+a.proof_file" target="_blank" class="text-blue-600 hover:underline">제출 파일 보기</a></div>
      </div>
      <button @click="approveEvent(a)" :disabled="busyId===a.id" class="text-xs font-bold bg-emerald-500 text-white px-3 py-1.5 rounded-lg hover:bg-emerald-600 disabled:opacity-50">승인+지급</button>
      <button @click="rejectEvent(a)" :disabled="busyId===a.id" class="text-xs font-bold bg-gray-100 text-ink-light px-3 py-1.5 rounded-lg hover:bg-gray-200 disabled:opacity-50">반려</button>
    </div>
  </div>

  <!-- 레시피 인기보상 -->
  <div v-else class="card overflow-hidden divide-y divide-gray-50">
    <div v-if="!recipeCandidates.length" class="px-5 py-8 text-center text-sm text-ink-faint">대상 레시피가 없습니다</div>
    <div v-for="r in recipeCandidates" :key="r.id" class="px-5 py-4 flex items-center gap-4">
      <div class="flex-1 min-w-0">
        <div class="text-sm font-bold text-ink">{{ r.title }} <span class="text-ink-faint font-normal">— {{ r.user?.nickname || r.user?.name }}</span></div>
        <div class="text-xs text-ink-muted mt-0.5">찜 {{ r.favorite_count }}개 · 댓글 {{ r.comments_count }}개</div>
      </div>
      <input v-model.number="amounts[r.id]" type="number" min="1" placeholder="지급P" class="input-soft !w-24 !px-2 !py-1.5 text-sm text-right" />
      <button @click="payRecipe(r)" :disabled="busyId===r.id || !amounts[r.id]" class="text-xs font-bold bg-amber-400 text-white px-3 py-1.5 rounded-lg hover:bg-amber-500 disabled:opacity-50">지급</button>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount, watch, inject } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
import NewFeatureBadge from '../../components/NewFeatureBadge.vue'

// 관리자 휴대폰 화면이면 카드 + 아래에서 올라오는 시트로 보여 줌 (AdminLayout 이 알려 줌)
const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)
const toast = ref(null)
let toastTimer = null
function say(text, error = false) { toast.value = { text, error }; clearTimeout(toastTimer); toastTimer = setTimeout(() => { toast.value = null }, 3000) }
const busy = ref(false)
const sheet = ref(null)   // { kind: 'approve' | 'reject' | 'pay', item }
const reason = ref('')
function ask(kind, item) { reason.value = ''; sheet.value = { kind, item } }
function closeSheet() { if (!busy.value) sheet.value = null }
async function doSheet() {
  if (busy.value || !sheet.value) return
  busy.value = true
  const { kind, item } = sheet.value
  try {
    if (kind === 'approve') { await axios.post(`/api/admin/rewards/event-proofs/${item.id}/approve`); eventProofs.value = eventProofs.value.filter(x => x.id !== item.id); say('지급했어요') }
    else if (kind === 'reject') { await axios.post(`/api/admin/rewards/event-proofs/${item.id}/reject`, { reason: reason.value.trim() }); eventProofs.value = eventProofs.value.filter(x => x.id !== item.id); say('반려했어요') }
    else { await axios.post(`/api/admin/rewards/recipes/${item.id}/pay`, { amount: amounts[item.id] }); recipeCandidates.value = recipeCandidates.value.filter(x => x.id !== item.id); say('지급했어요') }
    sheet.value = null
  } catch (e) { say(e.response?.data?.message || '처리하지 못했어요', true) }
  finally { busy.value = false }
}
watch(() => isMobile.value && !!sheet.value, locked => { document.body.style.overflow = locked ? 'hidden' : '' })
onBeforeUnmount(() => { document.body.style.overflow = ''; clearTimeout(toastTimer) })

const tab = ref('events')
const loading = ref(true)
const eventProofs = ref([])
const recipeCandidates = ref([])
const amounts = reactive({})
const busyId = ref(null)

async function load() {
  loading.value = true
  try {
    const [ev, rc] = await Promise.all([
      axios.get('/api/admin/rewards/event-proofs'),
      axios.get('/api/admin/rewards/recipe-candidates'),
    ])
    eventProofs.value = ev.data.data || []
    recipeCandidates.value = rc.data.data || []
  } catch {}
  loading.value = false
}

async function approveEvent(a) {
  if (!confirm(`${a.user?.nickname || a.user?.name}님에게 ${a.event?.reward_points}P를 지급할까요?`)) return
  busyId.value = a.id
  try {
    await axios.post(`/api/admin/rewards/event-proofs/${a.id}/approve`)
    eventProofs.value = eventProofs.value.filter(x => x.id !== a.id)
  } catch (e) { alert(e.response?.data?.message || '처리 실패') }
  busyId.value = null
}

async function rejectEvent(a) {
  const reason = prompt('반려 사유(선택):') || ''
  busyId.value = a.id
  try {
    await axios.post(`/api/admin/rewards/event-proofs/${a.id}/reject`, { reason })
    eventProofs.value = eventProofs.value.filter(x => x.id !== a.id)
  } catch (e) { alert(e.response?.data?.message || '처리 실패') }
  busyId.value = null
}

async function payRecipe(r) {
  const amount = amounts[r.id]
  if (!amount) return
  if (!confirm(`"${r.title}"에 ${amount}P를 지급할까요?`)) return
  busyId.value = r.id
  try {
    await axios.post(`/api/admin/rewards/recipes/${r.id}/pay`, { amount })
    recipeCandidates.value = recipeCandidates.value.filter(x => x.id !== r.id)
  } catch (e) { alert(e.response?.data?.message || '처리 실패') }
  busyId.value = null
}

onMounted(load)
</script>

<style>
/* 휴대폰 관리자: 입력창 글자가 16px 보다 작으면 iOS 가 화면을 확대해 버림 */
.alv-m input, .alv-m textarea, .alv-m select { font-size: 16px; }
</style>
