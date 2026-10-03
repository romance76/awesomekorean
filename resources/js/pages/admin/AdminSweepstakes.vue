<template>
<div>
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-2">
    <span class="icon-chip w-9 h-9 bg-violet-50 text-violet-600"><AppIcon name="gift" :size="20" /></span>
    Sweepstakes 관리
  </h1>
  <p class="text-sm text-ink-muted mb-6">경품 추첨 이벤트를 생성/관리합니다. 당첨자 선정은 서버에서 암호학적으로 안전한 난수로 1회만 수행되며, 결과는 되돌릴 수 없습니다.</p>

  <button @click="openCreate" class="btn-primary !px-5 !py-2.5 mb-5">+ 새 Sweepstakes</button>

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
          <button @click="openEdit(item)" :disabled="item.status==='winner_selected'" class="btn-secondary !px-3 !py-1.5 text-xs disabled:opacity-40">수정</button>
          <button
            v-if="item.status !== 'winner_selected'"
            @click="confirmSelectWinner(item)"
            class="btn-primary !px-3 !py-1.5 text-xs !bg-violet-600 hover:!bg-violet-700"
          >당첨자 선정</button>
          <button @click="remove(item)" :disabled="item.total_entries > 0" class="text-xs text-red-500 disabled:opacity-30 disabled:cursor-not-allowed">삭제</button>
        </div>
      </div>
    </div>
    <div v-if="!items.length" class="card py-16 text-center text-ink-muted text-sm">등록된 Sweepstakes가 없습니다</div>
  </div>

  <!-- 생성/수정 모달 -->
  <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="showForm=false">
    <div class="absolute inset-0 bg-black/40"></div>
    <div class="relative bg-white rounded-2xl p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto">
      <h2 class="font-bold text-lg mb-4">{{ editId ? 'Sweepstakes 수정' : '새 Sweepstakes' }}</h2>
      <div class="space-y-3">
        <div>
          <label class="text-xs text-ink-muted block mb-1">제목</label>
          <input v-model="form.title" class="input-soft w-full !py-2" />
        </div>
        <div>
          <label class="text-xs text-ink-muted block mb-1">설명</label>
          <textarea v-model="form.description" rows="3" class="input-soft w-full !py-2"></textarea>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-xs text-ink-muted block mb-1">경품명</label>
            <input v-model="form.prize_name" class="input-soft w-full !py-2" />
          </div>
          <div>
            <label class="text-xs text-ink-muted block mb-1">경품 가치($)</label>
            <input v-model="form.prize_value" type="number" step="0.01" class="input-soft w-full !py-2" />
          </div>
        </div>
        <div>
          <label class="text-xs text-ink-muted block mb-1">경품 이미지 URL</label>
          <input v-model="form.prize_image" class="input-soft w-full !py-2" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-xs text-ink-muted block mb-1">시작일시</label>
            <input v-model="form.start_at" type="datetime-local" class="input-soft w-full !py-2" />
          </div>
          <div>
            <label class="text-xs text-ink-muted block mb-1">종료일시</label>
            <input v-model="form.end_at" type="datetime-local" class="input-soft w-full !py-2" />
          </div>
        </div>
        <div>
          <label class="text-xs text-ink-muted block mb-1">상태</label>
          <select v-model="form.status" class="input-soft w-full !py-2">
            <option value="draft">준비중 (draft)</option>
            <option value="active">진행중 (active)</option>
            <option value="ended">마감 (ended)</option>
            <option value="cancelled">취소 (cancelled)</option>
          </select>
        </div>
        <div class="border-t border-gray-100 pt-3 mt-1">
          <div class="text-xs font-bold text-ink-muted mb-2">법률 검토용 확장 필드 (운영 전 별도 법률 검토 필요)</div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs text-ink-muted block mb-1">최소 연령</label>
              <input v-model="form.minimum_age" type="number" class="input-soft w-full !py-2" />
            </div>
            <div>
              <label class="text-xs text-ink-muted block mb-1">참가 가능 지역(State, 쉼표구분, 비우면 전체)</label>
              <input v-model="regionsInput" placeholder="예: GA,FL,NC" class="input-soft w-full !py-2" />
            </div>
          </div>
          <div class="mt-2">
            <label class="text-xs text-ink-muted block mb-1">공식 규정 URL</label>
            <input v-model="form.official_rules_url" class="input-soft w-full !py-2" />
          </div>
          <div class="mt-2">
            <label class="text-xs text-ink-muted block mb-1">무구매 조건 안내문</label>
            <textarea v-model="form.no_purchase_required_text" rows="2" class="input-soft w-full !py-2"></textarea>
          </div>
        </div>
      </div>
      <div class="flex items-center gap-3 mt-5">
        <button @click="submitForm" :disabled="saving" class="btn-primary !px-5 !py-2.5">{{ saving ? '저장중...' : '저장' }}</button>
        <button @click="showForm=false" class="btn-secondary !px-5 !py-2.5">취소</button>
      </div>
    </div>
  </div>

  <!-- 참가현황 모달 -->
  <div v-if="showParticipants" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="showParticipants=false">
    <div class="absolute inset-0 bg-black/40"></div>
    <div class="relative bg-white rounded-2xl p-6 w-full max-w-lg max-h-[80vh] overflow-y-auto">
      <h2 class="font-bold text-lg mb-4">참가 현황 — {{ activeItem?.title }}</h2>
      <div class="divide-y divide-gray-50">
        <div v-for="p in participants" :key="p.id" class="py-2.5 flex items-center justify-between text-sm">
          <span>{{ p.user?.nickname || p.user?.name || ('User #' + p.user_id) }}</span>
          <span class="font-bold text-violet-600">{{ p.entries_count }} Entry</span>
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
const saving = ref(false)

const showForm = ref(false)
const editId = ref(null)
const form = ref(blankForm())
const regionsInput = ref('')

const showParticipants = ref(false)
const participants = ref([])
const activeItem = ref(null)

function blankForm() {
  return {
    title: '', description: '', prize_name: '', prize_value: '', prize_image: '',
    start_at: '', end_at: '', status: 'draft',
    minimum_age: 18, official_rules_url: '', no_purchase_required_text: '',
  }
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

function openCreate() {
  editId.value = null
  form.value = blankForm()
  regionsInput.value = ''
  showForm.value = true
}

function openEdit(item) {
  editId.value = item.id
  form.value = {
    title: item.title, description: item.description, prize_name: item.prize_name,
    prize_value: item.prize_value, prize_image: item.prize_image,
    start_at: toLocalInput(item.start_at), end_at: toLocalInput(item.end_at),
    status: item.status, minimum_age: item.minimum_age,
    official_rules_url: item.official_rules_url, no_purchase_required_text: item.no_purchase_required_text,
  }
  regionsInput.value = (item.eligible_regions || []).join(',')
  showForm.value = true
}

function toLocalInput(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  const pad = n => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

async function submitForm() {
  saving.value = true
  try {
    const payload = {
      ...form.value,
      eligible_regions: regionsInput.value ? regionsInput.value.split(',').map(s => s.trim().toUpperCase()).filter(Boolean) : null,
    }
    if (editId.value) {
      await axios.put(`/api/admin/sweepstakes/${editId.value}`, payload)
    } else {
      await axios.post('/api/admin/sweepstakes', payload)
    }
    showForm.value = false
    await load()
  } catch (e) {
    alert(e.response?.data?.message || '저장 실패')
  }
  saving.value = false
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
