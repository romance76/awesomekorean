<template>
  <!-- /admin/accept-invitation?token=xxx (Phase 2-C Post) -->
  <div class="min-h-screen flex items-center justify-center bg-gray-50 p-4">
    <div class="max-w-md w-full bg-white rounded-xl shadow-lg p-6">
      <div class="text-center mb-6">
        <div class="w-16 h-16 bg-gradient-to-br from-amber-400 to-orange-400 rounded-full flex items-center justify-center text-3xl mx-auto mb-3">👑</div>
        <h1 class="text-2xl font-bold">관리자 초대 수락</h1>
        <p class="text-sm text-gray-500 mt-1">SomeKorean 관리자 계정 생성</p>
      </div>

      <div v-if="!token" class="text-center text-red-500">잘못된 접근입니다. 초대 링크를 다시 확인하세요.</div>

      <div v-else class="space-y-3">
        <label class="block">
          <span class="text-xs text-gray-500">이름</span>
          <input v-model="form.name" class="w-full border rounded px-3 py-2 mt-1 text-sm" />
        </label>
        <label class="block">
          <span class="text-xs text-gray-500">닉네임 (선택)</span>
          <input v-model="form.nickname" class="w-full border rounded px-3 py-2 mt-1 text-sm" />
        </label>
        <label class="block">
          <span class="text-xs text-gray-500">비밀번호 (8자 이상)</span>
          <input v-model="form.password" type="password" class="w-full border rounded px-3 py-2 mt-1 text-sm" />
        </label>
        <label class="block">
          <span class="text-xs text-gray-500">비밀번호 확인</span>
          <input v-model="form.password_confirmation" type="password" class="w-full border rounded px-3 py-2 mt-1 text-sm" />
        </label>

        <button @click="submit" :disabled="submitting || !canSubmit" class="w-full py-3 bg-amber-400 hover:bg-amber-500 text-white rounded-lg font-semibold disabled:opacity-50">
          {{ submitting ? '처리 중...' : '✅ 관리자 계정 생성' }}
        </button>

        <p v-if="error" class="text-sm text-red-500 text-center">{{ error }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import { useAuthStore } from '../../stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const token = computed(() => route.query.token)
const submitting = ref(false)
const error = ref('')
const form = reactive({ name: '', nickname: '', password: '', password_confirmation: '' })
const canSubmit = computed(() => form.name && form.password.length >= 8 && form.password === form.password_confirmation)

async function submit() {
  if (!canSubmit.value) { error.value = '모든 필드를 올바르게 입력하세요.'; return }
  submitting.value = true
  error.value = ''
  try {
    const { data } = await axios.post('/api/admin-invitations/accept', { token: token.value, ...form })
    if (data?.data?.token) {
      localStorage.setItem('sk_token', data.data.token)
      localStorage.setItem('sk_user', JSON.stringify(data.data.user))
      router.push('/admin/v2/dashboard')
    }
  } catch (e) {
    error.value = e.response?.data?.message || '처리 실패'
  } finally {
    submitting.value = false
  }
}
</script>
