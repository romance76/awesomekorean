<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <div class="max-w-[1200px] mx-auto px-4 pt-4">
    <!-- 헤더 -->
    <div class="bg-gradient-to-r from-blue-600 to-indigo-500 text-white px-6 py-4 rounded-2xl mb-4">
      <div class="flex items-center justify-between mb-2">
        <div class="flex items-center gap-2">
          <router-link to="/qa" class="text-blue-200 text-sm hover:text-white transition">&larr; Q&amp;A</router-link>
          <span v-if="question.category" class="bg-white/20 text-xs px-3 py-1 rounded-full">{{ question.category.name }}</span>
          <span v-if="question.is_resolved" class="bg-green-400/30 text-xs px-3 py-1 rounded-full font-bold">해결완료</span>
          <span v-else class="bg-orange-400/30 text-xs px-3 py-1 rounded-full font-bold">미해결</span>
          <span v-if="question.point_reward > 0" class="bg-yellow-400/30 text-xs px-3 py-1 rounded-full font-bold">{{ question.point_reward }}P</span>
        </div>
        <div class="flex items-center gap-2">
          <button @click="shareQuestion" class="flex items-center gap-1 text-xs text-white/80 hover:text-white bg-white/10 hover:bg-white/20 px-3 py-1.5 rounded-lg transition">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
            공유
          </button>
          <button class="flex items-center gap-1 text-xs text-white/80 hover:text-white bg-white/10 hover:bg-white/20 px-3 py-1.5 rounded-lg transition">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
            북마크
          </button>
        </div>
      </div>
      <h1 class="text-lg sm:text-xl font-black leading-tight">{{ question.title }}</h1>
      <div class="flex items-center gap-2 mt-2 text-sm text-blue-100">
        <span class="font-medium">{{ question.user?.name || '익명' }}</span>
        <span>·</span>
        <span>{{ formatDate(question.created_at) }}</span>
        <span>·</span>
        <span>조회 {{ question.views || 0 }}</span>
        <span>·</span>
        <span>추천 {{ question.recommend_count || 0 }}</span>
      </div>
    </div>

    <!-- 2컬럼 레이아웃 -->
    <div class="flex gap-5 items-start">
      <!-- 좌: 질문 + 답변 -->
      <div class="flex-1 min-w-0">
          <!-- 질문 본문 -->
          <div class="bg-white rounded-2xl shadow-sm p-6 mb-4">
            <div class="prose max-w-none text-gray-700 whitespace-pre-wrap" v-html="question.content"></div>
            <div class="flex items-center gap-3 mt-6 pt-4 border-t">
              <button @click="recommendQuestion"
                class="flex items-center gap-1 px-4 py-2 rounded-lg text-sm font-medium transition"
                :class="question.is_recommended ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-600 hover:bg-blue-50'">
                &#128077; 추천 {{ question.recommend_count || 0 }}
              </button>
            </div>
          </div>

          <!-- 답변 목록 -->
          <div class="mb-4">
            <h3 class="font-bold text-gray-800 text-lg mb-3">&#128172; 답변 {{ answers.length }}개</h3>

            <!-- 채택된 답변 먼저 -->
            <div v-for="ans in sortedAnswers" :key="ans.id"
              class="bg-white rounded-2xl shadow-sm p-5 mb-3"
              :class="ans.is_accepted ? 'border-2 border-green-400 ring-2 ring-green-100' : ''">
              <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                  <span class="font-bold text-gray-800 text-sm">{{ ans.user?.name || '익명' }}</span>
                  <span v-if="ans.user?.title" class="text-xs px-2 py-0.5 rounded-full bg-purple-50 text-purple-600">{{ ans.user.title }}</span>
                  <span v-if="ans.is_accepted" class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-bold">&#9989; 채택</span>
                </div>
                <span class="text-xs text-gray-400">{{ timeAgo(ans.created_at) }}</span>
              </div>
              <div class="prose max-w-none text-gray-700 whitespace-pre-wrap text-sm" v-html="ans.content"></div>
              <div class="flex items-center gap-3 mt-4 pt-3 border-t">
                <button @click="likeAnswer(ans)"
                  class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium transition"
                  :class="ans.is_liked ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-500 hover:bg-blue-50'">
                  &#128077; {{ ans.likes_count || 0 }}
                </button>
                <!-- 채택 버튼: 질문자 본인만, 미해결일 때만, 자기 답변 제외 -->
                <button v-if="canAccept && !question.is_resolved && ans.user_id !== currentUserId"
                  @click="acceptAnswer(ans)"
                  class="px-3 py-1.5 rounded-lg text-xs font-bold bg-green-100 text-green-700 hover:bg-green-200 transition">
                  &#10003; 채택하기
                </button>
              </div>
            </div>

            <div v-if="answers.length === 0" class="text-center py-8 text-gray-400 text-sm">
              아직 답변이 없습니다. 첫 답변을 남겨보세요!
            </div>
          </div>

          <!-- 답변 작성 폼 -->
          <div class="bg-white rounded-2xl shadow-sm p-5">
            <h3 class="font-bold text-gray-800 mb-3">&#9999;&#65039; 답변 작성</h3>
            <div v-if="!isLoggedIn" class="text-center py-6 text-gray-400 text-sm">
              <router-link to="/auth/login" class="text-blue-600 hover:underline">로그인</router-link> 후 답변을 작성할 수 있습니다.
            </div>
            <div v-else>
              <textarea v-model="answerContent" rows="5"
                class="w-full border border-gray-200 rounded-xl p-3 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                placeholder="답변을 작성해주세요..."></textarea>
              <div class="flex justify-end mt-3">
                <button @click="submitAnswer" :disabled="submitting || !answerContent.trim()"
                  class="px-5 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-bold hover:bg-blue-700 transition disabled:opacity-50">
                  {{ submitting ? '등록 중...' : '답변 등록' }}
                </button>
              </div>
            </div>
          </div>
        </div>

      <!-- 우: 사이드바 -->
      <div class="hidden lg:block flex-shrink-0 sticky top-4" style="width:320px">
        <!-- 해결왕 TOP 10 -->
        <div class="bg-white rounded-2xl shadow-sm flex flex-col overflow-hidden mb-4">
          <div class="bg-gradient-to-r from-yellow-400 to-orange-400 px-4 py-3">
            <h3 class="font-bold text-white text-sm">이번 달 해결왕 TOP 10</h3>
          </div>
          <div class="p-3 space-y-2">
            <div v-for="(user, idx) in leaderboard" :key="user.id"
              @click="$router.push('/user/'+user.id+'/resolved')"
              class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-50 cursor-pointer">
              <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-black"
                :class="idx < 3 ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-500'">
                {{ idx + 1 }}
              </span>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-800 truncate">{{ user.name }}</p>
                <p class="text-xs text-gray-400">{{ user.title || '' }} &middot; {{ user.accepted_count }}회 채택</p>
              </div>
            </div>
            <div v-if="leaderboard.length === 0" class="text-center py-4 text-xs text-gray-400">아직 데이터가 없습니다</div>
          </div>
        </div>

        <!-- 같은 카테고리 질문 -->
        <div class="bg-white rounded-2xl shadow-sm flex flex-col overflow-hidden">
          <h3 class="flex-shrink-0 font-bold text-gray-800 text-sm px-4 py-3 border-b border-gray-100">같은 카테고리 질문</h3>
          <div>
            <div v-if="relatedQuestions.length">
              <div v-for="rq in relatedQuestions" :key="rq.id"
                @click="$router.push('/qa/' + (question.category?.slug || 'general') + '/' + rq.id)"
                class="flex gap-3 py-3 px-3 cursor-pointer hover:bg-blue-50/40 transition border-b border-gray-50 last:border-0">
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-gray-800 line-clamp-2 leading-snug">{{ rq.title }}</p>
                  <div class="flex items-center gap-1.5 mt-1">
                    <span class="text-[11px] text-gray-400">답변 {{ rq.answers_count || 0 }}</span>
                    <span class="text-gray-300 text-[10px]">&middot;</span>
                    <span class="text-[11px] text-gray-400">{{ timeAgo(rq.created_at) }}</span>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="text-center py-10 text-gray-400 text-sm">관련 질문이 없습니다</div>
          </div>
        </div>
      </div><!-- /사이드바 -->
    </div><!-- /2컬럼 -->
    </div><!-- /max-w -->
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'

