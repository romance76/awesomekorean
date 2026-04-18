<template>
<div>
  <AdminBoardManager
    slug="market"
    label="장터"
    icon="🛒"
    api-url="/api/market"
    delete-url="/api/admin/market"
    :extra-cols='[{"key":"category","label":"카테고리"},{"key":"city","label":"도시"}]'
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

// 장터 고유 설정 스키마 — 키는 board.market.{key} 로 저장됨
const settingSchema = {
  enabled:            { label: '게시판 활성화',          type: 'bool',   default: true },
  allow_anonymous:    { label: '비로그인 열람 허용',     type: 'bool',   default: true },
  require_phone:      { label: '전화번호 필수',          type: 'bool',   default: false },
  require_city:       { label: '도시 필수',              type: 'bool',   default: true },
  max_photos:         { label: '글당 최대 사진 수',      type: 'number', default: 10 },
  auto_close_days:    { label: '자동 마감 (일)',         type: 'number', default: 30 },
  auto_hide_reports:  { label: '신고 N회 시 자동 숨김',  type: 'number', default: 5 },
  allow_negotiable:   { label: '가격 협의 허용',         type: 'bool',   default: true },
}

// 장터 고유 포인트 규칙 — 키는 board.market.point_{key} 로 저장됨
const pointSchema = {
  post_write:       { label: '게시글 작성',               default: 10, daily_max: 3 },
  sale_complete:    { label: '판매 완료',                 default: 20, daily_max: 10 },
  comment:          { label: '댓글 작성',                 default: 2,  daily_max: 20 },
  image_upload:     { label: '이미지 첨부 (장당)',        default: 1,  daily_max: 5 },
  bump:             { label: '끌어올리기 (-차감)',         is_deduction: true, default: -100, daily_max: 0 },
  promote_top:      { label: '상위노출 1일 (-차감)',       is_deduction: true, default: -500, daily_max: 0 },
  reported:         { label: '신고 당함 (-차감)',          is_deduction: true, default: -5,   daily_max: 0 },
}
</script>
