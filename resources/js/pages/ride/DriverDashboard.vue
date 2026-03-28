<template>
  <div class="min-h-screen bg-gray-900 text-white pb-20">
    <div class="bg-gray-800 px-4 py-4 flex items-center gap-3">
      <button @click="$router.back()" class="text-gray-400">←</button>
      <h1 class="font-bold text-lg text-yellow-400">드라이버 대시보드</h1>
    </div>

    <div class="px-4 pt-4 space-y-4">
      <!-- 드라이버 미등록 -->
      <div v-if="!profile?.license_number" class="bg-gray-800 rounded-2xl p-6 text-center">
        <p class="text-4xl mb-3">🚗</p>
        <h2 class="font-bold text-xl mb-2">드라이버 등록이 필요합니다</h2>
        <p class="text-gray-400 text-sm mb-4">면허증과 차량 정보를 등록하고 수입을 올려보세요!</p>
        <button @click="$router.push('/ride/driver/register')" class="w-full bg-yellow-500 text-black font-bold py-3 rounded-xl">
          드라이버 등록하기
        </button>
      </div>

      <!-- 미승인 대기 -->
      <div v-else-if="!profile?.verified" class="bg-gray-800 rounded-2xl p-6 text-center">
        <p class="text-4xl mb-3">⏳</p>
        <h2 class="font-bold text-xl mb-2">승인 대기 중</h2>
        <p class="text-gray-400 text-sm">관리자 검토 후 24시간 내 승인됩니다</p>
      </div>

      <!-- 드라이버 승인됨 -->
      <template v-else>
        <!-- 온라인/오프라인 토글 -->
        <div class="bg-gray-800 rounded-2xl p-5">
          <div class="flex items-center justify-between mb-4">
            <div>
              <p class="font-bold text-lg">{{ profile.is_online ? '🟢 온라인' : '🔴 오프라인' }}</p>
              <p class="text-gray-400 text-sm">{{ profile.is_online ? '콜을 받을 수 있습니다' : '콜을 받지 않는 상태' }}</p>
            </div>
            <button
              @click="toggleOnline"
              :class="profile.is_online ? 'bg-red-500' : 'bg-green-500'"
              class="px-6 py-3 rounded-xl font-bold text-sm"
            >{{ profile.is_online ? '오프라인' : '온라인' }}</button>
          </div>
        </div>

        <!-- 수입 현황 -->
        <div class="grid grid-cols-3 gap-3">
          <div class="bg-gray-800 rounded-xl p-4 text-center">
            <p class="text-2xl font-black text-yellow-400">${{ profile.total_earnings?.toFixed(0) || 0 }}</p>
            <p class="text-gray-400 text-xs mt-1">총 수입</p>
          </div>
          <div class="bg-gray-800 rounded-xl p-4 text-center">
            <p class="text-2xl font-black text-blue-400">{{ profile.total_rides || 0 }}</p>
            <p class="text-gray-400 text-xs mt-1">총 운행</p>
          </div>
          <div class="bg-gray-800 rounded-xl p-4 text-center">
            <p class="text-2xl font-black text-green-400">{{ profile.rating_avg || 5.0 }}</p>
            <p class="text-gray-400 text-xs mt-1">평점 ⭐</p>
          </div>
        </div>

        <!-- 차량 정보 -->
        <div class="bg-gray-800 rounded-2xl p-5">
          <h2 class="font-bold mb-3 text-gray-300">내 차량</h2>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-500">차량</span>
              <span>{{ profile.car_year }} {{ profile.car_make }} {{ profile.car_model }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">색상</span>
              <span>{{ profile.car_color }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">번호판</span>
              <span>{{ profile.car_plate }}</span>
            </div>
          </div>
        </div>

        <!-- 주변 라이드 요청 -->
        <div class="bg-gray-800 rounded-2xl p-5" v-if="profile.is_online">
          <h2 class="font-bold mb-3 text-yellow-400">근처 라이드 요청</h2>
          <div v-if="nearbyRides.length === 0" class="text-center text-gray-500 py-4">
            주변 요청이 없습니다
          </div>
          <div v-for="ride in nearbyRides" :key="ride.id" class="bg-gray-700 rounded-xl p-4 mb-3">
            <div class="flex justify-between items-start mb-2">
              <div>
                <p class="text-sm font-semibold text-white">{{ ride.pickup_address }}</p>
                <p class="text-xs text-gray-400">→ {{ ride.dropoff_address }}</p>
              </div>
              <span class="text-yellow-400 font-black text-lg">${{ ride.estimated_fare }}</span>
            </div>
            <button @click="acceptRide(ride.id)" class="w-full bg-yellow-500 text-black font-bold py-2 rounded-lg text-sm">
              수락하기
            </button>
          </div>
          <button @click="loadNearbyRides" class="w-full text-gray-400 text-sm py-2">새로고침</button>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const profile     = ref(null)
const nearbyRides = ref([])

async function loadProfile() {
  try {
    const { data } = await axios.get('/api/driver/profile')
    profile.value = data
    if (data.is_online) loadNearbyRides()
  } catch (e) {}
}

async function toggleOnline() {
  try {
    const { data } = await axios.post('/api/driver/online')
    profile.value.is_online = data.is_online
    if (data.is_online) loadNearbyRides()
    alert(data.message)
  } catch (e) {
    alert(e?.response?.data?.message || '오류')
  }
}

async function loadNearbyRides() {
  try {
    const { data } = await axios.get('/api/ride/nearby')
    nearbyRides.value = data
  } catch (e) {}
}

async function acceptRide(rideId) {
  try {
    await axios.post(`/api/ride/${rideId}/accept`)
    alert('라이드를 수락했습니다! 승객에게 연락해주세요.')
    nearbyRides.value = nearbyRides.value.filter(r => r.id !== rideId)
  } catch (e) {
    alert(e?.response?.data?.message || '이미 수락된 라이드입니다')
  }
}

onMounted(loadProfile)
</script>