const route = useRoute()
const router = useRouter()
const questionId = ref(route.params.id)

const question = ref({})
const answers = ref([])
const leaderboard = ref([])
const relatedQuestions = ref([])
const answerContent = ref('')
const submitting = ref(false)

const token = localStorage.getItem('sk_token')
const headers = token ? { Authorization: 'Bearer ' + token } : {}
const isLoggedIn = !!token

let currentUserId = null
try {
  const userData = localStorage.getItem('sk_user')
  if (userData) currentUserId = JSON.parse(userData).id
} catch (e) {}

const canAccept = computed(function() {
  return isLoggedIn && question.value.user_id === currentUserId
})

const sortedAnswers = computed(function() {
  const arr = [...answers.value]
  arr.sort(function(a, b) {
    if (a.is_accepted && !b.is_accepted) return -1
    if (!a.is_accepted && b.is_accepted) return 1
    return 0
  })
  return arr
})

function timeAgo(dateStr) {
  if (!dateStr) return ''
  const diff = Date.now() - new Date(dateStr).getTime()
  const m = Math.floor(diff / 60000)
  if (m < 1) return '방금'
  if (m < 60) return m + '분 전'
  const h = Math.floor(m / 60)
  if (h < 24) return h + '시간 전'
  const d = Math.floor(h / 24)
  if (d < 30) return d + '일 전'
  return new Date(dateStr).toLocaleDateString('ko-KR')
}

