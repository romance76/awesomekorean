<template>
<div class="min-h-screen flex items-center justify-center px-4">
  <div class="card p-8 w-full max-w-md">
    <div class="text-center mb-6">
      <AuthLogo />
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

    <div class="flex items-center gap-3 my-4">
      <div class="flex-1 h-px bg-gray-200"></div>
      <span class="text-xs text-ink-faint">또는</span>
      <div class="flex-1 h-px bg-gray-200"></div>
    </div>
    <div class="space-y-2">
      <a href="/auth/google/redirect" class="flex items-center justify-center gap-2.5 w-full border border-gray-200 rounded-xl py-2.5 text-sm font-semibold text-ink hover:bg-gray-50 transition-colors">
        <svg width="18" height="18" viewBox="0 0 18 18"><path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.9c1.7-1.57 2.7-3.88 2.7-6.62z"/><path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.9-2.26c-.8.54-1.84.86-3.06.86-2.35 0-4.34-1.59-5.05-3.72H.95v2.33A9 9 0 0 0 9 18z"/><path fill="#FBBC05" d="M3.95 10.7a5.4 5.4 0 0 1 0-3.4V4.97H.95a9 9 0 0 0 0 8.06l3-2.33z"/><path fill="#EA4335" d="M9 3.58c1.32 0 2.5.45 3.44 1.35l2.58-2.58C13.46.89 11.43 0 9 0A9 9 0 0 0 .95 4.97l3 2.33C4.66 5.17 6.65 3.58 9 3.58z"/></svg>
        Google로 계속하기
      </a>
      <a href="/auth/amazon/redirect" class="flex items-center justify-center gap-2.5 w-full border border-gray-200 rounded-xl py-2.5 text-sm font-semibold text-ink hover:bg-gray-50 transition-colors">
        <span class="text-base">🛒</span>
        Amazon으로 계속하기
      </a>
    </div>

    <div class="text-center mt-3"><RouterLink to="/forgot-password" class="text-sm text-ink-muted hover:text-amber-600 transition-colors">비밀번호를 잊으셨나요?</RouterLink></div>
    <div class="text-center mt-2 text-sm text-ink-light">계정이 없으신가요? <RouterLink to="/register" class="text-amber-600 font-semibold hover:text-amber-700 transition-colors">회원가입</RouterLink></div>
  </div>
</div>
</template>
<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import AuthLogo from '../../components/AuthLogo.vue'
const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
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
const socialErrorMessages = {
  no_email: 'Amazon 계정에 이메일 공유를 허용해야 로그인할 수 있습니다.',
  banned: '정지된 계정입니다.',
  1: '소셜 로그인에 실패했습니다. 다시 시도해주세요.',
}
onMounted(() => {
  const code = route.query.social_error
  if (code) error.value = socialErrorMessages[code] || socialErrorMessages[1]
})
</script>
