<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <div class="bg-white shadow-sm px-4 py-3 flex items-center gap-3">
      <button @click="$router.back()" class="text-gray-500 text-xl">←</button>
      <h1 class="font-bold text-gray-800">이용 내역</h1>
    </div>

    <div class="px-4 pt-4">
      <div v-if="loading" class="text-center py-8 text-gray-400">불러오는 중...</div>
      <div v-else-if="!rides.length" class="text-center py-12">
        <p class="text-4xl mb-3">🚗</p>
        <p class="text-gray-500">이용 내역이 없습니다</p>
        <button @click="$router.push('/ride/request')" class="mt-4 bg-yellow-500 text-white px-6 py-3 rounded-xl font-semibold">라이드 요청하기</button>
      </div>
      <div v-else class="space-y-3">
        <div
          v-for="ride in rides"
          :key="ride.id"
          class="bg-white rounded-xl shadow p-4"
        >
          <div class="flex items-center justify-between mb-2">
            <span class="text-xs px-2 py-1 rounded-full font-semibold"
              :class="{
                'bg-yellow-100 text-yellow-700': ride.status === 'requesting',
                'bg-blue-100 text-blue-700':   ride.status === 'matched' || ride.status === 'ongoing',
                'bg-green-100 text-green-700': ride.status === 'completed',
                'bg-red-100 text-red-700':     ride.status === 'cancelled',
              }">
              {{ statusLabel(ride.status) }}
            </span>
            <span class="text-gray-400 text-xs">{{ formatDate(ride.created_at) }}</span>
          </div>
          <div class="space-y-1 text-sm">
            <p class="text-gray-700"><span class="text-gray-400">출발:</span> {{ ride.pickup_address }}</p>
            <p class="text-gray-700"><span class="text-gray-400">도착:</span> {{ ride.dropoff_address }}</p>
          </div>
          <div class="flex justify-between items-center mt-3">
            <span class="text-gray-400 text-sm">예상 {{ ride.distance_miles }}mi</span>
            <span class="font-black text-orange-500">${{ ride.final_fare || ride.estimated_fare }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const rides   = ref([])
const loading = ref(true)

const statusLabel = (s) => ({ requesting:'요청중', matched:'매칭됨', ongoing:'이동중', completed:'완료', cancelled:'취소' }[s] || s)
const formatDate  = (d) => new Date(d).toLocaleDateString('ko-KR')

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/ride/history')
    rides.value = data.data || data
  } catch (e) {}
  finally { loading.value = false }
})
</script>
