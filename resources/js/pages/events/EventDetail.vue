<template>
  <div class="max-w-[1200px] mx-auto px-4 py-6 pb-20">
    <!-- 상단 네비게이션 -->
    <div class="flex items-center justify-between mb-4">
      <button @click="$router.back()" class="flex items-center gap-1 text-sm text-gray-500 hover:text-red-600">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        뒤로
      </button>
      <div class="flex items-center gap-2">
        <button @click="shareEvent" class="flex items-center gap-1 text-sm text-gray-500 hover:text-red-600 px-3 py-1.5 rounded-lg border border-gray-200 hover:border-red-200 transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
          공유
        </button>
        <button @click="toggleBookmark" :class="['flex items-center gap-1 text-sm px-3 py-1.5 rounded-lg border transition', event?.is_bookmarked ? 'text-yellow-600 border-yellow-300 bg-yellow-50' : 'text-gray-500 border-gray-200 hover:border-yellow-300']">
          <svg class="w-4 h-4" :fill="event?.is_bookmarked ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
          북마크
        </button>
      </div>
    </div>

    <div v-if="loading" class="text-center py-20 text-gray-400">불러오는 중...</div>

    <template v-else-if="event">
      <!-- Header -->
      <div class="bg-white rounded-2xl shadow-sm overflow-hidden mb-4">
        <div class="bg-gradient-to-br from-blue-500 to-indigo-600 p-6 text-white">
          <div class="flex items-center gap-2 mb-2">
            <span class="text-xs bg-white/20 px-2 py-0.5 rounded-full">{{ categoryLabel(event.category) }}</span>
            <span v-if="event.is_online" class="text-xs bg-green-400/30 px-2 py-0.5 rounded-full">🌐 온라인</span>
            <span v-if="isPastEvent" class="text-xs bg-red-400/40 px-2 py-0.5 rounded-full">종료됨</span>
          </div>
          <h1 class="text-xl font-black leading-tight">{{ event.title }}</h1>
          <div class="flex flex-wrap items-center gap-3 mt-3 text-blue-100 text-sm">
            <span>📅 {{ formatDate(event.event_date) }}</span>
            <span v-if="event.end_date">~ {{ formatDate(event.end_date) }}</span>
            <span v-if="event.location">📍 {{ event.location }}</span>
          </div>
        </div>

        <div class="p-5">
          <!-- 이벤트 상세 정보 -->
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
            <div class="text-center p-3 bg-blue-50 rounded-xl">
              <div class="text-2xl font-black text-blue-600">{{ event.attendee_count }}</div>
              <div class="text-xs text-gray-500 mt-0.5">참가자</div>
            </div>
            <div v-if="event.max_attendees" class="text-center p-3 bg-gray-50 rounded-xl">
              <div class="text-2xl font-black text-gray-500">{{ event.max_attendees }}</div>
              <div class="text-xs text-gray-500 mt-0.5">최대 정원</div>
            </div>
            <div class="text-center p-3 bg-green-50 rounded-xl">
              <div class="text-2xl font-black text-green-600" v-if="event.price">${{ Number(event.price).toLocaleString() }}</div>
              <div class="text-xl font-black text-green-600" v-else>무료</div>
              <div class="text-xs text-gray-500 mt-0.5">참가비</div>
            </div>
            <div class="text-center p-3 bg-purple-50 rounded-xl">
              <div class="text-lg font-black text-purple-600">{{ daysUntilEvent }}</div>
              <div class="text-xs text-gray-500 mt-0.5">{{ isPastEvent ? '종료됨' : '남은 일수' }}</div>
            </div>
          </div>

          <!-- 참가 버튼 -->
          <div class="flex items-center justify-between mb-4">
            <div></div>
            <button v-if="auth.isLoggedIn"
              @click="toggleAttend"
              :disabled="attendLoading || (event.max_attendees && event.attendee_count >= event.max_attendees && !event.is_attending)"
              class="px-6 py-2.5 rounded-xl font-semibold text-sm transition disabled:opacity-50"
              :class="event.is_attending
                ? 'bg-red-50 text-red-500 border border-red-200 hover:bg-red-100'
                : 'bg-blue-600 text-white hover:bg-blue-700'">
              {{ attendLoading ? '...' : event.is_attending ? '참가 취소' : '참가 신청' }}
            </button>
            <RouterLink v-else to="/auth/login"
              class="px-6 py-2.5 rounded-xl font-semibold text-sm bg-blue-600 text-white hover:bg-blue-700">
              로그인 후 참가
            </RouterLink>
          </div>

          <!-- 정원 바 -->
          <div v-if="event.max_attendees" class="mb-4">
            <div class="flex justify-between text-xs text-gray-400 mb-1">
              <span>참가율</span>
              <span>{{ event.attendee_count }}/{{ event.max_attendees }}명</span>
            </div>
            <div class="bg-gray-100 rounded-full h-2">
              <div class="bg-blue-500 rounded-full h-2 transition-all"
                :style="{ width: Math.min(100, (event.attendee_count / event.max_attendees) * 100) + '%' }"></div>
            </div>
            <p v-if="event.attendee_count >= event.max_attendees" class="text-xs text-red-500 mt-1">정원이 마감되었습니다.</p>
          </div>

          <!-- 주최자 정보 -->
          <div class="flex items-center gap-3 py-3 border-t border-gray-100">
            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-sm font-bold text-blue-600">
              {{ (event.user?.name || '?')[0] }}
            </div>
            <div class="flex-1">
              <div class="text-sm font-medium text-gray-700">{{ event.user?.name }}</div>
              <div class="text-xs text-gray-400">주최자</div>
            </div>
            <div v-if="canEdit" class="flex space-x-2">
              <router-link :to="`/events/write?edit=${event.id}`" class="text-xs text-gray-400 hover:text-gray-600">수정</router-link>
              <button @click="deleteEvent" class="text-xs text-red-400 hover:text-red-600">삭제</button>
            </div>
          </div>
        </div>
      </div>

      <!-- 참가자 목록 -->
      <div v-if="event.attendees?.length" class="bg-white rounded-2xl shadow-sm p-5 mb-4">
        <h2 class="text-sm font-bold text-gray-700 mb-3">참가자 {{ event.attendee_count }}명</h2>
        <div class="flex flex-wrap gap-2">
          <div v-for="attendee in event.attendees" :key="attendee.id"
            class="flex items-center gap-2 bg-gray-50 rounded-full px-3 py-1.5">
            <div class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center text-xs font-bold text-blue-600">
              {{ (attendee.name || '?')[0] }}
            </div>
            <span class="text-sm text-gray-700">{{ attendee.name }}</span>
          </div>
        </div>
      </div>

      <!-- 설명 -->
      <div v-if="event.description" class="bg-white rounded-2xl shadow-sm p-5 mb-4">
        <h2 class="text-sm font-bold text-gray-700 mb-3">이벤트 소개</h2>
        <p class="text-sm text-gray-600 leading-relaxed whitespace-pre-line">{{ event.description }}</p>
      </div>

      <!-- 지도 -->
      <div v-if="event.latitude && event.longitude" class="bg-white rounded-2xl shadow-sm overflow-hidden mb-4">
        <div class="px-5 py-3 border-b border-gray-100">
          <h2 class="text-sm font-bold text-gray-700">📍 장소</h2>
        </div>
        <iframe
          :src="`https://maps.google.com/maps?q=${event.latitude},${event.longitude}&output=embed`"
          class="w-full h-[300px] border-0"
          allowfullscreen
          loading="lazy"
        ></iframe>
        <div class="px-5 py-3 text-sm text-gray-600">
          {{ event.location }}<span v-if="event.region">, {{ event.region }}</span>
        </div>
      </div>
      <!-- 지도 없을 때 위치만 표시 -->
      <div v-else-if="event.location" class="bg-white rounded-2xl shadow-sm p-5 mb-4">
        <h2 class="text-sm font-bold text-gray-700 mb-2">📍 장소</h2>
        <div class="text-sm text-gray-600">
          {{ event.location }}<span v-if="event.region">, {{ event.region }}</span>
        </div>
      </div>

      <!-- 좋아요 버튼 -->
      <div class="flex justify-center gap-3 mb-6">
        <button @click="toggleLike"
          :class="['flex items-center space-x-2 px-6 py-2.5 rounded-full border-2 transition font-medium text-sm',
            event.is_liked ? 'border-red-500 bg-red-50 text-red-600' : 'border-gray-200 text-gray-500 hover:border-red-300']">
          <span>{{ event.is_liked ? '❤️' : '🤍' }}</span>
          <span>관심 {{ event.like_count || 0 }}</span>
        </button>
      </div>

      <!-- 댓글 섹션 -->
      <div class="bg-white rounded-2xl shadow-sm overflow-hidden mb-4">
        <div class="px-5 py-3 border-b border-gray-100">
          <h3 class="font-semibold text-gray-800 text-sm">💬 댓글 {{ event.comments?.length || 0 }}개</h3>
        </div>
        <ul v-if="event.comments?.length">
          <li v-for="comment in event.comments" :key="comment.id" class="px-5 py-3.5 border-b border-gray-50 last:border-0">
            <div class="flex items-start justify-between">
              <div class="flex items-start space-x-2 flex-1">
                <div class="w-7 h-7 bg-gray-100 rounded-full flex items-center justify-center text-gray-500 text-xs font-bold flex-shrink-0 mt-0.5">
                  {{ (comment.user?.name || comment.user_name || '익명')[0] }}
                </div>
                <div class="flex-1">
                  <div class="flex items-center space-x-2 mb-1">
                    <span class="text-sm font-medium text-gray-800">{{ comment.user?.name || comment.user_name || '익명' }}</span>
                    <span v-if="comment.user_id === event.user_id" class="text-xs bg-blue-50 text-blue-500 px-1.5 py-0.5 rounded">주최자</span>
                    <span class="text-xs text-gray-400">{{ formatRelative(comment.created_at) }}</span>
                  </div>
                  <p class="text-sm text-gray-700">{{ comment.content }}</p>
                </div>
              </div>
              <button v-if="auth.user?.id === comment.user?.id || auth.user?.is_admin"
                @click="deleteComment(comment.id)" class="text-xs text-gray-300 hover:text-red-400 ml-2">삭제</button>
            </div>
          </li>
        </ul>
        <div v-else class="px-5 py-8 text-center text-sm text-gray-400">아직 댓글이 없습니다.</div>

        <!-- 댓글 입력 -->
        <div v-if="auth.isLoggedIn" class="px-5 py-3 bg-gray-50 border-t border-gray-100">
          <div class="flex space-x-2">
            <input v-model="commentText" @keyup.enter.ctrl="submitComment" type="text" placeholder="댓글을 입력하세요 (Ctrl+Enter)"
              class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            <button @click="submitComment" :disabled="!commentText.trim()"
              class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-40">등록</button>
          </div>
        </div>
        <div v-else class="px-5 py-3 bg-gray-50 border-t border-gray-100 text-center">
          <router-link to="/auth/login" class="text-blue-600 text-sm hover:underline">로그인 후 댓글을 작성할 수 있습니다</router-link>
        </div>
      </div>

      <router-link to="/events" class="text-sm text-gray-500 hover:text-blue-600">← 이벤트 목록으로</router-link>
    </template>

    <div v-else class="text-center py-20 text-gray-400">이벤트를 찾을 수 없습니다.</div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'

