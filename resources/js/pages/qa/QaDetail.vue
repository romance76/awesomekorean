<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-4xl mx-auto px-4 py-5">
    <button @click="$router.back()" class="text-sm text-gray-500 hover:text-amber-600 mb-3">← Q&A 목록</button>
    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="qa">
      <!-- 질문 카드 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
        <div class="px-5 py-4">
          <div class="flex items-center gap-2 mb-2">
            <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-semibold">{{ qa.category?.name }}</span>
            <span v-if="qa.bounty_points > 0" class="text-xs bg-yellow-400 text-yellow-900 px-2 py-0.5 rounded-full font-bold">🏆 {{ qa.bounty_points }}P 현상금</span>
            <span v-if="qa.is_resolved" class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-semibold">✅ 해결됨</span>
          </div>
          <h1 class="text-lg font-bold text-gray-900">{{ qa.title }}</h1>
          <div class="text-xs text-gray-400 mt-2">{{ qa.user?.name }} · {{ qa.view_count }}조회 · 답변 {{ qa.answer_count }}개</div>
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
              <span class="font-semibold text-gray-600">{{ ans.user?.name }}</span>
              <span>❤️ {{ ans.like_count }}</span>
              <span>{{ formatDate(ans.created_at) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 답변 작성 -->
      <div v-if="auth.isLoggedIn && !qa.is_resolved" class="bg-white rounded-xl shadow-sm border border-gray-100 mt-4 p-5">
        <h3 class="font-bold text-sm text-gray-800 mb-3">✍️ 답변 작성</h3>
        <textarea v-model="newAnswer" rows="4" placeholder="답변을 입력하세요..." class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 outline-none resize-none"></textarea>
        <button @click="submitAnswer" :disabled="!newAnswer.trim()" class="mt-2 bg-amber-400 text-amber-900 font-bold px-5 py-2 rounded-lg text-sm hover:bg-amber-500 disabled:opacity-50">답변 등록</button>
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
const qa = ref(null)
const answers = ref([])
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
    const { data } = await axios.get(`/api/qa/${route.params.id}`)
    qa.value = data.data
    answers.value = data.data.answers || []
  } catch {}
  loading.value = false
})
</script>
