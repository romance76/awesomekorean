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
      <div>
        <label class="text-sm font-semibold text-gray-700">비밀번호</label>
        <input v-model="form.password" type="password" required minlength="8"
          class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" />
        <div class="text-[11px] text-gray-400 mt-1">📌 최소 8자, 영문 대소문자 + 숫자 포함</div>
        <div v-if="form.password" class="flex gap-1 mt-1 text-[10px]">
          <span :class="pwChecks.len ? 'text-green-600' : 'text-gray-400'">{{ pwChecks.len ? '✓' : '·' }} 8자</span>
          <span :class="pwChecks.upper ? 'text-green-600' : 'text-gray-400'">{{ pwChecks.upper ? '✓' : '·' }} 대문자</span>
          <span :class="pwChecks.lower ? 'text-green-600' : 'text-gray-400'">{{ pwChecks.lower ? '✓' : '·' }} 소문자</span>
          <span :class="pwChecks.num ? 'text-green-600' : 'text-gray-400'">{{ pwChecks.num ? '✓' : '·' }} 숫자</span>
        </div>
      </div>
      <div><label class="text-sm font-semibold text-gray-700">비밀번호 확인</label><input v-model="form.password_confirmation" type="password" required minlength="8" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
      <div class="flex items-start gap-2 mb-2">
        <div class="flex-1">
          <label class="text-sm font-semibold text-gray-700 mb-1 block">친구 요청 허용</label>
          <div class="flex gap-3">
            <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" v-model="form.allow_friend_request" :value="true" class="text-amber-500" /><span class="text-sm text-gray-600">수락 (다른 사람이 친구 추가 가능)</span></label>
            <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" v-model="form.allow_friend_request" :value="false" class="text-amber-500" /><span class="text-sm text-gray-600">거절</span></label>
          </div>
          <p class="text-[10px] text-gray-400 mt-0.5">나중에 프로필 설정에서 변경할 수 있습니다</p>
        </div>
      </div>
      <div class="flex items-start gap-2">
        <input v-model="agreeTerms" type="checkbox" id="terms" class="mt-1 rounded" />
        <label for="terms" class="text-xs text-gray-500">
          <RouterLink to="/terms" target="_blank" class="text-amber-600 underline">이용약관</RouterLink> 및
          <RouterLink to="/privacy" target="_blank" class="text-amber-600 underline">개인정보처리방침</RouterLink>에 동의합니다 (필수)
        </label>
      </div>
      <div v-if="error" class="text-red-500 text-sm">{{ error }}</div>
      <button type="submit" :disabled="submitting || !agreeTerms" class="w-full bg-amber-400 text-amber-900 font-bold py-2.5 rounded-lg hover:bg-amber-500 transition disabled:opacity-50">{{ submitting ? '가입 중...' : '회원가입' }}</button>
    </form>
    <div class="text-center mt-4 text-sm text-gray-500">이미 계정이 있으신가요? <RouterLink to="/login" class="text-amber-600 font-semibold">로그인</RouterLink></div>
  </div>
</div>
</template>
<script setup>
import { ref, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
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
