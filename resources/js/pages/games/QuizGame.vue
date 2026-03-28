<template>
  <div class="min-h-screen bg-gradient-to-b from-purple-600 to-indigo-700 pb-20">
    <!-- Header -->
    <div class="px-4 py-5 text-white">
      <button @click="$router.back()" class="text-white text-xl mb-2">← 뒤로</button>
      <h1 class="text-2xl font-bold">일일 퀴즈</h1>
      <p class="text-purple-200 text-sm">매일 새로운 퀴즈로 포인트를 획득하세요!</p>
    </div>

    <div class="px-4">
      <!-- Loading -->
      <div v-if="loading" class="bg-white rounded-2xl p-8 text-center text-gray-400">
        퀴즈 불러오는 중...
      </div>

      <!-- No quiz -->
      <div v-else-if="!quiz" class="bg-white rounded-2xl p-8 text-center">
        <p class="text-4xl mb-3">😴</p>
        <p class="font-bold text-gray-800">오늘의 퀴즈가 없습니다</p>
        <p class="text-gray-400 text-sm mt-1">내일 다시 확인해주세요!</p>
      </div>

      <!-- Quiz card -->
      <div v-else>
        <!-- Question -->
        <div class="bg-white rounded-2xl p-6 shadow-lg mb-4">
          <div class="flex items-center gap-2 mb-4">
            <span class="bg-purple-100 text-purple-600 text-xs px-3 py-1 rounded-full font-semibold">
              {{ categoryLabel }}
            </span>
            <span class="ml-auto text-yellow-500 font-bold text-sm">+{{ quiz.points }}P</span>
          </div>
          <p class="text-gray-800 font-bold text-lg leading-relaxed">{{ quiz.question }}</p>
        </div>

        <!-- Options -->
        <div v-if="!answered" class="space-y-3">
          <button
            v-for="(opt, key) in options"
            :key="key"
            @click="submitAnswer(key)"
            :disabled="submitting"
            class="w-full bg-white rounded-xl p-4 text-left shadow flex items-center gap-3 hover:bg-purple-50 transition active:scale-95"
          >
            <span class="w-8 h-8 rounded-full bg-purple-100 text-purple-600 font-bold flex items-center justify-center text-sm flex-shrink-0">
              {{ key }}
            </span>
            <span class="text-gray-800">{{ opt }}</span>
          </button>
        </div>

        <!-- Result -->
        <div v-else class="space-y-3">
          <div
            v-for="(opt, key) in options"
            :key="key"
            class="w-full rounded-xl p-4 flex items-center gap-3 shadow"
            :class="getOptionClass(key)"
          >
            <span class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0"
              :class="getIconClass(key)">
              {{ key === quiz.correct_answer ? '✓' : (key === selectedAnswer ? '✗' : key) }}
            </span>
            <span>{{ opt }}</span>
          </div>

          <!-- Result message -->
          <div class="bg-white rounded-2xl p-6 shadow text-center">
            <p class="text-4xl mb-2">{{ isCorrect ? '🎉' : '😢' }}</p>
            <p class="font-bold text-xl" :class="isCorrect ? 'text-green-600' : 'text-red-500'">
              {{ isCorrect ? '정답입니다!' : '틀렸습니다' }}
            </p>
            <p v-if="isCorrect" class="text-yellow-500 font-bold mt-1">+{{ quiz.points }} 포인트 획득!</p>
            <div v-if="quiz.explanation" class="mt-4 bg-gray-50 rounded-xl p-3 text-left">
              <p class="text-xs text-gray-500 mb-1 font-semibold">해설</p>
              <p class="text-sm text-gray-700">{{ quiz.explanation }}</p>
            </div>
          </div>

          <!-- Leaderboard preview -->
          <div class="bg-white rounded-2xl p-5 shadow">
            <h3 class="font-bold text-gray-800 mb-3">🏆 퀴즈 리더보드</h3>
            <div v-if="!leaders.length" class="text-center text-gray-400 text-sm py-2">데이터 없음</div>
            <div v-for="(leader, i) in leaders" :key="leader.user_id" class="flex items-center gap-3 mb-2">
              <span class="text-lg" :class="i === 0 ? 'text-yellow-400' : i === 1 ? 'text-gray-400' : 'text-orange-400'">
                {{ ['🥇','🥈','🥉'][i] || `${i+1}.` }}
              </span>
              <span class="text-gray-800 text-sm flex-1">{{ leader.user?.nickname || leader.user?.name }}</span>
              <span class="text-purple-600 font-bold text-sm">{{ leader.total_points }}P</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const quiz           = ref(null)
const loading        = ref(true)
const submitting     = ref(false)
const answered       = ref(false)
const selectedAnswer = ref('')
const isCorrect      = ref(false)
const leaders        = ref([])

const options = computed(() => {
  if (!quiz.value) return {}
  return { A: quiz.value.option_a, B: quiz.value.option_b, C: quiz.value.option_c, D: quiz.value.option_d }
})

const categoryLabel = computed(() => {
  const map = { general: '일반', culture: '문화', history: '역사', language: '언어' }
  return map[quiz.value?.category] || quiz.value?.category || '일반'
})

function getOptionClass(key) {
  if (key === quiz.value.correct_answer) return 'bg-green-100 border-2 border-green-400'
  if (key === selectedAnswer.value && !isCorrect.value) return 'bg-red-100 border-2 border-red-400'
  return 'bg-gray-50'
}

function getIconClass(key) {
  if (key === quiz.value.correct_answer) return 'bg-green-400 text-white'
  if (key === selectedAnswer.value && !isCorrect.value) return 'bg-red-400 text-white'
  return 'bg-gray-200 text-gray-600'
}

async function submitAnswer(key) {
  if (submitting.value) return
  submitting.value  = true
  selectedAnswer.value = key
  try {
    const { data } = await axios.post('/api/quiz/answer', {
      question_id: quiz.value.id,
      answer: key,
    })
    quiz.value.correct_answer = data.correct_answer
    quiz.value.explanation    = data.explanation
    isCorrect.value  = data.is_correct
    answered.value   = true
    loadLeaderboard()
  } catch (e) {
    if (e?.response?.status === 409) {
      // already answered
      const d = e.response.data.attempt
      selectedAnswer.value     = d.answer
      isCorrect.value          = d.is_correct
      answered.value           = true
    } else {
      alert(e?.response?.data?.message || '오류가 발생했습니다')
    }
  } finally {
    submitting.value = false
  }
}

async function loadLeaderboard() {
  try {
    const { data } = await axios.get('/api/quiz/leaderboard')
    leaders.value = data.slice(0, 5)
  } catch (e) {}
}

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/quiz/today')
    quiz.value = data
    if (data.attempted) {
      selectedAnswer.value = data.your_answer
      isCorrect.value      = data.is_correct
      answered.value       = true
      loadLeaderboard()
    }
  } catch (e) {
    if (e?.response?.status !== 404) console.error(e)
  } finally {
    loading.value = false
  }
})
</script>
