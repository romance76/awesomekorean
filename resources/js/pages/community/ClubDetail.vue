<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <button @click="$router.back()" class="text-sm text-gray-500 hover:text-amber-600 mb-3">← 동호회 목록</button>
    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="club" class="grid grid-cols-12 gap-4">
      <div class="col-span-12 lg:col-span-9">
        <!-- 헤더 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
          <div class="bg-gradient-to-r from-amber-400 to-orange-400 h-20"></div>
          <div class="px-5 pb-4 -mt-6">
            <div class="w-14 h-14 bg-white rounded-xl shadow flex items-center justify-center text-3xl border-2 border-white">👥</div>
            <h1 class="text-lg font-bold text-gray-900 mt-2">{{ club.name }}</h1>
            <div class="flex items-center gap-3 mt-1 text-xs text-gray-400">
              <span>{{ club.category }}</span>
              <span :class="club.type==='online' ? 'text-blue-600' : 'text-green-600'">{{ club.type === 'online' ? '🌐 온라인' : '📍 지역' }}</span>
              <span>👥 {{ club.member_count }}명</span>
            </div>
            <div class="text-sm text-gray-600 mt-2">{{ club.description }}</div>
            <div class="mt-3">
              <button v-if="auth.isLoggedIn && !isMember" @click="joinClub" class="bg-amber-400 text-amber-900 font-bold px-5 py-2 rounded-lg text-sm hover:bg-amber-500">가입하기</button>
              <button v-if="auth.isLoggedIn && isMember" @click="leaveClub" class="bg-gray-200 text-gray-600 font-bold px-5 py-2 rounded-lg text-sm hover:bg-gray-300">탈퇴하기</button>
            </div>
          </div>
        </div>

        <!-- 게시글 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="px-5 py-3 border-b font-bold text-sm text-amber-900">📝 동호회 게시글</div>
          <div v-for="post in posts" :key="post.id" class="px-5 py-3 border-b last:border-0">
            <div class="text-sm font-medium text-gray-800">{{ post.title }}</div>
            <div class="text-xs text-gray-400 mt-0.5"><UserName :userId="post.user?.id" :name="post.user?.name" /> · {{ formatDate(post.created_at) }}</div>
          </div>
          <div v-if="!posts.length" class="px-5 py-6 text-center text-sm text-gray-400">아직 게시글이 없습니다</div>
        </div>

        <CommentSection v-if="club.id" :type="'club'" :typeId="club.id" class="mt-4" />
      </div>

      <!-- 사이드바 -->
      <div class="col-span-12 lg:col-span-3 hidden lg:block">
        <SidebarWidgets api-url="/api/clubs" detail-path="/clubs/" :current-id="club.id"
          label="동호회" recommend-label="추천 동호회" quick-label="최근 활동"
          :links="[{to:'/clubs',icon:'📋',label:'전체 동호회'}]" />
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useSiteStore } from '../../stores/site'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import CommentSection from '../../components/CommentSection.vue'
import axios from 'axios'
const route = useRoute()
const auth = useAuthStore()
const siteStore = useSiteStore()
const club = ref(null)
const posts = ref([])
const isMember = ref(false)
const loading = ref(true)
function formatDate(dt) { return dt ? new Date(dt).toLocaleDateString('ko-KR') : '' }
async function joinClub() {
  try { await axios.post(`/api/clubs/${club.value.id}/join`); isMember.value = true; club.value.member_count++; siteStore.toast('가입되었습니다!', 'success') } catch(e) { siteStore.toast(e.response?.data?.message||'실패','error') }
}
async function leaveClub() {
  if (!confirm('정말 탈퇴하시겠습니까?')) return
  try { await axios.post(`/api/clubs/${club.value.id}/leave`); isMember.value = false; club.value.member_count--; siteStore.toast('탈퇴되었습니다','info') } catch {}
}
onMounted(async () => {
  try {
    const { data } = await axios.get(`/api/clubs/${route.params.id}`)
    club.value = data.data
    isMember.value = data.is_member || false
    try { const { data: p } = await axios.get(`/api/clubs/${route.params.id}/posts`); posts.value = p.data?.data || p.data || [] } catch {}
  } catch {}
  loading.value = false
})
</script>
