<template>
<div class="min-h-screen flex items-center justify-center px-4">
  <div class="card p-8 w-full max-w-md">
    <div class="text-center mb-6">
      <div class="w-12 h-12 bg-gradient-to-br from-[#FF8A53] to-[#F2570F] rounded-xl mx-auto mb-3 flex items-center justify-center text-xl font-black text-white shadow-btn">AK</div>
      <h1 class="text-xl font-bold text-ink">회원가입</h1>
    </div>
    <form @submit.prevent="handleRegister" class="space-y-4">
      <div><label class="input-label">이름</label><input v-model="form.name" type="text" required class="input-soft" /></div>
      <div><label class="input-label">닉네임</label><input v-model="form.nickname" type="text" class="input-soft" /></div>
      <div><label class="input-label">이메일</label><input v-model="form.email" type="email" required class="input-soft" /></div>
      <div>
        <label class="input-label">비밀번호</label>
        <input v-model="form.password" type="password" required minlength="8"
          class="input-soft" />
        <div class="flex items-center gap-1 text-[11px] text-ink-muted mt-1.5"><AppIcon name="info" :size="12" />최소 8자, 영문 대소문자 + 숫자 포함</div>
        <div v-if="form.password" class="flex gap-2 mt-1 text-xs">
          <span :class="pwChecks.len ? 'text-emerald-600 font-semibold' : 'text-ink-faint'">{{ pwChecks.len ? '✓' : '·' }} 8자</span>
          <span :class="pwChecks.upper ? 'text-emerald-600 font-semibold' : 'text-ink-faint'">{{ pwChecks.upper ? '✓' : '·' }} 대문자</span>
          <span :class="pwChecks.lower ? 'text-emerald-600 font-semibold' : 'text-ink-faint'">{{ pwChecks.lower ? '✓' : '·' }} 소문자</span>
          <span :class="pwChecks.num ? 'text-emerald-600 font-semibold' : 'text-ink-faint'">{{ pwChecks.num ? '✓' : '·' }} 숫자</span>
        </div>
      </div>
      <div><label class="input-label">비밀번호 확인</label><input v-model="form.password_confirmation" type="password" required minlength="8" class="input-soft" /></div>
      <div class="flex items-start gap-2 mb-2">
        <div class="flex-1">
          <label class="input-label">친구 요청 허용</label>
          <div class="flex gap-3">
            <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" v-model="form.allow_friend_request" :value="true" class="accent-amber-500" /><span class="text-sm text-ink-light">수락 (다른 사람이 친구 추가 가능)</span></label>
            <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" v-model="form.allow_friend_request" :value="false" class="accent-amber-500" /><span class="text-sm text-ink-light">거절</span></label>
          </div>
          <p class="text-xs text-ink-muted mt-0.5">나중에 프로필 설정에서 변경할 수 있습니다</p>
        </div>
      </div>
      <div class="flex items-start gap-2">
        <input v-model="agreeTerms" type="checkbox" id="terms" class="mt-1 rounded accent-amber-500" />
        <label for="terms" class="text-xs text-ink-light">
          <RouterLink to="/terms" target="_blank" class="text-amber-600 underline hover:text-amber-700 transition-colors">이용약관</RouterLink> 및
          <RouterLink to="/privacy" target="_blank" class="text-amber-600 underline hover:text-amber-700 transition-colors">개인정보처리방침</RouterLink>에 동의합니다 (필수)
        </label>
      </div>
      <div v-if="error" class="text-red-500 text-sm">{{ error }}</div>
      <button type="submit" :disabled="submitting || !agreeTerms" class="btn-primary w-full">{{ submitting ? '가입 중...' : '회원가입' }}</button>
    </form>
    <div class="text-center mt-4 text-sm text-ink-light">이미 계정이 있으신가요? <RouterLink to="/login" class="text-amber-600 font-semibold hover:text-amber-700 transition-colors">로그인</RouterLink></div>
  </div>
</div>
</template>
<script setup>
import { ref, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import AppIcon from '../../components/AppIcon.vue'
const auth = useAuthStore()
const router = useRouter()
const form = reactive({ name: '', nickname: '', email: '', password: '', password_confirmation: '', allow_friend_request: true })
const error = ref('')
const submitting = ref(false)
const agreeTerms = ref(false)
// 비밀번호 복잡도 체크 (Issue #3)
const pwChecks = computed(() => ({
  len: form.password.length >= 8,
  upper: /[A-Z]/.test(form.password),
  lower: /[a-z]/.test(form.password),
  num: /\d/.test(form.password),
}))
async function handleRegister() {
  submitting.value = true; error.value = ''
  try { await auth.register(form); router.push('/') }
  catch (e) { error.value = e.response?.data?.message || '가입 실패' }
  finally { submitting.value = false }
}
</script>
