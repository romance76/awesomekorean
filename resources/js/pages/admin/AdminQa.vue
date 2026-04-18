<template>
<div>
  <AdminBoardManager
    slug="qa"
    label="Q&A"
    icon="❓"
    api-url="/api/qa"
    delete-url="/api/admin/qa"
    :extra-cols='[{"key":"category.name","label":"카테고리"},{"key":"bounty_points","label":"현상금"},{"key":"answer_count","label":"답변"}]'
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
  enabled:          { label: '게시판 활성화',           type: 'bool',   default: true },
  allow_anonymous:  { label: '비로그인 열람 허용',      type: 'bool',   default: true },
  allow_bounty:     { label: '현상금(포인트 걸기) 허용', type: 'bool',   default: true },
  min_bounty:       { label: '최소 현상금 (P)',         type: 'number', default: 10 },
  max_bounty:       { label: '최대 현상금 (P)',         type: 'number', default: 1000 },
  auto_close_days:  { label: '자동 마감 (일)',          type: 'number', default: 30 },
}

const pointSchema = {
  question_write: { label: '질문 작성',         default: 5,  daily_max: 10 },
  answer_write:   { label: '답변 작성',         default: 10, daily_max: 20 },
  answer_accept:  { label: '답변 채택 (답변자)', default: 20, daily_max: 0 },
  best_answer:    { label: '베스트 답변',       default: 30, daily_max: 0 },
  reported:       { label: '신고 당함 (-차감)', is_deduction: true, default: -5, daily_max: 0 },
}
</script>
