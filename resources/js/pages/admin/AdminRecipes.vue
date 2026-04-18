<template>
<div>
  <!-- 상단 API 동기화 툴바 -->
  <div class="flex items-center justify-end gap-2 mb-3">
    <span v-if="apiStatus.success !== undefined" class="text-xs"
      :class="apiStatus.success ? 'text-green-600' : 'text-red-500'">
      API {{ apiStatus.success ? '✅ 정상' : '❌ 오류' }}
      <span v-if="apiStatus.total" class="text-gray-500">({{ apiStatus.total?.toLocaleString() }}건)</span>
    </span>
    <button @click="testConnection" :disabled="testing"
      class="bg-blue-500 text-white font-bold px-3 py-1.5 rounded text-xs disabled:opacity-50">
      {{ testing ? '테스트 중...' : '🔌 API 테스트' }}
    </button>
    <button @click="showSync = true" class="bg-green-500 text-white font-bold px-3 py-1.5 rounded text-xs">
      🔄 동기화
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

  <!-- 동기화 모달 -->
  <div v-if="showSync" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showSync=false">
    <div class="bg-white rounded-xl p-5 w-full max-w-md">
      <div class="flex justify-between items-center mb-3">
        <h3 class="font-black text-gray-800">🔄 식품안전나라 API 동기화</h3>
        <button @click="showSync=false" class="text-gray-400 text-xl">✕</button>
      </div>
      <div class="text-xs text-gray-600 mb-3 space-y-1 bg-gray-50 p-2 rounded">
        <div>인증키: <code class="bg-white px-1 rounded">e3ffc744a3fb41299c10</code></div>
        <div>서비스: <code class="bg-white px-1 rounded">COOKRCP01</code></div>
      </div>
      <div class="flex items-center gap-2 mb-3">
        <label class="text-xs text-gray-600">시작</label>
        <input v-model.number="syncStart" type="number" min="1" class="border rounded px-2 py-1 text-xs w-20" />
        <label class="text-xs text-gray-600">끝</label>
        <input v-model.number="syncEnd" type="number" min="1" class="border rounded px-2 py-1 text-xs w-20" />
      </div>
      <div class="flex gap-2">
        <button @click="syncRange" :disabled="syncing" class="flex-1 bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded text-xs disabled:opacity-50">
          {{ syncing ? '동기화 중...' : '범위 동기화' }}
        </button>
        <button @click="syncAll" :disabled="syncing" class="flex-1 bg-green-500 text-white font-bold px-3 py-1.5 rounded text-xs disabled:opacity-50">
          ⚡ 전체 (1~1000)
        </button>
      </div>
      <div v-if="syncResult" class="mt-3 rounded p-3 text-xs"
        :class="syncResult.success ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'">
        <div class="font-bold">{{ syncResult.success ? '✅ 완료' : '❌ 오류' }}</div>
        <div class="mt-1" v-if="syncResult.success">
          저장: <strong>{{ syncResult.saved || syncResult.total_saved || 0 }}</strong>개
          <span v-if="syncResult.skipped !== undefined">· 중복 스킵: {{ syncResult.skipped }}개</span>
        </div>
        <div v-else class="mt-1">{{ syncResult.error || '알 수 없는 오류' }}</div>
      </div>
      <button @click="clearAll" class="mt-3 w-full bg-red-500 text-white font-bold px-3 py-1.5 rounded text-xs">
        🗑 전체 삭제
      </button>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import AdminBoardManager from '../../components/AdminBoardManager.vue'
import AdminUserModal from '../../components/AdminUserModal.vue'

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

async function syncAll() {
  if (!confirm('전체 동기화(1~1000)는 시간이 걸립니다. 진행할까요?')) return
  syncing.value = true; syncResult.value = null
  try { const { data } = await axios.post('/api/admin/recipes/sync-all'); syncResult.value = data }
  catch (e) { syncResult.value = { success: false, error: e.response?.data?.message || e.message } }
  syncing.value = false
}

async function clearAll() {
  if (!confirm('DB의 모든 레시피를 삭제합니다. 계속할까요?')) return
  if (!confirm('정말로 모든 레시피를 삭제하시겠습니까? 되돌릴 수 없습니다.')) return
  try { const { data } = await axios.post('/api/admin/recipes/clear-all'); alert(`${data.deleted}개 삭제됨`); showSync.value = false }
  catch (e) { alert(e.response?.data?.message || '실패') }
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
