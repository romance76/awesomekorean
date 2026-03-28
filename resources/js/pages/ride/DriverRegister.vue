<template>
  <div class="min-h-screen bg-gray-50 pb-24">
    <div class="bg-white shadow-sm px-4 py-3 flex items-center gap-3">
      <button @click="$router.back()" class="text-gray-500 text-xl">←</button>
      <h1 class="font-bold text-gray-800">드라이버 등록</h1>
    </div>

    <div class="px-4 pt-4 space-y-4">
      <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-sm text-blue-700">
        <p class="font-bold mb-1">드라이버 등록 안내</p>
        <p>• 미국 운전면허증 필수</p>
        <p>• 차량 보험 필수</p>
        <p>• 등록 후 24시간 내 관리자 승인</p>
        <p>• 승인 후 즉시 활동 가능</p>
      </div>

      <div class="bg-white rounded-2xl shadow p-5 space-y-4">
        <h2 class="font-bold text-gray-800">운전면허 정보</h2>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">면허번호</label>
          <input v-model="form.license_number" type="text" placeholder="D1234567" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-blue-400" />
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow p-5 space-y-4">
        <h2 class="font-bold text-gray-800">차량 정보</h2>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">제조사</label>
            <input v-model="form.car_make" type="text" placeholder="Toyota" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">모델</label>
            <input v-model="form.car_model" type="text" placeholder="Camry" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">연식</label>
            <input v-model="form.car_year" type="text" placeholder="2020" maxlength="4" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">색상</label>
            <input v-model="form.car_color" type="text" placeholder="White" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">번호판</label>
          <input v-model="form.car_plate" type="text" placeholder="ABC1234" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none" />
        </div>
      </div>

      <button
        @click="submit"
        :disabled="submitting"
        class="w-full bg-blue-600 text-white py-4 rounded-2xl font-bold text-lg disabled:opacity-50"
      >{{ submitting ? '등록 중...' : '드라이버 등록 신청' }}</button>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const submitting = ref(false)
const form = ref({
  license_number: '',
  car_make: '',
  car_model: '',
  car_year: '',
  car_color: '',
  car_plate: '',
})

async function submit() {
  submitting.value = true
  try {
    await axios.post('/api/driver/register', form.value)
    alert('드라이버 등록 신청이 완료되었습니다! 24시간 내 승인됩니다.')
    router.push('/ride/driver')
  } catch (e) {
    const errors = e?.response?.data?.errors
    alert(errors ? Object.values(errors).flat().join('\n') : '오류가 발생했습니다')
  } finally {
    submitting.value = false
  }
}
</script>
