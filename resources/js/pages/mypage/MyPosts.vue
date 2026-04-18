<template>
  <!-- /mypage/posts (Phase 2-C 묶음 3) -->
  <div class="bg-white rounded-xl shadow-sm p-4">
    <h3 class="font-bold mb-3">📝 내가 쓴 글</h3>
    <div v-if="loading" class="p-6 text-center text-sm text-gray-400">로딩 중...</div>
    <div v-else-if="!posts.length" class="p-10 text-center text-sm text-gray-500">
      <p class="text-3xl mb-2">📄</p>
      <p>작성한 글이 없습니다.</p>
      <router-link to="/community" class="inline-block mt-3 px-4 py-2 bg-amber-400 hover:bg-amber-500 text-white rounded-lg text-sm font-semibold">
        커뮤니티로 이동 →
      </router-link>
    </div>
    <ul v-else class="divide-y">
      <li v-for="p in posts" :key="p.id" class="py-3 hover:bg-amber-50 px-2 rounded">
        <router-link :to="`/community/${p.id}`" class="block">
          <p class="text-sm font-semibold text-gray-800">{{ p.title }}</p>
          <div class="flex items-center gap-3 text-xs text-gray-500 mt-1">
            <span>💬 {{ p.comments_count || 0 }}</span>
            <span>❤️ {{ p.likes_count || 0 }}</span>
            <span>👁️ {{ p.view_count || 0 }}</span>
            <span class="text-gray-400">{{ fmtDate(p.created_at) }}</span>
          </div>
        </router-link>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { useAuthStore } from '../../stores/auth'

const auth = useAuthStore()
const posts = ref([])
const loading = ref(true)

const fmtDate = (s) => s ? new Date(s).toLocaleDateString('ko-KR') : ''

onMounted(async () => {
  try {
    if (!auth.user?.id) await auth.fetchUser?.()
    const uid = auth.user?.id
    if (!uid) return
    const { data } = await axios.get(`/api/users/${uid}/posts`)
    posts.value = data?.data?.data || data?.data || []
  } catch {} finally { loading.value = false }
})
</script>
