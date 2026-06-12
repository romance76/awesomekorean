<template>
<div>
  <AdminBoardManager
    slug="groupbuy"
    label="공동구매"
    icon="🛍"
    api-url="/api/groupbuys"
    delete-url="/api/admin/groupbuys"
    :extra-cols='[{"key":"category","label":"카테고리"},{"key":"status","label":"상태"},{"key":"current_participants","label":"참여"},{"key":"group_price","label":"공동가"}]'
    :setting-schema="settingSchema"
    :point-schema="pointSchema"
    :custom-tabs="[{ key: 'approval', label: '⏳ 승인 대기', badge: pending.length, position: 'afterCategory' }]"
    @open-user="u => { selectedUserId = u?.id; showUser = true }"
  >
    <template #tab-approval>
      <div class="mb-3 flex justify-between items-center">
        <div class="text-sm text-ink-light">공동구매 신규 등록 건은 승인 후 공개됩니다</div>
        <button @click="loadPending" class="btn-secondary !px-3 !py-1 text-xs"><AppIcon name="refresh" :size="12" /> 새로고침</button>
      </div>

      <div v-if="pending.length === 0" class="py-16 text-center">
        <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="check" :size="28" :stroke-width="1.5" /></div>
        <p class="text-sm text-ink-muted">승인 대기 중인 공동구매가 없습니다</p>
      </div>

      <div v-else class="space-y-2">
        <div v-for="item in pending" :key="item.id" class="border border-orange-100 rounded-xl p-3 bg-orange-50/50 hover:bg-orange-50 transition-colors">
          <div class="flex items-start gap-3">
            <img v-if="item.images && item.images[0]" :src="item.images[0]" class="w-16 h-16 object-cover rounded-lg shrink-0" @error="e=>e.target.style.display='none'" />
            <div class="flex-1 min-w-0">
              <div class="font-semibold text-sm text-ink truncate">{{ item.title }}</div>
              <div class="flex gap-3 text-xs text-ink-muted mt-1 flex-wrap">
                <span class="inline-flex items-center gap-1"><AppIcon name="tag" :size="11" /> {{ item.category }}</span>
                <span class="inline-flex items-center gap-1"><AppIcon name="dollar" :size="11" /> ${{ item.original_price }} → ${{ item.group_price }}</span>
                <span class="inline-flex items-center gap-1"><AppIcon name="users" :size="11" /> 최소 {{ item.min_participants }}명</span>
                <button @click="$emit('openUser', item.user)" class="text-blue-600 hover:underline transition-colors inline-flex items-center gap-1"><AppIcon name="user" :size="11" /> {{ item.user?.name }}</button>
                <a v-if="item.business_doc" :href="item.business_doc" target="_blank" class="text-amber-700 hover:underline transition-colors inline-flex items-center gap-1"><AppIcon name="paperclip" :size="11" /> 사업자등록증</a>
                <span class="text-ink-faint">{{ item.created_at?.slice(0,10) }}</span>
              </div>
              <div v-if="item.content" class="text-xs text-ink-light mt-2 line-clamp-2">{{ item.content }}</div>
            </div>
            <div class="flex flex-col gap-1 shrink-0">
              <button @click="approve(item.id)" class="inline-flex items-center gap-1 bg-green-500 hover:bg-green-600 text-white font-bold px-3 py-1 rounded-lg text-xs transition-colors"><AppIcon name="check" :size="12" /> 승인</button>
              <button @click="reject(item.id)" class="inline-flex items-center gap-1 bg-red-500 hover:bg-red-600 text-white font-bold px-3 py-1 rounded-lg text-xs transition-colors"><AppIcon name="x" :size="12" /> 거절</button>
            </div>
          </div>
        </div>
      </div>
    </template>
  </AdminBoardManager>
  <AdminUserModal :show="showUser" :user-id="selectedUserId" @close="showUser=false" />
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import AdminBoardManager from '../../components/AdminBoardManager.vue'
import AdminUserModal from '../../components/AdminUserModal.vue'
import AppIcon from '../../components/AppIcon.vue'

const showUser = ref(false)
const selectedUserId = ref(null)
const pending = ref([])

async function loadPending() {
  try {
    const { data } = await axios.get('/api/groupbuys', { params: { is_approved: 0, admin: 1, per_page: 50 } })
    pending.value = data.data?.data || []
  } catch {}
}

async function approve(id) {
  if (!confirm('승인하시겠습니까?')) return
  try { await axios.post(`/api/admin/groupbuys/${id}/approve`); loadPending() }
  catch (e) { alert(e.response?.data?.message || '실패') }
}

async function reject(id) {
  const reason = prompt('거절 사유를 입력하세요:')
  if (!reason) return
  try { await axios.post(`/api/admin/groupbuys/${id}/reject`, { reason }); loadPending() }
  catch (e) { alert(e.response?.data?.message || '실패') }
}

const settingSchema = {
  enabled:            { label: '게시판 활성화',             type: 'bool',   default: true },
  require_approval:   { label: '공구 등록 승인제',          type: 'bool',   default: true },
  require_business_doc:{label: '사업자등록증 필수',        type: 'bool',   default: true },
  allow_stripe:       { label: 'Stripe 결제 허용',          type: 'bool',   default: true },
  allow_point:        { label: '포인트 결제 허용',          type: 'bool',   default: true },
  min_participants:   { label: '최소 참여 인원',            type: 'number', default: 3 },
  max_discount_pct:   { label: '최대 할인율 (%)',           type: 'number', default: 70 },
  auto_close_days:    { label: '자동 마감 (일)',            type: 'number', default: 14 },
}

const pointSchema = {
  groupbuy_create:  { label: '공구 등록 (승인 후)',    default: 50, daily_max: 1 },
  groupbuy_join:    { label: '공구 참여',             default: 10, daily_max: 5 },
  groupbuy_complete:{ label: '공구 완료 (주최자)',    default: 100, daily_max: 0 },
  reported:         { label: '신고 당함 (-차감)',      is_deduction: true, default: -20, daily_max: 0 },
}

onMounted(() => loadPending())
</script>