const route = useRoute()
const router = useRouter()
const auth  = useAuthStore()

const event         = ref(null)
const loading       = ref(true)
const attendLoading = ref(false)
const commentText   = ref('')

const canEdit = computed(() =>
  auth.user && (auth.user.id === event.value?.user_id || auth.user.is_admin)
)

const isPastEvent = computed(() => {
  if (!event.value?.event_date) return false
  return new Date(event.value.event_date) < new Date()
})

const daysUntilEvent = computed(() => {
  if (!event.value?.event_date) return '-'
  const diff = Math.ceil((new Date(event.value.event_date) - new Date()) / (1000 * 60 * 60 * 24))
  if (diff < 0) return `${Math.abs(diff)}일 전`
  if (diff === 0) return 'D-Day'
  return `D-${diff}`
})

const categoryLabels = {
  general: '일반', meetup: '모임', food: '음식', culture: '문화',
  sports: '스포츠', education: '교육', business: '비즈니스',
}

function categoryLabel(c) { return categoryLabels[c] ?? c }

function formatDate(dt) {
  if (!dt) return ''
  return new Date(dt).toLocaleDateString('ko-KR', {
    year: 'numeric', month: 'long', day: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

function formatRelative(d) {
  const date = new Date(d)
  const now = new Date()
  const diff = (now - date) / 1000
  if (diff < 60) return '방금 전'
  if (diff < 3600) return `${Math.floor(diff/60)}분 전`
  if (diff < 86400) return `${Math.floor(diff/3600)}시간 전`
  return date.toLocaleDateString('ko-KR')
}

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get(`/api/events/${route.params.id}`)
    event.value = data
  } catch { event.value = null }
  finally { loading.value = false }
}

async function toggleAttend() {
  if (!auth.isLoggedIn) return
  attendLoading.value = true
  try {
    const { data } = await axios.post(`/api/events/${event.value.id}/attend`)
    event.value.is_attending   = data.attending
    event.value.attendee_count = data.attendee_count
    if (data.attendees) event.value.attendees = data.attendees
  } catch (e) {
    alert(e.response?.data?.message ?? '오류가 발생했습니다.')
  } finally {
    attendLoading.value = false
  }
}

async function toggleLike() {
  if (!auth.isLoggedIn) { router.push('/auth/login'); return }
  try {
    const { data } = await axios.post(`/api/events/${event.value.id}/like`)
    event.value.is_liked = data.liked
    event.value.like_count = data.like_count
  } catch {}
}

async function toggleBookmark() {
  if (!auth.isLoggedIn) { router.push('/auth/login'); return }
  try {
    const { data } = await axios.post(`/api/events/${event.value.id}/bookmark`)
    event.value.is_bookmarked = data.bookmarked
  } catch {}
}

function shareEvent() {
  const url = window.location.href
  if (navigator.share) {
    navigator.share({ title: event.value.title, url })
  } else if (navigator.clipboard) {
    navigator.clipboard.writeText(url)
    alert('링크가 복사되었습니다.')
  }
}

async function submitComment() {
  if (!commentText.value.trim()) return
  try {
    const { data } = await axios.post(`/api/events/${event.value.id}/comments`, { content: commentText.value })
    if (!event.value.comments) event.value.comments = []
    event.value.comments.push(data.comment)
    commentText.value = ''
  } catch (e) {
    alert(e.response?.data?.message || '댓글 등록 실패')
  }
}

async function deleteComment(id) {
  if (!confirm('댓글을 삭제하시겠습니까?')) return
  try {
    await axios.delete(`/api/comments/${id}`)
    event.value.comments = event.value.comments.filter(c => c.id !== id)
  } catch {}
}

async function deleteEvent() {
  if (!confirm('이벤트를 삭제하시겠습니까?')) return
  try {
    await axios.delete(`/api/events/${event.value.id}`)
    router.push('/events')
  } catch {}
}

onMounted(load)
</script>
