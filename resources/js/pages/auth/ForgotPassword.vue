<template>
<div class="min-h-screen bg-gray-50 flex items-center justify-center px-4">
  <div class="bg-white rounded-2xl shadow-lg p-8 w-full max-w-md">
    <div class="text-center mb-6">
      <div class="w-12 h-12 bg-amber-400 rounded-xl mx-auto mb-3 flex items-center justify-center text-xl font-black text-amber-900">AK</div>
      <h1 class="text-xl font-black text-gray-800">비밀번호 찾기</h1>
      <p class="text-sm text-gray-500 mt-1">가입한 이메일을 입력하세요</p>
    </div>

    <!-- Step 1: 이메일 입력 -->
    <div v-if="step === 1" class="space-y-4">
      <div><label class="text-sm font-semibold text-gray-700">이메일</label><input v-model="email" type="email" required placeholder="example@email.com" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
      <div v-if="error" class="text-red-500 text-sm">{{ error }}</div>
      <div v-if="msg" class="text-green-600 text-sm">{{ msg }}</div>
      <button @click="sendCode" :disabled="submitting" class="w-full bg-amber-400 text-amber-900 font-bold py-2.5 rounded-lg hover:bg-amber-500 transition disabled:opacity-50">{{ submitting ? '전송 중...' : '인증 코드 받기' }}</button>
    </div>

    <!-- Step 2: 코드 입력 + 새 비밀번호 -->
    <div v-if="step === 2" class="space-y-4">
      <div class="bg-amber-50 rounded-lg p-3 text-sm text-amber-800">{{ email }} 로 인증 코드가 전송되었습니다</div>
      <div><label class="text-sm font-semibold text-gray-700">인증 코드 (6자리)</label><input v-model="code" type="text" maxlength="6" placeholder="000000" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none text-center tracking-widest text-lg font-mono" /></div>
      <div><label class="text-sm font-semibold text-gray-700">새 비밀번호</label><input v-model="password" type="password" required minlength="6" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
      <div><label class="text-sm font-semibold text-gray-700">비밀번호 확인</label><input v-model="password_confirmation" type="password" required class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
      <div v-if="error" class="text-red-500 text-sm">{{ error }}</div>
      <button @click="resetPw" :disabled="submitting" class="w-full bg-amber-400 text-amber-900 font-bold py-2.5 rounded-lg hover:bg-amber-500 transition disabled:opacity-50">{{ submitting ? '변경 중...' : '비밀번호 변경' }}</button>
      <button @click="step=1" class="w-full text-gray-500 text-sm hover:text-amber-600">다시 보내기</button>
    </div>

    <!-- Step 3: 완료 -->
    <div v-if="step === 3" class="text-center py-6">
      <div class="text-4xl mb-3">✅</div>
      <div class="text-lg font-bold text-gray-800 mb-2">비밀번호가 변경되었습니다!</div>
      <RouterLink to="/login" class="inline-block bg-amber-400 text-amber-900 font-bold px-6 py-2.5 rounded-lg hover:bg-amber-500 mt-3">로그인하기</RouterLink>
    </div>

    <div v-if="step !== 3" class="text-center mt-4 text-sm text-gray-500">
      <RouterLink to="/login" class="text-amber-600 font-semibold">로그인으로 돌아가기</RouterLink>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref } from 'vue'
import axios from 'axios'
const step = ref(1)
const email = ref('')
const code = ref('')
const password = ref('')
const password_confirmation = ref('')
const error = ref('')
const msg = ref('')
const submitting = ref(false)

async function sendCode() {
  if (!email.value) { error.value = '이메일을 입력하세요'; return }
  submitting.value = true; error.value = ''; msg.value = ''
  try {
    await axios.post('/api/forgot-password', { email: email.value })
    step.value = 2
  } catch (e) { error.value = e.response?.data?.message || '전송 실패' }
  submitting.value = false
}

async function resetPw() {
  if (!code.value || !password.value || !password_confirmation.value) { error.value = '모든 필드를 입력하세요'; return }
  if (password.value !== password_confirmation.value) { error.value = '비밀번호가 일치하지 않습니다'; return }
  submitting.value = true; error.value = ''
  try {
    await axios.post('/api/reset-password', { email: email.value, code: code.value, password: password.value, password_confirmation: password_confirmation.value })
    step.value = 3
  } catch (e) { error.value = e.response?.data?.message || '변경 실패' }
  submitting.value = false
}
</script>
