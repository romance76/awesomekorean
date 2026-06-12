<template>
<div>
  <div class="flex justify-end mb-3">
    <button @click="fetchShorts" :disabled="fetching"
      class="inline-flex items-center gap-1.5 bg-blue-500 text-white font-semibold px-4 py-2 rounded-xl text-sm hover:bg-blue-600 transition-colors disabled:opacity-50">
      <AppIcon name="video" :size="14" />{{ fetching ? '수집 중...' : 'YouTube 숏츠 수집 (100개, 한국 75%)' }}
    </button>
  </div>

  <AdminBoardManager
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
</div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'
import AdminBoardManager from '../../components/AdminBoardManager.vue'
import AdminUserModal from '../../components/AdminUserModal.vue'
import AppIcon from '../../components/AppIcon.vue'

const showUser = ref(false)
const selectedUserId = ref(null)
const fetching = ref(false)

async function fetchShorts() {
  fetching.value = true
  try {
    const { data } = await axios.post('/api/admin/fetch-shorts')
    alert(data.message || '숏츠 수집 완료!')
    location.reload()
  } catch (e) { alert(e.response?.data?.message || '수집 실패') }
  fetching.value = false
}

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
