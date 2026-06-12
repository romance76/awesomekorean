<template>
<div class="min-h-screen">
  <div class="max-w-4xl mx-auto px-4 py-5">
    <div v-if="loading" class="text-center py-12 text-ink-faint">로딩중...</div>
    <div v-else-if="user">
      <!-- 프로필 헤더 -->
      <div class="card overflow-hidden mb-4">
        <div class="bg-gradient-to-r from-[#FF8A53] to-[#F2570F] h-24"></div>
        <div class="px-5 pb-4 -mt-10">
          <div class="w-20 h-20 rounded-full bg-amber-500 text-white flex items-center justify-center text-3xl font-black border-4 border-white shadow">
            {{ (user.name || '?')[0] }}
          </div>
          <h1 class="text-lg font-bold text-ink mt-2">{{ user.name }}</h1>
          <div v-if="user.nickname" class="text-sm text-ink-muted">@{{ user.nickname }}</div>
          <div v-if="user.bio" class="text-sm text-ink-light mt-1">{{ user.bio }}</div>
          <div class="flex items-center gap-4 mt-3 text-xs text-ink-muted">
            <span v-if="user.city" class="inline-flex items-center gap-1"><AppIcon name="map-pin" :size="12" /> {{ user.city }}, {{ user.state }}</span>
            <span class="inline-flex items-center gap-1"><AppIcon name="coins" :size="12" class="text-amber-500" /> {{ user.points || 0 }}P</span>
            <span class="inline-flex items-center gap-1"><AppIcon name="calendar" :size="12" /> {{ formatDate(user.created_at) }} 가입</span>
          </div>
          <!-- 친구추가 / 쪽지 (본인이 아닐 때) -->
          <div v-if="auth.isLoggedIn && auth.user?.id !== user.id" class="flex gap-2 mt-3">
            <button @click="doAddFriend" :disabled="friendLoading" class="btn-primary text-xs px-3 py-1.5"><AppIcon name="user-plus" :size="13" /> 친구 추가</button>
            <button @click="msgModal = true" class="btn-secondary text-xs px-3 py-1.5"><AppIcon name="mail" :size="13" /> 쪽지</button>
            <button @click="reportShow = true" class="text-xs text-ink-faint hover:text-red-500 px-2 transition-colors"><AppIcon name="flag" :size="14" /></button>
          </div>
        </div>
      </div>

      <!-- 게시글 -->
      <div class="card overflow-hidden divide-y divide-gray-50">
        <div class="px-5 py-3 font-bold text-sm text-ink flex items-center gap-2">
          <span class="icon-chip w-7 h-7 bg-amber-50 text-amber-600"><AppIcon name="edit" :size="14" /></span>작성한 글
        </div>
        <div v-for="post in posts" :key="post.id">
          <RouterLink :to="`/community/${post.board?.slug || 'free'}/${post.id}`" class="block px-5 py-3 hover:bg-amber-50/50 transition-colors">
            <div class="text-sm font-medium text-ink">{{ post.title }}</div>
            <div class="text-xs text-ink-faint mt-0.5">{{ post.view_count }}회 · {{ post.like_count }}좋아요</div>
          </RouterLink>
        </div>
        <div v-if="!posts.length" class="px-5 py-6 text-center text-sm text-ink-faint">작성한 글이 없습니다</div>
      </div>
    </div>
  </div>

  <!-- 신고 모달 -->
  <ReportModal :show="reportShow" reportableType="user" :reportableId="user?.id"
    contentType="user" @close="reportShow=false" @reported="reportShow=false" />

  <!-- 쪽지 모달 -->
  <MessageModal :show="msgModal" :userId="user?.id" :userName="user?.nickname || user?.name || ''"
    @close="msgModal=false" />
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import ReportModal from '../../components/ReportModal.vue'
import MessageModal from '../../components/MessageModal.vue'
import AppIcon from '../../components/AppIcon.vue'
import { useFriendAction } from '../../composables/useSocialActions'
import axios from 'axios'
const route = useRoute()
const auth = useAuthStore()
const user = ref(null)
const posts = ref([])
const loading = ref(true)
const reportShow = ref(false)
const msgModal = ref(false)
function formatDate(dt) { return dt ? new Date(dt).toLocaleDateString('ko-KR') : '' }

const { sendRequest, loading: friendLoading } = useFriendAction()
async function doAddFriend() { await sendRequest(user.value.id) }
onMounted(async () => {
  try {
    const { data } = await axios.get(`/api/users/${route.params.id}`)
    user.value = data.data
    const { data: pData } = await axios.get(`/api/users/${route.params.id}/posts`)
    posts.value = pData.data?.data || pData.data || []
  } catch {}
  loading.value = false
})
</script>
