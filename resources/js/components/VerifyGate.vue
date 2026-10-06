<template>
  <div v-if="active && auth.needsVerification" class="flex-1 w-full rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 flex items-center justify-between gap-2 flex-wrap">
    <span class="text-xs text-amber-800 flex items-center gap-1.5">
      <AppIcon name="alert-circle" :size="14" />{{ message }}
    </span>
    <button @click="resend" :disabled="sending || sent"
      class="text-xs font-bold bg-amber-400 text-white px-3 py-1.5 rounded-lg hover:bg-amber-500 disabled:opacity-60 transition-colors">
      {{ sent ? '발송됨 — 메일함을 확인하세요' : (sending ? '발송중...' : '인증 메일 재발송') }}
    </button>
  </div>
  <slot v-else />
</template>

<script setup>
// 이메일 미인증 회원에게는 작성 폼 대신 안내 + 인증 메일 재발송 버튼을 보여줌.
// 서버(EnsureEmailVerified)가 어차피 제출을 막으므로, 다 써놓고 제출 단계에서
// 실패하는 대신 처음부터 작성할 수 없다는 걸 알려주기 위한 것.
import { ref } from 'vue'
import axios from 'axios'
import { useAuthStore } from '../stores/auth'
import { useSiteStore } from '../stores/site'
import AppIcon from './AppIcon.vue'

defineProps({
  message: { type: String, default: '이메일 인증 후 작성할 수 있어요.' },
  // false면 항상 slot을 그대로 보여줌 (예: 공개방에서만 제한하는 채팅 입력창)
  active: { type: Boolean, default: true },
})

const auth = useAuthStore()
const siteStore = useSiteStore()
const sending = ref(false)
const sent = ref(false)

async function resend() {
  sending.value = true
  try {
    const { data } = await axios.post('/api/auth/resend-verification')
    sent.value = true
    siteStore.toast(data.message || '인증 메일을 다시 보냈습니다.', 'success')
  } catch (e) {
    siteStore.toast(e.response?.data?.message || '발송 실패', 'error')
  }
  sending.value = false
}
</script>
