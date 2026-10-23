<template>
<div class="min-h-screen flex items-center justify-center px-4">
  <div class="card p-8 w-full max-w-md">
    <div class="text-center mb-6">
      <AuthLogo />
      <h1 class="text-xl font-bold text-ink">회원가입</h1>
    </div>

    <!-- 관리자 사이트 설정 "회원가입 허용"이 꺼져 있으면 가입 화면 대신 안내 (이미 가입한 분은 로그인 가능) -->
    <div v-if="!site.signupOpen" class="text-center py-4" role="status">
      <div class="icon-chip w-14 h-14 bg-amber-50 text-amber-600 mx-auto mb-3"><AppIcon name="info" :size="28" /></div>
      <p class="text-base font-semibold text-ink mb-1">지금은 가입을 받지 않아요</p>
      <p class="text-sm text-ink-muted leading-relaxed">나중에 다시 와 주세요.<br />이미 가입하셨다면 로그인해서 이용하실 수 있어요.</p>
      <RouterLink to="/login" class="btn-primary inline-block mt-5 px-8">로그인하기</RouterLink>
    </div>

    <template v-else>
    <div class="space-y-2 mb-4">
      <a href="/auth/google/redirect" class="flex items-center justify-center gap-2.5 w-full border border-gray-200 rounded-xl py-2.5 text-sm font-semibold text-ink hover:bg-gray-50 transition-colors">
        <svg width="18" height="18" viewBox="0 0 18 18"><path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.9c1.7-1.57 2.7-3.88 2.7-6.62z"/><path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.9-2.26c-.8.54-1.84.86-3.06.86-2.35 0-4.34-1.59-5.05-3.72H.95v2.33A9 9 0 0 0 9 18z"/><path fill="#FBBC05" d="M3.95 10.7a5.4 5.4 0 0 1 0-3.4V4.97H.95a9 9 0 0 0 0 8.06l3-2.33z"/><path fill="#EA4335" d="M9 3.58c1.32 0 2.5.45 3.44 1.35l2.58-2.58C13.46.89 11.43 0 9 0A9 9 0 0 0 .95 4.97l3 2.33C4.66 5.17 6.65 3.58 9 3.58z"/></svg>
        Google로 가입하기
      </a>
      <a href="/auth/amazon/redirect" class="flex items-center justify-center gap-2.5 w-full border border-gray-200 rounded-xl py-2.5 text-sm font-semibold text-ink hover:bg-gray-50 transition-colors">
        <span class="text-base">🛒</span>
        Amazon으로 가입하기
      </a>
    </div>
    <div class="flex items-center gap-3 mb-4">
      <div class="flex-1 h-px bg-gray-200"></div>
      <span class="text-xs text-ink-faint">또는 이메일로 가입</span>
      <div class="flex-1 h-px bg-gray-200"></div>
    </div>

    <form @submit.prevent="handleRegister" class="space-y-4">
      <div><label class="input-label">이름</label><input v-model="form.name" type="text" required class="input-soft" /></div>
      <div><label class="input-label">닉네임</label><input v-model="form.nickname" type="text" class="input-soft" /></div>
      <div><label class="input-label">이메일</label><input v-model="form.email" type="email" required class="input-soft" /></div>
      <div>
        <label class="input-label">비밀번호</label>
        <PasswordInput v-model="form.password" required :minlength="site.minPasswordLength"
          class="input-soft" />
        <div class="flex items-center gap-1 text-[11px] text-ink-muted mt-1.5"><AppIcon name="info" :size="12" />최소 {{ site.minPasswordLength }}자, 영문 대소문자 + 숫자 포함</div>
        <div v-if="form.password" class="flex gap-2 mt-1 text-xs">
          <span :class="pwChecks.len ? 'text-emerald-600 font-semibold' : 'text-ink-faint'">{{ pwChecks.len ? '✓' : '·' }} {{ site.minPasswordLength }}자</span>
          <span :class="pwChecks.upper ? 'text-emerald-600 font-semibold' : 'text-ink-faint'">{{ pwChecks.upper ? '✓' : '·' }} 대문자</span>
          <span :class="pwChecks.lower ? 'text-emerald-600 font-semibold' : 'text-ink-faint'">{{ pwChecks.lower ? '✓' : '·' }} 소문자</span>
          <span :class="pwChecks.num ? 'text-emerald-600 font-semibold' : 'text-ink-faint'">{{ pwChecks.num ? '✓' : '·' }} 숫자</span>
        </div>
      </div>
      <div><label class="input-label">비밀번호 확인</label><PasswordInput v-model="form.password_confirmation" required :minlength="site.minPasswordLength" class="input-soft" /></div>
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
      <div class="flex items-start gap-2">
        <input v-model="form.marketing_consent" type="checkbox" id="mailconsent" class="mt-1 rounded accent-amber-500" />
        <label for="mailconsent" class="text-xs text-ink-light">
          <b class="text-ink">{{ mc.optional }}</b> {{ mc.title }}
          <span class="block text-ink-muted mt-0.5">{{ mc.body }}</span>
        </label>
      </div>
      <div v-if="error" class="text-red-500 text-sm">{{ error }}</div>
      <button type="submit" :disabled="submitting || !agreeTerms" class="btn-primary w-full">{{ submitting ? '가입 중...' : '회원가입' }}</button>
    </form>
    </template>
    <div class="text-center mt-4 text-sm text-ink-light">이미 계정이 있으신가요? <RouterLink to="/login" class="text-amber-600 font-semibold hover:text-amber-700 transition-colors">로그인</RouterLink></div>
  </div>
</div>
</template>
<script setup>
import { mailConsentText } from '../../utils/mailConsent'
import { useLangStore } from '../../stores/lang'
import PasswordInput from '../../components/PasswordInput.vue'
import { ref, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import AppIcon from '../../components/AppIcon.vue'
import AuthLogo from '../../components/AuthLogo.vue'
import { useSiteStore } from '../../stores/site'
const auth = useAuthStore()
const site = useSiteStore()
site.load()   // 가입 허용 여부·비밀번호 최소 길이 같은 공개 설정 (이미 불러왔으면 그대로 사용)
const router = useRouter()
const form = reactive({ name: '', nickname: '', email: '', password: '', password_confirmation: '', allow_friend_request: true, marketing_consent: false })
const langStore = useLangStore()
const mc = computed(() => mailConsentText(langStore.locale))   // 사이트 언어(영어/한국어)에 맞는 수신 동의 문구
const error = ref('')
const submitting = ref(false)
const agreeTerms = ref(false)
// 비밀번호 복잡도 체크 (Issue #3)
const pwChecks = computed(() => ({
  len: form.password.length >= site.minPasswordLength,
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
