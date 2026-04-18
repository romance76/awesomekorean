<template>
<div>
  <AdminBoardManager
    slug="clubs"
    label="동호회"
    icon="👥"
    api-url="/api/clubs"
    delete-url="/api/admin/clubs"
    :extra-cols='[{"key":"category","label":"카테고리"},{"key":"type","label":"유형"},{"key":"member_count","label":"회원수"},{"key":"max_members","label":"정원"}]'
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
  enabled:           { label: '게시판 활성화',           type: 'bool',   default: true },
  allow_anonymous:   { label: '비로그인 열람 허용',      type: 'bool',   default: true },
  allow_private:     { label: '비공개 동호회 허용',      type: 'bool',   default: true },
  max_clubs_per_user:{ label: '1인당 최대 동호회 개설',   type: 'number', default: 3 },
  min_members:       { label: '최소 회원 수 (유지)',     type: 'number', default: 3 },
  default_max_members:{label: '기본 정원',              type: 'number', default: 100 },
  require_approval:  { label: '가입 승인 기본값',        type: 'bool',   default: false },
}

const pointSchema = {
  club_create:   { label: '동호회 개설',         default: 50, daily_max: 1 },
  member_join:   { label: '회원 가입 (개설자)',  default: 5,  daily_max: 50 },
  post_write:    { label: '동호회 글 작성',      default: 3,  daily_max: 10 },
  comment:       { label: '댓글 작성',           default: 1,  daily_max: 20 },
  promote_top:   { label: '상위노출 (-차감)',     is_deduction: true, default: -200, daily_max: 0 },
  reported:      { label: '신고 당함 (-차감)',    is_deduction: true, default: -5,   daily_max: 0 },
}
</script>
