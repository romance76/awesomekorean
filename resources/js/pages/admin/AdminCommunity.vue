<template>
<div>
  <AdminBoardManager
    slug="community"
    label="커뮤니티"
    icon="💬"
    api-url="/api/posts"
    delete-url="/api/admin/posts"
    :extra-cols='[{"key":"board.name","label":"게시판"},{"key":"category","label":"카테고리"},{"key":"city","label":"도시"},{"key":"like_count","label":"♥"},{"key":"comment_count","label":"💬"}]'
    :setting-schema="settingSchema"
    :point-schema="pointSchema"
    @open-user="u => { selectedUserId = u?.id; showUser = true }"
  />
  <AdminUserModal :show="showUser" :user-id="selectedUserId" @close="showUser=false" />
</div>
</template>

<script setup>
import { ref } from 'vue'
import AdminBoardManager from '../../components/AdminBoardManager.vue'
import AdminUserModal from '../../components/AdminUserModal.vue'

const showUser = ref(false)
const selectedUserId = ref(null)

const settingSchema = {
  enabled:            { label: '커뮤니티 활성화',             type: 'bool',   default: true },
  allow_anonymous:    { label: '비로그인 열람 허용',          type: 'bool',   default: true },
  allow_anonymous_post:{ label: '익명 글쓰기 허용',           type: 'bool',   default: false },
  allow_images:       { label: '이미지 첨부 허용',            type: 'bool',   default: true },
  max_photos:         { label: '글당 최대 사진 수',            type: 'number', default: 10 },
  auto_hide_reports:  { label: '신고 N회 시 자동 숨김',       type: 'number', default: 5 },
  min_post_length:    { label: '최소 글 길이 (자)',            type: 'number', default: 10 },
  cooldown_minutes:   { label: '연속 작성 쿨다운 (분)',       type: 'number', default: 1 },
  allow_link:         { label: '외부 링크 허용',              type: 'bool',   default: true },
  pinned_days:        { label: '고정 게시글 기본 유지 (일)',   type: 'number', default: 7 },
}

const pointSchema = {
  post_write:    { label: '글 작성',                default: 5,  daily_max: 10 },
  comment_write: { label: '댓글 작성',              default: 2,  daily_max: 20 },
  like_received: { label: '좋아요 받기 (작성자)',   default: 1,  daily_max: 50 },
  like_given:    { label: '좋아요 누르기',           default: 0,  daily_max: 50 },
  image_upload:  { label: '이미지 첨부 (장당)',      default: 1,  daily_max: 10 },
  pinned:        { label: '관리자 고정 보너스',      default: 20, daily_max: 0 },
  reported:      { label: '신고 당함 (-차감)',       is_deduction: true, default: -5, daily_max: 0 },
  hidden:        { label: '숨김 처리 (-차감)',       is_deduction: true, default: -20, daily_max: 0 },
}
</script>
