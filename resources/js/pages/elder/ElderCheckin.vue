<template>
  <div class="min-h-screen bg-orange-50 flex flex-col items-center justify-center px-6 pb-20">
    <!-- Success state -->
    <div v-if="done" class="text-center">
      <p class="text-8xl mb-6">🎉</p>
      <h1 class="text-3xl font-bold text-green-600 mb-3">체크인 완료!</h1>
      <p class="text-gray-600 text-lg mb-2">오늘도 안전하게 지내고 계시네요!</p>
      <p v-if="pointsEarned > 0" class="text-orange-500 font-bold text-xl mb-6">+{{ pointsEarned }} 포인트 적립!</p>
      <p v-else class="text-gray-400 text-sm mb-6">오늘은 이미 체크인하셨어요</p>
      <button @click="$router.back()" class="bg-orange-500 text-white px-8 py-4 rounded-xl text-lg font-bold">
        돌아가기
      </button>
    </div>

    <!-- Checkin state -->
    <div v-else class="text-center w-full max-w-sm">
      <p class="text-6xl mb-6">👍</p>
      <h1 class="text-3xl font-bold text-gray-800 mb-2">안녕하세요!</h1>
      <p class="text-gray-500 text-lg mb-8">아래 버튼을 눌러서<br>오늘의 체크인을 해주세요</p>

      <button
        @click="doCheckin"
        :disabled="loading"
        class="w-full bg-orange-500 text-white rounded-2xl py-8 text-2xl font-bold shadow-lg active:scale-95 transition-transform disabled:opacity-60"
      >
        <span v-if="loading">처리중...</span>
        <span v-else>✅ 체크인</span>
      </button>

      <p class="text-gray-400 text-sm mt-6">체크인 시 포인트 5점이 적립됩니다</p>

      <!-- SOS 버튼 -->
      <button
        @click="sendSos"
        :disabled="sosSent"
        class="w-full mt-6 bg-red-500 text-white rounded-2xl py-5 text-xl font-bold shadow active:scale-95 transition-transform disabled:opacity-60"
      >
        <span v-if="sosSent">SOS 전송됨 ✓</span>
        <span v-else>🆘 SOS 긴급신호</span>
      </button>
      <p class="text-red-400 text-xs mt-2">보호자에게 긴급 연락을 보냅니다</p>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'

const loading     = ref(false)
const done        = ref(false)
const sosSent     = ref(false)
const pointsEarned = ref(0)

async function doCheckin() {
  loading.value = true
  try {
    const { data } = await axios.post('/api/elder/checkin')
    pointsEarned.value = data.points_earned
    done.value = true
  } catch (e) {
    alert(e?.response?.data?.message || '오류가 발생했습니다')
  } finally {
    loading.value = false
  }
}

async function sendSos() {
  if (!confirm('보호자에게 SOS를 보내시겠습니까?')) return
  try {
    await axios.post('/api/elder/sos')
    sosSent.value = true
    alert('보호자에게 SOS 신호를 전송했습니다!')
  } catch (e) {
    alert(e?.response?.data?.message || '오류가 발생했습니다')
  }
}
</script>
