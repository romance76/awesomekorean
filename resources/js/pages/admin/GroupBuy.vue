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
      <!-- 휴대폰: 승인 대기 카드 -->
      <div v-if="isMobile" class="alv-m space-y-3 pb-2">
        <div class="flex items-center gap-2">
          <p class="flex-1 min-w-0 text-[14px] text-ink-muted">새로 등록된 공동구매는 승인 후 공개돼요.</p>
          <button @click="loadPending" class="shrink-0 min-h-[44px] px-4 rounded-xl border border-gray-200 bg-white text-[14px] font-bold text-blue-600">새로고침</button>
        </div>
        <div v-if="pending.length === 0" class="text-center py-12 text-ink-muted text-[15px]">승인 대기 중인 공동구매가 없어요 👍</div>
        <div v-for="item in pending" :key="item.id" class="bg-white border border-amber-200 rounded-2xl p-3.5">
          <div class="flex gap-3">
            <img v-if="item.images && item.images[0]" :src="item.images[0]" alt="" class="w-20 h-20 object-cover rounded-xl shrink-0" @error="e=>e.target.style.display='none'" />
            <div class="min-w-0 flex-1">
              <div class="text-[16px] font-bold text-ink leading-snug break-words line-clamp-2">{{ item.title }}</div>
              <div class="text-[13px] text-ink-muted mt-0.5">{{ item.category }} · {{ item.created_at?.slice(0,10) }}</div>
              <div class="text-[15px] font-bold text-ink mt-1">${{ item.original_price }} → <span class="text-red-600">${{ item.group_price }}</span></div>
            </div>
          </div>
          <div class="flex items-center gap-x-3 flex-wrap text-[13px] text-ink-muted mt-2">
            <span>최소 {{ item.min_participants }}명</span>
            <button v-if="item.user?.id" @click="goUser(item.user)" class="min-h-[40px] text-blue-600 font-bold text-[14px]">{{ item.user?.name }}</button>
          </div>
          <a v-if="item.business_doc" :href="item.business_doc" target="_blank" rel="noopener noreferrer" class="mt-1 flex items-center justify-center min-h-[46px] rounded-xl bg-amber-50 text-amber-700 text-[15px] font-bold">📎 사업자등록증 보기</a>
          <div v-if="item.content" class="text-[14px] text-ink-light mt-2 bg-gray-50 rounded-xl px-3 py-2 break-words line-clamp-4">{{ item.content }}</div>
          <div class="grid grid-cols-2 gap-2 mt-3">
            <button @click="ask('approve', item)" class="min-h-[50px] rounded-xl bg-emerald-500 text-white text-[16px] font-bold">승인</button>
            <button @click="ask('reject', item)" class="min-h-[50px] rounded-xl bg-red-50 text-red-600 text-[16px] font-bold">거절</button>
          </div>
        </div>
        <Teleport to="body">
          <div v-if="sheet" class="alv-m fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="closeSheet">
            <div class="w-full bg-white rounded-t-3xl px-4 pt-2 max-h-[90vh] overflow-y-auto" role="dialog" aria-modal="true" :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
              <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>
              <div class="text-[17px] font-bold text-ink mb-1">{{ sheet.kind === 'approve' ? '공동구매를 승인할까요?' : '공동구매를 거절할까요?' }}</div>
              <p class="text-[15px] text-ink-light mb-3 break-words">{{ sheet.item.title }}</p>
              <textarea v-if="sheet.kind === 'reject'" v-model="reason" rows="3" maxlength="200" placeholder="거절 사유 (등록자에게 전달돼요)" aria-label="거절 사유" class="w-full rounded-xl border border-gray-200 px-3 py-3 mb-3"></textarea>
              <button @click="doSheet" :disabled="busy || (sheet.kind === 'reject' && !reason.trim())" class="w-full min-h-[52px] rounded-xl text-white text-[16px] font-bold disabled:opacity-40" :class="sheet.kind === 'approve' ? 'bg-emerald-500' : 'bg-red-500'">{{ busy ? '처리 중...' : (sheet.kind === 'approve' ? '승인하기' : '거절하기') }}</button>
              <button @click="closeSheet" :disabled="busy" class="mt-2 w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">취소</button>
            </div>
          </div>
          <div v-if="toast" class="alv-m fixed left-1/2 -translate-x-1/2 z-[80] max-w-[90vw] px-4 py-3 rounded-xl text-[15px] font-bold text-white shadow-lg" :class="toast.error ? 'bg-red-600' : 'bg-ink'" :style="{ top: 'calc(70px + env(safe-area-inset-top, 0px))' }" role="status">{{ toast.text }}</div>
        </Teleport>
      </div>
      <div v-else class="mb-3 flex justify-between items-center">
        <div class="text-sm text-ink-light">공동구매 신규 등록 건은 승인 후 공개됩니다</div>
        <button @click="loadPending" class="btn-secondary !px-3 !py-1 text-xs"><AppIcon name="refresh" :size="12" /> 새로고침</button>
      </div>

      <div v-if="!isMobile && pending.length === 0" class="py-16 text-center">
        <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="check" :size="28" :stroke-width="1.5" /></div>
        <p class="text-sm text-ink-muted">승인 대기 중인 공동구매가 없습니다</p>
      </div>

      <div v-else-if="!isMobile" class="space-y-2">
        <div v-for="item in pending" :key="item.id" class="border border-amber-100 rounded-xl p-3 bg-amber-50/50 hover:bg-amber-50 transition-colors">
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
import { ref, computed, watch, inject, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import AdminBoardManager from '../../components/AdminBoardManager.vue'
import AdminUserModal from '../../components/AdminUserModal.vue'
import AppIcon from '../../components/AppIcon.vue'

// 관리자 휴대폰 화면이면 카드 + 아래에서 올라오는 시트 (AdminLayout 이 알려 줌)
const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)
const router = useRouter()
const toast = ref(null); let toastTimer = null
function say(text, error = false) { toast.value = { text, error }; clearTimeout(toastTimer); toastTimer = setTimeout(() => { toast.value = null }, 3000) }
const busy = ref(false)
const sheet = ref(null)   // { kind: 'approve' | 'reject', item }
const reason = ref('')
function ask(kind, item) { reason.value = ''; sheet.value = { kind, item } }
function closeSheet() { if (!busy.value) sheet.value = null }
function goUser(u) { if (u?.id) router.push({ path: '/admin/members', query: { user: String(u.id) } }) }
async function doSheet() {
  if (busy.value || !sheet.value) return
  busy.value = true
  const { kind, item } = sheet.value
  try {
    if (kind === 'approve') await axios.post(`/api/admin/groupbuys/${item.id}/approve`)
    else await axios.post(`/api/admin/groupbuys/${item.id}/reject`, { reason: reason.value.trim() })
    sheet.value = null; say(kind === 'approve' ? '승인했어요' : '거절했어요'); loadPending()
  } catch (e) { say(e.response?.data?.message || '처리하지 못했어요', true) }
  finally { busy.value = false }
}
watch(() => isMobile.value && !!sheet.value, locked => { document.body.style.overflow = locked ? 'hidden' : '' })
onBeforeUnmount(() => { document.body.style.overflow = ''; clearTimeout(toastTimer) })
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

<style>
/* 휴대폰 관리자: 입력창 글자가 16px 보다 작으면 iOS 가 화면을 확대해 버림 */
.alv-m input, .alv-m textarea, .alv-m select { font-size: 16px; }
</style>
