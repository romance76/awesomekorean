<template>
  <div class="max-w-[1200px] mx-auto px-4 pt-4">

    <!-- 상단 배너 (커뮤니티와 동일 스타일) -->
    <div class="bg-gradient-to-r from-blue-600 to-indigo-500 rounded-2xl px-6 py-5 mb-8 shadow-lg">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-white text-xl font-black mb-1">❓ Q&A 지식인</h1>
          <p class="text-blue-100 text-sm">궁금한 것을 물어보고, 아는 것을 나눠주세요</p>
        </div>
        <router-link to="/qa/write"
          class="bg-white text-blue-600 px-5 py-2.5 rounded-xl text-sm font-bold hover:bg-blue-50 transition flex items-center gap-1.5 shadow">
          ✏️ 질문하기
        </router-link>
      </div>
    </div>

    <div>
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- 좌: 카테고리 사이드바 -->
        <div class="lg:col-span-2">
          <div class="bg-white rounded-2xl shadow-sm p-4 sticky top-20">
            <h3 class="font-bold text-gray-700 text-sm mb-3">📂 카테고리</h3>
            <div class="space-y-1">
              <div @click="$router.push('/qa')"
                class="px-3 py-2 rounded-lg text-sm cursor-pointer font-medium"
                :class="!currentSlug ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50'">
                전체
              </div>
              <div v-for="cat in categories" :key="cat.id"
                @click="$router.push('/qa/' + cat.slug)"
                class="px-3 py-2 rounded-lg text-sm cursor-pointer"
                :class="currentSlug === cat.slug ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-600 hover:bg-gray-50'">
                {{ cat.name }}
                <span class="text-xs text-gray-400 ml-1">({{ cat.count || 0 }})</span>
              </div>
            </div>
          </div>
        </div>

        <!-- 중앙: 최신 질문 -->
        <div class="lg:col-span-7">
          <div class="space-y-3">
            <div v-if="loading" class="text-center py-12 text-gray-400">로딩 중...</div>
            <div v-else-if="questions.length === 0" class="text-center py-12 text-gray-400">
              아직 질문이 없습니다. 첫 질문을 올려보세요!
            </div>
            <div v-for="q in questions" :key="q.id"
              @click="goQuestion(q)"
              class="bg-white rounded-xl shadow-sm p-4 hover:shadow-md transition cursor-pointer border border-transparent hover:border-blue-100">
              <div class="flex items-center gap-2 mb-2 flex-wrap">
                <span v-if="q.is_resolved"
                  class="px-2 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700">해결완료 ✓</span>
                <span v-else
                  class="px-2 py-0.5 rounded-full text-xs font-bold bg-orange-100 text-orange-600">미해결</span>
                <span v-if="q.category"
                  class="px-2 py-0.5 rounded-full text-xs bg-blue-50 text-blue-600">{{ q.category.name }}</span>
                <span v-if="q.point_reward > 0"
                  class="px-2 py-0.5 rounded-full text-xs font-bold bg-yellow-100 text-yellow-700">🏆 {{ q.point_reward }}P</span>
              </div>
              <h3 class="font-bold text-gray-800 text-base mb-2 line-clamp-2">{{ q.title }}</h3>
              <div class="flex items-center gap-3 text-xs text-gray-400">
                <span>{{ q.user?.name || '익명' }}</span>
                <span>{{ timeAgo(q.created_at) }}</span>
                <span>💬 {{ q.answers_count || 0 }}</span>
                <span>👍 {{ q.recommend_count || 0 }}</span>
              </div>
            </div>
          </div>
          <!-- 페이지네이션 -->
          <div v-if="pagination.last_page > 1" class="flex justify-center mt-6 gap-1">
            <button v-for="p in pagination.last_page" :key="p"
              @click="fetchQuestions(p)"
              class="w-8 h-8 rounded-lg text-sm"
              :class="p === pagination.current_page ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-100'">
              {{ p }}
            </button>
          </div>
        </div>

        <!-- 우: 해결왕 TOP 10 -->
        <div class="lg:col-span-3">
          <div class="bg-white rounded-2xl shadow-sm overflow-hidden sticky top-20">
            <div class="bg-gradient-to-r from-yellow-400 to-orange-400 px-4 py-3">
              <h3 class="font-bold text-white text-sm">🏆 이번 달 해결왕 TOP 10</h3>
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
                  <p class="text-xs text-gray-400">{{ user.title || '' }} · {{ user.accepted_count }}회 채택</p>
                </div>
              </div>
              <div v-if="leaderboard.length === 0" class="text-center py-4 text-xs text-gray-400">아직 데이터가 없습니다</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const categories = ref([])
const questions = ref([])
const leaderboard = ref([])
const loading = ref(true)
const currentSlug = ref(null)
const pagination = ref({ current_page: 1, last_page: 1 })

const token = localStorage.getItem('sk_token')
const headers = token ? { Authorization: `Bearer ${token}` } : {}

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

function goQuestion(q) {
  const slug = q.category?.slug || 'general'
  router.push('/qa/' + slug + '/' + q.id)
}

async function fetchQuestions(page = 1) {
  loading.value = true
  try {
    const { data } = await axios.get('/api/qa-v2/questions', { params: { page, per_page: 20 }, headers })
    questions.value = data.data || data
    if (data.meta) pagination.value = data.meta
    else if (data.current_page) pagination.value = data
  } catch (e) { console.error(e) }
  loading.value = false
}

onMounted(async () => {
  try {
    const [catRes, lbRes] = await Promise.all([
      axios.get('/api/qa-v2/categories', { headers }),
      axios.get('/api/qa-v2/leaderboard', { params: { period: 'month' }, headers })
    ])
    categories.value = catRes.data.data || catRes.data
    leaderboard.value = lbRes.data.data || lbRes.data
  } catch (e) { console.error(e) }
  fetchQuestions()
})
</script>
