<template>
  <div class="min-h-screen bg-gray-50 pb-24">
    <div class="bg-white shadow-sm px-4 py-3 flex items-center gap-3">
      <button @click="$router.back()" class="text-gray-500 text-xl">←</button>
      <h1 class="font-bold text-gray-800">매칭 프로필 {{ isEdit ? '수정' : '만들기' }}</h1>
    </div>

    <div class="px-4 pt-4 space-y-4">
      <div class="bg-white rounded-2xl shadow p-5 space-y-4">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">닉네임 *</label>
          <input v-model="form.nickname" type="text" maxlength="30" placeholder="매칭에 사용할 닉네임" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-pink-400" />
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">성별 *</label>
          <div class="grid grid-cols-3 gap-2">
            <button v-for="g in genders" :key="g.value"
              @click="form.gender = g.value"
              :class="form.gender === g.value ? 'bg-pink-500 text-white' : 'bg-gray-100 text-gray-600'"
              class="py-3 rounded-xl font-semibold text-sm">{{ g.label }}</button>
          </div>
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">출생연도 *</label>
          <input v-model="form.birth_year" type="number" min="1950" :max="new Date().getFullYear()-18" placeholder="예: 1985" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-pink-400" />
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">지역</label>
          <select v-model="form.region" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-pink-400">
            <option value="">선택하세요</option>
            <option v-for="r in regions" :key="r" :value="r">{{ r }}</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">상대방 나이 범위</label>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <p class="text-xs text-gray-500 mb-1">최소 나이</p>
              <input v-model="form.age_range_min" type="number" min="18" max="80" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none" />
            </div>
            <div>
              <p class="text-xs text-gray-500 mb-1">최대 나이</p>
              <input v-model="form.age_range_max" type="number" min="18" max="80" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none" />
            </div>
          </div>
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">자기소개</label>
          <textarea v-model="form.bio" rows="4" maxlength="500" placeholder="자신을 소개해주세요!" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-pink-400 resize-none"></textarea>
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">관심사 (복수 선택)</label>
          <div class="flex flex-wrap gap-2">
            <button v-for="tag in interestOptions" :key="tag"
              @click="toggleInterest(tag)"
              :class="form.interests.includes(tag) ? 'bg-pink-500 text-white' : 'bg-gray-100 text-gray-600'"
              class="px-3 py-1.5 rounded-full text-sm font-medium">{{ tag }}</button>
          </div>
        </div>
      </div>

      <button @click="submit" :disabled="submitting" class="w-full bg-pink-500 text-white py-4 rounded-2xl font-bold text-lg disabled:opacity-50">
        {{ submitting ? '저장 중...' : '프로필 저장하기' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router     = useRouter()
const submitting = ref(false)
const isEdit     = ref(false)

const genders  = [{ value:'male', label:'👨 남성' },{ value:'female', label:'👩 여성' },{ value:'other', label:'⚧ 기타' }]
const regions  = ['LA','NY','Atlanta','Chicago','Seattle','Dallas','Houston','SF','Boston','Miami']
const interestOptions = ['여행','요리','운동','독서','음악','영화','사진','게임','등산','맛집탐방','재테크','육아','쇼핑','커피','봉사활동']

const form = ref({
  nickname: '', gender: '', birth_year: '', region: '',
  age_range_min: 25, age_range_max: 45,
  bio: '', interests: [], visibility: 'public',
})

function toggleInterest(tag) {
  const idx = form.value.interests.indexOf(tag)
  if (idx === -1) form.value.interests.push(tag)
  else form.value.interests.splice(idx, 1)
}

async function submit() {
  submitting.value = true
  try {
    await axios.post('/api/match/profile', form.value)
    router.push('/match')
  } catch (e) {
    const errs = e?.response?.data?.errors
    alert(errs ? Object.values(errs).flat().join('\n') : '오류가 발생했습니다')
  } finally {
    submitting.value = false
  }
}

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/match/profile')
    if (data) {
      isEdit.value = true
      form.value = { ...form.value, ...data, interests: data.interests || [] }
    }
  } catch (e) {}
})
</script>
