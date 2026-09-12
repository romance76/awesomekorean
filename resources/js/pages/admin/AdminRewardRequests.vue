<template>
<div>
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
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
import NewFeatureBadge from '../../components/NewFeatureBadge.vue'

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
