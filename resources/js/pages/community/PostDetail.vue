<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <!-- 뒤로가기 -->
    <button @click="$router.back()" class="text-sm text-gray-500 hover:text-amber-600 mb-3 flex items-center gap-1">
      ← 목록으로
    </button>

    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>

    <div v-else-if="post" class="grid grid-cols-12 gap-4">
      <!-- 메인 -->
      <div class="col-span-12 lg:col-span-9">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <!-- 헤더 -->
          <div class="px-5 py-4 border-b">
            <div class="flex items-center gap-2 mb-2">
              <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-semibold">{{ post.board?.name }}</span>
              <span v-if="post.category" class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">{{ post.category }}</span>
            </div>
            <h1 class="text-lg font-bold text-gray-900">{{ post.title }}</h1>
            <div class="flex items-center gap-3 mt-2 text-xs text-gray-400">
              <span>{{ post.user?.name }}</span>
              <span>{{ formatDate(post.created_at) }}</span>
              <span>조회 {{ post.view_count }}</span>
              <span>좋아요 {{ post.like_count }}</span>
              <span>댓글 {{ post.comment_count }}</span>
            </div>
          </div>

          <!-- 본문 -->
          <div class="px-5 py-5 text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">{{ post.content }}</div>

          <!-- 이미지 -->
          <div v-if="post.images?.length" class="px-5 pb-4 flex flex-wrap gap-2">
            <img v-for="(img, i) in post.images" :key="i" :src="'/storage/' + img"
              class="w-32 h-32 object-cover rounded-lg border" @error="e => e.target.style.display='none'" />
          </div>

          <!-- 액션 버튼 -->
          <div class="px-5 py-3 border-t flex items-center gap-4">
            <button @click="toggleLike" class="flex items-center gap-1 text-sm" :class="liked ? 'text-red-500' : 'text-gray-400 hover:text-red-400'">
              {{ liked ? '❤️' : '🤍' }} 좋아요 {{ post.like_count }}
            </button>
            <button class="text-gray-400 text-sm hover:text-amber-600">🔖 북마크</button>
            <button class="text-gray-400 text-sm hover:text-amber-600">🔗 공유</button>
          </div>
        </div>

        <!-- 댓글 섹션 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 mt-4 overflow-hidden">
          <div class="px-5 py-3 border-b font-bold text-sm text-gray-800">💬 댓글 {{ comments.length }}개</div>

          <!-- 댓글 입력 -->
          <div v-if="auth.isLoggedIn" class="px-5 py-3 border-b">
            <div class="flex gap-2">
              <input v-model="newComment" @keyup.enter="submitComment" type="text" placeholder="댓글을 입력하세요..."
                class="flex-1 border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 outline-none" />
              <button @click="submitComment" :disabled="!newComment.trim()" class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-lg text-sm hover:bg-amber-500 disabled:opacity-50">등록</button>
            </div>
          </div>

          <!-- 댓글 목록 -->
          <div v-for="comment in comments" :key="comment.id" class="px-5 py-3 border-b last:border-0">
            <div class="flex items-center gap-2 mb-1">
              <span class="text-sm font-semibold text-gray-800">{{ comment.user?.name }}</span>
              <span class="text-xs text-gray-400">{{ formatDate(comment.created_at) }}</span>
            </div>
            <div class="text-sm text-gray-600">{{ comment.content }}</div>
            <!-- 대댓글 -->
            <div v-for="reply in (comment.replies || [])" :key="reply.id" class="ml-6 mt-2 pl-3 border-l-2 border-gray-100">
              <div class="flex items-center gap-2 mb-0.5">
                <span class="text-sm font-semibold text-gray-700">{{ reply.user?.name }}</span>
                <span class="text-xs text-gray-400">{{ formatDate(reply.created_at) }}</span>
              </div>
              <div class="text-sm text-gray-500">{{ reply.content }}</div>
            </div>
          </div>

          <div v-if="!comments.length" class="px-5 py-6 text-center text-sm text-gray-400">아직 댓글이 없습니다. 첫 댓글을 남겨보세요!</div>
        </div>
      </div>

      <!-- 사이드바 -->
      <div class="col-span-12 lg:col-span-3 hidden lg:block">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
          <div class="font-bold text-sm text-amber-900 mb-3">✍️ 작성자 정보</div>
          <div class="text-sm text-gray-700 font-semibold">{{ post.user?.name }}</div>
          <div v-if="post.user?.bio" class="text-xs text-gray-400 mt-1">{{ post.user.bio }}</div>
        </div>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'

const route = useRoute()
const auth = useAuthStore()
const post = ref(null)
const comments = ref([])
const loading = ref(true)
const liked = ref(false)
const newComment = ref('')

function formatDate(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  const diff = Date.now() - d.getTime()
  const h = Math.floor(diff / 3600000)
  if (h < 1) return '방금'
  if (h < 24) return h + '시간 전'
  const days = Math.floor(h / 24)
  if (days < 7) return days + '일 전'
  return d.toLocaleDateString('ko-KR')
}

async function toggleLike() {
  if (!auth.isLoggedIn) return
  try {
    const { data } = await axios.post(`/api/posts/${post.value.id}/like`)
    liked.value = data.liked
    post.value.like_count += data.liked ? 1 : -1
  } catch {}
}

async function submitComment() {
  if (!newComment.value.trim()) return
  try {
    const { data } = await axios.post('/api/comments', {
      commentable_type: 'post',
      commentable_id: post.value.id,
      content: newComment.value,
    })
    comments.value.unshift(data.data)
    newComment.value = ''
    post.value.comment_count++
  } catch {}
}

onMounted(async () => {
  try {
    const { data } = await axios.get(`/api/posts/${route.params.id}`)
    post.value = data.data

    const { data: cData } = await axios.get(`/api/comments/post/${route.params.id}`)
    comments.value = cData.data || []
  } catch {}
  loading.value = false
})
</script>
