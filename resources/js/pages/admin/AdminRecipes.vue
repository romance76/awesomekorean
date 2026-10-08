<template>
<div>
  <!-- 휴대폰: API 상태 + 큰 버튼 -->
  <div v-if="isMobile" class="alv-m mb-3 space-y-2">
    <div v-if="apiStatus.success !== undefined" class="flex items-center gap-1.5 text-[14px] font-bold px-1" :class="apiStatus.success ? 'text-green-600' : 'text-red-500'">
      {{ apiStatus.success ? '✅ API 정상' : '⚠️ API 오류' }}<span v-if="apiStatus.total" class="font-normal text-ink-muted">({{ apiStatus.total?.toLocaleString() }}건)</span>
    </div>
    <div class="grid grid-cols-2 gap-2">
      <button @click="testConnection" :disabled="testing" class="min-h-[50px] rounded-xl bg-blue-500 text-white text-[15px] font-bold disabled:opacity-50">{{ testing ? '테스트 중...' : 'API 테스트' }}</button>
      <button @click="showSync = true" class="min-h-[50px] rounded-xl bg-green-500 text-white text-[15px] font-bold">동기화</button>
    </div>
  </div>
  <!-- 상단 API 동기화 툴바 -->
  <div v-else class="flex items-center justify-end gap-2 mb-3">
    <span v-if="apiStatus.success !== undefined" class="inline-flex items-center gap-1 text-xs"
      :class="apiStatus.success ? 'text-green-600' : 'text-red-500'">
      <AppIcon :name="apiStatus.success ? 'check' : 'alert-circle'" :size="13" />API {{ apiStatus.success ? '정상' : '오류' }}
      <span v-if="apiStatus.total" class="text-ink-muted">({{ apiStatus.total?.toLocaleString() }}건)</span>
    </span>
    <button @click="testConnection" :disabled="testing"
      class="inline-flex items-center gap-1 bg-blue-500 text-white font-semibold px-3 py-1.5 rounded-xl text-xs hover:bg-blue-600 transition-colors disabled:opacity-50">
      <AppIcon name="globe" :size="13" />{{ testing ? '테스트 중...' : 'API 테스트' }}
    </button>
    <button @click="showSync = true" class="inline-flex items-center gap-1 bg-green-500 text-white font-semibold px-3 py-1.5 rounded-xl text-xs hover:bg-green-600 transition-colors">
      <AppIcon name="refresh" :size="13" />동기화
    </button>
  </div>

  <AdminBoardManager
    slug="recipes"
    label="레시피"
    icon="🍳"
    api-url="/api/recipes"
    delete-url="/api/admin/recipes"
    :extra-cols='[{"key":"category","label":"카테고리"},{"key":"cook_method","label":"조리법"},{"key":"calories","label":"칼로리"}]'
    :setting-schema="settingSchema"
    :point-schema="pointSchema"
    @open-user="u => { selectedUserId = u?.id; showUser = true }"
  />

  <AdminUserModal :show="showUser" :user-id="selectedUserId" @close="showUser=false" />

  <!-- 휴대폰: 동기화 시트 -->
  <Teleport to="body">
    <div v-if="isMobile && showSync" class="alv-m fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="closeSync">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2 max-h-[92vh] overflow-y-auto" role="dialog" aria-modal="true" aria-label="레시피 동기화" :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>
        <div class="text-[17px] font-bold text-ink">식품안전나라 레시피 동기화</div>
        <p class="text-[13px] text-ink-muted mb-3">서비스 COOKRCP01 · 번호 범위를 정해 가져와요.</p>
        <div class="grid grid-cols-2 gap-2 mb-3">
          <label class="block"><span class="block text-[13px] font-bold text-ink mb-1">시작</span><input v-model.number="syncStart" type="number" inputmode="numeric" min="1" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" /></label>
          <label class="block"><span class="block text-[13px] font-bold text-ink mb-1">끝</span><input v-model.number="syncEnd" type="number" inputmode="numeric" min="1" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" /></label>
        </div>
        <button @click="syncRange" :disabled="syncing" class="w-full min-h-[52px] rounded-xl bg-amber-500 text-white text-[16px] font-bold disabled:opacity-40">{{ syncing ? '동기화 중...' : '범위 동기화' }}</button>
        <button @click="mConfirm = 'all'" :disabled="syncing" class="mt-2 w-full min-h-[50px] rounded-xl bg-green-500 text-white text-[15px] font-bold disabled:opacity-40">전체 동기화 (1~1000)</button>
        <div v-if="syncResult" class="mt-3 rounded-xl p-3 text-[14px]" :class="syncResult.success ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'">
          <div class="font-bold">{{ syncResult.success ? '완료' : '오류' }}</div>
          <div v-if="syncResult.success">저장 <b>{{ syncResult.saved || syncResult.total_saved || 0 }}</b>개<span v-if="syncResult.skipped !== undefined"> · 중복 {{ syncResult.skipped }}개 건너뜀</span></div>
          <div v-else class="break-words">{{ syncResult.error || '알 수 없는 오류' }}</div>
        </div>
        <button @click="mConfirm = 'clear'" :disabled="syncing" class="mt-4 w-full min-h-[50px] rounded-xl bg-red-50 text-red-600 text-[15px] font-bold disabled:opacity-40">레시피 전체 삭제…</button>
        <button @click="closeSync" class="mt-2 w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">닫기</button>
      </div>
    </div>
    <!-- 휴대폰: 되돌릴 수 없는 작업 확인 -->
    <div v-if="isMobile && mConfirm" class="alv-m fixed inset-0 z-[75] bg-black/55 flex items-end" @click.self="mConfirm = null">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2" role="alertdialog" aria-modal="true" :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>
        <div class="text-[17px] font-bold text-ink mb-1">{{ mConfirm === 'clear' ? '모든 레시피를 삭제할까요?' : '전체 동기화를 시작할까요?' }}</div>
        <p class="text-[15px] text-ink-light mb-3">{{ mConfirm === 'clear' ? 'DB의 모든 레시피가 지워지고 되돌릴 수 없어요.' : '1~1000번을 모두 가져와서 시간이 걸려요. 끝날 때까지 화면을 닫지 마세요.' }}</p>
        <button @click="runConfirm" class="w-full min-h-[52px] rounded-xl text-white text-[16px] font-bold" :class="mConfirm === 'clear' ? 'bg-red-500' : 'bg-green-500'">{{ mConfirm === 'clear' ? '전부 삭제하기' : '동기화 시작' }}</button>
        <button @click="mConfirm = null" class="mt-2 w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">취소</button>
      </div>
    </div>
    <div v-if="toast && isMobile" class="alv-m fixed left-1/2 -translate-x-1/2 z-[80] max-w-[90vw] px-4 py-3 rounded-xl text-[15px] font-bold text-white shadow-lg" :class="toast.error ? 'bg-red-600' : 'bg-ink'" :style="{ top: 'calc(70px + env(safe-area-inset-top, 0px))' }" role="status">{{ toast.text }}</div>
  </Teleport>

  <!-- 동기화 모달 -->
  <div v-if="showSync && !isMobile" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showSync=false">
    <div class="bg-white rounded-2xl p-5 w-full max-w-md">
      <div class="flex justify-between items-center mb-3">
        <h3 class="flex items-center gap-2 font-bold text-ink"><span class="icon-chip w-7 h-7 bg-amber-50 text-amber-600"><AppIcon name="refresh" :size="15" /></span>식품안전나라 API 동기화</h3>
        <button @click="showSync=false" class="text-ink-muted hover:text-ink transition-colors"><AppIcon name="x" :size="20" /></button>
      </div>
      <div class="text-xs text-ink-light mb-3 space-y-1 bg-gray-50 p-2 rounded-lg">
        <div>인증키: <code class="bg-white px-1 rounded">e3ffc744a3fb41299c10</code></div>
        <div>서비스: <code class="bg-white px-1 rounded">COOKRCP01</code></div>
      </div>
      <div class="flex items-center gap-2 mb-3">
        <label class="text-xs text-ink-light">시작</label>
        <input v-model.number="syncStart" type="number" min="1" class="input-soft px-2 py-1 text-xs w-20" />
        <label class="text-xs text-ink-light">끝</label>
        <input v-model.number="syncEnd" type="number" min="1" class="input-soft px-2 py-1 text-xs w-20" />
      </div>
      <div class="flex gap-2">
        <button @click="syncRange" :disabled="syncing" class="flex-1 btn-primary px-3 py-1.5 text-xs">
          {{ syncing ? '동기화 중...' : '범위 동기화' }}
        </button>
        <button @click="syncAll" :disabled="syncing" class="flex-1 inline-flex items-center justify-center gap-1 bg-green-500 text-white font-semibold px-3 py-1.5 rounded-xl text-xs hover:bg-green-600 transition-colors disabled:opacity-50">
          <AppIcon name="sparkles" :size="13" />전체 (1~1000)
        </button>
      </div>
      <div v-if="syncResult" class="mt-3 rounded-lg p-3 text-xs"
        :class="syncResult.success ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'">
        <div class="flex items-center gap-1 font-bold"><AppIcon :name="syncResult.success ? 'check' : 'alert-circle'" :size="13" />{{ syncResult.success ? '완료' : '오류' }}</div>
        <div class="mt-1" v-if="syncResult.success">
          저장: <strong>{{ syncResult.saved || syncResult.total_saved || 0 }}</strong>개
          <span v-if="syncResult.skipped !== undefined">· 중복 스킵: {{ syncResult.skipped }}개</span>
        </div>
        <div v-else class="mt-1">{{ syncResult.error || '알 수 없는 오류' }}</div>
      </div>
      <button @click="clearAll" class="mt-3 w-full inline-flex items-center justify-center gap-1 bg-red-500 text-white font-semibold px-3 py-1.5 rounded-xl text-xs hover:bg-red-600 transition-colors">
        <AppIcon name="trash" :size="13" />전체 삭제
      </button>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, watch, inject, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import AdminBoardManager from '../../components/AdminBoardManager.vue'
