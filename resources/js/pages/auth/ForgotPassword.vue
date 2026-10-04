<template>
<div class="min-h-screen flex items-center justify-center px-4">
  <div class="card p-8 w-full max-w-md">
    <div class="text-center mb-6">
      <div class="w-12 h-12 bg-gradient-to-br from-[#FF8A53] to-[#F2570F] rounded-xl mx-auto mb-3 flex items-center justify-center text-xl font-black text-white shadow-btn">AK</div>
      <h1 class="text-xl font-bold text-ink">비밀번호 찾기</h1>
      <p class="text-sm text-ink-muted mt-1">가입한 이메일을 입력하세요</p>
    </div>

    <!-- Step 1: 이메일 입력 -->
    <div v-if="step === 1" class="space-y-4">
      <div><label class="input-label">이메일</label><input v-model="email" type="email" required autocomplete="email" placeholder="example@email.com" class="input-soft" /></div>
      <div v-if="error" class="text-red-500 text-sm">{{ error }}</div>
      <div v-if="msg" class="text-emerald-600 text-sm">{{ msg }}</div>
      <button @click="sendCode" :disabled="submitting || cooldown > 0" class="btn-primary w-full">{{ submitting ? '전송 중...' : cooldown > 0 ? `다시 보내기 (${cooldown}초)` : '인증 코드 받기' }}</button>
    </div>

    <!-- Step 2: 코드 입력 + 새 비밀번호 -->
    <div v-if="step === 2" class="space-y-4">
      <div class="bg-amber-50 rounded-xl p-3 text-sm text-amber-700">{{ email }} 로 인증 코드가 전송되었습니다. 메일이 안 보이면 스팸함도 확인해주세요.</div>
      <!-- autocomplete="one-time-code" + inputmode="numeric": 브라우저가 저장된 아이디/비번을 이 칸에 잘못 자동입력하던 문제 방지 -->
      <div><label class="input-label">인증 코드 (6자리)</label><input v-model="code" type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" maxlength="6" placeholder="000000" class="input-soft text-center tracking-widest text-lg font-mono" /></div>
      <div><label class="input-label">새 비밀번호</label><input v-model="password" type="password" required minlength="6" autocomplete="new-password" class="input-soft" /></div>
      <div><label class="input-label">비밀번호 확인</label><input v-model="password_confirmation" type="password" required autocomplete="new-password" class="input-soft" /></div>
      <div v-if="error" class="text-red-500 text-sm">{{ error }}</div>
      <button @click="resetPw" :disabled="submitting" class="btn-primary w-full">{{ submitting ? '변경 중...' : '비밀번호 변경' }}</button>
      <button @click="sendCode" :disabled="submitting || cooldown > 0" class="btn-ghost w-full">{{ cooldown > 0 ? `코드 다시 받기 (${cooldown}초)` : '코드 다시 받기' }}</button>
    </div>

    <!-- Step 3: 완료 -->
    <div v-if="step === 3" class="text-center py-6">
      <div class="icon-chip w-14 h-14 bg-emerald-50 text-emerald-500 mx-auto mb-3"><AppIcon name="check" :size="28" /></div>
      <div class="text-lg font-bold text-ink mb-2">비밀번호가 변경되었습니다!</div>
      <RouterLink to="/login" class="btn-primary mt-3">로그인하기</RouterLink>
    </div>

    <div v-if="step !== 3" class="text-center mt-4 text-sm text-ink-light">
      <RouterLink to="/login" class="text-amber-600 font-semibold hover:text-amber-700 transition-colors">로그인으로 돌아가기</RouterLink>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
const step = ref(1)
const email = ref('')
const code = ref('')
const password = ref('')
const password_confirmation = ref('')
const error = ref('')
const msg = ref('')
const submitting = ref(false)
const cooldown = ref(0)
let cooldownTimer = null

// "다시 보내기"를 연타하면 서버 쪽 전송 한도(10분당 8회)를 순식간에 소진해
// 정상 사용자까지 429로 잠기던 문제 — 60초 쿨다운으로 1차 방어.
function startCooldown() {
  cooldown.value = 60
  clearInterval(cooldownTimer)
  cooldownTimer = setInterval(() => {
    cooldown.value -= 1
    if (cooldown.value <= 0) clearInterval(cooldownTimer)
  }, 1000)
}

async function sendCode() {
  if (!email.value) { error.value = '이메일을 입력하세요'; return }
  if (cooldown.value > 0) return
  submitting.value = true; error.value = ''; msg.value = ''
  try {
    await axios.post('/api/forgot-password', { email: email.value })
    step.value = 2
    startCooldown()
  } catch (e) {
    error.value = e.response?.status === 429
      ? '너무 여러 번 요청했습니다. 1분 후 다시 시도해주세요'
      : (e.response?.data?.message || '전송 실패')
  }
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
