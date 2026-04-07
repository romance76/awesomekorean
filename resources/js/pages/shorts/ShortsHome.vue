<template>
<div class="fixed inset-0 bg-black z-40 flex flex-col">
  <!-- 상단 바 -->
  <div class="absolute top-0 left-0 right-0 z-50 flex items-center justify-between px-4 py-3">
    <RouterLink to="/" class="text-white text-sm font-bold opacity-80 hover:opacity-100">← 홈</RouterLink>
    <h1 class="text-white font-black text-sm">📱 숏츠</h1>
    <RouterLink v-if="auth.isLoggedIn" to="/shorts/upload" class="text-white text-sm opacity-80 hover:opacity-100">+ 업로드</RouterLink>
    <span v-else></span>
  </div>

  <!-- 메인 비디오 영역 -->
  <div v-if="loading" class="flex-1 flex items-center justify-center text-white">로딩중...</div>
  <div v-else-if="!shorts.length" class="flex-1 flex items-center justify-center text-white text-sm">숏츠가 없습니다</div>
  <div v-else class="flex-1 relative overflow-hidden">
    <!-- 현재 비디오 -->
    <div class="w-full h-full flex items-center justify-center">
      <div class="w-full max-w-md h-full max-h-[90vh] relative">
        <iframe
          :key="current.youtube_id"
          :src="`https://www.youtube.com/embed/${current.youtube_id}?autoplay=1&loop=1&controls=1&modestbranding=1`"
          class="w-full h-full rounded-xl"
          frameborder="0"
          allow="autoplay; encrypted-media"
          allowfullscreen
        ></iframe>

        <!-- 오른쪽 액션 버튼 -->
        <div class="absolute right-3 bottom-32 flex flex-col items-center gap-5">
          <button @click="toggleLike" class="flex flex-col items-center">
            <div class="w-10 h-10 bg-white/20 backdrop-blur rounded-full flex items-center justify-center text-xl">{{ liked ? '❤️' : '🤍' }}</div>
            <span class="text-white text-[10px] mt-1">{{ current.like_count }}</span>
          </button>
          <button @click="showComments=!showComments" class="flex flex-col items-center">
            <div class="w-10 h-10 bg-white/20 backdrop-blur rounded-full flex items-center justify-center text-xl">💬</div>
            <span class="text-white text-[10px] mt-1">{{ current.comment_count }}</span>
          </button>
          <button @click="shareShort" class="flex flex-col items-center">
            <div class="w-10 h-10 bg-white/20 backdrop-blur rounded-full flex items-center justify-center text-xl">🔗</div>
            <span class="text-white text-[10px] mt-1">공유</span>
          </button>
        </div>

        <!-- 하단 정보 -->
        <div class="absolute bottom-4 left-4 right-16">
          <div class="text-white font-bold text-sm drop-shadow">{{ current.title }}</div>
          <div class="text-white/70 text-xs mt-1">{{ current.user?.name || '익명' }}</div>
        </div>
      </div>
    </div>

    <!-- 위/아래 네비 버튼 -->
    <!-- 위/아래 버튼: 영상 바로 오른쪽 옆 -->
    <div class="absolute top-1/2 left-1/2 -translate-y-1/2 flex flex-col gap-3" style="margin-left: calc(224px + 16px);">
      <button @click="prev" :disabled="idx <= 0"
        class="w-10 h-10 bg-white/20 backdrop-blur rounded-full flex items-center justify-center text-white text-xl hover:bg-white/40 disabled:opacity-20 transition">
        ▲
      </button>
      <button @click="next" :disabled="idx >= shorts.length - 1"
        class="w-10 h-10 bg-white/20 backdrop-blur rounded-full flex items-center justify-center text-white text-xl hover:bg-white/40 disabled:opacity-20 transition">
        ▼
      </button>
    </div>

    <!-- 카운터 -->
    <div class="absolute bottom-4 right-4 text-white/50 text-xs">
      {{ idx + 1 }} / {{ shorts.length }}
    </div>
  </div>

  <!-- 댓글 패널 -->
  <div v-if="showComments" class="absolute bottom-0 left-0 right-0 bg-white rounded-t-2xl z-50 max-h-[50vh] overflow-y-auto shadow-xl">
    <div class="flex items-center justify-between px-4 py-3 border-b sticky top-0 bg-white">
      <span class="font-bold text-sm text-gray-800">💬 댓글</span>
      <button @click="showComments=false" class="text-gray-400">✕</button>
    </div>
    <div v-if="comments.length" class="divide-y">
      <div v-for="c in comments" :key="c.id" class="px-4 py-2.5">
        <div class="flex items-center gap-2 mb-0.5"><span class="text-xs font-semibold text-gray-700">{{ c.user?.name }}</span><span class="text-[10px] text-gray-400">{{ c.created_at?.slice(0,10) }}</span></div>
        <div class="text-sm text-gray-600">{{ c.content }}</div>
      </div>
    </div>
    <div v-else class="px-4 py-6 text-center text-sm text-gray-400">아직 댓글이 없습니다</div>
    <div v-if="auth.isLoggedIn" class="px-4 py-3 border-t flex gap-2 sticky bottom-0 bg-white">
      <input v-model="newComment" type="text" placeholder="댓글 입력..." class="flex-1 border rounded-full px-3 py-1.5 text-sm" @keyup.enter="submitComment" />
      <button @click="submitComment" class="bg-amber-400 text-amber-900 font-bold px-4 py-1.5 rounded-full text-sm">등록</button>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'

const auth = useAuthStore()
const shorts = ref([])
const idx = ref(0)
const loading = ref(true)
const liked = ref(false)
const showComments = ref(false)
const comments = ref([])
const newComment = ref('')

const current = computed(() => shorts.value[idx.value] || {})

function next() {
  if (idx.value < shorts.value.length - 1) { idx.value++; liked.value = false }
}
function prev() {
  if (idx.value > 0) { idx.value--; liked.value = false }
}

async function toggleLike() {
  if (!auth.isLoggedIn || !current.value.id) return
  try {
    const { data } = await axios.post(`/api/shorts/${current.value.id}/like`)
    liked.value = data.liked
    shorts.value[idx.value].like_count += data.liked ? 1 : -1
  } catch {}
}

function shareShort() {
  const url = `${window.location.origin}/shorts?v=${current.value.id}`
  if (navigator.share) { navigator.share({ title: current.value.title, url }) }
  else { navigator.clipboard.writeText(url); alert('링크가 복사되었습니다!') }
}

async function loadComments() {
  if (!current.value.id) return
  try { const { data } = await axios.get(`/api/comments/short/${current.value.id}`); comments.value = data.data || [] } catch { comments.value = [] }
}

async function submitComment() {
  if (!newComment.value.trim() || !current.value.id) return
  try {
    const { data } = await axios.post('/api/comments', { commentable_type: 'short', commentable_id: current.value.id, content: newComment.value })
    comments.value.push(data.data); newComment.value = ''
    shorts.value[idx.value].comment_count = (shorts.value[idx.value].comment_count || 0) + 1
  } catch {}
}

function onKeydown(e) {
  if (e.key === 'ArrowDown' || e.key === 'j') next()
  if (e.key === 'ArrowUp' || e.key === 'k') prev()
}

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/shorts?per_page=50')
    shorts.value = data.data?.data || []
  } catch {}
  loading.value = false
  window.addEventListener('keydown', onKeydown)
})

onUnmounted(() => { window.removeEventListener('keydown', onKeydown) })
</script>
