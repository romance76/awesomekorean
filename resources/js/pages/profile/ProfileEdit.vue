<template>
  <div class="max-w-[1200px] mx-auto px-4 py-6 pb-20">
    <div class="flex items-center gap-3 mb-6">
      <button @click="$router.back()" class="text-gray-400 hover:text-gray-600">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
      </button>
      <h1 class="text-xl font-bold text-gray-900">프로필 수정</h1>
    </div>

    <form @submit.prevent="save" class="space-y-5">

      <!-- 아바타 -->
      <div class="bg-white rounded-2xl p-5 shadow-sm">
        <h2 class="text-sm font-bold text-gray-700 mb-4">프로필 사진</h2>
        <div class="flex items-center gap-4">
          <div class="w-20 h-20 rounded-full bg-blue-100 flex items-center justify-center text-3xl font-bold text-blue-600 overflow-hidden flex-shrink-0">
            <img v-if="form.avatar" :src="form.avatar" class="w-full h-full object-cover" />
            <span v-else>{{ (auth.user?.name || '?')[0] }}</span>
          </div>
          <div>
            <input type="url" v-model="form.avatar" placeholder="이미지 URL 입력 (선택)"
              class="border border-gray-200 rounded-lg px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-blue-400"/>
            <p class="text-xs text-gray-400 mt-1">이미지 URL을 붙여넣으세요 (imgur, etc.)</p>
          </div>
        </div>
      </div>

      <!-- 기본 정보 -->
      <div class="bg-white rounded-2xl p-5 shadow-sm space-y-4">
        <h2 class="text-sm font-bold text-gray-700">기본 정보</h2>

        <div>
          <label class="block text-xs text-gray-500 mb-1">이름 <span class="text-red-400">*</span></label>
          <input v-model="form.name" type="text" required maxlength="50"
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"/>
        </div>

        <div>
          <label class="block text-xs text-gray-500 mb-1">자기소개</label>
          <textarea v-model="form.bio" rows="3" maxlength="500" placeholder="간단한 자기소개를 입력하세요"
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 resize-none"/>
          <p class="text-right text-xs text-gray-300 mt-1">{{ form.bio?.length ?? 0 }}/500</p>
        </div>

        <div>
          <label class="block text-xs text-gray-500 mb-1">지역</label>
          <select v-model="form.region" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
            <option value="">지역 선택</option>
            <option v-for="r in regions" :key="r" :value="r">{{ r }}</option>
          </select>
        </div>

        <div>
          <label class="block text-xs text-gray-500 mb-1">언어</label>
          <select v-model="form.lang" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
            <option value="ko">한국어</option>
            <option value="en">English</option>
            <option value="both">Both</option>
          </select>
        </div>
      </div>

      <!-- 비밀번호 변경 -->
      <div class="bg-white rounded-2xl p-5 shadow-sm space-y-4">
        <h2 class="text-sm font-bold text-gray-700">비밀번호 변경 <span class="text-gray-400 font-normal text-xs">(선택)</span></h2>

        <div>
          <label class="block text-xs text-gray-500 mb-1">현재 비밀번호</label>
          <input v-model="pwForm.current" type="password" autocomplete="current-password"
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"/>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">새 비밀번호</label>
          <input v-model="pwForm.password" type="password" autocomplete="new-password"
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"/>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">새 비밀번호 확인</label>
          <input v-model="pwForm.password_confirmation" type="password" autocomplete="new-password"
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"/>
        </div>
        <button v-if="pwForm.current" type="button" @click="changePassword"
          :disabled="pwLoading"
          class="text-sm bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 disabled:opacity-50">
          {{ pwLoading ? '변경 중...' : '비밀번호 변경' }}
        </button>
      </div>

      <div v-if="error" class="bg-red-50 text-red-600 text-sm rounded-xl p-3">{{ error }}</div>
      <div v-if="success" class="bg-green-50 text-green-600 text-sm rounded-xl p-3">✅ {{ success }}</div>

      <button type="submit" :disabled="loading"
        class="w-full bg-blue-600 text-white font-semibold py-3 rounded-xl hover:bg-blue-700 disabled:opacity-50 transition">
        {{ loading ? '저장 중...' : '저장하기' }}
      </button>
    </form>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { useRouter } from 'vue-router'
import axios from 'axios'

const auth   = useAuthStore()
const router = useRouter()

const loading  = ref(false)
const pwLoading = ref(false)
const error    = ref('')
const success  = ref('')

const regions = [
  'New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix',
  'Philadelphia', 'San Antonio', 'San Diego', 'Dallas', 'San Jose',
  'Atlanta', 'Seattle', 'Boston', 'Denver', 'Miami',
  'Washington DC', 'Las Vegas', 'Portland', 'Nashville', 'Austin',
]

const form = ref({
  name:   '',
  bio:    '',
  region: '',
  lang:   'ko',
  avatar: '',
})

const pwForm = ref({
  current: '',
  password: '',
  password_confirmation: '',
})

onMounted(() => {
  const u = auth.user
  if (!u) { router.push('/auth/login'); return }
  form.value.name   = u.name   ?? ''
  form.value.bio    = u.bio    ?? ''
  form.value.region = u.region ?? ''
  form.value.lang   = u.lang   ?? 'ko'
  form.value.avatar = u.avatar ?? ''
})

async function save() {
  loading.value = true
  error.value   = ''
  success.value = ''
  try {
    const { data } = await axios.put('/api/profile', form.value)
    auth.user = { ...auth.user, ...data.user }
    success.value = '프로필이 저장되었습니다!'
    setTimeout(() => { router.push(`/profile/${auth.user.username ?? auth.user.name}`) }, 1200)
  } catch (e) {
    error.value = e.response?.data?.message ?? '저장 실패'
  } finally {
    loading.value = false
  }
}

async function changePassword() {
  if (pwForm.value.password !== pwForm.value.password_confirmation) {
    error.value = '비밀번호가 일치하지 않습니다.'
    return
  }
  pwLoading.value = true
  error.value     = ''
  success.value   = ''
  try {
    await axios.post('/api/profile/password', pwForm.value)
    success.value = '비밀번호가 변경되었습니다!'
    pwForm.value  = { current: '', password: '', password_confirmation: '' }
  } catch (e) {
    error.value = e.response?.data?.message ?? '비밀번호 변경 실패'
  } finally {
    pwLoading.value = false
  }
}
</script>
