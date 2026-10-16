<template>
<div class="min-h-screen">
  <div class="page-main px-4 py-5">
    <PageHeader title="내 공동구매 관리" icon="heart-handshake" chip="bg-lime-50 text-lime-600" fallback="/groupbuy" />

    <div v-if="loading" class="text-center text-sm text-ink-muted py-10">불러오는 중...</div>

    <div v-else-if="!items.length" class="card p-8 text-center space-y-3">
      <div class="text-sm text-ink-muted">아직 연 공동구매가 없어요.</div>
      <RouterLink to="/groupbuy/create" class="btn-primary px-5 py-2 text-sm inline-flex"><AppIcon name="edit" :size="14" />공동구매 열기</RouterLink>
    </div>

    <div v-else class="space-y-4">
      <div v-for="g in items" :key="g.id" class="card p-4 space-y-3">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <RouterLink :to="`/groupbuy/${g.id}`" class="font-bold text-ink hover:underline break-words">{{ g.title }}</RouterLink>
            <div class="text-xs text-ink-muted mt-0.5">
              {{ g.tier === 'small' ? '소액 공동구매' : '사업자 공동구매' }}
              <template v-if="g.deadline"> · 마감 {{ fmtDate(g.deadline) }}</template>
            </div>
          </div>
          <span class="text-xs px-2 py-0.5 rounded-full font-bold flex-shrink-0" :class="stageInfo(g.stage).cls">{{ stageInfo(g.stage).label }}</span>
        </div>

        <div v-if="g.stage === 'rejected' && g.rejection_reason" class="bg-red-50 text-red-700 rounded-xl p-3 text-xs">반려 사유: {{ g.rejection_reason }}</div>
        <div v-else-if="g.stage === 'pending_approval'" class="bg-amber-50 text-amber-800 rounded-xl p-3 text-xs">관리자가 사업자 등록증을 확인하는 중이에요. 승인되면 목록에 공개되고 알림이 와요.</div>

        <div class="grid grid-cols-3 gap-2 text-center">
          <div class="bg-[#F4F6F8] rounded-xl py-2">
            <div class="text-[11px] text-ink-muted">참가자</div>
            <div class="font-bold text-ink">{{ g.current_participants }}<span class="text-xs text-ink-muted font-normal"> / {{ g.max_participants || '∞' }}명</span></div>
          </div>
          <div class="bg-[#F4F6F8] rounded-xl py-2">
            <div class="text-[11px] text-ink-muted">신청 수량</div>
            <div class="font-bold text-ink">{{ g.sold_quantity }}<span class="text-xs text-ink-muted font-normal"> / {{ g.total_quantity || '∞' }}개</span></div>
          </div>
          <div class="bg-[#F4F6F8] rounded-xl py-2">
            <div class="text-[11px] text-ink-muted">현재 가격</div>
            <div class="font-bold text-ink">${{ Number(g.price || 0).toLocaleString() }}</div>
          </div>
        </div>

        <div>
          <button @click="toggle(g.id)" class="text-xs font-semibold text-ink-light flex items-center gap-1">
            참가자 {{ g.participants.length }}명 {{ openId === g.id ? '접기' : '보기' }}
          </button>
          <div v-if="openId === g.id" class="mt-2 space-y-1">
            <div v-if="!g.participants.length" class="text-xs text-ink-muted">아직 참가자가 없어요.</div>
            <div v-for="p in g.participants" :key="p.id" class="flex items-center justify-between text-sm bg-[#F4F6F8] rounded-lg px-3 py-1.5">
              <span class="text-ink">{{ p.name }}</span>
              <span class="text-xs text-ink-muted">{{ p.quantity }}개 · {{ fmtDate(p.joined_at) }}</span>
            </div>
            <div class="text-xs text-ink-muted bg-amber-50 rounded-lg p-2 mt-2">
              🔒 참가자 이름·전화번호·이메일은 공동구매가 완료되거나 수락된 뒤, 수수료를 결제하면 열람할 수 있어요. (결제 기능 준비 중)
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="error" class="text-red-500 text-sm mt-3">{{ error }}</div>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
import PageHeader from '../../components/PageHeader.vue'

const items = ref([])
const loading = ref(true)
const error = ref('')
const openId = ref(null)

const STAGES = {
  pending_approval: { label: '승인 대기', cls: 'bg-amber-100 text-amber-700' },
  rejected: { label: '반려됨', cls: 'bg-red-100 text-red-600' },
  recruiting: { label: '모집 중', cls: 'bg-emerald-100 text-emerald-700' },
  confirmed: { label: '확정', cls: 'bg-sky-100 text-sky-700' },
  completed: { label: '완료', cls: 'bg-gray-200 text-gray-600' },
  cancelled: { label: '취소', cls: 'bg-gray-200 text-gray-500' },
  ended: { label: '마감', cls: 'bg-gray-200 text-gray-600' },
}
const stageInfo = s => STAGES[s] || { label: s, cls: 'bg-gray-100 text-gray-500' }

function fmtDate(v) {
  if (!v) return ''
  const d = new Date(v)
  return isNaN(d) ? '' : `${d.getMonth() + 1}/${d.getDate()}`
}
function toggle(id) { openId.value = openId.value === id ? null : id }

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/groupbuys/my-opened')
    items.value = data.data || []
  } catch (e) {
    error.value = e.response?.data?.message || '불러오지 못했어요'
  }
  loading.value = false
})
</script>
