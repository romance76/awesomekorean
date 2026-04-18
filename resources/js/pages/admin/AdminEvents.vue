<template>
<div>
  <AdminBoardManager
    slug="events"
    label="이벤트"
    icon="🎉"
    api-url="/api/events"
    delete-url="/api/admin/events"
    :extra-cols='[{"key":"category","label":"카테고리"},{"key":"event_type","label":"유형"},{"key":"city","label":"도시"},{"key":"attendee_count","label":"참가자"},{"key":"start_date","label":"시작일"}]'
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
  enabled:          { label: '게시판 활성화',             type: 'bool',   default: true },
  allow_anonymous:  { label: '비로그인 열람 허용',        type: 'bool',   default: true },
  allow_user_events:{ label: '일반 회원 이벤트 등록 허용',  type: 'bool',   default: true },
  require_venue:    { label: '장소 필수',                type: 'bool',   default: true },
  require_date:     { label: '시작일 필수',              type: 'bool',   default: true },
  default_reward:   { label: '기본 참여 보상 포인트',      type: 'number', default: 10 },
  max_photos:       { label: '이벤트당 최대 사진',        type: 'number', default: 10 },
}

const pointSchema = {
  event_create:  { label: '이벤트 등록',          default: 20, daily_max: 3 },
  event_join:    { label: '이벤트 참가',          default: 10, daily_max: 10 },
  event_complete:{ label: '이벤트 완료 인증',      default: 30, daily_max: 0 },
  comment:       { label: '댓글 작성',           default: 2,  daily_max: 20 },
  promote_pin:   { label: '상단 고정 (-차감)',    is_deduction: true, default: -200, daily_max: 0 },
  reported:      { label: '신고 당함 (-차감)',    is_deduction: true, default: -5,   daily_max: 0 },
}
</script>