import AdminUserModal from '../../components/AdminUserModal.vue'
import AppIcon from '../../components/AppIcon.vue'

// 관리자 휴대폰 화면이면 큰 버튼 + 아래에서 올라오는 시트 (AdminLayout 이 알려 줌)
const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)
const toast = ref(null); let toastTimer = null
function say(text, error = false) { toast.value = { text, error }; clearTimeout(toastTimer); toastTimer = setTimeout(() => { toast.value = null }, 3500) }
const mConfirm = ref(null)   // 'all' | 'clear'
function closeSync() { if (!syncing.value) showSync.value = false }
async function runConfirm() {
  const k = mConfirm.value; mConfirm.value = null
  if (k === 'all') await syncAll(true)
  else if (k === 'clear') await clearAll(true)
}
watch(() => isMobile.value && (showSync.value || !!mConfirm.value), locked => { document.body.style.overflow = locked ? 'hidden' : '' })
onBeforeUnmount(() => { document.body.style.overflow = ''; clearTimeout(toastTimer) })
const showUser = ref(false)
const selectedUserId = ref(null)
const showSync = ref(false)

const apiStatus = ref({})
const testing = ref(false)
const syncing = ref(false)
const syncStart = ref(1)
const syncEnd = ref(100)
const syncResult = ref(null)

