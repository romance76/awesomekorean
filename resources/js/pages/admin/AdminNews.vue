<template>
<div>
  <div class="flex justify-end mb-3">
    <button @click="fetchNews" :disabled="fetching"
      class="bg-blue-500 text-white font-bold px-4 py-2 rounded-lg text-sm hover:bg-blue-600 disabled:opacity-50">
      {{ fetching ? '수집 중...' : '🔄 뉴스 수집 (RSS)' }}
    </button>
  </div>

  <AdminBoardManager
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
</div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'
import AdminBoardManager from '../../components/AdminBoardManager.vue'
import AdminUserModal from '../../components/AdminUserModal.vue'

const showUser = ref(false)
const selectedUserId = ref(null)
const fetching = ref(false)

async function fetchNews() {
  fetching.value = true
  try {
    const { data } = await axios.post('/api/admin/fetch-news')
    alert(`뉴스 수집 완료! ${data.saved || 0}건 저장됨`)
  } catch (e) { alert(e.response?.data?.message || '수집 실패') }
  fetching.value = false
}

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
