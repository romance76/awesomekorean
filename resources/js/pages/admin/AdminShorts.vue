<template>
<div>
  <div class="flex justify-end mb-3 alv-m" :class="isMobile ? '!justify-stretch' : ''">
    <AdminCollectButton kind="shorts" endpoint="/api/admin/fetch-shorts" label="YouTube 숏츠 수집 (100개, 한국 75%)" icon="video" :mobile="isMobile" @toast="say" @done="refreshKey++" />
  </div>

  <AdminBoardManager
    :key="refreshKey"
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
import AdminBoardManager from '../../components/AdminBoardManager.vue'
import AdminUserModal from '../../components/AdminUserModal.vue'
import AdminCollectButton from '../../components/AdminCollectButton.vue'

// 관리자 휴대폰 화면: 수집 버튼을 크게, 결과는 위쪽 알림으로 (AdminLayout 이 알려 줌)
const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)
const toast = ref(null); let toastTimer = null
function say(text, error = false) { toast.value = { text, error }; clearTimeout(toastTimer); toastTimer = setTimeout(() => { toast.value = null }, 3500) }
onBeforeUnmount(() => clearTimeout(toastTimer))
const showUser = ref(false)
const selectedUserId = ref(null)
const refreshKey = ref(0)   // 수집이 끝나면 목록을 다시 불러오기 위해 올림 (예전엔 전체 새로고침)

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
