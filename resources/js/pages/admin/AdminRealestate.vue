<template>
<div>
  <AdminBoardManager
    slug="realestate"
    label="부동산"
    icon="🏠"
    api-url="/api/realestate"
    delete-url="/api/admin/realestate"
    :extra-cols='[{"key":"type","label":"매물 종류"},{"key":"property_type","label":"주택 타입"},{"key":"city","label":"도시"},{"key":"price","label":"가격"}]'
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
  enabled:         { label: '게시판 활성화',         type: 'bool',   default: true },
  allow_anonymous: { label: '비로그인 열람 허용',    type: 'bool',   default: true },
  require_real_name:{ label: '실명 공개 필수',       type: 'bool',   default: true },
  require_address: { label: '상세 주소 필수',        type: 'bool',   default: true },
  require_phone:   { label: '전화번호 필수',         type: 'bool',   default: true },
  max_photos:      { label: '매물당 최대 사진',       type: 'number', default: 20 },
  auto_close_days: { label: '자동 마감 (일)',        type: 'number', default: 60 },
}

const pointSchema = {
  post_write:     { label: '매물 등록',           default: 20, daily_max: 2 },
  rent_complete:  { label: '임대/매매 완료',      default: 100, daily_max: 0 },
  comment:        { label: '댓글 작성',           default: 2,  daily_max: 20 },
  promote_top:    { label: '상위노출 (-차감)',     is_deduction: true, default: -500, daily_max: 0 },
  reported:       { label: '신고 당함 (-차감)',    is_deduction: true, default: -10,  daily_max: 0 },
}
</script>
