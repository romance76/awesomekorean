<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <div class="bg-blue-600 text-white px-4 py-5">
      <button @click="$router.back()" class="text-white text-xl mb-2">← 뒤로</button>
      <h1 class="text-xl font-bold">보호자 대시보드</h1>
      <p class="text-blue-100 text-sm">가족의 안전 상태를 확인하세요</p>
    </div>

    <div class="px-4 pt-4">
      <!-- 사용자 ID 입력 -->
      <div v-if="!elderData" class="bg-white rounded-xl shadow p-6">
        <h2 class="font-bold text-gray-800 mb-3">가족 ID 입력</h2>
        <input
          v-model="userId"
          type="number"
          placeholder="가족의 회원 ID를 입력하세요"
          class="w-full border border-gray-200 rounded-lg px-4 py-3 mb-3 focus:outline-none focus:border-blue-400"
        />
        <button
          @click="loadElder"
          :disabled="!userId || loading"
          class="w-full bg-blue-500 text-white py-3 rounded-lg font-semibold"
        >{{ loading ? '불러오는 중...' : '확인하기' }}</button>
        <p v-if="error" class="text-red-500 text-sm mt-2 text-center">{{ error }}</p>
      </div>

      <!-- 상태 카드 -->
      <div v-else>
        <div class="bg-white rounded-xl shadow p-5 mb-4">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center text-xl font-bold text-blue-700">
              {{ elderData.user?.nickname?.[0] || elderData.user?.name?.[0] || '?' }}
            </div>
            <div>
              <p class="font-bold text-gray-800">{{ elderData.user?.nickname || elderData.user?.name }}</p>
              <p class="text-gray-400 text-sm">회원 ID: {{ elderData.user?.id }}</p>
            </div>
            <div class="ml-auto">
              <span
                class="px-3 py-1 rounded-full text-sm font-bold"
                :class="elderData.is_overdue ? 'bg-red-100 text-red-600' : 'bg-green-100 text-green-600'"
              >
                {{ elderData.is_overdue ? '⚠️ 미체크인' : '✅ 정상' }}
              </span>
            </div>
          </div>

          <div class="space-y-3">
            <div class="flex justify-between text-sm">
              <span class="text-gray-500">마지막 체크인</span>
              <span class="font-semibold" :class="elderData.is_overdue ? 'text-red-500' : 'text-green-600'">
                {{ elderData.last_checkin_at ? formatDate(elderData.last_checkin_at) : '체크인 없음' }}
              </span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-gray-500">체크인 주기</span>
              <span class="font-semibold text-gray-800">{{ elderData.checkin_interval }}시간</span>
            </div>
            <div v-if="elderData.last_sos_at" class="flex justify-between text-sm">
              <span class="text-gray-500">마지막 SOS</span>
              <span class="font-semibold text-red-500">{{ formatDate(elderData.last_sos_at) }}</span>
            </div>
          </div>

          <div v-if="elderData.is_overdue" class="mt-4 bg-red-50 border border-red-200 rounded-lg p-3 text-center">
            <p class="text-red-600 font-bold">체크인이 지연되었습니다!</p>
            <p class="text-red-400 text-sm mt-1">가족에게 연락해보세요</p>
          </div>
        </div>

        <button @click="elderData = null; userId = ''" class="w-full border border-gray-300 text-gray-600 py-3 rounded-xl">
          다른 가족 확인
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'

const userId    = ref('')
const loading   = ref(false)
const error     = ref('')
const elderData = ref(null)

function formatDate(dt) {
  return new Date(dt).toLocaleString('ko-KR', {
    month: 'numeric', day: 'numeric',
    hour: '2-digit', minute: '2-digit'
  })
}

async function loadElder() {
  loading.value = true
  error.value   = ''
  try {
    const { data } = await axios.get(`/api/elder/guardian/${userId.value}`)
    elderData.value = data
  } catch (e) {
    error.value = e?.response?.data?.message || '가족 정보를 불러올 수 없습니다'
  } finally {
    loading.value = false
  }
}
</script>
