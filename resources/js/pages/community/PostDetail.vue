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
            <button @click="toggleBookmark" class="text-sm" :class="bookmarked ? 'text-amber-600' : 'text-gray-400 hover:text-amber-600'">🔖 {{ bookmarked ? '저장됨' : '북마크' }}</button>
            <button @click="sharePost" class="text-gray-400 text-sm hover:text-amber-600">🔗 공유</button>
            <button @click="showReport=true" class="text-gray-400 text-sm hover:text-red-400 ml-auto">🚨 신고</button>
            <!-- 작성자 전용: 수정/삭제 -->
            <template v-if="auth.user?.id === post.user_id">
              <RouterLink :to="`/community/write/${post.board?.slug}?edit=${post.id}`" class="text-gray-400 text-sm hover:text-amber-600">✏️ 수정</RouterLink>
              <button @click="deletePost" class="text-gray-400 text-sm hover:text-red-500">🗑️ 삭제</button>
            </template>
          </div>

          <!-- 신고 모달 -->
          <div v-if="showReport" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" @click.self="showReport=false">
            <div class="bg-white rounded-xl p-5 w-full max-w-sm shadow-xl">
              <h3 class="font-bold text-gray-800 mb-3">🚨 신고하기</h3>
              <select v-model="reportReason" class="w-full border rounded-lg px-3 py-2 text-sm mb-3">
                <option value="">신고 사유 선택</option>
                <option value="spam">스팸/광고</option>
                <option value="abuse">욕설/비방</option>
                <option value="inappropriate">부적절한 내용</option>
                <option value="fraud">사기/허위정보</option>
                <option value="other">기타</option>
              </select>
              <textarea v-model="reportContent" rows="3" placeholder="상세 사유 (선택)" class="w-full border rounded-lg px-3 py-2 text-sm resize-none mb-3"></textarea>
              <div class="flex gap-2">
                <button @click="submitReport" :disabled="!reportReason" class="bg-red-500 text-white font-bold px-4 py-2 rounded-lg text-sm flex-1 hover:bg-red-600 disabled:opacity-50">신고</button>
                <button @click="showReport=false" class="text-gray-500 px-4 py-2">취소</button>
              </div>
            </div>
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
              <template v-if="auth.user?.id === comment.user_id">
                <button v-if="editingComment!==comment.id" @click="startEditComment(comment)" class="text-[10px] text-gray-400 hover:text-amber-600 ml-auto">수정</button>
                <button @click="deleteComment(comment.id)" class="text-[10px] text-gray-400 hover:text-red-500">삭제</button>
              </template>
              <button v-if="auth.isLoggedIn" @click="replyTo=replyTo===comment.id?null:comment.id" class="text-[10px] text-gray-400 hover:text-amber-600" :class="{'ml-auto': auth.user?.id !== comment.user_id}">답글</button>
            </div>
            <!-- 댓글 수정 모드 -->
            <div v-if="editingComment===comment.id" class="flex gap-2 mt-1">
              <input v-model="editCommentText" class="flex-1 border rounded-lg px-2 py-1 text-sm" @keyup.enter="saveEditComment(comment.id)" />
              <button @click="saveEditComment(comment.id)" class="text-amber-600 text-xs font-bold">저장</button>
              <button @click="editingComment=null" class="text-gray-400 text-xs">취소</button>
            </div>
            <div v-else class="text-sm text-gray-600">{{ comment.content }}</div>
            <!-- 대댓글 입력 -->
            <div v-if="replyTo===comment.id" class="mt-2 flex gap-2">
              <input v-model="replyText" @keyup.enter="submitReply(comment.id)" type="text" placeholder="답글 입력..." class="flex-1 border rounded-lg px-2 py-1.5 text-xs" />
              <button @click="submitReply(comment.id)" class="text-amber-600 text-xs font-bold">등록</button>
            </div>
            <!-- 대댓글 목록 -->
            <div v-for="reply in (comment.replies || [])" :key="reply.id" class="ml-6 mt-2 pl-3 border-l-2 border-gray-100">
              <div class="flex items-center gap-2 mb-0.5">
                <span class="text-sm font-semibold text-gray-700">{{ reply.user?.name }}</span>
                <span class="text-xs text-gray-400">{{ formatDate(reply.created_at) }}</span>
                <template v-if="auth.user?.id === reply.user_id">
                  <button @click="deleteComment(reply.id)" class="text-[10px] text-gray-400 hover:text-red-500 ml-auto">삭제</button>
                </template>
              </div>
              <div class="text-sm text-gray-500">{{ reply.content }}</div>
            </div>
          </div>

          <div v-if="!comments.length" class="px-5 py-6 text-center text-sm text-gray-400">아직 댓글이 없습니다. 첫 댓글을 남겨보세요!</div>
        </div>
      </div>

      <!-- 사이드바 -->
      <div class="col-span-12 lg:col-span-3 hidden lg:block space-y-3">
        <!-- 작성자 정보 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
          <div class="font-bold text-sm text-amber-900 mb-3">✍️ 작성자 정보</div>
          <RouterLink :to="`/profile/${post.user?.id}`" class="flex items-center gap-2 hover:bg-amber-50 -mx-2 px-2 py-1 rounded-lg transition">
            <div class="w-8 h-8 bg-amber-100 rounded-full flex items-center justify-center text-sm font-bold text-amber-700">{{ (post.user?.name || '?')[0] }}</div>
            <div>
              <div class="text-sm text-gray-700 font-semibold">{{ post.user?.name }}</div>
              <div v-if="post.user?.bio" class="text-[10px] text-gray-400 truncate max-w-[120px]">{{ post.user.bio }}</div>
            </div>
          </RouterLink>
        </div>

        <!-- 공통 사이드바 위젯 -->
        <SidebarWidgets
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
import axios from 'axios'

const route = useRoute()
const auth = useAuthStore()
const router = useRouter()
const post = ref(null)
const comments = ref([])
const loading = ref(true)
const liked = ref(false)
const bookmarked = ref(false)
const newComment = ref('')
const showReport = ref(false)
const reportReason = ref('')
const reportContent = ref('')
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
    const { data } = await axios.post('/api/bookmarks', { bookmarkable_type: 'post', bookmarkable_id: post.value.id })
    bookmarked.value = data.bookmarked
  } catch {}
}

function sharePost() {
  const url = window.location.href
  if (navigator.share) {
    navigator.share({ title: post.value.title, url })
  } else {
    navigator.clipboard.writeText(url)
    alert('링크가 복사되었습니다!')
  }
}

async function submitReport() {
  if (!reportReason.value) return
  try {
    await axios.post('/api/reports', {
      reportable_type: 'post', reportable_id: post.value.id,
      reason: reportReason.value, content: reportContent.value
    })
    showReport.value = false; reportReason.value = ''; reportContent.value = ''
    alert('신고가 접수되었습니다')
  } catch {}
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

    // 댓글 로딩
    try {
      const { data: cData } = await axios.get(`/api/comments/post/${route.params.id}`)
      comments.value = cData.data || []
    } catch {}
  } catch {}
  loading.value = false
})
</script>
