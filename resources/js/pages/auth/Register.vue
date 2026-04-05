<template>
<div class="min-h-screen bg-gray-50 flex items-center justify-center px-4">
  <div class="bg-white rounded-2xl shadow-lg p-8 w-full max-w-md">
    <div class="text-center mb-6">
      <div class="w-12 h-12 bg-amber-400 rounded-xl mx-auto mb-3 flex items-center justify-center text-xl font-black text-amber-900">SK</div>
      <h1 class="text-xl font-black text-gray-800">회원가입</h1>
    </div>
    <form @submit.prevent="handleRegister" class="space-y-4">
      <div><label class="text-sm font-semibold text-gray-700">이름</label><input v-model="form.name" type="text" required class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
      <div><label class="text-sm font-semibold text-gray-700">닉네임</label><input v-model="form.nickname" type="text" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
      <div><label class="text-sm font-semibold text-gray-700">이메일</label><input v-model="form.email" type="email" required class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
      <div><label class="text-sm font-semibold text-gray-700">비밀번호</label><input v-model="form.password" type="password" required minlength="6" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
      <div><label class="text-sm font-semibold text-gray-700">비밀번호 확인</label><input v-model="form.password_confirmation" type="password" required class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
      <div v-if="error" class="text-red-500 text-sm">{{ error }}</div>
      <button type="submit" :disabled="submitting" class="w-full bg-amber-400 text-amber-900 font-bold py-2.5 rounded-lg hover:bg-amber-500 transition disabled:opacity-50">{{ submitting ? '가입 중...' : '회원가입' }}</button>
    </form>
    <div class="text-center mt-4 text-sm text-gray-500">이미 계정이 있으신가요? <RouterLink to="/login" class="text-amber-600 font-semibold">로그인</RouterLink></div>
  </div>
</div>
</template>
<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
const auth = useAuthStore()
const router = useRouter()
const form = reactive({ name: '', nickname: '', email: '', password: '', password_confirmation: '' })
const error = ref('')
const submitting = ref(false)
async function handleRegister() {
  submitting.value = true; error.value = ''
  try { await auth.register(form); router.push('/') }
  catch (e) { error.value = e.response?.data?.message || '가입 실패' }
  finally { submitting.value = false }
}
</script>
