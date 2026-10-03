<template>
<div>
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-2">
    <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="gift" :size="20" /></span>
    경품 추첨(Sweepstakes) 관리
  </h1>
  <p class="text-sm text-ink-muted mb-6">경품 추첨 이벤트는 "이벤트" 페이지의 등록 화면에서 "경품 추첨(Sweepstakes) 이벤트로 등록"을 체크해 생성합니다. 생성된 이벤트는 사이트의 "이벤트" 목록에 노출되며, 당첨자 선정은 서버에서 암호학적으로 안전한 난수로 1회만 수행되고 결과는 되돌릴 수 없습니다.</p>

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
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

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

onMounted(load)
</script>
