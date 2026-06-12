<template>
<div class="min-h-screen flex items-center justify-center px-4">
  <div class="card p-8 w-full max-w-md">
    <div class="text-center mb-6">
      <div class="w-12 h-12 bg-gradient-to-br from-[#FF8A53] to-[#F2570F] rounded-xl mx-auto mb-3 flex items-center justify-center text-xl font-black text-white shadow-btn">AK</div>
      <h1 class="text-xl font-bold text-ink">로그인</h1>
    </div>
    <form @submit.prevent="handleLogin" class="space-y-4">
      <div><label class="input-label">이메일</label><input v-model="form.email" type="email" required class="input-soft" /></div>
      <div><label class="input-label">비밀번호</label><input v-model="form.password" type="password" required class="input-soft" /></div>
      <!-- Issue #4: 로그인 유지 토글 -->
      <label class="flex items-center gap-2 text-sm text-ink-light cursor-pointer select-none">
        <input v-model="remember" type="checkbox" class="accent-amber-500 w-4 h-4" />
        <span>로그인 유지</span>
        <span class="text-[11px] text-ink-muted ml-1">(공용 기기는 해제)</span>
      </label>
      <div v-if="error" class="text-red-500 text-sm">{{ error }}</div>
      <button type="submit" :disabled="submitting" class="btn-primary w-full">{{ submitting ? '로그인 중...' : '로그인' }}</button>
    </form>
    <div class="text-center mt-3"><RouterLink to="/forgot-password" class="text-sm text-ink-muted hover:text-amber-600 transition-colors">비밀번호를 잊으셨나요?</RouterLink></div>
    <div class="text-center mt-2 text-sm text-ink-light">계정이 없으신가요? <RouterLink to="/register" class="text-amber-600 font-semibold hover:text-amber-700 transition-colors">회원가입</RouterLink></div>
  </div>
</div>
</template>
<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
const auth = useAuthStore()
const router = useRouter()
const form = reactive({ email: '', password: '' })
const error = ref('')
const submitting = ref(false)
// Issue #4: 이전 선택 복원 (기본 true)
const remember = ref(localStorage.getItem('sk_auth_persist') !== '0')
async function handleLogin() {
  submitting.value = true; error.value = ''
  try { await auth.login(form.email, form.password, remember.value); router.push('/') }
  catch (e) { error.value = e.response?.data?.message || '로그인 실패' }
  finally { submitting.value = false }
}
</script>
