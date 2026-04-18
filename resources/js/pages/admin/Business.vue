<template>
<div>
  <AdminBoardManager
    slug="business"
    label="업소록"
    icon="🏪"
    api-url="/api/businesses"
    delete-url="/api/admin/businesses"
    :extra-cols='[{"key":"category","label":"업종"},{"key":"subcategory","label":"세부"},{"key":"city","label":"도시"},{"key":"rating","label":"평점"},{"key":"is_claimed","label":"클레임"}]'
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
  allow_user_claim:  { label: '업주 클레임 신청 허용',    type: 'bool',   default: true },
  auto_approve_claim:{ label: '클레임 자동 승인',        type: 'bool',   default: false },
  require_phone:     { label: '전화번호 필수',           type: 'bool',   default: true },
  require_address:   { label: '주소 필수',               type: 'bool',   default: true },
  google_sync:       { label: 'Google Places 자동 동기화', type: 'bool', default: false },
  show_google_rating:{ label: 'Google 평점 노출',        type: 'bool',   default: true },
  max_photos:        { label: '업소당 최대 사진',        type: 'number', default: 20 },
}

const pointSchema = {
  business_add:      { label: '업소 등록',           default: 30, daily_max: 3 },
  review_write:      { label: '리뷰 작성',           default: 5,  daily_max: 10 },
  claim_approved:    { label: '업주 클레임 승인',     default: 100, daily_max: 0 },
  promote_top:       { label: '상위노출 (-차감)',     is_deduction: true, default: -500, daily_max: 0 },
  reported:          { label: '신고 당함 (-차감)',    is_deduction: true, default: -10,  daily_max: 0 },
}
</script>
