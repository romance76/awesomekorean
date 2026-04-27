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
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
          <!-- 헤더 -->
          <div class="px-5 py-4 border-b">
            <div class="flex items-center gap-2 mb-2">
              <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-semibold">{{ post.board?.name }}</span>
              <span v-if="post.category" class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">{{ post.category }}</span>
            </div>
            <h1 class="text-lg font-bold text-gray-900">{{ post.title }}</h1>
            <div class="flex items-center gap-3 mt-2 text-xs text-gray-400">
              <UserName :userId="post.user?.id" :name="post.user?.name" className="text-gray-800" />
              <span>{{ formatDate(post.created_at) }}</span>
              <span>{{ post.view_count }}회</span>
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
            <BookmarkToggle :active="bookmarked" @toggle="toggleBookmark" size="lg" />
            <button @click="sharePost" class="text-gray-400 text-sm hover:text-amber-600">🔗 공유</button>
            <button @click="showReport=true" class="text-gray-400 text-sm hover:text-red-400 ml-auto">🚨 신고</button>
            <!-- 작성자 전용: 수정/삭제 -->
            <template v-if="auth.user?.id === post.user_id">
              <RouterLink :to="`/community/write/${post.board?.slug}?edit=${post.id}`" class="text-gray-400 text-sm hover:text-amber-600">✏️ 수정</RouterLink>
              <button @click="deletePost" class="text-gray-400 text-sm hover:text-red-500">🗑️ 삭제</button>
            </template>
          </div>

        </div>

        <!-- 신고 모달 -->
        <ReportModal :show="showReport" reportableType="post" :reportableId="post?.id"
          contentType="post" @close="showReport=false" @reported="showReport=false" />

        <!-- 댓글 섹션 -->
        <CommentSection :type="'post'" :typeId="post.id" class="mt-4" />

        <!-- 이전글 / 목록 / 다음글 -->
        <PostNavigator :prev-id="prev?.id" :prev-title="prev?.title"
          :next-id="next?.id" :next-title="next?.title"
          :list-path="`/community/${post.board?.slug || 'free'}`"
          :detail-base="`/community/${post.board?.slug || 'free'}/`" />
      </div>

      <!-- 사이드바 -->
      <div class="col-span-12 lg:col-span-3 hidden lg:block space-y-3">
        <!-- 작성자 정보 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
          <div class="font-bold text-sm text-amber-900 mb-3">✍️ 작성자 정보</div>
          <RouterLink :to="`/profile/${post.user?.id}`" class="flex items-center gap-2 hover:bg-amber-50 -mx-2 px-2 py-1 rounded-lg transition">
            <div class="w-8 h-8 bg-amber-100 rounded-full flex items-center justify-center text-sm font-bold text-amber-700">{{ (post.user?.name || '?')[0] }}</div>
            <div>
              <UserName :userId="post.user?.id" :name="post.user?.name" className="text-sm text-gray-700 font-semibold" />
              <div v-if="post.user?.bio" class="text-[10px] text-gray-400 truncate max-w-[120px]">{{ post.user.bio }}</div>
            </div>
          </RouterLink>
        </div>

        <!-- 공통 사이드바 위젯 -->
        <SidebarWidgets mode="detail" :currentCategory="post?.category || ''"
          :api-url="`/api/posts?board_id=${post.board_id}`"
          :detail-path="`/community/${post.board?.slug || 'free'}/`"
          :current-id="post.id"
          label="게시글"
          recommend-label="추천 글"
          quick-label="실시간 게시글"
          :links="[
            { to: '/community', icon: '📋', label: '전체 게시판' },
            { to: `/community/write/${post.board?.slug || ''}`, icon: '✏️', label: '글쓰기' },
            { to: '/qa', icon: '❓', label: 'Q&A' },
          ]"
        />
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import CommentSection from '../../components/CommentSection.vue'
import PostNavigator from '../../components/PostNavigator.vue'
import ReportModal from '../../components/ReportModal.vue'
import { useSiteStore } from '../../stores/site'
import axios from 'axios'
import BookmarkToggle from '../../components/BookmarkToggle.vue'

const route = useRoute()
const auth = useAuthStore()
const router = useRouter()
const post = ref(null)
const prev = ref(null)
const next = ref(null)
const comments = ref([])
const loading = ref(true)
const liked = ref(false)
const bookmarked = ref(false)
const newComment = ref('')
const siteStore = useSiteStore()
const showReport = ref(false)
const editingComment = ref(null)
const editCommentText = ref('')
const replyTo = ref(null)
const replyText = ref('')

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

async function toggleBookmark() {
  if (!auth.isLoggedIn) return
  try {
    const { data } = await axios.post('/api/bookmarks', { bookmarkable_type: 'App\\Models\\Post', bookmarkable_id: post.value.id })
    bookmarked.value = data.bookmarked
  } catch {}
}

function sharePost() {
  const url = window.location.href
  if (navigator.share) {
    navigator.share({ title: post.value.title, url })
  } else {
    navigator.clipboard.writeText(url)
    siteStore.toast('링크가 복사되었습니다', 'success')
  }
}

async function deletePost() {
  if (!confirm('정말 삭제하시겠습니까?')) return
  try { await axios.delete(`/api/posts/${post.value.id}`); router.push('/community') } catch {}
}

function startEditComment(c) { editingComment.value = c.id; editCommentText.value = c.content }

async function saveEditComment(id) {
  try {
    await axios.put(`/api/comments/${id}`, { content: editCommentText.value })
    const c = comments.value.find(c => c.id === id)
    if (c) c.content = editCommentText.value
    editingComment.value = null
  } catch {}
}

async function deleteComment(id) {
  if (!confirm('댓글을 삭제하시겠습니까?')) return
  try {
    await axios.delete(`/api/comments/${id}`)
    comments.value = comments.value.filter(c => c.id !== id)
    // 대댓글도 제거
    comments.value.forEach(c => { if (c.replies) c.replies = c.replies.filter(r => r.id !== id) })
    post.value.comment_count--
  } catch {}
}

async function submitReply(parentId) {
  if (!replyText.value.trim()) return
  try {
    const { data } = await axios.post('/api/comments', {
      commentable_type: 'post', commentable_id: post.value.id,
      content: replyText.value, parent_id: parentId
    })
    const parent = comments.value.find(c => c.id === parentId)
    if (parent) { if (!parent.replies) parent.replies = []; parent.replies.push(data.data) }
    replyText.value = ''; replyTo.value = null
    post.value.comment_count++
  } catch {}
}

onMounted(async () => {
  try {
    const { data } = await axios.get(`/api/posts/${route.params.id}`)
    post.value = data.data
    prev.value = data.prev || null
    next.value = data.next || null

    // 댓글 로딩
    try {
      const { data: cData } = await axios.get(`/api/comments/post/${route.params.id}`)
      comments.value = cData.data || []
    } catch {}
  } catch {}
  loading.value = false
})
</script>
