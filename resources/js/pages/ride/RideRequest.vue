<template>
  <div class="min-h-screen bg-gray-50 pb-24">
    <div class="bg-white shadow-sm px-4 py-3 flex items-center gap-3">
      <button @click="$router.back()" class="text-gray-500 text-xl">←</button>
      <h1 class="font-bold text-gray-800">라이드 요청</h1>
    </div>

    <!-- 요청 완료 화면 -->
    <div v-if="rideCreated" class="px-4 pt-8 text-center">
      <div class="bg-white rounded-2xl shadow-lg p-8">
        <p class="text-6xl mb-4">🚗</p>
        <h2 class="text-2xl font-bold text-gray-800 mb-2">라이드 요청 완료!</h2>
        <p class="text-gray-500 mb-4">주변 드라이버를 찾고 있습니다...</p>
        <div class="bg-yellow-50 rounded-xl p-4 mb-6 text-left space-y-2">
          <div class="flex justify-between text-sm">
            <span class="text-gray-500">출발지</span>
            <span class="font-semibold text-gray-800 text-right max-w-xs">{{ form.pickup_address }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-gray-500">목적지</span>
            <span class="font-semibold text-gray-800 text-right max-w-xs">{{ form.dropoff_address }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-gray-500">예상 요금</span>
            <span class="font-bold text-orange-500 text-lg">${{ createdRide?.estimated_fare }}</span>
          </div>
        </div>
        <div class="flex gap-3">
          <button @click="cancelRide" class="flex-1 border border-red-400 text-red-500 py-3 rounded-xl font-semibold">요청 취소</button>
          <button @click="$router.push('/ride/history')" class="flex-1 bg-blue-500 text-white py-3 rounded-xl font-semibold">내역 보기</button>
        </div>
      </div>
    </div>

    <!-- 요청 폼 -->
    <div v-else class="px-4 pt-4 flex justify-center">
      <div class="w-full max-w-[600px] space-y-3">
        <div class="bg-white rounded-xl shadow-sm p-4 space-y-3">
          <!-- 출발지 -->
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">출발지</label>
            <input
              v-model="form.pickup_address"
              type="text"
              placeholder="출발 주소를 입력하세요"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400"
            />
            <button @click="useMyLocation" class="inline-flex items-center gap-1 text-blue-500 text-xs mt-1 hover:underline">📍 현재 위치 사용</button>
          </div>

          <!-- 목적지 -->
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">목적지</label>
            <input
              v-model="form.dropoff_address"
              type="text"
              placeholder="목적지 주소를 입력하세요"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400"
            />
          </div>

          <!-- 결제 수단 -->
          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">결제 수단</label>
            <div class="flex gap-2">
              <button v-for="m in paymentMethods" :key="m.value"
                @click="form.payment_method = m.value"
                :class="form.payment_method === m.value ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-600'"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition">
                {{ m.label }}
              </button>
            </div>
          </div>
        </div>

        <!-- 요금 미리보기 -->
        <div v-if="estimatedFare" class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-center">
          <p class="text-xs text-gray-500">예상 요금</p>
          <p class="text-2xl font-black text-orange-500">${{ estimatedFare }}</p>
          <p class="text-xs text-gray-400">실제 요금은 달라질 수 있습니다</p>
        </div>

        <button
          @click="requestRide"
          :disabled="submitting || !isFormValid"
          class="w-full bg-yellow-500 text-white py-2.5 rounded-xl font-bold text-sm shadow disabled:opacity-50"
        >{{ submitting ? '요청 중...' : '🚗 라이드 요청하기' }}</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'

const form = ref({
  pickup_address: '',
  pickup_lat: '',
  pickup_lng: '',
  dropoff_address: '',
  dropoff_lat: '',
  dropoff_lng: '',
  payment_method: 'cash',
})

const paymentMethods = [
  { value: 'cash',   label: '💵 현금' },
  { value: 'card',   label: '💳 카드' },
  { value: 'points', label: '⭐ 포인트' },
]

const submitting   = ref(false)
const rideCreated  = ref(false)
const createdRide  = ref(null)

const isFormValid = computed(() =>
  form.value.pickup_address && form.value.dropoff_address
)

const estimatedFare = computed(() => {
  if (!form.value.pickup_lat || !form.value.pickup_lng || !form.value.dropoff_lat || !form.value.dropoff_lng) return null
  const R    = 3959
  const dLat = (Number(form.value.dropoff_lat) - Number(form.value.pickup_lat)) * Math.PI / 180
  const dLng = (Number(form.value.dropoff_lng) - Number(form.value.pickup_lng)) * Math.PI / 180
  const a    = Math.sin(dLat/2)**2 + Math.cos(Number(form.value.pickup_lat)*Math.PI/180) * Math.cos(Number(form.value.dropoff_lat)*Math.PI/180) * Math.sin(dLng/2)**2
  const dist = R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a))
  return Math.max(5, 3 + dist * 1.2).toFixed(2)
})

function useMyLocation() {
  if (!navigator.geolocation) return
  navigator.geolocation.getCurrentPosition(pos => {
    form.value.pickup_lat = pos.coords.latitude.toFixed(7)
    form.value.pickup_lng = pos.coords.longitude.toFixed(7)
    form.value.pickup_address = `현재 위치 (${form.value.pickup_lat}, ${form.value.pickup_lng})`
  })
}

async function requestRide() {
  submitting.value = true
  try {
    const { data } = await axios.post('/api/ride/request', form.value)
    createdRide.value = data
    rideCreated.value = true
  } catch (e) {
    alert(e?.response?.data?.message || '오류가 발생했습니다')
  } finally {
    submitting.value = false
  }
}

async function cancelRide() {
  if (!confirm('라이드 요청을 취소하시겠습니까?')) return
  try {
    await axios.post(`/api/ride/${createdRide.value.id}/cancel`)
    rideCreated.value = false
    createdRide.value = null
  } catch (e) {
    alert(e?.response?.data?.message || '오류')
  }
}
</script>
