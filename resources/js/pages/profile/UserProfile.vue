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
          <h1 class="text-lg font-bold text-ink mt-2 flex items-center gap-2">
            {{ user.name }}
            <span v-if="user.grade" class="text-xs bg-amber-100 text-amber-700 rounded-full px-2 py-0.5 font-bold whitespace-nowrap">{{ user.grade.icon }} Lv.{{ user.grade.level }} {{ user.grade.label }}</span>
          </h1>
          <div v-if="user.nickname" class="text-sm text-ink-muted">@{{ user.nickname }}</div>
          <div v-if="user.bio" class="text-sm text-ink-light mt-1">{{ user.bio }}</div>
          <div class="flex items-center gap-4 mt-3 text-xs text-ink-muted">
            <span v-if="user.city" class="inline-flex items-center gap-1"><AppIcon name="map-pin" :size="12" /> {{ user.city }}, {{ user.state }}</span>
            <span class="inline-flex items-center gap-1"><AppIcon name="coins" :size="12" class="text-amber-500" /> {{ user.points || 0 }}P</span>
            <span class="inline-flex items-center gap-1"><AppIcon name="calendar" :size="12" /> {{ formatDate(user.created_at) }} 가입</span>
          </div>

          <!-- 등급 진행도 -->
          <div v-if="user.grade" class="mt-3 bg-gray-50 rounded-xl p-3">
            <div class="flex items-center justify-between text-xs mb-1.5">
              <span class="font-bold text-ink">{{ user.grade.icon }} {{ user.grade.label }}</span>
              <span v-if="user.grade.next_label" class="text-ink-faint">다음: {{ user.grade.next_label }} ({{ user.grade.lifetime_points }} / {{ user.grade.next_min }}P)</span>
              <span v-else class="text-ink-faint">최고 등급 달성</span>
            </div>
            <div class="h-1.5 bg-gray-200 rounded-full overflow-hidden">
              <div class="h-full bg-gradient-to-r from-amber-400 to-orange-500 rounded-full transition-all" :style="{ width: user.grade.progress + '%' }"></div>
            </div>
          </div>

          <!-- 뱃지 -->
          <div v-if="user.badges?.length" class="mt-3">
            <div class="text-xs font-bold text-ink-muted mb-1.5">획득 뱃지 {{ user.badges.filter(b => b.earned).length }}/{{ user.badges.length }}</div>
            <div class="flex flex-wrap gap-2">
              <div v-for="b in user.badges" :key="b.key"
                class="flex flex-col items-center justify-center w-14 h-14 rounded-xl border text-center"
                :class="b.earned ? 'bg-amber-50 border-amber-200' : 'bg-gray-50 border-gray-100 opacity-40 grayscale'"
                :title="`${b.label} — ${b.desc}${b.earned_at ? ' (' + formatDate(b.earned_at) + ')' : ''}`">
                <span class="text-lg leading-none">{{ b.icon }}</span>
                <span class="text-[9px] text-ink-muted mt-0.5 leading-none px-0.5 truncate w-full">{{ b.label }}</span>
              </div>
            </div>
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
