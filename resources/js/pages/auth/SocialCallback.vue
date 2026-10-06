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
  const token = route.query.token
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
