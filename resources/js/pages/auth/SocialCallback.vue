<template>
<div class="min-h-screen flex items-center justify-center px-4">
  <div class="text-center">
    <div v-if="!error" class="text-ink-muted text-sm">로그인 처리 중...</div>
    <div v-else class="text-red-500 text-sm">{{ error }}</div>
  </div>
</div>
</template>
<script setup>
import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

onMounted(async () => {
  // 토큰은 # 뒤로 온다 (예전 방식 ?token= 도 호환). 읽자마자 주소창에서 지워 기록에 남지 않게 한다
  const fromHash = new URLSearchParams((window.location.hash || '').replace(/^#/, '')).get('token')
  const token = fromHash || route.query.token
  try { history.replaceState(null, '', window.location.pathname) } catch {}
  if (!token) {
    router.replace('/login?social_error=1')
    return
  }
  try {
    await auth.loginWithToken(token)
    router.replace('/')
  } catch {
    router.replace('/login?social_error=1')
  }
})
</script>
