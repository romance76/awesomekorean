<template>
<div>
  <AdminBoardManager
    slug="jobs"
    label="구인구직"
    icon="💼"
    api-url="/api/jobs"
    delete-url="/api/admin/jobs"
    :extra-cols='[{"key":"company","label":"회사"},{"key":"city","label":"도시"},{"key":"type","label":"유형"}]'
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
  enabled:           { label: '게시판 활성화',          type: 'bool',   default: true },
  allow_anonymous:   { label: '비로그인 열람 허용',     type: 'bool',   default: true },
  require_company:   { label: '회사명 필수',            type: 'bool',   default: true },
  require_salary:    { label: '연봉 공개 필수',         type: 'bool',   default: false },
  require_business_license: { label: '사업자등록증 필수', type: 'bool', default: false },
  expire_days:       { label: '자동 마감 (일)',         type: 'number', default: 60 },
  max_photos:        { label: '글당 최대 사진',         type: 'number', default: 5 },
}

const pointSchema = {
  post_write:    { label: '구인글 작성',        default: 10, daily_max: 3 },
  seeker_write:  { label: '구직글 작성',        default: 5,  daily_max: 3 },
  hire_complete: { label: '채용 완료',          default: 30, daily_max: 0 },
  comment:       { label: '댓글 작성',          default: 2,  daily_max: 20 },
  promote_top:   { label: '상위노출 (-차감)',    is_deduction: true, default: -300, daily_max: 0 },
  reported:      { label: '신고 당함 (-차감)',   is_deduction: true, default: -10,  daily_max: 0 },
}
</script>
