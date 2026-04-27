<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <button @click="$router.back()" class="text-sm text-gray-500 hover:text-amber-600 mb-3">← Q&A 목록</button>
    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="qa" class="grid grid-cols-12 gap-4">
      <!-- 왼쪽: 카테고리 -->
      <div class="col-span-12 lg:col-span-2 hidden lg:block">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden sticky top-20">
          <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">📋 카테고리</div>
          <RouterLink to="/qa" class="block px-3 py-2 text-xs text-gray-600 hover:bg-amber-50/50">전체</RouterLink>
          <RouterLink v-for="cat in categories" :key="cat.id" :to="`/qa?category=${cat.id}`"
            class="block px-3 py-2 text-xs transition"
            :class="qa.category_id===cat.id ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">{{ cat.name }}</RouterLink>
        </div>
      </div>

      <div class="col-span-12 lg:col-span-7">
      <!-- 질문 카드 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-4">
        <div class="px-5 py-4">
          <div class="flex items-center gap-2 mb-2">
            <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-semibold">{{ qa.category?.name }}</span>
            <span v-if="qa.bounty_points > 0" class="text-xs bg-yellow-400 text-yellow-900 px-2 py-0.5 rounded-full font-bold">🏆 {{ qa.bounty_points }}P 현상금</span>
            <span v-if="qa.is_resolved" class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-semibold">✅ 해결됨</span>
          </div>
          <h1 class="text-lg font-bold text-gray-900">{{ qa.title }}</h1>
          <div class="text-xs text-gray-400 mt-2"><UserName :userId="qa.user?.id" :name="qa.user?.name" className="text-xs text-gray-400 inline" /> · {{ qa.view_count }}회 · 답변 {{ qa.answer_count }}개</div>
        </div>
        <div class="px-5 py-4 border-t text-sm text-gray-700 whitespace-pre-wrap">{{ qa.content }}</div>
      </div>

      <!-- 답변 목록 -->
      <div class="space-y-3">
        <h3 class="font-bold text-gray-800">💡 답변 {{ answers.length }}개</h3>
        <div v-for="ans in answers" :key="ans.id"
          class="bg-white rounded-xl shadow-sm border overflow-hidden"
          :class="ans.is_best ? 'border-amber-400' : 'border-gray-100'">
          <div v-if="ans.is_best" class="bg-amber-50 px-4 py-1.5 text-xs font-bold text-amber-700">👑 채택된 답변</div>
          <div class="px-5 py-4">
            <div class="text-sm text-gray-700 whitespace-pre-wrap">{{ ans.content }}</div>
            <div class="flex items-center gap-3 mt-3 text-xs text-gray-400">
              <UserName :userId="ans.user?.id" :name="ans.user?.name" className="font-semibold text-gray-600" />
              <span>❤️ {{ ans.like_count }}</span>
              <span>{{ formatDate(ans.created_at) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 답변 작성 -->
      <div v-if="auth.isLoggedIn && !qa.is_resolved" class="bg-white rounded-xl shadow-sm border border-gray-200 mt-4 p-5">
        <h3 class="font-bold text-sm text-gray-800 mb-3">✍️ 답변 작성</h3>
        <textarea v-model="newAnswer" rows="4" placeholder="답변을 입력하세요..." class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 outline-none resize-none"></textarea>
        <button @click="submitAnswer" :disabled="!newAnswer.trim()" class="mt-2 bg-amber-400 text-amber-900 font-bold px-5 py-2 rounded-lg text-sm hover:bg-amber-500 disabled:opacity-50">답변 등록</button>
      </div>

      <!-- 이전글 / 목록 / 다음글 -->
      <PostNavigator v-if="qa" :prev-id="prev?.id" :prev-title="prev?.title"
        :next-id="next?.id" :next-title="next?.title"
        list-path="/qa" detail-base="/qa/" />
      </div>
      <!-- 사이드바 -->
      <div class="col-span-12 lg:col-span-3 hidden lg:block">
        <SidebarWidgets mode="detail" :currentCategory="qa?.category || ''" api-url="/api/qa" detail-path="/qa/" :current-id="qa.id"
          label="질문" recommend-label="추천 질문" quick-label="실시간 질문"
          :links="[{to:'/qa',icon:'📋',label:'전체 Q&A'},{to:'/qa/write',icon:'✏️',label:'질문하기'},{to:'/community',icon:'💬',label:'커뮤니티'}]" />
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import PostNavigator from '../../components/PostNavigator.vue'
import axios from 'axios'
const route = useRoute()
const auth = useAuthStore()
const qa = ref(null)
const prev = ref(null)
const next = ref(null)
const answers = ref([])
const categories = ref([])
const loading = ref(true)
const newAnswer = ref('')
function formatDate(dt) { return dt ? new Date(dt).toLocaleDateString('ko-KR') : '' }
async function submitAnswer() {
  if (!newAnswer.value.trim()) return
  try {
    const { data } = await axios.post(`/api/qa/${qa.value.id}/answer`, { content: newAnswer.value })
    answers.value.push(data.data)
    newAnswer.value = ''
    qa.value.answer_count++
  } catch {}
}
onMounted(async () => {
  try {
    const [qRes, cRes] = await Promise.allSettled([
      axios.get(`/api/qa/${route.params.id}`),
      axios.get('/api/qa/categories'),
    ])
    if (qRes.status === 'fulfilled') {
      qa.value = qRes.value.data?.data
      answers.value = qa.value?.answers || []
      prev.value = qRes.value.data?.prev || null
      next.value = qRes.value.data?.next || null
    }
    if (cRes.status === 'fulfilled') categories.value = cRes.value.data?.data || []
  } catch {}
  loading.value = false
})
</script>
