<template>
  <div class="min-h-screen bg-gray-50">
    <!-- 배너 -->
    <div class="bg-gradient-to-r from-blue-600 to-indigo-500 rounded-2xl shadow-lg text-white">
      <div class="max-w-[1200px] mx-auto px-6 py-5">
        <div class="flex items-center justify-between">
          <div>
            <div class="flex items-center gap-2 mb-1">
              <router-link to="/qa" class="text-blue-200 hover:text-white text-sm">&larr; Q&amp;A</router-link>
              <span class="text-blue-300">/</span>
              <span class="font-bold">{{ categoryInfo.name || slug }}</span>
            </div>
            <h1 class="text-xl font-black">{{ categoryInfo.name || slug }}</h1>
            <p v-if="categoryInfo.description" class="text-blue-100 mt-1 text-sm">{{ categoryInfo.description }}</p>
          </div>
          <router-link to="/qa/write"
            class="bg-white text-blue-600 px-5 py-2.5 rounded-xl font-bold text-sm hover:bg-blue-50 transition shadow">
            &#9999;&#65039; 질문하기
          </router-link>
        </div>
        <!-- 정렬 탭 -->
        <div class="flex gap-2 mt-4">
          <button v-for="s in sortOptions" :key="s.value"
            @click="currentSort = s.value; fetchQuestions()"
            class="px-3 py-1.5 rounded-lg text-xs font-medium transition"
            :class="currentSort === s.value ? 'bg-white text-blue-600' : 'bg-blue-500/30 text-blue-100 hover:bg-blue-500/50'">
            {{ s.label }}
          </button>
        </div>
      </div>
    </div>

    <div class="max-w-[1200px] mx-auto px-4 pt-4">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- 좌: 카테고리 사이드바 -->
        <div class="lg:col-span-2">
          <div class="bg-white rounded-2xl shadow-sm p-4 sticky top-20">
            <h3 class="font-bold text-gray-700 text-sm mb-3">&#128194; 카테고리</h3>
            <div class="space-y-1">
              <div @click="$router.push('/qa')"
                class="px-3 py-2 rounded-lg text-sm cursor-pointer text-gray-600 hover:bg-gray-50">
                전체
              </div>
              <div v-for="cat in categories" :key="cat.id"
                @click="$router.push('/qa/' + cat.slug)"
                class="px-3 py-2 rounded-lg text-sm cursor-pointer"
                :class="slug === cat.slug ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-600 hover:bg-gray-50'">
                {{ cat.name }}
                <span class="text-xs text-gray-400 ml-1">({{ cat.count || 0 }})</span>
              </div>
            </div>
          </div>
        </div>

        <!-- 중앙: 질문 목록 -->
        <div class="lg:col-span-7">
          <div class="space-y-3">
            <div v-if="loading" class="text-center py-12 text-gray-400">로딩 중...</div>
            <div v-else-if="questions.length === 0" class="text-center py-12 text-gray-400">
              이 카테고리에 질문이 없습니다.
            </div>
            <div v-for="q in questions" :key="q.id"
              @click="$router.push('/qa/' + slug + '/' + q.id)"
              class="bg-white rounded-xl shadow-sm p-4 hover:shadow-md transition cursor-pointer border border-transparent hover:border-blue-100">
              <div class="flex items-center gap-2 mb-2 flex-wrap">
                <span v-if="q.is_resolved"
                  class="px-2 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700">해결완료 &#10003;</span>
                <span v-else
                  class="px-2 py-0.5 rounded-full text-xs font-bold bg-orange-100 text-orange-600">미해결</span>
                <span v-if="q.category"
                  class="px-2 py-0.5 rounded-full text-xs bg-blue-50 text-blue-600">{{ q.category.name }}</span>
                <span v-if="q.point_reward > 0"
                  class="px-2 py-0.5 rounded-full text-xs font-bold bg-yellow-100 text-yellow-700">&#127942; {{ q.point_reward }}P</span>
              </div>
              <h3 class="font-bold text-gray-800 text-base mb-2 line-clamp-2">{{ q.title }}</h3>
              <div class="flex items-center gap-3 text-xs text-gray-400">
                <span>{{ q.user?.name || '익명' }}</span>
                <span>{{ timeAgo(q.created_at) }}</span>
                <span>&#128172; {{ q.answers_count || 0 }}</span>
                <span>&#128077; {{ q.recommend_count || 0 }}</span>
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
              <h3 class="font-bold text-white text-sm">&#127942; 이번 달 해결왕 TOP 10</h3>
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
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'

const route = useRoute()
const slug = ref(route.params.slug)
const categories = ref([])
const questions = ref([])
const leaderboard = ref([])
const categoryInfo = ref({})
const loading = ref(true)
const currentSort = ref('latest')
const pagination = ref({ current_page: 1, last_page: 1 })

const sortOptions = [
  { label: '최신순', value: 'latest' },
  { label: '답변많은순', value: 'answers' },
  { label: '해결완료순', value: 'resolved' },
  { label: '추천순', value: 'recommend' },
]

const token = localStorage.getItem('sk_token')
const headers = token ? { Authorization: 'Bearer ' + token } : {}

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

async function fetchQuestions(page = 1) {
  loading.value = true
  try {
    const resp = await axios.get('/api/qa-v2/' + slug.value + '/questions', {
      params: { sort: currentSort.value, page: page, per_page: 20 },
      headers: headers
    })
    const data = resp.data
    questions.value = data.data || data
    if (data.meta) pagination.value = data.meta
    else if (data.current_page) pagination.value = data
  } catch (e) { console.error(e) }
  loading.value = false
}

onMounted(async () => {
  try {
    const results = await Promise.all([
      axios.get('/api/qa-v2/categories', { headers: headers }),
      axios.get('/api/qa-v2/leaderboard', { params: { period: 'month' }, headers: headers })
    ])
    categories.value = results[0].data.data || results[0].data
    leaderboard.value = results[1].data.data || results[1].data
    const found = categories.value.find(function(c) { return c.slug === slug.value })
    categoryInfo.value = found || {}
  } catch (e) { console.error(e) }
  fetchQuestions()
})

watch(function() { return route.params.slug }, function(newSlug) {
  slug.value = newSlug
  const found = categories.value.find(function(c) { return c.slug === newSlug })
  categoryInfo.value = found || {}
  currentSort.value = 'latest'
  fetchQuestions()
})
</script>
