<template>
<div>
  <div class="flex justify-end mb-3 alv-m" :class="isMobile ? '!justify-stretch' : ''">
    <button @click="fetchShorts" :disabled="fetching"
      class="inline-flex items-center justify-center gap-1.5 bg-blue-500 text-white font-semibold px-4 py-2 rounded-xl text-sm hover:bg-blue-600 transition-colors disabled:opacity-50" :class="isMobile ? 'w-full min-h-[50px] !text-[15px] !font-bold' : ''">
      <AppIcon name="video" :size="14" />{{ fetching ? '수집 중...' : 'YouTube 숏츠 수집 (100개, 한국 75%)' }}
    </button>
  </div>

  <AdminBoardManager
    slug="shorts"
    label="숏츠"
    icon="🎬"
    api-url="/api/admin/shorts"
    :extra-cols='[{"key":"youtube_id","label":"YouTube"},{"key":"duration","label":"초"},{"key":"like_count","label":"좋아요"}]'
    :setting-schema="settingSchema"
    :point-schema="pointSchema"
    @open-user="u => { selectedUserId = u?.id; showUser = true }"
  />
  <AdminUserModal :show="showUser" :user-id="selectedUserId" @close="showUser=false" />
  <Teleport to="body">
    <div v-if="toast && isMobile" class="alv-m fixed left-1/2 -translate-x-1/2 z-[80] max-w-[90vw] px-4 py-3 rounded-xl text-[15px] font-bold text-white shadow-lg" :class="toast.error ? 'bg-red-600' : 'bg-ink'" :style="{ top: 'calc(70px + env(safe-area-inset-top, 0px))' }" role="status">{{ toast.text }}</div>
  </Teleport>
</div>
</template>

<script setup>
import { ref, computed, inject, onBeforeUnmount } from 'vue'
import axios from 'axios'
import AdminBoardManager from '../../components/AdminBoardManager.vue'
import AdminUserModal from '../../components/AdminUserModal.vue'
import AppIcon from '../../components/AppIcon.vue'

// 관리자 휴대폰 화면: 수집 버튼을 크게, 결과는 위쪽 알림으로 (AdminLayout 이 알려 줌)
const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)
const toast = ref(null); let toastTimer = null
function say(text, error = false) { toast.value = { text, error }; clearTimeout(toastTimer); toastTimer = setTimeout(() => { toast.value = null }, 3500) }
onBeforeUnmount(() => clearTimeout(toastTimer))
const showUser = ref(false)
const selectedUserId = ref(null)
const fetching = ref(false)

async function fetchShorts() {
  fetching.value = true
  try {
    const { data } = await axios.post('/api/admin/fetch-shorts')
    if (isMobile.value) { say(data.message || '숏츠 수집 완료!'); setTimeout(() => location.reload(), 1200) }
    else { (window.nativeAlert || alert)(data.message || '숏츠 수집 완료!'); location.reload() }
  } catch (e) { { if (isMobile.value) say(e.response?.data?.message || '수집 실패', true); else alert(e.response?.data?.message || '수집 실패') } }
  fetching.value = false
}

const settingSchema = {
  enabled:           { label: '게시판 활성화',              type: 'bool',   default: true },
  allow_user_upload: { label: '일반 회원 업로드 허용',      type: 'bool',   default: true },
  auto_fetch:        { label: 'YouTube 자동 수집',          type: 'bool',   default: true },
  korea_ratio:       { label: '한국 콘텐츠 비율 (%)',        type: 'number', default: 75 },
  max_duration:      { label: '최대 길이 (초)',              type: 'number', default: 60 },
  keep_days:         { label: '보관 기간 (일, 0=영구)',       type: 'number', default: 0 },
  allow_comment:     { label: '댓글 허용',                  type: 'bool',   default: true },
}

const pointSchema = {
  upload:      { label: '숏츠 업로드',         default: 5,  daily_max: 3 },
  view_reward: { label: '시청 보상',            default: 1,  daily_max: 20 },
  like_given:  { label: '좋아요 누르기',        default: 0,  daily_max: 50 },
  comment:     { label: '댓글 작성',            default: 2,  daily_max: 20 },
  reported:    { label: '신고 당함 (-차감)',     is_deduction: true, default: -5, daily_max: 0 },
}
</script>
