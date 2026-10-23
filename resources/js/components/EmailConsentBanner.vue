<template>
  <div v-if="show" class="fixed z-40 left-3 right-3 bottom-[84px] lg:bottom-5 sm:left-auto sm:right-5 sm:w-[380px] card shadow-lg p-4 border border-amber-100" role="dialog" :aria-label="mc.dialog">
    <div class="flex items-start gap-2.5">
      <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600 shrink-0"><AppIcon name="mail" :size="18" /></span>
      <div class="min-w-0 flex-1">
        <div class="font-bold text-ink text-[15px] leading-snug">{{ mc.title }}</div>
        <p class="text-[12.5px] text-ink-light mt-1 leading-relaxed">{{ mc.body }}</p>
        <p class="text-[12px] text-ink-muted mt-1">{{ mc.region }}</p>
        <div class="flex flex-wrap gap-2 mt-3">
          <button type="button" class="btn-primary px-4 py-2 text-sm" :disabled="busy" @click="answer(true)">{{ mc.yes }}</button>
          <button type="button" class="btn-secondary px-4 py-2 text-sm" :disabled="busy" @click="answer(false)">{{ mc.no }}</button>
          <button type="button" class="text-xs text-ink-muted underline px-1" :disabled="busy" @click="later">{{ mc.later }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import { useAuthStore } from '../stores/auth'
import AppIcon from './AppIcon.vue'
import { mailConsentText } from '../utils/mailConsent'
import { useLangStore } from '../stores/lang'

// 이미 동의했거나, 한 번이라도 답하거나 "나중에"를 누른 회원에게는 다시 묻지 않는다(서버에 기록).
// 로그인·가입·관리자·전체화면 영상 같은 화면에서는 보여 주지 않는다.
const auth = useAuthStore()
const route = useRoute()
const mc = computed(() => mailConsentText(useLangStore().locale))   // 사이트 언어에 맞는 문구
const ready = ref(false)
const busy = ref(false)
const HIDE = ['/login', '/register', '/forgot', '/admin', '/shorts', '/email-unsubscribed']
const show = computed(() => ready.value && auth.isLoggedIn && auth.user && !auth.user.marketing_email_consent && !auth.user.marketing_consent_asked_at
  && !HIDE.some(p => route.path === p || route.path.startsWith(p + '/')))
onMounted(() => { setTimeout(() => { ready.value = true }, 4000) })   // 들어오자마자 가리지 않게 잠깐 뒤에

async function answer(consent) {
  busy.value = true
  try {
    await axios.put('/api/user/email-consent', { consent, source: 'banner' })
    auth.user.marketing_email_consent = consent
    auth.user.marketing_consent_asked_at = new Date().toISOString()
  } catch {} finally { busy.value = false }
}
async function later() {
  busy.value = true
  try { await axios.post('/api/user/email-consent/later'); auth.user.marketing_consent_asked_at = new Date().toISOString() } catch {} finally { busy.value = false }
}
</script>
