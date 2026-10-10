<template>
<div class="min-h-screen">
  <div class="page-main px-4 py-5">
    <DetailHeader :title="user?.display_name || user?.name || '프로필'" fallback="/" />
    <div v-if="loading" class="text-center py-12 text-ink-faint">로딩중...</div>
    <div v-else-if="user">
      <!-- 프로필 헤더 -->
      <div class="card overflow-hidden mb-4">
        <div class="bg-gradient-to-r from-[#FF8A4D] to-[#FC226B] h-24"></div>
        <div class="px-5 pb-4 -mt-10">
          <UserAvatar :user="user" :level="user.grade?.level" :size="150" class="-ml-5 -mt-5 -mb-5" />
          <h1 class="text-lg font-bold text-ink mt-2 flex items-center gap-2">
            {{ user.name }}
            <span v-if="user.grade" class="text-xs bg-amber-100 text-amber-700 rounded-full px-2 py-0.5 font-bold whitespace-nowrap">{{ user.grade.icon }} Lv.{{ user.grade.level }} {{ user.grade.label }}</span>
          </h1>
          <!-- 다른 회원에게는 등급(레벨)만 보여준다 — 포인트·거주지·가입일·소개·뱃지·작성글은 공개하지 않음 -->
          <RouterLink to="/grades" class="inline-block mt-2 text-xs text-amber-600 font-bold">등급 안내 →</RouterLink>
          <!-- 친구추가 / 쪽지 (본인이 아닐 때) -->
          <div v-if="auth.isLoggedIn && auth.user?.id !== user.id" class="flex gap-2 mt-3">
            <button @click="doAddFriend" :disabled="friendLoading" class="btn-primary text-xs px-3 py-1.5"><AppIcon name="user-plus" :size="13" /> 친구 추가</button>
            <button @click="msgModal = true" class="btn-secondary text-xs px-3 py-1.5"><AppIcon name="mail" :size="13" /> 쪽지</button>
            <button @click="reportShow = true" class="text-xs text-ink-faint hover:text-red-500 px-2 transition-colors"><AppIcon name="flag" :size="14" /></button>
          </div>
        </div>
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
import UserAvatar from '../../components/UserAvatar.vue'
import DetailHeader from '../../components/DetailHeader.vue'
import { useFriendAction } from '../../composables/useSocialActions'
import axios from 'axios'
const route = useRoute()
const auth = useAuthStore()
const user = ref(null)
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
  } catch {}
  loading.value = false
})
</script>
