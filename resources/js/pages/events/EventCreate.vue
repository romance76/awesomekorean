<template>
  <div class="min-h-screen bg-gray-50 pb-24">
    <div class="bg-white shadow-sm px-4 py-3 flex items-center gap-3">
      <button @click="$router.back()" class="text-gray-500 text-xl">←</button>
      <h1 class="font-bold text-gray-800">이벤트 등록</h1>
    </div>

    <div class="px-4 pt-4 space-y-4">
      <div class="bg-white rounded-2xl shadow p-5 space-y-4">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">이벤트 제목 *</label>
          <input v-model="form.title" type="text" maxlength="100" placeholder="이벤트 제목" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-blue-400" />
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">카테고리</label>
          <select v-model="form.category" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none">
            <option value="general">일반</option>
            <option value="meetup">모임</option>
            <option value="food">음식/맛집</option>
            <option value="culture">문화/예술</option>
            <option value="sports">스포츠</option>
            <option value="education">교육</option>
            <option value="business">비즈니스</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">날짜/시간 *</label>
          <input v-model="form.event_date" type="datetime-local" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-blue-400" />
        </div>

        <div class="flex items-center gap-3">
          <input v-model="form.is_online" type="checkbox" id="is_online" class="w-5 h-5 text-blue-500" />
          <label for="is_online" class="text-sm font-semibold text-gray-700">온라인 이벤트</label>
        </div>

        <div v-if="!form.is_online">
          <label class="block text-sm font-semibold text-gray-700 mb-1">장소</label>
          <input v-model="form.location" type="text" placeholder="주소 또는 장소명" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-blue-400" />
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">지역</label>
          <select v-model="form.region" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none">
            <option value="">선택하세요</option>
            <option v-for="r in regions" :key="r" :value="r">{{ r }}</option>
          </select>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">참가비</label>
            <input v-model="form.price" type="number" min="0" step="0.01" placeholder="0 = 무료" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">최대 참가자</label>
            <input v-model="form.max_attendees" type="number" min="1" placeholder="무제한" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none" />
          </div>
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">상세 내용</label>
          <textarea v-model="form.description" rows="5" placeholder="이벤트에 대해 자세히 설명해주세요" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none resize-none"></textarea>
        </div>
      </div>

      <button @click="submit" :disabled="submitting" class="w-full bg-blue-500 text-white py-4 rounded-2xl font-bold text-lg disabled:opacity-50">
        {{ submitting ? '등록 중...' : '이벤트 등록하기' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router     = useRouter()
const submitting = ref(false)
const regions    = ['LA','NY','Atlanta','Chicago','Seattle','Dallas','Houston','SF','Boston','Miami']

const form = ref({
  title: '', category: 'general', event_date: '', is_online: false,
  location: '', region: '', price: 0, max_attendees: '',  description: '',
})

async function submit() {
  submitting.value = true
  try {
    const { data } = await axios.post('/api/events', form.value)
    router.push(`/events/${data.id}`)
  } catch (e) {
    const errs = e?.response?.data?.errors
    alert(errs ? Object.values(errs).flat().join('\n') : '오류가 발생했습니다')
  } finally {
    submitting.value = false
  }
}
</script>