function shareQuestion() {
  if (navigator.share) {
    navigator.share({ title: question.value.title, url: window.location.href })
  } else {
    navigator.clipboard.writeText(window.location.href)
    alert('링크가 복사되었습니다!')
  }
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleDateString('ko-KR', { year: 'numeric', month: 'long', day: 'numeric' })
}

async function fetchQuestion() {
  try {
    const resp = await axios.get('/api/qa-v2/questions/' + questionId.value, { headers: headers })
    question.value = resp.data.data || resp.data
    if (question.value.answers) {
      answers.value = question.value.answers
    }
  } catch (e) {
    console.error(e)
    if (e.response && e.response.status === 404) router.push('/qa')
  }
}

async function fetchAnswers() {
  try {
    const resp = await axios.get('/api/qa-v2/questions/' + questionId.value + '/answers', { headers: headers })
    answers.value = resp.data.data || resp.data
  } catch (e) { console.error(e) }
}

async function recommendQuestion() {
  if (!isLoggedIn) return router.push('/auth/login')
  try {
    const resp = await axios.post('/api/qa-v2/questions/' + questionId.value + '/recommend', {}, { headers: headers })
    question.value.recommend_count = resp.data.recommend_count
    question.value.is_recommended = resp.data.recommended
  } catch (e) { console.error(e) }
}

async function likeAnswer(ans) {
  if (!isLoggedIn) return router.push('/auth/login')
  try {
    const resp = await axios.post('/api/qa-v2/answers/' + ans.id + '/like', {}, { headers: headers })
    ans.likes_count = resp.data.like_count
    ans.is_liked = resp.data.liked
  } catch (e) { console.error(e) }
}

async function acceptAnswer(ans) {
  if (!confirm('이 답변을 채택하시겠습니까? 채택 후에는 변경할 수 없습니다.')) return
  try {
    await axios.post('/api/qa-v2/answers/' + ans.id + '/accept', {}, { headers: headers })
    ans.is_accepted = true
    question.value.is_resolved = true
  } catch (e) {
    console.error(e)
    alert(e.response?.data?.message || '채택에 실패했습니다.')
  }
}

async function submitAnswer() {
  if (!answerContent.value.trim()) return
  submitting.value = true
  try {
    const resp = await axios.post('/api/qa-v2/questions/' + questionId.value + '/answers', {
      content: answerContent.value
    }, { headers: headers })
    const newAnswer = resp.data.data || resp.data
    answers.value.push(newAnswer)
    answerContent.value = ''
  } catch (e) {
    console.error(e)
    alert(e.response?.data?.message || '답변 등록에 실패했습니다.')
  }
  submitting.value = false
}

onMounted(async () => {
  await fetchQuestion()
  fetchAnswers()
  try {
    const lbResp = await axios.get('/api/qa-v2/leaderboard', { params: { period: 'month' }, headers: headers })
    leaderboard.value = lbResp.data.data || lbResp.data
  } catch (e) { console.error(e) }
  // 같은 카테고리 질문
  if (question.value.category) {
    try {
      const resp = await axios.get('/api/qa-v2/' + question.value.category.slug + '/questions', {
        params: { per_page: 5 },
        headers: headers
      })
      const data = resp.data.data || resp.data
      relatedQuestions.value = data.filter(function(q) { return q.id !== Number(questionId.value) }).slice(0, 5)
    } catch (e) { console.error(e) }
  }
})
</script>
