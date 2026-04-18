<template>
  <div class="bg-white rounded-xl shadow-sm p-4">
    <h3 class="font-bold mb-3">💬 내 댓글</h3>
    <div v-if="loading" class="p-6 text-center text-sm text-gray-400">로딩 중...</div>
    <div v-else-if="!items.length" class="p-10 text-center text-sm text-gray-500">
      <p class="text-3xl mb-2">💬</p><p>작성한 댓글이 없습니다.</p>
    </div>
    <ul v-else class="divide-y">
      <li v-for="c in items" :key="c.id" class="py-3">
        <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ c.content || c.body }}</p>
        <div class="flex items-center justify-between mt-2 text-xs text-gray-400">
          <router-link v-if="c.post_id || c.target_id" :to="targetLink(c)" class="text-amber-600 hover:text-amber-800 truncate max-w-xs">
            → {{ c.post?.title || c.target?.title || '원문 보기' }}
          </router-link>
          <span>{{ fmtDate(c.created_at) }}</span>
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { useAuthStore } from '../../stores/auth'
const auth = useAuthStore()
const items = ref([])
const loading = ref(true)
const fmtDate = (s) => s ? new Date(s).toLocaleDateString('ko-KR') : ''
const targetLink = (c) => {
  const t = c.commentable_type || c.target_type || 'post'
  const id = c.commentable_id || c.target_id || c.post_id
  const map = { post: `/community/${id}`, market: `/market/${id}`, realestate: `/realestate/${id}`, business: `/directory/${id}` }
  const key = t.toLowerCase().split('\\').pop()
  return map[key] || map.post
}
onMounted(async () => {
  try {
    if (!auth.user?.id) await auth.fetchUser?.()
    const { data } = await axios.get(`/api/users/${auth.user?.id}/comments?per_page=50`).catch(() => ({ data: { data: [] } }))
    items.value = data?.data?.data || data?.data || []
  } finally { loading.value = false }
})
</script>