async function testConnection() {
  testing.value = true
  try { const { data } = await axios.get('/api/admin/recipes/test-connection'); apiStatus.value = data }
  catch (e) { apiStatus.value = { success: false, error: e.message } }
  testing.value = false
}

async function syncRange() {
  syncing.value = true; syncResult.value = null
  try {
    const { data } = await axios.post('/api/admin/recipes/sync', { start: syncStart.value, end: syncEnd.value })
    syncResult.value = data
  } catch (e) { syncResult.value = { success: false, error: e.response?.data?.message || e.message } }
  syncing.value = false
}

async function syncAll(confirmed = false) {
  if (confirmed !== true && !confirm('전체 동기화(1~1000)는 시간이 걸립니다. 진행할까요?')) return
  syncing.value = true; syncResult.value = null
  try { const { data } = await axios.post('/api/admin/recipes/sync-all'); syncResult.value = data }
  catch (e) { syncResult.value = { success: false, error: e.response?.data?.message || e.message } }
  syncing.value = false
}

async function clearAll(confirmed = false) {
  if (confirmed !== true) {
    if (!confirm('DB의 모든 레시피를 삭제합니다. 계속할까요?')) return
    if (!confirm('정말로 모든 레시피를 삭제하시겠습니까? 되돌릴 수 없습니다.')) return
  }
  try { const { data } = await axios.post('/api/admin/recipes/clear-all'); if (isMobile.value) say(`${data.deleted}개 삭제됨`); else alert(`${data.deleted}개 삭제됨`); showSync.value = false }
  catch (e) { if (isMobile.value) say(e.response?.data?.message || '실패', true); else alert(e.response?.data?.message || '실패') }
}

const settingSchema = {
  enabled:        { label: '게시판 활성화',            type: 'bool',   default: true },
  allow_anonymous:{ label: '비로그인 열람 허용',       type: 'bool',   default: true },
  allow_user_post:{ label: '일반 회원 레시피 등록 허용', type: 'bool',   default: true },
  auto_fetch:     { label: '식품안전나라 자동 수집',    type: 'bool',   default: false },
  keep_inactive:  { label: '비활성 레시피 보관 (일)',  type: 'number', default: 0 },
  max_photos:     { label: '레시피당 최대 사진',       type: 'number', default: 15 },
}

const pointSchema = {
  recipe_create: { label: '레시피 등록',          default: 15, daily_max: 5 },
  recipe_cook:   { label: '요리 인증 (완료)',      default: 10, daily_max: 10 },
  comment:       { label: '댓글 작성',            default: 2,  daily_max: 20 },
  like_given:    { label: '좋아요 누르기',        default: 0,  daily_max: 50 },
  like_received: { label: '좋아요 받기 (작성자)', default: 1,  daily_max: 50 },
  reported:      { label: '신고 당함 (-차감)',     is_deduction: true, default: -5, daily_max: 0 },
}

onMounted(() => testConnection())
</script>

<style>
/* 휴대폰 관리자: 입력창 글자가 16px 보다 작으면 iOS 가 화면을 확대해 버림 */
.alv-m input, .alv-m textarea, .alv-m select { font-size: 16px; }
</style>
