<template>
<div>
  <div class="flex justify-end mb-3 alv-m" :class="isMobile ? '!justify-stretch' : ''">
    <AdminCollectButton kind="news" endpoint="/api/admin/fetch-news" label="뉴스 수집 (RSS)" :mobile="isMobile" @toast="say" @done="refreshKey++" />
  </div>

  <AdminBoardManager
    :key="refreshKey"
    slug="news"
    label="뉴스"
    icon="📰"
    api-url="/api/news"
    delete-url="/api/admin/news"
    :extra-cols='[{"key":"source","label":"출처"},{"key":"category.name","label":"카테고리"},{"key":"published_at","label":"게시일"}]'
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
const refreshKey = ref(0)   // 수집이 끝나면 목록을 다시 불러오기 위해 올림

const settingSchema = {
  enabled:        { label: '게시판 활성화',           type: 'bool',   default: true },
  auto_fetch:     { label: 'RSS 자동 수집',           type: 'bool',   default: true },
  fetch_interval: { label: '자동 수집 간격 (시간)',    type: 'number', default: 6 },
  rss_source:     { label: 'RSS 출처',               type: 'text',   default: '오마이뉴스' },
  keep_days:      { label: '보관 기간 (일, 0=영구)',   type: 'number', default: 90 },
  allow_comment:  { label: '댓글 허용',              type: 'bool',   default: true },
}

const pointSchema = {
  view_reward:  { label: '뉴스 읽기 보상',         default: 1, daily_max: 20 },
  comment:      { label: '댓글 작성',             default: 2, daily_max: 20 },
  share:        { label: '공유하기',              default: 3, daily_max: 10 },
  reported:     { label: '신고 당함 (-차감)',      is_deduction: true, default: -5, daily_max: 0 },
}
</script>
