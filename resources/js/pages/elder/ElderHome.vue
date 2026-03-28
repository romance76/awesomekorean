<template>
  <div class="min-h-screen bg-orange-50 pb-20">
    <div class="max-w-[1200px] mx-auto px-4 pt-4">
      <div class="bg-gradient-to-r from-blue-600 to-blue-500 text-white px-6 py-6 rounded-2xl">
        <h1 class="text-xl font-black">👴👵 노인 안심 서비스</h1>
        <p class="text-blue-100 text-sm mt-0.5">매일 체크인으로 보호자에게 안전 알림</p>
      </div>
    </div>

    <div class="max-w-[1200px] mx-auto px-4 pt-6 space-y-4">
      <!-- 체크인 카드 -->
      <div class="bg-white rounded-2xl shadow p-6 text-center">
        <p class="text-5xl mb-3">✅</p>
        <h2 class="text-xl font-bold text-gray-800 mb-1">오늘 체크인</h2>
        <p class="text-gray-500 text-sm mb-4">매일 체크인하면 포인트 5점을 드려요</p>
        <button
          @click="$router.push('/elder/checkin')"
          class="w-full bg-orange-500 text-white py-4 rounded-xl font-bold text-lg"
        >체크인 하러 가기</button>
      </div>

      <!-- 보호자 연결 -->
      <div class="bg-white rounded-2xl shadow p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-3">보호자 설정</h2>
        <div v-if="settings">
          <div class="flex items-center gap-3 mb-3">
            <span class="text-2xl">📱</span>
            <div>
              <p class="text-sm text-gray-500">보호자 이름</p>
              <p class="font-semibold text-gray-800">{{ settings.guardian_name || '미설정' }}</p>
            </div>
          </div>
          <div class="flex items-center gap-3 mb-4">
            <span class="text-2xl">📞</span>
            <div>
              <p class="text-sm text-gray-500">보호자 연락처</p>
              <p class="font-semibold text-gray-800">{{ maskedPhone || '미설정' }}</p>
            </div>
          </div>
        </div>
        <button
          @click="$router.push('/elder/settings')"
          class="w-full border border-orange-400 text-orange-500 py-3 rounded-xl font-semibold"
        >설정 변경</button>
      </div>

      <!-- 보호자 대시보드 링크 -->
      <div class="bg-white rounded-2xl shadow p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-2">보호자 대시보드</h2>
        <p class="text-sm text-gray-500 mb-4">가족의 안전 상태를 확인하세요</p>
        <button
          @click="$router.push('/elder/guardian')"
          class="w-full bg-blue-500 text-white py-3 rounded-xl font-semibold"
        >보호자 화면 보기</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const settings = ref(null)

const maskedPhone = computed(() => {
  const p = settings.value?.guardian_phone
  if (!p) return null
  return p.slice(0, 3) + '****' + p.slice(-4)
})

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/elder/settings')
    settings.value = data
  } catch (e) {}
})
</script>
